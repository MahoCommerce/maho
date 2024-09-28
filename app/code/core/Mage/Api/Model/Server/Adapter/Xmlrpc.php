<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Webservice XmlRpc adapter
 *
 * @category   Mage
 * @package    Mage_Api
 */
class Mage_Api_Model_Server_Adapter_Xmlrpc extends Varien_Object implements Mage_Api_Model_Server_Adapter_Interface
{
    /**
     * XmlRpc Server
     *
     * @var Zend_XmlRpc_Server
     */
    protected $_xmlRpc = null;

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
     * Retrieve webservice api controller. If no controller have been set - emulate it by the use of Varien_Object
     *
     * @return Mage_Api_Controller_Action|Varien_Object
     */
    #[\Override]
    public function getController()
    {
        $controller = $this->getData('controller');

        if ($controller === null) {
            $controller = new Varien_Object(
                ['request' => Mage::app()->getRequest(), 'response' => Mage::app()->getResponse()]
            );

            $this->setData('controller', $controller);
        }
        return $controller;
    }

    /**
     * Run webservice
     *
     * @return $this
     */
    #[\Override]
    public function run()
    {
        $apiConfigCharset = Mage::getStoreConfig('api/config/charset');

        $this->_xmlRpc = new Zend_XmlRpc_Server();
        $this->_xmlRpc->setEncoding($apiConfigCharset)
            ->setClass($this->getHandler());
        $this->getController()->getResponse()
            ->clearHeaders()
            ->setHeader('Content-Type', 'text/xml; charset=' . $apiConfigCharset)
            ->setBody($this->_xmlRpc->handle());
        return $this;
    }

    /**
     * Dispatch webservice fault
     *
     * @param int $code
     * @param string $message
     */
    #[\Override]
    public function fault($code, $message): never
    {
        throw new Zend_XmlRpc_Server_Exception($message, $code);
    }
}
