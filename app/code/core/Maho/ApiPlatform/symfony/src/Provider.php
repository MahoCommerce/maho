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

use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Maho\ApiPlatform\Trait\PaginationTrait;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Base class for all API state providers.
 *
 * Bundles AuthenticationTrait (customer/admin access checks, permission enforcement)
 * and PaginationTrait (page/pageSize extraction from REST and GraphQL contexts).
 *
 * Subclasses that need Security should accept it via constructor and call parent::__construct().
 * Subclasses that don't need Security can omit the constructor entirely — $security defaults to null,
 * and authentication methods gracefully return null/false.
 *
 * @implements ProviderInterface<object>
 */
abstract class Provider implements ProviderInterface
{
    use AuthenticationTrait;
    use PaginationTrait;

    public function __construct(?Security $security = null)
    {
        $this->security = $security;
    }
}
