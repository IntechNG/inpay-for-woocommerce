#!/bin/bash

# Webhook Diagnostic Script for iNPAY WooCommerce Plugin
# This script tests the webhook endpoint accessibility

echo "======================================"
echo "iNPAY Webhook Diagnostic Tool"
echo "======================================"
echo ""

# Get the site URL from user
read -p "Enter your WordPress site URL (e.g., https://example.com): " SITE_URL

# Remove trailing slash
SITE_URL=${SITE_URL%/}

echo ""
echo "Testing webhook endpoint: ${SITE_URL}/?wc-api=inpay_checkout_webhook"
echo ""

# Test 1: Basic GET request (should fail but proves endpoint exists)
echo "Test 1: Basic GET Request"
echo "-------------------------"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}\n" "${SITE_URL}/?wc-api=inpay_checkout_webhook")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | grep -v "HTTP_CODE")

echo "HTTP Status: $HTTP_CODE"
echo "Response Body: $BODY"

if [ "$HTTP_CODE" == "404" ]; then
    echo "❌ FAILED: Endpoint returns 404 - Webhook URL is not accessible"
    echo "   This usually means WordPress rewrite rules are not set up correctly."
    echo ""
    echo "   Solutions:"
    echo "   1. Go to WordPress Admin → Settings → Permalinks → Click 'Save Changes'"
    echo "   2. Check if .htaccess file exists and is writable"
    echo "   3. Verify mod_rewrite is enabled on Apache"
elif [ "$HTTP_CODE" == "401" ] || [ "$HTTP_CODE" == "400" ]; then
    echo "✅ PASSED: Endpoint is accessible (rejected due to missing/invalid auth)"
    echo "   This is expected - the endpoint exists and is working."
elif [ "$HTTP_CODE" == "200" ]; then
    echo "⚠️  WARNING: Endpoint returns 200 on GET request"
    echo "   This might indicate the endpoint is not properly secured."
else
    echo "⚠️  UNKNOWN: Unexpected HTTP status code"
fi

echo ""
echo ""

# Test 2: POST request with headers (simulating webhook)
echo "Test 2: POST Request with Webhook Headers"
echo "------------------------------------------"
TIMESTAMP=$(date +%s)000
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}\n" -X POST "${SITE_URL}/?wc-api=inpay_checkout_webhook" \
  -H "X-Webhook-Signature: sha256=invalid_signature_for_testing" \
  -H "X-Webhook-Timestamp: ${TIMESTAMP}" \
  -H "X-Webhook-Event: payment.virtual_payid.completed" \
  -H "Content-Type: application/json" \
  -d '{"event":"payment.virtual_payid.completed","data":{"reference":"test_reference"}}')

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | grep -v "HTTP_CODE")

echo "HTTP Status: $HTTP_CODE"
echo "Response Body: $BODY"

if [ "$HTTP_CODE" == "404" ]; then
    echo "❌ FAILED: Endpoint returns 404"
elif [ "$HTTP_CODE" == "401" ]; then
    echo "✅ PASSED: Endpoint rejected request (signature validation working)"
    echo "   This is the expected behavior - webhook is accessible and validating signatures."
elif [ "$HTTP_CODE" == "400" ]; then
    echo "✅ PASSED: Endpoint rejected request (validation working)"
    echo "   The endpoint is accessible and performing validation."
elif [ "$HTTP_CODE" == "200" ]; then
    echo "⚠️  WARNING: Endpoint accepted invalid signature"
    echo "   This might be a security concern."
else
    echo "⚠️  UNKNOWN: Unexpected HTTP status code"
fi

echo ""
echo ""

# Test 3: Check for redirects
echo "Test 3: Checking for Redirects"
echo "-------------------------------"
REDIRECT=$(curl -s -I "${SITE_URL}/?wc-api=inpay_checkout_webhook" | grep -i "location:")

if [ -z "$REDIRECT" ]; then
    echo "✅ No redirects detected"
else
    echo "⚠️  WARNING: Redirect detected"
    echo "   $REDIRECT"
    echo "   The endpoint might be redirecting to another URL."
fi

echo ""
echo ""

# Test 4: Alternative REST API endpoint check
echo "Test 4: Checking Alternative REST API Endpoint"
echo "-----------------------------------------------"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}\n" "${SITE_URL}/wp-json/")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)

if [ "$HTTP_CODE" == "200" ]; then
    echo "✅ WordPress REST API is accessible"
    echo "   You could use a REST API endpoint as an alternative to wc-api"
else
    echo "⚠️  WordPress REST API might not be accessible"
    echo "   HTTP Status: $HTTP_CODE"
fi

echo ""
echo ""

# Summary
echo "======================================"
echo "Summary & Recommendations"
echo "======================================"
echo ""

if [ "$HTTP_CODE" == "404" ]; then
    echo "🔴 CRITICAL ISSUE DETECTED"
    echo ""
    echo "Your webhook endpoint is not accessible. This will prevent iNPAY from"
    echo "sending payment notifications to your site."
    echo ""
    echo "IMMEDIATE ACTIONS REQUIRED:"
    echo "1. Flush WordPress permalinks:"
    echo "   - Go to WordPress Admin → Settings → Permalinks"
    echo "   - Click 'Save Changes' (don't change anything)"
    echo ""
    echo "2. Check .htaccess file:"
    echo "   - Ensure it exists in your WordPress root directory"
    echo "   - Ensure it's writable by WordPress"
    echo ""
    echo "3. Verify server configuration:"
    echo "   - Apache: Ensure mod_rewrite is enabled"
    echo "   - Nginx: Check rewrite rules in server config"
    echo ""
    echo "4. Check for conflicting plugins:"
    echo "   - Temporarily disable security plugins"
    echo "   - Temporarily disable caching plugins"
    echo ""
else
    echo "✅ WEBHOOK ENDPOINT IS ACCESSIBLE"
    echo ""
    echo "Your webhook endpoint appears to be working correctly."
    echo "The endpoint is accessible and performing validation as expected."
    echo ""
    echo "NEXT STEPS:"
    echo "1. Configure the webhook URL in your iNPAY Dashboard:"
    echo "   URL: ${SITE_URL}/?wc-api=inpay_checkout_webhook"
    echo ""
    echo "2. Enable debug logging in WooCommerce:"
    echo "   - Go to WooCommerce → Settings → iNPAY Checkout"
    echo "   - Enable 'Debug logging'"
    echo "   - Check logs at WooCommerce → Status → Logs"
    echo ""
    echo "3. Test a real payment to verify webhook delivery"
fi

echo ""
echo "======================================"
echo "Diagnostic Complete"
echo "======================================"
