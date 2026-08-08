<?php
/**
 * Plugin Name: Fluxo Cursos - Mautic Bridge
 * Plugin URI: https://fluxocursos.com.br
 * Description: Envia eventos do WooCommerce e LearnDash para o endpoint de webhook da vitrine Fluxo Cursos, que sincroniza com o Mautic.
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * WC requires at least: 7.0
 * Author: Fluxo Cursos
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const FLUXO_WOO_MAUTIC_VERSION = '1.0.0';
const FLUXO_WOO_MAUTIC_OPTION = 'fluxo_woo_mautic_settings';
const FLUXO_WOO_MAUTIC_TIMEOUT = 10;

if (!class_exists('Fluxo_Woo_Mautic_Bridge')) {
    final class Fluxo_Woo_Mautic_Bridge
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
            add_action('woocommerce_order_status_completed', [$this, 'onOrderCompleted']);
            add_action('woocommerce_order_status_processing', [$this, 'onOrderCompleted']);
            add_action('woocommerce_order_status_cancelled', [$this, 'onOrderCancelled']);
            add_action('woocommerce_order_status_refunded', [$this, 'onOrderRefunded']);
            add_action('learndash_course_completed', [$this, 'onLearnDashCourseCompleted'], 20, 1);
            add_action('learndash_lesson_completed', [$this, 'onLearnDashLessonCompleted'], 20, 1);
        }

        public function loadTextdomain(): void
        {
            load_plugin_textdomain('fluxo-woo-mautic', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }

        public function getSettings(): array
        {
            $defaults = [
                'endpoint' => 'https://fluxocursos.com.br/api/webhook-woo.php',
                'secret' => '',
                'send_completed' => 1,
                'send_cancelled' => 1,
                'send_refunded' => 1,
                'send_enrolled' => 1,
                'send_completed_course' => 1,
                'debug' => 0,
            ];
            $stored = get_option(FLUXO_WOO_MAUTIC_OPTION, []);
            return is_array($stored) ? array_merge($defaults, $stored) : $defaults;
        }

        public function registerMenu(): void
        {
            add_options_page(
                'Fluxo Cursos - Mautic Bridge',
                'Fluxo - Mautic',
                'manage_options',
                'fluxo-woo-mautic',
                [$this, 'renderSettingsPage']
            );
        }

        public function registerSettings(): void
        {
            register_setting(FLUXO_WOO_MAUTIC_OPTION, FLUXO_WOO_MAUTIC_OPTION, [
                'sanitize_callback' => [$this, 'sanitizeSettings'],
            ]);
        }

        public function sanitizeSettings(array $input): array
        {
            $current = $this->getSettings();
            return [
                'endpoint' => esc_url_raw(trim((string) ($input['endpoint'] ?? $current['endpoint']))),
                'secret' => trim((string) ($input['secret'] ?? $current['secret'])),
                'send_completed' => !empty($input['send_completed']) ? 1 : 0,
                'send_cancelled' => !empty($input['send_cancelled']) ? 1 : 0,
                'send_refunded' => !empty($input['send_refunded']) ? 1 : 0,
                'send_enrolled' => !empty($input['send_enrolled']) ? 1 : 0,
                'send_completed_course' => !empty($input['send_completed_course']) ? 1 : 0,
                'debug' => !empty($input['debug']) ? 1 : 0,
            ];
        }

        public function renderSettingsPage(): void
        {
            if (!current_user_can('manage_options')) {
                wp_die('Sem permissão.');
            }
            $settings = $this->getSettings();
            ?>
            <div class="wrap">
                <h1>Fluxo Cursos - Mautic Bridge</h1>
                <p>Configura o envio de eventos do WooCommerce e LearnDash para a vitrine da Fluxo Cursos, que sincroniza com o Mautic.</p>
                <form method="post" action="options.php">
                    <?php settings_fields(FLUXO_WOO_MAUTIC_OPTION); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="endpoint">Endpoint do webhook</label></th>
                            <td><input id="endpoint" type="url" name="<?php echo esc_attr(FLUXO_WOO_MAUTIC_OPTION); ?>[endpoint]" value="<?php echo esc_attr($settings['endpoint']); ?>" class="regular-text code" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="secret">Segredo compartilhado</label></th>
                            <td>
                                <input id="secret" type="text" name="<?php echo esc_attr(FLUXO_WOO_MAUTIC_OPTION); ?>[secret]" value="<?php echo esc_attr($settings['secret']); ?>" class="regular-text code" required>
                                <p class="description">Defina a variavel <code>FLUXO_WEBHOOK_SECRET</code> na vitrine com o mesmo valor.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Eventos ativos</th>
                            <td>
                                <label><input type="checkbox" name="<?php echo esc_attr(FLUXO_WOO_MAUTIC_OPTION); ?>[send_completed]" value="1" <?php checked($settings['send_completed'], 1); ?>> Pedido pago</label><br>
                                <label><input type="checkbox" name="<?php echo esc_attr(FLUXO_WOO_MAUTIC_OPTION); ?>[send_cancelled]" value="1" <?php checked($settings['send_cancelled'], 1); ?>> Pedido cancelado</label><br>
                                <label><input type="checkbox" name="<?php echo esc_attr(FLUXO_WOO_MAUTIC_OPTION); ?>[send_refunded]" value="1" <?php checked($settings['send_refunded'], 1); ?>> Reembolso</label><br>
                                <label><input type="checkbox" name="<?php echo esc_attr(FLUXO_WOO_MAUTIC_OPTION); ?>[send_enrolled]" value="1" <?php checked($settings['send_enrolled'], 1); ?>> Matrícula LearnDash criada</label><br>
                                <label><input type="checkbox" name="<?php echo esc_attr(FLUXO_WOO_MAUTIC_OPTION); ?>[send_completed_course]" value="1" <?php checked($settings['send_completed_course'], 1); ?>> Curso LearnDash concluído</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Depuração</th>
                            <td><label><input type="checkbox" name="<?php echo esc_attr(FLUXO_WOO_MAUTIC_OPTION); ?>[debug]" value="1" <?php checked($settings['debug'], 1); ?>> Registrar eventos no log do WooCommerce</label></td>
                        </tr>
                    </table>
                    <?php submit_button('Salvar configurações'); ?>
                </form>
            </div>
            <?php
        }

        public function onOrderCompleted(int $orderId): void
        {
            $settings = $this->getSettings();
            if (empty($settings['send_completed'])) {
                return;
            }
            $this->dispatch($orderId, 'pedido_pago');
        }

        public function onOrderCancelled(int $orderId): void
        {
            $settings = $this->getSettings();
            if (empty($settings['send_cancelled'])) {
                return;
            }
            $this->dispatch($orderId, 'pedido_cancelado');
        }

        public function onOrderRefunded(int $orderId): void
        {
            $settings = $this->getSettings();
            if (empty($settings['send_refunded'])) {
                return;
            }
            $this->dispatch($orderId, 'reembolso');
        }

        public function onLearnDashCourseCompleted(array $data): void
        {
            $settings = $this->getSettings();
            if (empty($settings['send_completed_course'])) {
                return;
            }
            $courseId = (int) ($data['course']->ID ?? 0);
            $userId = (int) ($data['user']->ID ?? 0);
            if ($courseId <= 0 || $userId <= 0) {
                return;
            }
            $this->dispatchEnrollment($userId, $courseId, 'matricula_concluida');
        }

        public function onLearnDashLessonCompleted(array $data): void
        {
            $settings = $this->getSettings();
            if (empty($settings['send_enrolled'])) {
                return;
            }
            $lessonId = (int) ($data['lesson']->ID ?? 0);
            $userId = (int) ($data['user']->ID ?? 0);
            $courseId = (int) ($data['course']->ID ?? 0);
            if ($lessonId <= 0 || $userId <= 0) {
                return;
            }
            if ($courseId <= 0) {
                $courseId = (int) get_post_meta($lessonId, 'course_id', true);
            }
            if ($courseId <= 0) {
                return;
            }
            $alreadyDispatched = (int) get_user_meta($userId, '_fluxo_enrollment_dispatched', true);
            if ($alreadyDispatched === $courseId) {
                return;
            }
            update_user_meta($userId, '_fluxo_enrollment_dispatched', $courseId);
            $this->dispatchEnrollment($userId, $courseId, 'matricula_criada');
        }

        private function dispatch(int $orderId, string $event): void
        {
            if (!function_exists('wc_get_order')) {
                return;
            }
            $order = wc_get_order($orderId);
            if (!$order) {
                return;
            }
            $payload = $this->buildOrderPayload($order, $event);
            $this->send($payload);
        }

        private function dispatchEnrollment(int $userId, int $courseId, string $event): void
        {
            $user = get_userdata($userId);
            $course = get_post($courseId);
            if (!$user || !$course) {
                return;
            }
            $payload = [
                'event' => $event,
                'order_id' => '',
                'customer' => [
                    'name' => trim($user->first_name . ' ' . $user->last_name) ?: $user->display_name,
                    'email' => $user->user_email,
                    'phone' => get_user_meta($userId, 'billing_phone', true) ?: '',
                ],
                'products' => [],
                'course_slug' => $course->post_name,
                'total' => '',
            ];
            $this->send($payload);
        }

        private function buildOrderPayload($order, string $event): array
        {
            $items = [];
            foreach ($order->get_items() as $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }
                $product = $item->get_product();
                $items[] = [
                    'name' => $item->get_name(),
                    'slug' => $product ? $product->get_slug() : '',
                    'id' => $item->get_product_id(),
                    'quantity' => (int) $item->get_quantity(),
                    'total' => (string) $item->get_total(),
                ];
            }
            return [
                'event' => $event,
                'order_id' => (string) $order->get_id(),
                'customer' => [
                    'name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: $order->get_billing_company(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                ],
                'products' => $items,
                'total' => (string) $order->get_total(),
            ];
        }

        private function send(array $payload): void
        {
            $settings = $this->getSettings();
            $endpoint = (string) $settings['endpoint'];
            $secret = (string) $settings['secret'];
            if ($endpoint === '' || $secret === '') {
                return;
            }
            $body = wp_json_encode($payload);
            $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
            $args = [
                'method' => 'POST',
                'timeout' => FLUXO_WOO_MAUTIC_TIMEOUT,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Fluxo-Signature' => $signature,
                    'X-Fluxo-Source' => 'fluxo-woo-mautic/' . FLUXO_WOO_MAUTIC_VERSION,
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
            if (!function_exists('wc_get_logger')) {
                return;
            }
            wc_get_logger()->info('[Fluxo - Mautic] ' . $message, ['source' => 'fluxo-woo-mautic']);
        }
    }
}

add_action('plugins_loaded', static function (): void {
    Fluxo_Woo_Mautic_Bridge::instance();
});
