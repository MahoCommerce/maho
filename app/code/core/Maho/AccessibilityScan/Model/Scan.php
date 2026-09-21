<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

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

    public function getStoreId(): int
    {
        return (int) $this->getData('store_id');
    }

    public function setStoreId(int $value): self
    {
        return $this->setData('store_id', $value);
    }

    public function getStatus(): string
    {
        return (string) $this->getData('status');
    }

    public function setStatus(string $value): self
    {
        return $this->setData('status', $value);
    }

    public function getWcagLevel(): string
    {
        return (string) $this->getData('wcag_level');
    }

    public function setWcagLevel(string $value): self
    {
        return $this->setData('wcag_level', $value);
    }

    public function getUrl(): string
    {
        return (string) $this->getData('url');
    }

    public function setUrl(string $value): self
    {
        return $this->setData('url', $value);
    }

    public function getTriggeredBy(): string
    {
        return (string) $this->getData('triggered_by');
    }

    public function setTriggeredBy(string $value): self
    {
        return $this->setData('triggered_by', $value);
    }

    public function setTotalViolations(int $value): self
    {
        return $this->setData('total_violations', $value);
    }

    public function getViolationsCritical(): int
    {
        return (int) $this->getData('violations_critical');
    }

    public function setViolationsCritical(int $value): self
    {
        return $this->setData('violations_critical', $value);
    }

    public function getViolationsSerious(): int
    {
        return (int) $this->getData('violations_serious');
    }

    public function setViolationsSerious(int $value): self
    {
        return $this->setData('violations_serious', $value);
    }

    public function getViolationsModerate(): int
    {
        return (int) $this->getData('violations_moderate');
    }

    public function setViolationsModerate(int $value): self
    {
        return $this->setData('violations_moderate', $value);
    }

    public function getViolationsMinor(): int
    {
        return (int) $this->getData('violations_minor');
    }

    public function setViolationsMinor(int $value): self
    {
        return $this->setData('violations_minor', $value);
    }

    public function setIncompleteCount(int $value): self
    {
        return $this->setData('incomplete_count', $value);
    }

    public function getErrorMessage(): ?string
    {
        $value = $this->getData('error_message');
        return $value === null ? null : (string) $value;
    }

    public function setErrorMessage(?string $value): self
    {
        return $this->setData('error_message', $value);
    }

    public function getStartedAt(): ?string
    {
        $value = $this->getData('started_at');
        return $value === null ? null : (string) $value;
    }

    public function setStartedAt(string $value): self
    {
        return $this->setData('started_at', $value);
    }

    public function getCompletedAt(): ?string
    {
        $value = $this->getData('completed_at');
        return $value === null ? null : (string) $value;
    }

    public function setCompletedAt(string $value): self
    {
        return $this->setData('completed_at', $value);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData('created_at');
        return $value === null ? null : (string) $value;
    }

    public function setCreatedAt(string $value): self
    {
        return $this->setData('created_at', $value);
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
            Maho_AccessibilityScan_Model_Violation::IMPACT_CRITICAL => $this->getViolationsCritical(),
            Maho_AccessibilityScan_Model_Violation::IMPACT_SERIOUS  => $this->getViolationsSerious(),
            Maho_AccessibilityScan_Model_Violation::IMPACT_MODERATE => $this->getViolationsModerate(),
            Maho_AccessibilityScan_Model_Violation::IMPACT_MINOR    => $this->getViolationsMinor(),
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
