<?php

/**
 * Maho
 *
 * @package    Mage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Api_Model_Server_Adapter_Jsonrpc extends \Maho\DataObject implements Mage_Api_Model_Server_Adapter_Interface
{
    protected $_jsonRpc = null;

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
     * @return Mage_Api_Controller_Action|\Maho\DataObject
     */
    #[\Override]
    public function getController()
    {
        $controller = $this->getData('controller');

        if (null === $controller) {
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
     */
    #[\Override]
    public function run()
    {
        $this->_jsonRpc = new Laminas\Json\Server\Server();
        $this->_jsonRpc->setClass($this->getHandler());

        // Allow soap_v2 style request.
        $request = $this->_jsonRpc->getRequest();
        $method = $request->getMethod();
        if (!$this->_jsonRpc->getServiceMap()->getService($method)) {
            // Convert request to v1 style.
            $request->setMethod('call');
            $params = $request->getParams();
            $sessionId = $params[0] ?? null;
            unset($params[0]);
            $params = count($params)
                ? [$sessionId, $method, $params]
                : [$sessionId, $method];
            $request->setParams($params);
        }

        $this->getController()->getResponse()
            ->clearHeaders()
            ->setHeader('Content-Type', 'application/json; charset=utf8')
            ->setBody($this->_jsonRpc->handle());

        Mage::dispatchEvent('api_server_adapter_jsonrpc_run_after', [
            'method' => $method,
            'request' => $request,
            'response' => $this->_jsonRpc->getResponse(),
        ]);

        return $this;
    }

    /**
     * Dispatch webservice fault
     *
     * @param int $code
     * @param string $message
     * @throws Laminas\Json\Server\Exception\RuntimeException
     */
    #[\Override]
    public function fault($code, $message): never
    {
        throw new Laminas\Json\Server\Exception\RuntimeException($message, $code);
    }
}
