<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;

/**
 * Email path validation constraint and validator
 */
#[\Attribute]
class Mage_Adminhtml_Model_Email_PathValidator extends Constraint
{
    public string $invalidPathMessage = 'The configuration path is not valid for email templates.';
    private array $_messages = [];

    public function validate(mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        $pathNode = is_array($value) ? array_shift($value) : $value;

        if (!$this->isEncryptedNodePath($pathNode)) {
            $context->buildViolation($this->invalidPathMessage)
                ->setParameter('{{ value }}', (string) $pathNode)
                ->addViolation();
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

    public function isEncryptedNodePath(string $path): bool
    {
        $configModel = Mage::getSingleton('adminhtml/config');

        return in_array((string) $path, $configModel->getEncryptedNodeEntriesPaths());
    }
}
