<?php

/**
 * Accepts a token whose audience is any one of a permitted set.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Validation;

use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\ConstraintViolation;

/**
 * Lcobucci's PermittedFor takes exactly one audience, but an install serves
 * several resource identifiers: a root per host, plus the MCP endpoint under
 * each. This check only proves the audience is one this install issues. Whether
 * it covers the path being requested is decided further up, by
 * OAuth2Authenticator.
 */
final class PermittedForAny implements Constraint
{
    /** @param non-empty-list<non-empty-string> $audiences */
    public function __construct(private readonly array $audiences) {}

    #[\Override]
    public function assert(#[\SensitiveParameter]
        Token $token): void
    {
        foreach ($this->audiences as $audience) {
            if ($token->isPermittedFor($audience)) {
                return;
            }
        }

        throw ConstraintViolation::error(
            'The token is not allowed to be used by this audience',
            $this,
        );
    }
}
