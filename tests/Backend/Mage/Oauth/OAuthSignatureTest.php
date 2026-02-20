<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('OAuth 1.0 Signature Validation', function () {
    it('validates HMAC-SHA1 signature using RFC 5849 official test vector', function () {
        // Official OAuth 1.0 RFC 5849 Appendix A test vector
        // This proves our signature implementation is correct
        $params = [
            'oauth_consumer_key' => 'dpf43f3p2l4k3l03',
            'oauth_token' => 'nnch734d00sl2jdk',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => '1191242096',
            'oauth_nonce' => 'kllo9940pd9333jh',
            'oauth_version' => '1.0',
            'file' => 'vacation.jpg',
            'size' => 'original',
        ];

        $consumerSecret = 'kd94hf93k423kf44';
        $tokenSecret = 'pfkkdhi9sl3r4s00';

        $signature = calculateHmacSignature(
            $params,
            'GET',
            'http://photos.example.net/photos',
            $consumerSecret,
            $tokenSecret,
        );

        // Expected signature from RFC 5849 Appendix A.1
        expect($signature)->toBe('tR3+Ty81lMeYAr/Fid0kMTYa/WM=');
    });

    it('encodes URLs per RFC 3986 specification', function () {
        // RFC 3986 requires specific encoding rules for OAuth
        expect(urlEncodeRfc3986('hello world'))->toBe('hello%20world');
        expect(urlEncodeRfc3986('test@example.com'))->toBe('test%40example.com');
        expect(urlEncodeRfc3986('~test'))->toBe('~test'); // ~ should NOT be encoded
        expect(urlEncodeRfc3986('test/path'))->toBe('test%2Fpath');
        expect(urlEncodeRfc3986('a+b'))->toBe('a%2Bb');
    });

    it('normalizes URLs correctly for signature base string', function () {
        // Default ports should be removed
        expect(normalizeUrl('http://example.com:80/path'))->toBe('http://example.com/path');
        expect(normalizeUrl('https://example.com:443/path'))->toBe('https://example.com/path');

        // Non-default ports should be kept
        expect(normalizeUrl('http://example.com:8080/path'))->toBe('http://example.com:8080/path');

        // Scheme and host should be lowercased
        expect(normalizeUrl('HTTP://EXAMPLE.COM/path'))->toBe('http://example.com/path');

        // Query and fragment should be removed
        expect(normalizeUrl('http://example.com/path?query=1#fragment'))->toBe('http://example.com/path');
    });

    it('sorts parameters correctly for signature base string', function () {
        $params = [
            'z' => 'last',
            'a' => 'first',
            'm' => 'middle',
        ];

        $sorted = toByteValueOrderedQueryString($params);
        expect($sorted)->toBe('a=first&m=middle&z=last');
    });

    it('handles array parameters in signature base string', function () {
        $params = [
            'color' => ['blue', 'red', 'green'],
        ];

        $sorted = toByteValueOrderedQueryString($params);
        // Array values should be naturally sorted
        expect($sorted)->toBe('color=blue&color=green&color=red');
    });

    it('removes oauth_signature parameter from base string', function () {
        $params = [
            'oauth_consumer_key' => 'test',
            'oauth_signature' => 'should_be_removed',
            'other_param' => 'should_stay',
        ];

        $baseString = getBaseSignatureString($params, 'GET', 'http://example.com/api');

        // oauth_signature should be removed
        expect($baseString)->not->toContain('oauth_signature%3Dshould_be_removed');
        // But oauth_signature_method would be ok (different parameter)
        expect($baseString)->toContain('other_param');
    });
});

// Helper functions for OAuth signature calculation
function calculateHmacSignature(
    array $params,
    string $method,
    string $url,
    string $consumerSecret,
    ?string $tokenSecret,
): string {
    $normalizedUrl = normalizeUrl($url);
    $baseString = getBaseSignatureString($params, $method, $normalizedUrl);
    $key = urlEncodeRfc3986($consumerSecret) . '&' . ($tokenSecret ? urlEncodeRfc3986($tokenSecret) : '');

    return base64_encode(hash_hmac('sha1', $baseString, $key, true));
}

function getBaseSignatureString(array $params, string $method, string $url): string
{
    // Remove oauth_signature before encoding
    unset($params['oauth_signature']);

    // Encode parameters
    $encodedParams = [];
    foreach ($params as $key => $value) {
        $encodedParams[urlEncodeRfc3986($key)] = urlEncodeRfc3986($value);
    }

    // Build base string
    $baseString = strtoupper($method) . '&'
        . urlEncodeRfc3986($url) . '&'
        . urlEncodeRfc3986(toByteValueOrderedQueryString($encodedParams));

    return $baseString;
}

function normalizeUrl(string $url): string
{
    $parts = parse_url($url);

    $scheme = strtolower($parts['scheme'] ?? 'http');
    $host = strtolower($parts['host'] ?? '');
    $port = $parts['port'] ?? null;
    $path = $parts['path'] ?? '/';

    // Remove default ports
    if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
        $port = null;
    }

    $normalized = $scheme . '://' . $host;
    if ($port !== null) {
        $normalized .= ':' . $port;
    }
    $normalized .= $path;

    return $normalized;
}

function toByteValueOrderedQueryString(array $params): string
{
    $pairs = [];

    // Sort by key using natural comparison
    uksort($params, 'strnatcmp');

    foreach ($params as $key => $value) {
        if (is_array($value)) {
            natsort($value);
            foreach ($value as $duplicateValue) {
                $pairs[] = $key . '=' . $duplicateValue;
            }
        } else {
            $pairs[] = $key . '=' . $value;
        }
    }

    return implode('&', $pairs);
}

function urlEncodeRfc3986(string $value): string
{
    $encoded = rawurlencode($value);
    // RFC 3986 specifies that ~ should not be encoded
    return str_replace('%7E', '~', $encoded);
}
