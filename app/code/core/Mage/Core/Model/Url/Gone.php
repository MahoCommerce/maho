<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

/**
 * Registry of URLs whose entity was deleted, served as 410 Gone by the no-route page
 */
class Mage_Core_Model_Url_Gone extends Mage_Core_Model_Abstract
{
    public const ENTITY_TYPE_PRODUCT = 'product';

    public const XML_PATH_PURGE_AFTER_DAYS = 'catalog/seo/gone_purge_after_days';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('core/url_gone');
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getRequestPath(): ?string
    {
        $value = $this->getData('request_path');
        return $value === null ? null : (string) $value;
    }

    public function setRequestPath(?string $value): static
    {
        return $this->setData('request_path', $value);
    }

    public function getEntityType(): ?string
    {
        $value = $this->getData('entity_type');
        return $value === null ? null : (string) $value;
    }

    public function setEntityType(?string $value): static
    {
        return $this->setData('entity_type', $value);
    }

    public function getDeletedAt(): ?string
    {
        $value = $this->getData('deleted_at');
        return $value === null ? null : (string) $value;
    }

    public function setDeletedAt(?string $value): static
    {
        return $this->setData('deleted_at', $value);
    }

    #[Maho\Config\CronJob('core_url_gone_purge', schedule: '0 3 * * *')]
    public function purgeOld(): void
    {
        $resource = Mage::getResourceSingleton('core/url_gone');
        $resource->purgeClaimed();

        $days = Mage::getStoreConfigAsInt(self::XML_PATH_PURGE_AFTER_DAYS);
        if ($days <= 0) {
            return;
        }

        $cutoff = new \DateTimeImmutable("-{$days} days", new \DateTimeZone('UTC'));
        $resource->purgeOlderThan($cutoff);
    }
}
