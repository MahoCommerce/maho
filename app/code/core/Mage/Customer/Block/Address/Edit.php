<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer address edit block
 *
 * @category   Mage
 * @package    Mage_Customer
 *
 * @method $this setBackUrl(string $value)
 * @method $this setErrorUrl(string $value)
 * @method $this setSuccessUrl(string $value)
 * @method $this setTitle(string $value)
 */
class Mage_Customer_Block_Address_Edit extends Mage_Core_Block_Template
{
    protected $_address;

    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->_address = Mage::getModel('customer/address');

        // Init address object
        if ($id = $this->getRequest()->getParam('id')) {
            $this->_address->load($id);
            if ($this->_address->getCustomerId() != Mage::getSingleton('customer/session')->getCustomerId()) {
                $this->_address->setData([]);
            }
        }

        if (!$this->_address->getId()) {
            $this->_address->setPrefix($this->getCustomer()->getPrefix())
                ->setFirstname($this->getCustomer()->getFirstname())
                ->setMiddlename($this->getCustomer()->getMiddlename())
                ->setLastname($this->getCustomer()->getLastname())
                ->setSuffix($this->getCustomer()->getSuffix());
        }

        if ($headBlock = $this->getLayout()->getBlock('head')) {
            $headBlock->setTitle($this->getTitle());
        }

        if ($postedData = Mage::getSingleton('customer/session')->getAddressFormData(true)) {
            $this->_address->addData($postedData);
        }
        return $this;
    }

    /**
     * Create form block for template file
     */
    #[\Override]
    protected function _beforeToHtml()
    {
        /** @var Mage_Customer_Model_Form $form */
        $form = Mage::getModel('customer/form');
        $form->setFormCode('customer_address_edit')
             ->setEntity($this->_address)
             ->initDefaultValues();

        /** @var Mage_Eav_Block_Widget_Form $block */
        $block = $this->getLayout()->createBlock('eav/widget_form');
        $block->setTranslationHelper($this->helper('customer'));
        $block->setForm($form);

        $groups = array_keys($block->getGroupedAttributes());
        if ($groups[0] === 'General') {
            $block->setDefaultLabel('Address Information');
        }
        $this->setChild('form_customer_address_edit', $block);

        return parent::_beforeToHtml();
    }

    /**
     * Generate name block html
     *
     * @return string
     * @deprecated
     */
    public function getNameBlockHtml()
    {
        $nameBlock = $this->getLayout()
            ->createBlock('customer/widget_name')
            ->setObject($this->getAddress());

        return $nameBlock->toHtml();
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        if ($title = $this->getData('title')) {
            return $title;
        }
        if ($this->getAddress()->getId()) {
            $title = Mage::helper('customer')->__('Edit Address');
        } else {
            $title = Mage::helper('customer')->__('Add New Address');
        }
        return $title;
    }

    /**
     * @return mixed|string
     */
    public function getBackUrl()
    {
        if ($this->getData('back_url')) {
            return $this->getData('back_url');
        }

        if ($this->getCustomerAddressCount()) {
            return $this->getUrl('customer/address');
        } else {
            return $this->getUrl('customer/account/');
        }
    }

    /**
     * @return string
     */
    public function getSaveUrl()
    {
        return Mage::getUrl('customer/address/formPost', ['_secure' => true, 'id' => $this->getAddress()->getId()]);
    }

    /**
     * @return Mage_Customer_Model_Address
     */
    public function getAddress()
    {
        return $this->_address;
    }

    /**
     * @return string
     * @deprecated
     */
    public function getCountryId()
    {
        if ($countryId = $this->getAddress()->getCountryId()) {
            return $countryId;
        }
        return Mage::helper('core')->getDefaultCountry();
    }

    /**
     * @return string
     * @deprecated
     */
    public function getCountryHtmlSelect()
    {
        return $this->getLayout()->createBlock('directory/data')
                    ->getCountryHtmlSelect($this->getCountryId());
    }

    /**
     * @return int
     * @deprecated
     */
    public function getRegionId()
    {
        return $this->getAddress()->getRegionId();
    }

    /**
     * @return int
     */
    public function getCustomerAddressCount()
    {
        return count(Mage::getSingleton('customer/session')->getCustomer()->getAddresses());
    }

    /**
     * @return bool|int
     */
    public function canSetAsDefaultBilling()
    {
        if (!$this->getAddress()->getId()) {
            return $this->getCustomerAddressCount();
        }
        return !$this->isDefaultBilling();
    }

    /**
     * @return bool|int
     */
    public function canSetAsDefaultShipping()
    {
        if (!$this->getAddress()->getId()) {
            return $this->getCustomerAddressCount();
        }
        return !$this->isDefaultShipping();
    }

    /**
     * @return bool
     */
    public function isDefaultBilling()
    {
        $defaultBilling = Mage::getSingleton('customer/session')->getCustomer()->getDefaultBilling();
        return $this->getAddress()->getId() && $this->getAddress()->getId() == $defaultBilling;
    }

    /**
     * @return bool
     */
    public function isDefaultShipping()
    {
        $defaultShipping = Mage::getSingleton('customer/session')->getCustomer()->getDefaultShipping();
        return $this->getAddress()->getId() && $this->getAddress()->getId() == $defaultShipping;
    }

    /**
     * @return Mage_Customer_Model_Customer
     */
    public function getCustomer()
    {
        return Mage::getSingleton('customer/session')->getCustomer();
    }

    /**
     * @return string
     */
    public function getBackButtonUrl()
    {
        if ($this->getCustomerAddressCount()) {
            return $this->getUrl('customer/address');
        } else {
            return $this->getUrl('customer/account/');
        }
    }
}
