<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Currency_Rate_Services extends Mage_Adminhtml_Block_Template
{
    /**
     * Set import services template
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('system/currency/rate/services.phtml');
    }

    /**
     * Create import services form select element
     *
     * @return Mage_Core_Block_Abstract
     */
    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'import_services',
            $this->getLayout()->createBlock('adminhtml/html_select')
            ->setOptions(Mage::getModel('adminhtml/system_config_source_currency_service')->toOptionArray(0))
            ->setId('rate_services')
            ->setName('rate_services')
            ->setValue(Mage::getSingleton('adminhtml/session')->getCurrencyRateService(true))
            ->setTitle(Mage::helper('adminhtml')->__('Import Service')),
        );

        return parent::_prepareLayout();
    }
}
