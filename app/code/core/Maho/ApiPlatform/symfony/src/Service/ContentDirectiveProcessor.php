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

namespace Maho\ApiPlatform\Service;

/**
 * Processes CMS directives ({{media}}, {{block}}, {{config}}, etc.) for API output.
 *
 * Delegates to the core CMS template filter which already handles all directive
 * types including {{media}}, {{store}}, {{skin}}, {{config}}, {{block}}, and {{widget}}.
 */
final class ContentDirectiveProcessor
{
    /**
     * Process all supported directives in CMS content for API output.
     */
    public static function process(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        $filter = \Mage::helper('cms')->getPageTemplateProcessor();
        $filter->setStoreId(StoreContext::getStoreId());

        return $filter->filter($content);
    }
}
