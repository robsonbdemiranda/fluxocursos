<?php
declare(strict_types=1);

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

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_FLUXO_SIGNATURE'] ?? '';
if ($signature === '' || !hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $secret), $signature)) {
    respond(401, false, 'Assinatura inválida.');
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    respond(422, false, 'Payload inválido.');
}

$event = (string) ($payload['event'] ?? '');
$allowedEvents = ['cadastro_comunidade', 'perfil_atualizado_comunidade'];
if (!in_array($event, $allowedEvents, true)) {
    respond(422, false, 'Evento não suportado.');
}

$customer = $payload['customer'] ?? [];
$profile = $payload['profile'] ?? [];
$communitySlug = sanitizeTag((string) ($payload['community_slug'] ?? 'clube-do-doppler'));

$email = strtolower(trim((string) ($customer['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, false, 'E-mail inválido.');
}

try {
    $client = MauticClient::fromEnvironment();
    if ($client->isConfigured()) {
        [$firstname, $lastname] = splitName((string) ($customer['name'] ?? ''));
        $tags = ['site-fluxocursos', 'comunidade_' . $communitySlug];

        switch ($event) {
            case 'cadastro_comunidade':
                $tags[] = 'comunidade_clube_doppler';
                $tags[] = 'comunidade_novo_cadastro';
                break;
            case 'perfil_atualizado_comunidade':
                $tags[] = 'comunidade_perfil_atualizado';
                break;
        }

        $especialidade = sanitizeTag((string) ($profile['especialidade'] ?? ''));
        $cidade = sanitizeTag((string) ($profile['cidade'] ?? ''));
        $crm = sanitizeTag((string) ($profile['crm'] ?? ''));
        if ($especialidade !== '') {
            $tags[] = 'comunidade_especialidade_' . $especialidade;
        }
        if ($cidade !== '') {
            $tags[] = 'comunidade_cidade_' . $cidade;
        }
        if ($crm !== '') {
            $tags[] = 'comunidade_crm_' . $crm;
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
    error_log('Falha webhook BuddyBoss: ' . $error->getMessage());
}

respond(200, true, 'Evento processado.', ['event' => $event]);

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
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value);
    return trim(preg_replace('/-+/', '-', $value), '-');
}