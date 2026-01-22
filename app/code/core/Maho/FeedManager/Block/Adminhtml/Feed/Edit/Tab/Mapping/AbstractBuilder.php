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
        $html = '';

        // Feed Attributes (computed/virtual fields) - at the top for easy access
        $html .= '<optgroup label="' . $this->__('Feed Attributes') . '">';
        $html .= '<option value="sku">SKU</option>';
        $html .= '<option value="entity_id">Product ID</option>';
        $html .= '<option value="type_id">Product Type</option>';
        $html .= '<option value="url">Product URL</option>';
        $html .= '<option value="is_in_stock">In Stock (0/1)</option>';
        $html .= '<option value="stock_status">Stock Status (0/1)</option>';
        $html .= '<option value="qty">Quantity</option>';
        $html .= '<option value="is_variant">Is Variant (has parent)</option>';
        $html .= '<option value="has_parent">Has Parent</option>';
        $html .= '<option value="parent_id">Parent ID</option>';
        $html .= '<option value="category_path">Category Path</option>';
        $html .= '<option value="category_names">Category Names</option>';
        $html .= '<option value="category_ids">Category IDs</option>';
        $html .= '</optgroup>';

        // Store Information (from config)
        $html .= '<optgroup label="' . $this->__('Store Information') . '">';
        $html .= '<option value="store_name">Store Name</option>';
        $html .= '<option value="store_url">Store URL</option>';
        $html .= '<option value="store_phone">Store Phone</option>';
        $html .= '<option value="store_email">Store Email</option>';
        $html .= '<option value="store_country">Store Country</option>';
        $html .= '<option value="store_currency">Store Currency</option>';
        $html .= '</optgroup>';

        // Images
        $html .= '<optgroup label="' . $this->__('Images') . '">';
        $html .= '<option value="image">Main Image URL</option>';
        $html .= '<option value="small_image">Small Image URL</option>';
        $html .= '<option value="thumbnail">Thumbnail URL</option>';
        $html .= '<option value="additional_images_csv">Additional Images (comma-separated)</option>';
        for ($i = 1; $i <= 10; $i++) {
            $html .= '<option value="image_' . $i . '">Gallery Image ' . $i . '</option>';
        }
        $html .= '</optgroup>';

        // Price attributes
        $html .= '<optgroup label="' . $this->__('Price') . '">';
        $html .= '<option value="price">Final Price</option>';
        $html .= '<option value="regular_price">Regular Price</option>';
        $html .= '<option value="special_price">Special Price</option>';
        $html .= '<option value="special_from_date">Special Price From Date</option>';
        $html .= '<option value="special_to_date">Special Price To Date</option>';
        $html .= '<option value="min_price">Minimal Price</option>';
        $html .= '<option value="max_price">Maximal Price</option>';
        $html .= '</optgroup>';

        // Product Attributes from EAV
        $html .= '<optgroup label="' . $this->__('Product Attributes') . '">';
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

        return $html;
    }

    /**
     * Get platform preset options for dropdown
     *
     * Filters platforms by the format supported by this builder
     */
    protected function _getPlatformPresetOptions(): string
    {
        $format = $this->_getBuilderFormat();
        $html = '';

        foreach (Maho_FeedManager_Model_Platform::getAvailablePlatforms() as $code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            if (!$adapter) {
                continue;
            }

            // Check if this platform supports the builder's format
            $supportedFormats = $adapter->getSupportedFormats();

            // JSONL is treated as JSON for builder purposes
            if ($format === 'json' && in_array('jsonl', $supportedFormats)) {
                $supportedFormats[] = 'json';
            }

            if (!in_array($format, $supportedFormats)) {
                continue;
            }

            $html .= '<option value="' . $code . '">' . $this->escapeHtml($adapter->getName()) . '</option>';
        }

        return $html;
    }

    /**
     * Get the format this builder handles
     */
    protected function _getBuilderFormat(): string
    {
        return 'csv'; // Default, override in subclasses
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
