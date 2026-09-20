<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
require __DIR__ . '/mautic.php';

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

function field(string $name, int $min = 1, int $max = 254): string
{
    $value = trim((string) ($_POST[$name] ?? ''));
    if (textLength($value) < $min || textLength($value) > $max) {
        respond(422, false, 'Campo obrigatório inválido.');
    }
    if (preg_match('/[\r\n]/', $value)) {
        respond(422, false, 'Caracteres inválidos no formulário.');
    }
    return $value;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Método não permitido.');
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(200, true, 'Inscrição registrada.');
}

$nome = field('nome', 2, 100);
$email = field('email', 6, 254);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, false, 'Informe um e-mail válido.');
}
$telefone = trim((string) ($_POST['telefone'] ?? ''));
if (textLength($telefone) > 30 || preg_match('/[\r\n]/', $telefone)) {
    respond(422, false, 'Telefone inválido.');
}
$curso = field('curso', 2, 80);
$interests = [
    'Ecovasc Teórico' => ['slug' => 'ecovasc-te-rico', 'type' => 'curso'],
    'Ecovasc Prático' => ['slug' => 'ecovasc-pr-tico', 'type' => 'curso'],
    'Duplexweb Venoso' => ['slug' => 'duplexweb-venoso', 'type' => 'curso'],
    'Duplexweb Fleboestética' => ['slug' => 'duplexweb-fleboest-tica', 'type' => 'curso'],
    'Duplexweb Arterial Básico' => ['slug' => 'duplexweb-arterial-b-sico', 'type' => 'curso'],
    'Duplexweb Arterial Avançado' => ['slug' => 'duplexweb-arterial-avan-ado', 'type' => 'curso'],
    'Duplexweb Vasos Arteriais Abdominais' => ['slug' => 'duplexweb-vasos-arteriais-abdominais', 'type' => 'curso'],
    'Duplexweb Vasos Venosos Abdominais' => ['slug' => 'duplexweb-vasos-venosos-abdominais', 'type' => 'curso'],
    'Duplexweb Procedimentos Ecoguiados' => ['slug' => 'duplexweb-procedimentos-ecoguiados', 'type' => 'curso'],
    'Duplexweb Acesso para Hemodiálise' => ['slug' => 'duplexweb-acesso-para-hemodi-lise', 'type' => 'curso'],
    'Fellowship em Ecografia Vascular com Doppler' => ['slug' => 'fellowship-em-ecografia-vascular-com-doppler', 'type' => 'curso'],
    'Cursos Imersivos de curta duração' => ['slug' => 'cursos-imersivos-de-curta-dura-o', 'type' => 'curso'],
    'Mini-fellowship Doppler' => ['slug' => 'mini-fellowship-doppler', 'type' => 'curso'],
    'Cases Clínicos Comentados' => ['slug' => 'cases-clinicos-comentados', 'type' => 'material'],
    'Aula Gravada de Doppler' => ['slug' => 'aula-gravada-doppler', 'type' => 'material'],
    'Doppler Arterial — Carótidas, Abdome e Membros (Pré-lançamento)' => ['slug' => 'livro-doppler-arterial', 'type' => 'livro'],
];
if (!isset($interests[$curso])) {
    respond(422, false, 'Interesse inválido.');
}
$privacyAccepted = (string) ($_POST['privacidade'] ?? '') === '1';
$marketingConsent = (string) ($_POST['consentimento_marketing'] ?? '') === '1';
if (!$privacyAccepted) {
    respond(422, false, 'Confirme que leu a Política de Privacidade para continuar.');
}
$interest = $interests[$curso];
$origem = trim((string) ($_POST['origem'] ?? ''));
if (preg_match('/[\r\n]/', $origem)) {
    respond(422, false, 'Origem inválida.');
}
$origem = substr($origem, 0, 80);

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fluxocursos-lista-' . hash('sha256', $ip . '|' . $curso);
$now = time();
if (is_file($rateLimitFile) && $now - (int) file_get_contents($rateLimitFile) < 60) {
    respond(429, false, 'Aguarde um minuto antes de enviar outra inscrição.');
}

$recipient = getenv('CONTACT_RECIPIENT') ?: '';
$from = getenv('CONTACT_FROM') ?: $recipient;
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
    error_log('CONTACT_RECIPIENT ou CONTACT_FROM inválidos.');
    respond(500, false, 'Não foi possível registrar a inscrição. Tente novamente mais tarde.');
}

$smtpHost = trim((string) getenv('SMTP_HOST'));
$smtpUsername = trim((string) getenv('SMTP_USERNAME'));
$smtpPassword = (string) getenv('SMTP_PASSWORD');
$smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
$smtpEncryption = strtolower((string) (getenv('SMTP_ENCRYPTION') ?: 'tls'));

if ($smtpHost === '' || $smtpUsername === '' || $smtpPassword === '' || $smtpPort < 1 || $smtpPort > 65535 || !in_array($smtpEncryption, ['tls', 'ssl'], true)) {
    error_log('Configuração SMTP ausente ou inválida.');
    respond(500, false, 'Não foi possível registrar a inscrição. Tente novamente mais tarde.');
}

$subject = $interest['type'] === 'livro'
    ? sprintf('[Pré-lançamento Livro] %s - %s', $curso, $nome)
    : sprintf('[Lista de Interesse] %s - %s', $curso, $nome);
$body = sprintf(
    "Interesse: %s\nNome: %s\nE-mail: %s\nTelefone: %s\nOrigem: %s\nConsentimento de marketing: %s",
    $curso,
    $nome,
    $email,
    $telefone,
    $origem,
    $marketingConsent ? 'sim' : 'não'
);

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
    error_log('Falha no envio SMTP da lista de interesse: ' . $exception->getMessage());
    respond(500, false, 'Não foi possível registrar a inscrição. Tente novamente mais tarde.');
}

try {
    $client = MauticClient::fromEnvironment();
    if ($client->isConfigured()) {
        [$firstname, $lastname] = splitName($nome);
        $tags = ['lista_espera_' . $interest['type'], 'lista_espera_' . $interest['slug'], 'site-fluxocursos'];
        if ($interest['type'] === 'curso') {
            $tags[] = 'lista_espera';
        }
        if ($interest['type'] === 'livro' && $marketingConsent) {
            $tags[] = 'pre_lancamento_doppler_arterial';
        }
        if ($marketingConsent) {
            $tags[] = 'consentimento_marketing';
        }
        $client->upsertContact(array_merge([
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'phone' => $telefone,
            'tags' => $tags,
        ], readUtmAttributes()));
    }
} catch (Throwable $error) {
    error_log('Falha ao sincronizar lead da lista de interesse: ' . $error->getMessage());
}

file_put_contents($rateLimitFile, (string) $now, LOCK_EX);
respond(200, true, 'Inscrição registrada. Avisaremos quando houver novidade.');

function splitName(string $nome): array
{
    $parts = preg_split('/\s+/u', trim($nome));
    if ($parts === false || $parts === []) {
        return [$nome, ''];
    }
    $firstname = array_shift($parts);
    return [$firstname, implode(' ', $parts)];
}

function readUtmAttributes(): array
{
    $attributes = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
        $value = trim((string) ($_POST[$key] ?? ''));
        if ($value === '' || textLength($value) > 100 || preg_match('/[\r\n]/', $value)) {
            continue;
        }
        $attributes[$key] = $value;
    }
    return $attributes;
}
