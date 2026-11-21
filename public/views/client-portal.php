<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Client portal. Provided variables: $public, $slug, $user, $licenses,
 * $acts_map, $tab, $total_licenses, $total_slots, $active_activations.
 */

$msg_param = $slug . '_msg';
$err_param = $slug . '_err';
$tab_param = $slug . '_tab';
$pass_action  = $slug . '_change_pass_action';
$pass_nonce   = $slug . '_change_pass_nonce';

$notices = array();
if (isset($_GET[$msg_param])) {
    $msgs = array(
        'pass_changed'    => '✅ Security password updated successfully!',
        'domain_unlinked' => '✅ Domain unlinked successfully.',
        'logged_out'      => '✅ You have been signed out.',
    );
    $m = sanitize_key(wp_unslash($_GET[$msg_param]));
    if (isset($msgs[$m])) $notices[] = array('success', $msgs[$m]);
}
if (isset($_GET[$err_param])) {
    $errs = array(
        'pass_mismatch' => '❌ Passwords did not match or the current password was incorrect.',
        'not_permitted' => '❌ You are not allowed to manage that domain.',
    );
    $e = sanitize_key(wp_unslash($_GET[$err_param]));
    if (isset($errs[$e])) $notices[] = array('error', $errs[$e]);
}

$nav_items = array(
    'dashboard' => array('📊', 'Dashboard'),
    'licenses'  => array('🔑', 'License Portfolio'),
    'downloads' => array('📥', 'Plugin Downloads'),
    'profile'   => array('👤', 'Account Details'),
    'security'  => array('🛡️', 'Password & Security'),
);
$logout_url = wp_nonce_url($public->get_url(array('action' => 'logout')), $slug . '_logout');
?>
<div class="kvm-wrap kvm-portal">
    <div class="kvm-portal-layout">

        <aside class="kvm-card kvm-side">
            <div class="kvm-side-head">
                <div class="kvm-avatar"><?php echo esc_html(strtoupper(substr(trim($user->name), 0, 1))); ?></div>
                <strong class="kvm-side-name"><?php echo esc_html($user->name); ?></strong>
                <span class="kvm-side-mail"><?php echo esc_html($user->email); ?></span>
            </div>
            <nav class="kvm-side-nav">
                <?php foreach ($nav_items as $key => $item) : ?>
                    <a class="kvm-nav-item<?php echo $tab === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url($public->get_url(array('tab' => $key))); ?>">
                        <span aria-hidden="true"><?php echo $item[0]; ?></span><?php echo esc_html($item[1]); ?>
                    </a>
                <?php endforeach; ?>
                <a class="kvm-nav-item kvm-nav-item--danger" href="<?php echo esc_url($logout_url); ?>">
                    <span aria-hidden="true">🚪</span>Sign Out
                </a>
            </nav>
        </aside>

        <main class="kvm-card kvm-portal-main">
            <?php foreach ($notices as $notice) : ?>
                <div class="kvm-alert kvm-alert--<?php echo esc_attr($notice[0]); ?>"><?php echo esc_html($notice[1]); ?></div>
            <?php endforeach; ?>

            <?php if ($tab === 'dashboard') : ?>
                <h2 class="kvm-page-title">Account Overview</h2>
                <p class="kvm-page-sub">Manage your licenses, linked websites, downloads and account security.</p>

                <div class="kvm-stat-grid">
                    <div class="kvm-stat-tile">
                        <span class="kvm-stat-label">Licenses</span>
                        <h3 class="kvm-stat-value"><?php echo intval($total_licenses); ?></h3>
                    </div>
                    <div class="kvm-stat-tile">
                        <span class="kvm-stat-label">Connected Sites</span>
                        <h3 class="kvm-stat-value kvm-accent"><?php echo intval($active_activations); ?> / <?php echo intval($total_slots); ?></h3>
                    </div>
                    <div class="kvm-stat-tile">
                        <span class="kvm-stat-label">Account Status</span>
                        <h3 class="kvm-stat-value kvm-good">Active</h3>
                    </div>
                </div>

                <div class="kvm-panel kvm-panel--soft">
                    <strong>Quick actions</strong>
                    <p class="kvm-muted">View <a href="<?php echo esc_url($public->get_url(array('tab' => 'licenses'))); ?>">recent license keys</a>, update your <a href="<?php echo esc_url($public->get_url(array('tab' => 'security'))); ?>">password</a>, or grab the latest <a href="<?php echo esc_url($public->get_url(array('tab' => 'downloads'))); ?>">client software package</a>.</p>
                </div>

            <?php elseif ($tab === 'profile') : ?>
                <h2 class="kvm-page-title">Account Details</h2>
                <div class="kvm-panel kvm-panel--soft kvm-profile">
                    <div class="kvm-profile-row">
                        <span class="kvm-stat-label">Full Name</span>
                        <strong><?php echo esc_html($user->name); ?></strong>
                    </div>
                    <div class="kvm-profile-row">
                        <span class="kvm-stat-label">Email Address</span>
                        <strong><?php echo esc_html($user->email); ?></strong>
                    </div>
                </div>

            <?php elseif ($tab === 'security') : ?>
                <h2 class="kvm-page-title">Password &amp; Security</h2>
                <p class="kvm-page-sub">Choose a strong password with at least 8 characters.</p>
                <form method="post" class="kvm-form kvm-form--stack">
                    <?php wp_nonce_field($pass_action, $pass_nonce); ?>
                    <div class="kvm-field">
                        <label class="kvm-label">Current Password</label>
                        <input type="password" name="<?php echo esc_attr($slug . '_old_password'); ?>" class="kvm-input" required autocomplete="current-password">
                    </div>
                    <div class="kvm-field">
                        <label class="kvm-label">New Password</label>
                        <input type="password" name="<?php echo esc_attr($slug . '_new_password'); ?>" class="kvm-input" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="kvm-field">
                        <label class="kvm-label">Confirm New Password</label>
                        <input type="password" name="<?php echo esc_attr($slug . '_confirm_password'); ?>" class="kvm-input" minlength="8" required autocomplete="new-password">
                    </div>
                    <div><button type="submit" name="<?php echo esc_attr($slug . '_submit_change_pass'); ?>" class="kvm-btn kvm-btn--primary">Save Password Changes</button></div>
                </form>

            <?php elseif ($tab === 'downloads') : ?>
                <h2 class="kvm-page-title">Client Plugin Downloads</h2>
                <?php if (empty($licenses)) : ?>
                    <div class="kvm-empty">
                        <div class="kvm-empty-icon">📦</div>
                        <p>No licenses are attached to your account yet — downloads unlock with your first license key.</p>
                    </div>
                <?php else :
                    $first_key = '';
                    foreach ($licenses as $lic) {
                        if ($lic->status === 'active') { $first_key = $lic->license_key; break; }
                    }
                    if ($first_key === '') { $first_key = $licenses[0]->license_key; }
                    $namespace = defined('KVM_REST_NAMESPACE') ? KVM_REST_NAMESPACE : $slug . '/v1';
                    $dl_link = site_url('/wp-json/' . $namespace . '/download-update?key=' . urlencode($first_key));
                    ?>
                    <div class="kvm-panel kvm-panel--soft kvm-download-row">
                        <div>
                            <strong class="kvm-download-title"><?php echo esc_html(ucwords($slug)); ?> Optimization Engine</strong>
                            <span class="kvm-muted">Official client plugin package — latest licensed build.</span>
                        </div>
                        <a href="<?php echo esc_url($dl_link); ?>" class="kvm-btn kvm-btn--success">Download ZIP &darr;</a>
                    </div>
                <?php endif; ?>

            <?php else : /* licenses */ ?>
                <h2 class="kvm-page-title">License Keys &amp; Orders</h2>
                <?php if (empty($licenses)) : ?>
                    <div class="kvm-empty">
                        <div class="kvm-empty-icon">🔑</div>
                        <p>No active licenses are registered to your account.</p>
                    </div>
                <?php else : ?>
                    <?php foreach ($licenses as $lic) :
                        $acts_count  = isset($acts_map[intval($lic->id)]) ? $acts_map[intval($lic->id)] : 0;
                        $activations = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM " . KeyVault_Manager_DB::table('activations') . " WHERE license_id = %d ORDER BY id ASC",
                            intval($lic->id)
                        ));
                        $plan_tier   = isset($lic->plan_tier) ? sanitize_text_field($lic->plan_tier) : 'Starter';
                        $license_mode = isset($lic->license_mode) ? sanitize_text_field($lic->license_mode) : 'multi';
                        $is_expired  = (!empty($lic->expires_at) && strtotime($lic->expires_at) < strtotime(current_time('mysql')));
                        ?>
                        <div class="kvm-license-card">
                            <div class="kvm-license-head">
                                <div class="kvm-badge-row">
                                    <span class="kvm-badge kvm-badge--<?php echo esc_attr(strtolower($plan_tier)); ?>"><?php echo esc_html($plan_tier); ?> Plan</span>
                                    <span class="kvm-badge kvm-badge--plain"><?php echo esc_html(strtoupper($license_mode)); ?> MODE</span>
                                    <?php if (empty($lic->expires_at)) : ?>
                                        <span class="kvm-pill kvm-pill--lifetime">Lifetime</span>
                                    <?php elseif ($is_expired) : ?>
                                        <span class="kvm-pill kvm-pill--expired">Expired <?php echo esc_html(date('M d, Y', strtotime($lic->expires_at))); ?></span>
                                    <?php else : ?>
                                        <span class="kvm-pill kvm-pill--ok">Expires <?php echo esc_html(date('M d, Y', strtotime($lic->expires_at))); ?></span>
                                    <?php endif; ?>
                                    <?php if ($lic->status !== 'active') : ?>
                                        <span class="kvm-pill kvm-pill--expired"><?php echo esc_html(strtoupper($lic->status)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="kvm-quota">Quota used: <strong><?php echo intval($acts_count); ?> / <?php echo intval($lic->max_activations); ?></strong></div>
                            </div>

                            <div class="kvm-keyrow">
                                <code class="kvm-key"><?php echo esc_html($lic->license_key); ?></code>
                                <button type="button" class="kvm-btn kvm-btn--small kvm-copy-btn" data-kvm-copy="<?php echo esc_attr($lic->license_key); ?>">Copy Key</button>
                            </div>

                            <div class="kvm-domains">
                                <strong class="kvm-domains-title">Connected Websites</strong>
                                <?php if (empty($activations)) : ?>
                                    <em class="kvm-muted">No websites linked yet. Activate inside your WordPress plugin.</em>
                                <?php else : ?>
                                    <?php foreach ($activations as $a) :
                                        $unlink_url = wp_nonce_url($public->get_url(array('action' => 'unlink_user', 'act_id' => $a->id)), $slug . '_unlink_user_' . $a->id);
                                        ?>
                                        <span class="kvm-chip">
                                            <span class="kvm-chip-domain"><?php echo esc_html($a->domain); ?></span>
                                            <a href="<?php echo esc_url($unlink_url); ?>" class="kvm-chip-unlink" onclick="return confirm('Unlink this domain?');">&times; Unlink</a>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </main>

    </div>
</div>
