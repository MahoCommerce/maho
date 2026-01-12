<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Abstract base class for format-specific builder blocks (CSV, JSON, XML)
 *
 * Provides shared functionality for attribute options, platform presets, and feed access.
 */
abstract class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Mapping_AbstractBuilder extends Mage_Adminhtml_Block_Abstract
{
    use Maho_FeedManager_Block_Adminhtml_Feed_Edit_FeedRegistryTrait;

    /**
     * Get the builder HTML for this format
     */
    abstract public function getBuilderHtml(): string;

    /**
     * Get product attribute options for editor dropdown
     */
    protected function _getProductAttributeOptionsForEditor(): string
    {
        $html = '<optgroup label="' . $this->__('Product Attributes') . '">';

        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addVisibleFilter()
            ->setOrder('frontend_label', 'ASC');

        foreach ($attributes as $attribute) {
            $label = $attribute->getFrontendLabel();
            $code = $attribute->getAttributeCode();
            if ($label) {
                $html .= '<option value="' . htmlspecialchars($code) . '">' . htmlspecialchars($label) . '</option>';
            }
        }
        $html .= '</optgroup>';

        // Price attributes
        $html .= '<optgroup label="' . $this->__('Price') . '">';
        $html .= '<option value="price">Price</option>';
        $html .= '<option value="special_price">Special Price</option>';
        $html .= '<option value="final_price">Final Price</option>';
        $html .= '<option value="min_price">Minimal Price</option>';
        $html .= '<option value="max_price">Maximal Price</option>';
        $html .= '</optgroup>';

        // Other attributes
        $html .= '<optgroup label="' . $this->__('Other Attributes') . '">';
        $html .= '<option value="sku">SKU</option>';
        $html .= '<option value="url">URL</option>';
        $html .= '<option value="image">Base Image</option>';
        $html .= '<option value="category">Category</option>';
        $html .= '<option value="category_id">Category ID</option>';
        $html .= '<option value="is_in_stock">In Stock</option>';
        $html .= '<option value="qty">Qty</option>';
        $html .= '<option value="parent_id">Parent ID</option>';
        $html .= '<option value="entity_id">Product ID</option>';
        $html .= '<option value="type_id">Type ID</option>';
        $html .= '</optgroup>';

        // Images
        $html .= '<optgroup label="' . $this->__('Images') . '">';
        for ($i = 1; $i <= 10; $i++) {
            $html .= '<option value="image_' . $i . '">Image ' . $i . '</option>';
        }
        $html .= '</optgroup>';

        return $html;
    }

    /**
     * Get platform preset options for dropdown
     */
    protected function _getPlatformPresetOptions(): string
    {
        $platforms = [
            'google' => 'Google Shopping',
            'facebook' => 'Facebook/Meta',
            'custom' => 'Custom',
        ];

        $html = '';
        foreach ($platforms as $code => $label) {
            $html .= '<option value="' . $code . '">' . $this->escapeHtml($label) . '</option>';
        }
        return $html;
    }

    /**
     * Get dynamic rule options for dropdown (HTML)
     */
    protected function _getDynamicRuleOptionsHtml(): string
    {
        $collection = Mage::getResourceModel('feedmanager/dynamicRule_collection')
            ->addEnabledFilter()
            ->setOrder('sort_order', 'ASC');

        $html = '<option value="">' . $this->__('-- Select Rule --') . '</option>';
        foreach ($collection as $rule) {
            $html .= '<option value="' . htmlspecialchars($rule->getCode()) . '">'
                   . htmlspecialchars($rule->getName())
                   . '</option>';
        }
        return $html;
    }

    /**
     * Get dynamic rule options as array for JavaScript
     */
    protected function _getDynamicRuleOptionsArray(): array
    {
        $collection = Mage::getResourceModel('feedmanager/dynamicRule_collection')
            ->addEnabledFilter()
            ->setOrder('sort_order', 'ASC');

        $options = [];
        foreach ($collection as $rule) {
            $options[$rule->getCode()] = $rule->getName();
        }
        return $options;
    }
}
