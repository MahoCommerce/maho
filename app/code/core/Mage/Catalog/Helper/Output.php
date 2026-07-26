<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Helper_Output extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Catalog';

    /**
     * Array of existing handlers
     *
     * @var array
     */
    protected $_handlers;

    /**
     * Template processor instance
     *
     * @var \Maho\Filter\Template
     */
    protected $_templateProcessor = null;

    public function __construct()
    {
        Mage::dispatchEvent('catalog_helper_output_construct', ['helper' => $this]);
    }

    /**
     * @return \Maho\Filter\Template
     */
    protected function _getTemplateProcessor()
    {
        if ($this->_templateProcessor === null) {
            $this->_templateProcessor = Mage::helper('catalog')->getPageTemplateProcessor();
        }

        return $this->_templateProcessor;
    }

    /**
     * Adding method handler
     *
     * @param   string $method
     * @param   object $handler
     * @return  Mage_Catalog_Helper_Output
     */
    public function addHandler($method, $handler)
    {
        if (!is_object($handler)) {
            return $this;
        }
        $method = strtolower($method);

        if (!isset($this->_handlers[$method])) {
            $this->_handlers[$method] = [];
        }

        $this->_handlers[$method][] = $handler;
        return $this;
    }

    /**
     * Get all handlers for some method
     *
     * @param   string $method
     * @return  array
     */
    public function getHandlers($method)
    {
        $method = strtolower($method);
        return $this->_handlers[$method] ?? [];
    }

    /**
     * Process all method handlers
     *
     * @param string $method
     * @param mixed $result
     * @param array $params
     * @return mixed
     */
    public function process($method, $result, $params)
    {
        foreach ($this->getHandlers($method) as $handler) {
            if (method_exists($handler, $method)) {
                $result = $handler->$method($this, $result, $params);
            }
        }
        return $result;
    }

    /**
     * Prepare product attribute html output
     *
     * @param   Mage_Catalog_Model_Product $product
     * @param   string $attributeHtml
     * @param   string $attributeName
     * @return  string
     */
    public function productAttribute($product, $attributeHtml, $attributeName)
    {
        /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
        $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $attributeName);
        if ($attribute && $attribute->getId() && ($attribute->getFrontendInput() != 'media_image')
            && (!$attribute->getIsHtmlAllowedOnFront() && !$attribute->getIsWysiwygEnabled())
        ) {
            if ($attribute->getFrontendInput() != 'price') {
                $attributeHtml = $this->escapeHtml($attributeHtml);
            }
            if ($attribute->getFrontendInput() == 'textarea') {
                // Only add <br> if we don't already have HTML
                if ($attributeHtml === strip_tags($attributeHtml)) {
                    $attributeHtml = nl2br($attributeHtml);
                }
            }
        }
        // Gated on WYSIWYG alone, unlike the escaping above: a WYSIWYG-enabled attribute is saved
        // with its directives preserved (Mage_Catalog_Model_Abstract::_sanitizeWysiwygAttributes),
        // so every attribute that can hold one has to either resolve it or remove it here. Keying
        // this off is_html_allowed_on_front as well would leave the two flags' mismatched
        // combination falling through all three branches, emitting the directive verbatim.
        if ($attribute->getIsWysiwygEnabled()) {
            if ($attribute->getIsHtmlAllowedOnFront() && Mage::helper('catalog')->isUrlDirectivesParsingAllowed()) {
                $attributeHtml = $this->_getTemplateProcessor()->filter($attributeHtml);
            } else {
                // Directive syntax is not valid HTML. Emitting an unresolved directive would let
                // its quotes close the enclosing attribute, turning whatever follows into a live
                // one — so when this store will not resolve them, remove them instead.
                $attributeHtml = Mage_Core_Model_Input_Filter_MaliciousCode::stripDirectives($attributeHtml);
            }
        }

        $attributeHtml = $this->process('productAttribute', $attributeHtml, [
            'product'   => $product,
            'attribute' => $attributeName,
        ]);

        return $attributeHtml;
    }

    /**
     * Prepare category attribute html output
     *
     * @param   Mage_Catalog_Model_Category $category
     * @param   string $attributeHtml
     * @param   string $attributeName
     * @return  string
     */
    public function categoryAttribute($category, $attributeHtml, $attributeName)
    {
        /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
        $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Category::ENTITY, $attributeName);

        if ($attribute && ($attribute->getFrontendInput() != 'image')
            && (!$attribute->getIsHtmlAllowedOnFront() && !$attribute->getIsWysiwygEnabled())
        ) {
            $attributeHtml = $this->escapeHtml($attributeHtml);
        }
        // Gated on WYSIWYG alone — see productAttribute() above.
        if ($attribute->getIsWysiwygEnabled()) {
            if ($attribute->getIsHtmlAllowedOnFront() && Mage::helper('catalog')->isUrlDirectivesParsingAllowed()) {
                $attributeHtml = $this->_getTemplateProcessor()->filter($attributeHtml);
            } else {
                // Directive syntax is not valid HTML. Emitting an unresolved directive would let
                // its quotes close the enclosing attribute, turning whatever follows into a live
                // one — so when this store will not resolve them, remove them instead.
                $attributeHtml = Mage_Core_Model_Input_Filter_MaliciousCode::stripDirectives($attributeHtml);
            }
        }
        $attributeHtml = $this->process('categoryAttribute', $attributeHtml, [
            'category'  => $category,
            'attribute' => $attributeName,
        ]);
        return $attributeHtml;
    }
}
