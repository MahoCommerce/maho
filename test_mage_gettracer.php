<?php

/**
 * Debug Mage::getTracer() specifically
 */

require 'vendor/autoload.php';

echo "=== Debugging Mage::getTracer() ===\n\n";

Mage::app('admin');

// Force re-initialization by resetting static cache
$reflection = new ReflectionClass('Mage');
$property = $reflection->getProperty('_tracer');
$property->setAccessible(true);
$property->setValue(null, null);

echo "Testing Mage::getTracer() with fresh state...\n\n";

// Try to get tracer
$tracer = Mage::getTracer();

echo "Result: " . ($tracer ? "SUCCESS" : "FAILED") . "\n";

if ($tracer) {
    echo "Class: " . get_class($tracer) . "\n";
    echo "isEnabled(): " . ($tracer->isEnabled() ? 'YES' : 'NO') . "\n";

    echo "\n--- Creating test span ---\n";
    $span = Mage::startSpan('grafana.test', [
        'test.attribute' => 'test_value',
        'timestamp' => time()
    ]);

    if ($span) {
        echo "✅ Span created: " . get_class($span) . "\n";
        $span->setAttribute('custom.key', 'custom_value');
        $span->setStatus('ok');
        $span->end();

        echo "\n--- Flushing to Grafana Cloud ---\n";
        $tracer->flush();
        echo "✅ Data sent!\n";

        echo "\n🎉 SUCCESS! Check Grafana Cloud in 10-30 seconds.\n";
    }
} else {
    echo "\n--- Checking what went wrong ---\n";

    // Check config
    $config = Mage::getConfig();
    if (!$config) {
        echo "❌ Config not loaded\n";
    } else {
        echo "✅ Config loaded\n";
    }

    // Check module status
    $modules = $config->getNode('modules');
    if ($modules) {
        $moduleConfig = $modules->Maho_OpenTelemetry;
        if ($moduleConfig) {
            echo "✅ Module config found\n";
            echo "  Active: " . ((string) $moduleConfig->active) . "\n";
        } else {
            echo "❌ Module config not found\n";
        }
    }

    // Try manual initialization
    echo "\n--- Trying manual initialization ---\n";
    $tracer = Mage::getSingleton('opentelemetry/tracer');
    echo "Tracer class: " . get_class($tracer) . "\n";
    echo "Is Maho_OpenTelemetry_Model_Tracer: " . ($tracer instanceof Maho_OpenTelemetry_Model_Tracer ? 'YES' : 'NO') . "\n";

    $result = $tracer->initialize();
    echo "initialize() returned: " . ($result ? get_class($result) : 'false') . "\n";

    if ($result) {
        echo "✅ Manual initialization worked!\n";
        echo "   The issue is in Mage::getTracer() logic.\n";
    }
}
