<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Install
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
