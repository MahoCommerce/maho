<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Custom Zend_Controller_Response_Http class (formally)
 *
 * @category   Mage
 * @package    Mage_Core
 */
class Mage_Core_Controller_Response_Http extends Zend_Controller_Response_Http
{
    /**
     * Transport object for observers to perform
     * @var Varien_Object
     */
    protected static $_transportObject = null;

    /**
     * Fixes CGI only one Status header allowed bug
     * @link  http://bugs.php.net/bug.php?id=36705
     */
    #[\Override]
    public function sendHeaders()
    {
        if (!$this->canSendHeaders()) {
            Mage::log('HEADERS ALREADY SENT: ' . mageDebugBacktrace(true, true, true));
            return $this;
        }

        if (substr(php_sapi_name(), 0, 3) == 'cgi') {
            $statusSent = false;
            foreach ($this->_headersRaw as $i => $header) {
                if (stripos($header, 'status:') === 0) {
                    if ($statusSent) {
                        unset($this->_headersRaw[$i]);
                    } else {
                        $statusSent = true;
                    }
                }
            }
            foreach ($this->_headers as $i => $header) {
                if (strcasecmp($header['name'], 'status') === 0) {
                    if ($statusSent) {
                        unset($this->_headers[$i]);
                    } else {
                        $statusSent = true;
                    }
                }
            }
        }

        return parent::sendHeaders();
    }

    #[\Override]
    public function sendResponse()
    {
        Mage::dispatchEvent('http_response_send_before', ['response' => $this]);
        parent::sendResponse();
    }

    /**
     * Additionally check for session messages in several domains case
     */
    #[\Override]
    public function setRedirect($url, $code = 302)
    {
        /**
         * Use single transport object instance
         */
        if (self::$_transportObject === null) {
            self::$_transportObject = new Varien_Object();
        }
        self::$_transportObject->setUrl($url);
        self::$_transportObject->setCode($code);
        Mage::dispatchEvent(
            'controller_response_redirect',
            ['response' => $this, 'transport' => self::$_transportObject],
        );

        return parent::setRedirect(self::$_transportObject->getUrl(), self::$_transportObject->getCode());
    }

    /**
     * Method send already collected headers and exit from script
     */
    public function sendHeadersAndExit(): never
    {
        $this->sendHeaders();
        exit;
    }

    /**
     * Prepare JSON formatted data for response to client
     */
    public function setBodyJson($response): self
    {
        $this->setHeader('Content-type', 'application/json', true);
        $this->setBody(Mage::helper('core')->jsonEncode($response));
        return $this;
    }
}
