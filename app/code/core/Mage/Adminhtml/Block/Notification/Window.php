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

class Mage_Adminhtml_Block_Notification_Window extends Mage_Adminhtml_Block_Notification_Toolbar
{
    /**
     * Is available flag
     *
     * @var bool|null
     */
    protected $_available = null;

    /**
     * Initialize block window
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();

        $this->setHeaderText($this->escapeHtml($this->__('Incoming Message')));
        $this->setCloseText($this->escapeHtml($this->__('close')));
        $this->setReadDetailsText($this->escapeHtml($this->__('Read details')));
        $this->setNoticeText($this->escapeHtml($this->__('NOTICE')));
        $this->setMinorText($this->escapeHtml($this->__('MINOR')));
        $this->setMajorText($this->escapeHtml($this->__('MAJOR')));
        $this->setCriticalText($this->escapeHtml($this->__('CRITICAL')));

        $this->setNoticeMessageText($this->escapeHtml($this->getLastNotice()->getTitle()));
        $this->setNoticeMessageUrl($this->escapeUrl($this->getLastNotice()->getUrl()));

        switch ($this->getLastNotice()->getSeverity()) {
            default:
            case Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE:
                $severity = 'SEVERITY_NOTICE';
                break;
            case Mage_AdminNotification_Model_Inbox::SEVERITY_MINOR:
                $severity = 'SEVERITY_MINOR';
                break;
            case Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR:
                $severity = 'SEVERITY_MAJOR';
                break;
            case Mage_AdminNotification_Model_Inbox::SEVERITY_CRITICAL:
                $severity = 'SEVERITY_CRITICAL';
                break;
        }

        $this->setNoticeSeverity($severity);
    }

    /**
     * Can we show notification window
     *
     * @return bool
     */
    public function canShow()
    {
        if (!is_null($this->_available)) {
            return $this->_available;
        }

        if (!Mage::getSingleton('admin/session')->isFirstPageAfterLogin()) {
            $this->_available = false;
            return false;
        }

        if (!$this->isModuleOutputEnabled('Mage_AdminNotification')) {
            $this->_available = false;
            return false;
        }

        if (!$this->_isAllowed()) {
            $this->_available = false;
            return false;
        }

        if (is_null($this->_available)) {
            $this->_available = $this->isShow();
        }
        return $this->_available;
    }

    /**
     * Retrieve Last Notice object
     *
     * @return Mage_AdminNotification_Model_Inbox
     */
    public function getLastNotice()
    {
        return $this->_getHelper()->getLatestNotice();
    }

    /**
     * Retrieve severity text
     *
     * @return string
     */
    public function getSeverityText()
    {
        return strtolower(str_replace('SEVERITY_', '', $this->getNoticeSeverity()));
    }

    /**
     * Check if current block allowed in ACL
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        if (!is_null($this->_aclResourcePath)) {
            return Mage::getSingleton('admin/session')
                ->isAllowed('admin/system/adminnotification/show_toolbar');
        }
        return true;
    }
}
