<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Http {
    final class Client
    {
        public static \Symfony\Contracts\HttpClient\HttpClientInterface $client;

        public static function create(array $options = []): \Symfony\Contracts\HttpClient\HttpClientInterface
        {
            return self::$client;
        }
    }
}

namespace {
    $root = $argv[1];
    $vendor = $argv[2];
    spl_autoload_register(static function (string $class) use ($vendor): void {
        foreach ([
            'Symfony\\Component\\HttpClient\\' => '/symfony/http-client/',
            'Symfony\\Contracts\\HttpClient\\' => '/symfony/http-client-contracts/',
            'Symfony\\Contracts\\Service\\' => '/symfony/service-contracts/',
        ] as $prefix => $directory) {
            if (str_starts_with($class, $prefix)) {
                $path = $vendor . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($path)) {
                    require $path;
                }
                return;
            }
        }
    });
    foreach ([
        'lib/Maho/DataObject.php',
        'app/code/core/Mage/Core/Model/Locale.php',
        'app/code/core/Mage/Core/Helper/Abstract.php',
        'app/code/core/Mage/Core/Helper/Data.php',
        'app/code/core/Mage/Usa/Model/Shipping/Carrier/Usps/OAuthClient.php',
        'app/code/core/Mage/Usa/Model/Shipping/Carrier/Usps/RestClient.php',
    ] as $path) {
        require $root . '/' . $path;
    }

    final class Mage
    {
        public static function helper(string $alias): Mage_Core_Helper_Data
        {
            if ($alias !== 'core') {
                throw new LogicException('Unexpected helper');
            }
            return new Mage_Core_Helper_Data();
        }
    }

    final class OwnedUspsOAuthFixture extends Mage_Usa_Model_Shipping_Carrier_Usps_OAuthClient
    {
        public function __construct() {}

        #[\Override]
        public function getAccessToken(): string
        {
            return 'owned-synthetic-token';
        }
    }

    $result = [];
    foreach ([
        ['json-success', 200, '{"trackingEvents":[]}', 'application/json'],
        ['binary-success', 201, 'OWNED-LABEL', 'application/pdf'],
        ['success-with-error', 200, '{"error":{"message":"Fixture address invalid"}}', 'application/json'],
        ['http-simple-error', 400, '{"error":{"message":"Fixture address invalid"}}', 'application/json'],
        ['http-nested-error', 422, '{"error":{"errors":[{"message":"Fixture weight invalid"},{"detail":"Fixture size invalid"}]}}', 'application/json'],
        ['http-malformed-error', 500, '<html>Fixture unavailable</html>', 'text/html'],
    ] as [$name, $status, $body, $type]) {
        $response = new \Symfony\Component\HttpClient\Response\MockResponse($body, [
            'http_code' => $status,
            'response_headers' => ['content-type: ' . $type],
        ]);
        $http = new \Symfony\Component\HttpClient\MockHttpClient($response);
        \Maho\Http\Client::$client = $http;
        $client = new Mage_Usa_Model_Shipping_Carrier_Usps_RestClient(new OwnedUspsOAuthFixture());
        $row = ['response' => null, 'error' => null, 'previous' => null];
        try {
            $row['response'] = $client->getTracking('OWNED-HTTP');
        } catch (Exception $exception) {
            $row['error'] = $exception->getMessage();
            $row['previous'] = $exception->getPrevious()?->getMessage();
        }
        $row['requests'] = $http->getRequestsCount();
        $result[$name] = $row;
    }
    echo json_encode($result, JSON_THROW_ON_ERROR);
}
