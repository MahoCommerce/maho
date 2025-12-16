<?php

/**
 * Test if Maho OpenTelemetry is sending data to Grafana Cloud
 */

require 'vendor/autoload.php';

echo "=== Testing Maho OpenTelemetry → Grafana Cloud ===\n\n";

// Initialize Maho
Mage::app('admin');

// Check 1: Is module enabled?
$helper = Mage::helper('opentelemetry');
echo "1. Module Enabled: " . ($helper->isEnabled() ? '✅ YES' : '❌ NO') . "\n";

if (!$helper->isEnabled()) {
    echo "   ⚠️  OpenTelemetry is disabled. Check your config.\n";
    exit(1);
}

// Check 2: Is tracer available?
$tracer = Mage::getTracer();
echo "2. Tracer Available: " . ($tracer ? '✅ YES' : '❌ NO') . "\n";

if (!$tracer) {
    echo "   ⚠️  Tracer not initialized. Check logs.\n";
    exit(1);
}

// Check 3: Is tracer enabled?
echo "3. Tracer Enabled: " . ($tracer->isEnabled() ? '✅ YES' : '❌ NO') . "\n";

if (!$tracer->isEnabled()) {
    echo "   ⚠️  Tracer not enabled. Check endpoint configuration.\n";
    exit(1);
}

// Check 4: Show configuration
echo "\n--- Configuration ---\n";
echo "Service Name: " . $helper->getServiceName() . "\n";
echo "Endpoint: " . $helper->getEndpoint() . "\n";
echo "Sampling Rate: " . ($helper->getSamplingRate() * 100) . "%\n";

// Check 5: Create test spans
echo "\n--- Creating Test Traces ---\n";

// Root span
$rootSpan = $tracer->startRootSpan('grafana_cloud_test', [
    'test.type' => 'connectivity_check',
    'test.timestamp' => date('Y-m-d H:i:s'),
]);

echo "✓ Created root span: grafana_cloud_test\n";

// Child span 1
$dbSpan = $tracer->startSpan('test.database.query', [
    'db.system' => 'mysql',
    'db.operation' => 'SELECT',
]);
usleep(50000); // 50ms
$dbSpan->setStatus('ok');
$dbSpan->end();
echo "✓ Created database span (50ms)\n";

// Child span 2
$httpSpan = $tracer->startSpan('test.http.request', [
    'http.method' => 'GET',
    'http.url' => 'https://example.com',
]);
usleep(100000); // 100ms
$httpSpan->setStatus('ok');
$httpSpan->end();
echo "✓ Created HTTP span (100ms)\n";

// Test exception recording
$errorSpan = $tracer->startSpan('test.error', [
    'error.test' => true,
]);
try {
    throw new Exception('This is a test error for Grafana Cloud');
} catch (Exception $e) {
    $errorSpan->recordException($e);
    $errorSpan->setStatus('error', $e->getMessage());
}
$errorSpan->end();
echo "✓ Created error span with exception\n";

// End root span
$rootSpan->setStatus('ok');
$rootSpan->end();

// Flush all spans
echo "\n--- Sending to Grafana Cloud ---\n";
$tracer->flush();
echo "✓ Flushed telemetry data\n";

echo "\n=== Test Complete! ===\n\n";
echo "Next steps:\n";
echo "1. Wait 10-30 seconds for data to appear in Grafana Cloud\n";
echo "2. Go to: https://YOUR_INSTANCE.grafana.net/\n";
echo "3. Click 'Explore' (compass icon on left)\n";
echo "4. Select 'Tempo' from data source dropdown\n";
echo "5. Click 'Search' tab\n";
echo "6. Look for service: '" . $helper->getServiceName() . "'\n";
echo "7. You should see 'grafana_cloud_test' trace with 4 spans!\n";
echo "\n";
echo "If you don't see traces:\n";
echo "  - Check endpoint URL is correct\n";
echo "  - Check Authorization header is base64 encoded correctly\n";
echo "  - Check firewall/network allows HTTPS to Grafana Cloud\n";
echo "  - Check var/log/system.log for errors\n";
