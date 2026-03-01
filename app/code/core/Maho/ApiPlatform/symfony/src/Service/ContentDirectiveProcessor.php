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
 * Used by CMS Page, CMS Block, Blog Post, and Category providers to render
 * Magento template directives into plain HTML before sending via the API.
 */
final class ContentDirectiveProcessor
{
    /** Config paths safe to expose via API */
    private const ALLOWED_CONFIG_PREFIXES = [
        'general/store_information/',
        'web/unsecure/',
        'web/secure/',
        'design/',
        'trans_email/',
        'contacts/',
        'catalog/seo/',
    ];

    /**
     * Process all supported directives in CMS content for API output.
     *
     * Handles: {{media}}, {{config}}, {{store}}, {{skin}}, {{block type="cms/block"}},
     * {{block type="..."}}, and strips {{widget}} directives.
     */
    public static function process(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        $storeId = StoreContext::getStoreId();
        $store = \Mage::app()->getStore($storeId);

        // Process {{media url="..."}} directive
        $content = preg_replace_callback(
            '/\{\{media\s+url=["\']?([^"\'}\s]+)["\']?\s*\}\}/i',
            function ($matches) use ($store) {
                return $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_MEDIA) . $matches[1];
            },
            $content,
        );

        // Process {{config path="..."}} directive (whitelist safe paths only)
        $content = preg_replace_callback(
            '/\{\{config\s+path=["\']?([^"\'}\s]+)["\']?\s*\}\}/i',
            function ($matches) use ($storeId) {
                $path = $matches[1];
                foreach (self::ALLOWED_CONFIG_PREFIXES as $prefix) {
                    if (str_starts_with($path, $prefix)) {
                        return \Mage::getStoreConfig($path, $storeId) ?? '';
                    }
                }
                return '';
            },
            $content,
        );

        // Process {{store url="..."}} directive
        $content = preg_replace_callback(
            '/\{\{store\s+url=["\']?([^"\'}\s]+)["\']?\s*\}\}/i',
            function ($matches) use ($store) {
                return $store->getUrl($matches[1]);
            },
            $content,
        );

        // Process {{skin url="..."}} directive
        $content = preg_replace_callback(
            '/\{\{skin\s+url=["\']?([^"\'}\s]+)["\']?\s*\}\}/i',
            function ($matches) use ($store) {
                return $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_SKIN) . $matches[1];
            },
            $content,
        );

        // Process {{block type="cms/block" block_id="..."}} — render static CMS blocks inline
        $content = preg_replace_callback(
            '/\{\{block\s+type="cms\/block"\s+block_id="([^"]+)"\s*\}\}/i',
            function ($matches) use ($storeId) {
                $identifier = $matches[1];
                try {
                    /** @var \Mage_Cms_Model_Block $block */
                    $block = \Mage::getModel('cms/block')
                        ->setStoreId($storeId)
                        ->load($identifier, 'identifier');
                    if ($block->getIsActive() && $block->getContent()) {
                        return self::process($block->getContent());
                    }
                } catch (\Throwable) {
                }
                return '';
            },
            $content,
        );

        // Process other {{block type="..." ...}} directives — render via layout blocks
        $content = preg_replace_callback(
            '/\{\{block\s+type="([^"]+)"([^}]*)\}\}/i',
            function ($matches) use ($storeId) {
                $blockType = $matches[1];
                $attrString = $matches[2];

                // Parse key="value" attributes
                $attrs = [];
                if (preg_match_all('/(\w+)="([^"]*)"/', $attrString, $attrMatches, PREG_SET_ORDER)) {
                    foreach ($attrMatches as $m) {
                        $attrs[$m[1]] = $m[2];
                    }
                }

                try {
                    // Ensure design package is loaded so templates can be found
                    $design = \Mage::getSingleton('core/design_package');
                    if (!$design->getPackageName()) {
                        $design->setStore($storeId);
                    }

                    $layout = \Mage::app()->getLayout();
                    /** @var \Mage_Core_Block_Abstract $block */
                    $block = $layout->createBlock($blockType);
                    if (!$block) {
                        return '';
                    }

                    if (!empty($attrs['template'])) {
                        $block->setTemplate($attrs['template']);
                    }

                    foreach ($attrs as $key => $value) {
                        if ($key === 'template' || $key === 'type') {
                            continue;
                        }
                        $block->setData($key, $value);
                    }

                    return $block->toHtml();
                } catch (\Throwable) {
                }
                return '';
            },
            $content,
        );

        // Strip {{widget ...}} directives - they require full page context
        $content = preg_replace(
            '/\{\{widget[^}]*\}\}/i',
            '<!-- widget removed for API -->',
            $content,
        );

        return $content;
    }
}
