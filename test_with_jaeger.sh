#!/bin/bash

echo "=== OpenTelemetry + Jaeger Test ==="
echo ""
echo "Starting Jaeger (if not already running)..."

# Check if Jaeger is already running
if docker ps | grep -q jaeger; then
    echo "Jaeger is already running ✓"
else
    echo "Starting Jaeger container..."
    docker run -d --name jaeger \
      -e COLLECTOR_OTLP_ENABLED=true \
      -p 16686:16686 \
      -p 4317:4317 \
      -p 4318:4318 \
      jaegertracing/all-in-one:latest

    echo "Waiting for Jaeger to start..."
    sleep 3
fi

echo ""
echo "Jaeger UI: http://localhost:16686"
echo ""
echo "Running Maho test with OpenTelemetry..."
echo ""

# Run the test with Jaeger endpoint
OTEL_SDK_DISABLED=false \
OTEL_SERVICE_NAME=maho-local-test \
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318 \
OTEL_TRACES_SAMPLER_ARG=1.0 \
php test_opentelemetry.php

echo ""
echo "=== Test Complete ==="
echo ""
echo "Open Jaeger UI to view traces:"
echo "  http://localhost:16686"
echo ""
echo "Select 'maho-local-test' from the Service dropdown and click 'Find Traces'"
echo ""
echo "To stop Jaeger:"
echo "  docker stop jaeger"
echo "  docker rm jaeger"
