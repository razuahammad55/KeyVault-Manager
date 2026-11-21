<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_User_Auth {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!session_id() && !headers_sent()) {
            @session_start();
        }
    }

    public function generate_strong_temp_password($length = 14) {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghijkmnopqrstuvwxyz';
        $numbers   = '23456789';
        $special   = '!@#$%^&*';

        $pwd = array(
            $uppercase[random_int(0, strlen($uppercase) - 1)],
            $lowercase[random_int(0, strlen($lowercase) - 1)],
            $numbers[random_int(0, strlen($numbers) - 1)],
            $special[random_int(0, strlen($special) - 1)]
        );

        $all_chars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $pwd[] = $all_chars[random_int(0, strlen($all_chars) - 1)];
        }

        shuffle($pwd);
        return implode('', $pwd);
    }

    public function get_or_create_user($name, $email, $custom_password = '') {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_users = $wpdb->prefix . $slug . '_users';
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE email = %s", sanitize_email($email)));
        if ($user) {
            return array('user' => $user, 'is_new' => false, 'temp_pass' => '');
        }

        $temp_pass = !empty($custom_password) ? $custom_password : $this->generate_strong_temp_password(14);
        $pass_hash = password_hash($temp_pass, PASSWORD_BCRYPT);
        $wpdb->insert($table_users, array(
            'name'          => sanitize_text_field($name),
            'email'         => sanitize_email($email),
            'password_hash' => $pass_hash,
            'temp_password' => $temp_pass
        ));
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE id = %d", $wpdb->insert_id));
        return array('user' => $user, 'is_new' => true, 'temp_pass' => $temp_pass);
    }

    public function update_user_info($user_id, $name, $email, $new_password = '') {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_users = $wpdb->prefix . $slug . '_users';
        $uid = intval($user_id);
        
        $update_data = array(
            'name'  => sanitize_text_field($name),
            'email' => sanitize_email($email)
        );

        if (!empty($new_password)) {
            $update_data['password_hash'] = password_hash($new_password, PASSWORD_BCRYPT);
            $update_data['temp_password'] = $new_password;
        }

        return $wpdb->update($table_users, $update_data, array('id' => $uid));
    }

    public function authenticate($email, $password) {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_users = $wpdb->prefix . $slug . '_users';
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE email = %s", sanitize_email($email)));
        if ($user && password_verify($password, $user->password_hash)) {
            $_SESSION['kvm_user_id'] = $user->id;
            $_SESSION['kvm_user_email'] = $user->email;
            return $user;
        }
        return false;
    }

    public function get_current_user() {
        if (!empty($_SESSION['kvm_user_id'])) {
            global $wpdb;
            $slug = get_option('kvm_plugin_slug', KVM_SLUG);
            $table_users = $wpdb->prefix . $slug . '_users';
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE id = %d", intval($_SESSION['kvm_user_id'])));
        }
        return false;
    }

    public function change_password($user_id, $old_password, $new_password) {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_users = $wpdb->prefix . $slug . '_users';
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE id = %d", intval($user_id)));
        if ($user && password_verify($old_password, $user->password_hash)) {
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $wpdb->update(
                $table_users,
                array('password_hash' => $new_hash, 'temp_password' => $new_password),
                array('id' => intval($user_id))
            );
            return true;
        }
        return false;
    }

    public function logout() {
        unset($_SESSION['kvm_user_id']);
        unset($_SESSION['kvm_user_email']);
    }
}