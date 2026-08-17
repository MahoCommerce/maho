<?php

/**
 * Root span lifecycle shared by every HTTP entry point: the front controller, the
 * API Platform kernel and the legacy API server.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_OpenTelemetry
 */

declare(strict_types=1);

class Maho_OpenTelemetry_Model_Request
{
    /**
     * Open the request root span and expose its trace id to browser RUM tooling
     */
    public static function start(Maho_OpenTelemetry_Model_Tracer $tracer): Maho_OpenTelemetry_Model_Span
    {
        $method = self::method();
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');

        $span = $tracer->startRootSpan($method, [
            'http.request.method' => $method,
            'url.path' => self::path(),
            'url.scheme' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
            // Strip the port, preserving bracketed IPv6 literals ("[::1]:8080" becomes "[::1]")
            'server.address' => preg_replace('/:\d+$/', '', $host) ?? $host,
        ]);

        try {
            $span->setAttributes([
                'maho.store_id' => Mage::app()->getStore()->getId(),
                'maho.store_code' => Mage::app()->getStore()->getCode(),
                'maho.website_id' => Mage::app()->getWebsite()->getId(),
            ]);
        } catch (\Throwable) {
            // Store may not be fully initialized yet
        }

        try {
            if (Mage::helper('opentelemetry')->isServerTimingEnabled()) {
                $traceparent = $tracer->getTracePropagationHeaders()['traceparent'] ?? '';
                if ($traceparent !== '' && !headers_sent()) {
                    header('Server-Timing: traceparent;desc="' . $traceparent . '"');
                }
            }
        } catch (\Throwable) {
            // Telemetry must never break the response
        }

        return $span;
    }

    /**
     * Record the outcome of a handled request on the root span
     *
     * @param string|null $route Low cardinality route identifier, used as the span name
     * @param string|null $area  Named by entry points that know it, derived otherwise
     */
    public static function describe(
        Maho_OpenTelemetry_Model_Span $span,
        int $statusCode,
        ?string $route = null,
        ?string $area = null,
    ): void {
        $span->setAttribute('http.response.status_code', $statusCode);
        $span->setStatus($statusCode >= 500 ? 'error' : 'ok');

        $isAdmin = false;
        try {
            $isAdmin = Mage::app()->getStore()->isAdmin();
        } catch (\Throwable) {
            // Store may not be initialized
        }

        $request = Mage::app()->getRequest();
        if ($area === null) {
            // Only the front controller routes through the Mage request; an entry
            // point that names its own area names its own route too
            if ($route === null && $request) {
                $route = $request->getModuleName()
                    . '/' . $request->getControllerName()
                    . '/' . $request->getActionName();
            }

            $area = $isAdmin ? 'admin' : 'frontend';
            if (str_starts_with($request?->getModuleName() ?? '', 'api')
                || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')
            ) {
                $area = 'api';
            }
        }

        if ($route !== null && $route !== '') {
            // Span name per HTTP semconv, so trace lists group by route
            $span->setAttribute('http.route', $route);
            $span->updateName(self::method() . ' ' . $route);
        }
        $span->setAttribute('maho.area', $area);

        $enduserId = self::enduserId($area === 'admin');
        if ($enduserId !== '') {
            $span->setAttribute('enduser.id', $enduserId);
        }
    }

    /**
     * End the root span and ship everything queued for this request
     */
    public static function finish(
        Maho_OpenTelemetry_Model_Tracer $tracer,
        ?Maho_OpenTelemetry_Model_Span $span,
        int $statusCode,
    ): void {
        $span?->end();

        // Recorded before flush(), so it ships with this request
        $tracer->recordRequestDuration(
            microtime(true) - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)),
            [
                'http.request.method' => self::method(),
                'http.response.status_code' => $statusCode,
            ],
        );

        $tracer->flush();
    }

    /**
     * Pseudonymous id of the signed-in user, empty when nobody is signed in.
     * Outside the admin area the session is read only if the request already
     * built one: creating it here would start a session for every visitor.
     */
    private static function enduserId(bool $isAdmin): string
    {
        try {
            if ($isAdmin) {
                $adminSession = Mage::getSingleton('admin/session');
                return $adminSession->isLoggedIn() ? (string) $adminSession->getUser()?->getUserId() : '';
            }

            $customerSession = Mage::registry('_singleton/customer/session');
            if ($customerSession instanceof Mage_Customer_Model_Session && $customerSession->isLoggedIn()) {
                return (string) $customerSession->getCustomerId();
            }

            $adminSession = Mage::registry('_singleton/admin/session');
            if ($adminSession instanceof Mage_Admin_Model_Session && $adminSession->isLoggedIn()) {
                return (string) $adminSession->getUser()?->getUserId();
            }
        } catch (\Throwable) {
            // Session not available, skip
        }

        return '';
    }

    private static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    }

    private static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strtok($uri, '?') ?: $uri;
    }
}
