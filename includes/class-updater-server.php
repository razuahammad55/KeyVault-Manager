<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Self-hosted update distribution server.
 *
 * Client products call /update-check (with a license key + current version)
 * and download builds from /download-update (key-gated, served from the ZIP
 * path configured in Settings).
 */
class KeyVault_Manager_Updater_Server {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        $namespace = defined('KVM_REST_NAMESPACE') ? KVM_REST_NAMESPACE : 'kvm/v1';

        register_rest_route($namespace, '/update-check', array(
            'methods'             => array('GET', 'POST'),
            'callback'            => array($this, 'api_update_check'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/download-update', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'api_download_update'),
            'permission_callback' => '__return_true',
        ));
    }

    private function validate_license($license_key) {
        $license_key = sanitize_text_field((string) $license_key);
        if ($license_key === '') {
            return null;
        }
        $lic = KeyVault_Manager_DB::get_instance()->find_license($license_key);
        if (!$lic || $lic->status !== 'active') {
            return null;
        }
        // Reject expired licenses (grace window applies, consistent with /activate).
        if (!empty($lic->expires_at)) {
            $grace_days = intval(get_option('kvm_grace_period_days', '7'));
            $cutoff     = strtotime($lic->expires_at) - ($grace_days * DAY_IN_SECONDS);
            if (strtotime(current_time('mysql')) > $cutoff) {
                return null;
            }
        }
        return $lic;
    }

    public function api_update_check($request) {
        global $wpdb;

        $license_key = sanitize_text_field((string) $request->get_param('license_key'));
        $lic = $this->validate_license($license_key);
        if (!$lic) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid or expired license key.'), 403);
        }

        $current_version = sanitize_text_field((string) $request->get_param('version'));
        $new_version     = (string) KeyVault_Manager_DB::opt('client_version', '');

        if ($new_version === '') {
            return new WP_REST_Response(array('success' => true, 'update_available' => false), 200);
        }

        if ($current_version !== '' && version_compare($current_version, $new_version, '>=')) {
            return new WP_REST_Response(array('success' => true, 'update_available' => false), 200);
        }

        $download_url = add_query_arg(
            array('key' => $license_key),
            site_url('/wp-json/' . (defined('KVM_REST_NAMESPACE') ? KVM_REST_NAMESPACE : 'kvm/v1') . '/download-update')
        );

        return new WP_REST_Response(array(
            'success'          => true,
            'update_available' => true,
            'new_version'      => $new_version,
            'package'          => $download_url,
            'slug'             => KeyVault_Manager_DB::opt('client_plugin_slug', 'client-plugin'),
        ), 200);
    }

    public function api_download_update($request) {
        $license_key = sanitize_text_field((string) $request->get_param('key'));
        $lic = $this->validate_license($license_key);
        if (!$lic) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid or expired license key.'), 403);
        }

        $zip_setting = trim((string) KeyVault_Manager_DB::opt('zip_file_path', ''));
        if ($zip_setting === '') {
            return new WP_REST_Response(array('success' => false, 'message' => 'No client package is configured. Set the ZIP path in Settings.'), 404);
        }

        if (preg_match('#^https?://#i', $zip_setting)) {
            // Remote source — proxy the download through the hub so the URL stays gated.
            $response = wp_remote_get($zip_setting, array('timeout' => 120));
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return new WP_REST_Response(array('success' => false, 'message' => 'Unable to read the configured package URL.'), 404);
            }
            $body     = wp_remote_retrieve_body($response);
            $filename = basename(parse_url($zip_setting, PHP_URL_PATH));
            if ($filename === '' || substr($filename, -4) !== '.zip') {
                $filename = 'client-plugin.zip';
            }
            nocache_headers();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
            header('Content-Length: ' . strlen($body));
            echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- binary file stream.
            exit;
        }

        $file = wp_normalize_path($zip_setting);
        if (substr($file, -4) !== '.zip' || !file_exists($file)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Configured package file was not found on the server.'), 404);
        }

        $filename = basename($file);
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Content-Length: ' . filesize($file));
        if (ob_get_level()) {
            ob_end_clean();
        }
        readfile($file); // phpcs:ignore WordPress.WP.AlternativeFunctions -- streaming a binary asset.
        exit;
    }
}
