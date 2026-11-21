<?php
if (!defined('ABSPATH')) { exit; }

$public = KeyVault_Manager_Public::get_instance();
$slug = KVM_SLUG;
$msg_param = $slug . '_msg';
$err_param = $slug . '_err';
$pass_action = $slug . '_change_pass_action';
$pass_nonce = $slug . '_change_pass_nonce';
$old_pass_name = $slug . '_old_password';
$new_pass_name = $slug . '_new_password';
$confirm_pass_name = $slug . '_confirm_password';
$submit_pass_name = $slug . '_submit_change_pass';
?>
<div class="kvm-wrap" style="max-width:1240px; margin:40px auto; background:#f8fafc; border-radius:24px; padding:30px; border:1px solid #e2e8f0;">
    <div style="display:grid; grid-template-columns: 260px 1fr; gap:30px;">
        
        <div class="kvm-card" style="padding:25px; height:fit-content;">
            <div style="text-align:center; border-bottom:1px solid #f1f5f9; padding-bottom:20px; margin-bottom:20px;">
                <div style="width:60px; height:60px; background:#eff6ff; color:#2563eb; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:800; margin:0 auto 12px auto;">
                    <?php echo strtoupper(substr($user->name, 0, 1)); ?>
                </div>
                <strong style="color:#0f172a; font-size:16px; display:block;"><?php echo esc_html($user->name); ?></strong>
                <span style="color:#64748b; font-size:13px;"><?php echo esc_html($user->email); ?></span>
            </div>

            <nav style="display:flex; flex-direction:column; gap:6px;">
                <a href="<?php echo esc_url($public->get_url(array('tab' => 'dashboard'))); ?>" style="padding:12px 16px; border-radius:10px; font-weight:700; font-size:14px; text-decoration:none; display:flex; align-items:center; gap:10px; <?php echo $tab === 'dashboard' ? 'background:#2563eb; color:#ffffff;' : 'color:#475569; background:transparent;'; ?>">📊 Dashboard</a>
                <a href="<?php echo esc_url($public->get_url(array('tab' => 'licenses'))); ?>" style="padding:12px 16px; border-radius:10px; font-weight:700; font-size:14px; text-decoration:none; display:flex; align-items:center; gap:10px; <?php echo $tab === 'licenses' ? 'background:#2563eb; color:#ffffff;' : 'color:#475569; background:transparent;'; ?>">🔑 License Portfolio</a>
                <a href="<?php echo esc_url($public->get_url(array('tab' => 'downloads'))); ?>" style="padding:12px 16px; border-radius:10px; font-weight:700; font-size:14px; text-decoration:none; display:flex; align-items:center; gap:10px; <?php echo $tab === 'downloads' ? 'background:#2563eb; color:#ffffff;' : 'color:#475569; background:transparent;'; ?>">📥 Plugin Downloads</a>
                <a href="<?php echo esc_url($public->get_url(array('tab' => 'profile'))); ?>" style="padding:12px 16px; border-radius:10px; font-weight:700; font-size:14px; text-decoration:none; display:flex; align-items:center; gap:10px; <?php echo $tab === 'profile' ? 'background:#2563eb; color:#ffffff;' : 'color:#475569; background:transparent;'; ?>">👤 Account Details</a>
                <a href="<?php echo esc_url($public->get_url(array('tab' => 'security'))); ?>" style="padding:12px 16px; border-radius:10px; font-weight:700; font-size:14px; text-decoration:none; display:flex; align-items:center; gap:10px; <?php echo $tab === 'security' ? 'background:#2563eb; color:#ffffff;' : 'color:#475569; background:transparent;'; ?>">🛡️ Security Password</a>
                <a href="<?php echo esc_url($public->get_url(array('action' => 'logout'))); ?>" style="padding:12px 16px; border-radius:10px; font-weight:700; font-size:14px; text-decoration:none; display:flex; align-items:center; gap:10px; color:#ef4444; margin-top:10px;">🚪 Sign Out</a>
            </nav>
        </div>

        <div class="kvm-card" style="padding:35px;">
            <?php if (isset($_GET[$msg_param]) && $_GET[$msg_param] === 'pass_changed') : ?>
                <div style="background:#dcfce7; border:1px solid #86efac; color:#166534; padding:15px; border-radius:12px; margin-bottom:25px; font-weight:700;">✅ Security password updated successfully!</div>
            <?php endif; ?>

            <?php if (isset($_GET[$err_param]) && $_GET[$err_param] === 'pass_mismatch') : ?>
                <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:15px; border-radius:12px; margin-bottom:25px; font-weight:700;">❌ Passwords did not match or current password was incorrect.</div>
            <?php endif; ?>

            <?php if ($tab === 'dashboard') : ?>
                <h2 style="margin:0 0 8px 0; font-size:24px; font-weight:800; color:#0f172a;">Account Overview</h2>
                <p style="color:#64748b; font-size:15px; margin-bottom:30px;">From your account dashboard you can view your <a href="<?php echo esc_url($public->get_url(array('tab' => 'licenses'))); ?>" style="color:#2563eb; font-weight:700;">recent license keys</a>, manage <a href="<?php echo esc_url($public->get_url(array('tab' => 'security'))); ?>" style="color:#2563eb; font-weight:700;">password security</a>, and download <a href="<?php echo esc_url($public->get_url(array('tab' => 'downloads'))); ?>" style="color:#2563eb; font-weight:700;">client software packages</a>.</p>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px; margin-bottom:35px;">
                    <div style="background:#f8fafc; border:1.5px solid #e2e8f0; padding:22px; border-radius:14px;">
                        <span style="font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase;">Licenses</span>
                        <h3 style="font-size:30px; font-weight:900; color:#0f172a; margin:8px 0 0 0;"><?php echo $total_licenses; ?></h3>
                    </div>
                    <div style="background:#f8fafc; border:1.5px solid #e2e8f0; padding:22px; border-radius:14px;">
                        <span style="font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase;">Connected Sites</span>
                        <h3 style="font-size:30px; font-weight:900; color:#2563eb; margin:8px 0 0 0;"><?php echo $active_activations; ?> / <?php echo $total_slots; ?></h3>
                    </div>
                    <div style="background:#f8fafc; border:1.5px solid #e2e8f0; padding:22px; border-radius:14px;">
                        <span style="font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase;">Account Status</span>
                        <h3 style="font-size:22px; font-weight:800; color:#10b981; margin:8px 0 0 0;">Active Account</h3>
                    </div>
                </div>

            <?php elseif ($tab === 'profile') : ?>
                <h2 style="margin:0 0 8px 0; font-size:24px; font-weight:800; color:#0f172a;">Account Details</h2>
                <div style="background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:16px; padding:25px; max-width:540px;">
                    <div style="margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:15px;">
                        <span style="font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase; display:block; margin-bottom:4px;">Full Name</span>
                        <strong style="font-size:18px; color:#0f172a;"><?php echo esc_html($user->name); ?></strong>
                    </div>
                    <div style="margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:15px;">
                        <span style="font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase; display:block; margin-bottom:4px;">Email Address</span>
                        <strong style="font-size:18px; color:#0f172a;"><?php echo esc_html($user->email); ?></strong>
                    </div>
                </div>

            <?php elseif ($tab === 'security') : ?>
                <h2 style="margin:0 0 8px 0; font-size:24px; font-weight:800; color:#0f172a;">Password & Security</h2>
                <form method="post" style="max-width:500px;">
                    <?php wp_nonce_field($pass_action, $pass_nonce); ?>
                    <div style="margin-bottom:20px;">
                        <label style="font-weight:700; display:block; margin-bottom:8px; color:#334155; font-size:14px;">Current Password</label>
                        <input type="password" name="<?php echo esc_attr($old_pass_name); ?>" class="kvm-input" required>
                    </div>
                    <div style="margin-bottom:20px;">
                        <label style="font-weight:700; display:block; margin-bottom:8px; color:#334155; font-size:14px;">New Password</label>
                        <input type="password" name="<?php echo esc_attr($new_pass_name); ?>" class="kvm-input" required>
                    </div>
                    <div style="margin-bottom:25px;">
                        <label style="font-weight:700; display:block; margin-bottom:8px; color:#334155; font-size:14px;">Confirm New Password</label>
                        <input type="password" name="<?php echo esc_attr($confirm_pass_name); ?>" class="kvm-input" required>
                    </div>
                    <button type="submit" name="<?php echo esc_attr($submit_pass_name); ?>" class="kvm-btn" style="width:auto; padding:14px 28px;">Save Password Changes</button>
                </form>

            <?php elseif ($tab === 'downloads') : ?>
                <h2 style="margin:0 0 8px 0; font-size:24px; font-weight:800; color:#0f172a;">Client Plugin Downloads</h2>
                <?php if (!empty($licenses)) : 
                    $first_key = $licenses[0]->license_key;
                    $namespace = defined('KVM_REST_NAMESPACE') ? KVM_REST_NAMESPACE : get_option($slug . '_rest_namespace', $slug . '/v1');
                    $dl_link = site_url('/wp-json/' . $namespace . '/download-update?key=' . urlencode($first_key));
                    ?>
                    <div style="background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:16px; padding:30px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:20px;">
                        <div>
                            <strong style="font-size:18px; color:#0f172a; display:block;"><?php echo ucwords($slug); ?> Optimization Engine v1.0.2</strong>
                            <span style="color:#64748b; font-size:14px;">Official client plugin package</span>
                        </div>
                        <a href="<?php echo esc_url($dl_link); ?>" style="background:#10b981; color:#ffffff; padding:14px 26px; border-radius:12px; font-weight:800; font-size:15px; text-decoration:none;">Download ZIP &darr;</a>
                    </div>
                <?php endif; ?>

            <?php else : ?>
                <h2 style="margin:0 0 8px 0; font-size:24px; font-weight:800; color:#0f172a;">License Keys & Orders</h2>
                <?php if (empty($licenses)) : ?>
                    <p style="color:#64748b;">No active licenses registered to your account.</p>
                <?php else : ?>
                    <?php foreach ($licenses as $lic) : 
                        $acts = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$slug}_activations WHERE license_id = %d", $lic->id));
                        $plan_tier = isset($lic->plan_tier) ? $lic->plan_tier : 'Starter';
                        $license_mode = isset($lic->license_mode) ? $lic->license_mode : 'multi';
                        $tier_color = ($plan_tier === 'Enterprise') ? '#d97706' : (($plan_tier === 'Professional') ? '#9333ea' : '#2563eb');
                        $exp_str = empty($lic->expires_at) ? 'Lifetime' : 'Expires: ' . esc_html($lic->expires_at);
                        ?>
                        <div style="background:#f8fafc; border:1.5px solid #e2e8f0; padding:25px; border-radius:16px; margin-bottom:22px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                                <div>
                                    <span style="background:<?php echo $tier_color; ?>; color:#ffffff; padding:4px 12px; border-radius:6px; font-size:11px; font-weight:800; text-transform:uppercase;"><?php echo esc_html($plan_tier); ?> Plan</span>
                                    <span style="background:#e2e8f0; color:#334155; padding:4px 12px; border-radius:6px; font-size:11px; font-weight:800; margin-left:8px; text-transform:uppercase;"><?php echo strtoupper($license_mode); ?> MODE</span>
                                    <span style="background:#dcfce7; color:#15803d; padding:4px 12px; border-radius:6px; font-size:11px; font-weight:800; margin-left:8px; text-transform:uppercase;"><?php echo $exp_str; ?></span>
                                </div>
                                <div style="font-size:14px; color:#475569; font-weight:600;">
                                    Quota Used: <strong style="color:#0f172a;"><?php echo count($acts); ?> / <?php echo esc_html($lic->max_activations); ?></strong>
                                </div>
                            </div>

                            <div style="margin-top:18px; display:flex; align-items:center; gap:12px; background:#ffffff; border:1.5px solid #cbd5e1; padding:14px 18px; border-radius:12px;">
                                <code style="font-size:16px; font-weight:800; color:#0f172a; flex-grow:1; letter-spacing:0.5px;"><?php echo esc_html($lic->license_key); ?></code>
                                <button type="button" class="kvm-copy-btn" data-clipboard="<?php echo esc_attr($lic->license_key); ?>" style="background:#2563eb; color:#ffffff; border:none; padding:8px 16px; border-radius:8px; font-weight:800; cursor:pointer; font-size:13px;">Copy Key</button>
                            </div>

                            <div style="margin-top:18px;">
                                <strong style="font-size:13px; color:#334155; display:block; margin-bottom:8px;">Connected Websites:</strong>
                                <div>
                                    <?php if (empty($acts)) : ?>
                                        <em style="color:#94a3b8; font-size:13px;">No websites linked yet. Activate inside your WordPress plugin.</em>
                                    <?php else : ?>
                                        <?php foreach ($acts as $a) : 
                                            $unlink_url = wp_nonce_url($public->get_url(array('action' => 'unlink_user', 'act_id' => $a->id)), $slug . '_unlink_user_' . $a->id);
                                            ?>
                                            <div style="display:inline-flex; align-items:center; background:#ffffff; border:1.5px solid #cbd5e1; padding:6px 14px; border-radius:8px; margin-right:10px; margin-bottom:8px; font-size:13px;">
                                                <span style="font-weight:700; color:#0f172a; margin-right:8px;"><?php echo esc_html($a->domain); ?></span>
                                                <a href="<?php echo esc_url($unlink_url); ?>" style="color:#ef4444; font-weight:800; text-decoration:none;" onclick="return confirm('Unlink this domain?');">&times; Unlink</a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</div>