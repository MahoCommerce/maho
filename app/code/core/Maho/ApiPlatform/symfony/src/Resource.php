<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform;

use ApiPlatform\Metadata\ApiProperty;

/**
 * Base class for all API resource DTOs.
 *
 * Provides the $extensions property used by the event-based extension system.
 * Third-party modules populate this field via api_{resource}_dto_build events,
 * allowing them to attach arbitrary data to API responses without modifying
 * core resource classes.
 *
 * All API DTOs should extend this class.
 */
abstract class Resource
{
    /**
     * Module-provided extension data.
     * Populated via api_{resource}_dto_build event. Modules can append
     * arbitrary keyed data here without modifying core API resources.
     * @var array<string, mixed>
     */
    #[ApiProperty(description: 'Module-provided extension data')]
    public array $extensions = [];
}
