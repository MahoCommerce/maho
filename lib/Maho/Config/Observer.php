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
 * Registers a method as an event observer.
 *
 * Compiled at `composer dump-autoload` into `vendor/composer/maho_attributes.php`.
 * Run `composer dump-autoload` after adding, modifying, or removing this attribute.
 * This attribute is repeatable — apply multiple times to listen to different events or areas.
 *
 * @see Mage::dispatchEvent()
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class Observer
{
    /**
     * @param string  $event    Event name to observe (e.g. 'catalog_product_save_after')
     * @param string  $area     Area scope: 'global' (default), 'frontend', 'adminhtml', 'crontab', 'install'
     * @param string  $type     Instantiation type: 'singleton' (default, shared instance) or 'model' (new instance per dispatch)
     * @param ?string $id       Observer identifier, auto-generated if omitted. Set explicitly when other code references this observer by id.
     * @param ?string $replaces The `id` of another observer on the same event/area to disable and replace.
     *                          Accepts either the explicit id, the class name, or the class alias format
     *                          (e.g. `'my_observer'`, `'Mage_Catalog_Model_Observer::myMethod'`,
     *                          or `'catalog/observer::myMethod'`).
     * @param array   $args     Additional arguments passed to the observer via the event object
     */
    public function __construct(
        public string $event,
        public string $area = 'global',
        public string $type = 'singleton',
        public ?string $id = null,
        public ?string $replaces = null,
        public array $args = [],
    ) {}
}
