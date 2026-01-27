<?php
/**
 * Webhook Diagnostic Tool
 * 
 * This file can be placed in your WordPress root directory and accessed directly
 * to test the webhook endpoint without requiring authentication.
 * 
 * Usage: Upload this file to your WordPress root and visit:
 * https://yoursite.com/webhook-diagnostic.php
 * 
 * IMPORTANT: Delete this file after testing for security reasons!
 */

// Prevent direct access from non-localhost in production
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) && !isset($_GET['allow'])) {
    die('Access denied. Add ?allow=1 to the URL to run this diagnostic.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>iNPAY Webhook Diagnostic Tool</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #3498db;
        }
        .success {
            border-left-color: #27ae60;
            background: #d4edda;
        }
        .warning {
            border-left-color: #f39c12;
            background: #fff3cd;
        }
        .error {
            border-left-color: #e74c3c;
            background: #f8d7da;
        }
        .code {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pass {
            background: #27ae60;
            color: white;
        }
        .status-fail {
            background: #e74c3c;
            color: white;
        }
        .status-warn {
            background: #f39c12;
            color: white;
        }
        ul {
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 iNPAY Webhook Diagnostic Tool</h1>
        
        <?php
        // Load WordPress
        $wp_load_path = __DIR__ . '/wp-load.php';
        if (!file_exists($wp_load_path)) {
            echo '<div class="test-section error">';
            echo '<h3>❌ WordPress Not Found</h3>';
            echo '<p>This file must be placed in your WordPress root directory.</p>';
            echo '</div>';
            echo '</div></body></html>';
            exit;
        }
        
        require_once($wp_load_path);
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            echo '<div class="test-section error">';
            echo '<h3>❌ WooCommerce Not Active</h3>';
            echo '<p>WooCommerce must be installed and activated.</p>';
            echo '</div>';
            echo '</div></body></html>';
            exit;
        }
        
        // Check if iNPAY plugin is active
        if (!class_exists('WC_Gateway_Inpay_Checkout')) {
            echo '<div class="test-section error">';
            echo '<h3>❌ iNPAY Plugin Not Active</h3>';
            echo '<p>The iNPAY Checkout for WooCommerce plugin must be activated.</p>';
            echo '</div>';
            echo '</div></body></html>';
            exit;
        }
        
        // Get the webhook URL
        $webhook_url = WC()->api_request_url('inpay_checkout_webhook');
        $site_url = get_site_url();
        
        echo '<div class="test-section">';
        echo '<h3>📍 Webhook URL</h3>';
        echo '<div class="code">' . esc_html($webhook_url) . '</div>';
        echo '</div>';
        
        // Test 1: Check if the action is registered
        echo '<div class="test-section">';
        echo '<h3>Test 1: Action Hook Registration</h3>';
        $has_action = has_action('woocommerce_api_inpay_checkout_webhook');
        if ($has_action) {
            echo '<span class="status-badge status-pass">✓ PASS</span>';
            echo '<p>The webhook action hook is properly registered.</p>';
        } else {
            echo '<span class="status-badge status-fail">✗ FAIL</span>';
            echo '<p>The webhook action hook is NOT registered. The plugin may not be properly initialized.</p>';
        }
        echo '</div>';
        
        // Test 2: Check rewrite rules
        echo '<div class="test-section">';
        echo '<h3>Test 2: Rewrite Rules</h3>';
        global $wp_rewrite;
        $permalink_structure = get_option('permalink_structure');
        
        if (empty($permalink_structure)) {
            echo '<span class="status-badge status-pass">✓ PASS</span>';
            echo '<p>Permalinks are set to "Plain" - the ?wc-api= parameter should work directly.</p>';
        } else {
            echo '<span class="status-badge status-warn">⚠ CHECK</span>';
            echo '<p>Pretty permalinks are enabled: <code>' . esc_html($permalink_structure) . '</code></p>';
            echo '<p>The webhook URL relies on WordPress rewrite rules. If the endpoint is not accessible, try flushing permalinks.</p>';
        }
        echo '</div>';
        
        // Test 3: Check .htaccess (for Apache)
        echo '<div class="test-section">';
        echo '<h3>Test 3: Server Configuration</h3>';
        $htaccess_path = ABSPATH . '.htaccess';
        
        if (file_exists($htaccess_path)) {
            $htaccess_writable = is_writable($htaccess_path);
            $htaccess_content = file_get_contents($htaccess_path);
            $has_rewrite = strpos($htaccess_content, 'mod_rewrite') !== false;
            
            if ($htaccess_writable && $has_rewrite) {
                echo '<span class="status-badge status-pass">✓ PASS</span>';
                echo '<p>.htaccess file exists, is writable, and contains rewrite rules.</p>';
            } elseif (!$htaccess_writable) {
                echo '<span class="status-badge status-warn">⚠ WARNING</span>';
                echo '<p>.htaccess file exists but is not writable. WordPress cannot update rewrite rules automatically.</p>';
            } else {
                echo '<span class="status-badge status-warn">⚠ WARNING</span>';
                echo '<p>.htaccess file exists but may not have proper rewrite rules.</p>';
            }
        } else {
            echo '<span class="status-badge status-warn">⚠ WARNING</span>';
            echo '<p>.htaccess file not found. This might be normal if you\'re using Nginx or another web server.</p>';
        }
        echo '</div>';
        
        // Test 4: Check gateway settings
        echo '<div class="test-section">';
        echo '<h3>Test 4: Gateway Configuration</h3>';
        $gateway = new WC_Gateway_Inpay_Checkout();
        $enabled = $gateway->enabled === 'yes';
        $has_public_key = !empty($gateway->public_key);
        $has_secret_key = !empty($gateway->get_option('secret_key'));
        
        if ($enabled && $has_public_key && $has_secret_key) {
            echo '<span class="status-badge status-pass">✓ PASS</span>';
            echo '<p>Gateway is enabled and API keys are configured.</p>';
        } else {
            echo '<span class="status-badge status-warn">⚠ WARNING</span>';
            echo '<ul>';
            echo '<li>Gateway enabled: ' . ($enabled ? 'Yes' : 'No') . '</li>';
            echo '<li>Public key configured: ' . ($has_public_key ? 'Yes' : 'No') . '</li>';
            echo '<li>Secret key configured: ' . ($has_secret_key ? 'Yes' : 'No') . '</li>';
            echo '</ul>';
        }
        echo '</div>';
        
        // Test 5: Simulate webhook request
        echo '<div class="test-section">';
        echo '<h3>Test 5: Endpoint Accessibility Test</h3>';
        echo '<p>Testing the webhook endpoint with a simulated request...</p>';
        
        $test_url = $webhook_url;
        $response = wp_remote_post($test_url, array(
            'headers' => array(
                'X-Webhook-Signature' => 'sha256=test_signature',
                'X-Webhook-Timestamp' => (string)(time() * 1000),
                'X-Webhook-Event' => 'payment.virtual_payid.completed',
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'event' => 'payment.virtual_payid.completed',
                'data' => array('reference' => 'test_reference')
            )),
            'timeout' => 10,
        ));
        
        if (is_wp_error($response)) {
            echo '<span class="status-badge status-fail">✗ FAIL</span>';
            echo '<p>Error: ' . esc_html($response->get_error_message()) . '</p>';
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code == 404) {
                echo '<span class="status-badge status-fail">✗ FAIL</span>';
                echo '<p>HTTP 404: Endpoint not found. The webhook URL is not accessible.</p>';
                echo '<p><strong>Action Required:</strong> Flush permalinks by going to Settings → Permalinks → Save Changes</p>';
            } elseif ($status_code == 401 || $status_code == 400) {
                echo '<span class="status-badge status-pass">✓ PASS</span>';
                echo '<p>HTTP ' . $status_code . ': Endpoint is accessible and rejecting invalid requests (expected behavior).</p>';
                echo '<p>Response: ' . esc_html($body) . '</p>';
            } else {
                echo '<span class="status-badge status-warn">⚠ CHECK</span>';
                echo '<p>HTTP ' . $status_code . ': Unexpected response.</p>';
                echo '<p>Response: ' . esc_html(substr($body, 0, 200)) . '</p>';
            }
        }
        echo '</div>';
        
        // Recommendations
        echo '<div class="test-section">';
        echo '<h3>📋 Recommendations</h3>';
        echo '<ol>';
        echo '<li><strong>Copy the webhook URL above</strong> and configure it in your iNPAY Dashboard under Settings → Webhooks</li>';
        echo '<li><strong>Enable debug logging</strong> in WooCommerce → Settings → iNPAY Checkout to track webhook requests</li>';
        echo '<li><strong>Test with a real payment</strong> to verify the webhook is receiving notifications</li>';
        echo '<li><strong>Check logs</strong> at WooCommerce → Status → Logs if issues occur</li>';
        echo '<li><strong>Delete this diagnostic file</strong> after testing for security reasons</li>';
        echo '</ol>';
        echo '</div>';
        
        // cURL test command
        echo '<div class="test-section">';
        echo '<h3>🔧 Manual Testing Command</h3>';
        echo '<p>You can also test the webhook manually using this cURL command:</p>';
        echo '<div class="code">';
        echo 'curl -X POST "' . esc_html($webhook_url) . '" \\<br>';
        echo '  -H "X-Webhook-Signature: sha256=test" \\<br>';
        echo '  -H "X-Webhook-Timestamp: ' . (time() * 1000) . '" \\<br>';
        echo '  -H "X-Webhook-Event: payment.virtual_payid.completed" \\<br>';
        echo '  -H "Content-Type: application/json" \\<br>';
        echo '  -d \'{"event":"payment.virtual_payid.completed","data":{"reference":"test"}}\'';
        echo '</div>';
        echo '<p><small>Expected: HTTP 401 (signature mismatch) - this proves the endpoint is accessible</small></p>';
        echo '</div>';
        ?>
        
        <div class="test-section warning">
            <h3>⚠️ Security Notice</h3>
            <p><strong>IMPORTANT:</strong> Delete this file (<code>webhook-diagnostic.php</code>) after you finish testing. It exposes information about your WordPress installation.</p>
        </div>
    </div>
</body>
</html>
