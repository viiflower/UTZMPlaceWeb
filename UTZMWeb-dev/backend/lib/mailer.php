<?php
require_once __DIR__ . '/../../env.php';

function mailer_encode_header(string $value): string
{
  return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function mailer_read_response($socket, array $allowedCodes): string
{
  $response = '';

  while (($line = fgets($socket, 515)) !== false) {
    $response .= $line;
    if (strlen($line) < 4 || $line[3] !== '-') {
      break;
    }
  }

  $code = (int) substr($response, 0, 3);
  if (!in_array($code, $allowedCodes, true)) {
    throw new RuntimeException('No se pudo enviar el correo. Respuesta SMTP: ' . trim($response));
  }

  return $response;
}

function mailer_write_command($socket, string $command, array $allowedCodes): string
{
  fwrite($socket, $command . "\r\n");
  return mailer_read_response($socket, $allowedCodes);
}

function mailer_dot_stuff(string $value): string
{
  $normalized = str_replace(["\r\n", "\r"], "\n", $value);
  $lines = explode("\n", $normalized);

  foreach ($lines as &$line) {
    if ($line !== '' && $line[0] === '.') {
      $line = '.' . $line;
    }
  }

  return implode("\r\n", $lines);
}

function send_mail_message(string $to, string $subject, string $textBody): void
{
  $smtpUser = env_value('GMAIL_USERNAME');
  $smtpPass = env_value('GMAIL_APP_PASSWORD');
  $fromAddress = env_value('MAIL_FROM_ADDRESS') ?? $smtpUser;
  $fromName = env_value('MAIL_FROM_NAME') ?? 'Utzmplace';

  if (!$smtpUser || !$smtpPass || !$fromAddress) {
    throw new RuntimeException('Faltan variables de correo: GMAIL_USERNAME, GMAIL_APP_PASSWORD o MAIL_FROM_ADDRESS.');
  }

  $context = stream_context_create([
    'ssl' => [
      'verify_peer' => true,
      'verify_peer_name' => true,
      'allow_self_signed' => false,
    ],
  ]);

  $socket = stream_socket_client(
    'ssl://smtp.gmail.com:465',
    $errno,
    $errstr,
    20,
    STREAM_CLIENT_CONNECT,
    $context,
  );

  if (!$socket) {
    throw new RuntimeException('No se pudo conectar con Gmail SMTP: ' . $errstr);
  }

  try {
    mailer_read_response($socket, [220]);
    mailer_write_command($socket, 'EHLO localhost', [250]);
    mailer_write_command($socket, 'AUTH LOGIN', [334]);
    mailer_write_command($socket, base64_encode($smtpUser), [334]);
    mailer_write_command($socket, base64_encode($smtpPass), [235]);
    mailer_write_command($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
    mailer_write_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
    mailer_write_command($socket, 'DATA', [354]);

    $headers = [
      'Date: ' . date(DATE_RFC2822),
      'From: ' . mailer_encode_header($fromName) . ' <' . $fromAddress . '>',
      'To: <' . $to . '>',
      'Subject: ' . mailer_encode_header($subject),
      'MIME-Version: 1.0',
      'Content-Type: text/plain; charset=UTF-8',
      'Content-Transfer-Encoding: base64',
    ];

    $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($textBody));

    fwrite($socket, mailer_dot_stuff($message) . "\r\n.\r\n");
    mailer_read_response($socket, [250]);
    mailer_write_command($socket, 'QUIT', [221]);
  } finally {
    fclose($socket);
  }
}
