<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Client-side updater snippet generator.
 *
 * The hub owner copies this snippet into the plugin(s) they distribute so the
 * distributed products check this hub for updates using the customer's
 * license key. The snippet is shown (with copy button) in the Settings tab.
 */
class KeyVault_Manager_Updater {

    /**
     * Build the ready-to-paste updater class for client products.
     *
     * @return string PHP source code.
     */
    public static function get_client_snippet() {
        $hub_url  = esc_url(site_url());
        $rest_ns  = esc_html(defined('KVM_REST_NAMESPACE') ? KVM_REST_NAMESPACE : 'kvm/v1');
        $brand    = esc_html(kvm_brand_name());

        return "<?php
/**
 * {$brand} — Client Product Updater
 * ---------------------------------------------------------------------------
 * Include this file in the client plugin you distribute (or paste the class
 * into the plugin's main file). Configure the constants, then call:
 *
 *     add_action('plugins_loaded', array('KV_Client_Updater', 'init'));
 *
 * Customers enter their license key on the client plugin's settings page,
 * which is stored in the KBV_CLIENT_LICENSE_OPT option.
 */
if (!defined('ABSPATH')) { exit; }

class KV_Client_Updater {

    const HUB_URL            = '{$hub_url}';
    const REST_NS            = '{$rest_ns}';
    const PLUGIN_BASENAME    = 'client-plugin/client-plugin.php'; // basename() of YOUR product's main file
    const LICENSE_OPT        = 'kv_client_license_key';

    public static function get_license_key() {
        return sanitize_text_field(get_option(self::LICENSE_OPT, ''));
    }

    public static function init() {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject_update'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 20, 3);
        add_action('admin_menu', array(__CLASS__, 'register_settings_field'));
    }

    private static function fetch_update_info() {
        $response = wp_remote_post(self::HUB_URL . '/wp-json/' . self::REST_NS . '/update-check', array(
            'timeout' => 15,
            'body'    => array(
                'license_key' => self::get_license_key(),
                'version'     => defined('YOUR_PLUGIN_VERSION') ? YOUR_PLUGIN_VERSION : '0.0.0',
            ),
        ));
        if (is_wp_error(\$response) || wp_remote_retrieve_response_code(\$response) !== 200) {
            return false;
        }
        \$data = json_decode(wp_remote_retrieve_body(\$response), true);
        if (empty(\$data['update_available'])) {
            return false;
        }
        return \$data;
    }

    public static function inject_update(\$transient) {
        if (empty(\$transient->checked) || !isset(\$transient->checked[self::PLUGIN_BASENAME])) {
            return \$transient;
        }
        \$info = self::fetch_update_info();
        if (!\$info || empty(\$info['package'])) {
            return \$transient;
        }
        \$obj = new stdClass();
        \$obj->slug        = \$info['slug'];
        \$obj->plugin      = self::PLUGIN_BASENAME;
        \$obj->new_version = \$info['new_version'];
        \$obj->url         = self::HUB_URL;
        \$obj->package     = \$info['package'];
        \$transient->response[self::PLUGIN_BASENAME] = \$obj;
        return \$transient;
    }

    public static function plugin_info(\$result, \$action, \$args) {
        if (\$action !== 'plugin_information' || !isset(\$args->slug) || \$args->slug !== 'client-plugin') {
            return \$result;
        }
        \$info = self::fetch_update_info();
        if (!\$info) {
            return \$result;
        }
        \$api = new stdClass();
        \$api->name           = 'Client Product';
        \$api->slug           = \$info['slug'];
        \$api->version        = \$info['new_version'];
        \$api->download_link  = \$info['package'];
        \$api->sections       = array('description' => 'Licensed build delivered from your vendor hub.');
        return \$api;
    }

    public static function register_settings_field() {
        add_settings_field(
            'kv_client_license_key',
            'License Key',
            function () {
                \$v = esc_attr(self::get_license_key());
                echo \"<input type='text' class='regular-text' name='kv_client_license_key' value='{\$v}'>\";
            },
            'general'
        );
        register_setting('general', 'kv_client_license_key', 'sanitize_text_field');
    }
}
";
    }
}
