<?php

/**
 * Test tracer initialization step by step
 */

require 'vendor/autoload.php';

echo "=== Testing Tracer Initialization ===\n\n";

Mage::app('admin');

echo "Step 1: Get helper\n";
try {
    $helper = Mage::helper('opentelemetry');
    echo "✅ Helper loaded: " . get_class($helper) . "\n";
} catch (Exception $e) {
    echo "❌ Failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nStep 2: Check helper values\n";
echo "  isEnabled(): " . ($helper->isEnabled() ? 'YES' : 'NO') . "\n";
echo "  getEndpoint(): '" . $helper->getEndpoint() . "'\n";

echo "\nStep 3: Get tracer singleton\n";
try {
    $tracer = Mage::getSingleton('opentelemetry/tracer');
    echo "✅ Singleton loaded: " . get_class($tracer) . "\n";
} catch (Exception $e) {
    echo "❌ Failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nStep 4: Initialize tracer\n";
try {
    $initialized = $tracer->initialize();
    if ($initialized) {
        echo "✅ Tracer initialized successfully\n";
        echo "  isEnabled(): " . ($tracer->isEnabled() ? 'YES' : 'NO') . "\n";
    } else {
        echo "❌ initialize() returned false\n";
        echo "  Checking why...\n";

        // Re-check conditions
        if (!$helper->isEnabled()) {
            echo "  ❌ Helper says not enabled\n";
        }

        $endpoint = $helper->getEndpoint();
        if (empty($endpoint)) {
            echo "  ❌ Endpoint is empty\n";
        } else {
            echo "  ✅ Endpoint is set: $endpoint\n";
        }

        echo "\n  This means the tracer initialized but returned false.\n";
        echo "  In stub mode, this is actually OK - it just means no real SDK.\n";
    }
} catch (Exception $e) {
    echo "❌ Exception during initialize(): " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nStep 5: Test via Mage::getTracer()\n";
$tracer2 = Mage::getTracer();
if ($tracer2) {
    echo "✅ Mage::getTracer() returned tracer\n";
    echo "  Class: " . get_class($tracer2) . "\n";
    echo "  isEnabled(): " . ($tracer2->isEnabled() ? 'YES' : 'NO') . "\n";
} else {
    echo "❌ Mage::getTracer() returned null/false\n";
}

echo "\nStep 6: Try creating a span\n";
$span = Mage::startSpan('test.span');
if ($span) {
    echo "✅ Created span: " . get_class($span) . "\n";
    $span->end();
} else {
    echo "❌ Failed to create span\n";
}
