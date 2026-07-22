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
 * @method $this setIncompleteCount(int $value)
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

    /** @var array<string, list<Maho_AccessibilityScan_Model_Violation>>|null */
    protected ?array $violationsGroupedByImpact = null;

    /** @var list<Maho_AccessibilityScan_Model_Page>|null */
    protected ?array $pages = null;

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
        return $this->getPages()[0] ?? null;
    }

    /**
     * Pages of this scan in insert order (one per scanned viewport).
     * Cached for the lifetime of the model instance: the scan view renders
     * per-violation marker links, which would otherwise re-query the pages
     * once per violation.
     *
     * @return list<Maho_AccessibilityScan_Model_Page>
     */
    public function getPages(): array
    {
        return $this->pages ??= array_values($this->getPageCollection()->getItems());
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
     * Violations grouped by impact, most severe first; empty groups omitted.
     * Cached for the lifetime of the model instance.
     *
     * @return array<string, list<Maho_AccessibilityScan_Model_Violation>>
     */
    public function getViolationsGroupedByImpact(): array
    {
        if ($this->violationsGroupedByImpact === null) {
            $grouped = array_fill_keys(Maho_AccessibilityScan_Model_Violation::IMPACT_LEVELS, []);
            foreach ($this->getViolationCollection() as $violation) {
                $impact = $violation->getImpact();
                if (!in_array($impact, Maho_AccessibilityScan_Model_Violation::IMPACT_LEVELS, true)) {
                    $impact = Maho_AccessibilityScan_Model_Violation::IMPACT_MINOR;
                }
                $grouped[$impact][] = $violation;
            }
            $this->violationsGroupedByImpact = array_filter($grouped, fn(array $violations) => $violations !== []);
        }
        return $this->violationsGroupedByImpact;
    }

    /**
     * Sequential violation numbers as rendered grouped by impact, indexed by
     * violation id; shared by the admin view, its screenshot markers and the
     * PDF report so a number always refers to the same violation
     *
     * @return array<int, int>
     */
    public function getViolationNumbers(): array
    {
        $numbers = [];
        $number = 1;
        foreach ($this->getViolationsGroupedByImpact() as $violations) {
            foreach ($violations as $violation) {
                $numbers[(int) $violation->getId()] = $number++;
            }
        }
        return $numbers;
    }

    public function getTotalViolations(): int
    {
        return (int) $this->getData('total_violations');
    }

    /**
     * Number of checks axe-core could not evaluate automatically ("incomplete"
     * results, e.g. contrast over an image) that need a manual review
     */
    public function getIncompleteCount(): int
    {
        return (int) $this->getData('incomplete_count');
    }

    public function isPending(): bool
    {
        return $this->getStatus() === self::STATUS_PENDING;
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
            if (is_string($path) && $path !== '' && str_starts_with($path, $screenshotDir . DS)) {
                @unlink($path);
            }
        }
        return $this;
    }
}
