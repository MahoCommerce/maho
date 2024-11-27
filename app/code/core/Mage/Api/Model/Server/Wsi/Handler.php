<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Webservices server handler WSI
 *
 * @category   Mage
 * @package    Mage_Api
 */
class Mage_Api_Model_Server_Wsi_Handler extends Mage_Api_Model_Server_Handler_Abstract
{
    protected $_resourceSuffix = '_v2';

    /**
     * Interceptor for all interfaces
     *
     * @param string $function
     * @param array $args
     * @return stdClass
     */
    public function __call($function, $args)
    {
        $args = $args[0];

        /** @var Mage_Api_Helper_Data $helper */
        $helper = Mage::helper('api/data');

        $helper->wsiArrayUnpacker($args);
        $args = get_object_vars($args);

        if (isset($args['sessionId'])) {
            $sessionId = $args['sessionId'];
            unset($args['sessionId']);
        } else {
            // Was left for backward compatibility.
            $sessionId = array_shift($args);
        }

        $apiKey = '';
        $nodes = Mage::getSingleton('api/config')->getNode('v2/resources_function_prefix')->children();
        foreach ($nodes as $resource => $prefix) {
            $prefix = $prefix->asArray();
            if (str_contains($function, $prefix)) {
                $method = substr($function, strlen($prefix));
                $apiKey = $resource . '.' . strtolower($method[0]) . substr($method, 1);
            }
        }

        list($modelName, $methodName) = $this->_getResourceName($apiKey);
        $methodParams = $this->getMethodParams($modelName, $methodName);

        $args = $this->prepareArgs($methodParams, $args);

        $res = $this->call($sessionId, $apiKey, $args);

        $obj = $helper->wsiArrayPacker($res);
        $stdObj = new stdClass();
        $stdObj->result = $obj;

        return $stdObj;
    }

    /**
     * Login user and Retrieve session id
     *
     * @param string $username
     * @param string|null $apiKey
     * @return stdClass
     */
    #[\Override]
    public function login(#[\SensitiveParameter] $username, #[\SensitiveParameter] $apiKey = null)
    {
        if (is_object($username)) {
            $apiKey = $username->apiKey;
            $username = $username->username;
        }

        $stdObject = new stdClass();
        $stdObject->result = parent::login($username, $apiKey);
        return $stdObject;
    }

    /**
     * Return called class and method names
     *
     * @param String $apiPath
     * @return array|void
     */
    protected function _getResourceName($apiPath)
    {
        list($resourceName, $methodName) = explode('.', $apiPath);

        if (empty($resourceName) || empty($methodName)) {
            $this->_fault('resource_path_invalid');
            return;
        }

        $resourcesAlias = $this->_getConfig()->getResourcesAlias();
        $resources      = $this->_getConfig()->getResources();
        if (isset($resourcesAlias->$resourceName)) {
            $resourceName = (string) $resourcesAlias->$resourceName;
        }

        $methodInfo = $resources->$resourceName->methods->$methodName;
        $modelName = $this->_prepareResourceModelName((string) $resources->$resourceName->model);
        $modelClass = Mage::getConfig()->getModelClassName($modelName);
        $method = (isset($methodInfo->method) ? (string) $methodInfo->method : $methodName);

        return [$modelClass, $method];
    }

    /**
     * Return an array of parameters for the callable method.
     *
     * @param String $modelName
     * @param String $methodName
     * @return array of ReflectionParameter
     */
    public function getMethodParams($modelName, $methodName)
    {
        $method = new ReflectionMethod($modelName, $methodName);

        return $method->getParameters();
    }

    /**
     * Prepares arguments for the method calling. Sort in correct order, set default values for omitted parameters.
     *
     * @param array $params
     * @param array $args
     * @return array
     */
    public function prepareArgs($params, $args)
    {
        $callArgs = [];

        /** @var ReflectionParameter $parameter */
        foreach ($params as $parameter) {
            $pName = $parameter->getName();
            if (isset($args[$pName])) {
                $callArgs[$pName] = $args[$pName];
            } else {
                if ($parameter->isOptional()) {
                    $callArgs[$pName] = $parameter->getDefaultValue();
                } else {
                    Mage::logException(new Exception("Required parameter \"$pName\" is missing.", 0));
                    $this->_fault('invalid_request_param');
                }
            }
        }
        return $callArgs;
    }

    /**
     * End web service session
     *
     * @param stdClass|string $sessionId
     * @return stdClass
     */
    #[\Override]
    public function endSession($sessionId)
    {
        $stdObject = new stdClass();
        $stdObject->result = parent::endSession($sessionId->sessionId);
        return $stdObject;
    }
}
