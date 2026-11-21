<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_Updater_Server {
    private static $instance = null;
    public static function get_instance() {
        if (null === self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_filter('site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
    }

    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $plugin_file = 'keyvault-manager/keyvault-manager.php';
        if (!isset($transient->checked[$plugin_file])) {
            return $transient;
        }

        $current_version = $transient->checked[$plugin_file];
        $license_key = get_option('kvm_license_key', '');
        $namespace = defined('KVM_REST_NAMESPACE') ? KVM_REST_NAMESPACE : get_option('kvm_rest_namespace', 'kvm/v1');
        
        $response = wp_remote_post(site_url('/wp-json/' . $namespace . '/update-check'), array(
            'timeout' => 10,
            'body' => array('license_key' => $license_key)
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($data['new_version']) && version_compare($current_version, $data['new_version'], '<')) {
                $obj = new stdClass();
                $obj->slug = $data['slug'] ?? 'keyvault-manager';
                $obj->plugin = $plugin_file;
                $obj->new_version = $data['new_version'];
                $obj->url = site_url();
                $obj->package = $data['package'];
                $transient->response[$plugin_file] = $obj;
            }
        }

        return $transient;
    }

    public function plugin_info($res, $action, $args) {
        if ($action !== 'plugin_information') {
            return $res;
        }

        $slug = get_option('kvm_plugin_slug', 'keyvault-manager');
        if (isset($args->slug) && $args->slug === $slug) {
            $license_key = get_option('kvm_license_key', '');
            $namespace = defined('KVM_REST_NAMESPACE') ? KVM_REST_NAMESPACE : get_option('kvm_rest_namespace', 'kvm/v1');

            $response = wp_remote_post(site_url('/wp-json/' . $namespace . '/update-check'), array(
                'timeout' => 10,
                'body' => array('license_key' => $license_key)
            ));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($data['new_version'])) {
                    $info = new stdClass();
                    $info->name = 'KeyVault Manager';
                    $info->slug = $slug;
                    $info->version = $data['new_version'];
                    $info->download_link = $data['package'];
                    $info->sections = array(
                        'description' => 'Secure license management and plugin distribution suite.'
                    );
                    return $info;
                }
            }
        }

        return $res;
    }
}