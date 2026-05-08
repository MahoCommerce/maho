<?php

/**
 * Maho
 *
 * @package    Maho_LegacyMigrateTestB
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_LegacyMigrateTestB_Model_Observer
{
    public function onAdminLogin(Maho\Event\Observer $observer): void
    {
    }

    public function generateReport(Mage_Cron_Model_Schedule $schedule): void
    {
    }
}
