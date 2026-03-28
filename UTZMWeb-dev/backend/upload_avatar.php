<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['id'])) {
  echo json_encode(["error" => "No autorizado"]);
  exit;
}

if (!isset($_FILES['avatar'])) {
  echo json_encode(["error" => "No se recibió archivo"]);
  exit;
}

$dir = __DIR__ . '/../public/uploads/';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }

$safe = preg_replace('/[^a-zA-Z0-9._-]/','_', basename($_FILES['avatar']['name']));
$filename = time() . '_' . $safe;
$path = $dir . $filename;
if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $path)) {
  echo json_encode(["error" => "No se pudo guardar el archivo"]);
  exit;
}

$url = '/public/uploads/' . $filename;

try {
  $stmt = $pdo->prepare("UPDATE users SET avatar_url=? WHERE id=? RETURNING id, full_name, email, avatar_url, role");
  $stmt->execute([$url, (int)$_SESSION['user']['id']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  $_SESSION['user'] = $user;
  echo json_encode(["success" => true, "url" => $url]);
} catch (Exception $e) {
  echo json_encode(["error" => $e->getMessage()]);
}
