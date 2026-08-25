<?php
declare(strict_types=1);

require __DIR__ . '/mautic.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(int $status, bool $success, string $message, array $data = []): void
{
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function textLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Método não permitido.');
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(200, true, 'Download liberado.');
}

$nome = trim((string) ($_POST['nome'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$material = trim((string) ($_POST['material'] ?? ''));
$privacyAccepted = (string) ($_POST['privacidade'] ?? '') === '1';
$marketingConsent = (string) ($_POST['consentimento_marketing'] ?? '') === '1';

$materials = [
    'tabela-cim-aric' => 'Tabela_CIM_ARIC_Fluxo_Cursos.pdf',
    'tabela-cim-caps' => 'Tabela_CIM_CAPS_Fluxo_Cursos.pdf',
    'tabela-cim-elsa' => 'Tabela_CIM_ELSA_Brasil_Fluxo_Cursos.pdf',
    'tabela-cim-mesa' => 'Tabela_CIM_MESA_Fluxo_Cursos.pdf',
];

if (textLength($nome) < 2 || textLength($nome) > 100 || preg_match('/[\r\n]/', $nome)) {
    respond(422, false, 'Informe um nome válido.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
    respond(422, false, 'Informe um e-mail válido.');
}
if (textLength($telefone) > 30 || preg_match('/[\r\n]/', $telefone)) {
    respond(422, false, 'Informe um telefone válido.');
}
if (!$privacyAccepted) {
    respond(422, false, 'Confirme que leu a Política de Privacidade para continuar.');
}
if (!isset($materials[$material])) {
    respond(422, false, 'Material inválido.');
}

$downloadName = $materials[$material];
$downloadPath = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $downloadName;
if (!is_file($downloadPath)) {
    error_log('Arquivo de material não encontrado: ' . $downloadPath);
    respond(503, false, 'O material está temporariamente indisponível. Tente novamente mais tarde.');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fluxocursos-download-' . hash('sha256', $ip . '|' . $material);
$now = time();
if (is_file($rateLimitFile) && $now - (int) file_get_contents($rateLimitFile) < 60) {
    respond(429, false, 'Aguarde um minuto antes de baixar novamente.');
}

try {
    $client = MauticClient::fromEnvironment();
    if ($client->isConfigured()) {
        [$firstname, $lastname] = splitName($nome);
        $tags = ['download_material', 'download_' . sanitizeTag($material), 'site-fluxocursos'];
        if ($marketingConsent) {
            $tags[] = 'consentimento_marketing';
        }
        $client->upsertContact([
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'phone' => $telefone,
            'tags' => $tags,
        ]);
    }
} catch (Throwable $error) {
    error_log('Falha ao sincronizar download no Mautic: ' . $error->getMessage());
}

file_put_contents($rateLimitFile, (string) $now, LOCK_EX);
respond(200, true, 'Download liberado para ' . $email, [
    'downloadUrl' => 'assets/' . rawurlencode($downloadName),
    'downloadName' => $downloadName,
]);

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
