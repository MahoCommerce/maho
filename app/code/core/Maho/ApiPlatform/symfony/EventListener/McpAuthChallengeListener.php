<?php

/**
 * Turn an MCP authentication refusal into the 401 challenge clients act on.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use Maho\ApiPlatform\Security\OperationAccessChecker;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The MCP server catches a tool's exception in its own loop and answers with a
 * JSON-RPC error at HTTP 200. For an ordinary failure that is right. For "you
 * are not authenticated" it is a dead end: the reply says to send a bearer
 * token, in prose, with nothing a client can act on.
 *
 * The authorization specification is explicit that 401 means authorization is
 * required, and that the reply must carry `WWW-Authenticate` naming the
 * protected resource metadata. That header is what starts discovery, dynamic
 * registration and the browser consent flow.
 *
 * Two moments, because clients differ in when they will start that flow:
 *
 * - On the response, whenever a tool was refused for want of a token. This is
 *   the specification's own behaviour and costs anonymous callers nothing: a
 *   client browsing public catalog tools never triggers it.
 * - On the request, when the store has asked for MCP to require authentication.
 *   Some clients only attempt OAuth while connecting and ignore a 401 raised
 *   later, so a store that wants the merchant connection to work everywhere can
 *   have the endpoint challenge up front.
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onRequest', priority: 6)]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onResponse')]
final class McpAuthChallengeListener
{
    /** Set by McpDispatchProvider when a tool is refused for want of a token. */
    public const ATTRIBUTE_AUTH_REQUIRED = '_maho_mcp_auth_required';

    public function __construct(private readonly OperationAccessChecker $accessChecker) {}

    /**
     * Runs after the firewall, so an authenticated caller is already known.
     */
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->isMcp($event->getRequest())) {
            return;
        }

        if (!$this->helper()->isMcpAuthRequired() || $this->accessChecker->isAuthenticated()) {
            return;
        }

        $event->setResponse($this->challenge('Authentication is required to use this MCP server.'));
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$this->isMcp($request)) {
            return;
        }

        if (!$request->attributes->getBoolean(self::ATTRIBUTE_AUTH_REQUIRED)) {
            return;
        }

        // The JSON-RPC error body stays as it is: a client reads the transport
        // status to decide whether to authenticate, and reads the body to tell
        // the user what happened.
        $response = $event->getResponse();
        $response->setStatusCode(Response::HTTP_UNAUTHORIZED);
        $response->headers->set('WWW-Authenticate', $this->helper()->getBearerChallenge());
    }

    private function challenge(string $message): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'unauthorized', 'message' => $message],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => $this->helper()->getBearerChallenge()],
        );
    }

    private function isMcp(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/mcp');
    }

    private function helper(): \Maho_ApiPlatform_Helper_Data
    {
        /** @var \Maho_ApiPlatform_Helper_Data $helper */
        $helper = \Mage::helper('apiplatform');
        return $helper;
    }
}
