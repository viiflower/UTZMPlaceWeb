<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['error' => 'No autenticado']);
  exit;
}

$user_id = (int)($_SESSION['user']['id'] ?? 0);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function ensureOrderAccess(PDO $pdo, int $order_id, int $user_id): array {
  $stmt = $pdo->prepare('SELECT id, buyer_id, seller_id, status FROM orders WHERE id = ?');
  $stmt->execute([$order_id]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Orden no encontrada']);
    exit;
  }

  if ((int)$order['buyer_id'] !== $user_id && (int)$order['seller_id'] !== $user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso para esta orden']);
    exit;
  }

  return $order;
}

function orderMessagesTableExists(PDO $pdo): bool {
  try {
    $check = $pdo->query("SELECT to_regclass('public.order_messages') IS NOT NULL");
    return (bool)$check->fetchColumn();
  } catch (Exception $e) {
    return false;
  }
}

function makeAttachmentUrl(?string $path): ?string {
  if (!$path) return null;
  $normalized = ltrim($path, '/');
  if (strpos($normalized, 'public/') === 0) {
    return '/' . $normalized;
  }
  return '/public/' . $normalized;
}

$hasTable = orderMessagesTableExists($pdo);
if (!$hasTable) {
  http_response_code(503);
  echo json_encode(['error' => 'Falta la tabla order_messages. Ejecuta la migración indicada en la documentación.']);
  exit;
}

if ($method === 'GET') {
  $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
  $since_id = isset($_GET['since_id']) ? max(0, (int)$_GET['since_id']) : 0;
  if ($order_id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'order_id requerido']);
    exit;
  }

  $order = ensureOrderAccess($pdo, $order_id, $user_id);

  $params = [':oid' => $order_id, ':uid' => $user_id];
  $whereSince = '';
  if ($since_id > 0) {
    $whereSince = 'AND m.id > :since';
    $params[':since'] = $since_id;
  }

  $sql = "
    SELECT m.id, m.order_id, m.sender_id, m.body, m.created_at,
           m.attachment_path, m.attachment_mime, m.attachment_size,
           u.full_name AS sender_name,
           (m.sender_id = :uid) AS is_mine
    FROM order_messages m
    JOIN users u ON u.id = m.sender_id
    WHERE m.order_id = :oid
    $whereSince
    ORDER BY m.created_at ASC, m.id ASC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Marcar como leídos los mensajes del contrario
  $mark = $pdo->prepare('UPDATE order_messages SET read_at = NOW() WHERE order_id = ? AND sender_id <> ? AND read_at IS NULL');
  $mark->execute([$order_id, $user_id]);

  $formatted = array_map(function ($msg) {
    return [
      'id' => (int)$msg['id'],
      'order_id' => (int)$msg['order_id'],
      'sender_id' => (int)$msg['sender_id'],
      'sender_name' => $msg['sender_name'],
      'body' => $msg['body'],
      'created_at' => $msg['created_at'],
      'is_mine' => (bool)$msg['is_mine'],
      'attachment_url' => makeAttachmentUrl($msg['attachment_path'] ?? null),
      'attachment_mime' => $msg['attachment_mime'] ?? null,
      'attachment_size' => isset($msg['attachment_size']) ? (int)$msg['attachment_size'] : null,
    ];
  }, $messages);

  echo json_encode([
    'order' => [
      'id' => (int)$order['id'],
      'buyer_id' => (int)$order['buyer_id'],
      'seller_id' => (int)$order['seller_id'],
      'status' => $order['status'],
    ],
    'messages' => $formatted,
  ]);
  exit;
}

if ($method === 'POST') {
  $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
  $isJson = stripos($contentType, 'application/json') === 0;
  $payload = $isJson ? (json_decode(file_get_contents('php://input'), true) ?? []) : $_POST;

  $order_id = isset($payload['order_id']) ? (int)$payload['order_id'] : 0;
  $body = trim($payload['body'] ?? '');
  $hasAttachment = isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE;

  if ($order_id <= 0 || ($body === '' && !$hasAttachment)) {
    http_response_code(422);
    echo json_encode(['error' => 'Envía un mensaje o adjunta una imagen.']);
    exit;
  }

  $order = ensureOrderAccess($pdo, $order_id, $user_id);

  if (mb_strlen($body) > 1000) {
    $body = mb_substr($body, 0, 1000);
  }

  $attachmentPath = null;
  $attachmentMime = null;
  $attachmentSize = null;

  if ($hasAttachment) {
    $file = $_FILES['attachment'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
      http_response_code(422);
      echo json_encode(['error' => 'No se pudo subir la imagen.']);
      exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
      http_response_code(422);
      echo json_encode(['error' => 'La imagen debe pesar menos de 5 MB.']);
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
      echo json_encode(['error' => 'Formato de imagen no permitido.']);
      exit;
    }

    $baseUploads = realpath(__DIR__ . '/../public/uploads') ?: (__DIR__ . '/../public/uploads');
    $targetDir = $baseUploads . DIRECTORY_SEPARATOR . 'messages';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
      http_response_code(500);
      echo json_encode(['error' => 'No se pudo preparar el directorio de cargas.']);
      exit;
    }

    $filename = sprintf('msg_%s_%s.%s', date('YmdHis'), bin2hex(random_bytes(4)), $ext);
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
      http_response_code(500);
      echo json_encode(['error' => 'Error al guardar la imagen.']);
      exit;
    }

    $attachmentPath = 'uploads/messages/' . $filename;
    $attachmentMime = $detectedMime;
    $attachmentSize = (int)$file['size'];
  }

  $stmt = $pdo->prepare('
    INSERT INTO order_messages (order_id, sender_id, body, attachment_path, attachment_mime, attachment_size)
    VALUES (:order_id, :sender_id, :body, :attachment_path, :attachment_mime, :attachment_size)
    RETURNING id, created_at, attachment_path, attachment_mime, attachment_size
  ');
  $stmt->execute([
    ':order_id' => $order_id,
    ':sender_id' => $user_id,
    ':body' => $body === '' ? '' : $body,
    ':attachment_path' => $attachmentPath,
    ':attachment_mime' => $attachmentMime,
    ':attachment_size' => $attachmentSize,
  ]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  echo json_encode([
    'message' => [
      'id' => (int)$row['id'],
      'order_id' => $order_id,
      'sender_id' => $user_id,
      'sender_name' => $_SESSION['user']['full_name'] ?? $_SESSION['user']['email'] ?? 'Tú',
      'body' => $body,
      'created_at' => $row['created_at'],
      'is_mine' => true,
      'attachment_url' => makeAttachmentUrl($row['attachment_path'] ?? $attachmentPath),
      'attachment_mime' => $row['attachment_mime'] ?? $attachmentMime,
      'attachment_size' => isset($row['attachment_size']) ? (int)$row['attachment_size'] : $attachmentSize,
    ],
    'order' => [
      'id' => (int)$order['id'],
      'buyer_id' => (int)$order['buyer_id'],
      'seller_id' => (int)$order['seller_id'],
      'status' => $order['status'],
    ],
  ]);
  exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
