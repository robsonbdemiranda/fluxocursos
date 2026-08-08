<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require __DIR__ . '/../mautic.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(int $status, bool $success, string $message, array $data = []): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Método não permitido.');
}

$secret = trim((string) getenv('FLUXO_WEBHOOK_SECRET'));
if ($secret === '') {
    respond(500, false, 'Webhook secret não configurado no servidor.');
}

$signature = $_SERVER['HTTP_X_FLUXO_SIGNATURE'] ?? '';
if ($signature === '' || !hash_equals('sha256=' . hash_hmac('sha256', file_get_contents('php://input'), $secret), $signature)) {
    respond(401, false, 'Assinatura inválida.');
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    respond(422, false, 'Payload inválido.');
}

$event = (string) ($payload['event'] ?? '');
$allowedEvents = ['pedido_pago', 'pedido_cancelado', 'matricula_criada', 'matricula_concluida', 'reembolso'];
if (!in_array($event, $allowedEvents, true)) {
    respond(422, false, 'Evento não suportado.');
}

$customer = $payload['customer'] ?? [];
$products = $payload['products'] ?? [];
$orderId = (string) ($payload['order_id'] ?? '');

$email = strtolower(trim((string) ($customer['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, false, 'E-mail inválido.');
}

try {
    $client = MauticClient::fromEnvironment();
    if ($client->isConfigured()) {
        [$firstname, $lastname] = splitName((string) ($customer['name'] ?? ''));
        $tags = ['site-fluxocursos', 'cliente_potencial'];

        switch ($event) {
            case 'pedido_pago':
                $tags[] = 'cliente_pago';
                $tags[] = 'pedido_' . sanitizeTag($orderId);
                foreach ($products as $product) {
                    $slug = sanitizeTag((string) ($product['slug'] ?? ''));
                    if ($slug !== '') {
                        $tags[] = 'produto_' . $slug;
                    }
                }
                break;

            case 'pedido_cancelado':
                $tags[] = 'pedido_cancelado';
                break;

            case 'matricula_criada':
                $tags[] = 'aluno_ativo';
                $slug = sanitizeTag((string) ($payload['course_slug'] ?? ''));
                if ($slug !== '') {
                    $tags[] = 'aluno_' . $slug;
                }
                break;

            case 'matricula_concluida':
                $tags[] = 'aluno_concluinte';
                $slug = sanitizeTag((string) ($payload['course_slug'] ?? ''));
                if ($slug !== '') {
                    $tags[] = 'concluiu_' . $slug;
                }
                break;

            case 'reembolso':
                $tags[] = 'reembolso';
                break;
        }

        $client->upsertContact([
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'phone' => (string) ($customer['phone'] ?? ''),
            'tags' => array_values(array_unique($tags)),
        ]);
    }
} catch (Throwable $error) {
    error_log('Falha webhook Mautic: ' . $error->getMessage());
}

if ($event === 'pedido_pago' && getenv('CONTACT_RECIPIENT') && getenv('SMTP_HOST')) {
    try {
        sendOrderNotification($payload);
    } catch (Throwable $error) {
        error_log('Falha SMTP webhook: ' . $error->getMessage());
    }
}

respond(200, true, 'Evento processado.', ['event' => $event, 'order_id' => $orderId]);

function sendOrderNotification(array $payload): void
{
    $recipient = getenv('CONTACT_RECIPIENT');
    $from = getenv('CONTACT_FROM') ?: $recipient;
    $smtpHost = trim((string) getenv('SMTP_HOST'));
    $smtpUsername = trim((string) getenv('SMTP_USERNAME'));
    $smtpPassword = (string) getenv('SMTP_PASSWORD');
    $smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
    $smtpEncryption = strtolower((string) (getenv('SMTP_ENCRYPTION') ?: 'tls'));

    if (!$smtpHost || !$smtpUsername || !$smtpPassword) {
        return;
    }

    $customer = $payload['customer'] ?? [];
    $email = (string) ($customer['email'] ?? '');
    if ($email === '') {
        return;
    }

    $orderId = (string) ($payload['order_id'] ?? '');
    $total = (string) ($payload['total'] ?? '');
    $body = sprintf(
        "Novo pedido pago\nPedido: %s\nCliente: %s\nE-mail: %s\nTotal: %s",
        $orderId,
        (string) ($customer['name'] ?? ''),
        $email,
        $total
    );

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
    $mail->Subject = 'Novo pedido pago #' . $orderId;
    $mail->Body = $body;
    $mail->isHTML(false);
    $mail->send();
}

function splitName(string $nome): array
{
    $parts = preg_split('/\s+/u', trim($nome));
    if ($parts === false || $parts === []) {
        return [$nome, ''];
    }
    $firstname = array_shift($parts);
    return [$firstname, implode(' ', $parts)];
}

function sanitizeTag(string $value): string
{
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value);
    return trim(preg_replace('/-+/', '-', $value), '-');
}