<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Helper_Config extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Adminhtml';

    /**
     * Return information array of input types
     */
    public function getInputTypes(?string $inputType = null): array
    {
        $inputTypes = [
            'color' => [
                'backend_model' => 'adminhtml/system_config_backend_color',
            ],
        ];

        if (is_null($inputType)) {
            return $inputTypes;
        }
        return $inputTypes[$inputType] ?? [];
    }

    /**
     * Return default backend model by input type
     */
    public function getBackendModelByInputType(string $inputType): ?string
    {
        $inputTypes = $this->getInputTypes();
        if (!empty($inputTypes[$inputType]['backend_model'])) {
            return $inputTypes[$inputType]['backend_model'];
        }
        return null;
    }

    /**
     * Get field backend model by field config node
     */
    public function getBackendModelByFieldConfig(\Maho\Simplexml\Element $fieldConfig): ?string
    {
        if (isset($fieldConfig->backend_model)) {
            return (string) $fieldConfig->backend_model;
        }
        if (isset($fieldConfig->frontend_type)) {
            return $this->getBackendModelByInputType((string) $fieldConfig->frontend_type);
        }
        return null;
    }
}
