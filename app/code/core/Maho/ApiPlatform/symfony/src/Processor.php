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

use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Maho\ApiPlatform\Trait\ModelPersistenceTrait;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Base class for all API state processors.
 *
 * Bundles AuthenticationTrait (customer/admin access checks, permission enforcement)
 * and ModelPersistenceTrait (safeSave, safeDelete, secureAreaDelete, loadOrFail).
 *
 * Subclasses that need Security should accept it via constructor and call parent::__construct().
 * Subclasses that don't need Security can omit the constructor entirely — $security defaults to null,
 * and authentication methods gracefully return null/false.
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
abstract class Processor implements ProcessorInterface
{
    use AuthenticationTrait;
    use ModelPersistenceTrait;

    public function __construct(?Security $security = null)
    {
        $this->security = $security;
    }
}
