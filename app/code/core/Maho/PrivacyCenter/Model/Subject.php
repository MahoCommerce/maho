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
}
