<?php

/**
 * An OAuth 2.1 error, carrying the code the specification puts on the wire.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Model_Oauth_Exception extends Mage_Core_Exception
{
    public const ERROR_INVALID_REQUEST = 'invalid_request';
    public const ERROR_INVALID_CLIENT = 'invalid_client';
    public const ERROR_INVALID_GRANT = 'invalid_grant';
    public const ERROR_UNAUTHORIZED_CLIENT = 'unauthorized_client';
    public const ERROR_UNSUPPORTED_GRANT_TYPE = 'unsupported_grant_type';
    public const ERROR_UNSUPPORTED_RESPONSE_TYPE = 'unsupported_response_type';
    public const ERROR_INVALID_SCOPE = 'invalid_scope';
    public const ERROR_INVALID_TARGET = 'invalid_target';
    public const ERROR_ACCESS_DENIED = 'access_denied';
    public const ERROR_INVALID_REDIRECT_URI = 'invalid_redirect_uri';
    public const ERROR_INVALID_CLIENT_METADATA = 'invalid_client_metadata';
    public const ERROR_SERVER_ERROR = 'server_error';

    /**
     * @param bool $redirectable Whether the error may be reported to the client's
     *                           redirect URI. False when the client or the URI
     *                           itself could not be established, because sending
     *                           an error to an unverified URI is an open redirect.
     */
    public function __construct(
        protected string $error,
        string $description = '',
        protected bool $redirectable = true,
        protected int $httpStatus = 400,
    ) {
        parent::__construct($description !== '' ? $description : $error);
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getDescription(): string
    {
        return $this->getMessage();
    }

    public function isRedirectable(): bool
    {
        return $this->redirectable;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
