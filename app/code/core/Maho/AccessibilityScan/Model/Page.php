<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

/**
 * @method int getScanId()
 * @method $this setScanId(int $value)
 * @method string getUrl()
 * @method $this setUrl(string $value)
 * @method ?string getPageTitle()
 * @method $this setPageTitle(?string $value)
 * @method string getStatus()
 * @method $this setStatus(string $value)
 * @method ?string getScreenshotPath()
 * @method $this setScreenshotPath(?string $value)
 * @method $this setViolationCount(int $value)
 * @method $this setScannedAt(string $value)
 */
class Maho_AccessibilityScan_Model_Page extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('accessibilityscan/page');
    }

    public function getViolationCount(): int
    {
        return (int) $this->getData('violation_count');
    }
}
