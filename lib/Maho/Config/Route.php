<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Config;

use Attribute;

/**
 * Registers a controller action method as a Symfony route.
 *
 * Routes defined with this attribute are compiled at `composer dump-autoload` into
 * `vendor/composer/maho_attributes.php` and loaded into Symfony's RouteCollection
 * at runtime, taking priority over XML-configured catch-all routes.
 *
 * Run `composer dump-autoload` after adding, modifying, or removing this attribute.
 * This attribute is repeatable — apply multiple times for different paths or methods.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class Route
{
    /**
     * @param string      $path         URL path pattern (e.g. '/catalog/product/view/{id}')
     * @param ?string     $name         Route name for URL generation, auto-generated from class::method if omitted
     * @param string[]    $methods      Allowed HTTP methods (e.g. ['GET', 'POST']). Empty = any method.
     * @param array       $defaults     Default parameter values
     * @param array       $requirements Regex constraints for parameters (e.g. ['id' => '\d+'])
     * @param ?string     $area         Area scope: 'frontend' or 'adminhtml'. Auto-detected from controller class if omitted.
     */
    public function __construct(
        public string $path,
        public ?string $name = null,
        public array $methods = [],
        public array $defaults = [],
        public array $requirements = [],
        public ?string $area = null,
    ) {}
}
