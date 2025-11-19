<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Payment_Block_Info_Checkmo extends Mage_Payment_Block_Info
{
    protected $_payableTo;
    protected $_mailingAddress;

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payment/info/checkmo.phtml');
    }

    /**
     * @return string
     */
    public function getPayableTo()
    {
        if (is_null($this->_payableTo)) {
            $this->_convertAdditionalData();
        }
        return $this->_payableTo;
    }

    /**
     * @return string
     */
    public function getMailingAddress()
    {
        if (is_null($this->_mailingAddress)) {
            $this->_convertAdditionalData();
        }
        return $this->_mailingAddress;
    }

    /**
     * @return $this
     */
    protected function _convertAdditionalData()
    {
        $details = false;
        try {
            $details = Mage::helper('core/unserializeArray')
                ->unserialize($this->getInfo()->getAdditionalData());
        } catch (Exception $e) {
            Mage::logException($e);
        }
        if (is_array($details)) {
            $this->_payableTo = isset($details['payable_to']) ? (string) $details['payable_to'] : '';
            $this->_mailingAddress = isset($details['mailing_address']) ? (string) $details['mailing_address'] : '';
        } else {
            $this->_payableTo = '';
            $this->_mailingAddress = '';
        }
        return $this;
    }

    /**
     * @return string
     */
    #[\Override]
    public function toPdf()
    {
        $this->setTemplate('payment/info/pdf/checkmo.phtml');
        return $this->toHtml();
    }
}
