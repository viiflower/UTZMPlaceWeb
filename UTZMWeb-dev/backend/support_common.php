<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

function support_json(array $payload, int $status = 200): void
{
  http_response_code($status);
  echo json_encode($payload);
  exit;
}

function support_read_json(): array
{
  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') {
    return [];
  }

  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function support_normalize_user(array $user, string $defaultAvatar): array
{
  return [
    'id' => isset($user['id']) ? (int)$user['id'] : 0,
    'full_name' => $user['full_name'] ?? '',
    'email' => $user['email'] ?? '',
    'avatar_url' => $user['avatar_url'] ?: $defaultAvatar,
    'role' => $user['role'] ?? 'customer',
  ];
}

function support_require_user(PDO $pdo): array
{
  if (!isset($_SESSION['user']['id'])) {
    support_json(['error' => 'No autorizado'], 401);
  }

  $defaultAvatar = '/public/uploads/blank-profile.png';
  $stmt = $pdo->prepare('SELECT id, full_name, email, avatar_url, role FROM users WHERE id = ? LIMIT 1');
  $stmt->execute([(int)$_SESSION['user']['id']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    unset($_SESSION['user']);
    support_json(['error' => 'La sesión ya no es válida'], 401);
  }

  $normalized = support_normalize_user($user, $defaultAvatar);
  $_SESSION['user'] = $normalized;
  return $normalized;
}

function support_is_staff(array $user): bool
{
  return in_array($user['role'] ?? 'customer', ['support', 'admin'], true);
}

function support_is_admin(array $user): bool
{
  return ($user['role'] ?? 'customer') === 'admin';
}

function support_require_staff(array $user): void
{
  if (!support_is_staff($user)) {
    support_json(['error' => 'Acceso restringido al equipo de soporte'], 403);
  }
}

function support_require_admin(array $user): void
{
  if (!support_is_admin($user)) {
    support_json(['error' => 'Acceso restringido a administradores'], 403);
  }
}

function support_fetch_ticket(PDO $pdo, int $ticketId): ?array
{
  $stmt = $pdo->prepare(
    "SELECT
      t.id,
      t.user_id,
      t.assigned_to,
      t.order_id,
      t.category,
      t.subject,
      t.description,
      t.status,
      t.priority,
      t.created_at,
      t.updated_at,
      t.last_message_at,
      requester.full_name AS requester_name,
      requester.email AS requester_email,
      requester.role AS requester_role,
      assignee.full_name AS assignee_name,
      assignee.email AS assignee_email
    FROM support_tickets t
    JOIN users requester ON requester.id = t.user_id
    LEFT JOIN users assignee ON assignee.id = t.assigned_to
    WHERE t.id = ?
    LIMIT 1"
  );
  $stmt->execute([$ticketId]);
  $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$ticket) {
    return null;
  }

  return [
    'id' => (int)$ticket['id'],
    'user_id' => (int)$ticket['user_id'],
    'assigned_to' => isset($ticket['assigned_to']) ? (int)$ticket['assigned_to'] : null,
    'order_id' => isset($ticket['order_id']) ? (int)$ticket['order_id'] : null,
    'category' => $ticket['category'],
    'subject' => $ticket['subject'],
    'description' => $ticket['description'],
    'status' => $ticket['status'],
    'priority' => $ticket['priority'],
    'created_at' => $ticket['created_at'],
    'updated_at' => $ticket['updated_at'],
    'last_message_at' => $ticket['last_message_at'],
    'requester_name' => $ticket['requester_name'],
    'requester_email' => $ticket['requester_email'],
    'requester_role' => $ticket['requester_role'],
    'assignee_name' => $ticket['assignee_name'],
    'assignee_email' => $ticket['assignee_email'],
  ];
}

function support_assert_ticket_access(array $ticket, array $user): void
{
  if (support_is_staff($user)) {
    return;
  }

  if ((int)$ticket['user_id'] !== (int)$user['id']) {
    support_json(['error' => 'No tienes acceso a este ticket'], 403);
  }
}

function support_validate_status(string $status): bool
{
  return in_array($status, ['open', 'in_progress', 'waiting_user', 'resolved', 'closed'], true);
}

function support_validate_priority(string $priority): bool
{
  return in_array($priority, ['low', 'normal', 'high', 'urgent'], true);
}
