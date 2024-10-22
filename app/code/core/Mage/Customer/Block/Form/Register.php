<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer register form block
 *
 * @category   Mage
 * @package    Mage_Customer
 *
 * @method $this setBackUrl(string $value)
 * @method $this setErrorUrl(string $value)
 * @method $this setShowAddressFields(bool $value)
 * @method $this setSuccessUrl(string $value)
 */
class Mage_Customer_Block_Form_Register extends Mage_Core_Block_Template
{
    /**
     * Address instance with data
     *
     * @var Mage_Customer_Model_Address|null
     */
    protected $_address;

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function _prepareLayout()
    {
        $this->getLayout()->getBlock('head')->setTitle(Mage::helper('customer')->__('Create New Customer Account'));
        return parent::_prepareLayout();
    }

    /**
     * Create form block for template file
     */
    #[\Override]
    protected function _beforeToHtml()
    {
        /** @var Mage_Customer_Model_Form $form */
        $form = Mage::getModel('customer/form');
        $form->setFormCode('customer_account_create')
             ->setEntity(Mage::getModel('customer/customer'))
             ->initDefaultValues();

        $this->restoreSessionData($form);

        /** @var Mage_Eav_Block_Widget_Form $block */
        $block = $this->getLayout()->createBlock('eav/widget_form');
        $block->setTranslationHelper($this->helper('customer'));
        $block->setForm($form);

        if ($this->getShowAddressFields()) {
            /** @var Mage_Customer_Model_Form $form */
            $addressForm = Mage::getModel('customer/form');
            $addressForm->setFormCode('customer_address_edit')
                        ->setEntity($this->getAddress())
                        ->initDefaultValues();

            $this->restoreSessionData($addressForm);

            $block->mergeFormAttributes($addressForm);
        }

        $groups = array_keys($block->getGroupedAttributes());
        if ($groups[0] === 'General') {
            $block->setDefaultLabel('Account Information');
        }
        $this->setChild('form_customer_account_create', $block);

        return parent::_beforeToHtml();
    }

    /**
     * Retrieve form posting url
     *
     * @return string
     */
    public function getPostActionUrl()
    {
        /** @var Mage_Customer_Helper_Data $helper */
        $helper = $this->helper('customer');
        return $helper->getRegisterPostUrl();
    }

    /**
     * Retrieve back url
     *
     * @return string
     */
    public function getBackUrl()
    {
        $url = $this->getData('back_url');
        if (is_null($url)) {
            /** @var Mage_Customer_Helper_Data $helper */
            $helper = $this->helper('customer');
            $url = $helper->getLoginUrl();
        }
        return $url;
    }

    /**
     * Retrieve form data
     *
     * @return Varien_Object
     */
    public function getFormData()
    {
        $data = $this->getData('form_data');
        if (is_null($data)) {
            $formData = Mage::getSingleton('customer/session')->getCustomerFormData(true);
            $data = new Varien_Object();
            if ($formData) {
                $data->addData($formData);
                $data->setCustomerData(1);
            }
            if (isset($data['region_id'])) {
                $data['region_id'] = (int)$data['region_id'];
            }
            if ($data->getDob()) {
                $dob = $data->getYear() . '-' . $data->getMonth() . '-' . $data->getDay();
                $data->setDob($dob);
            }
            $this->setData('form_data', $data);
        }
        return $data;
    }

    /**
     * Restore entity data from session
     * Entity and form code must be defined for the form
     *
     * @param string|null $scope
     * @return $this
     */
    public function restoreSessionData(Mage_Customer_Model_Form $form, $scope = null)
    {
        if ($this->getFormData()->getCustomerData()) {
            $request = $form->prepareRequest($this->getFormData()->getData());
            $data    = $form->extractData($request, $scope, false);
            $form->restoreData($data);
        }

        return $this;
    }

    /**
     * Return customer address instance
     *
     * @return Mage_Customer_Model_Address
     */
    public function getAddress()
    {
        if (is_null($this->_address)) {
            $this->_address = Mage::getModel('customer/address');
        }

        return $this->_address;
    }

    /**
     * @return string
     * @deprecated
     */
    public function getCountryId()
    {
        if ($countryId = $this->getFormData()->getCountryId()) {
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
     * @return string|int|null
     * @deprecated
     */
    public function getRegion()
    {
        if (($region = $this->getFormData()->getRegion()) !== false) {
            return $region;
        }
        return null;
    }

    /**
     *  Newsletter module availability
     *
     *  @return bool
     */
    public function isNewsletterEnabled()
    {
        return Mage::helper('core')->isModuleOutputEnabled('Mage_Newsletter');
    }

    /**
     * Retrieve minimum length of customer password
     *
     * @return int
     */
    public function getMinPasswordLength()
    {
        return Mage::getModel('customer/customer')->getMinPasswordLength();
    }
}
