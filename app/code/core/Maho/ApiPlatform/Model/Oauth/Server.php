<?php

/**
 * The OAuth 2.1 authorization server: request validation, code issuance,
 * token exchange and client registration.
 *
 * Shared by the machine endpoints under /api/oauth and by the admin consent
 * screen, so both agree on what a valid request is.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Model_Oauth_Server
{
    public const RESPONSE_TYPE_CODE = 'code';
    public const SCOPE_MCP = 'mcp';

    /** The scopes a client may ask for. The admin ACL decides what a token can reach. */
    public const SUPPORTED_SCOPES = [self::SCOPE_MCP];

    /** The cookie naming the authorization request that waits for approval. */
    public const PENDING_REQUEST_COOKIE = 'maho_oauth_request';

    /** Long enough to log in and read the screen, short enough to not linger. */
    protected const PENDING_REQUEST_TTL = 900;

    /** RFC 7591 caps nothing, so cap it here: a name is shown on the consent screen. */
    protected const CLIENT_NAME_LIMIT = 120;
    protected const MAX_REDIRECT_URIS = 10;

    protected ?\Maho\ApiPlatform\Service\JwtService $jwtService = null;

    /**
     * Validate an authorization request and return it normalized.
     *
     * The client and the redirect URI are established first. Until both hold, an
     * error must not be sent to the redirect URI, because an unverified URI is
     * an open redirect.
     *
     * @param array<string, mixed> $params
     * @return array{client: Maho_ApiPlatform_Model_Oauth_Client, redirect_uri: string, scope: string, resource: string, code_challenge: string, state: string}
     * @throws Maho_ApiPlatform_Model_Oauth_Exception
     */
    public function validateAuthorizationRequest(array $params): array
    {
        $clientId = trim($this->stringParam($params, 'client_id'));
        if ($clientId === '') {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_REQUEST, 'client_id is required', false);
        }

        $client = $this->loadClient($clientId);
        if (!$client->getId()) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_CLIENT, 'Unknown client', false);
        }

        $redirectUri = trim($this->stringParam($params, 'redirect_uri'));
        if ($redirectUri === '' || !$client->hasRedirectUri($redirectUri)) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_REQUEST, 'redirect_uri does not match a registered value', false);
        }

        // From here the redirect URI is trusted, so errors may travel back to it.
        if (!$client->hasGrantType(Maho_ApiPlatform_Model_Oauth_Client::GRANT_AUTHORIZATION_CODE)) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_UNAUTHORIZED_CLIENT, 'Client may not use the authorization code grant');
        }

        if ($this->stringParam($params, 'response_type') !== self::RESPONSE_TYPE_CODE) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_UNSUPPORTED_RESPONSE_TYPE, 'Only response_type=code is supported');
        }

        if ($this->stringParam($params, 'code_challenge_method') !== Maho_ApiPlatform_Model_Oauth_Token::CHALLENGE_METHOD_S256) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_REQUEST, 'code_challenge_method must be S256');
        }

        $codeChallenge = $this->stringParam($params, 'code_challenge');
        if (!preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $codeChallenge)) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_REQUEST, 'code_challenge is missing or malformed');
        }

        return [
            'client' => $client,
            'redirect_uri' => $redirectUri,
            'scope' => $this->normalizeScope($this->stringParam($params, 'scope')),
            'resource' => $this->resolveResource($params['resource'] ?? null),
            'code_challenge' => $codeChallenge,
            'state' => $this->stringParam($params, 'state'),
        ];
    }

    /**
     * Record the admin's approval and mint a single-use code against it.
     *
     * @param array{client: Maho_ApiPlatform_Model_Oauth_Client, redirect_uri: string, scope: string, resource: string, code_challenge: string, state: string} $request
     */
    public function issueAuthorizationCode(array $request, int $adminId): Maho_ApiPlatform_Model_Oauth_Token
    {
        $clientId = (string) $request['client']->getData('client_id');
        $consentId = $this->tokenResource()->findConsentId($clientId, $adminId, $request['scope'], $request['resource'])
            ?? $this->createConsent($clientId, $adminId, $request['scope'], $request['resource']);

        /** @var Maho_ApiPlatform_Model_Oauth_Token $code */
        $code = Mage::getModel('apiplatform/oauth_token');
        $code->setData([
            'parent_id' => $consentId,
            'client_id' => $clientId,
            'admin_id' => $adminId,
            'type' => Maho_ApiPlatform_Model_Oauth_Token::TYPE_CODE,
            'scope' => $request['scope'],
            'resource' => $request['resource'],
            'redirect_uri' => $request['redirect_uri'],
            'code_challenge' => $request['code_challenge'],
            'code_challenge_method' => Maho_ApiPlatform_Model_Oauth_Token::CHALLENGE_METHOD_S256,
            'expires_at' => time() + $this->helper()->getAuthorizationCodeLifetime(),
        ]);
        $code->issueSecret();
        $code->save();

        return $code;
    }

    /**
     * Park a validated authorization request and return the secret that names it.
     *
     * The request is not passed through the URL on purpose:
     * Mage_Admin_Model_Redirectpolicy sends a visitor to the dashboard when the
     * URL they logged in at carries any parameter, which is a deliberate
     * login-CSRF guard. A parameter-free consent URL keeps that guard intact and
     * survives the login round trip. The secret travels in a cookie instead,
     * which also keeps the authorization parameters out of admin history and out
     * of the Referer header.
     *
     * A row rather than the session: the alias endpoint runs under the Symfony
     * kernel in rest.php, where no admin session is started, so a session write
     * there would be silently dropped.
     *
     * @param array{client: Maho_ApiPlatform_Model_Oauth_Client, redirect_uri: string, scope: string, resource: string, code_challenge: string, state: string} $request
     */
    public function createPendingRequest(array $request): string
    {
        /** @var Maho_ApiPlatform_Model_Oauth_Token $pending */
        $pending = Mage::getModel('apiplatform/oauth_token');
        $pending->setData([
            'client_id' => (string) $request['client']->getData('client_id'),
            'type' => Maho_ApiPlatform_Model_Oauth_Token::TYPE_PENDING,
            'scope' => $request['scope'],
            'resource' => $request['resource'],
            'redirect_uri' => $request['redirect_uri'],
            'code_challenge' => $request['code_challenge'],
            'code_challenge_method' => Maho_ApiPlatform_Model_Oauth_Token::CHALLENGE_METHOD_S256,
            'expires_at' => time() + self::PENDING_REQUEST_TTL,
        ]);
        $pending->setState($request['state']);
        $pending->issueSecret();
        $pending->save();

        return (string) $pending->getPlainToken();
    }

    /**
     * Read back a parked request and validate it again from scratch, so a client
     * revoked or changed in the meantime cannot be approved from a stale copy.
     *
     * @return array{client: Maho_ApiPlatform_Model_Oauth_Client, redirect_uri: string, scope: string, resource: string, code_challenge: string, state: string}|null
     */
    public function readPendingRequest(#[\SensitiveParameter] string $secret, bool $consume = false): ?array
    {
        if ($secret === '') {
            return null;
        }

        /** @var Maho_ApiPlatform_Model_Oauth_Token $pending */
        $pending = Mage::getModel('apiplatform/oauth_token');
        $pending->loadBySecret($secret, Maho_ApiPlatform_Model_Oauth_Token::TYPE_PENDING);

        if (!$pending->isUsable()) {
            return null;
        }

        if ($consume) {
            $pending->markUsed();
        }

        try {
            return $this->validateAuthorizationRequest([
                'client_id' => (string) $pending->getData('client_id'),
                'redirect_uri' => (string) $pending->getData('redirect_uri'),
                'response_type' => self::RESPONSE_TYPE_CODE,
                'scope' => (string) $pending->getData('scope'),
                'resource' => (string) $pending->getData('resource'),
                'code_challenge' => (string) $pending->getData('code_challenge'),
                'code_challenge_method' => Maho_ApiPlatform_Model_Oauth_Token::CHALLENGE_METHOD_S256,
                'state' => (string) $pending->getState(),
            ]);
        } catch (Maho_ApiPlatform_Model_Oauth_Exception) {
            return null;
        }
    }

    /**
     * Whether this admin already approved this exact client, scope and resource.
     * A change in either means the approval screen must be shown again.
     */
    public function findExistingConsentId(string $clientId, int $adminId, string $scope, string $resource): ?int
    {
        return $this->tokenResource()->findConsentId($clientId, $adminId, $scope, $resource);
    }

    /**
     * Exchange a code for an access token and a refresh token.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws Maho_ApiPlatform_Model_Oauth_Exception
     */
    public function exchangeAuthorizationCode(array $params): array
    {
        $client = $this->authenticateClient($params);

        /** @var Maho_ApiPlatform_Model_Oauth_Token $code */
        $code = Mage::getModel('apiplatform/oauth_token');
        $code->loadBySecret($this->stringParam($params, 'code'), Maho_ApiPlatform_Model_Oauth_Token::TYPE_CODE);

        if (!$code->getId()) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_GRANT, 'Unknown authorization code');
        }

        // Established before the replay check below, so a caller holding someone
        // else's spent code cannot cut a grant that was never theirs.
        if ($code->getData('client_id') !== $client->getData('client_id')) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_GRANT, 'Authorization code is not valid');
        }

        // A code presented twice means it leaked. The one legitimate holder
        // already has its tokens, so cut the grant rather than guess which
        // caller was real.
        if ($code->getData('used_at') !== null) {
            $code->revokeGrant();
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_GRANT, 'Authorization code already used');
        }

        if (!$code->isUsable()) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_GRANT, 'Authorization code is not valid');
        }

        if ($this->stringParam($params, 'redirect_uri') !== (string) $code->getData('redirect_uri')) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_GRANT, 'redirect_uri does not match the authorization request');
        }

        if (!$code->verifyCodeChallenge($this->stringParam($params, 'code_verifier'))) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_GRANT, 'code_verifier does not match the challenge');
        }

        // The admin can revoke while a code is in flight, so the consent is
        // checked here as well as on refresh: without it a revoked grant still
        // yields a full-lifetime access token.
        $consentId = $code->getData('parent_id') === null ? null : (int) $code->getData('parent_id');
        if ($consentId === null || !$this->isConsentLive($consentId)) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_GRANT, 'The grant was revoked');
        }

        $code->markUsed();
        $client->recordUsage();

        return $this->issueTokenSet($code, $consentId);
    }

    /**
     * Exchange a refresh token, rotating it. Reuse of a rotated token means the
     * token leaked, so it cuts the grant.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws Maho_ApiPlatform_Model_Oauth_Exception
     */
    public function exchangeRefreshToken(array $params): array
    {
        $client = $this->authenticateClient($params);

        /** @var Maho_ApiPlatform_Model_Oauth_Token $refresh */
        $refresh = Mage::getModel('apiplatform/oauth_token');
        $refresh->loadBySecret($this->stringParam($params, 'refresh_token'), Maho_ApiPlatform_Model_Oauth_Token::TYPE_REFRESH);

        if (!$refresh->getId()) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_GRANT, 'Unknown refresh token');
        }

        // Established before the replay check below, so a caller holding someone
        // else's rotated token cannot cut a grant that was never theirs.
        if ($refresh->getData('client_id') !== $client->getData('client_id')) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_GRANT, 'Refresh token is not valid');
        }

        if ($refresh->getData('used_at') !== null) {
            $refresh->revokeGrant();
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_GRANT, 'Refresh token already used');
        }

        if (!$refresh->isUsable()) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_GRANT, 'Refresh token is not valid');
        }

        $consentId = $refresh->getData('parent_id') === null ? null : (int) $refresh->getData('parent_id');
        if ($consentId === null || !$this->isConsentLive($consentId)) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_GRANT, 'The grant was revoked');
        }

        $refresh->markUsed();
        $client->recordUsage();

        return $this->issueTokenSet($refresh, $consentId);
    }

    /**
     * RFC 7591. Registration grants nothing on its own: an admin must still log
     * in and approve before any token exists.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     * @throws Maho_ApiPlatform_Model_Oauth_Exception
     */
    public function registerClient(array $metadata): array
    {
        $uris = $metadata['redirect_uris'] ?? null;
        if (!is_array($uris) || $uris === []) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_REDIRECT_URI, 'redirect_uris is required');
        }

        if (count($uris) > self::MAX_REDIRECT_URIS) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_REDIRECT_URI, 'Too many redirect URIs');
        }

        $clean = [];
        foreach ($uris as $uri) {
            if (!is_string($uri) || !Maho_ApiPlatform_Model_Oauth_Client::isAllowedRedirectUri($uri)) {
                throw $this->fail(
                    Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_REDIRECT_URI,
                    'Every redirect URI must use https, or http on loopback, and carry no fragment',
                );
            }
            $clean[] = $uri;
        }

        $authMethod = isset($metadata['token_endpoint_auth_method'])
            ? $this->stringParam($metadata, 'token_endpoint_auth_method')
            : Maho_ApiPlatform_Model_Oauth_Client::AUTH_METHOD_NONE;
        if (!in_array($authMethod, [
            Maho_ApiPlatform_Model_Oauth_Client::AUTH_METHOD_NONE,
            Maho_ApiPlatform_Model_Oauth_Client::AUTH_METHOD_CLIENT_SECRET_POST,
            Maho_ApiPlatform_Model_Oauth_Client::AUTH_METHOD_CLIENT_SECRET_BASIC,
        ], true)) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_CLIENT_METADATA, 'Unsupported token_endpoint_auth_method');
        }

        $name = trim($this->stringParam($metadata, 'client_name'));
        if ($name === '') {
            $name = (string) $this->helper()->__('Unnamed client');
        }

        $clientId = bin2hex(random_bytes(16));
        $secret = null;

        /** @var Maho_ApiPlatform_Model_Oauth_Client $client */
        $client = Mage::getModel('apiplatform/oauth_client');
        $client->setData([
            'client_id' => $clientId,
            'client_name' => mb_substr($name, 0, self::CLIENT_NAME_LIMIT),
            'grant_types' => implode(',', [
                Maho_ApiPlatform_Model_Oauth_Client::GRANT_AUTHORIZATION_CODE,
                Maho_ApiPlatform_Model_Oauth_Client::GRANT_REFRESH_TOKEN,
            ]),
            'token_endpoint_auth_method' => $authMethod,
            'is_trusted' => 0,
        ]);
        $client->setRedirectUris($clean);

        if ($authMethod !== Maho_ApiPlatform_Model_Oauth_Client::AUTH_METHOD_NONE) {
            $secret = bin2hex(random_bytes(32));
            $client->setData('client_secret_hash', password_hash($secret, PASSWORD_DEFAULT));
        }

        $client->save();

        $response = [
            'client_id' => $clientId,
            'client_id_issued_at' => time(),
            'client_name' => $client->getData('client_name'),
            'redirect_uris' => $clean,
            'grant_types' => $client->getGrantTypes(),
            'response_types' => [self::RESPONSE_TYPE_CODE],
            'token_endpoint_auth_method' => $authMethod,
        ];

        if ($secret !== null) {
            $response['client_secret'] = $secret;
            // 0 means "does not expire", per RFC 7591.
            $response['client_secret_expires_at'] = 0;
        }

        return $response;
    }

    /**
     * A resource indicator must name something this install actually serves,
     * or the token would carry an audience nobody validates.
     *
     * @throws Maho_ApiPlatform_Model_Oauth_Exception
     */
    public function resolveResource(mixed $requested): string
    {
        if ($requested === null || $requested === '') {
            // The narrowest audience available, so a token defaults to the least reach.
            return $this->helper()->getDefaultResource();
        }

        if (!is_string($requested)) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_TARGET, 'resource must be a single URI');
        }

        $value = rtrim($requested, '/');
        if (!in_array($value, $this->helper()->getCanonicalResources(), true)) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_TARGET, 'Unknown resource indicator');
        }

        return $value;
    }

    /**
     * @throws Maho_ApiPlatform_Model_Oauth_Exception
     */
    public function normalizeScope(string $scope): string
    {
        $requested = array_values(array_filter(preg_split('/\s+/', trim($scope)) ?: []));
        if ($requested === []) {
            return self::SCOPE_MCP;
        }

        foreach ($requested as $one) {
            if (!in_array($one, self::SUPPORTED_SCOPES, true)) {
                throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_SCOPE, 'Unknown scope: ' . $one);
            }
        }

        sort($requested);

        return implode(' ', array_unique($requested));
    }

    public function loadClient(string $clientId): Maho_ApiPlatform_Model_Oauth_Client
    {
        /** @var Maho_ApiPlatform_Model_Oauth_Client $client */
        $client = Mage::getModel('apiplatform/oauth_client');
        $client->loadByClientId($clientId);

        return $client;
    }

    /**
     * Mint the access token and the next refresh token from a spent code or a
     * spent refresh token.
     *
     * @return array<string, mixed>
     * @throws Maho_ApiPlatform_Model_Oauth_Exception
     */
    protected function issueTokenSet(Maho_ApiPlatform_Model_Oauth_Token $parent, int $consentId): array
    {
        $admin = Mage::getModel('admin/user')->load((int) $parent->getData('admin_id'));
        if (!$admin->getId() || !$admin->getIsActive()) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_GRANT, 'The approving admin user is no longer active');
        }

        $resource = (string) $parent->getData('resource');
        $scope = (string) $parent->getData('scope');

        /** @var Maho_ApiPlatform_Model_Oauth_Token $refresh */
        $refresh = Mage::getModel('apiplatform/oauth_token');
        $refresh->setData([
            'parent_id' => $consentId,
            'client_id' => $parent->getData('client_id'),
            'admin_id' => $parent->getData('admin_id'),
            'type' => Maho_ApiPlatform_Model_Oauth_Token::TYPE_REFRESH,
            'scope' => $scope,
            'resource' => $resource,
            'expires_at' => time() + $this->helper()->getRefreshTokenLifetime(),
        ]);
        $refresh->issueSecret();
        $refresh->save();

        return [
            'access_token' => $this->jwt()->generateAdminToken($admin, audience: $resource, scope: $scope),
            'token_type' => 'Bearer',
            'expires_in' => $this->helper()->getTokenLifetime(),
            'refresh_token' => $refresh->getPlainToken(),
            'scope' => $scope,
        ];
    }

    protected function createConsent(string $clientId, int $adminId, string $scope, string $resource): int
    {
        /** @var Maho_ApiPlatform_Model_Oauth_Token $consent */
        $consent = Mage::getModel('apiplatform/oauth_token');
        $consent->setData([
            'client_id' => $clientId,
            'admin_id' => $adminId,
            'type' => Maho_ApiPlatform_Model_Oauth_Token::TYPE_CONSENT,
            'scope' => $scope,
            'resource' => $resource,
        ]);
        $consent->save();

        return (int) $consent->getId();
    }

    protected function isConsentLive(int $consentId): bool
    {
        /** @var Maho_ApiPlatform_Model_Oauth_Token $consent */
        $consent = Mage::getModel('apiplatform/oauth_token')->load($consentId);

        return $consent->getId() !== null && $consent->isUsable();
    }

    /**
     * @param array<string, mixed> $params
     * @throws Maho_ApiPlatform_Model_Oauth_Exception
     */
    protected function authenticateClient(array $params): Maho_ApiPlatform_Model_Oauth_Client
    {
        $client = $this->loadClient(trim($this->stringParam($params, 'client_id')));
        if (!$client->getId()) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_CLIENT, 'Unknown client', httpStatus: 401);
        }

        if (!$client->isPublic() && !$client->verifySecret($this->stringParam($params, 'client_secret'))) {
            throw $this->fail(Maho_ApiPlatform_Model_Oauth_Exception::ERROR_INVALID_CLIENT, 'Client authentication failed', httpStatus: 401);
        }

        return $client;
    }

    /**
     * Query strings and JSON bodies can carry an array where a string belongs
     * (`?state[]=x`), and casting one to string raises a warning that developer
     * mode turns into an exception. Anything not a scalar is simply absent.
     *
     * @param array<string, mixed> $params
     */
    protected function stringParam(array $params, string $key): string
    {
        $value = $params[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    protected function fail(string $error, string $description, bool $redirectable = true, int $httpStatus = 400): Maho_ApiPlatform_Model_Oauth_Exception
    {
        return new Maho_ApiPlatform_Model_Oauth_Exception($error, $description, $redirectable, $httpStatus);
    }

    protected function tokenResource(): Maho_ApiPlatform_Model_Resource_Oauth_Token
    {
        /** @var Maho_ApiPlatform_Model_Resource_Oauth_Token $resource */
        $resource = Mage::getResourceSingleton('apiplatform/oauth_token');
        return $resource;
    }

    protected function jwt(): \Maho\ApiPlatform\Service\JwtService
    {
        return $this->jwtService ??= new \Maho\ApiPlatform\Service\JwtService();
    }

    protected function helper(): Maho_ApiPlatform_Helper_Data
    {
        /** @var Maho_ApiPlatform_Helper_Data $helper */
        $helper = Mage::helper('apiplatform');
        return $helper;
    }
}
