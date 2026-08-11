<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue;

use Psr\Container\NotFoundExceptionInterface;

final class ServiceNotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface {}
