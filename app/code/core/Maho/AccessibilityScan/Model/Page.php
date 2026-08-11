<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Model_Page extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('accessibilityscan/page');
    }

    public function getScanId(): int
    {
        return (int) $this->getData('scan_id');
    }

    public function setScanId(int $value): self
    {
        return $this->setData('scan_id', $value);
    }

    public function getViewport(): string
    {
        return (string) $this->getData('viewport');
    }

    public function setViewport(string $value): self
    {
        return $this->setData('viewport', $value);
    }

    public function getUrl(): string
    {
        return (string) $this->getData('url');
    }

    public function setUrl(string $value): self
    {
        return $this->setData('url', $value);
    }

    public function getPageTitle(): ?string
    {
        $value = $this->getData('page_title');
        return $value === null ? null : (string) $value;
    }

    public function setPageTitle(?string $value): self
    {
        return $this->setData('page_title', $value);
    }

    public function getStatus(): string
    {
        return (string) $this->getData('status');
    }

    public function setStatus(string $value): self
    {
        return $this->setData('status', $value);
    }

    public function getScreenshotPath(): ?string
    {
        $value = $this->getData('screenshot_path');
        return $value === null ? null : (string) $value;
    }

    public function setScreenshotPath(?string $value): self
    {
        return $this->setData('screenshot_path', $value);
    }

    public function getPageWidth(): ?int
    {
        $value = $this->getData('page_width');
        return $value === null ? null : (int) $value;
    }

    public function setPageWidth(?int $value): self
    {
        return $this->setData('page_width', $value);
    }

    public function getPageHeight(): ?int
    {
        $value = $this->getData('page_height');
        return $value === null ? null : (int) $value;
    }

    public function setPageHeight(?int $value): self
    {
        return $this->setData('page_height', $value);
    }

    public function getViolationCount(): int
    {
        return (int) $this->getData('violation_count');
    }

    public function setViolationCount(int $value): self
    {
        return $this->setData('violation_count', $value);
    }

    public function getScannedAt(): ?string
    {
        $value = $this->getData('scanned_at');
        return $value === null ? null : (string) $value;
    }

    public function setScannedAt(string $value): self
    {
        return $this->setData('scanned_at', $value);
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
