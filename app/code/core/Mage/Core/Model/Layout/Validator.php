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

class Mage_Core_Model_Layout_Validator
{
    public string $invalidXmlMessage;
    public string $invalidTemplatePathMessage;
    public string $invalidBlockNameMessage;
    public string $protectedHelperMessage;
    public string $invalidXmlObjectMessage;

    public array $disallowedBlocks = [];
    public array $protectedExpressions = [];
    public array $disallowedXPathExpressions = [];

    private array $_messages = [];

    public function __construct(
        ?array $disallowedBlocks = null,
        ?array $protectedExpressions = null,
        ?array $disallowedXPathExpressions = null,
        ?string $invalidXmlMessage = null,
        ?string $invalidTemplatePathMessage = null,
        ?string $invalidBlockNameMessage = null,
        ?string $protectedHelperMessage = null,
        ?string $invalidXmlObjectMessage = null,
    ) {
        $this->disallowedBlocks = $disallowedBlocks ?? $this->_getDefaultDisallowedBlocks();
        $this->protectedExpressions = $protectedExpressions ?? $this->_getDefaultProtectedExpressions();
        $this->disallowedXPathExpressions = $disallowedXPathExpressions ?? $this->_getDefaultDisallowedXPathExpressions();
        $this->invalidXmlMessage = $invalidXmlMessage ?? Mage::helper('core')->__('XML data is invalid.');
        $this->invalidTemplatePathMessage = $invalidTemplatePathMessage ?? Mage::helper('core')->__('Invalid template path used in layout update.');
        $this->invalidBlockNameMessage = $invalidBlockNameMessage ?? Mage::helper('core')->__('Disallowed block name for frontend.');
        $this->protectedHelperMessage = $protectedHelperMessage ?? Mage::helper('core')->__('Helper attributes should not be used in custom layout updates.');
        $this->invalidXmlObjectMessage = $invalidXmlObjectMessage ?? Mage::helper('core')->__('XML object is not instance of "Varien_Simplexml_Element".');
    }

    public function isValid(mixed $value): bool
    {
        $this->_messages = [];

        if (null === $value || '' === $value) {
            return true;
        }

        // Handle string input - convert to XML
        if (is_string($value)) {
            $value = trim($value);
            try {
                $value = new Varien_Simplexml_Element('<config>' . $value . '</config>');
            } catch (Exception $e) {
                $this->_messages[] = $this->invalidXmlMessage;
                return false;
            }
        } elseif (!($value instanceof Varien_Simplexml_Element)) {
            $this->_messages[] = $this->invalidXmlObjectMessage;
            return false;
        }

        // Validate against disallowed blocks
        $xpathBlockValidation = $this->_getXpathBlockValidationExpression($this->disallowedBlocks);
        if ($xpathBlockValidation && $value->xpath($xpathBlockValidation)) {
            $this->_messages[] = $this->invalidBlockNameMessage;
            return false;
        }

        // Validate template paths
        $xpathValidation = implode(' | ', $this->disallowedXPathExpressions);
        if ($templatePaths = $value->xpath($xpathValidation)) {
            try {
                $this->_validateTemplatePath($templatePaths);
            } catch (Exception $e) {
                $this->_messages[] = $this->invalidTemplatePathMessage;
                return false;
            }
        }

        // Validate protected expressions
        foreach ($this->protectedExpressions as $key => $xpr) {
            if ($value->xpath($xpr)) {
                $this->_messages[] = $this->protectedHelperMessage;
                return false;
            }
        }

        return true;
    }

    public function getMessages(): array
    {
        return $this->_messages;
    }

    public function getMessage(): string
    {
        return !empty($this->_messages) ? $this->_messages[0] : '';
    }

    public function getDisallowedBlocks(): array
    {
        return $this->disallowedBlocks;
    }

    public function getProtectedExpressions(): array
    {
        return $this->protectedExpressions;
    }

    public function getDisallowedXpathValidationExpression(): array
    {
        return $this->disallowedXPathExpressions;
    }

    public function getXpathValidationExpression(): string
    {
        return implode(' | ', $this->disallowedXPathExpressions);
    }

    public function getXpathBlockValidationExpression(): string
    {
        if (!count($this->disallowedBlocks)) {
            return '';
        }

        $expression = '';
        foreach ($this->disallowedBlocks as $key => $value) {
            $expression .= $key > 0 ? ' | ' : '';
            $expression .= "//block[translate(@type, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = ";
            $expression .= "translate('$value', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')]";
        }
        return $expression;
    }

    public function setMessages(array $messages): self
    {
        // Map message constants to properties
        if (isset($messages['invalidXml'])) {
            $this->invalidXmlMessage = $messages['invalidXml'];
        }
        if (isset($messages['invalidTemplatePath'])) {
            $this->invalidTemplatePathMessage = $messages['invalidTemplatePath'];
        }
        if (isset($messages['invalidBlockName'])) {
            $this->invalidBlockNameMessage = $messages['invalidBlockName'];
        }
        if (isset($messages['protectedAttrHelperInActionVar'])) {
            $this->protectedHelperMessage = $messages['protectedAttrHelperInActionVar'];
        }
        if (isset($messages[self::INVALID_XML_OBJECT_EXCEPTION])) {
            $this->invalidXmlObjectMessage = $messages[self::INVALID_XML_OBJECT_EXCEPTION];
        }
        return $this;
    }

    public function validateTemplatePath(array $templatePaths): void
    {
        $this->_validateTemplatePath($templatePaths);
    }

    private function _getXpathBlockValidationExpression(array $disallowedBlocks): string
    {
        if (!count($disallowedBlocks)) {
            return '';
        }

        $expression = '';
        foreach ($disallowedBlocks as $key => $value) {
            $expression .= $key > 0 ? ' | ' : '';
            $expression .= "//block[translate(@type, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = ";
            $expression .= "translate('$value', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')]";
        }
        return $expression;
    }

    private function _validateTemplatePath(array $templatePaths): void
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

    // Constants for backward compatibility
    public const INVALID_XML_OBJECT_EXCEPTION = 'invalidXmlObjectException';

    private function _getDefaultDisallowedBlocks(): array
    {
        $disallowedBlocks = [];
        $disallowedBlockConfig = Mage::getStoreConfig('validators/custom_layout/disallowed_block');
        if (is_array($disallowedBlockConfig)) {
            foreach (array_keys($disallowedBlockConfig) as $blockName) {
                $disallowedBlocks[] = $blockName;
            }
        }
        return $disallowedBlocks;
    }

    private function _getDefaultProtectedExpressions(): array
    {
        return [
            'protectedAttrHelperInActionVar' => '//action/*[@helper]',
        ];
    }

    private function _getDefaultDisallowedXPathExpressions(): array
    {
        return [
            '*//template',
            '*//@template',
            '//*[@method=\'setTemplate\']',
            '//*[@method=\'setDataUsingMethod\']//*[contains(translate(text(),
            \'ABCDEFGHIJKLMNOPQRSTUVWXYZ\', \'abcdefghijklmnopqrstuvwxyz\'), \'template\')]/../*',
        ];
    }
}
