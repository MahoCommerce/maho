<?php

/**
 * Maho
 *
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Laminas\Soap\Server as LaminasSoapServer;
use Laminas\Soap\Exception\ExceptionInterface as LaminasSoapException;

class Mage_Api_Model_Server_Adapter_Soap extends \Maho\DataObject implements Mage_Api_Model_Server_Adapter_Interface
{
    /**
     * Wsdl config
     *
     * @var \Maho\DataObject
     */
    protected $wsdlConfig = null;

    /**
     * Soap server
     *
     * @var LaminasSoapServer
     */
    protected $_soap = null;

    /**
     * Internal constructor
     */
    #[\Override]
    protected function _construct()
    {
        $this->wsdlConfig = $this->_getWsdlConfig();
    }

    /**
     * Get wsdl config
     *
     * @return \Maho\DataObject
     */
    protected function _getWsdlConfig()
    {
        $wsdlConfig = new \Maho\DataObject();
        $queryParams = $this->getController()->getRequest()->getQuery();
        if (isset($queryParams['wsdl'])) {
            unset($queryParams['wsdl']);
        }

        $wsdlConfig->setUrl(Mage::helper('api')->getServiceUrl('*/*/*', ['_query' => $queryParams], true));
        $wsdlConfig->setName('Magento');
        $wsdlConfig->setHandler($this->getHandler());
        return $wsdlConfig;
    }

    /**
     * Set handler class name for webservice
     *
     * @param string $handler
     * @return $this
     */
    #[\Override]
    public function setHandler($handler)
    {
        $this->setData('handler', $handler);
        return $this;
    }

    /**
     * Retrieve handler class name for webservice
     *
     * @return string
     */
    #[\Override]
    public function getHandler()
    {
        return $this->getData('handler');
    }

    /**
     * Set webservice api controller
     *
     * @return $this
     */
    #[\Override]
    public function setController(Mage_Api_Controller_Action $controller)
    {
        $this->setData('controller', $controller);
        return $this;
    }

    /**
     * Retrieve webservice api controller. If no controller have been set - emulate it by the use of \Maho\DataObject
     *
     * @return \Maho\DataObject
     */
    #[\Override]
    public function getController()
    {
        $controller = $this->getData('controller');

        if ($controller === null) {
            $controller = new \Maho\DataObject(
                ['request' => Mage::app()->getRequest(), 'response' => Mage::app()->getResponse()],
            );

            $this->setData('controller', $controller);
        }
        return $controller;
    }

    /**
     * Run webservice
     *
     * @return $this
     * @throws SoapFault
     */
    #[\Override]
    public function run()
    {
        $apiConfigCharset = Mage::getStoreConfig('api/config/charset');

        if ($this->getController()->getRequest()->getParam('wsdl') !== null) {
            // Generating wsdl content from template
            $io = new \Maho\Io\File();
            $io->open(['path' => Mage::getModuleDir('etc', 'Mage_Api')]);

            $wsdlContent = $io->read('wsdl.xml');

            $template = Mage::getModel('core/email_template_filter');

            $template->setVariables(['wsdl' => $this->wsdlConfig]);

            $this->getController()->getResponse()
                ->clearHeaders()
                ->setHeader('Content-Type', 'text/xml; charset=' . $apiConfigCharset)
                ->setBody(
                    preg_replace(
                        '/<\?xml version="([^\"]+)"([^\>]+)>/i',
                        '<?xml version="$1" encoding="' . $apiConfigCharset . '"?>',
                        $template->filter($wsdlContent),
                    ),
                );
        } else {
            try {
                $this->_instantiateServer();

                $this->getController()->getResponse()
                    ->clearHeaders()
                    ->setHeader('Content-Type', 'text/xml; charset=' . $apiConfigCharset)
                    ->setBody(
                        preg_replace(
                            '/<\?xml version="([^\"]+)"([^\>]+)>/i',
                            '<?xml version="$1" encoding="' . $apiConfigCharset . '"?>',
                            $this->_soap->handle(),
                        ),
                    );
            } catch (LaminasSoapException $e) {
                $this->fault($e->getCode(), $e->getMessage());
            } catch (Exception $e) {
                $this->fault($e->getCode(), $e->getMessage());
            }
        }

        return $this;
    }

    /**
     * Dispatch webservice fault
     *
     * @param int $code
     * @param string $message
     */
    #[\Override]
    public function fault($code, $message)
    {
        if ($this->_extensionLoaded()) {
            throw new SoapFault($code, $message);
        }
        die('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
                <SOAP-ENV:Body>
                <SOAP-ENV:Fault>
                <faultcode>' . $code . '</faultcode>
                <faultstring>' . $message . '</faultstring>
                </SOAP-ENV:Fault>
                </SOAP-ENV:Body>
                </SOAP-ENV:Envelope>');
    }

    /**
     * Check whether Soap extension is loaded
     *
     * @return bool
     */
    protected function _extensionLoaded()
    {
        return class_exists('SoapServer', false);
    }

    /**
     * Transform wsdl url if $_SERVER["PHP_AUTH_USER"] is set
     *
     * @param array $params
     * @param bool $withAuth
     * @return string
     */
    protected function getWsdlUrl($params = null, $withAuth = true)
    {
        $urlModel = Mage::getModel('core/url')
            ->setUseSession(false);

        $wsdlUrl = $params !== null
            ? Mage::helper('api')->getServiceUrl('*/*/*', ['_current' => true, '_query' => $params])
            : Mage::helper('api')->getServiceUrl('*/*/*');

        if ($withAuth) {
            $phpAuthUser = rawurlencode($this->getController()->getRequest()->getServer('PHP_AUTH_USER', false));
            $phpAuthPw = rawurlencode($this->getController()->getRequest()->getServer('PHP_AUTH_PW', false));
            $scheme = rawurlencode($this->getController()->getRequest()->getScheme());

            if ($phpAuthUser && $phpAuthPw) {
                $wsdlUrl = sprintf(
                    '%s://%s:%s@%s',
                    $scheme,
                    $phpAuthUser,
                    $phpAuthPw,
                    str_replace($scheme . '://', '', $wsdlUrl),
                );
            }
        }

        return $wsdlUrl;
    }

    /**
     * Try to instantiate Laminas Soap Server
     * If schema import error is caught, it will retry in 1 second.
     *
     * @throws SoapFault
     */
    protected function _instantiateServer()
    {
        $apiConfigCharset = Mage::getStoreConfig('api/config/charset');
        $wsdlCacheEnabled = Mage::getStoreConfigFlag('api/config/wsdl_cache_enabled');

        if ($wsdlCacheEnabled) {
            ini_set('soap.wsdl_cache_enabled', '1');
        } else {
            ini_set('soap.wsdl_cache_enabled', '0');
        }

        // Disable external schema imports to prevent requests to schemas.xmlsoap.org
        ini_set('soap.wsdl_cache_import', '0');
        libxml_use_internal_errors(true);

        $this->_soap = new LaminasSoapServer(
            $this->getWsdlUrl(['wsdl' => 1]),
            ['encoding' => $apiConfigCharset],
        );
        use_soap_error_handler(false);
        $this->_soap
            ->setReturnResponse(true)
            ->setClass($this->getHandler());
    }
}
