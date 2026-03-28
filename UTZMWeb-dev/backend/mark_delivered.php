<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Método no permitido']);
  exit;
}

if (!isset($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['error' => 'No autenticado']);
  exit;
}

$user_id = (int)($_SESSION['user']['id'] ?? 0);
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

if ($order_id <= 0) {
  http_response_code(422);
  echo json_encode(['error' => 'Pedido inválido']);
  exit;
}

$stmt = $pdo->prepare("SELECT id, seller_id, buyer_id, status FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  http_response_code(404);
  echo json_encode(['error' => 'Pedido no encontrado']);
  exit;
}

if ((int)$order['seller_id'] !== $user_id) {
  http_response_code(403);
  echo json_encode(['error' => 'Solo el vendedor puede marcar la entrega']);
  exit;
}

if (($order['status'] ?? '') === 'delivered') {
  http_response_code(422);
  echo json_encode(['error' => 'El pedido ya está entregado']);
  exit;
}

if (($order['status'] ?? '') === 'cancelled') {
  http_response_code(422);
  echo json_encode(['error' => 'No puedes marcar como entregado un pedido cancelado']);
  exit;
}

// Evidencia opcional
$attachmentPath = null;
$attachmentMime = null;
$attachmentSize = null;
$hasFile = isset($_FILES['evidence']) && $_FILES['evidence']['error'] !== UPLOAD_ERR_NO_FILE;

if ($hasFile) {
  $file = $_FILES['evidence'];
  if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['error' => 'No se pudo subir la evidencia']);
    exit;
  }
  if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['error' => 'La imagen debe pesar menos de 5 MB']);
    exit;
  }
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $detectedMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
  if ($finfo) {
    finfo_close($finfo);
  }
  $allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
  ];
  $ext = $allowed[$detectedMime] ?? null;
  if (!$ext) {
    http_response_code(422);
    echo json_encode(['error' => 'Formato de imagen no permitido']);
    exit;
  }
  $baseUploads = realpath(__DIR__ . '/../public/uploads') ?: (__DIR__ . '/../public/uploads');
  $targetDir = $baseUploads . DIRECTORY_SEPARATOR . 'deliveries';
  if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo preparar el directorio de cargas']);
    exit;
  }
  $filename = sprintf('delivery_%s_%s.%s', date('YmdHis'), bin2hex(random_bytes(4)), $ext);
  $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;
  if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar la evidencia']);
    exit;
  }
  $attachmentPath = 'uploads/deliveries/' . $filename;
  $attachmentMime = $detectedMime;
  $attachmentSize = (int)$file['size'];
}

$pdo->beginTransaction();

$pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?")->execute([$order_id]);

// Registrar mensaje en el chat si existe la tabla order_messages
$insertedMessage = null;
try {
  $hasTableStmt = $pdo->query("SELECT to_regclass('public.order_messages') IS NOT NULL");
  $hasTable = (bool)$hasTableStmt->fetchColumn();
  if ($hasTable) {
    $body = 'Pedido marcado como entregado';
    if ($notes !== '') {
      $body .= ": {$notes}";
    }
    $stmtMsg = $pdo->prepare("
      INSERT INTO order_messages (order_id, sender_id, body, attachment_path, attachment_mime, attachment_size)
      VALUES (:order_id, :sender_id, :body, :attachment_path, :attachment_mime, :attachment_size)
      RETURNING id, created_at
    ");
    $stmtMsg->execute([
      ':order_id' => $order_id,
      ':sender_id' => $user_id,
      ':body' => $body,
      ':attachment_path' => $attachmentPath,
      ':attachment_mime' => $attachmentMime,
      ':attachment_size' => $attachmentSize,
    ]);
    $row = $stmtMsg->fetch(PDO::FETCH_ASSOC);
    $insertedMessage = [
      'id' => (int)$row['id'],
      'order_id' => $order_id,
      'sender_id' => $user_id,
      'sender_name' => $_SESSION['user']['full_name'] ?? $_SESSION['user']['email'] ?? 'Vendedor',
      'body' => $body,
      'created_at' => $row['created_at'],
      'is_mine' => true,
      'attachment_url' => $attachmentPath ? '/' . ltrim($attachmentPath, '/') : null,
      'attachment_mime' => $attachmentMime,
      'attachment_size' => $attachmentSize,
    ];
  }
} catch (Exception $e) {
  // si falla el log de mensaje, continuamos con el cambio de estado
}

$pdo->commit();

echo json_encode([
  'message' => 'Pedido marcado como entregado',
  'status' => 'delivered',
  'attachment_url' => $attachmentPath ? '/' . ltrim($attachmentPath, '/') : null,
  'chat_message' => $insertedMessage,
]);
