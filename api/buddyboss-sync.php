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

function cleanEnv(string $name): string
{
    $value = (string) getenv($name);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/[\r\n\t]+/', '', $value) ?? '';
    return trim($value);
}

function getAuthorizationHeader(): string
{
    return trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Metodo nao permitido.');
}

$syncToken = cleanEnv('BUDDYBOSS_SYNC_TOKEN');
if ($syncToken === '') {
    respond(500, false, 'Token de sincronizacao nao configurado no servidor.');
}

$authorization = getAuthorizationHeader();
$prefix = 'Bearer ';
if (stripos($authorization, $prefix) !== 0 || !hash_equals($syncToken, trim(substr($authorization, strlen($prefix))))) {
    header('WWW-Authenticate: Bearer');
    respond(401, false, 'Nao autorizado.');
}

function curlGet(string $url, string $username, string $password): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $username . ':' . $password,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('Erro cURL: ' . $error);
    }
    return ['status' => $code, 'body' => (string) $body];
}

function fetchUsers(string $baseUrl, string $username, string $password, int $page, int $perPage): array
{
    $url = sprintf('%s/wp-json/wp/v2/users?per_page=%d&page=%d&context=edit&roles=subscriber', rtrim($baseUrl, '/'), $perPage, $page);
    $r = curlGet($url, $username, $password);
    if ($r['status'] >= 400) {
        throw new RuntimeException('WP REST status ' . $r['status'] . ': ' . substr($r['body'], 0, 200));
    }
    $data = json_decode($r['body'], true);
    if (!is_array($data)) {
        throw new RuntimeException('WP REST resposta invalida: ' . substr($r['body'], 0, 200));
    }
    return $data;
}

function fetchMemberProfile(string $baseUrl, string $username, string $password, int $memberId): array
{
    $url = sprintf('%s/wp-json/buddyboss/v1/members/%d', rtrim($baseUrl, '/'), $memberId);
    $r = curlGet($url, $username, $password);
    if ($r['status'] >= 400) {
        return [];
    }
    $data = json_decode($r['body'], true);
    if (!is_array($data)) {
        return [];
    }
    $xprofile = $data['xprofile'] ?? [];
    if (!is_array($xprofile)) {
        return [];
    }
    $fields = [];
    if (isset($xprofile['groups']) && is_array($xprofile['groups'])) {
        foreach ($xprofile['groups'] as $group) {
            if (!is_array($group) || !isset($group['fields']) || !is_array($group['fields'])) {
                continue;
            }
            foreach ($group['fields'] as $fieldId => $field) {
                if (is_array($field)) {
                    $fields[$fieldId] = $field;
                }
            }
        }
    }
    return $fields;
}

function getXprofileValue(array $fields, array $candidates): string
{
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $name = strtolower((string) ($field['name'] ?? ''));
        $label = strtolower((string) ($field['label'] ?? ''));
        foreach ($candidates as $candidate) {
            $needle = strtolower($candidate);
            if ($name === $needle || $label === $needle || strpos($name, $needle) !== false || strpos($label, $needle) !== false) {
                $raw = $field['value']['raw'] ?? '';
                $unserialized = $field['value']['unserialized'] ?? null;

                if (is_array($unserialized) && !empty($unserialized)) {
                    return (string) implode(', ', array_filter(array_map('strval', $unserialized)));
                }

                if (is_string($raw) && preg_match('/^a:\d+:\{/', $raw)) {
                    $unserialized = @unserialize($raw);
                    if (is_array($unserialized)) {
                        return (string) implode(', ', array_filter(array_map('strval', $unserialized)));
                    }
                }

                if (is_array($raw)) {
                    return (string) implode(', ', array_filter(array_map('strval', $raw)));
                }

                return (string) $raw;
            }
        }
    }
    return '';
}

function mapEspecialidade(string $raw): string
{
    $value = trim((string) $raw);
    if ($value === '') {
        return '';
    }
    $normalized = strtolower(thisRemoveAccents($value));
    $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? $normalized;
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';
    $normalized = trim($normalized);

    $candidates = [
        'cirurgiao vascular' => 'cirurgiao_vascular',
        'cirurgiâ vascular' => 'cirurgiao_vascular',
        'angiologista' => 'angiologista',
        'ecocardiografista' => 'ecocardiografista',
        'ultrassonografista' => 'ultrassonografista',
        'radiologista' => 'radiologista',
        'outros' => 'outros',
        'outras' => 'outros',
    ];

    foreach ($candidates as $needle => $slug) {
        if (strpos($normalized, $needle) !== false) {
            return $slug;
        }
    }
    return 'outros';
}

function thisRemoveAccents(string $value): string
{
    $search = ['á', 'à', 'â', 'ã', 'ä', 'Á', 'À', 'Â', 'Ã', 'Ä',
        'é', 'è', 'ê', 'ë', 'É', 'È', 'Ê', 'Ë',
        'í', 'ì', 'î', 'ï', 'Í', 'Ì', 'Î', 'Ï',
        'ó', 'ò', 'ô', 'õ', 'ö', 'Ó', 'Ò', 'Ô', 'Õ', 'Ö',
        'ú', 'ù', 'û', 'ü', 'Ú', 'Ù', 'Û', 'Ü',
        'ç', 'Ç', 'ñ', 'Ñ'];
    $replace = ['a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
        'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
        'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i',
        'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
        'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
        'c', 'c', 'n', 'n'];
    return str_replace($search, $replace, $value);
}

function postWebhook(string $endpoint, string $secret, array $payload): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Fluxo-Signature: ' . $signature,
            'X-Fluxo-Source: fluxo-buddyboss-bulk/1.0.1',
        ],
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return ['status' => 0, 'error' => $error];
    }
    return ['status' => $status, 'body' => (string) $raw];
}

$secret = cleanEnv('FLUXO_WEBHOOK_SECRET');
$baseUrl = cleanEnv('BUDDYBOSS_BASE_URL');
$username = cleanEnv('BUDDYBOSS_USERNAME');
$password = cleanEnv('BUDDYBOSS_APP_PASSWORD');

if ($secret === '' || $baseUrl === '' || $username === '' || $password === '') {
    respond(500, false, 'Variaveis de ambiente faltando. Configure FLUXO_WEBHOOK_SECRET, BUDDYBOSS_BASE_URL, BUDDYBOSS_USERNAME, BUDDYBOSS_APP_PASSWORD.');
}

$webhookEndpoint = cleanEnv('BUDDYBOSS_WEBHOOK_URL');
if ($webhookEndpoint === '') {
    $webhookEndpoint = cleanEnv('BUDDYBOSS_PUBLIC_URL');
    if ($webhookEndpoint === '') {
        $webhookEndpoint = 'https://fluxocursos.com.br/api/webhook-buddyboss.php';
    } else {
        $webhookEndpoint = rtrim($webhookEndpoint, '/') . '/api/webhook-buddyboss.php';
    }
}

$limit = isset($_GET['limit']) ? max(0, (int) $_GET['limit']) : 0;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
$dryRun = !empty($_GET['dry_run']);
$perPage = 50;
$role = cleanEnv('BUDDYBOSS_ROLE') ?: 'subscriber';

$processed = 0;
$updated = 0;
$skipped = 0;
$errors = 0;
$samples = [];

try {
    $page = 1;
    while (true) {
        $url = sprintf('%s/wp-json/wp/v2/users?per_page=%d&page=%d&context=edit&roles=%s', rtrim($baseUrl, '/'), $perPage, $page, $role);
        $r = curlGet($url, $username, $password);
        if ($r['status'] === 400 && strpos($r['body'], 'rest_invalid_param') !== false) {
            $url = sprintf('%s/wp-json/wp/v2/users?per_page=%d&page=%d&context=edit', rtrim($baseUrl, '/'), $perPage, $page);
            $r = curlGet($url, $username, $password);
        }
        if ($r['status'] >= 400) {
            throw new RuntimeException('WP REST status ' . $r['status'] . ': ' . substr($r['body'], 0, 200));
        }
        $users = json_decode($r['body'], true);
        if (!is_array($users)) {
            throw new RuntimeException('WP REST resposta invalida: ' . substr($r['body'], 0, 200));
        }
        if (empty($users)) {
            break;
        }
        foreach ($users as $user) {
            $userId = (int) ($user['id'] ?? 0);
            $email = (string) ($user['email'] ?? '');
            if ($userId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }
            if ($offset > 0 && $processed < $offset) {
                $processed++;
                $skipped++;
                continue;
            }
            if ($limit > 0 && $updated >= $limit) {
                break 2;
            }

            $xprofile = fetchMemberProfile($baseUrl, $username, $password, $userId);

            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            if ($name === '') {
                $name = (string) ($user['name'] ?? $user['slug'] ?? '');
            }

            $payload = [
                'event' => 'comunidade_importacao_inicial',
                'user_id' => $userId,
                'community_slug' => cleanEnv('BUDDYBOSS_COMMUNITY_SLUG') ?: 'clube-do-doppler',
                'customer' => [
                    'name' => $name,
                    'email' => $email,
                    'phone' => (string) ($user['meta']['phone'] ?? ''),
                ],
            'profile' => [
                'especialidade' => mapEspecialidade(getXprofileValue($xprofile, ['Especialidad', 'Especialidade'])),
                'cidade' => '',
                'crm' => '',
                'telefone' => getXprofileValue($xprofile, ['Teléfono', 'Telefone', 'Phone']),
            ],
            ];

            if (!$dryRun) {
                $result = postWebhook($webhookEndpoint, $secret, $payload);
                if ($result['status'] >= 200 && $result['status'] < 300) {
                    $updated++;
                } else {
                    $errors++;
                }
                if (count($samples) < 3) {
                    $samples[] = [
                        'user_id' => $userId,
                        'status' => $result['status'],
                        'body' => substr((string) ($result['body'] ?? $result['error'] ?? ''), 0, 200),
                    ];
                }
                usleep(200000);
            } else {
                $updated++;
                if (count($samples) < 3) {
                    $samples[] = ['user_id' => $userId, 'dry_run' => true];
                }
            }
            $processed++;
        }
        $page++;
    }
} catch (Throwable $error) {
    respond(500, false, 'Erro durante sincronizacao: ' . $error->getMessage(), [
        'processed' => $processed,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'samples' => $samples,
    ]);
}

respond(200, true, 'Sincronizacao concluida.', [
    'processed' => $processed,
    'updated' => $updated,
    'skipped' => $skipped,
    'errors' => $errors,
    'dry_run' => $dryRun,
    'samples' => $samples,
]);
