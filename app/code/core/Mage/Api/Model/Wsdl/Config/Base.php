<?php

/**
 * Maho
 *
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Api_Model_Wsdl_Config_Base extends \Maho\Simplexml\Config
{
    protected $_handler = '';

    /**
     * @var \Maho\DataObject
     */
    protected $_wsdlVariables = null;

    protected $_loadedFiles = [];

    public function __construct($sourceData = null)
    {
        $this->_elementClass = 'Mage_Api_Model_Wsdl_Config_Element';

        // remove wsdl parameter from query
        $queryParams = Mage::app()->getRequest()->getQuery();
        unset($queryParams['wsdl']);

        // set up default WSDL template variables
        $this->_wsdlVariables = new \Maho\DataObject(
            [
                'name' => 'Maho',
                'url'  => Mage::helper('api')->getServiceUrl('*/*/*', ['_query' => $queryParams], true),
            ],
        );
        parent::__construct($sourceData);
    }

    /**
     * Set handler
     *
     * @param string $handler
     * @return $this
     */
    public function setHandler($handler)
    {
        $this->_handler = $handler;
        return $this;
    }

    /**
     * Get handler
     *
     * @return string
     */
    public function getHandler()
    {
        return $this->_handler;
    }

    /**
     * Processing file data
     *
     * @param string $text
     * @return string
     */
    #[\Override]
    public function processFileData($text)
    {
        /** @var Mage_Core_Model_Email_Template_Filter $template */
        $template = Mage::getModel('core/email_template_filter');

        $this->_wsdlVariables->setHandler($this->getHandler());

        $template->setVariables(['wsdl' => $this->_wsdlVariables]);

        return $template->filter($text);
    }

    /**
     * @param string $file
     * @return $this
     */
    public function addLoadedFile($file)
    {
        if (!in_array($file, $this->_loadedFiles)) {
            $this->_loadedFiles[] = $file;
        }
        return $this;
    }

    /**
     * @param string $file
     * @return $this|false
     */
    #[\Override]
    public function loadFile($file)
    {
        if (in_array($file, $this->_loadedFiles)) {
            return false;
        }
        $res = parent::loadFile($file);
        if ($res) {
            $this->addLoadedFile($file);
        }
        return $this;
    }

    /**
     * Set variable to be used in WSDL template processing
     *
     * @param string $key Variable key
     * @param string $value Variable value
     * @return $this
     */
    public function setWsdlVariable($key, $value)
    {
        $this->_wsdlVariables->setData($key, $value);

        return $this;
    }
}
