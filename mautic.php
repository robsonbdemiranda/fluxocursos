<?php
declare(strict_types=1);

final class MauticClient
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $username;
    private string $password;
    private int $timeoutSeconds;
    private ?string $token = null;

    public function __construct(array $config)
    {
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        if ($baseUrl === '' || !preg_match('#^https?://#i', $baseUrl)) {
            throw new InvalidArgumentException('Mautic base URL inválida.');
        }

        $this->baseUrl = $baseUrl;
        $this->clientId = (string) ($config['client_id'] ?? '');
        $this->clientSecret = (string) ($config['client_secret'] ?? '');
        $this->username = (string) ($config['username'] ?? '');
        $this->password = (string) ($config['password'] ?? '');
        $this->timeoutSeconds = max(5, (int) ($config['timeout'] ?? 15));
    }

    public static function fromEnvironment(): self
    {
        return new self([
            'base_url' => getenv('MAUTIC_BASE_URL') ?: '',
            'client_id' => getenv('MAUTIC_PUBLIC_CLIENT_ID') ?: '',
            'client_secret' => getenv('MAUTIC_PUBLIC_CLIENT_SECRET') ?: '',
            'username' => getenv('MAUTIC_PUBLIC_USER') ?: '',
            'password' => getenv('MAUTIC_PUBLIC_PASS') ?: '',
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== ''
            && $this->clientId !== ''
            && $this->clientSecret !== ''
            && $this->username !== ''
            && $this->password !== '';
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

        return $this->request('POST', '/api/contacts/new', $body, true);
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
        ], true);

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

    private function ensureToken(): void
    {
        if ($this->token !== null) {
            return;
        }

        $ch = curl_init($this->baseUrl . '/s/oauth/v2/access_token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'password',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'username' => $this->username,
                'password' => $this->password,
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
    }

    private function request(string $method, string $path, array $body = [], bool $useSlimPrefix = false): array
    {
        $this->ensureToken();

        $prefix = $useSlimPrefix ? '/s' : '';
        $url = $this->baseUrl . $prefix . $path;
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
            throw new RuntimeException('Token Mautic expirado.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Resposta inválida do Mautic.');
        }

        return $data;
    }
}