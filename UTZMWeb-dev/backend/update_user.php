<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['id'])) {
  echo json_encode(["error" => "No autorizado"]);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$full_name = trim($data['full_name'] ?? '');
$email     = trim($data['email'] ?? '');
$store_name = trim($data['store_name'] ?? '');
$seller_bio = trim($data['seller_bio'] ?? '');
$password  = $data['password'] ?? '';
$old_password = $data['old_password'] ?? '';

if ($full_name === '' || $email === '') {
  echo json_encode(["error" => "Nombre y correo son obligatorios"]);
  exit;
}

$user_id = (int)$_SESSION['user']['id'];

try {
  if ($password !== '') {
    if (trim($old_password) === '') {
      echo json_encode(["error" => "Debes ingresar tu contraseña actual para cambiarla."]);
      exit;
    }

    $stmtChk = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
    $stmtChk->execute([$user_id]);
    $row = $stmtChk->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify($old_password, $row['password_hash'] ?? '')) {
      echo json_encode(["error" => "La contraseña actual no es correcta."]);
      exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, store_name=?, seller_bio=?, password_hash=? WHERE id=? RETURNING id, full_name, email, avatar_url, role, store_name, seller_bio");
    $stmt->execute([$full_name, $email, $store_name, $seller_bio, $hash, $user_id]);
  } else {
    $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, store_name=?, seller_bio=? WHERE id=? RETURNING id, full_name, email, avatar_url, role, store_name, seller_bio");
    $stmt->execute([$full_name, $email, $store_name, $seller_bio, $user_id]);
  }
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  $_SESSION['user'] = $user;
  echo json_encode(["success" => true, "user" => $user]);
} catch (PDOException $e) {
  // Postgres unique_violation
  if ($e->getCode() === '23505') {
    echo json_encode(["error" => "El correo ya está en uso","code"=>"email_taken","field"=>"email"]);
    exit;
  }
  echo json_encode(["error" => "No se pudo actualizar"]);
} catch (Exception $e) {
  echo json_encode(["error" => "No se pudo actualizar"]);
}
