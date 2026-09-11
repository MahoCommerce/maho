<?php

/**
 * Thrown when a URL must not be fetched by the server.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Security;

class OutboundUrlException extends \InvalidArgumentException {}
