<?php

/**
 * A saved report on the dashboard of an administrator.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_DashboardItem extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('reports/dashboardItem');
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

    public function getPosition(): ?int
    {
        $value = $this->getData('position');
        return $value === null ? null : (int) $value;
    }

    public function setPosition(?int $value): static
    {
        return $this->setData('position', $value);
    }
}
