<?php
/**
 * Plugin Name: Fluxo Cursos - BuddyBoss Mautic Bridge
 * Plugin URI: https://fluxocursos.com.br
 * Description: Envia eventos de cadastro e atualizacao de perfil do BuddyBoss para a vitrine Fluxo Cursos, que sincroniza com o Mautic.
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Author: Fluxo Cursos
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const FLUXO_BUDDYBOSS_VERSION = '1.0.0';
const FLUXO_BUDDYBOSS_OPTION = 'fluxo_buddyboss_mautic_settings';
const FLUXO_BUDDYBOSS_TIMEOUT = 10;

if (!class_exists('Fluxo_BuddyBoss_Mautic_Bridge')) {
    final class Fluxo_BuddyBoss_Mautic_Bridge
    {
        private static ?self $instance = null;

        public static function instance(): self
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct()
        {
            add_action('init', [$this, 'loadTextdomain']);
            add_action('admin_menu', [$this, 'registerMenu']);
            add_action('admin_init', [$this, 'registerSettings']);
            add_action('bp_core_activated_user', [$this, 'onActivated'], 20, 3);
            add_action('xprofile_updated_profile', [$this, 'onProfileUpdated'], 20, 5);
        }

        public function loadTextdomain(): void
        {
            load_plugin_textdomain('fluxo-buddyboss', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }

        public function getSettings(): array
        {
            $defaults = [
                'endpoint' => 'https://fluxocursos.com.br/api/webhook-buddyboss.php',
                'secret' => '',
                'send_signup' => 1,
                'send_update' => 1,
                'community_slug' => 'clube-do-doppler',
                'debug' => 0,
            ];
            $stored = get_option(FLUXO_BUDDYBOSS_OPTION, []);
            return is_array($stored) ? array_merge($defaults, $stored) : $defaults;
        }

        public function registerMenu(): void
        {
            add_options_page(
                'Fluxo Cursos - BuddyBoss Mautic',
                'Fluxo - BuddyBoss',
                'manage_options',
                'fluxo-buddyboss',
                [$this, 'renderSettingsPage']
            );
        }

        public function registerSettings(): void
        {
            register_setting(FLUXO_BUDDYBOSS_OPTION, FLUXO_BUDDYBOSS_OPTION, [
                'sanitize_callback' => [$this, 'sanitizeSettings'],
            ]);
        }

        public function sanitizeSettings(array $input): array
        {
            $current = $this->getSettings();
            return [
                'endpoint' => esc_url_raw(trim((string) ($input['endpoint'] ?? $current['endpoint']))),
                'secret' => trim((string) ($input['secret'] ?? $current['secret'])),
                'send_signup' => !empty($input['send_signup']) ? 1 : 0,
                'send_update' => !empty($input['send_update']) ? 1 : 0,
                'community_slug' => sanitize_title((string) ($input['community_slug'] ?? $current['community_slug'])),
                'debug' => !empty($input['debug']) ? 1 : 0,
            ];
        }

        public function renderSettingsPage(): void
        {
            if (!current_user_can('manage_options')) {
                wp_die('Sem permissao.');
            }
            $settings = $this->getSettings();
            ?>
            <div class="wrap">
                <h1>Fluxo Cursos - BuddyBoss Mautic</h1>
                <p>Envia eventos do BuddyBoss para a vitrine Fluxo Cursos, que sincroniza com o Mautic.</p>
                <form method="post" action="options.php">
                    <?php settings_fields(FLUXO_BUDDYBOSS_OPTION); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="endpoint">Endpoint do webhook</label></th>
                            <td><input id="endpoint" type="url" name="<?php echo esc_attr(FLUXO_BUDDYBOSS_OPTION); ?>[endpoint]" value="<?php echo esc_attr($settings['endpoint']); ?>" class="regular-text code" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="secret">Segredo compartilhado</label></th>
                            <td>
                                <input id="secret" type="text" name="<?php echo esc_attr(FLUXO_BUDDYBOSS_OPTION); ?>[secret]" value="<?php echo esc_attr($settings['secret']); ?>" class="regular-text code" required>
                                <p class="description">Defina a variavel <code>FLUXO_WEBHOOK_SECRET</code> na vitrine com o mesmo valor.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Eventos ativos</th>
                            <td>
                                <label><input type="checkbox" name="<?php echo esc_attr(FLUXO_BUDDYBOSS_OPTION); ?>[send_signup]" value="1" <?php checked($settings['send_signup'], 1); ?>> Novo cadastro</label><br>
                                <label><input type="checkbox" name="<?php echo esc_attr(FLUXO_BUDDYBOSS_OPTION); ?>[send_update]" value="1" <?php checked($settings['send_update'], 1); ?>> Atualizacao de perfil</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="community_slug">Slug da comunidade</label></th>
                            <td>
                                <input id="community_slug" type="text" name="<?php echo esc_attr(FLUXO_BUDDYBOSS_OPTION); ?>[community_slug]" value="<?php echo esc_attr($settings['community_slug']); ?>" class="regular-text">
                                <p class="description">Identificador usado para gerar a tag <code>comunidade_&lt;slug&gt;</code> no Mautic.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Depuracao</th>
                            <td><label><input type="checkbox" name="<?php echo esc_attr(FLUXO_BUDDYBOSS_OPTION); ?>[debug]" value="1" <?php checked($settings['debug'], 1); ?>> Registrar eventos no log do WordPress</label></td>
                        </tr>
                    </table>
                    <?php submit_button('Salvar configuracoes'); ?>
                </form>
            </div>
            <?php
        }

        public function onActivated(int $userId, string $key, $user): void
        {
            $settings = $this->getSettings();
            if (empty($settings['send_signup'])) {
                return;
            }

            $userObject = is_object($user) ? $user : get_userdata($userId);
            if (!$userObject || empty($userObject->user_email)) {
                return;
            }

            $email = (string) $userObject->user_email;
            $usermeta = is_array($userObject) ? $userObject : [];
            $displayName = (string) $userObject->display_name;
            $firstName = (string) $userObject->first_name;
            $lastName = (string) $userObject->last_name;

            $usermeta['display_name'] = $displayName;
            $usermeta['first_name'] = $firstName;
            $usermeta['last_name'] = $lastName;

            $payload = $this->buildPayload($userId, $email, 'cadastro_comunidade', $usermeta, $firstName, $lastName);
            $this->send($payload, $email);
        }

        private function isPendingSignup(int $userId): bool
        {
            global $wpdb;
            $status = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT user_status FROM {$wpdb->users} WHERE ID = %d",
                $userId
            ));
            return $status === 2;
        }

        public function onProfileUpdated(int $userId, array $postedFieldIds, array $errors, array $oldFieldData, array $newFieldData): void
        {
            $settings = $this->getSettings();
            if (empty($settings['send_update'])) {
                return;
            }
            if (!empty($errors)) {
                return;
            }
            $user = get_userdata($userId);
            if (!$user || empty($user->user_email)) {
                return;
            }
            $usermeta = array_map(static function ($entry) {
                return is_array($entry) && isset($entry['value']) ? $entry['value'] : '';
            }, $newFieldData);
            $payload = $this->buildPayload($userId, $user->user_email, 'perfil_atualizado_comunidade', $usermeta);
            $this->send($payload, $user->user_email);
        }

        private function buildPayload(int $userId, string $email, string $event, array $usermeta, string $firstName = '', string $lastName = ''): array
        {
            $settings = $this->getSettings();
            $user = get_userdata($userId);
            $displayName = $user ? $user->display_name : ($usermeta['display_name'] ?? '');

            if ($firstName === '') {
                $firstName = $user && !empty($user->first_name)
                    ? $user->first_name
                    : ($usermeta['first_name'] ?? '');
            }
            if ($lastName === '') {
                $lastName = $user && !empty($user->last_name)
                    ? $user->last_name
                    : ($usermeta['last_name'] ?? '');
            }

            $especialidade = '';
            $cidade = '';
            $crm = '';
            $telefone = '';

            if (function_exists('xprofile_get_field_data')) {
                $candidates = [
                    'especialidade' => ['Especialidad médica', 'Especialidade'],
                    'cidade'         => ['Ciudad', 'Cidade'],
                    'crm'            => ['Registro médico', 'CRM', 'Crm'],
                    'telefone'       => ['Teléfono', 'Telefone'],
                ];
                foreach ($candidates as $key => $names) {
                    foreach ($names as $name) {
                        $value = xprofile_get_field_data($name, $userId);
                        if (!empty($value)) {
                            ${$key} = (string) $value;
                            break;
                        }
                    }
                }
            }

            foreach ($usermeta as $key => $value) {
                if (!is_string($value)) {
                    continue;
                }
                $lowerKey = strtolower((string) $key);
                if ($lowerKey === 'telefone' || $lowerKey === 'phone') {
                    $telefone = $value;
                }
            }

            if (empty($telefone)) {
                $telefone = (string) get_user_meta($userId, 'phone', true);
            }

            return [
                'event' => $event,
                'user_id' => $userId,
                'community_slug' => $settings['community_slug'],
                'customer' => [
                    'name' => trim($firstName . ' ' . $lastName) ?: $displayName,
                    'email' => $email,
                    'phone' => $telefone,
                ],
                'profile' => [
                    'especialidade' => $especialidade,
                    'cidade' => $cidade,
                    'crm' => $crm,
                ],
            ];
        }

        private function send(array $payload, string $email): void
        {
            $settings = $this->getSettings();
            $endpoint = (string) $settings['endpoint'];
            $secret = (string) $settings['secret'];
            if ($endpoint === '' || $secret === '' || $email === '') {
                return;
            }
            $body = wp_json_encode($payload);
            $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
            $args = [
                'method' => 'POST',
                'timeout' => FLUXO_BUDDYBOSS_TIMEOUT,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Fluxo-Signature' => $signature,
                    'X-Fluxo-Source' => 'fluxo-buddyboss-mautic/' . FLUXO_BUDDYBOSS_VERSION,
                ],
                'body' => $body,
            ];
            $response = wp_remote_post($endpoint, $args);
            if (is_wp_error($response)) {
                $this->log('Erro ao enviar webhook: ' . $response->get_error_message());
                return;
            }
            $status = (int) wp_remote_retrieve_response_code($response);
            $body = (string) wp_remote_retrieve_body($response);
            if ($status >= 400) {
                $this->log(sprintf('Webhook retornou status %d: %s', $status, $body));
            } else {
                $this->log(sprintf('Webhook %s enviado (status %d).', $payload['event'], $status));
            }
        }

        private function log(string $message): void
        {
            if (!$this->getSettings()['debug']) {
                return;
            }
            if (!function_exists('error_log')) {
                return;
            }
            error_log('[Fluxo - BuddyBoss] ' . $message);
        }
    }
}

add_action('plugins_loaded', static function (): void {
    Fluxo_BuddyBoss_Mautic_Bridge::instance();
});
