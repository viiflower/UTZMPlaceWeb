<?php
require_once __DIR__ . '/mailer.php';

const EMAIL_CODE_VERIFY = 'verify_email';
const EMAIL_CODE_RESET = 'reset_password';

function normalize_auth_email(string $email): string
{
  return strtolower(trim($email));
}

function auth_code_hash(string $code): string
{
  return hash('sha256', $code);
}

function generate_email_code(): string
{
  return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function issue_email_code(PDO $pdo, string $email, string $purpose, ?int $userId = null, int $ttlMinutes = 15): string
{
  $normalizedEmail = normalize_auth_email($email);
  $code = generate_email_code();

  $pdo->prepare('DELETE FROM email_codes WHERE email = ? AND purpose = ? AND used_at IS NULL')
    ->execute([$normalizedEmail, $purpose]);

  $stmt = $pdo->prepare(
    'INSERT INTO email_codes (user_id, email, purpose, code_hash, expires_at) VALUES (?, ?, ?, ?, NOW() + (? || \' minutes\')::interval)'
  );
  $stmt->execute([$userId, $normalizedEmail, $purpose, auth_code_hash($code), (string) $ttlMinutes]);

  return $code;
}

function consume_email_code(PDO $pdo, string $email, string $purpose, string $code): ?array
{
  $normalizedEmail = normalize_auth_email($email);
  $stmt = $pdo->prepare(
    'SELECT id, user_id, email, purpose, code_hash, expires_at
     FROM email_codes
     WHERE email = ? AND purpose = ? AND used_at IS NULL AND expires_at >= LOCALTIMESTAMP
     ORDER BY id DESC
     LIMIT 1'
  );
  $stmt->execute([$normalizedEmail, $purpose]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    return null;
  }

  if (!hash_equals((string) $row['code_hash'], auth_code_hash(trim($code)))) {
    return null;
  }

  $pdo->prepare('UPDATE email_codes SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);

  return $row;
}

function send_auth_code_email(string $email, string $purpose, string $code): void
{
  if ($purpose === EMAIL_CODE_VERIFY) {
    $subject = 'Codigo para verificar tu cuenta';
    $body = "Hola,\n\nTu codigo de verificacion de Utzmplace es: {$code}\n\nEl codigo vence en 15 minutos.\n\nSi no solicitaste esta cuenta, ignora este mensaje.";
  } elseif ($purpose === EMAIL_CODE_RESET) {
    $subject = 'Codigo para restablecer tu contrasena';
    $body = "Hola,\n\nTu codigo para restablecer la contrasena de Utzmplace es: {$code}\n\nEl codigo vence en 15 minutos.\n\nSi no solicitaste este cambio, ignora este mensaje.";
  } else {
    throw new InvalidArgumentException('Tipo de codigo no soportado.');
  }

  send_mail_message($email, $subject, $body);
}

function fetch_public_user(PDO $pdo, int $userId): ?array
{
  $stmt = $pdo->prepare('SELECT id, full_name, email, avatar_url, role, store_name, seller_bio FROM users WHERE id = ? LIMIT 1');
  $stmt->execute([$userId]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    return null;
  }

  if (empty($user['avatar_url'])) {
    $user['avatar_url'] = '/public/uploads/blank-profile.png';
  }

  return $user;
}
