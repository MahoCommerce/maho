<?php

/**
 * Settings that the uploader sends with each request.
 *
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_Uploader_Config_Uploader extends Mage_Adminhtml_Model_Uploader_Config_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->setFileParameterName('file');
    }

    public function setTarget(?string $value): static
    {
        return $this->setData('target', $value);
    }

    public function setFileParameterName(?string $value): static
    {
        return $this->setData('file_parameter_name', $value);
    }

    public function setQuery(?array $value): static
    {
        return $this->setData('query', $value);
    }

    public function setHeaders(?array $value): static
    {
        return $this->setData('headers', $value);
    }

    public function setSingleFile(?bool $value = true): static
    {
        return $this->setData('single_file', $value);
    }
}
