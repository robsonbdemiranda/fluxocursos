<?php
declare(strict_types=1);

final class MauticClient
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private int $timeoutSeconds;
    private ?string $token = null;
    private ?int $tokenExpiresAt = null;

    public function __construct(array $config)
    {
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        if ($baseUrl === '' || !preg_match('#^https?://#i', $baseUrl)) {
            throw new InvalidArgumentException('Mautic base URL inválida.');
        }

        $this->baseUrl = $baseUrl;
        $this->clientId = (string) ($config['client_id'] ?? '');
        $this->clientSecret = (string) ($config['client_secret'] ?? '');
        $this->timeoutSeconds = max(5, (int) ($config['timeout'] ?? 15));
    }

    public static function fromEnvironment(): self
    {
        return new self([
            'base_url' => self::cleanEnv('MAUTIC_BASE_URL'),
            'client_id' => self::cleanEnv('MAUTIC_PUBLIC_CLIENT_ID'),
            'client_secret' => self::cleanEnv('MAUTIC_PUBLIC_CLIENT_SECRET'),
        ]);
    }

    private static function cleanEnv(string $name): string
    {
        $value = (string) getenv($name);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/[\r\n\t]+/', '', $value) ?? '';
        return trim($value);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== ''
            && $this->clientId !== ''
            && $this->clientSecret !== '';
    }

    public function upsertContact(array $payload): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Mautic não configurado.');
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-mail inválido para Mautic.');
        }

        $contact = [
            'email' => $email,
            'firstname' => $payload['firstname'] ?? '',
            'lastname' => $payload['lastname'] ?? '',
            'phone' => $payload['phone'] ?? '',
            'company' => $payload['company'] ?? '',
        ];

        $body = array_filter($contact, static fn($value) => $value !== '');

        if (!empty($payload['tags'])) {
            $body['tags'] = $this->normalizeTags($payload['tags']);
        }

        $response = $this->request('POST', '/api/contacts/new', $body);
        $utm = $this->extractUtm($payload);
        if ($utm !== []) {
            $contactId = (int) ($response['contact']['id'] ?? 0);
            if ($contactId <= 0) {
                throw new RuntimeException('Mautic não retornou o contato para registrar UTMs.');
            }
            $this->request('POST', '/api/contacts/' . $contactId . '/utm/add', $utm);
        }

        return $response;
    }

    public function findContactByEmail(string $email): ?array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Mautic não configurado.');
        }

        $email = trim($email);
        if ($email === '') {
            return null;
        }

        $response = $this->request('GET', '/api/contacts', [
            'search' => 'email:' . $email,
            'limit' => 1,
        ]);

        $contacts = $response['contacts'] ?? [];
        return $contacts === [] ? null : reset($contacts);
    }

    private function normalizeTags(array $tags): array
    {
        $output = [];
        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag !== '') {
                $output[] = $tag;
            }
        }
        return $output;
    }

    private function extractUtm(array $payload): array
    {
        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $field) {
            if (isset($payload[$field]) && $payload[$field] !== '') {
                $utm[$field] = (string) $payload[$field];
            }
        }
        return $utm;
    }

    private function ensureToken(): void
    {
        if ($this->token !== null && $this->tokenExpiresAt !== null && time() < $this->tokenExpiresAt - 30) {
            return;
        }

        $ch = curl_init($this->baseUrl . '/oauth/v2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Falha ao autenticar no Mautic: ' . $error);
        }
        curl_close($ch);

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('Resposta inválida do Mautic durante autenticação.');
        }

        $this->token = (string) $data['access_token'];
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;
        $this->tokenExpiresAt = time() + max(60, $expiresIn);
    }

    public function getToken(): string
    {
        $this->ensureToken();
        return (string) $this->token;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $this->ensureToken();

        $url = $this->baseUrl . $path;
        $headers = ['Authorization: Bearer ' . $this->token];

        if ($method === 'GET') {
            $url .= '?' . http_build_query($body);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Falha ao chamar Mautic: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 401) {
            $this->token = null;
            $this->tokenExpiresAt = null;
            throw new RuntimeException('Token Mautic expirado.');
        }

        $data = json_decode($raw, true);
        if ($status >= 400) {
            throw new RuntimeException('Mautic retornou HTTP ' . $status . '.');
        }
        if (!is_array($data)) {
            throw new RuntimeException('Resposta inválida do Mautic.');
        }

        return $data;
    }
}
