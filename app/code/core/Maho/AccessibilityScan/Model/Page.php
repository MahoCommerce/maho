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
 * @method string getViewport()
 * @method $this setViewport(string $value)
 * @method string getUrl()
 * @method $this setUrl(string $value)
 * @method ?string getPageTitle()
 * @method $this setPageTitle(?string $value)
 * @method string getStatus()
 * @method $this setStatus(string $value)
 * @method ?string getScreenshotPath()
 * @method $this setScreenshotPath(?string $value)
 * @method $this setPageWidth(?int $value)
 * @method $this setPageHeight(?int $value)
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

    /**
     * Absolute path of the page screenshot, or null when the stored path
     * does not resolve to a file inside the screenshot directory
     */
    public function getScreenshotFile(): ?string
    {
        $path = (string) $this->getScreenshotPath();
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $real = realpath($path);
        $dir = Mage::helper('accessibilityscan')->getScreenshotDir();
        return $real !== false && str_starts_with($real, $dir . DS) ? $real : null;
    }
}
