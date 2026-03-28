<?php
require_once __DIR__ . '/env.php';

$host = env_value('DB_HOST');
$port = env_value('DB_PORT');
$dbname = env_value('DB_NAME');
$user = env_value('DB_USER') ?? env_value('USER');
$pass = env_value('DB_PASSWORD') ?? env_value('PASSWORD');

if (!$host || !$port || !$dbname || !$user || $pass === null) {
  echo json_encode(["error" => "Faltan variables de entorno para la conexion a la base de datos"]);
  exit;
}

try {
  $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  echo json_encode(["error" => "Error de conexion: " . $e->getMessage()]);
  exit;
}
