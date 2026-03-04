<?php

/**
 * Maho ShippingBridge — Test API Endpoint
 *
 * Usage:
 *   php -S localhost:8888 public/shipping_bridge_test.php
 *
 * Then in admin, set API Endpoint URL to: http://localhost:8888
 *
 * Incoming requests are logged to var/log/shipping_bridge_test_requests.log
 */

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$logFile = __DIR__ . '/../var/log/shipping_bridge_test_requests.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . "\n" . json_encode($input, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

echo json_encode([
    'methods' => [
        [
            'code'        => 'standard',
            'title'       => 'Standard Shipping',
            'price'       => 5.99,
            'cost'        => 4.50,
            'description' => '3-5 business days',
            'logo'        => 'https://placehold.co/24x24/4a90d9/white?text=S',
        ],
        [
            'code'        => 'express',
            'title'       => 'Express Shipping',
            'price'       => 12.99,
            'cost'        => 10.00,
            'description' => '1-2 business days',
            'logo'        => 'https://placehold.co/48x24/orange/white?text=Express',
        ],
        [
            'code'        => 'overnight',
            'title'       => 'Overnight Shipping',
            'price'       => 24.99,
            'cost'        => 20.00,
            'description' => 'Next business day',
            'logo'        => 'https://placehold.co/120x40/e74c3c/white?text=Overnight',
        ],
        [
            'code'        => 'economy',
            'title'       => 'Economy Shipping',
            'price'       => 2.99,
            'cost'        => 2.00,
            'description' => '5-10 business days',
            'logo'        => 'https://placehold.co/200x80/27ae60/white?text=Economy',
        ],
        [
            'code'        => 'freight',
            'title'       => 'Freight Shipping',
            'price'       => 49.99,
            'cost'        => 40.00,
            'description' => '7-14 business days',
            'logo'        => 'https://placehold.co/300x100/8e44ad/white?text=Freight+Shipping',
        ],
    ],
]);
