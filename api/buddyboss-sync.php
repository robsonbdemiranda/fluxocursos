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

function loadSyncTracker(): array
{
    $file = sys_get_temp_dir() . '/fluxo-buddyboss-sync.json';
    if (!is_file($file)) {
        return [];
    }
    $raw = (string) file_get_contents($file);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function saveSyncTracker(array $tracker): void
{
    $file = sys_get_temp_dir() . '/fluxo-buddyboss-sync.json';
    file_put_contents($file, json_encode($tracker, JSON_PRETTY_PRINT), LOCK_EX);
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

function fetchMembers(string $baseUrl, string $username, string $password, int $page, int $perPage): array
{
    $url = sprintf('%s/wp-json/buddyboss/v1/members?per_page=%d&page=%d', rtrim($baseUrl, '/'), $perPage, $page);
    $r = curlGet($url, $username, $password);
    if ($r['status'] >= 400) {
        throw new RuntimeException('BuddyBoss status ' . $r['status'] . ': ' . substr($r['body'], 0, 200));
    }
    $data = json_decode($r['body'], true);
    return is_array($data) ? $data : [];
}

function extractXprofileValue(array $xprofile, array $candidates): string
{
    foreach ($xprofile as $field) {
        if (!is_array($field)) {
            continue;
        }
        $name = strtolower((string) ($field['name'] ?? ''));
        $label = strtolower((string) ($field['label'] ?? ''));
        foreach ($candidates as $candidate) {
            $needle = strtolower($candidate);
            if ($name === $needle || $label === $needle || strpos($name, $needle) !== false || strpos($label, $needle) !== false) {
                $value = $field['value']['raw'] ?? ($field['value'] ?? '');
                if (is_array($value)) {
                    $value = implode(', ', array_filter(array_map('strval', $value)));
                }
                return (string) $value;
            }
        }
    }
    return '';
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
            'X-Fluxo-Source: fluxo-buddyboss-bulk/1.0.0',
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
    $webhookEndpoint = str_replace('/api/buddyboss-sync.php', '/api/webhook-buddyboss.php', cleanEnv('BUDDYBOSS_PUBLIC_URL') ?: 'https://fluxocursos.com.br/api/webhook-buddyboss.php');
}

$limit = isset($_GET['limit']) ? max(0, (int) $_GET['limit']) : 0;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
$dryRun = !empty($_GET['dry_run']);
$perPage = 50;

$tracker = loadSyncTracker();
$processed = 0;
$updated = 0;
$skipped = 0;
$errors = 0;
$samples = [];

try {
    $page = 1;
    while (true) {
        $members = fetchMembers($baseUrl, $username, $password, $page, $perPage);
        if (empty($members)) {
            break;
        }
        foreach ($members as $member) {
            if (!is_array($member)) {
                continue;
            }
            $memberId = (int) ($member['id'] ?? 0);
            $email = (string) ($member['email'] ?? '');
            if ($memberId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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

            $xprofile = is_array($member['xprofile'] ?? null) ? $member['xprofile'] : [];

            $payload = [
                'event' => 'comunidade_importacao_inicial',
                'user_id' => $memberId,
                'community_slug' => cleanEnv('BUDDYBOSS_COMMUNITY_SLUG') ?: 'clube-do-doppler',
                'customer' => [
                    'name' => (string) ($member['name'] ?? ''),
                    'email' => $email,
                    'phone' => (string) ($member['meta']['phone'] ?? ''),
                ],
                'profile' => [
                    'especialidade' => extractXprofileValue($xprofile, ['Especialidad', 'Especialidade']),
                    'cidade' => extractXprofileValue($xprofile, ['Ciudad', 'Cidade']),
                    'crm' => extractXprofileValue($xprofile, ['CRM', 'Registro']),
                    'telefone' => extractXprofileValue($xprofile, ['Teléfono', 'Telefone', 'Phone']),
                ],
            ];

            if (!$dryRun) {
                $result = postWebhook($webhookEndpoint, $secret, $payload);
                if ($result['status'] >= 200 && $result['status'] < 300) {
                    $tracker[(string) $memberId] = time();
                    $updated++;
                } else {
                    $errors++;
                }

                if (count($samples) < 3) {
                    $samples[] = [
                        'member_id' => $memberId,
                        'email' => $email,
                        'status' => $result['status'],
                        'body' => substr((string) ($result['body'] ?? $result['error'] ?? ''), 0, 200),
                    ];
                }
                usleep(200000);
            } else {
                $updated++;
                if (count($samples) < 3) {
                    $samples[] = ['member_id' => $memberId, 'email' => $email, 'dry_run' => true];
                }
            }
            $processed++;
        }
        $page++;
    }
    if (!$dryRun) {
        saveSyncTracker($tracker);
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