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

class Mage_Adminhtml_Block_Notification_Curl extends Mage_Adminhtml_Block_Template
{
    /**
     * Required version of cURL.
     */
    public const REQUIRED_CURL_VERSION = '7.34.0';

    /**
     * Information about cURL version.
     *
     * @var array
     */
    protected $_curlVersion;

    public function __construct()
    {
        $this->_curlVersion = curl_version();
    }

    /**
     * Check cURL version and return true if system must show notification message.
     *
     * @return bool
     */
    protected function _canShow()
    {
        $result = false;
        if ($this->getRequest()->getParam('section') == 'payment'
            && !version_compare($this->_curlVersion['version'], $this::REQUIRED_CURL_VERSION, '>=')
        ) {
            $result = true;
        }

        return $result;
    }

    /**
     * Returns a message that should be displayed.
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->helper('adminhtml')->__(
            'Your current version of cURL php5 module is %s, which can prevent services that require TLS v1.2 from working correctly. It is recommended to update your cURL php5 module to version %s or higher.',
            $this->_curlVersion['version'],
            $this::REQUIRED_CURL_VERSION,
        );
    }

    /**
     * Prepare html output.
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!$this->_canShow()) {
            return '';
        }

        return parent::_toHtml();
    }
}
