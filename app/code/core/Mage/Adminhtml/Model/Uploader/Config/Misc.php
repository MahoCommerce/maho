<?php

/**
 * Settings that the uploader reads once, before the first file.
 *
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_Uploader_Config_Misc extends Mage_Adminhtml_Model_Uploader_Config_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $helper = Mage::helper('adminhtml/uploader');

        $this->setMaxSizeInBytes($helper->getDataMaxSizeInBytes())
            ->setMaxSizePlural($helper->getDataMaxSize());
    }

    public function setMaxSizeInBytes(?int $value): static
    {
        return $this->setData('max_size_in_bytes', $value);
    }

    public function setMaxSizePlural(?string $value): static
    {
        return $this->setData('max_size_plural', $value);
    }

    /**
     * Replace the browse button with the remove button after the person selects a file.
     */
    public function setReplaceBrowseWithRemove(?bool $value = true): static
    {
        return $this->setData('replace_browse_with_remove', $value);
    }

    /**
     * Upload the files as soon as the person chooses or drops them, from one drop zone.
     */
    public function setAutoUpload(?bool $value = true): static
    {
        return $this->setData('auto_upload', $value);
    }

    public function getAutoUpload(): ?bool
    {
        $value = $this->getData('auto_upload');
        return $value === null ? null : (bool) $value;
    }

    /**
     * A limit of the files, such as their size. The drop zone shows it below its text.
     */
    public function setDropZoneNote(?string $value): static
    {
        return $this->setData('drop_zone_note', $value);
    }

    public function getDropZoneNote(): ?string
    {
        $value = $this->getData('drop_zone_note');
        return $value === null ? null : (string) $value;
    }
}
