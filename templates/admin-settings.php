<?php
/**
 * Template: Admin Settings Page
 * Security: API calls proxied through WordPress AJAX - api_secret never exposed to browser
 * Version: 2.0.0 - Added Auto-Signing with Crossmark
 */
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

// Handle return from yesallofus.com dashboard (secure claim token flow)
if (isset($_GET['claim_token'])) {
    $claim_token = sanitize_text_field($_GET['claim_token']);
    
    // Validate format (32 hex chars)
    if (preg_match('/^[a-f0-9]{32}$/', $claim_token)) {
        
        // Call API to claim the secret (one-time use)
        $response = wp_remote_post(DLTPAYS_API_URL . '/store/claim-secret', [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['claim_token' => $claim_token])
        ]);
        
        if (is_wp_error($response)) {
            error_log('DLTPays: Claim secret failed - ' . $response->get_error_message());
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            error_log('DLTPays: Claim response code: ' . $code);
            error_log('DLTPays: Claim response: ' . print_r($body, true));
            
            if ($code === 200 && !empty($body['success']) && !empty($body['api_secret'])) {
                update_option('dltpays_store_id', $body['store_id']);
                update_option('dltpays_api_secret', $body['api_secret']);
                update_option('dltpays_wallet_address', $body['wallet_address']);
                update_option('dltpays_wallet_type', $body['wallet_type'] ?? 'web3auth');
                
                error_log('DLTPays: Store claimed successfully - ' . $body['store_id']);
                
                // Redirect to remove claim_token from URL
                wp_redirect(admin_url('admin.php?page=dltpays-settings&connected=1'));
                exit;
            } else {
                error_log('DLTPays: Claim failed - ' . ($body['error'] ?? 'Unknown error'));
            }
        }
    } else {
        error_log('DLTPays: Invalid claim token format');
    }
}

// Show success message if just connected
$just_connected = isset($_GET['connected']);

// Check wallet status if connected
$wallet_status = null;
$wallet_address = get_option('dltpays_wallet_address');
if ($wallet_address) {
    $status_response = wp_remote_get(DLTPAYS_API_URL . '/wallet/status/' . $wallet_address, array(
        'timeout' => 10
    ));
    if (!is_wp_error($status_response)) {
        $status_body = json_decode(wp_remote_retrieve_body($status_response), true);
        if (!empty($status_body['success'])) {
            $wallet_status = $status_body;
        }
    }
}

$wallet_type = get_option('dltpays_wallet_type', '');

$store_id = get_option('dltpays_store_id');
$has_credentials = !empty($store_id) && !empty(get_option('dltpays_api_secret'));
$rates = json_decode(get_option('dltpays_commission_rates', '[25,5,3,2,1]'), true) ?: [25, 5, 3, 2, 1];
$has_referral = !empty(get_option('dltpays_referral_code'));
$payout_mode = get_option('dltpays_payout_mode', 'manual');
?>

<div class="wrap dltpays-admin">

<?php if ($wallet_type === 'xaman'): ?>
<style>
    #onboard-step-3, #step-ind-3 { display: none !important; }
</style>
<?php endif; ?>

    <h1><?php _e('YesAllofUs Settings', 'yesallofus'); ?></h1>
    
    <?php if (!$has_credentials): ?>

        <?php if (false): ?>
<!-- Web3Auth Social Login Section -->
<div style="background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0; opacity: 0.5; pointer-events: none;">
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
        <div style="display: flex; -space-x: -8px;">
            <!-- Google -->
            <div style="width: 32px; height: 32px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #f0f0f0;">
                <svg width="16" height="16" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            </div>
            <!-- Apple -->
            <div style="width: 32px; height: 32px; background: #000; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-left: -8px; border: 2px solid #f0f0f0;">
                <svg width="14" height="14" fill="#fff" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
            </div>
            <!-- Facebook -->
            <div style="width: 32px; height: 32px; background: #1877F2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-left: -8px; border: 2px solid #f0f0f0;">
                <svg width="14" height="14" fill="#fff" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </div>
            <!-- X/Twitter -->
            <div style="width: 32px; height: 32px; background: #000; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-left: -8px; border: 2px solid #f0f0f0;">
                <svg width="12" height="12" fill="#fff" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            </div>
            <!-- Discord -->
            <div style="width: 32px; height: 32px; background: #5865F2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-left: -8px; border: 2px solid #f0f0f0;">
                <svg width="14" height="14" fill="#fff" viewBox="0 0 24 24"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
            </div>
            <!-- More -->
            <div style="width: 32px; height: 32px; background: #6b7280; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-left: -8px; border: 2px solid #f0f0f0;">
                <span style="color: #fff; font-size: 10px; font-weight: bold;">+7</span>
            </div>
        </div>
        <div>
            <span style="font-weight: 600; font-size: 16px;"><?php _e('Sign up with Social', 'yesallofus'); ?></span>
<span style="background: #d1fae5; color: #065f46; font-size: 11px; padding: 2px 8px; border-radius: 4px; margin-left: 8px;"><?php _e('Coming Soon', 'yesallofus'); ?></span>
        </div>
    </div>
    
    <p style="color: #555; margin-bottom: 16px; font-size: 14px;">
        <?php _e('Get an XRPL wallet instantly using Google, Apple, Facebook, X, Discord, and more. After a one-time setup, payouts process automatically.', 'yesallofus'); ?>
    </p>
    
    <!-- How it works -->
    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
        <p style="color: #1e40af; font-weight: 600; margin: 0 0 8px 0; font-size: 13px;">ℹ️ <?php _e('How It Works', 'yesallofus'); ?></p>
        <ul style="color: #1e40af; font-size: 13px; margin: 0; padding-left: 20px; line-height: 1.6;">
            <li><?php _e('Your wallet is created via Web3Auth, linked to your social account', 'yesallofus'); ?></li>
            <li><?php _e('After a one-time setup, payouts are processed automatically by our platform', 'yesallofus'); ?></li>
            <li><?php _e('No browser session required — payouts happen 24/7 without you being online', 'yesallofus'); ?></li>
            <li><?php _e('Withdraw your funds anytime from the dashboard', 'yesallofus'); ?></li>
        </ul>
    </div>
    
    <!-- Terms checkbox -->
    <label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px; background: #f9fafb; border-radius: 8px; cursor: pointer; margin-bottom: 16px;">
        <input type="checkbox" id="web3auth-terms-checkbox" style="margin-top: 3px; width: 18px; height: 18px;">
        <span style="color: #374151; font-size: 13px;">
            <?php _e('I have read and understand the above, and agree to the', 'yesallofus'); ?>
            <a href="https://yesallofus.com/terms" target="_blank" style="color: #2563eb;"><?php _e('Terms of Service', 'yesallofus'); ?></a>
        </span>
    </label>
    
    <a href="https://yesallofus.com/dashboard?wordpress_return=<?php echo urlencode(admin_url('admin.php?page=dltpays-settings')); ?>" id="web3auth-connect-btn" style="
    display: block;
    width: 100%;
    box-sizing: border-box;
    background: #e5e7eb;
    color: #9ca3af;
    border: none;
    padding: 14px 24px;
    font-size: 15px;
    font-weight: 600;
    cursor: not-allowed;
    border-radius: 8px;
    transition: all 0.2s;
    text-align: center;
    text-decoration: none;
    pointer-events: none;
">
    <?php _e('Sign in with Social Account', 'yesallofus'); ?>
</a>
    
    <p style="text-align: center; margin-top: 12px; font-size: 12px; color: #9ca3af;">
        <a href="https://yesallofus.com/terms" target="_blank" style="color: #6b7280;"><?php _e('Terms of Service', 'yesallofus'); ?></a>
        · 
        <a href="https://yesallofus.com/privacy" target="_blank" style="color: #6b7280;"><?php _e('Privacy Policy', 'yesallofus'); ?></a>
    </p>
</div>
<?php endif; ?>

<!-- Divider -->
<div style="display: flex; align-items: center; gap: 16px; margin: 24px 0;">
    <div style="flex: 1; height: 1px; background: #e5e7eb;"></div>
    <span style="color: #9ca3af; font-size: 13px;"><?php _e('or use a crypto wallet', 'yesallofus'); ?></span>
    <div style="flex: 1; height: 1px; background: #e5e7eb;"></div>
</div>

<!-- Xaman Option -->
<div id="xaman-login-option" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 12px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#2563eb'" onmouseout="this.style.borderColor='#e5e7eb'">
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
        <img src="<?php echo DLTPAYS_PLUGIN_URL; ?>assets/XamanWalletlogo.jpeg" alt="Xaman" style="width: 32px; height: 32px; border-radius: 6px;">
        <span style="font-weight: 600;"><?php _e('Xaman Mobile App', 'yesallofus'); ?></span>
        <span style="background: #d1fae5; color: #065f46; font-size: 11px; padding: 2px 8px; border-radius: 4px;"><?php _e('✓ Live', 'yesallofus'); ?></span>
    </div>
    <p style="color: #6b7280; font-size: 13px; margin: 0;"><?php _e('Approve each payout via push notification on your phone. Best for security.', 'yesallofus'); ?></p>
</div>

<?php if (false): ?>
<!-- Crossmark Option -->
<div id="crossmark-login-option" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 12px; opacity: 0.5; pointer-events: none;">
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
        <img src="<?php echo DLTPAYS_PLUGIN_URL; ?>assets/CrossmarkWalletlogo.jpeg" alt="Crossmark" style="width: 32px; height: 32px; border-radius: 6px;">
        <span style="font-weight: 600;"><?php _e('Crossmark Browser Extension', 'yesallofus'); ?></span>
        <span style="background: #d1fae5; color: #065f46; font-size: 11px; padding: 2px 8px; border-radius: 4px;"><?php _e('Coming Soon', 'yesallofus'); ?></span>
    </div>
    <p style="color: #6b7280; font-size: 13px; margin: 0;"><?php _e('Desktop browser wallet. Enable auto-sign for automatic payouts.', 'yesallofus'); ?></p>
</div>
<?php endif; ?>

<!-- Xaman QR Modal -->
<div id="xaman-qr-modal" style="display: none; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-bottom: 12px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="margin: 0; font-size: 16px;"><?php _e('Scan with Xaman', 'yesallofus'); ?></h3>
        <button type="button" id="close-xaman-modal" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #9ca3af;">&times;</button>
    </div>
    <div id="xaman-qr-content" style="text-align: center;">
        <p style="color: #6b7280; font-size: 14px;"><?php _e('Loading...', 'yesallofus'); ?></p>
    </div>
</div>

<!-- Promotional Code Section -->
<div class="dltpays-promo-card" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 24px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); margin: 20px 0; color: #fff;">
    <div style="display: flex; align-items: center; margin-bottom: 16px;">
        <span style="font-size: 28px; margin-right: 12px;">🎁</span>
        <h2 style="margin: 0; color: #fff; font-size: 22px;"><?php _e('Have a Promotional Code?', 'yesallofus'); ?></h2>
    </div>
    
    <p style="font-size: 16px; line-height: 1.6; margin-bottom: 20px; color: #e0e7ef;">
        <?php _e('Share your referral code with other store owners. Earn 25% of their platform fees every time they pay affiliates — plus 5 levels deep on stores they refer.', 'yesallofus'); ?>
    </p>
    
    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <input type="text" 
               id="promo-code-input" 
               placeholder="<?php _e('Enter code (e.g. A1B2C3D4)', 'yesallofus'); ?>"
               value="<?php echo esc_attr(get_option('dltpays_referral_code', '')); ?>"
               style="padding: 12px 16px; font-size: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 6px; background: rgba(255,255,255,0.1); color: #fff; width: 200px; text-transform: uppercase; letter-spacing: 2px;"
               maxlength="8">
        <button type="button" 
                id="apply-promo-code" 
                style="background: #fff; color: #059669; border: none; padding: 12px 24px; font-size: 14px; font-weight: bold; cursor: pointer; border-radius: 6px; transition: all 0.2s;">
            <?php _e('Apply Code', 'yesallofus'); ?>
        </button>
    </div>
    
    <div id="promo-code-message" style="margin-top: 12px; display: none;"></div>
    
    <?php if ($has_referral): ?>
    <div style="margin-top: 16px; padding: 12px; background: rgba(255,255,255,0.15); border-radius: 6px;">
        <span style="color: #d1fae5;">✓ <?php _e('Promotional code applied:', 'yesallofus'); ?></span>
        <strong style="color: #fff; letter-spacing: 2px;"><?php echo esc_html(get_option('dltpays_referral_code')); ?></strong>
        <span style="color: #d1fae5;"> — <?php _e('50% off first month fees!', 'yesallofus'); ?></span>
    </div>
    <?php endif; ?>
</div>
<!-- Web3Auth Onboarding Flow (shows after store creation, needs setup) -->
<div id="web3auth-onboarding" style="display: none; margin: 20px 0;">
    <div style="background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h2 style="margin: 0 0 16px 0;">🚀 <?php _e('Complete Your Setup', 'yesallofus'); ?></h2>
        
        <!-- Progress indicators -->
<div style="display: flex; gap: 8px; margin-bottom: 20px;">
    <div id="step-ind-1" style="flex: 1; height: 4px; background: #e5e7eb; border-radius: 2px;"></div>
    <div id="step-ind-2" style="flex: 1; height: 4px; background: #e5e7eb; border-radius: 2px;"></div>
    <div id="step-ind-3" class="web3auth-only" style="flex: 1; height: 4px; background: #e5e7eb; border-radius: 2px; display: none;"></div>
</div>
        
        <!-- Step 1: Fund Wallet -->
        <div id="onboard-step-1" style="display: none; padding: 20px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; margin-bottom: 16px;">
            <h3 style="margin: 0 0 8px 0; color: #92400e;">💰 <?php _e('Step 1: Fund Your Wallet', 'yesallofus'); ?></h3>
            <p style="color: #78350f; font-size: 14px; margin-bottom: 12px;">
                <?php _e('Your wallet needs ~$3-5 worth of XRP to activate and pay transaction fees.', 'yesallofus'); ?>
            </p>
            <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-bottom: 12px;">
                <label style="font-size: 12px; color: #6b7280;"><?php _e('Your Wallet Address', 'yesallofus'); ?></label>
                <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
                    <code id="onboard-wallet-address" style="flex: 1; font-size: 11px; word-break: break-all;">Loading...</code>
                    <button type="button" id="copy-onboard-wallet" class="button button-small">📋</button>
                </div>
            </div>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <a href="https://yesallofus.com/dashboard?tab=topup" target="_blank" class="button button-primary">💳 <?php _e('Buy XRP with Card', 'yesallofus'); ?></a>
                <button type="button" id="check-funding-btn" class="button">🔄 <?php _e('Check Balance', 'yesallofus'); ?></button>
            </div>
            <p style="margin: 12px 0 0 0; font-size: 12px; color: #92400e;">
                <?php _e('Already have XRP? Send at least 12 XRP to the address above.', 'yesallofus'); ?>
            </p>
        </div>
        
        <!-- Step 2: RLUSD Trustline -->
        <div id="onboard-step-2" style="display: none; padding: 20px; background: #eff6ff; border: 1px solid #3b82f6; border-radius: 8px; margin-bottom: 16px;">
            <h3 style="margin: 0 0 8px 0; color: #1e40af;">🔗 <?php _e('Step 2: Enable RLUSD', 'yesallofus'); ?></h3>
            <p style="color: #1e3a8a; font-size: 14px; margin-bottom: 12px;">
                <?php _e('Add the RLUSD trustline so your wallet can receive stablecoin payments.', 'yesallofus'); ?>
            </p>
            <button type="button" id="setup-trustline-btn" class="button button-primary">🔗 <?php _e('Add RLUSD Trustline', 'yesallofus'); ?></button>
            <p style="margin: 12px 0 0 0; font-size: 12px; color: #1e40af;">
                <?php _e('This will sign a transaction with your social login.', 'yesallofus'); ?>
            </p>
        </div>
        
        <!-- Step 3: Auto-Sign (Web3Auth/Crossmark only - not for Xaman) -->
<div id="onboard-step-3" class="web3auth-only" style="display: none; padding: 20px; background: #f0fdf4; border: 1px solid #22c55e; border-radius: 8px; margin-bottom: 16px;">
            <h3 style="margin: 0 0 8px 0; color: #166534;">⚡ <?php _e('Step 3: Enable Auto-Payouts', 'yesallofus'); ?></h3>
            <p style="color: #15803d; font-size: 14px; margin-bottom: 12px;">
                <?php _e('Allow YesAllofUs to automatically process affiliate payouts 24/7.', 'yesallofus'); ?>
            </p>
            <button type="button" id="setup-autosign-btn" class="button button-primary">⚡ <?php _e('Enable Auto-Payouts', 'yesallofus'); ?></button>
            <p style="margin: 12px 0 0 0; font-size: 12px; color: #166534;">
                <?php _e('You can revoke this anytime from the dashboard.', 'yesallofus'); ?>
            </p>
        </div>
        
        <!-- Success -->
        <div id="onboard-complete" style="display: none; text-align: center; padding: 40px 20px; background: #f0fdf4; border: 1px solid #22c55e; border-radius: 8px;">
            <div style="font-size: 48px; margin-bottom: 16px;">🎉</div>
            <h3 style="margin: 0 0 8px 0; color: #166534;"><?php _e('Setup Complete!', 'yesallofus'); ?></h3>
            <p style="color: #15803d; margin-bottom: 20px;"><?php _e('Your store is ready to process affiliate commissions.', 'yesallofus'); ?></p>
            <button type="button" onclick="location.reload()" class="button button-primary"><?php _e('Go to Dashboard', 'yesallofus'); ?></button>
        </div>
    </div>
</div>

<?php else: ?>

<?php if ($just_connected): ?>
<div style="background: #d1fae5; border: 1px solid #10b981; border-radius: 8px; padding: 16px; margin: 20px 0;">
    <span style="color: #065f46; font-weight: 600;">✓ Wallet connected successfully!</span>
</div>
<?php endif; ?>

<?php if ($wallet_address): ?>
<div style="background: #1e293b; border-radius: 12px; padding: 20px; margin: 20px 0;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
        <span style="color: #22c55e; font-size: 20px;">✓</span>
        <span style="color: #fff; font-weight: 600;">Wallet Connected</span>
        <span style="color: #94a3b8; font-size: 13px;"><?php echo esc_html(substr($wallet_address, 0, 8) . '...' . substr($wallet_address, -6)); ?></span>
    </div>
    
    <?php if ($wallet_status): ?>
    <?php 
// Only funding + trustline needed for all wallet types (auto-sign coming soon)
$needs_setup = !$wallet_status['funded'] || !$wallet_status['rlusd_trustline'];
?>
    
    <?php if ($needs_setup): ?>
    <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
        <div style="display: flex; align-items: flex-start; gap: 10px;">
            <span style="font-size: 18px;">⚠️</span>
            <div>
                <div style="color: #92400e; font-weight: 600; margin-bottom: 5px;">
    <?php if (!$wallet_status['funded']): ?>
    Step 1: Fund Your Wallet
<?php elseif (!$wallet_status['rlusd_trustline']): ?>
    Step 2: Enable RLUSD Trustline
<?php else: ?>
    Wallet Setup Incomplete
<?php endif; ?>
</div>
                <div style="color: #a16207; font-size: 13px;">
    <?php if (!$wallet_status['funded']): ?>
        Send at least 1.5 XRP to activate your wallet.
    <?php elseif (!$wallet_status['rlusd_trustline']): ?>
        Your wallet is funded! Now add the RLUSD trustline to receive payments.
    <?php else: ?>
        Your wallet needs additional setup.
    <?php endif; ?>
</div>
            </div>
        </div>
    </div>
            
            <div style="background: #0f172a; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                <div style="color: #94a3b8; font-size: 12px; text-transform: uppercase; margin-bottom: 10px;">Setup Status</div>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <?php if ($wallet_status['funded']): ?>
                            <span style="color: #22c55e;">✓</span>
                            <span style="color: #d1d5db;">Wallet funded (<?php echo number_format($wallet_status['xrp_balance'], 2); ?> XRP)</span>
                        <?php else: ?>
                            <span style="color: #ef4444;">✗</span>
                            <span style="color: #d1d5db;">Wallet not funded (needs ~1.5 XRP)</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <?php if ($wallet_status['rlusd_trustline']): ?>
                            <span style="color: #22c55e;">✓</span>
                            <span style="color: #d1d5db;">RLUSD trustline set</span>
                        <?php else: ?>
                            <span style="color: #ef4444;">✗</span>
                            <span style="color: #d1d5db;">RLUSD trustline needed</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <a href="https://yesallofus.com/dashboard" target="_blank" style="display: inline-block; background: #f59e0b; color: #000; font-weight: 600; padding: 12px 24px; border-radius: 8px; text-decoration: none;">Complete Wallet Setup →</a>
        <?php else: ?>
    <div style="background: #064e3b; border: 1px solid #10b981; border-radius: 8px; padding: 15px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 18px;">✅</span>
            <div>
                <div style="color: #d1fae5; font-weight: 600;">Ready to receive commissions</div>
                <div style="color: #a7f3d0; font-size: 13px;">Balance: <?php echo number_format($wallet_status['rlusd_balance'], 2); ?> RLUSD</div>
                <?php if ($wallet_type === 'xaman'): ?>
                <div style="color: #a7f3d0; font-size: 13px; margin-top: 4px;">📱 Manual mode — approve payouts via push notification</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
            <div style="margin-top: 15px;">
                <a href="https://yesallofus.com/dashboard" target="_blank" style="color: #60a5fa; text-decoration: none; font-size: 14px;">Manage Wallet Settings →</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <a href="https://yesallofus.com/dashboard" target="_blank" style="color: #60a5fa; text-decoration: none;">Manage wallet on YesAllofUs Dashboard →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="dltpays-connection-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2 style="margin-top: 0;"><?php _e('Connection Status', 'yesallofus'); ?></h2>
        
        <table class="form-table" style="margin: 0;">
            <tr>
                <th scope="row"><?php _e('Store ID', 'yesallofus'); ?></th>
                <td><code style="font-size: 14px;"><?php echo esc_html($store_id); ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('API Status', 'yesallofus'); ?></th>
                <td><div id="connection-status"><span style="color: #666;">Checking...</span></div></td>
            </tr>
            <tr>
    <th scope="row"><?php _e('Wallet', 'yesallofus'); ?></th>
    <td>
        <?php if ($wallet_address && $wallet_type === 'web3auth'): ?>
            <span style="color: #28a745;">✓ Connected via Social Login</span><br>
            <code style="font-size: 12px;"><?php echo esc_html(substr($wallet_address, 0, 8) . '...' . substr($wallet_address, -6)); ?></code>
            <br><a href="https://yesallofus.com/dashboard" target="_blank" style="color: #6b7280; font-size: 12px;">Manage on Dashboard →</a>
        <?php else: ?>
            <div id="wallet-status"><span style="color: #666;">Checking...</span></div>
        <?php endif; ?>
    </td>
</tr>
        </table>
        
        <p style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
            <a href="#" id="disconnect-store" style="color: #f0ad4e; text-decoration: none;">
                <?php _e('Disconnect Store', 'yesallofus'); ?>
            </a>
            <span style="color: #999; margin-left: 10px; font-size: 12px;"><?php _e('(You can reconnect later by reactivating the plugin)', 'yesallofus'); ?></span>
        </p>
    </div>
    
    <!-- Referral Program Section -->
    <div class="dltpays-referral-card" style="background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%); padding: 24px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); margin: 20px 0; color: #fff;">
        <div style="display: flex; align-items: center; margin-bottom: 16px;">
            <span style="font-size: 28px; margin-right: 12px;">💰</span>
            <h2 style="margin: 0; color: #fff; font-size: 22px;"><?php _e('Earn by Sharing YesAllofUs', 'yesallofus'); ?></h2>
        </div>
        
        <p style="font-size: 16px; line-height: 1.6; margin-bottom: 20px; color: #e0e7ef;">
            <?php _e('Share your referral code with other store owners. When they install YesAllofUs and process sales...', 'yesallofus'); ?>
        </p>
        
        <div style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <span style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #a0b4c8;"><?php _e('Your Referral Code', 'yesallofus'); ?></span>
                    <div id="store-referral-code" style="font-size: 28px; font-weight: bold; font-family: monospace; letter-spacing: 3px; color: #4ade80; margin-top: 4px;">
                        <span style="color: #666;">Loading...</span>
                    </div>
                </div>
                <button type="button" id="copy-referral-code" style="
                    background: #4ade80;
                    color: #1e3a5f;
                    border: none;
                    padding: 12px 24px;
                    font-size: 14px;
                    font-weight: bold;
                    cursor: pointer;
                    border-radius: 6px;
                    transition: all 0.2s;
                " onmouseover="this.style.background='#22c55e'" onmouseout="this.style.background='#4ade80'">
                    <?php _e('📋 Copy Code', 'yesallofus'); ?>
                </button>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 16px;">
            <div style="background: rgba(255,255,255,0.08); border-radius: 6px; padding: 12px; text-align: center;">
                <div style="font-size: 11px; text-transform: uppercase; color: #a0b4c8;">Level 1</div>
                <div style="font-size: 20px; font-weight: bold; color: #4ade80;">25%</div>
            </div>
            <div style="background: rgba(255,255,255,0.08); border-radius: 6px; padding: 12px; text-align: center;">
                <div style="font-size: 11px; text-transform: uppercase; color: #a0b4c8;">Level 2</div>
                <div style="font-size: 20px; font-weight: bold; color: #4ade80;">5%</div>
            </div>
            <div style="background: rgba(255,255,255,0.08); border-radius: 6px; padding: 12px; text-align: center;">
                <div style="font-size: 11px; text-transform: uppercase; color: #a0b4c8;">Level 3</div>
                <div style="font-size: 20px; font-weight: bold; color: #4ade80;">3%</div>
            </div>
            <div style="background: rgba(255,255,255,0.08); border-radius: 6px; padding: 12px; text-align: center;">
                <div style="font-size: 11px; text-transform: uppercase; color: #a0b4c8;">Level 4</div>
                <div style="font-size: 20px; font-weight: bold; color: #4ade80;">2%</div>
            </div>
            <div style="background: rgba(255,255,255,0.08); border-radius: 6px; padding: 12px; text-align: center;">
                <div style="font-size: 11px; text-transform: uppercase; color: #a0b4c8;">Level 5</div>
                <div style="font-size: 20px; font-weight: bold; color: #4ade80;">1%</div>
            </div>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.1);">
            <div>
                <span style="font-size: 12px; color: #a0b4c8;"><?php _e('Your Referral Earnings', 'yesallofus'); ?></span>
                <div id="chainb-earnings" style="font-size: 24px; font-weight: bold; color: #4ade80;">$0.00</div>
            </div>
            <div style="font-size: 13px; color: #a0b4c8; text-align: right;">
                <?php _e('% of platform fees paid by<br>stores you refer', 'yesallofus'); ?>
            </div>
        </div>
    </div>
    
    <form method="post" action="options.php">
        <?php settings_fields('dltpays_settings'); ?>
        
        <!-- Hidden fields to preserve store_id only - api_secret handled separately -->
        <input type="hidden" name="dltpays_store_id" value="<?php echo esc_attr($store_id); ?>">
        
        <h2><?php _e('Commission Rates', 'yesallofus'); ?></h2>
        <p class="description"><?php _e('Set commission percentages for each MLM level. These are percentages of the platform fee that YesAllofUs charges.', 'yesallofus'); ?></p>
        
        <table class="form-table">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <tr>
                <th scope="row">
                    <label for="rate_l<?php echo $i; ?>"><?php printf(__('Level %d', 'yesallofus'), $i); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="rate_l<?php echo $i; ?>" 
                           name="dltpays_rate_l<?php echo $i; ?>" 
                           value="<?php echo esc_attr($rates[$i-1] ?? 0); ?>" 
                           min="0" 
                           max="50" 
                           step="0.5"
                           style="width: 80px;">
                    <span>%</span>
                    <?php if ($i === 1): ?>
                        <span class="description"><?php _e('(Direct referrer)', 'yesallofus'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endfor; ?>
            <tr>
                <th scope="row"><?php _e('Total Commission', 'yesallofus'); ?></th>
                <td>
                    <strong id="total-rate" style="font-size: 16px;"><?php echo array_sum($rates); ?>%</strong>
                    <span class="description"><?php _e('of platform fee goes to affiliates', 'yesallofus'); ?></span>
                </td>
            </tr>
        </table>
        
        <h2><?php _e('Payout Settings', 'yesallofus'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="dltpays_payout_mode"><?php _e('Payout Mode', 'yesallofus'); ?></label>
                </th>
                <td>
                    <select id="dltpays_payout_mode" name="dltpays_payout_mode" style="min-width: 200px;">
    <option value="manual" <?php selected($payout_mode, 'manual'); ?>><?php _e('Manual - Sign each payout yourself', 'yesallofus'); ?></option>
    <option value="auto" <?php selected($payout_mode, 'auto'); ?> disabled><?php _e('Auto - Coming Soon (pending regulatory clarity)', 'yesallofus'); ?></option>
</select>
                    
                    <div id="payout-mode-description" style="margin-top: 10px; padding: 12px; background: #f8f9fa; border-left: 3px solid #2563eb; border-radius: 4px;">
                        <div id="manual-mode-info" style="<?php echo $payout_mode === 'manual' ? '' : 'display:none;'; ?>">
                            <strong style="color: #1e40af;">📱 Manual Mode (Recommended for low volume)</strong>
                            <ul style="margin: 8px 0 0 20px; color: #555;">
                                <li>You receive a push notification for each affiliate payout</li>
                                <li>Open Xaman and approve the transaction</li>
                                <li>Full control - you see every payment before it happens</li>
                                <li>Best for: stores wanting maximum oversight</li>
                            </ul>
                        </div>
                        <div id="auto-mode-info" style="<?php echo $payout_mode === 'auto' ? '' : 'display:none;'; ?>">
                            <strong style="color: #059669;">⚡ Auto Mode (Recommended for high volume)</strong>
                            <ul style="margin: 8px 0 0 20px; color: #555;">
                                <li>Payouts sign automatically - no manual approval needed</li>
                                <li>Set your own limits for security</li>
                                <li>Uses Crossmark wallet for secure delegation</li>
                                <li>Revoke anytime from your wallet settings</li>
                                <li>Best for: hands-off operation, 10+ orders per day</li>
                            </ul>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

<h2><?php _e('Payout Batching', 'yesallofus'); ?></h2>
<p class="description"><?php _e('Control when affiliate commissions are paid out. Batching reduces transaction fees and approval requests.', 'yesallofus'); ?></p>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="dltpays_payout_threshold"><?php _e('Minimum Payout', 'yesallofus'); ?></label>
        </th>
        <td>
            <select id="dltpays_payout_threshold" name="dltpays_payout_threshold" style="min-width: 200px;">
                <option value="0" <?php selected(get_option('dltpays_payout_threshold', 0), 0); ?>><?php _e('No minimum - pay instantly', 'yesallofus'); ?></option>
                <option value="5" <?php selected(get_option('dltpays_payout_threshold', 0), 5); ?>>$5 <?php _e('minimum', 'yesallofus'); ?></option>
                <option value="10" <?php selected(get_option('dltpays_payout_threshold', 0), 10); ?>>$10 <?php _e('minimum', 'yesallofus'); ?></option>
                <option value="25" <?php selected(get_option('dltpays_payout_threshold', 0), 25); ?>>$25 <?php _e('minimum', 'yesallofus'); ?></option>
                <option value="50" <?php selected(get_option('dltpays_payout_threshold', 0), 50); ?>>$50 <?php _e('minimum', 'yesallofus'); ?></option>
                <option value="100" <?php selected(get_option('dltpays_payout_threshold', 0), 100); ?>>$100 <?php _e('minimum', 'yesallofus'); ?></option>
            </select>
            <p class="description"><?php _e('Commissions accumulate until they reach this amount before paying out.', 'yesallofus'); ?></p>
        </td>
    </tr>
    <tr>
    <th scope="row">
        <label for="dltpays_payout_schedule"><?php _e('Payout Schedule', 'yesallofus'); ?></label>
    </th>
    <td>
        <select id="dltpays_payout_schedule" name="dltpays_payout_schedule" style="min-width: 200px;">
            <option value="0" <?php selected(get_option('dltpays_payout_schedule', '0'), '0'); ?>><?php _e('Instant - pay after each order', 'yesallofus'); ?></option>
            <option value="1" <?php selected(get_option('dltpays_payout_schedule', '0'), '1'); ?>><?php _e('Every 1 day', 'yesallofus'); ?></option>
            <option value="3" <?php selected(get_option('dltpays_payout_schedule', '0'), '3'); ?>><?php _e('Every 3 days', 'yesallofus'); ?></option>
            <option value="7" <?php selected(get_option('dltpays_payout_schedule', '0'), '7'); ?>><?php _e('Every 7 days', 'yesallofus'); ?></option>
            <option value="14" <?php selected(get_option('dltpays_payout_schedule', '0'), '14'); ?>><?php _e('Every 14 days', 'yesallofus'); ?></option>
            <option value="30" <?php selected(get_option('dltpays_payout_schedule', '0'), '30'); ?>><?php _e('Every 30 days', 'yesallofus'); ?></option>
        </select>
        <p class="description"><?php _e('How often to batch and process affiliate payouts. Batching reduces transaction fees and approval requests.', 'yesallofus'); ?></p>
    </td>
</tr>
</table>

<div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px; margin: 16px 0;">
    <p style="color: #1e40af; margin: 0; font-size: 14px;">
        <strong>💡 Tip:</strong> <?php _e('For Xaman manual mode, we recommend setting a minimum payout of $10-25 to reduce the number of approvals you need to sign.', 'yesallofus'); ?>
    </p>
</div>

        <!-- ================================================================= -->
        <!-- AUTO-SIGNING SETUP SECTION -->
        <!-- ================================================================= -->
        <div id="auto-signing-section" style="<?php echo $payout_mode === 'auto' ? '' : 'display:none;'; ?> margin-top: 30px;">
    <div style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); padding: 24px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); color: #fff; opacity: 0.5;">
        <div style="display: flex; align-items: center; margin-bottom: 16px;">
            <span style="font-size: 28px; margin-right: 12px;">⚡</span>
            <h2 style="margin: 0; color: #fff; font-size: 22px;"><?php _e('Auto-Signing Setup', 'yesallofus'); ?></h2>
            <span style="background: rgba(255,255,255,0.2); color: #fff; font-size: 11px; padding: 4px 10px; border-radius: 4px; margin-left: 12px;"><?php _e('Coming Soon', 'yesallofus'); ?></span>
        </div>
        
        <!-- Coming Soon Notice -->
        <div style="background: rgba(255,255,255,0.15); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
            <p style="margin: 0; color: #fff; font-size: 14px;">
                <?php _e('Auto-signing payouts are not yet available pending regulatory clarity. Currently, only manual signing via Xaman is supported.', 'yesallofus'); ?>
            </p>
        </div>
                
                <!-- Status Box -->
                <div id="autosign-status-box" style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #c4b5fd;"><?php _e('Status', 'yesallofus'); ?></span>
                            <div id="autosign-status" style="font-size: 18px; font-weight: bold; margin-top: 4px;">
                                <span style="color: #fbbf24;">⏳ <?php _e('Checking...', 'yesallofus'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- STEP 1: Terms & Conditions -->
                <div id="autosign-terms-section" style="display: none; background: rgba(255,255,255,0.1); border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="margin: 0 0 16px 0; color: #fff;">⚠️ <?php _e('Step 1: Read & Accept Terms', 'yesallofus'); ?></h3>
                    
                    <div style="background: #fef3c7; color: #92400e; padding: 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; line-height: 1.6;">
                        <p style="margin: 0 0 12px 0;"><strong><?php _e('By enabling auto-signing, you authorize YesAllofUs to:', 'yesallofus'); ?></strong></p>
                        <ul style="margin: 0 0 12px 20px; padding: 0;">
                            <li><?php _e('Automatically sign and send affiliate commission payments from your connected wallet', 'yesallofus'); ?></li>
                            <li><?php _e('Process payments up to your configured limits without manual approval', 'yesallofus'); ?></li>
                        </ul>
                        
                        <p style="margin: 0 0 12px 0;"><strong style="color: #dc2626;"><?php _e('🛡️ Security Recommendations:', 'yesallofus'); ?></strong></p>
                        <ul style="margin: 0 0 12px 20px; padding: 0;">
                            <li><strong><?php _e('Keep only 1-2 days worth of expected commissions in this wallet', 'yesallofus'); ?></strong></li>
                            <li><?php _e('Top up your wallet regularly rather than storing large balances', 'yesallofus'); ?></li>
                            <li><?php _e('Set conservative limits below to minimize risk', 'yesallofus'); ?></li>
                        </ul>
                        
                        <p style="margin: 0; background: #fde68a; padding: 8px; border-radius: 4px;"><strong><?php _e('You can revoke auto-signing permission at any time from your Xaman wallet settings.', 'yesallofus'); ?></strong></p>
                    </div>
                    
                    <label style="display: flex; align-items: flex-start; cursor: pointer; color: #fff;">
                        <input type="checkbox" id="autosign-terms-checkbox" style="margin: 4px 12px 0 0; width: 20px; height: 20px; cursor: pointer;">
                        <span style="line-height: 1.5;">
                            <?php _e('I understand and accept the risks. I have read the security recommendations and agree to the terms of auto-signing.', 'yesallofus'); ?>
                        </span>
                    </label>
                    
                    <button type="button" id="accept-autosign-terms" disabled style="
                        margin-top: 16px;
                        background: #4ade80;
                        color: #1e3a5f;
                        border: none;
                        padding: 12px 24px;
                        font-size: 14px;
                        font-weight: bold;
                        cursor: not-allowed;
                        border-radius: 6px;
                        opacity: 0.5;
                    ">
                        <?php _e('Accept & Continue →', 'yesallofus'); ?>
                    </button>
                </div>
                
                <!-- STEP 2: Configure Limits -->
                <div id="autosign-limits-section" style="display: none;">
                    <h3 style="margin: 0 0 16px 0; color: #fff;">🎚️ <?php _e('Step 2: Configure Your Limits', 'yesallofus'); ?></h3>
                    
                    <div style="display: grid; gap: 24px;">
                        <!-- Max Single Payout Slider -->
                        <div style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <label style="font-weight: bold; color: #fff;"><?php _e('Max Single Payout', 'yesallofus'); ?></label>
                                <span id="max-single-value" style="font-size: 24px; font-weight: bold; color: #4ade80;">$100</span>
                            </div>
                            <input type="range" 
                                   id="autosign-max-single" 
                                   min="1" 
                                   max="10000" 
                                   value="100"
                                   style="width: 100%; height: 8px; cursor: pointer; accent-color: #4ade80;">
                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #c4b5fd; margin-top: 4px;">
                                <span>$1</span>
                                <span>$10,000</span>
                            </div>
                            <p style="margin: 8px 0 0 0; font-size: 13px; color: #c4b5fd;">
                                <?php _e('Any single payment above this amount will require manual approval.', 'yesallofus'); ?>
                            </p>
                        </div>
                        
                        <!-- Daily Limit Slider -->
                        <div style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <label style="font-weight: bold; color: #fff;"><?php _e('Daily Auto-Sign Limit', 'yesallofus'); ?></label>
                                <span id="daily-limit-value" style="font-size: 24px; font-weight: bold; color: #4ade80;">$1,000</span>
                            </div>
                            <input type="range" 
                                   id="autosign-daily-limit" 
                                   min="10" 
                                   max="50000" 
                                   value="1000"
                                   step="10"
                                   style="width: 100%; height: 8px; cursor: pointer; accent-color: #4ade80;">
                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #c4b5fd; margin-top: 4px;">
                                <span>$10</span>
                                <span>$50,000</span>
                            </div>
                            <p style="margin: 8px 0 0 0; font-size: 13px; color: #c4b5fd;">
                                <?php _e('Total auto-signed payouts per day. Once exceeded, manual approval required until next day.', 'yesallofus'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <button type="button" id="save-autosign-limits" style="
                        margin-top: 20px;
                        background: #4ade80;
                        color: #1e3a5f;
                        border: none;
                        padding: 14px 28px;
                        font-size: 16px;
                        font-weight: bold;
                        cursor: pointer;
                        border-radius: 8px;
                        width: 100%;
                    ">
                        <?php _e('💾 Save Limits & Continue →', 'yesallofus'); ?>
                    </button>
                </div>
                
                <!-- STEP 3: Setup Wallet -->
                <div id="autosign-setup-section" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <h3 style="margin: 0 0 16px 0; color: #fff;">🔗 <?php _e('Step 3: Connect Your Wallet', 'yesallofus'); ?></h3>
                    
                    <p style="color: #e9d5ff; margin-bottom: 16px; line-height: 1.6;">
                        <?php _e('Add YesAllofUs as an authorized signer on your wallet. This requires the Crossmark browser extension.', 'yesallofus'); ?>
                    </p>
                    
                    <div style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                        <p style="margin: 0 0 8px 0; color: #fff;"><strong><?php _e('Platform Signer Address:', 'yesallofus'); ?></strong></p>
                        <code id="platform-signer-address" style="background: rgba(0,0,0,0.3); padding: 8px 12px; border-radius: 4px; font-size: 12px; word-break: break-all; display: block; color: #4ade80;">
                            Loading...
                        </code>
                        <button type="button" id="copy-signer-address" style="margin-top: 8px; background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                            📋 <?php _e('Copy Address', 'yesallofus'); ?>
                        </button>
                    </div>
                    
                    <div style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                        <p style="margin: 0 0 8px 0; color: #fff;"><strong><?php _e("Don't have Crossmark?", 'yesallofus'); ?></strong></p>
                        <a href="https://crossmark.io/" target="_blank" style="color: #4ade80; text-decoration: underline;">
                            <?php _e('Download Crossmark browser extension →', 'yesallofus'); ?>
                        </a>
                    </div>
                    
                    <?php if ($wallet_type === 'web3auth'): ?>
<a href="https://yesallofus.com/dashboard" target="_blank" style="
    display: block;
    background: #fff;
    color: #7c3aed;
    border: none;
    padding: 14px 28px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    border-radius: 8px;
    width: 100%;
    margin-bottom: 12px;
    text-align: center;
    text-decoration: none;
    box-sizing: border-box;
">
    ⚡ <?php _e('Confirm Auto-Sign on Dashboard', 'yesallofus'); ?>
</a>
<?php else: ?>
<button type="button" id="setup-autosign-crossmark" style="
    background: #fff;
    color: #7c3aed;
    border: none;
    padding: 14px 28px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    border-radius: 8px;
    width: 100%;
    margin-bottom: 12px;
">
    🦊 <?php _e('Open Crossmark Setup', 'yesallofus'); ?>
</button>
<?php endif; ?>
                    
                    <button type="button" id="verify-autosign-setup" style="
                        background: transparent;
                        color: #fff;
                        border: 2px solid rgba(255,255,255,0.3);
                        padding: 12px 24px;
                        font-size: 14px;
                        cursor: pointer;
                        border-radius: 8px;
                        width: 100%;
                    ">
                        ✓ <?php _e("I've Added the Signer - Verify Setup", 'yesallofus'); ?>
                    </button>
                </div>
                
                <!-- SUCCESS: Auto-signing enabled -->
                <div id="autosign-enabled-section" style="display: none;">
                    <div style="text-align: center; padding: 20px;">
                        <span style="font-size: 48px;">✅</span>
                        <h3 style="color: #4ade80; margin: 12px 0;"><?php _e('Auto-Signing Active!', 'yesallofus'); ?></h3>
                        <p style="color: #e9d5ff;"><?php _e('Affiliate payouts will be processed automatically within your limits.', 'yesallofus'); ?></p>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px;">
                        <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 6px; text-align: center;">
                            <div style="font-size: 12px; color: #c4b5fd;"><?php _e('Max Single Payout', 'yesallofus'); ?></div>
                            <div id="current-max-single" style="font-size: 20px; font-weight: bold; color: #4ade80;">$100</div>
                        </div>
                        <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 6px; text-align: center;">
                            <div style="font-size: 12px; color: #c4b5fd;"><?php _e('Daily Limit', 'yesallofus'); ?></div>
                            <div id="current-daily-limit" style="font-size: 20px; font-weight: bold; color: #4ade80;">$1,000</div>
                        </div>
                    </div>
                    
                    <button type="button" id="edit-autosign-limits" style="
                        margin-top: 16px;
                        background: rgba(255,255,255,0.1);
                        color: #fff;
                        border: none;
                        padding: 10px 20px;
                        font-size: 14px;
                        cursor: pointer;
                        border-radius: 6px;
                        width: 100%;
                    ">
                        ✏️ <?php _e('Edit Limits', 'yesallofus'); ?>
                    </button>
                    
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2);">
                        <button type="button" id="revoke-autosign" style="
                            background: transparent;
                            color: #fca5a5;
                            border: 1px solid #fca5a5;
                            padding: 10px 20px;
                            font-size: 14px;
                            cursor: pointer;
                            border-radius: 6px;
                        ">
                            <?php _e('Revoke Auto-Signing Permission', 'yesallofus'); ?>
                        </button>
                        <p style="margin-top: 8px; font-size: 12px; color: #c4b5fd;">
                            <?php _e('To fully revoke, also remove the signer from your Xaman wallet settings.', 'yesallofus'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <!-- END AUTO-SIGNING SECTION -->
        
        <h2><?php _e('Tracking Settings', 'yesallofus'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="dltpays_cookie_days"><?php _e('Cookie Duration', 'yesallofus'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="dltpays_cookie_days" 
                           name="dltpays_cookie_days" 
                           value="<?php echo esc_attr(get_option('dltpays_cookie_days', 30)); ?>" 
                           min="1" 
                           max="365"
                           style="width: 80px;">
                    <span><?php _e('days', 'yesallofus'); ?></span>
                    <p class="description"><?php _e('How long referral tracking lasts after someone clicks an affiliate link.', 'yesallofus'); ?></p>
                </td>
            </tr>
        </table>
        
        <!-- Testnet Mode Toggle - COMMENTED OUT FOR PRODUCTION
    <div style="background: #fef3c7; border: 2px solid #f59e0b; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h3 style="color: #92400e; margin-top: 0;">🧪 Developer Mode</h3>
        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" name="dltpays_testnet_mode" value="1" <?php checked(get_option('dltpays_testnet_mode'), 1); ?>>
            <span><strong>Enable Testnet Mode</strong> — WooCommerce orders trigger XRPL testnet payments (no real money)</span>
        </label>
        <p style="color: #78350f; font-size: 13px; margin: 10px 0 0 0;">⚠️ Only enable for demos. Disable for production.</p>
    </div>
-->
    
    <?php submit_button(__('Save Settings', 'yesallofus')); ?>
   </form>
<?php endif; ?>

<hr>

<h2><?php _e('Shortcodes', 'yesallofus'); ?></h2>
    <table class="widefat" style="max-width: 600px;">
        <thead>
            <tr>
                <th><?php _e('Shortcode', 'yesallofus'); ?></th>
                <th><?php _e('Description', 'yesallofus'); ?></th>
            </tr>
        </thead>
        <tbody>
    <tr>
        <td><code>[yesallofus_affiliate_signup]</code></td>
        <td><?php _e('Affiliate registration form', 'yesallofus'); ?></td>
    </tr>
    <tr>
        <td><code>[yesallofus_affiliate_dashboard_link]</code></td>
        <td><?php _e('Button linking to YesAllofUs affiliate dashboard', 'yesallofus'); ?></td>
    </tr>
</tbody>
    </table>
    
    <?php if ($store_id): ?>
    <!-- Danger Zone -->
    <div style="margin-top: 40px; padding: 20px; border: 2px solid #dc2626; border-radius: 8px; background: #fef2f2;">
        <h2 style="color: #991b1b; margin-top: 0; margin-bottom: 10px;">⚠️ <?php _e('Danger Zone', 'yesallofus'); ?></h2>
        <p style="color: #7f1d1d; margin-bottom: 15px;">
            <?php _e('Permanently delete your store and all associated data. This action cannot be undone.', 'yesallofus'); ?>
        </p>
        <button type="button" id="delete-store-permanent" style="
            background: #1f2937;
            color: #ef4444;
            border: 2px solid #dc2626;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
        " onmouseover="this.style.background='#dc2626'; this.style.color='#fff';" onmouseout="this.style.background='#1f2937'; this.style.color='#ef4444';">
            <?php _e('Permanently Delete Store', 'yesallofus'); ?>
        </button>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    console.log('Web3authModal:', typeof window.Web3authModal);
    console.log('Web3authXrplProvider:', typeof window.Web3authXrplProvider);
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var adminNonce = '<?php echo wp_create_nonce('dltpays_admin_nonce'); ?>';

    // =========================================================================
// WEB3AUTH INITIALIZATION
// =========================================================================
// Enable link when terms checked
$('#web3auth-terms-checkbox').on('change', function() {
    var btn = $('#web3auth-connect-btn');
    if ($(this).is(':checked')) {
        btn.css({ background: '#2563eb', color: '#fff', cursor: 'pointer', pointerEvents: 'auto' });
    } else {
        btn.css({ background: '#e5e7eb', color: '#9ca3af', cursor: 'not-allowed', pointerEvents: 'none' });
    }
});
    
    // =========================================================================
    // PROMOTIONAL CODE
    // =========================================================================
    $('#apply-promo-code').on('click', function() {
        var code = $('#promo-code-input').val().trim().toUpperCase();
        var btn = $(this);
        var msgDiv = $('#promo-code-message');
        
        if (!code || code.length < 6) {
            msgDiv.html('<span style="color: #fecaca;">⚠ Please enter a valid promotional code</span>').show();
            return;
        }
        
        btn.prop('disabled', true).text('Validating...');
        msgDiv.hide();
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: { action: 'dltpays_validate_promo_code', nonce: adminNonce, code: code },
            success: function(response) {
                if (response.success) {
                    msgDiv.html(
                        '<div style="padding: 12px; background: rgba(255,255,255,0.15); border-radius: 6px;">' +
                        '<span style="color: #fff; font-size: 16px;">✓ Code applied!</span><br>' +
                        '<span style="color: #d1fae5;">Referred by: <strong>' + response.data.store_name + '</strong></span><br>' +
                        '<span style="color: #fef08a; font-weight: bold;">🎉 You\'ll get 50% off platform fees for your first month!</span>' +
                        '</div>'
                    ).show();
                    btn.text('✓ Applied!').css('background', '#22c55e').css('color', '#fff');
                    setTimeout(function() {
                        msgDiv.append('<div style="margin-top: 12px; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 4px; color: #fff;">👉 Now <strong>deactivate</strong> and <strong>reactivate</strong> the plugin to complete registration with your discount.</div>');
                    }, 1000);
                } else {
                    msgDiv.html('<span style="color: #fecaca;">✗ ' + (response.data || 'Invalid promotional code') + '</span>').show();
                    btn.prop('disabled', false).text('Apply Code');
                }
            },
            error: function() {
                msgDiv.html('<span style="color: #fecaca;">✗ Connection error. Please try again.</span>').show();
                btn.prop('disabled', false).text('Apply Code');
            }
        });
    });
    
    $('#promo-code-input').on('input', function() {
        $(this).val($(this).val().toUpperCase());
    });
    
    // =========================================================================
    // PAYOUT MODE TOGGLE
    // =========================================================================
    $('#dltpays_payout_mode').on('change', function() {
        if ($(this).val() === 'auto') {
            $('#auto-signing-section').show();
            $('#manual-mode-info').hide();
            $('#auto-mode-info').show();
            loadAutosignStatus();
        } else {
            $('#auto-signing-section').hide();
            $('#manual-mode-info').show();
            $('#auto-mode-info').hide();
        }
    });
    
    // =========================================================================
    // COMMISSION RATES
    // =========================================================================
    function updateTotalRate() {
        let total = 0;
        for (let i = 1; i <= 5; i++) {
            total += parseFloat($('#rate_l' + i).val()) || 0;
        }
        $('#total-rate').text(total.toFixed(1) + '%');
        $('#total-rate').css('color', total > 50 ? '#dc3545' : '#28a745');
    }
    
    $('[id^="rate_l"]').on('input', updateTotalRate);
    updateTotalRate();
    
    // =========================================================================
    // AUTO-SIGNING FUNCTIONS
    // =========================================================================
    function loadAutosignStatus() {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: { action: 'dltpays_get_autosign_settings', nonce: adminNonce },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Update platform signer address
                    if (data.platform_signer_address) {
                        var addr = data.platform_signer_address;
                        $('#platform-signer-address')
                            .text(addr.substring(0, 8) + '...' + addr.slice(-4))
                            .data('full-address', addr);
                    }
                    
                    // Update slider values
                    var maxSingle = data.auto_sign_max_single_payout || 100;
                    var dailyLimit = data.auto_sign_daily_limit || 1000;
                    
                    $('#autosign-max-single').val(maxSingle);
                    $('#max-single-value').text('$' + maxSingle.toLocaleString());
                    $('#autosign-daily-limit').val(dailyLimit);
                    $('#daily-limit-value').text('$' + dailyLimit.toLocaleString());
                    
                    // Show appropriate section based on status
                    if (data.auto_signing_enabled) {
                        $('#autosign-status').html('<span style="color: #4ade80;">✅ Active</span>');
                        $('#autosign-terms-section, #autosign-limits-section, #autosign-setup-section').hide();
                        $('#autosign-enabled-section').show();
                        $('#current-max-single').text('$' + maxSingle.toLocaleString());
                        $('#current-daily-limit').text('$' + dailyLimit.toLocaleString());
                    } else if (data.auto_sign_terms_accepted) {
                        $('#autosign-status').html('<span style="color: #fbbf24;">⚠️ Setup Required</span>');
                        $('#autosign-terms-section').hide();
                        $('#autosign-limits-section').show();
                        $('#autosign-setup-section').show();
                        $('#autosign-enabled-section').hide();
                    } else {
                        $('#autosign-status').html('<span style="color: #f87171;">❌ Not Configured</span>');
                        $('#autosign-terms-section').show();
                        $('#autosign-limits-section, #autosign-setup-section, #autosign-enabled-section').hide();
                    }
                } else {
                    $('#autosign-status').html('<span style="color: #f87171;">❌ Error loading</span>');
                }
            },
            error: function() {
                $('#autosign-status').html('<span style="color: #f87171;">❌ Connection error</span>');
            }
        });
    }
    
    // Load on page load if auto mode selected
    if ($('#dltpays_payout_mode').val() === 'auto') {
        loadAutosignStatus();
    }
    
    // Terms checkbox enables button
    $('#autosign-terms-checkbox').on('change', function() {
        var btn = $('#accept-autosign-terms');
        if ($(this).is(':checked')) {
            btn.prop('disabled', false).css({ opacity: 1, cursor: 'pointer' });
        } else {
            btn.prop('disabled', true).css({ opacity: 0.5, cursor: 'not-allowed' });
        }
    });
    
    // Accept terms
    $('#accept-autosign-terms').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: { action: 'dltpays_update_autosign_settings', nonce: adminNonce, auto_sign_terms_accepted: true },
            success: function(response) {
                if (response.success) {
                    $('#autosign-terms-section').hide();
                    $('#autosign-limits-section').show();
                    $('#autosign-status').html('<span style="color: #fbbf24;">⚠️ Configure Limits</span>');
                } else {
                    alert('Error: ' + (response.data || 'Failed to save'));
                    btn.prop('disabled', false).text('Accept & Continue →');
                }
            },
            error: function() {
                alert('Connection error');
                btn.prop('disabled', false).text('Accept & Continue →');
            }
        });
    });
    
    // Slider value updates
    $('#autosign-max-single').on('input', function() {
        $('#max-single-value').text('$' + parseInt($(this).val()).toLocaleString());
    });
    
    $('#autosign-daily-limit').on('input', function() {
        $('#daily-limit-value').text('$' + parseInt($(this).val()).toLocaleString());
    });
    
    // Save limits
    $('#save-autosign-limits').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'dltpays_update_autosign_settings',
                nonce: adminNonce,
                auto_sign_max_single_payout: $('#autosign-max-single').val(),
                auto_sign_daily_limit: $('#autosign-daily-limit').val()
            },
            success: function(response) {
                if (response.success) {
                    $('#autosign-limits-section').hide();
                    $('#autosign-setup-section').show();
                    $('#autosign-status').html('<span style="color: #fbbf24;">⚠️ Wallet Setup Required</span>');
                } else {
                    alert('Error: ' + (response.data || 'Failed to save'));
                }
                btn.prop('disabled', false).text('💾 Save Limits & Continue →');
            },
            error: function() {
                alert('Connection error');
                btn.prop('disabled', false).text('💾 Save Limits & Continue →');
            }
        });
    });
    
    // Copy signer address
    $('#copy-signer-address').on('click', function() {
        var address = $('#platform-signer-address').data('full-address');
        var btn = $(this);
        
        if (navigator.clipboard && address) {
            navigator.clipboard.writeText(address).then(function() {
                btn.text('✓ Copied!');
                setTimeout(function() { btn.text('📋 Copy Address'); }, 2000);
            });
        }
    });
    
    // Setup with Crossmark (Auto-signing section)
    $('#setup-autosign-crossmark').on('click', async function() {
        var btn = $(this);
        
        if (typeof window.xrpl === 'undefined' || !window.xrpl.crossmark) {
            if (confirm('Crossmark wallet not detected!\n\nClick OK to download Crossmark.')) {
                window.open('https://crossmark.io', '_blank');
            }
            return;
        }
        
        btn.prop('disabled', true).text('Connecting...');
        
        try {
            var sdk = window.xrpl.crossmark;
            var signIn = await sdk.methods.signInAndWait();
            
            if (!signIn.response.data.address) {
                throw new Error('Connection cancelled');
            }
            
            var userWallet = signIn.response.data.address;
            btn.text('Adding signer...');
            
            var tx = await sdk.methods.signAndSubmitAndWait({
                TransactionType: 'SignerListSet',
                Account: userWallet,
                SignerQuorum: 1,
                SignerEntries: [{
                    SignerEntry: {
                        Account: $('#platform-signer-address').data('full-address'),
                        SignerWeight: 1
                    }
                }]
            });
            
            if (tx.response.data.meta.TransactionResult === 'tesSUCCESS') {
                alert('✅ Signer added! Click "Verify Setup" to enable auto-signing.');
            } else {
                throw new Error(tx.response.data.meta.TransactionResult);
            }
            
        } catch (err) {
            alert('❌ ' + (err.message || 'Crossmark error'));
        }
        btn.prop('disabled', false).text('🦊 Open Crossmark Setup');
    });
    
    // Verify auto-sign setup
    $('#verify-autosign-setup').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Verifying...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: { action: 'dltpays_verify_autosign', nonce: adminNonce },
            success: function(response) {
                if (response.success && response.data.auto_signing_enabled) {
                    alert('✅ ' + (response.data.message || 'Auto-signing enabled!'));
                    location.reload();
                } else {
                    alert('❌ ' + (response.data.message || response.data || 'Verification failed. Make sure you added the signer in Crossmark.'));
                    btn.prop('disabled', false).text('✓ I\'ve Added the Signer - Verify Setup');
                }
            },
            error: function() {
                alert('Connection error');
                btn.prop('disabled', false).text('✓ I\'ve Added the Signer - Verify Setup');
            }
        });
    });
    
    // Edit limits
    $('#edit-autosign-limits').on('click', function() {
        $('#autosign-enabled-section').hide();
        $('#autosign-limits-section').show();
        $('#save-autosign-limits').text('💾 Save Limits');
    });
    
    // Revoke auto-signing
    $('#revoke-autosign').on('click', function() {
        if (!confirm('Are you sure you want to revoke auto-signing? You will need to manually approve each payout in Xaman.')) {
            return;
        }
        
        var btn = $(this);
        btn.prop('disabled', true).text('Revoking...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: { action: 'dltpays_revoke_autosign', nonce: adminNonce },
            success: function(response) {
                alert(response.data.message || 'Auto-signing disabled. Remember to also remove the signer from your wallet.');
                loadAutosignStatus();
                btn.prop('disabled', false).text('Revoke Auto-Signing Permission');
            },
            error: function() {
                alert('Connection error');
                btn.prop('disabled', false).text('Revoke Auto-Signing Permission');
            }
        });
    });
    
    // =========================================================================
    // CONNECTION STATUS & WALLET HANDLERS
    // =========================================================================
    <?php if ($has_credentials): ?>
    $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: { action: 'dltpays_check_connection', nonce: adminNonce },
        success: function(response) {
            if (response.success) {
                var data = response.data;
                
                $('#connection-status').html('<span style="color: #28a745;">✓ Connected</span>');
                
                if (data.store_referral_code) {
                    $('#store-referral-code').html('<span style="color: #4ade80;">' + data.store_referral_code + '</span>');
                } else {
                    $('#store-referral-code').html('<span style="color: #999;">Not available</span>');
                }
                
                var earnings = data.chainb_earned || 0;
                $('#chainb-earnings').text('$' + earnings.toFixed(2));
                
                if (data.xaman_connected && data.wallet_address) {
                    var walletType = data.push_enabled ? 'Xaman' : 'Crossmark';
                    var walletHtml = '<span style="color: #28a745;">✓ Connected via ' + walletType + '</span><br>' +
                        '<code style="font-size: 12px;">' + data.wallet_address.substring(0, 8) + '...' + data.wallet_address.slice(-4) + '</code>' +
                        '<br><span style="font-size: 11px; color: #666;">Mode: ' + (data.payout_mode === 'auto' ? '⚡ Auto' : '📱 Manual') + '</span>' +
                        '<br><button type="button" class="button" id="disconnect-wallet" style="margin-top: 8px; color: #dc3545;">Disconnect Wallet</button>';
                    
                    $('#wallet-status').html(walletHtml);
                    
                    // Grey out incompatible payout mode
                    if (data.payout_mode === 'auto') {
                        $('#dltpays_payout_mode option[value="manual"]').prop('disabled', true).text('Manual - Not available (Crossmark connected)');
                    } else if (data.push_enabled) {
                        $('#dltpays_payout_mode option[value="auto"]').prop('disabled', true).text('Auto - Not available (requires Crossmark)');
                    }
                } else {
                    $('#wallet-status').html(
                        '<span style="color: #dc3545;">✗ Not connected</span><br>' +
                        '<button type="button" class="button button-primary" id="connect-wallet" style="margin-top: 5px;">Connect Wallet</button>' +
                        
                        '<div id="wallet-choice-modal" style="display: none; margin-top: 15px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #ddd;">' +
                            '<p style="margin: 0 0 15px 0; font-weight: bold; font-size: 15px;">Choose your wallet:</p>' +
                            
                            '<div id="xaman-option" style="padding: 15px; border: 2px solid #2563eb; border-radius: 8px; margin-bottom: 12px; cursor: pointer; background: #eff6ff;">' +
                                '<div style="display: flex; align-items: center; gap: 10px;">' +
                                    '<span style="font-size: 24px;">📱</span>' +
                                    '<div>' +
                                        '<strong style="color: #1e40af;">Xaman Mobile App</strong>' +
                                        '<span style="background: #2563eb; color: white; font-size: 10px; padding: 2px 6px; border-radius: 3px; margin-left: 8px;">RECOMMENDED</span>' +
                                        '<p style="margin: 4px 0 0 0; font-size: 12px; color: #555;">Manual payouts — approve each via push notification</p>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                            
                            '<div id="crossmark-option" style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; cursor: pointer; background: #fff;">' +
                                '<div style="display: flex; align-items: center; gap: 10px;">' +
                                    '<span style="font-size: 24px;">🦊</span>' +
                                    '<div>' +
                                        '<strong>Crossmark Browser Extension</strong>' +
                                        '<p style="margin: 4px 0 0 0; font-size: 12px; color: #555;">Auto payouts — process automatically within limits</p>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                            
                            '<div id="crossmark-terms" style="display: none; margin-top: 15px; padding: 15px; background: #fef3c7; border-radius: 8px; border: 1px solid #f59e0b;">' +
                                '<h4 style="margin: 0 0 10px 0; color: #92400e;">⚠️ Auto-Payout Terms & Conditions</h4>' +
                                '<div style="font-size: 13px; color: #78350f; line-height: 1.5;">' +
                                    '<p style="margin: 0 0 10px 0;"><strong>By enabling auto-payouts, you authorize YesAllofUs to:</strong></p>' +
                                    '<ul style="margin: 0 0 10px 15px; padding: 0;">' +
                                        '<li>Automatically sign and send affiliate commission payments from your wallet</li>' +
                                        '<li>Process payments up to your configured limits without manual approval</li>' +
                                    '</ul>' +
                                    '<p style="margin: 0 0 10px 0;"><strong style="color: #dc2626;">🛡️ Security Recommendations:</strong></p>' +
                                    '<ul style="margin: 0 0 10px 15px; padding: 0;">' +
                                        '<li><strong>Keep only 1-2 days worth of expected commissions in this wallet</strong></li>' +
                                        '<li>Top up your wallet regularly rather than storing large balances</li>' +
                                        '<li>Set conservative limits to minimize risk</li>' +
                                    '</ul>' +
                                    '<p style="margin: 0; padding: 8px; background: #fde68a; border-radius: 4px;"><strong>You can revoke auto-signing permission at any time from your wallet settings.</strong></p>' +
                                '</div>' +
                                '<label style="display: flex; align-items: flex-start; margin-top: 12px; cursor: pointer;">' +
                                    '<input type="checkbox" id="crossmark-terms-checkbox" style="margin: 3px 10px 0 0; width: 18px; height: 18px;">' +
                                    '<span style="font-size: 13px; color: #78350f;">I have read and agree to the terms and conditions for auto-payouts</span>' +
                                '</label>' +
                                '<button type="button" id="connect-crossmark-btn" disabled style="margin-top: 12px; width: 100%; padding: 12px; background: #9ca3af; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: not-allowed;">' +
                                    '🦊 Connect Crossmark' +
                                '</button>' +
                            '</div>' +
                            
                            '<div id="xaman-qr" style="margin-top: 15px; display: none;"></div>' +
                        '</div>'
                    );
                }
            } else {
                $('#connection-status').html('<span style="color: #dc3545;">✗ ' + (response.data || 'Connection failed') + '</span>');
                $('#wallet-status').html('<span style="color: #666;">-</span>');
            }
        },
        error: function() {
            $('#connection-status').html('<span style="color: #dc3545;">✗ Connection failed</span>');
            $('#wallet-status').html('<span style="color: #666;">-</span>');
        }
    });

    // Show wallet choice modal
    $(document).on('click', '#connect-wallet', function() {
        $('#wallet-choice-modal').slideToggle();
    });

    // Xaman option selected
    $(document).on('click', '#xaman-option', function() {
        $('#xaman-option').css('border-color', '#2563eb').css('background', '#eff6ff');
        $('#crossmark-option').css('border-color', '#ddd').css('background', '#fff');
        $('#crossmark-terms').hide();
        $('#connect-xaman-btn').remove();
        $('#xaman-qr').before('<button type="button" class="button button-primary" id="connect-xaman-btn" style="width: 100%; margin-top: 10px; padding: 10px;">📱 Connect Xaman</button>');
    });

    // Crossmark option selected
    $(document).on('click', '#crossmark-option', function() {
        $('#crossmark-option').css('border-color', '#f59e0b').css('background', '#fffbeb');
        $('#xaman-option').css('border-color', '#ddd').css('background', '#fff');
        $('#crossmark-terms').slideDown();
        $('#connect-xaman-btn').remove();
    });

    // Crossmark terms checkbox
    $(document).on('change', '#crossmark-terms-checkbox', function() {
        var btn = $('#connect-crossmark-btn');
        if ($(this).is(':checked')) {
            btn.prop('disabled', false).css('background', '#f59e0b').css('cursor', 'pointer');
        } else {
            btn.prop('disabled', true).css('background', '#9ca3af').css('cursor', 'not-allowed');
        }
    });

    // Connect with Crossmark (from wallet choice modal)
    $(document).on('click', '#connect-crossmark-btn', async function() {
        var btn = $(this);
        
        if (typeof window.xrpl === 'undefined' || !window.xrpl.crossmark) {
            if (confirm('Crossmark wallet not detected!\n\nClick OK to download Crossmark.')) {
                window.open('https://crossmark.io', '_blank');
            }
            return;
        }
        
        btn.prop('disabled', true).text('Connecting...');
        
        try {
            var sdk = window.xrpl.crossmark;
            var signIn = await sdk.methods.signInAndWait();
            
            if (!signIn.response.data.address) {
                throw new Error('Connection cancelled');
            }
            
            var userWallet = signIn.response.data.address;
            btn.text('Saving...');
            
            // Save wallet to backend
            var saveResult = await $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'dltpays_save_crossmark_wallet',
                    nonce: adminNonce,
                    wallet_address: userWallet
                }
            });
            
            if (saveResult.success) {
                // Also accept auto-sign terms
                await $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'dltpays_update_autosign_settings',
                        nonce: adminNonce,
                        auto_sign_terms_accepted: true
                    }
                });
                
                alert('✅ Wallet connected!\n\nAddress: ' + userWallet.substring(0, 8) + '...' + userWallet.slice(-4) + '\n\nPayout mode set to Auto. Configure your limits in the Auto-Signing section below.');
                location.reload();
            } else {
                throw new Error(saveResult.data || 'Failed to save wallet');
            }
            
        } catch (err) {
            alert('❌ ' + (err.message || 'Crossmark error'));
            btn.prop('disabled', false).text('🦊 Connect Crossmark');
        }
    });

    // Connect with Xaman (from modal)
    $(document).on('click', '#connect-xaman-btn', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Connecting...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: { action: 'dltpays_connect_xaman', nonce: adminNonce },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    $('#xaman-qr').html(
                        '<p><strong>Scan with Xaman app:</strong></p>' +
                        '<img src="' + data.qr_png + '" style="max-width: 200px; border: 1px solid #ddd; border-radius: 8px;">' +
                        '<p style="margin-top: 10px;"><a href="' + data.deep_link + '" class="button button-primary" target="_blank">Open Xaman App</a></p>' +
                        '<p style="font-size: 12px; color: #666;">Waiting for approval...</p>'
                    ).show();
                    
                    btn.text('Waiting for Xaman...');
                    
                    var pollCount = 0;
                    var pollInterval = setInterval(function() {
                        pollCount++;
                        if (pollCount > 60) {
                            clearInterval(pollInterval);
                            btn.text('Timeout - Try Again').prop('disabled', false);
                            return;
                        }
                        
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: { action: 'dltpays_poll_xaman', nonce: adminNonce, connection_id: data.connection_id },
                            success: function(pollResponse) {
                                if (pollResponse.success && pollResponse.data.status === 'connected') {
                                    clearInterval(pollInterval);
                                    $('#xaman-qr').html('<p style="color: #28a745;"><strong>✓ Connected!</strong></p>');
                                    btn.text('Connected!');
                                    setTimeout(function() { location.reload(); }, 2000);
                                } else if (pollResponse.data && (pollResponse.data.status === 'expired' || pollResponse.data.status === 'cancelled')) {
                                    clearInterval(pollInterval);
                                    btn.text('Try Again').prop('disabled', false);
                                    $('#xaman-qr').html('<p style="color: #dc3545;">Connection ' + pollResponse.data.status + '</p>');
                                }
                            }
                        });
                    }, 5000);
                } else {
                    btn.text('Failed - Try Again').prop('disabled', false);
                    alert(response.data || 'Connection failed');
                }
            },
            error: function() {
                btn.text('Failed - Try Again').prop('disabled', false);
            }
        });
    });

    // Disconnect wallet
    $(document).on('click', '#disconnect-wallet', function() {
        if (!confirm('Disconnect your wallet? You will need to reconnect to process payouts.')) {
            return;
        }
        
        var btn = $(this);
        btn.prop('disabled', true).text('Disconnecting...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: { action: 'dltpays_disconnect_xaman', nonce: adminNonce },
            success: function(response) {
                if (response.success) {
                    alert('Wallet disconnected.');
                    location.reload();
                } else {
                    alert('Failed: ' + (response.data || 'Unknown error'));
                    btn.prop('disabled', false).text('Disconnect Wallet');
                }
            },
            error: function() {
                alert('Connection error');
                btn.prop('disabled', false).text('Disconnect Wallet');
            }
        });
    });
    
    // Disconnect store
    $('#disconnect-store').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to disconnect this store? You can reconnect later by reactivating the plugin.')) {
            $('<form method="post" action="options.php">' +
                '<?php echo wp_nonce_field('dltpays_settings-options', '_wpnonce', true, false); ?>' +
                '<input type="hidden" name="option_page" value="dltpays_settings">' +
                '<input type="hidden" name="action" value="update">' +
                '<input type="hidden" name="dltpays_store_id" value="">' +
                '<input type="hidden" name="dltpays_api_secret" value="">' +
              '</form>').appendTo('body').submit();
        }
    });
    
    // Permanently delete store
    $('#delete-store-permanent').on('click', function(e) {
        e.preventDefault();
        if (confirm('⚠️ WARNING: This will permanently delete your store, all affiliates, and all payout history. This cannot be undone.')) {
            var confirmation = prompt('Type "PERMANENTLY DELETE" to confirm:');
            if (confirmation === 'PERMANENTLY DELETE') {
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: { action: 'dltpays_delete_store', nonce: adminNonce, confirm: 'PERMANENTLY DELETE' },
                    success: function(response) {
                        if (response.success) {
                            alert('Store permanently deleted.');
                            location.reload();
                        } else {
                            alert('Failed: ' + (response.data || 'Unknown error'));
                        }
                    }
                });
            }
        }
    });
    
<?php endif; ?>

// =========================================================================
// XAMAN LOGIN (for new stores - no credentials yet)
// =========================================================================
$('#xaman-login-option').on('click', function() {
    $('#xaman-qr-modal').show();
    $('#xaman-qr-content').html('<p style="color: #6b7280;">Connecting...</p>');
    
    $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: { action: 'dltpays_xaman_login', nonce: adminNonce },
        success: function(response) {
            if (response.success) {
                var data = response.data;
                $('#xaman-qr-content').html(
                    '<img src="' + data.qr_png + '" style="max-width: 200px; border-radius: 8px; margin-bottom: 16px;">' +
                    '<p style="color: #6b7280; font-size: 13px; margin-bottom: 12px;">Scan with Xaman app to sign in</p>' +
                    '<a href="' + data.deep_link + '" target="_blank" style="display: inline-block; background: #2563eb; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-size: 14px;">Open Xaman App</a>'
                );
                pollXamanLogin(data.login_id);
            } else {
                $('#xaman-qr-content').html('<p style="color: #dc2626;">Error: ' + (response.data || 'Connection failed') + '</p>');
            }
        },
        error: function() {
            $('#xaman-qr-content').html('<p style="color: #dc2626;">Connection failed</p>');
        }
    });
});

function pollXamanLogin(loginId) {
    var pollCount = 0;
    var pollInterval = setInterval(function() {
        pollCount++;
        if (pollCount > 60) {
            clearInterval(pollInterval);
            $('#xaman-qr-content').html('<p style="color: #dc2626;">Timeout. Please try again.</p>');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: { action: 'dltpays_poll_xaman_login', nonce: adminNonce, login_id: loginId },
            success: function(response) {
                if (response.success && response.data.wallet_address) {
                    clearInterval(pollInterval);
                    $('#xaman-qr-content').html('<p style="color: #059669; font-weight: 600;">✓ Signed! Registering store...</p>');
                    
                    // Register the store with the wallet address
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'dltpays_register_store',
                            nonce: adminNonce,
                            wallet_address: response.data.wallet_address,
                            wallet_type: 'xaman',
                            xaman_user_token: response.data.xaman_user_token || '',
                            referral_code: $('#promo-code-input').val() || ''
                        },
                        success: function(regResponse) {
                            if (regResponse.success) {
                                $('#xaman-qr-content').html('<p style="color: #059669; font-weight: 600;">✓ Store registered! Reloading...</p>');
                                setTimeout(function() { location.reload(); }, 1500);
                            } else {
                                $('#xaman-qr-content').html('<p style="color: #dc2626;">Error: ' + (regResponse.data || 'Registration failed') + '</p>');
                            }
                        },
                        error: function() {
                            $('#xaman-qr-content').html('<p style="color: #dc2626;">Registration failed</p>');
                        }
                    });
                } else if (response.data && response.data.status === 'expired') {
                    clearInterval(pollInterval);
                    $('#xaman-qr-content').html('<p style="color: #dc2626;">Request expired. Please try again.</p>');
                } else if (response.data && response.data.status === 'cancelled') {
                    clearInterval(pollInterval);
                    $('#xaman-qr-content').html('<p style="color: #dc2626;">Request cancelled. Please try again.</p>');
                }
            }
        });
    }, 3000);
}

// =========================================================================
// CROSSMARK LOGIN (for new stores)
// =========================================================================
$('#crossmark-login-option').on('click', async function() {
    var sdk = window.xrpl && window.xrpl.crossmark;
    if (!sdk) {
        if (confirm('Crossmark wallet not detected. Click OK to download.')) {
            window.open('https://crossmark.io', '_blank');
        }
        return;
    }
    
    try {
        var signIn = await sdk.methods.signInAndWait();
        if (!signIn.response || !signIn.response.data || !signIn.response.data.address) {
            throw new Error('Connection cancelled');
        }
        
        var address = signIn.response.data.address;
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'dltpays_register_store',
                nonce: adminNonce,
                wallet_address: address,
                referral_code: $('#promo-code-input').val() || ''
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Store connected!\n\nWallet: ' + address.substring(0, 8) + '...' + address.slice(-4));
                    location.reload();
                } else {
                    alert('❌ ' + (response.data || 'Registration failed'));
                }
            }
        });
    } catch (err) {
        if (!err.message.includes('cancelled')) {
            alert('❌ ' + err.message);
        }
    }
});

// =========================================================================
// PAYOUT BATCHING SETTINGS
// =========================================================================
$('#dltpays_payout_threshold, #dltpays_payout_schedule').on('change', function() {
    var threshold = $('#dltpays_payout_threshold').val();
    var schedule = $('#dltpays_payout_schedule').val();
    
    $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'dltpays_save_payout_settings',
            nonce: adminNonce,
            payout_threshold: threshold,
            payout_schedule: schedule
        },
        success: function(response) {
            if (response.success) {
                // Brief visual feedback
                var select = event.target;
                var original = select.style.borderColor;
                select.style.borderColor = '#22c55e';
                setTimeout(function() { select.style.borderColor = original; }, 1500);
            } else {
                alert('Failed to save: ' + (response.data || 'Unknown error'));
            }
        },
        error: function() {
            alert('Connection error - settings not saved');
        }
    });
});
    
    // Copy referral code
    $('#copy-referral-code').on('click', function() {
        var code = $('#store-referral-code').text().trim();
        var btn = $(this);
        
        if (code && code !== 'Loading...' && code !== 'Not available') {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(function() {
                    btn.text('✓ Copied!').css('background', '#22c55e');
                    setTimeout(function() { btn.text('📋 Copy Code').css('background', '#4ade80'); }, 2000);
                });
            }
        }
    });
    
    // Save rates as JSON
    $('form').on('submit', function() {
        const rates = [];
        for (let i = 1; i <= 5; i++) {
            rates.push(parseFloat($('#rate_l' + i).val()) || 0);
        }
        if (!$('#dltpays_commission_rates').length) {
            $(this).append('<input type="hidden" name="dltpays_commission_rates" id="dltpays_commission_rates">');
        }
        $('#dltpays_commission_rates').val(JSON.stringify(rates));
    });
});
</script>
