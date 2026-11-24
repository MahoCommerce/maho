<?php

/**
 * A saved report that an administrator gets by email: each day, week or month, or only when a metric changes.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Subscription extends Mage_Core_Model_Abstract
{
    public const string FREQUENCY_DAILY = 'daily';
    public const string FREQUENCY_WEEKLY = 'weekly';
    public const string FREQUENCY_MONTHLY = 'monthly';

    public const string DIRECTION_DOWN = 'down';
    public const string DIRECTION_UP = 'up';
    public const string DIRECTION_ANY = 'any';

    public const float THRESHOLD_MAX = 1000.0;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('reports/subscription');
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

    public function getSavedReportId(): ?int
    {
        $value = $this->getData('saved_report_id');
        return $value === null ? null : (int) $value;
    }

    public function setSavedReportId(?int $value): static
    {
        return $this->setData('saved_report_id', $value);
    }

    public function getFrequency(): ?string
    {
        $value = $this->getData('frequency');
        return $value === null ? null : (string) $value;
    }

    public function setFrequency(?string $value): static
    {
        return $this->setData('frequency', $value);
    }

    public function getAlertMetric(): ?string
    {
        $value = $this->getData('alert_metric');
        return $value === null || $value === '' ? null : (string) $value;
    }

    public function setAlertMetric(?string $value): static
    {
        return $this->setData('alert_metric', $value === '' ? null : $value);
    }

    public function getAlertDirection(): ?string
    {
        $value = $this->getData('alert_direction');
        return $value === null || $value === '' ? null : (string) $value;
    }

    public function setAlertDirection(?string $value): static
    {
        return $this->setData('alert_direction', $value === '' ? null : $value);
    }

    public function getAlertThreshold(): ?float
    {
        $value = $this->getData('alert_threshold');
        return $value === null || $value === '' ? null : (float) $value;
    }

    public function setAlertThreshold(?float $value): static
    {
        return $this->setData('alert_threshold', $value);
    }

    public function getCheckedAt(): ?string
    {
        $value = $this->getData('checked_at');
        return $value === null ? null : (string) $value;
    }

    public function setCheckedAt(?string $value): static
    {
        return $this->setData('checked_at', $value);
    }

    public function getSentAt(): ?string
    {
        $value = $this->getData('sent_at');
        return $value === null ? null : (string) $value;
    }

    public function setSentAt(?string $value): static
    {
        return $this->setData('sent_at', $value);
    }

    /** An alert sends the report only when its metric changes by the threshold. Without an alert, every check sends it. */
    public function isAlert(): bool
    {
        return $this->getAlertMetric() !== null;
    }

    /** @return list<string> */
    public static function frequencies(): array
    {
        return [self::FREQUENCY_DAILY, self::FREQUENCY_WEEKLY, self::FREQUENCY_MONTHLY];
    }

    /** @return list<string> */
    public static function directions(): array
    {
        return [self::DIRECTION_DOWN, self::DIRECTION_UP, self::DIRECTION_ANY];
    }

    /**
     * Check the fields of the subscription. The service checks the metric against the dataset of the report.
     *
     * @return array<string, string>|true
     */
    public function validate(): array|true
    {
        $helper = Mage::helper('reports');
        $errors = [];
        if (!in_array($this->getFrequency(), self::frequencies(), true)) {
            $errors['frequency'] = $helper->__('Select how often to send the report.');
        }
        if ($this->isAlert()) {
            if (!in_array($this->getAlertDirection(), self::directions(), true)) {
                $errors['alert_direction'] = $helper->__('Select the direction of the change.');
            }
            $threshold = $this->getAlertThreshold();
            if ($threshold === null || $threshold <= 0 || $threshold > self::THRESHOLD_MAX) {
                $errors['alert_threshold'] = $helper->__('The change must be more than 0 %% and at most %s %%.', self::THRESHOLD_MAX);
            }
        }
        return $errors === [] ? true : $errors;
    }
}
