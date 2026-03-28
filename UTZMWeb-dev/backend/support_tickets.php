<?php
require __DIR__ . '/support_common.php';

$user = support_require_user($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $where = [];
  $params = [];
  $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 60;

  if (!support_is_staff($user)) {
    $where[] = 't.user_id = :uid';
    $params[':uid'] = (int)$user['id'];
  }

  $status = strtolower(trim((string)($_GET['status'] ?? 'all')));
  if ($status !== '' && $status !== 'all') {
    if (!support_validate_status($status)) {
      support_json(['error' => 'Estado de ticket no válido'], 422);
    }
    $where[] = 't.status = :status';
    $params[':status'] = $status;
  }

  if (support_is_staff($user)) {
    $assignment = strtolower(trim((string)($_GET['assignment'] ?? 'all')));
    if ($assignment === 'mine') {
      $where[] = 't.assigned_to = :assigned_uid';
      $params[':assigned_uid'] = (int)$user['id'];
    } elseif ($assignment === 'unassigned') {
      $where[] = 't.assigned_to IS NULL';
    }
  }

  $query = trim((string)($_GET['q'] ?? ''));
  if ($query !== '') {
    $where[] = '(t.subject ILIKE :q OR t.category ILIKE :q OR requester.full_name ILIKE :q OR requester.email ILIKE :q)';
    $params[':q'] = '%' . $query . '%';
  }

  $sql = "
    SELECT
      t.id,
      t.user_id,
      t.assigned_to,
      t.order_id,
      t.category,
      t.subject,
      t.status,
      t.priority,
      t.created_at,
      t.updated_at,
      t.last_message_at,
      requester.full_name AS requester_name,
      requester.email AS requester_email,
      assignee.full_name AS assignee_name
    FROM support_tickets t
    JOIN users requester ON requester.id = t.user_id
    LEFT JOIN users assignee ON assignee.id = t.assigned_to
  ";

  if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
  }

  $sql .= "
    ORDER BY
      CASE t.status
        WHEN 'open' THEN 0
        WHEN 'in_progress' THEN 1
        WHEN 'waiting_user' THEN 2
        WHEN 'resolved' THEN 3
        ELSE 4
      END,
      t.last_message_at DESC
    LIMIT {$limit}
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $tickets = array_map(static function (array $row): array {
    return [
      'id' => (int)$row['id'],
      'user_id' => (int)$row['user_id'],
      'assigned_to' => isset($row['assigned_to']) ? (int)$row['assigned_to'] : null,
      'order_id' => isset($row['order_id']) ? (int)$row['order_id'] : null,
      'category' => $row['category'],
      'subject' => $row['subject'],
      'status' => $row['status'],
      'priority' => $row['priority'],
      'created_at' => $row['created_at'],
      'updated_at' => $row['updated_at'],
      'last_message_at' => $row['last_message_at'],
      'requester_name' => $row['requester_name'],
      'requester_email' => $row['requester_email'],
      'assignee_name' => $row['assignee_name'],
    ];
  }, $stmt->fetchAll(PDO::FETCH_ASSOC));

  support_json(['tickets' => $tickets]);
}

if ($method === 'POST') {
  $payload = support_read_json();
  if (support_is_staff($user)) {
    support_json(['error' => 'El equipo de soporte no puede crear tickets desde esta vista'], 403);
  }

  $subject = trim((string)($payload['subject'] ?? ''));
  $description = trim((string)($payload['description'] ?? ''));
  $category = trim((string)($payload['category'] ?? ''));
  $orderId = isset($payload['order_id']) && (int)$payload['order_id'] > 0 ? (int)$payload['order_id'] : null;

  if ($subject === '' || mb_strlen($subject) < 6) {
    support_json(['error' => 'El asunto debe tener al menos 6 caracteres'], 422);
  }

  if ($description === '' || mb_strlen($description) < 12) {
    support_json(['error' => 'Describe el problema con mayor detalle'], 422);
  }

  if ($category === '') {
    support_json(['error' => 'Selecciona una categoría'], 422);
  }

  if ($orderId !== null) {
    $orderStmt = $pdo->prepare('SELECT id, buyer_id, seller_id FROM orders WHERE id = ? LIMIT 1');
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
      support_json(['error' => 'El pedido asociado no existe'], 404);
    }

    $isOwner = (int)$order['buyer_id'] === (int)$user['id'] || (int)$order['seller_id'] === (int)$user['id'];
    if (!support_is_staff($user) && !$isOwner) {
      support_json(['error' => 'No puedes asociar un pedido ajeno'], 403);
    }
  }

  try {
    $pdo->beginTransaction();

    $ticketStmt = $pdo->prepare(
      "INSERT INTO support_tickets (user_id, order_id, category, subject, description, status, priority, updated_at, last_message_at)
       VALUES (:user_id, :order_id, :category, :subject, :description, 'open', 'normal', NOW(), NOW())
       RETURNING id"
    );
    $ticketStmt->execute([
      ':user_id' => (int)$user['id'],
      ':order_id' => $orderId,
      ':category' => $category,
      ':subject' => $subject,
      ':description' => $description,
    ]);

    $ticketId = (int)$ticketStmt->fetchColumn();

    $messageStmt = $pdo->prepare(
      'INSERT INTO support_ticket_messages (ticket_id, sender_id, body, is_internal) VALUES (?, ?, ?, false)'
    );
    $messageStmt->execute([$ticketId, (int)$user['id'], $description]);

    $pdo->commit();
    support_json(['success' => true, 'ticket_id' => $ticketId], 201);
  } catch (Throwable $error) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }

    support_json(['error' => 'No se pudo crear el ticket'], 500);
  }
}

support_json(['error' => 'Método no permitido'], 405);
