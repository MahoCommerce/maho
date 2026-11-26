<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_Model_Cron
{
    #[Maho\Config\CronJob('privacy_expire_requests', schedule: '15 * * * *')]
    public function expireRequests(): void
    {
        Mage::getService('privacycenter/request')->expirePending();
    }

    #[Maho\Config\CronJob('privacy_process_erasures', schedule: '30 * * * *')]
    public function processErasures(): void
    {
        Mage::getService('privacycenter/request')->processDueErasures();
    }

    #[Maho\Config\CronJob('privacy_clean_exports', schedule: '45 3 * * *')]
    public function cleanExports(): void
    {
        Mage::getService('privacycenter/request')->cleanExports();
    }
}
