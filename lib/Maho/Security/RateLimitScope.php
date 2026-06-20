<?php

/**
 * Request-identity scope for the shared rate limiter.
 *
 * Core resolves the identity from the request so callers never read the client IP or the
 * session id themselves.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Security;

enum RateLimitScope
{
    case Client;   // client IP, falling back to the session id when the IP is unknown
    case Ip;       // client IP only
    case Session;  // session id only
}
