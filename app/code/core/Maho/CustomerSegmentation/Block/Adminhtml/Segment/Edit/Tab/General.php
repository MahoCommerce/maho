<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tab_General extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $model = Mage::registry('current_customer_segment');
        $form = new \Maho\Data\Form();
        $form->setUseContainer(false);

        $fieldset = $form->addFieldset('general_fieldset', [
            'legend' => Mage::helper('customersegmentation')->__('General Information'),
        ]);

        if ($model && $model->getId()) {
            $fieldset->addField('segment_id', 'hidden', [
                'name' => 'segment_id',
            ]);
        }

        $fieldset->addField('name', 'text', [
            'name'     => 'name',
            'label'    => Mage::helper('customersegmentation')->__('Segment Name'),
            'title'    => Mage::helper('customersegmentation')->__('Segment Name'),
            'required' => true,
        ]);

        $fieldset->addField('description', 'textarea', [
            'name'  => 'description',
            'label' => Mage::helper('customersegmentation')->__('Description'),
            'title' => Mage::helper('customersegmentation')->__('Description'),
        ]);

        $fieldset->addField('is_active', 'select', [
            'label'  => Mage::helper('customersegmentation')->__('Status'),
            'title'  => Mage::helper('customersegmentation')->__('Status'),
            'name'   => 'is_active',
            'values' => [
                ['value' => 1, 'label' => Mage::helper('customersegmentation')->__('Active')],
                ['value' => 0, 'label' => Mage::helper('customersegmentation')->__('Inactive')],
            ],
        ]);

        $fieldset->addField('refresh_mode', 'select', [
            'label'  => Mage::helper('customersegmentation')->__('Refresh Mode'),
            'title'  => Mage::helper('customersegmentation')->__('Refresh Mode'),
            'name'   => 'refresh_mode',
            'values' => Mage::getModel('customersegmentation/segment')->getRefreshModeOptions(),
            'note'   => Mage::helper('customersegmentation')->__('Automatic: Refreshed daily via cron. Manual: Must be refreshed manually.'),
        ]);

        if (!Mage::app()->isSingleStoreMode()) {
            $fieldset->addField('website_ids', 'multiselect', [
                'name'     => 'website_ids',
                'label'    => Mage::helper('customersegmentation')->__('Assign to Website'),
                'title'    => Mage::helper('customersegmentation')->__('Assign to Website'),
                'required' => true,
                'values'   => Mage::getSingleton('adminhtml/system_config_source_website')->toOptionArray(),
            ]);
        } else {
            $fieldset->addField('website_ids', 'hidden', [
                'name'  => 'website_ids',
                'value' => Mage::app()->getStore(true)->getWebsiteId(),
            ]);
        }

        $customerGroups = Mage::getResourceModel('customer/group_collection')->toOptionArray();
        array_unshift($customerGroups, ['value' => '', 'label' => Mage::helper('customersegmentation')->__('-- Please Select --')]);

        $fieldset->addField('customer_group_ids', 'multiselect', [
            'name'   => 'customer_group_ids',
            'label'  => Mage::helper('customersegmentation')->__('Customer Groups'),
            'title'  => Mage::helper('customersegmentation')->__('Customer Groups'),
            'values' => $customerGroups,
            'note'   => Mage::helper('customersegmentation')->__('Leave empty to apply to all customer groups'),
        ]);

        if ($model) {
            $form->setValues($model->getData());
        }
        $this->setForm($form);

        return parent::_prepareForm();
    }

    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('customersegmentation')->__('General Properties');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return Mage::helper('customersegmentation')->__('General Properties');
    }

    #[\Override]
    public function canShowTab(): bool
    {
        return true;
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }
}
