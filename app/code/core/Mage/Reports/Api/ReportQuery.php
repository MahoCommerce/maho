<?php

/**
 * The query parameters that the reports share: date range, period type, scope, order statuses and date basis.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Trait\FilterValueTrait;
use Maho\ApiPlatform\Trait\StoreRestrictionTrait;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ReportQuery
{
    use FilterValueTrait;
    use StoreRestrictionTrait;

    public const PERIOD_DAY = 'day';
    public const PERIOD_MONTH = 'month';
    public const PERIOD_YEAR = 'year';
    public const PERIOD_TYPES = [self::PERIOD_DAY, self::PERIOD_MONTH, self::PERIOD_YEAR];

    /**
     * A response has at most this number of periods, so a day report covers about 2.7 years.
     */
    public const MAX_PERIODS = 1000;

    public string $from = '';
    public string $to = '';
    public string $periodType = self::PERIOD_DAY;
    public ?int $storeId = null;
    public ?int $websiteId = null;
    /** @var list<int> */
    public array $storeIds = [];
    /** True when the caller named a scope or the token has a store restriction */
    public bool $scoped = false;
    /** @var list<string>|null */
    public ?array $orderStatuses = null;
    public ?string $dateBasis = null;
    public bool $emptyPeriods = true;

    /**
     * Read the query of a period report.
     *
     * @param array<string, mixed> $filters
     * @param list<string> $dateBases the accepted dateBasis values, the first one is the default
     */
    public static function forPeriods(array $filters, ApiUser $user, array $dateBases = [], bool $orderStatuses = false): self
    {
        $query = new self();
        $query->readDateRange($filters);
        $query->periodType = $query->readEnum($filters, 'periodType', self::PERIOD_TYPES) ?? self::PERIOD_DAY;
        if ($query->countPeriods() > self::MAX_PERIODS) {
            throw new BadRequestHttpException(sprintf(
                'The range has more than %d periods. Use a larger periodType or a shorter range.',
                self::MAX_PERIODS,
            ));
        }
        $query->readScope($filters, $user);
        if ($dateBases !== []) {
            $query->dateBasis = $query->readEnum($filters, 'dateBasis', $dateBases) ?? $dateBases[0];
        }
        if ($orderStatuses) {
            $query->orderStatuses = $query->readOrderStatuses($filters);
        }
        $query->emptyPeriods = $query->booleanFilter($filters, 'emptyPeriods') ?? true;
        return $query;
    }

    /**
     * Read the date range and the scope of a live report without periods.
     *
     * @param array<string, mixed> $filters
     */
    public static function forRange(array $filters, ApiUser $user): self
    {
        $query = new self();
        $query->readDateRange($filters);
        $query->readScope($filters, $user);
        return $query;
    }

    /**
     * The range in UTC ('Y-m-d H:i:s'): from the start of the day $from to the end of the day $to,
     * in the time zone of the default scope.
     *
     * @return array{string, string}
     */
    public function utcRange(): array
    {
        $locale = \Mage::app()->getLocale();
        return [
            $locale->storeToUtc(\Mage_Core_Model_App::ADMIN_STORE_ID, $this->from . ' 00:00:00')->format(\Mage_Core_Model_Locale::DATETIME_FORMAT),
            $locale->storeToUtc(\Mage_Core_Model_App::ADMIN_STORE_ID, $this->to . ' 23:59:59')->format(\Mage_Core_Model_Locale::DATETIME_FORMAT),
        ];
    }

    /**
     * Read only the scope (storeId or websiteId) of a request.
     *
     * @param array<string, mixed> $filters
     */
    public static function forScope(array $filters, ApiUser $user): self
    {
        $query = new self();
        $query->readScope($filters, $user);
        return $query;
    }

    /**
     * Return the value of $key, which must be one of $allowed, or null when it is absent.
     *
     * @param array<string, mixed> $filters
     * @param list<string> $allowed
     */
    public function readEnum(array $filters, string $key, array $allowed): ?string
    {
        $value = $this->stringFilter($filters, $key);
        if ($value === null) {
            return null;
        }
        if (!in_array($value, $allowed, true)) {
            throw new BadRequestHttpException(sprintf('%s must be one of: %s', $key, implode(', ', $allowed)));
        }
        return $value;
    }

    /**
     * Return a comma-separated list of positive integers, or null when $key is absent.
     *
     * @param array<string, mixed> $filters
     * @return list<int>|null
     */
    public function readIdList(array $filters, string $key, int $max = 100): ?array
    {
        $value = $this->stringFilter($filters, $key);
        if ($value === null) {
            return null;
        }
        $ids = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if (!preg_match('/^[1-9]\d{0,8}$/', $part)) {
                throw new BadRequestHttpException("{$key} must be a comma-separated list of IDs");
            }
            $ids[(int) $part] = (int) $part;
        }
        if (count($ids) > $max) {
            throw new BadRequestHttpException("{$key} has more than {$max} IDs");
        }
        return array_values($ids);
    }

    /**
     * Return an integer between 1 and $max, or $default when $key is absent.
     *
     * @param array<string, mixed> $filters
     */
    public function readLimit(array $filters, int $default, int $max, string $key = 'limit'): int
    {
        $limit = $this->intFilter($filters, $key) ?? $default;
        if ($limit < 1 || $limit > $max) {
            throw new BadRequestHttpException("{$key} must be between 1 and {$max}");
        }
        return $limit;
    }

    /**
     * The labels of all periods from $from to $to: 2026-09-01 for day, 2026-09 for month, 2026 for year.
     *
     * @return list<string>
     */
    public function periodLabels(): array
    {
        [$format, $step] = match ($this->periodType) {
            self::PERIOD_YEAR => ['Y', '+1 year'],
            self::PERIOD_MONTH => ['Y-m', '+1 month'],
            default => ['Y-m-d', '+1 day'],
        };
        $cursor = new \DateTimeImmutable(match ($this->periodType) {
            self::PERIOD_YEAR => substr($this->from, 0, 4) . '-01-01',
            self::PERIOD_MONTH => substr($this->from, 0, 7) . '-01',
            default => $this->from,
        });
        $last = new \DateTimeImmutable($this->to)->format($format);
        $labels = [];
        do {
            $labels[] = $label = $cursor->format($format);
            $cursor = $cursor->modify($step);
        } while ($label < $last);
        return $labels;
    }

    /**
     * Convert a period value of a report table to the label of the period type.
     */
    public function periodLabel(mixed $period): string
    {
        $period = (string) $period;
        return match ($this->periodType) {
            self::PERIOD_YEAR => substr($period, 0, 4),
            self::PERIOD_MONTH => substr($period, 0, 7),
            default => substr($period, 0, 10),
        };
    }

    /**
     * @return array{storeIds: list<int>, websiteId: ?int, storeId: ?int}
     */
    public function scopeArray(): array
    {
        return ['storeIds' => $this->storeIds, 'websiteId' => $this->websiteId, 'storeId' => $this->storeId];
    }

    /**
     * The website IDs of the scope, or null when the caller can read all websites.
     *
     * @return list<int>|null
     */
    public function websiteIds(): ?array
    {
        if (!$this->scoped) {
            return null;
        }
        if ($this->websiteId !== null) {
            return [$this->websiteId];
        }
        $websiteIds = [];
        foreach ($this->storeIds as $storeId) {
            // The store ID -1 is the placeholder of a scope without store views
            if ($storeId > 0) {
                $websiteIds[] = (int) \Mage::app()->getStore($storeId)->getWebsiteId();
            }
        }
        return array_values(array_unique($websiteIds));
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function readDateRange(array $filters): void
    {
        foreach (['from', 'to'] as $key) {
            $value = $this->stringFilter($filters, $key);
            if ($value === null) {
                throw new BadRequestHttpException("{$key} is required, in the format YYYY-MM-DD");
            }
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match)
                || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])
            ) {
                throw new BadRequestHttpException("{$key} must be a date in the format YYYY-MM-DD");
            }
            $this->{$key} = $value;
        }
        if ($this->from > $this->to) {
            throw new BadRequestHttpException('from must not be after to');
        }
    }

    private function countPeriods(): int
    {
        $from = new \DateTimeImmutable($this->from);
        $to = new \DateTimeImmutable($this->to);
        $years = (int) $to->format('Y') - (int) $from->format('Y');
        return match ($this->periodType) {
            self::PERIOD_YEAR => $years + 1,
            self::PERIOD_MONTH => $years * 12 + (int) $to->format('n') - (int) $from->format('n') + 1,
            default => (int) $from->diff($to)->days + 1,
        };
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function readScope(array $filters, ApiUser $user): void
    {
        $storeId = $this->intFilter($filters, 'storeId');
        $websiteId = $this->intFilter($filters, 'websiteId');
        if ($storeId !== null && $websiteId !== null) {
            throw new BadRequestHttpException('Send storeId or websiteId, not both');
        }

        $allStoreIds = array_map(intval(...), array_keys(\Mage::app()->getStores()));
        $allowed = $user->getAllowedStoreIds();
        $allowed = $allowed === null ? null : array_values(array_intersect($allStoreIds, array_map(intval(...), $allowed)));

        if ($storeId !== null) {
            if (!in_array($storeId, $allStoreIds, true)) {
                throw new BadRequestHttpException('storeId is not the ID of a store view');
            }
            if ($allowed !== null && !in_array($storeId, $allowed, true)) {
                throw new AccessDeniedHttpException('Access denied for this store');
            }
            $this->storeId = $storeId;
            $this->storeIds = [$storeId];
            $this->scoped = true;
            return;
        }

        if ($websiteId !== null) {
            // The loaded websites, not a query: a large ID is out of range for the column on some databases
            $website = \Mage::app()->getWebsites()[$websiteId] ?? null;
            if ($website === null) {
                throw new BadRequestHttpException('websiteId is not the ID of a website');
            }
            $allowedWebsites = $this->allowedWebsiteIds($user);
            if ($allowedWebsites !== null && !in_array($websiteId, $allowedWebsites, true)) {
                throw new AccessDeniedHttpException('Access denied for this website');
            }
            $storeIds = array_map(intval(...), array_values((array) $website->getStoreIds()));
            if ($allowed !== null) {
                $storeIds = array_values(array_intersect($storeIds, $allowed));
            }
            $this->websiteId = $websiteId;
            $this->storeIds = $storeIds === [] ? [-1] : $storeIds;
            $this->scoped = true;
            return;
        }

        if ($allowed !== null) {
            $this->storeIds = $allowed === [] ? [-1] : $allowed;
            $this->scoped = true;
            return;
        }

        sort($allStoreIds);
        $this->storeIds = $allStoreIds;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<string>|null
     */
    private function readOrderStatuses(array $filters): ?array
    {
        $value = $this->stringFilter($filters, 'orderStatuses');
        if ($value === null) {
            return null;
        }
        $known = array_map(strval(...), array_keys(\Mage::getSingleton('sales/order_config')->getStatuses()));
        $statuses = [];
        foreach (explode(',', $value) as $status) {
            $status = trim($status);
            if (!in_array($status, $known, true)) {
                throw new BadRequestHttpException('orderStatuses has an unknown order status: ' . mb_substr($status, 0, 64));
            }
            $statuses[$status] = $status;
        }
        return array_values($statuses);
    }
}
