<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Filters extends Mage_Adminhtml_Block_Widget_Form
{
    use Maho_FeedManager_Block_Adminhtml_Feed_Edit_FeedRegistryTrait;

    #[\Override]
    protected function _prepareForm(): self
    {
        $feed = $this->_getFeed();

        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('feed_');

        // Common exclusions fieldset
        $exclusionsFieldset = $form->addFieldset('exclusions_fieldset', [
            'legend' => $this->__('Common Exclusions'),
        ]);

        $exclusionsFieldset->addField('exclude_disabled', 'select', [
            'name' => 'exclude_disabled',
            'label' => $this->__('Exclude Disabled Products'),
            'values' => [
                ['value' => 1, 'label' => $this->__('Yes')],
                ['value' => 0, 'label' => $this->__('No')],
            ],
            'value' => $feed->getData('exclude_disabled') ?? 1,
            'note' => $this->__('Exclude products with status "Disabled" from the feed.'),
        ]);

        $exclusionsFieldset->addField('exclude_out_of_stock', 'select', [
            'name' => 'exclude_out_of_stock',
            'label' => $this->__('Exclude Out of Stock Products'),
            'values' => [
                ['value' => 1, 'label' => $this->__('Yes')],
                ['value' => 0, 'label' => $this->__('No')],
            ],
            'value' => $feed->getData('exclude_out_of_stock') ?? 1,
            'note' => $this->__('Exclude products that are out of stock from the feed.'),
        ]);

        $exclusionsFieldset->addField('include_product_types', 'multiselect', [
            'name' => 'include_product_types',
            'label' => $this->__('Product Types'),
            'values' => $this->_getProductTypeOptionsForForm(),
            'value' => $feed->getData('include_product_types') ? explode(',', $feed->getData('include_product_types')) : ['simple'],
            'note' => $this->__('Select which product types to include in the feed. Leave empty for all types.'),
        ]);

        // Product Conditions fieldset with standard rules UX
        $renderer = Mage::getBlockSingleton('adminhtml/widget_form_renderer_fieldset')
            ->setTemplate('promo/fieldset.phtml')
            ->setNewChildUrl($this->getUrl('*/*/newConditionHtml', ['form' => 'feed_conditions_fieldset']));

        $conditionsFieldset = $form->addFieldset('conditions_fieldset', [
            'legend' => $this->__('Product Conditions (leave blank to include all products)'),
        ])->setRenderer($renderer);

        $conditionsFieldset->addField('conditions', 'text', [
            'name' => 'conditions',
            'label' => $this->__('Conditions'),
            'title' => $this->__('Conditions'),
        ])->setRule($feed)->setRenderer(Mage::getBlockSingleton('rule/conditions'));

        $this->setForm($form);
        return parent::_prepareForm();
    }

    /**
     * Get product type options formatted for form multiselect
     */
    protected function _getProductTypeOptionsForForm(): array
    {
        $types = [
            'simple' => $this->__('Simple Product'),
            'configurable' => $this->__('Configurable Product'),
            'grouped' => $this->__('Grouped Product'),
            'bundle' => $this->__('Bundle Product'),
            'virtual' => $this->__('Virtual Product'),
            'downloadable' => $this->__('Downloadable Product'),
        ];

        $options = [];
        foreach ($types as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }
}
