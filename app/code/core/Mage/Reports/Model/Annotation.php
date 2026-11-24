<?php

/**
 * A note on one day of the report charts, such as the start of a promotion.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Annotation extends Mage_Core_Model_Abstract
{
    public const int TITLE_MAX_LENGTH = 255;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('reports/annotation');
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

    public function getAnnotationDate(): ?string
    {
        $value = $this->getData('annotation_date');
        return $value === null ? null : (string) $value;
    }

    public function setAnnotationDate(?string $value): static
    {
        return $this->setData('annotation_date', $value);
    }

    public function getTitle(): ?string
    {
        $value = $this->getData('title');
        return $value === null ? null : (string) $value;
    }

    public function setTitle(?string $value): static
    {
        return $this->setData('title', $value);
    }

    /** @return array<string, string>|true */
    public function validate(): array|true
    {
        $helper = Mage::helper('reports');
        $errors = [];
        $title = trim((string) $this->getTitle());
        if ($title === '') {
            $errors['title'] = $helper->__('The text of the note is required.');
        } elseif (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            $errors['title'] = $helper->__('The text of the note has at most %d characters.', self::TITLE_MAX_LENGTH);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->getAnnotationDate());
        if ($date === false || $date->format('Y-m-d') !== $this->getAnnotationDate()) {
            $errors['date'] = $helper->__('The date must be a day as YYYY-MM-DD.');
        }
        return $errors === [] ? true : $errors;
    }
}
