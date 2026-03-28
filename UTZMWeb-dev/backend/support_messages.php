<?php
require __DIR__ . '/support_common.php';

$user = support_require_user($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
  if ($ticketId <= 0) {
    support_json(['error' => 'Ticket no válido'], 422);
  }

  $ticket = support_fetch_ticket($pdo, $ticketId);
  if (!$ticket) {
    support_json(['error' => 'Ticket no encontrado'], 404);
  }

  support_assert_ticket_access($ticket, $user);

  $sql = "
    SELECT
      m.id,
      m.ticket_id,
      m.sender_id,
      m.body,
      m.is_internal,
      m.created_at,
      u.full_name AS sender_name,
      u.email AS sender_email,
      u.role AS sender_role
    FROM support_ticket_messages m
    JOIN users u ON u.id = m.sender_id
    WHERE m.ticket_id = :ticket_id
  ";

  if (!support_is_staff($user)) {
    $sql .= ' AND m.is_internal = false';
  }

  $sql .= ' ORDER BY m.created_at ASC, m.id ASC';

  $stmt = $pdo->prepare($sql);
  $stmt->execute([':ticket_id' => $ticketId]);
  $messages = array_map(static function (array $row): array {
    return [
      'id' => (int)$row['id'],
      'ticket_id' => (int)$row['ticket_id'],
      'sender_id' => (int)$row['sender_id'],
      'body' => $row['body'],
      'is_internal' => filter_var($row['is_internal'], FILTER_VALIDATE_BOOLEAN),
      'created_at' => $row['created_at'],
      'sender_name' => $row['sender_name'],
      'sender_email' => $row['sender_email'],
      'sender_role' => $row['sender_role'],
    ];
  }, $stmt->fetchAll(PDO::FETCH_ASSOC));

  support_json(['ticket' => $ticket, 'messages' => $messages]);
}

if ($method === 'POST') {
  $payload = support_read_json();
  $ticketId = isset($payload['ticket_id']) ? (int)$payload['ticket_id'] : 0;
  $body = trim((string)($payload['body'] ?? ''));
  $isInternal = !empty($payload['is_internal']);

  if ($ticketId <= 0) {
    support_json(['error' => 'Ticket no válido'], 422);
  }

  if ($body === '') {
    support_json(['error' => 'Escribe un mensaje'], 422);
  }

  if ($isInternal && !support_is_staff($user)) {
    support_json(['error' => 'No puedes enviar notas internas'], 403);
  }

  $ticket = support_fetch_ticket($pdo, $ticketId);
  if (!$ticket) {
    support_json(['error' => 'Ticket no encontrado'], 404);
  }

  support_assert_ticket_access($ticket, $user);

  $nextStatus = $ticket['status'];
  if (support_is_staff($user)) {
    if (in_array($ticket['status'], ['open', 'waiting_user'], true)) {
      $nextStatus = 'in_progress';
    }
  } elseif (in_array($ticket['status'], ['waiting_user', 'resolved', 'closed'], true)) {
    $nextStatus = 'open';
  }

  try {
    $pdo->beginTransaction();

    $msgStmt = $pdo->prepare(
      'INSERT INTO support_ticket_messages (ticket_id, sender_id, body, is_internal) VALUES (?, ?, ?, ?) RETURNING id, created_at'
    );
    $msgStmt->execute([$ticketId, (int)$user['id'], $body, $isInternal ? 't' : 'f']);
    $messageRow = $msgStmt->fetch(PDO::FETCH_ASSOC);

    $ticketStmt = $pdo->prepare(
      'UPDATE support_tickets SET status = ?, updated_at = NOW(), last_message_at = NOW() WHERE id = ?'
    );
    $ticketStmt->execute([$nextStatus, $ticketId]);

    $pdo->commit();

    support_json([
      'success' => true,
      'message' => [
        'id' => (int)$messageRow['id'],
        'ticket_id' => $ticketId,
        'sender_id' => (int)$user['id'],
        'body' => $body,
        'is_internal' => $isInternal,
        'created_at' => $messageRow['created_at'],
        'sender_name' => $user['full_name'],
        'sender_email' => $user['email'],
        'sender_role' => $user['role'],
      ],
      'status' => $nextStatus,
    ], 201);
  } catch (Throwable $error) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }

    support_json(['error' => 'No se pudo guardar el mensaje'], 500);
  }
}

support_json(['error' => 'Método no permitido'], 405);
