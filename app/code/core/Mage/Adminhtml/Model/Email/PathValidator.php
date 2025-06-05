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
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;

/**
 * Email path validation constraint
 */
#[\Attribute]
class Mage_Adminhtml_Model_Email_PathValidator extends Constraint
{
    public string $invalidPathMessage = 'The configuration path is not valid for email templates.';
    private array $_messages = [];

    #[\Override]
    public function validatedBy(): string
    {
        return Mage_Adminhtml_Model_Email_PathValidatorValidator::class;
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

    /**
     * Return bool after checking the encrypted model in the path to config node
     *
     * @param string $path
     * @return bool
     */
    public function isEncryptedNodePath($path)
    {
        /** @var Mage_Adminhtml_Model_Config $configModel */
        $configModel = Mage::getSingleton('adminhtml/config');

        return in_array((string) $path, $configModel->getEncryptedNodeEntriesPaths());
    }
}

/**
 * Email path constraint validator
 */
class Mage_Adminhtml_Model_Email_PathValidatorValidator extends ConstraintValidator
{
    #[\Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Mage_Adminhtml_Model_Email_PathValidator) {
            throw new UnexpectedTypeException($constraint, Mage_Adminhtml_Model_Email_PathValidator::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $pathNode = is_array($value) ? array_shift($value) : $value;

        if (!$constraint->isEncryptedNodePath($pathNode)) {
            $this->context->buildViolation($constraint->invalidPathMessage)
                ->setParameter('{{ value }}', (string) $pathNode)
                ->addViolation();
        }
    }
}
