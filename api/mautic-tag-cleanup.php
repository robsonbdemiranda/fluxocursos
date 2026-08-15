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

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Método não permitido.');
}

$secret = trim((string) getenv('FLUXO_WEBHOOK_SECRET'));
if ($secret === '' || !hash_equals('sha256=' . hash_hmac('sha256', '', $secret), $_SERVER['HTTP_X_FLUXO_SIGNATURE'] ?? '')) {
    $readToken = trim((string) ($_GET['token'] ?? ''));
    if ($readToken === '' || !hash_equals($secret, $readToken)) {
        respond(401, false, 'Token inválido.');
    }
}

$client = MauticClient::fromEnvironment();
if (!$client->isConfigured()) {
    respond(500, false, 'Mautic não configurado.');
}

$limit = isset($_GET['limit']) ? max(0, (int) $_GET['limit']) : 0;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
$dryRun = !empty($_GET['dry_run']);

$TAG_PREFIX = 'comunidade_';
$TAG_PREFIX_KEEP = [
    'comunidade_clube_doppler',
    'comunidade_clube-do-doppler',
    'comunidade_importacao_em_massa',
    'comunidade_novo_cadastro',
    'comunidade_perfil_atualizado',
];
$ESPECIALIDADES_VALIDAS = [
    'cirurgiao_vascular',
    'angiologista',
    'ecocardiografista',
    'ultrassonografista',
    'radiologista',
    'outros',
];

function filterTags(array $tags, array $keep, array $especialidades): array
{
    $out = [];
    foreach ($tags as $tag) {
        if (!is_string($tag) || $tag === '') {
            continue;
        }
        if (in_array($tag, $keep, true)) {
            $out[] = $tag;
            continue;
        }
        if (preg_match('/^comunidade_especialidade_(.+)$/', $tag, $m)) {
            $slug = $m[1];
            if (in_array($slug, $especialidades, true)) {
                $out[] = $tag;
            }
            continue;
        }
        if (preg_match('/^comunidade_c[a-z0-9_]+_a-[0-9]+/', $tag)) {
            continue;
        }
        if (preg_match('/^comunidade_especialidade_a-[0-9]+/', $tag)) {
            continue;
        }
        if (preg_match('/^comunidade_cidade_a-[0-9]+/', $tag)) {
            continue;
        }
        $out[] = $tag;
    }

        $invalid = ['comunidade_crm_203270', 'comunidade_especialidade_a-1-i-0-s-19-cirurgi-o-vascular'];

        $cleaned = [];
        foreach ($out as $tag) {
            if (in_array($tag, $invalid, true)) {
                continue;
            }
            if (preg_match('/^comunidade_crm_[0-9]+/', $tag)) {
                continue;
            }
            if (preg_match('/^comunidade_crm_[a-z]{2,5}$/', $tag)) {
                continue;
            }
            $cleaned[] = $tag;
        }

        return array_values(array_unique($cleaned));
}

$processed = 0;
$updated = 0;
$skipped = 0;
$errors = 0;
$samples = [];

try {
    $page = 1;
    $perPage = 100;
    while (true) {
        $url = sprintf('%s/api/contacts?search=tag:comunidade_clube_doppler&limit=%d&start=%d', $client->getBaseUrl(), $perPage, ($page - 1) * $perPage);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $client->getToken(),
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status >= 400) {
            throw new RuntimeException('Mautic status ' . $status . ': ' . substr((string) $body, 0, 200));
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data) || !isset($data['contacts']) || !is_array($data['contacts'])) {
            break;
        }
        $contacts = $data['contacts'];
        if (empty($contacts)) {
            break;
        }
        foreach ($contacts as $contactId => $contact) {
            if (!is_array($contact)) {
                continue;
            }
            $processed++;
            if ($offset > 0 && $processed <= $offset) {
                continue;
            }
            if ($limit > 0 && $updated >= $limit) {
                break 2;
            }
            $currentTags = [];
            if (isset($contact['tags']) && is_array($contact['tags'])) {
                foreach ($contact['tags'] as $tag) {
                    if (is_array($tag) && isset($tag['tag'])) {
                        $currentTags[] = (string) $tag['tag'];
                    } elseif (is_string($tag)) {
                        $currentTags[] = $tag;
                    }
                }
            }
            $cleaned = filterTags($currentTags, $TAG_PREFIX_KEEP, $ESPECIALIDADES_VALIDAS);
            if ($cleaned === $currentTags) {
                $skipped++;
                continue;
            }
            if (!$dryRun) {
                $updateUrl = sprintf('%s/api/contacts/%d/edit', $client->getBaseUrl(), (int) $contactId);
                $ch = curl_init($updateUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => 'PATCH',
                    CURLOPT_POSTFIELDS => json_encode(['tags' => $cleaned], JSON_UNESCAPED_UNICODE),
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $client->getToken(),
                        'Content-Type: application/json',
                    ],
                    CURLOPT_TIMEOUT => 30,
                ]);
                $response = curl_exec($ch);
                $rStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($rStatus >= 200 && $rStatus < 300) {
                    $updated++;
                } else {
                    $errors++;
                }
            } else {
                $updated++;
            }
            if (count($samples) < 3) {
                $samples[] = [
                    'contact_id' => (int) $contactId,
                    'email' => (string) ($contact['fields']['core']['email']['value'] ?? ''),
                    'removed' => array_values(array_diff($currentTags, $cleaned)),
                    'kept' => $cleaned,
                ];
            }
        }
        $page++;
    }
} catch (Throwable $error) {
    respond(500, false, 'Erro durante limpeza: ' . $error->getMessage(), [
        'processed' => $processed,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'samples' => $samples,
    ]);
}

respond(200, true, 'Limpeza concluida.', [
    'processed' => $processed,
    'updated' => $updated,
    'skipped' => $skipped,
    'errors' => $errors,
    'dry_run' => $dryRun,
    'samples' => $samples,
]);