<?php

/**
 * The person whose data an exporter or an eraser reads: a customer account, or a guest known only by an email.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

final readonly class Maho_PrivacyCenter_Model_Subject
{
    public function __construct(
        public ?int $customerId,
        #[\SensitiveParameter]
        public string $email,
        public ?int $storeId = null,
    ) {}

    public static function fromRequest(Maho_PrivacyCenter_Model_Request $request): self
    {
        return new self($request->getCustomerId(), (string) $request->getEmail(), $request->getStoreId());
    }

    public function isGuest(): bool
    {
        return $this->customerId === null;
    }

    /**
     * The SQL condition that selects the rows of this subject: the rows of its customer, and the guest rows of its
     * email. $guestCustomerIds are the values of $customerColumn on a guest row. The condition has one ? for the email.
     *
     * @param list<int|null> $guestCustomerIds
     */
    public function getRowsCondition(
        \Maho\Db\Adapter\AdapterInterface $adapter,
        string $customerColumn,
        string $emailColumn,
        array $guestCustomerIds = [null],
    ): string {
        $guest = implode(' OR ', array_map(
            fn(?int $id): string => $id === null ? "{$customerColumn} IS NULL" : "{$customerColumn} = {$id}",
            $guestCustomerIds,
        ));
        $condition = "({$guest}) AND " . $adapter->getCaseInsensitiveEqualsSql($emailColumn, '?');
        return $this->isGuest() ? $condition : "{$customerColumn} = {$this->customerId} OR ({$condition})";
    }
}
