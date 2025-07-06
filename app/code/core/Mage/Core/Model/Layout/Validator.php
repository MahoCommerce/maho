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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Validation;

#[\Attribute]
class Mage_Core_Model_Layout_Validator extends Constraint
{
    public string $invalidXmlMessage = 'XML data is invalid.';
    public string $invalidTemplatePathMessage = 'Invalid template path used in layout update.';
    public string $invalidBlockNameMessage = 'Disallowed block name for frontend.';
    public string $protectedHelperMessage = 'Helper attributes should not be used in custom layout updates.';
    public string $invalidXmlObjectMessage = 'XML object is not instance of "Varien_Simplexml_Element".';

    public array $disallowedBlocks = [];
    public array $protectedExpressions = [];
    public array $disallowedXPathExpressions = [];

    private array $_messages = [];

    public function __construct(
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
        ?array $disallowedBlocks = null,
        ?array $protectedExpressions = null,
        ?array $disallowedXPathExpressions = null,
        ?string $invalidXmlMessage = null,
        ?string $invalidTemplatePathMessage = null,
        ?string $invalidBlockNameMessage = null,
        ?string $protectedHelperMessage = null,
        ?string $invalidXmlObjectMessage = null,
    ) {
        parent::__construct($options, $groups, $payload);

        $this->disallowedBlocks = $disallowedBlocks ?? $this->_getDefaultDisallowedBlocks();
        $this->protectedExpressions = $protectedExpressions ?? $this->_getDefaultProtectedExpressions();
        $this->disallowedXPathExpressions = $disallowedXPathExpressions ?? $this->_getDefaultDisallowedXPathExpressions();
        $this->invalidXmlMessage = $invalidXmlMessage ?? $this->invalidXmlMessage;
        $this->invalidTemplatePathMessage = $invalidTemplatePathMessage ?? $this->invalidTemplatePathMessage;
        $this->invalidBlockNameMessage = $invalidBlockNameMessage ?? $this->invalidBlockNameMessage;
        $this->protectedHelperMessage = $protectedHelperMessage ?? $this->protectedHelperMessage;
        $this->invalidXmlObjectMessage = $invalidXmlObjectMessage ?? $this->invalidXmlObjectMessage;
    }

    public function validate(mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        // Handle string input - convert to XML
        if (is_string($value)) {
            $value = trim($value);
            try {
                $value = new Varien_Simplexml_Element('<config>' . $value . '</config>');
            } catch (Exception $e) {
                $context->buildViolation($this->invalidXmlMessage)
                    ->addViolation();
                return;
            }
        } elseif (!($value instanceof Varien_Simplexml_Element)) {
            $context->buildViolation($this->invalidXmlObjectMessage)
                ->addViolation();
            return;
        }

        // Validate against disallowed blocks
        $xpathBlockValidation = $this->_getXpathBlockValidationExpression($this->disallowedBlocks);
        if ($xpathBlockValidation && $value->xpath($xpathBlockValidation)) {
            $context->buildViolation($this->invalidBlockNameMessage)
                ->addViolation();
            return;
        }

        // Validate template paths
        $xpathValidation = implode(' | ', $this->disallowedXPathExpressions);
        if ($templatePaths = $value->xpath($xpathValidation)) {
            try {
                $this->_validateTemplatePath($templatePaths);
            } catch (Exception $e) {
                $context->buildViolation($this->invalidTemplatePathMessage)
                    ->addViolation();
                return;
            }
        }

        // Validate protected expressions
        foreach ($this->protectedExpressions as $key => $xpr) {
            if ($value->xpath($xpr)) {
                $context->buildViolation($this->protectedHelperMessage)
                    ->addViolation();
                return;
            }
        }
    }

    // Backward compatibility methods
    public function isValid(mixed $value): bool
    {
        $this->_messages = [];
        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $this);

        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->_messages[] = $violation->getMessage();
            }
            return false;
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
        // Legacy method for setting message templates - not used in Symfony constraints
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
