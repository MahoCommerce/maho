<?php

/**
 * The OAuth 2.1 machine endpoints: authorization alias, token exchange and
 * dynamic client registration.
 *
 * These speak RFC 6749 on the wire (form encoded requests, snake_case errors),
 * not the JSON-LD shapes API Platform produces, so they are plain Symfony
 * controllers rather than API resources.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Controller;

use Mage;
use Maho_ApiPlatform_Model_Oauth_Exception as OauthException;
use Maho_ApiPlatform_Model_Oauth_Server as OauthServer;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OAuthController
{
    /** Registration is unauthenticated by design, so it needs its own ceiling. */
    private const REGISTER_MAX_ATTEMPTS = 20;
    private const REGISTER_WINDOW = 3600;

    private const TOKEN_MAX_ATTEMPTS = 60;
    private const TOKEN_WINDOW = 60;

    /**
     * The public face of the authorization endpoint. It validates what it can
     * without a session, then hands the browser to the admin consent screen.
     *
     * The alias exists so the RFC 8414 document, which anyone may fetch, does
     * not have to name the store's admin path.
     */
    #[Route('/api/oauth/authorize', name: 'api_oauth_authorize', methods: ['GET'])]
    public function authorize(Request $request): Response
    {
        if (!$this->helper()->isAuthorizationServerEnabled()) {
            return $this->notFound();
        }

        $params = $request->query->all();

        try {
            $validated = $this->server()->validateAuthorizationRequest($params);
        } catch (OauthException $e) {
            return $this->authorizationError($e, (string) ($params['redirect_uri'] ?? ''), (string) ($params['state'] ?? ''));
        }

        // The request is parked server-side and named by a cookie, so the consent
        // URL carries no parameters. See Server::createPendingRequest for why.
        $secret = $this->server()->createPendingRequest($validated);

        // _nosecret: the caller has no admin session, so any key minted here is
        // meaningless. The action is public for exactly this reason, and the
        // approval POST is protected by the admin form key instead.
        $response = new RedirectResponse(
            Mage::helper('adminhtml')->getUrl('adminhtml/apiplatform_oauth/authorize', ['_nosecret' => true]),
        );

        // Lax, not Strict: the browser arrives here from the client application,
        // and a Strict cookie would not be sent on that first navigation.
        $response->headers->setCookie(Cookie::create(OauthServer::PENDING_REQUEST_COOKIE)
            ->withValue($secret)
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite(Cookie::SAMESITE_LAX));

        return $response;
    }

    #[Route('/api/oauth/token', name: 'api_oauth_token', methods: ['POST'])]
    public function token(Request $request): Response
    {
        if (!$this->helper()->isAuthorizationServerEnabled()) {
            return $this->notFound();
        }

        if (!$this->withinRateLimit('oauth_token', self::TOKEN_MAX_ATTEMPTS, self::TOKEN_WINDOW)) {
            return $this->error(OauthException::ERROR_INVALID_REQUEST, 'Too many token requests', 429);
        }

        $params = $this->readFormBody($request);
        $params += $this->readBasicAuth($request);

        try {
            $result = match ((string) ($params['grant_type'] ?? '')) {
                \Maho_ApiPlatform_Model_Oauth_Client::GRANT_AUTHORIZATION_CODE => $this->server()->exchangeAuthorizationCode($params),
                \Maho_ApiPlatform_Model_Oauth_Client::GRANT_REFRESH_TOKEN => $this->server()->exchangeRefreshToken($params),
                default => throw new OauthException(
                    OauthException::ERROR_UNSUPPORTED_GRANT_TYPE,
                    'Supported grant types: authorization_code, refresh_token',
                ),
            };
        } catch (OauthException $e) {
            return $this->error($e->getError(), $e->getDescription(), $e->getHttpStatus());
        }

        // RFC 6749 section 5.1: a token response is never cached.
        return new JsonResponse($result, 200, [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }

    #[Route('/api/oauth/register', name: 'api_oauth_register', methods: ['POST'])]
    public function register(Request $request): Response
    {
        if (!$this->helper()->isDynamicRegistrationEnabled()) {
            return $this->notFound();
        }

        if (!$this->withinRateLimit('oauth_register', self::REGISTER_MAX_ATTEMPTS, self::REGISTER_WINDOW)) {
            return $this->error(OauthException::ERROR_INVALID_REQUEST, 'Too many registration requests', 429);
        }

        try {
            $metadata = (array) Mage::helper('core')->jsonDecode($request->getContent() ?: '{}');
        } catch (\JsonException) {
            return $this->error(OauthException::ERROR_INVALID_CLIENT_METADATA, 'Request body must be JSON');
        }

        try {
            $client = $this->server()->registerClient($metadata);
        } catch (OauthException $e) {
            return $this->error($e->getError(), $e->getDescription(), $e->getHttpStatus());
        }

        return new JsonResponse($client, 201, ['Cache-Control' => 'no-store']);
    }

    /**
     * An error may only travel to a redirect URI that has been matched against
     * the client's registered list. Anything earlier renders instead, because
     * redirecting to an unverified URI is an open redirect.
     */
    private function authorizationError(OauthException $e, string $redirectUri, string $state): Response
    {
        if (!$e->isRedirectable() || $redirectUri === '') {
            return $this->error($e->getError(), $e->getDescription(), $e->getHttpStatus());
        }

        $query = ['error' => $e->getError(), 'error_description' => $e->getDescription()];
        if ($state !== '') {
            $query['state'] = $state;
        }

        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return new RedirectResponse($redirectUri . $separator . http_build_query($query));
    }

    /**
     * @return array<string, mixed>
     */
    private function readFormBody(Request $request): array
    {
        // RFC 6749 requires form encoding. JSON is accepted as well, because
        // some clients send it and refusing costs interoperability for nothing.
        if ($request->request->count() > 0) {
            return $request->request->all();
        }

        try {
            $decoded = Mage::helper('core')->jsonDecode($request->getContent() ?: '{}');
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * client_secret_basic puts the credentials in the Authorization header
     * rather than the body.
     *
     * @return array<string, mixed>
     */
    private function readBasicAuth(Request $request): array
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Basic ')) {
            return [];
        }

        $decoded = base64_decode(substr($header, 6), true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return [];
        }

        [$clientId, $secret] = explode(':', $decoded, 2);

        return ['client_id' => urldecode($clientId), 'client_secret' => urldecode($secret)];
    }

    private function withinRateLimit(string $namespace, int $maxAttempts, int $window): bool
    {
        return Mage::helper('core')->rateLimiter($namespace, $maxAttempts, $window)->attempt();
    }

    private function error(string $error, string $description, int $status = 400): JsonResponse
    {
        return new JsonResponse(
            ['error' => $error, 'error_description' => $description],
            $status,
            ['Cache-Control' => 'no-store'],
        );
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(['error' => 'not_found'], 404);
    }

    private function server(): OauthServer
    {
        /** @var OauthServer $server */
        $server = Mage::getSingleton('apiplatform/oauth_server');
        return $server;
    }

    private function helper(): \Maho_ApiPlatform_Helper_Data
    {
        /** @var \Maho_ApiPlatform_Helper_Data $helper */
        $helper = Mage::helper('apiplatform');
        return $helper;
    }
}
