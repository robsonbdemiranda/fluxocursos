<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(int $status, bool $success, string $message): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function textLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Método não permitido.');
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin !== '' && $host !== '' && parse_url($origin, PHP_URL_HOST) !== preg_replace('/:.+$/', '', $host)) {
    respond(403, false, 'Origem da solicitação inválida.');
}

// Honeypot: bots costumam preencher campos invisíveis ao visitante.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(200, true, 'Mensagem enviada com sucesso.');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fluxocursos-contact-' . hash('sha256', $ip);
$now = time();
if (is_file($rateLimitFile) && $now - (int) file_get_contents($rateLimitFile) < 60) {
    respond(429, false, 'Aguarde um minuto antes de enviar outra mensagem.');
}

$nome = trim((string) ($_POST['nome'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$mensagem = trim((string) ($_POST['mensagem'] ?? ''));

if (textLength($nome) < 2 || textLength($nome) > 100) {
    respond(422, false, 'Informe um nome válido.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
    respond(422, false, 'Informe um e-mail válido.');
}
if (textLength($telefone) > 30 || preg_match('/[\r\n]/', $telefone)) {
    respond(422, false, 'Informe um telefone válido.');
}
if (textLength($mensagem) < 1 || textLength($mensagem) > 3000) {
    respond(422, false, 'A mensagem deve ter entre 1 e 3000 caracteres.');
}

$recipient = getenv('CONTACT_RECIPIENT') ?: '';
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    error_log('CONTACT_RECIPIENT inválido.');
    respond(500, false, 'Não foi possível enviar a mensagem. Tente novamente mais tarde.');
}

$from = getenv('CONTACT_FROM') ?: '';
if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
    error_log('CONTACT_FROM inválido.');
    respond(500, false, 'Não foi possível enviar a mensagem. Tente novamente mais tarde.');
}

$smtpHost = trim((string) getenv('SMTP_HOST'));
$smtpUsername = trim((string) getenv('SMTP_USERNAME'));
$smtpPassword = (string) getenv('SMTP_PASSWORD');
$smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
$smtpEncryption = strtolower((string) (getenv('SMTP_ENCRYPTION') ?: 'tls'));

if ($smtpHost === '' || $smtpUsername === '' || $smtpPassword === '' || $smtpPort < 1 || $smtpPort > 65535 || !in_array($smtpEncryption, ['tls', 'ssl'], true)) {
    error_log('Configuração SMTP ausente ou inválida.');
    respond(500, false, 'Não foi possível enviar a mensagem. Tente novamente mais tarde.');
}

$subject = 'Contato do Site - Fluxo Cursos';
$body = "Nome: {$nome}\nTelefone: {$telefone}\nE-mail: {$email}\n\nMensagem:\n{$mensagem}";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->Port = $smtpPort;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->SMTPSecure = $smtpEncryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Timeout = 15;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->setFrom($from, 'Fluxo Cursos');
    $mail->addAddress($recipient);
    $mail->addReplyTo($email, $nome);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->isHTML(false);
    $mail->send();
} catch (Throwable $exception) {
    error_log('Falha no envio SMTP do contato: ' . $exception->getMessage());
    respond(500, false, 'Não foi possível enviar a mensagem. Tente novamente mais tarde.');
}

file_put_contents($rateLimitFile, (string) $now, LOCK_EX);
respond(200, true, 'Mensagem enviada com sucesso.');
