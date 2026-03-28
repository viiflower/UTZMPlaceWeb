<?php
require __DIR__ . '/support_common.php';

$user = support_require_user($pdo);
support_require_staff($user);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  support_json(['error' => 'Método no permitido'], 405);
}

$payload = support_read_json();
$ticketId = isset($payload['ticket_id']) ? (int)$payload['ticket_id'] : 0;

if ($ticketId <= 0) {
  support_json(['error' => 'Ticket no válido'], 422);
}

$ticket = support_fetch_ticket($pdo, $ticketId);
if (!$ticket) {
  support_json(['error' => 'Ticket no encontrado'], 404);
}

$sets = [];
$params = [':id' => $ticketId];

if (array_key_exists('status', $payload)) {
  $status = strtolower(trim((string)$payload['status']));
  if (!support_validate_status($status)) {
    support_json(['error' => 'Estado no válido'], 422);
  }
  $sets[] = 'status = :status';
  $params[':status'] = $status;
}

if (array_key_exists('priority', $payload)) {
  $priority = strtolower(trim((string)$payload['priority']));
  if (!support_validate_priority($priority)) {
    support_json(['error' => 'Prioridad no válida'], 422);
  }
  $sets[] = 'priority = :priority';
  $params[':priority'] = $priority;
}

if (array_key_exists('assigned_to', $payload)) {
  $assignedTo = $payload['assigned_to'];

  if ($assignedTo === null || $assignedTo === '' || (int)$assignedTo === 0) {
    $sets[] = 'assigned_to = NULL';
  } else {
    $agentStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role IN ('support', 'admin') LIMIT 1");
    $agentStmt->execute([(int)$assignedTo]);
    $agent = $agentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$agent) {
      support_json(['error' => 'El usuario asignado no pertenece al equipo de soporte'], 422);
    }

    $sets[] = 'assigned_to = :assigned_to';
    $params[':assigned_to'] = (int)$assignedTo;
  }
}

if (!$sets) {
  support_json(['error' => 'No hay cambios por aplicar'], 422);
}

$sets[] = 'updated_at = NOW()';

$stmt = $pdo->prepare(
  'UPDATE support_tickets SET ' . implode(', ', $sets) . ' WHERE id = :id'
);
$stmt->execute($params);

$updatedTicket = support_fetch_ticket($pdo, $ticketId);
support_json(['success' => true, 'ticket' => $updatedTicket]);
