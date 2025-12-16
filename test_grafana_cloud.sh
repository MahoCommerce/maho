#!/bin/bash

echo "=== Testing Maho OpenTelemetry with Grafana Cloud ==="
echo ""
echo "⚠️  IMPORTANT: Replace the values below with YOUR Grafana Cloud credentials"
echo "    Get them from: Grafana Cloud Portal → OpenTelemetry → Configure"
echo ""

# TODO: Replace these with YOUR actual Grafana Cloud credentials
GRAFANA_ENDPOINT="https://otlp-gateway-prod-XX.grafana.net/otlp"
GRAFANA_INSTANCE_ID="YOUR_INSTANCE_ID"
GRAFANA_API_TOKEN="YOUR_API_TOKEN"

# Create base64 encoded credentials
AUTH_ENCODED=$(echo -n "${GRAFANA_INSTANCE_ID}:${GRAFANA_API_TOKEN}" | base64)

# Set environment variables
export OTEL_SDK_DISABLED=false
export OTEL_SERVICE_NAME=maho-test
export OTEL_EXPORTER_OTLP_ENDPOINT="${GRAFANA_ENDPOINT}"
export OTEL_EXPORTER_OTLP_HEADERS="Authorization=Basic ${AUTH_ENCODED}"
export OTEL_TRACES_SAMPLER_ARG=1.0

echo "Configuration:"
echo "  Endpoint: ${GRAFANA_ENDPOINT}"
echo "  Service Name: maho-test"
echo "  Sampling: 100%"
echo ""
echo "Running Maho with Grafana Cloud integration..."
echo ""

# Example: Run a simple PHP script with Maho
php -r "
require 'vendor/autoload.php';
Mage::app('admin');

echo 'Testing OpenTelemetry...\n';

// Create a test span
\$span = Mage::startSpan('test.operation', [
    'test.type' => 'grafana_cloud_test',
    'test.timestamp' => time()
]);

// Simulate some work
usleep(100000); // 100ms

\$span?->setStatus('ok');
\$span?->end();

// Flush telemetry
Mage::getTracer()?->flush();

echo 'Test complete! Check Grafana Cloud for traces.\n';
"

echo ""
echo "✅ Test complete!"
echo ""
echo "Next steps:"
echo "1. Go to Grafana Cloud → Explore"
echo "2. Select 'Tempo' data source"
echo "3. Search for service: 'maho-test'"
echo "4. You should see the test trace!"
