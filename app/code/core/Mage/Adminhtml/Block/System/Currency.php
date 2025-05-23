<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Currency extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('system/currency/rates.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'save_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('adminhtml')->__('Save Currency Rates'),
                    'onclick'   => 'currencyForm.submit();',
                    'class'     => 'save',
                ]),
        );

        $this->setChild(
            'reset_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('adminhtml')->__('Reset'),
                    'onclick'   => 'document.location.reload()',
                    'class'     => 'reset',
                ]),
        );

        $this->setChild(
            'import_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('adminhtml')->__('Import'),
                    'class'     => 'add',
                    'type'      => 'submit',
                ]),
        );

        $this->setChild(
            'rates_matrix',
            $this->getLayout()->createBlock('adminhtml/system_currency_rate_matrix'),
        );

        $this->setChild(
            'import_services',
            $this->getLayout()->createBlock('adminhtml/system_currency_rate_services'),
        );

        return parent::_prepareLayout();
    }

    protected function getHeader()
    {
        return Mage::helper('adminhtml')->__('Manage Currency Rates');
    }

    protected function getSaveButtonHtml()
    {
        return $this->getChildHtml('save_button');
    }

    protected function getResetButtonHtml()
    {
        return $this->getChildHtml('reset_button');
    }

    protected function getImportButtonHtml()
    {
        return $this->getChildHtml('import_button');
    }

    protected function getServicesHtml()
    {
        return $this->getChildHtml('import_services');
    }

    protected function getRatesMatrixHtml()
    {
        return $this->getChildHtml('rates_matrix');
    }

    protected function getImportFormAction()
    {
        return $this->getUrl('*/*/fetchRates');
    }
}
