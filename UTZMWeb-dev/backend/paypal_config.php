<?php
require_once __DIR__ . '/../env.php';
header('Content-Type: application/json; charset=utf-8');

$clientId = env_value('PAYPAL_CLIENT_ID');
$currency = env_value('PAYPAL_CURRENCY') ?? 'MXN';

if (!$clientId) {
  http_response_code(500);
  echo json_encode(['error' => 'Falta PAYPAL_CLIENT_ID en el archivo .env']);
  exit;
}

echo json_encode([
  'client_id' => $clientId,
  'currency' => strtoupper($currency),
  'environment' => 'sandbox',
]);
