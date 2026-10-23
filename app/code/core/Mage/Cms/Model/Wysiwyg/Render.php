<?php

/**
 * Render the widget, block and icon directives of the content editor as the storefront of a
 * store view shows them, with the stylesheets of its theme.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

class Mage_Cms_Model_Wysiwyg_Render
{
    /**
     * The directive types that this model renders, with the ACL resource that each one needs.
     * An icon reads only the icon library, so it needs no resource.
     *
     * @var array<string, ?string>
     */
    public const TYPES = [
        'widget' => 'cms/widget_instance',
        'block' => 'cms/block',
        'icon' => null,
    ];

    public const MAX_DIRECTIVES = 50;
    public const MAX_LENGTH = 4096;

    /**
     * The type of $directive when it is one directive of a type in TYPES and nothing more,
     * else null.
     */
    public function getDirectiveType(string $directive): ?string
    {
        if (strlen($directive) > self::MAX_LENGTH
            || preg_match_all(\Maho\Filter\Template::CONSTRUCTION_PATTERN, $directive, $matches) !== 1
            || $matches[0][0] !== $directive
        ) {
            return null;
        }
        $type = strtolower($matches[1][0]);
        return array_key_exists($type, self::TYPES) ? $type : null;
    }

    /**
     * Render each directive in the store view $storeId. A directive that fails gives an empty
     * string, and the exception goes to the log.
     *
     * @param list<string> $directives
     * @return array{head: string, html: array<string, string>}
     */
    public function render(array $directives, int $storeId): array
    {
        // A block cached here holds links to the admin URL of this request, such as the page
        // links of a widget pager, and the storefront would serve them
        return Mage::app()->getCache()->runWithout(
            Mage_Core_Block_Abstract::CACHE_GROUP,
            fn(): array => $this->renderInStore($directives, $storeId),
        );
    }

    /**
     * The stylesheets of the store view $storeId, the URL of the css/styles.css among them, and the
     * Tailwind browser build of its theme. `tailwind` is empty when no Tailwind theme compiles the
     * stylesheet of the store view.
     *
     * @return array{head: string, styles: string, tailwind: string}
     */
    public function renderStyles(int $storeId): array
    {
        return Mage::app()->getCache()->runWithout(
            Mage_Core_Block_Abstract::CACHE_GROUP,
            function () use ($storeId): array {
                $emulation = Mage::getSingleton('core/app_emulation');
                $environment = $emulation->startEnvironmentEmulation($storeId);
                try {
                    $design = Mage::getDesign();
                    $stylesFile = (string) $design->getFilename(Mage_Core_Model_Design_Content_Bundle::STYLES_FILE, ['_type' => 'skin']);
                    return [
                        'head' => $this->getHeadHtml(),
                        'styles' => Mage::getSingleton('core/design_content_bundle')->getUrl($stylesFile)
                            ?? $design->getSkinUrl(Mage_Core_Model_Design_Content_Bundle::STYLES_FILE),
                        'tailwind' => Mage::app()->getLayout()->createBlock('cms/preview_tailwind')->toHtml(),
                    ];
                } finally {
                    $emulation->stopEnvironmentEmulation($environment);
                }
            },
        );
    }

    /**
     * @param list<string> $directives
     * @return array{head: string, html: array<string, string>}
     */
    protected function renderInStore(array $directives, int $storeId): array
    {
        $emulation = Mage::getSingleton('core/app_emulation');
        $environment = $emulation->startEnvironmentEmulation($storeId);
        try {
            $processor = Mage::helper('cms')->getPageTemplateProcessor();
            $html = array_fill_keys($directives, '');
            if (!$processor instanceof Mage_Core_Model_Email_Template_Filter) {
                return ['head' => $this->getHeadHtml(), 'html' => $html];
            }
            $processor->setStoreId($storeId);
            foreach ($directives as $directive) {
                try {
                    $html[$directive] = (string) $processor->filter($directive);
                } catch (Exception $e) {
                    Mage::logException($e);
                    $html[$directive] = '';
                }
            }
            return ['head' => $this->getHeadHtml(), 'html' => $html];
        } finally {
            $emulation->stopEnvironmentEmulation($environment);
        }
    }

    /**
     * The stylesheets of the current store view. Only the head block and its actions from the
     * default layout handle run, so no other storefront block is created.
     */
    protected function getHeadHtml(): string
    {
        $source = Mage::getModel('core/layout', ['area' => Mage_Core_Model_App_Area::AREA_FRONTEND]);
        $source->getUpdate()->addHandle('default')->load();

        $xml = new Mage_Core_Model_Layout_Element('<layout/>');
        foreach ($source->getUpdate()->asSimplexml()->xpath('//block[@name="head"] | //reference[@name="head"]') ?: [] as $node) {
            $copy = $xml->addChild($node->getName());
            foreach ($node->attributes() as $name => $value) {
                $copy->addAttribute($name, (string) $value);
            }
            foreach ($node->xpath('action') ?: [] as $action) {
                if ($action instanceof \Maho\Simplexml\Element) {
                    $copy->appendChild($action);
                }
            }
        }

        $layout = Mage::getModel('core/layout', ['area' => Mage_Core_Model_App_Area::AREA_FRONTEND]);
        $layout->setXml($xml);
        $layout->generateBlocks();
        $head = $layout->getBlock('head');

        return $head instanceof Mage_Page_Block_Html_Head ? $head->getStylesheetHtml() : '';
    }
}
