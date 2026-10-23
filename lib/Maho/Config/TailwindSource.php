<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Config;

use Attribute;

/**
 * Registers a method that returns storefront HTML from the database, so that the Tailwind
 * build compiles the classes of that HTML.
 *
 * Compiled at `composer dump-autoload` into `vendor/composer/maho_attributes.php`.
 * Run `composer dump-autoload` after adding, modifying, or removing this attribute.
 * The method takes no arguments and returns an iterable of HTML strings. The declaring class
 * is instantiated via `Mage::getSingleton()` when the build runs.
 *
 * A save of `$model` that changes one of `$fields` queues a build. A source with another
 * rule leaves `$model` null and calls `Mage_Core_Model_Design_Content_Bundle::queueBuild()`
 * from its own observer.
 *
 * @see Mage_Core_Model_Design_Content_Collector::getSources()
 */
#[Attribute(Attribute::TARGET_METHOD)]
readonly class TailwindSource
{
    /**
     * @param string        $id     Source identifier (e.g. 'cms_block'). A later source with the same id replaces the earlier one
     * @param ?class-string $model  Model class whose save can change the content
     * @param list<string>  $fields Fields of $model that hold the content. Empty means every save of $model
     */
    public function __construct(
        public string $id,
        public ?string $model = null,
        public array $fields = [],
    ) {}
}
