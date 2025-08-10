<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Validator for custom layout update
 * Validator checked XML validation and protected expressions
 */
class Mage_Core_Model_Layout_Validator
{
    public const XML_INVALID                             = 'invalidXml';
    public const INVALID_TEMPLATE_PATH                   = 'invalidTemplatePath';
    public const INVALID_BLOCK_NAME                      = 'invalidBlockName';
    public const PROTECTED_ATTR_HELPER_IN_TAG_ACTION_VAR = 'protectedAttrHelperInActionVar';
    public const INVALID_XML_OBJECT_EXCEPTION            = 'invalidXmlObject';

    /**
     * XPath expression for checking layout update
     *
     * @var array
     */
    protected $_disallowedXPathExpressions = [
        '*//template',
        '*//@template',
        '//*[@method=\'setTemplate\']',
        '//*[@method=\'setDataUsingMethod\']//*[contains(translate(text(),
        \'ABCDEFGHIJKLMNOPQRSTUVWXYZ\', \'abcdefghijklmnopqrstuvwxyz\'), \'template\')]/../*',
    ];

    /**
     * Protected expressions
     *
     * @var array
     */
    protected $_protectedExpressions = [
        self::PROTECTED_ATTR_HELPER_IN_TAG_ACTION_VAR => '//action/*[@helper]',
    ];

    /**
     * Disallowed block names
     *
     * @var array
     */
    protected $_disallowedBlocks = [];

    /**
     * @var array
     */
    protected $_messageTemplates = [];

    /**
     * @var array
     */
    protected $messages = [];

    public function __construct()
    {
        $this->_initMessageTemplates();
        $this->getDisallowedBlocks();
    }

    /**
     * Initialize messages templates with translating
     *
     * @return $this
     */
    protected function _initMessageTemplates()
    {
        if (!$this->_messageTemplates) {
            $this->_messageTemplates = [
                self::PROTECTED_ATTR_HELPER_IN_TAG_ACTION_VAR =>
                    Mage::helper('core')->__('Helper attributes should not be used in custom layout updates.'),
                self::XML_INVALID => Mage::helper('core')->__('XML data is invalid.'),
                self::INVALID_TEMPLATE_PATH => Mage::helper('core')->__(
                    'Invalid template path used in layout update.',
                ),
                self::INVALID_BLOCK_NAME => Mage::helper('core')->__('Disallowed block name for frontend.'),
                self::INVALID_XML_OBJECT_EXCEPTION =>
                    Mage::helper('core')->__('XML object is not instance of "Varien_Simplexml_Element".'),
            ];
        }
        return $this;
    }

    /**
     * Returns array of errors
     *
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * Returns true if and only if $value meets the validation requirements
     *
     * @param mixed $value
     * @return bool
     */
    public function isValid($value)
    {
        $this->messages = [];

        if (is_string($value)) {
            $value = trim($value);
            try {
                $value = simplexml_load_string('<config>' . $value . '</config>', 'Varien_Simplexml_Element');
            } catch (Exception $e) {
                $this->_error(self::XML_INVALID);
                return false;
            }
        } elseif (!($value instanceof Varien_Simplexml_Element)) {
            $this->_error(self::INVALID_XML_OBJECT_EXCEPTION);
            return false;
        }

        // if layout update declare custom templates then validate their paths
        if ($templatePaths = $value->xpath('//*[@template]')) {
            try {
                $this->_validateTemplatePath($templatePaths);
            } catch (Exception $e) {
                $this->_error(self::INVALID_TEMPLATE_PATH);
                return false;
            }
        }

        // XPath expressions
        $xpathValidationExpression = $this->_getXpathValidationExpression();
        if ($xpathValidationExpression && $value->xpath($xpathValidationExpression)) {
            $this->_error(self::INVALID_TEMPLATE_PATH);
            return false;
        }

        // Protected expressions
        foreach ($this->_protectedExpressions as $key => $xpr) {
            if ($value->xpath($xpr)) {
                $this->_error($key);
                return false;
            }
        }

        // Disallowed blocks
        $xpathBlockValidationExpression = $this->_getXpathBlockValidationExpression();
        if ($xpathBlockValidationExpression && $value->xpath($xpathBlockValidationExpression)) {
            $this->_error(self::INVALID_BLOCK_NAME);
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    public function getDisallowedBlocks()
    {
        if (!count($this->_disallowedBlocks)) {
            $disallowedBlockConfig = Mage::getStoreConfig('validators/custom_layout/disallowed_block');
            if (is_array($disallowedBlockConfig)) {
                foreach (array_keys($disallowedBlockConfig) as $blockName) {
                    $this->_disallowedBlocks[] = $blockName;
                }
            }
        }
        return $this->_disallowedBlocks;
    }

    /**
     * @return array
     */
    public function getProtectedExpressions()
    {
        return $this->_protectedExpressions;
    }

    /**
     * @param array $templatePaths
     * @return void
     */
    public function validateTemplatePath($templatePaths)
    {
        $this->_validateTemplatePath($templatePaths);
    }

    /**
     * @param array $templatePaths
     * @return void
     * @throws Exception
     */
    protected function _validateTemplatePath($templatePaths)
    {
        /** @var Varien_Simplexml_Element $path */
        foreach ($templatePaths as $path) {
            $path = $path->hasChildren()
                ? stripcslashes(trim((string) $path->children(), '"'))
                : (string) $path;
            if (str_contains($path, '..' . DS)) {
                throw new Exception();
            }
        }
    }

    /**
     * @return array
     */
    public function getDisallowedXpathValidationExpression()
    {
        return $this->_disallowedXPathExpressions;
    }

    /**
     * @return string
     */
    protected function _getXpathValidationExpression()
    {
        return implode(' | ', $this->_disallowedXPathExpressions);
    }

    /**
     * Set messages for validator
     *
     * @param array $messages
     * @return $this
     */
    public function setMessages($messages)
    {
        // Merge any custom messages with existing templates
        if (is_array($messages)) {
            $this->_messageTemplates = array_merge($this->_messageTemplates, $messages);
        }
        return $this;
    }

    /**
     * Get XPath validation expression
     *
     * @return string
     */
    public function getXpathValidationExpression()
    {
        return $this->_getXpathValidationExpression();
    }

    /**
     * Get XPath block validation expression
     *
     * @return string
     */
    public function getXpathBlockValidationExpression()
    {
        return $this->_getXpathBlockValidationExpression();
    }

    /**
     * @return string
     */
    protected function _getXpathBlockValidationExpression()
    {
        $disallowedBlocks = $this->getDisallowedBlocks();
        if (!count($disallowedBlocks)) {
            return '';
        }

        $expression = '';
        foreach ($disallowedBlocks as $key => $value) {
            if ($key > 0) {
                $expression .= ' | ';
            }
            $expression .= "//block[translate(@type, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = ";
            $expression .= "translate('$value', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')]";
        }
        return $expression;
    }

    /**
     * Add error message
     *
     * @param string $messageKey
     * @return void
     */
    protected function _error($messageKey)
    {
        if (isset($this->_messageTemplates[$messageKey])) {
            $this->messages[] = $this->_messageTemplates[$messageKey];
        }
    }
}
