<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

/**
 * Registry of URLs whose entity was deleted, served as 410 Gone by the no-route page
 *
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method string getRequestPath()
 * @method $this setRequestPath(string $value)
 * @method string getEntityType()
 * @method $this setEntityType(string $value)
 * @method string getDeletedAt()
 * @method $this setDeletedAt(string $value)
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

    #[Maho\Config\CronJob('core_url_gone_purge', schedule: '0 3 * * *')]
    public function purgeOld(): void
    {
        $days = Mage::getStoreConfigAsInt(self::XML_PATH_PURGE_AFTER_DAYS);
        if ($days <= 0) {
            return;
        }

        $cutoff = new \DateTimeImmutable("-{$days} days", new \DateTimeZone('UTC'));
        Mage::getResourceSingleton('core/url_gone')->purgeOlderThan($cutoff);
    }
}
