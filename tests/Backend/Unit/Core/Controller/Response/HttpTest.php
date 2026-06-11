<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\Cookie;

describe('Mage_Core_Controller_Response_Http', function () {
    beforeEach(function () {
        $this->response = new Mage_Core_Controller_Response_Http();
    });

    describe('Response Body Management', function () {
        it('sets and retrieves response body', function () {
            $this->response->setBody('Test content');
            expect($this->response->getBody())->toBe('Test content');
        });

        it('appends to response body', function () {
            $this->response->setBody('Initial');
            $this->response->appendBody('Appended');
            expect($this->response->getBody())->toBe('InitialAppended');
        });

        it('prepends to response body', function () {
            $this->response->setBody('Initial');
            $this->response->prependBody('Prepended');
            expect($this->response->getBody())->toBe('PrependedInitial');
        });

        it('clears response body', function () {
            $this->response->setBody('Content');
            $this->response->clearBody();
            expect($this->response->getBody())->toBe('');
        });

        it('handles body segments', function () {
            $this->response->appendBody('Part1', 'segment1');
            $this->response->appendBody('Part2', 'segment2');

            $body = $this->response->getBody(true);
            expect($body)->toHaveKey('segment1');
            expect($body)->toHaveKey('segment2');
            expect($body['segment1'])->toBe('Part1');
            expect($body['segment2'])->toBe('Part2');
        });

        it('handles body segment output order', function () {
            $this->response->appendBody('Third', 'c');
            $this->response->appendBody('First', 'a');
            $this->response->appendBody('Second', 'b');

            $this->response->setOutputOrder(['a', 'b', 'c']);
            expect($this->response->getBody())->toBe('FirstSecondThird');
        });
    });

    describe('Header Management', function () {
        it('sets and retrieves headers', function () {
            $this->response->setHeader('X-Custom-Header', 'test-value');

            $headers = $this->response->getHeaders();
            expect($headers)->toContainEqual([
                'name' => 'X-Custom-Header',
                'value' => 'test-value',
                'replace' => true,
            ]);
        });

        it('replaces headers by default', function () {
            $this->response->setHeader('X-Header', 'value1');
            $this->response->setHeader('X-Header', 'value2');

            $headers = $this->response->getHeaders();
            $xHeaderCount = 0;
            $lastValue = '';

            foreach ($headers as $header) {
                if ($header['name'] === 'X-Header') {
                    $xHeaderCount++;
                    $lastValue = $header['value'];
                }
            }

            expect($xHeaderCount)->toBe(1);
            expect($lastValue)->toBe('value2');
        });

        it('allows multiple headers when replace is false', function () {
            $this->response->setHeader('X-Header', 'value1', false);
            $this->response->setHeader('X-Header', 'value2', false);

            $headers = $this->response->getHeaders();
            $values = [];

            foreach ($headers as $header) {
                if ($header['name'] === 'X-Header') {
                    $values[] = $header['value'];
                }
            }

            expect($values)->toContain('value1');
            expect($values)->toContain('value2');
        });

        it('clears headers', function () {
            $this->response->setHeader('X-Header', 'value');
            $this->response->clearHeaders();
            // the header bag keeps its Cache-Control and Date defaults
            expect($this->response->hasHeader('X-Header'))->toBeFalse();
        });

        it('handles raw headers', function () {
            $this->response->setRawHeader('HTTP/1.1 404 Not Found');

            $rawHeaders = $this->response->getRawHeaders();
            expect($rawHeaders)->toContain('HTTP/1.1 404 Not Found');
        });

        it('clears raw headers', function () {
            $this->response->setRawHeader('HTTP/1.1 200 OK');
            $this->response->clearRawHeaders();
            expect($this->response->getRawHeaders())->toBe([]);
        });

        it('clears all headers including raw', function () {
            $this->response->setHeader('X-Header', 'value');
            $this->response->setRawHeader('HTTP/1.1 200 OK');
            $this->response->clearAllHeaders();

            expect($this->response->hasHeader('X-Header'))->toBeFalse();
            expect($this->response->getRawHeaders())->toBe([]);
        });
    });

    describe('HTTP Status Code Management', function () {
        it('sets and retrieves HTTP response code', function () {
            $this->response->setHttpResponseCode(404);
            expect($this->response->getHttpResponseCode())->toBe(404);
        });

        it('validates HTTP response codes', function () {
            expect(function () {
                $this->response->setHttpResponseCode(999);
            })->toThrow(Exception::class);
        });

        it('allows valid HTTP response codes', function () {
            $validCodes = [200, 201, 301, 302, 400, 401, 403, 404, 500, 503];

            foreach ($validCodes as $code) {
                $this->response->setHttpResponseCode($code);
                expect($this->response->getHttpResponseCode())->toBe($code);
            }
        });
    });

    describe('Redirect Management', function () {
        it('sets redirect URL', function () {
            $this->response->setRedirect('https://example.com');

            $headers = $this->response->getHeaders();
            $locationHeader = null;

            foreach ($headers as $header) {
                if ($header['name'] === 'Location') {
                    $locationHeader = $header['value'];
                    break;
                }
            }

            expect($locationHeader)->toBe('https://example.com');
            expect($this->response->getHttpResponseCode())->toBe(302);
        });

        it('sets redirect with custom code', function () {
            $this->response->setRedirect('https://example.com', 301);
            expect($this->response->getHttpResponseCode())->toBe(301);
        });

        it('sets redirect URL without exit', function () {
            $this->response->setRedirectUrl('https://example.com');

            $headers = $this->response->getHeaders();
            $hasLocation = false;

            foreach ($headers as $header) {
                if ($header['name'] === 'Location') {
                    $hasLocation = true;
                    break;
                }
            }

            expect($hasLocation)->toBeTrue();
        });

        it('checks if response is redirect', function () {
            expect($this->response->isRedirect())->toBeFalse();

            $this->response->setRedirect('https://example.com');
            expect($this->response->isRedirect())->toBeTrue();
        });

        it('makes permanent redirects browser-cacheable', function () {
            $this->response->setRedirect('https://example.com', 301);
            expect($this->response->getSymfonyResponse()->headers->get('Cache-Control'))
                ->toBe('max-age=86400, private');
        });

        it('keeps temporary redirects uncacheable', function () {
            $this->response->setRedirect('https://example.com', 302);
            expect($this->response->getSymfonyResponse()->headers->get('Cache-Control'))
                ->toBe('no-cache, private');
        });

        it('lets an explicit Cache-Control set after a permanent redirect win', function () {
            $this->response->setRedirect('https://example.com', 301);
            $this->response->setHeader('Cache-Control', 'no-store');
            expect($this->response->getSymfonyResponse()->headers->get('Cache-Control'))
                ->toBe('no-store, private');
        });
    });

    describe('Cookie Management', function () {
        it('sets cookies with default parameters', function () {
            $this->response->setCookie('test_cookie', 'test_value');

            // Since cookies are sent via headers, check if cookie was set
            $headers = $this->response->getHeaders();
            $hasCookie = false;

            foreach ($headers as $header) {
                if ($header['name'] === 'Set-Cookie' && str_contains($header['value'], 'test_cookie')) {
                    $hasCookie = true;
                    break;
                }
            }

            expect($hasCookie)->toBeTrue();
        });

        it('sets cookies with custom parameters', function () {
            $this->response->setCookie(
                'custom_cookie',
                'value',
                3600,
                '/',
                'example.com',
                true,
                true,
            );

            $headers = $this->response->getHeaders();
            $cookieHeader = null;

            foreach ($headers as $header) {
                if ($header['name'] === 'Set-Cookie' && str_contains($header['value'], 'custom_cookie')) {
                    $cookieHeader = $header['value'];
                    break;
                }
            }

            expect($cookieHeader)->toContain('custom_cookie');
            // Symfony emits the attributes lowercase
            expect($cookieHeader)->toContain('secure');
            expect($cookieHeader)->toContain('httponly');
        });

        it('clears cookies', function () {
            $this->response->clearCookie('test_cookie');

            // Clearing a cookie sets it with past expiry
            $headers = $this->response->getHeaders();
            $hasClearCookie = false;

            foreach ($headers as $header) {
                if ($header['name'] === 'Set-Cookie' && str_contains($header['value'], 'test_cookie')) {
                    $hasClearCookie = true;
                    // Should have past expiry time
                    expect($header['value'])->toContain('expires');
                    break;
                }
            }

            expect($hasClearCookie)->toBeTrue();
        });
    });

    describe('Symfony Response Compatibility', function () {
        it('wraps Symfony Response instance', function () {
            expect($this->response)->toBeInstanceOf(Mage_Core_Controller_Response_Http::class);
        });

        it('can be created from existing Symfony Response', function () {
            $symfonyResponse = new SymfonyResponse('Test content', 201, ['X-Test' => 'value']);
            $response = new Mage_Core_Controller_Response_Http($symfonyResponse);

            expect($response->getBody())->toBe('Test content');
            expect($response->getHttpResponseCode())->toBe(201);
        });

        it('returns underlying Symfony Response', function () {
            $this->response->setBody('content');
            $this->response->setHttpResponseCode(200);

            $symfonyResponse = $this->response->getSymfonyResponse();
            expect($symfonyResponse)->toBeInstanceOf(SymfonyResponse::class);
            expect($symfonyResponse->getContent())->toBe('content');
            expect($symfonyResponse->getStatusCode())->toBe(200);
        });

        it('sends response using Symfony send method', function () {
            $this->response->setBody('Test content');
            $this->response->setHeader('X-Test', 'value');

            // We can't actually send in tests, but verify the method exists
            expect(method_exists($this->response, 'sendResponse'))->toBeTrue();
        });

        it('handles output callback for sending response', function () {
            $called = false;
            $callback = function () use (&$called) {
                $called = true;
            };

            $this->response->setOutputCallback($callback);

            // The callback should be stored
            expect($this->response->getOutputCallback())->toBe($callback);
        });
    });

    describe('Response State Checks', function () {
        it('checks if headers have been sent', function () {
            expect($this->response->canSendHeaders())->toBeTrue();
        });

        it('checks if response has been sent', function () {
            expect($this->response->isSent())->toBeFalse();
        });

        it('reports header already sent state', function () {
            // In test environment, headers aren't actually sent
            expect($this->response->headersSentThrowsException)->toBeFalse();
        });
    });

    describe('Special Response Types', function () {
        it('handles exception in response', function () {
            $exception = new Exception('Test error');
            $this->response->setException($exception);

            expect($this->response->hasExceptions())->toBeTrue();

            $exceptions = $this->response->getExceptions();
            expect($exceptions)->toContain($exception);
        });

        it('checks if response is exception', function () {
            expect($this->response->isException())->toBeFalse();

            $this->response->setException(new Exception('Error'));
            expect($this->response->isException())->toBeTrue();
        });
    });

    describe('Output Buffering', function () {
        it('handles append/prepend with output callback', function () {
            $output = '';
            $callback = function ($content) use (&$output) {
                $output = $content;
                return $content;
            };

            $this->response->setOutputCallback($callback);
            $this->response->setBody('Middle');
            $this->response->prependBody('Start');
            $this->response->appendBody('End');

            expect($this->response->getBody())->toBe('StartMiddleEnd');
        });
    });

    describe('HTTP Protocol Version', function () {
        it('upgrades to HTTP/1.1 on send via prepare()', function () {
            $this->response->sendHeaders();
            expect($this->response->getSymfonyResponse()->getProtocolVersion())->toBe('1.1');
        });

        it('upgrades an injected Symfony response on send', function () {
            $response = new Mage_Core_Controller_Response_Http(new SymfonyResponse('content'));
            $response->sendHeaders();
            expect($response->getSymfonyResponse()->getProtocolVersion())->toBe('1.1');
        });
    });

    describe('Default Cache-Control', function () {
        it('defaults to no-cache, private when no caching headers are set', function () {
            expect($this->response->getSymfonyResponse()->headers->get('Cache-Control'))
                ->toBe('no-cache, private');
        });

        it('switches to private, must-revalidate when Last-Modified is set', function () {
            $this->response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
            expect($this->response->getSymfonyResponse()->headers->get('Cache-Control'))
                ->toBe('private, must-revalidate');
        });

        it('switches to private, must-revalidate when Expires is set', function () {
            $this->response->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
            expect($this->response->getSymfonyResponse()->headers->get('Cache-Control'))
                ->toBe('private, must-revalidate');
        });

        it('keeps an explicitly set Cache-Control header', function () {
            $this->response->setHeader('Cache-Control', 'public, max-age=3600');
            // Symfony normalizes directive order
            expect($this->response->getSymfonyResponse()->headers->get('Cache-Control'))
                ->toBe('max-age=3600, public');
        });

        it('does not wipe bag cookies when a raw Set-Cookie header is sent', function () {
            $this->response->setCookie('keep_me', 'value');
            $this->response->setRawHeader('Set-Cookie: raw_cookie=1');
            $this->response->sendHeaders();
            expect($this->response->getSymfonyResponse()->headers->getCookies())->toHaveCount(1);
        });

        it('removes a bag header shadowed by a raw header on send', function () {
            $this->response->setRawHeader('Cache-Control: no-store');
            $this->response->sendHeaders();
            expect($this->response->getSymfonyResponse()->headers->has('Cache-Control'))->toBeFalse();
        });

        it('prepares the response against the request on send', function () {
            $this->response->sendHeaders();
            // prepare() supplies a default Content-Type with charset
            expect($this->response->getSymfonyResponse()->headers->get('Content-Type'))
                ->toBe('text/html; charset=utf-8');
        });

        it('appends the charset to a text Content-Type on send', function () {
            $this->response->setHeader('Content-Type', 'text/xml');
            $this->response->sendHeaders();
            expect($this->response->getSymfonyResponse()->headers->get('Content-Type'))
                ->toBe('text/xml; charset=utf-8');
        });

        it('strips body and entity headers from a 304 response on send', function () {
            $this->response->setBody('stale content');
            $this->response->setHttpResponseCode(304);
            $this->response->sendHeaders();
            expect($this->response->getSymfonyResponse()->getContent())->toBe('')
                ->and($this->response->getSymfonyResponse()->headers->has('Content-Type'))->toBeFalse();
        });

        it('is a no-op on a second sendHeaders call', function () {
            $this->response->sendHeaders();
            $this->response->setRawHeader('X-Test: raw');
            $this->response->setHeader('X-Test', 'bag');
            $this->response->sendHeaders();
            // raw-header shadowing did not run, proving the second call was skipped
            expect($this->response->getSymfonyResponse()->headers->has('X-Test'))->toBeTrue();
        });
    });

    describe('Content Type Management', function () {
        it('sets content type header', function () {
            $this->response->setHeader('Content-Type', 'application/json');

            $headers = $this->response->getHeaders();
            $contentType = null;

            foreach ($headers as $header) {
                if ($header['name'] === 'Content-Type') {
                    $contentType = $header['value'];
                    break;
                }
            }

            expect($contentType)->toBe('application/json');
        });

        it('handles charset in content type', function () {
            $this->response->setHeader('Content-Type', 'text/html; charset=UTF-8');

            $symfonyResponse = $this->response->getSymfonyResponse();
            expect($symfonyResponse->headers->get('Content-Type'))->toBe('text/html; charset=UTF-8');
        });
    });
});
