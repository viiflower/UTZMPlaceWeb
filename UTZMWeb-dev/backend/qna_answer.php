<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['id'])) {
  echo json_encode(["error" => "No autorizado"]);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$question_id = (int)($data['question_id'] ?? 0);
$text = trim($data['text'] ?? '');
if ($question_id <= 0 || $text === '') {
  echo json_encode(["error" => "Datos inválidos"]);
  exit;
}

// Verificar que el usuario en sesión es el vendedor del producto
$stmt = $pdo->prepare("SELECT p.seller_id FROM questions q JOIN products p ON p.id = q.product_id WHERE q.id = ?");
$stmt->execute([$question_id]);
$seller_id = (int)$stmt->fetchColumn();
if (!$seller_id || $seller_id !== (int)$_SESSION['user']['id']) {
  echo json_encode(["error" => "Solo el vendedor puede responder"]);
  exit;
}

// Evitar múltiples respuestas por pregunta
$exists = $pdo->prepare("SELECT 1 FROM answers WHERE question_id = ? LIMIT 1");
$exists->execute([$question_id]);
if ($exists->fetch()) {
  echo json_encode(["error" => "La pregunta ya tiene respuesta"]);
  exit;
}

try {
  $stmt = $pdo->prepare("INSERT INTO answers (question_id, seller_id, text) VALUES (?,?,?) RETURNING id");
  $stmt->execute([$question_id, (int)$_SESSION['user']['id'], $text]);
  $id = (int)$stmt->fetchColumn();
  echo json_encode(["success" => true, "id" => $id]);
} catch (Exception $e) {
  echo json_encode(["error" => $e->getMessage()]);
}
