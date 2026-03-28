<?php
require __DIR__ . '/../db.php';
require_once __DIR__ . '/lib/cvv_storage.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Método no permitido']);
  exit;
}

if (!isset($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Debes iniciar sesión']);
  exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$order_id = isset($payload['order_id']) ? (int)$payload['order_id'] : 0;
$payment_method_id = isset($payload['payment_method_id']) ? (int)$payload['payment_method_id'] : 0;
$payment_method_type = isset($payload['payment_method_type']) ? strtolower(trim((string)$payload['payment_method_type'])) : null;
$payment_method_label = isset($payload['payment_method_label']) ? trim((string)$payload['payment_method_label']) : null;
$payment_method_last4 = isset($payload['payment_method_last4']) ? preg_replace('/\D/', '', (string)$payload['payment_method_last4']) : null;
$payment_method_last4 = $payment_method_last4 ? substr($payment_method_last4, -4) : null;
$amount_cents = isset($payload['amount_cents']) ? (int)$payload['amount_cents'] : 0;
$cvv_code = isset($payload['cvv']) ? preg_replace('/\D/', '', (string)$payload['cvv']) : null;
$cvv_code = $cvv_code === '' ? null : $cvv_code;
$user_id = (int)($_SESSION['user']['id'] ?? 0);

if ($order_id <= 0 || $amount_cents <= 0) {
  http_response_code(422);
  echo json_encode(['error' => 'Datos de pago incompletos']);
  exit;
}

$orderStmt = $pdo->prepare("SELECT id, buyer_id, status, total_cents FROM orders WHERE id = ?");
$orderStmt->execute([$order_id]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  http_response_code(404);
  echo json_encode(['error' => 'Orden no encontrada']);
  exit;
}

if ((int)$order['buyer_id'] !== $user_id) {
  http_response_code(403);
  echo json_encode(['error' => 'No puedes pagar una orden que no es tuya']);
  exit;
}

if ($order['status'] === 'paid') {
  echo json_encode(['message' => 'La orden ya estaba pagada']);
  exit;
}

if ($amount_cents !== (int)$order['total_cents']) {
  http_response_code(422);
  echo json_encode(['error' => 'El monto no coincide con el total de la orden']);
  exit;
}

try {
  $pdo->beginTransaction();

  if ($payment_method_id > 0) {
    $methodStmt = $pdo->prepare("
      SELECT id, type, label, last4
      FROM payment_methods
      WHERE id = :id AND user_id = :user_id
      LIMIT 1
    ");
    $methodStmt->execute([
      ':id' => $payment_method_id,
      ':user_id' => $user_id,
    ]);
    $storedMethod = $methodStmt->fetch(PDO::FETCH_ASSOC);

    if (!$storedMethod) {
      throw new RuntimeException('El método de pago no pertenece a tu cuenta', 403);
    }

    $payment_method_type = $payment_method_type ?: strtolower((string)$storedMethod['type']);
    $payment_method_label = $payment_method_label ?: ($storedMethod['label'] ?? null);
    $payment_method_last4 = $payment_method_last4 ?: ($storedMethod['last4'] ?? null);
  }

  if ($payment_method_type === null && $payment_method_id === 0) {
    $payment_method_type = 'card';
  }

  if (!$payment_method_label && $payment_method_type) {
    if ($payment_method_type === 'cash') {
      $payment_method_label = 'Efectivo';
    } elseif ($payment_method_type === 'paypal') {
      $payment_method_label = 'PayPal';
    } else {
      $payment_method_label = 'Tarjeta';
    }
  }

  $isCardMethod = ($payment_method_type ?: '') === 'card';
  if ($isCardMethod && !$cvv_code) {
    throw new RuntimeException('Ingresa el CVV de tu tarjeta', 422);
  }

  if ($payment_method_label) {
    $payment_method_label = preg_replace('/\bdemo\b/i', '', $payment_method_label);
    $payment_method_label = preg_replace('/^tarjeta\s*/i', '', $payment_method_label);
    $payment_method_label = trim(preg_replace('/\s{2,}/', ' ', $payment_method_label));
    if ($payment_method_label === '') {
      if ($payment_method_type === 'card') {
        $payment_method_label = 'Tarjeta';
      } elseif ($payment_method_type === 'paypal') {
        $payment_method_label = 'PayPal';
      }
    }
  }

  $payment_method_id = max(0, $payment_method_id);
  if ($payment_method_id === 0) {
    $find = $pdo->prepare("
      SELECT id
      FROM payment_methods
      WHERE user_id = :user_id
        AND type = :type
        AND COALESCE(last4, '') = COALESCE(:last4, '')
      ORDER BY id DESC
      LIMIT 1
    ");
    $find->execute([
      ':user_id' => $user_id,
      ':type' => $payment_method_type ?: 'card',
      ':last4' => $payment_method_last4,
    ]);
    $payment_method_id = (int)$find->fetchColumn();
  }

  if ($isCardMethod && $payment_method_id > 0) {
    $storedMeta = cvv_storage_fetch($payment_method_id);
    if (!$storedMeta) {
      cvv_storage_save($payment_method_id, $cvv_code);
    } elseif (!cvv_storage_verify($payment_method_id, $cvv_code ?? '')) {
      throw new RuntimeException('El CVV no coincide con la tarjeta guardada', 422);
    }
  }

  if ($payment_method_id === 0) {
    try {
      $pdo->exec("SELECT setval('payment_methods_id_seq', (SELECT COALESCE(MAX(id), 0) FROM payment_methods))");
    } catch (Exception $e) {
      // continuar aunque falle el ajuste de secuencia
    }

    $insertMethod = $pdo->prepare("
      INSERT INTO payment_methods (user_id, type, label, last4)
      VALUES (:user_id, :type, :label, :last4)
      RETURNING id
    ");
    $insertMethod->execute([
      ':user_id' => $user_id,
      ':type' => $payment_method_type ?: 'card',
      ':label' => $payment_method_label,
      ':last4' => $payment_method_last4,
    ]);
    $payment_method_id = (int)$insertMethod->fetchColumn();

    if ($isCardMethod && $cvv_code) {
      cvv_storage_save($payment_method_id, $cvv_code);
    }
  }

  if ($payment_method_id <= 0) {
    throw new RuntimeException('No se pudo preparar el método de pago', 400);
  }

  $insertPay = $pdo->prepare("
    INSERT INTO payments (order_id, payment_method_id, amount_cents, status, paid_at)
    VALUES (:order_id, :payment_method_id, :amount_cents, 'captured', NOW())
  ");
  $insertPay->execute([
    ':order_id' => $order_id,
    ':payment_method_id' => $payment_method_id,
    ':amount_cents' => $amount_cents,
  ]);

  $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$order_id]);
  $pdo->commit();

  echo json_encode([
    'message' => 'Pago registrado y orden marcada como pagada',
    'payment_method_id' => $payment_method_id,
    'payment_method_type' => $payment_method_type,
    'payment_method_label' => $payment_method_label,
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }

  $status = (int)$e->getCode();
  if (!in_array($status, [400, 403, 422], true)) {
    $status = 400;
  }

  http_response_code($status);
  echo json_encode(['error' => $e->getMessage()]);
}
