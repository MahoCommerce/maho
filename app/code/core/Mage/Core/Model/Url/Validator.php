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

use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

/**
 * URL validation extending Symfony's built-in Url constraint
 */
#[\Attribute]
class Mage_Core_Model_Url_Validator extends Url
{
    private array $_messages = [];

    public function __construct(
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
        ?string $message = null
    ) {
        // Set default message if not provided
        $message = $message ?? 'Invalid URL "{{ value }}".';

        parent::__construct(
            message: $message,
            groups: $groups,
            payload: $payload,
        );
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
}
