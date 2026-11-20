<?php

/**
 * A report request that an administrator saved, private or shared with the other administrators.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_SavedReport extends Mage_Core_Model_Abstract
{
    public const NAME_MAX_LENGTH = 255;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('reports/savedReport');
    }

    public function getAdminUserId(): ?int
    {
        $value = $this->getData('admin_user_id');
        return $value === null ? null : (int) $value;
    }

    public function setAdminUserId(?int $value): static
    {
        return $this->setData('admin_user_id', $value);
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
    }

    /** The JSON of the request, in the shape of {@see \Maho\Report\Request::toArray()}. */
    public function getDefinition(): ?string
    {
        $value = $this->getData('definition');
        return $value === null ? null : (string) $value;
    }

    public function setDefinition(?string $value): static
    {
        return $this->setData('definition', $value);
    }

    public function getIsShared(): ?bool
    {
        $value = $this->getData('is_shared');
        return $value === null ? null : (bool) $value;
    }

    public function setIsShared(?bool $value = true): static
    {
        return $this->setData('is_shared', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

    /** @return array<string, mixed> the decoded definition, or an empty array */
    public function getRequestData(): array
    {
        $definition = $this->getDefinition();
        if ($definition === null || $definition === '') {
            return [];
        }
        try {
            $data = Mage::helper('core')->jsonDecode($definition);
        } catch (JsonException) {
            return [];
        }
        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $data */
    public function setRequestData(array $data): static
    {
        return $this->setDefinition(Mage::helper('core')->jsonEncode($data));
    }

    /** Whether the report is on the dashboard of the administrator who reads it. The table of the report does not hold it. */
    public function getIsPinned(): ?bool
    {
        $value = $this->getData('is_pinned');
        return $value === null ? null : (bool) $value;
    }

    public function setIsPinned(?bool $value = true): static
    {
        return $this->setData('is_pinned', $value);
    }

    /** @return array<string, string>|true */
    public function validate(): array|true
    {
        $helper = Mage::helper('reports');
        $errors = [];
        $name = trim((string) $this->getName());
        if ($name === '') {
            $errors['name'] = $helper->__('The name is required.');
        } elseif (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            $errors['name'] = $helper->__('The name has at most %d characters.', self::NAME_MAX_LENGTH);
        }
        if ($this->getRequestData() === []) {
            $errors['definition'] = $helper->__('The report definition is required.');
        }
        return $errors === [] ? true : $errors;
    }
}
