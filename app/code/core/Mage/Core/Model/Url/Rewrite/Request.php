<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Url_Rewrite_Request
{
    /**
     * Instance of request
     *
     * @var Mage_Core_Controller_Request_Http
     */
    protected $_request;

    /**
     * Instance of core config model
     *
     * @var Mage_Core_Model_Config
     */
    protected $_config;

    /**
     * Collection of front controller's routers
     *
     * @var array
     */
    protected $_routers = [];

    /**
     * Instance of url rewrite model
     *
     * @var Mage_Core_Model_Url_Rewrite
     */
    protected $_rewrite;

    /**
     * Application
     *
     * @var Mage_Core_Model_App
     */
    protected $_app;

    /**
     * Mage Factory model
     *
     * @var Mage_Core_Model_Factory
     */
    protected $_factory;

    /**
     * Constructor
     * Arguments:
     *   request  - Mage_Core_Controller_Request_Http
     *   config   - Mage_Core_Model_Config
     *   factory  - Mage_Core_Model_Factory
     *   routers  - array
     */
    public function __construct(array $args)
    {
        $this->_factory = empty($args['factory']) ? Mage::getModel('core/factory') : $args['factory'];
        $this->_app     = empty($args['app']) ? Mage::app() : $args['app'];
        $this->_config  = empty($args['config']) ? Mage::getConfig() : $args['config'];
        $this->_request = empty($args['request'])
            ? Mage::app()->getFrontController()->getRequest() : $args['request'];
        $this->_rewrite = empty($args['rewrite'])
            ? $this->_factory->getModel('core/url_rewrite') : $args['rewrite'];

        if (!empty($args['routers'])) {
            $this->_routers = $args['routers'];
        }
    }

    /**
     * @deprecated Use middleware pipeline instead. This class is retained for backward compatibility.
     * @return bool
     */
    public function rewrite()
    {
        return true;
    }
}
