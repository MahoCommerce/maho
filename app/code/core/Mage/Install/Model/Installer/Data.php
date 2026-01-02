<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Install_Model_Installer_Data extends Maho\DataObject
{
    /**
     * Errors array
     *
     * @var array<string>
     */
    protected array $_errors = [];

    /**
     * Add error
     */
    public function addError(string $error): self
    {
        $this->_errors[] = $error;
        return $this;
    }

    /**
     * Get all errors
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->_errors;
    }
}
