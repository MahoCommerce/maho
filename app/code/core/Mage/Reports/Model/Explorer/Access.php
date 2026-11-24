<?php

/**
 * What a caller of the report explorer may read. The transport builds it from the session or the token.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Maho\Report\Definition\Dataset;

final class Mage_Reports_Model_Explorer_Access
{
    /**
     * @param list<string>|null $datasets the dataset codes that the caller may read; null reads every dataset
     * @param bool $personalData whether the caller may read customer emails, names and postcodes
     * @param list<int>|null $storeIds the stores that the caller may read; null reads every store
     * @param list<string>|null $records the dataset codes whose rows the caller may list; null lists every dataset
     */
    public function __construct(
        public readonly ?array $datasets = null,
        public readonly bool $personalData = false,
        public readonly ?array $storeIds = null,
        public readonly ?array $records = [],
    ) {}

    /** Every dataset, every store and the personal data: for the CLI and for a full administrator. */
    public static function full(): self
    {
        return new self(null, true, null, null);
    }

    /**
     * The access of the administrator of an admin session: the datasets whose ACL resource the role allows, and the
     * personal data when the role may manage customers, and the rows of a dataset when the role may open them.
     */
    public static function forAdmin(Mage_Admin_Model_Session $session): self
    {
        return self::fromAcl($session->isAllowed(...));
    }

    /**
     * The access of an administrator outside a session, such as in a cron job that emails a report: the same rules as
     * {@see forAdmin()}, on the ACL of the role of the administrator.
     */
    public static function forAdminUser(Mage_Admin_Model_User $user): self
    {
        $acl = Mage::getResourceModel('admin/acl')->loadAcl();
        $role = $user->getAclRole();
        return self::fromAcl(static function (string $resource) use ($acl, $role): bool {
            $resource = str_starts_with($resource, 'admin') ? $resource : 'admin/' . $resource;
            try {
                return $acl->isAllowed($role, $resource);
            } catch (Exception) {
                try {
                    return !$acl->hasResource($resource) && $acl->isAllowed($role, null);
                } catch (Exception) {
                    return false;
                }
            }
        });
    }

    /** @param \Closure(string): bool $isAllowed whether the role allows an ACL resource */
    private static function fromAcl(\Closure $isAllowed): self
    {
        $datasets = $records = [];
        foreach (Mage::getSingleton('reports/explorer_registry')->getDatasets() as $dataset) {
            if ($isAllowed($dataset->aclResource)) {
                $datasets[] = $dataset->code;
                $definition = $dataset->getRecords();
                if ($definition !== null && $isAllowed($definition->aclResource)) {
                    $records[] = $dataset->code;
                }
            }
        }
        return new self($datasets, $isAllowed('customer/manage'), null, $records);
    }

    public function allowsDataset(Dataset $dataset): bool
    {
        return $this->datasets === null || in_array($dataset->code, $this->datasets, true);
    }

    /** Whether the caller may list the rows behind the numbers of a dataset. */
    public function allowsRecords(Dataset $dataset): bool
    {
        return $dataset->getRecords() !== null
            && $this->allowsDataset($dataset)
            && ($this->records === null || in_array($dataset->code, $this->records, true));
    }

    /** @return array{datasets: list<string>|null, personalData: bool, storeIds: list<int>|null, records: list<string>|null} */
    public function toArray(): array
    {
        return ['datasets' => $this->datasets, 'personalData' => $this->personalData, 'storeIds' => $this->storeIds, 'records' => $this->records];
    }

    /** @param array<string, mixed> $data the shape of {@see toArray()} */
    public static function fromArray(array $data): self
    {
        $list = static fn(mixed $value): ?array => is_array($value) ? array_values($value) : null;
        $storeIds = $list($data['storeIds'] ?? null);
        return new self(
            $list($data['datasets'] ?? null),
            (bool) ($data['personalData'] ?? false),
            $storeIds === null ? null : array_map(intval(...), $storeIds),
            array_key_exists('records', $data) ? $list($data['records']) : [],
        );
    }
}
