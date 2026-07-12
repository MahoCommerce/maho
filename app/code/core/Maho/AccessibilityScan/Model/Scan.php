<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

/**
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method string getStatus()
 * @method $this setStatus(string $value)
 * @method string getWcagLevel()
 * @method $this setWcagLevel(string $value)
 * @method string getUrl()
 * @method $this setUrl(string $value)
 * @method string getTriggeredBy()
 * @method $this setTriggeredBy(string $value)
 * @method $this setTotalViolations(int $value)
 * @method $this setViolationsCritical(int $value)
 * @method $this setViolationsSerious(int $value)
 * @method $this setViolationsModerate(int $value)
 * @method $this setViolationsMinor(int $value)
 * @method $this setErrorMessage(?string $value)
 * @method $this setStartedAt(string $value)
 * @method $this setCompletedAt(string $value)
 * @method $this setCreatedAt(string $value)
 */
class Maho_AccessibilityScan_Model_Scan extends Mage_Core_Model_Abstract
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_RUNNING  = 'running';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_FAILED   = 'failed';

    public const TRIGGER_MANUAL   = 'manual';
    public const TRIGGER_CLI      = 'cli';
    public const TRIGGER_SCHEDULE = 'schedule';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('accessibilityscan/scan');
    }

    public function getPageCollection(): Maho_AccessibilityScan_Model_Resource_Page_Collection
    {
        return Mage::getResourceModel('accessibilityscan/page_collection')
            ->addFieldToFilter('scan_id', $this->getId());
    }

    public function getFirstPage(): ?Maho_AccessibilityScan_Model_Page
    {
        $page = $this->getPageCollection()->getFirstItem();
        return $page instanceof Maho_AccessibilityScan_Model_Page && $page->getId() ? $page : null;
    }

    public function getViolationCollection(): Maho_AccessibilityScan_Model_Resource_Violation_Collection
    {
        return Mage::getResourceModel('accessibilityscan/violation_collection')
            ->addFieldToFilter('scan_id', $this->getId());
    }

    /**
     * Violation counters indexed by axe impact level
     *
     * @return array<string, int>
     */
    public function getViolationCounts(): array
    {
        return [
            Maho_AccessibilityScan_Model_Violation::IMPACT_CRITICAL => (int) $this->getData('violations_critical'),
            Maho_AccessibilityScan_Model_Violation::IMPACT_SERIOUS  => (int) $this->getData('violations_serious'),
            Maho_AccessibilityScan_Model_Violation::IMPACT_MODERATE => (int) $this->getData('violations_moderate'),
            Maho_AccessibilityScan_Model_Violation::IMPACT_MINOR    => (int) $this->getData('violations_minor'),
        ];
    }

    /**
     * Violations grouped by impact, most severe first; empty groups omitted
     *
     * @return array<string, list<Maho_AccessibilityScan_Model_Violation>>
     */
    public function getViolationsGroupedByImpact(): array
    {
        $grouped = array_fill_keys(Maho_AccessibilityScan_Model_Violation::IMPACT_LEVELS, []);
        foreach ($this->getViolationCollection() as $violation) {
            $impact = $violation->getImpact();
            if (!in_array($impact, Maho_AccessibilityScan_Model_Violation::IMPACT_LEVELS, true)) {
                $impact = Maho_AccessibilityScan_Model_Violation::IMPACT_MINOR;
            }
            $grouped[$impact][] = $violation;
        }
        return array_filter($grouped, fn(array $violations) => $violations !== []);
    }

    public function getTotalViolations(): int
    {
        return (int) $this->getData('total_violations');
    }

    public function isComplete(): bool
    {
        return $this->getStatus() === self::STATUS_COMPLETE;
    }

    public function isFailed(): bool
    {
        return $this->getStatus() === self::STATUS_FAILED;
    }

    /**
     * Delete the scan together with its page screenshots on disk
     * (page and violation rows cascade at the database level)
     */
    public function deleteWithScreenshots(): self
    {
        $screenshots = $this->getPageCollection()->getColumnValues('screenshot_path');
        $this->delete();

        $screenshotDir = Mage::helper('accessibilityscan')->getScreenshotDir();
        foreach ($screenshots as $path) {
            if (is_string($path) && $path !== '' && str_starts_with($path, $screenshotDir)) {
                @unlink($path);
            }
        }
        return $this;
    }
}
