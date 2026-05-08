<?php

/**
 * Maho
 *
 * @package    Maho_LegacyMigrateTestA
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_LegacyMigrateTestA_Model_Observer
{
    public function onProductSave(Maho\Event\Observer $observer): void {}

    public function onCustomerLogin(Maho\Event\Observer $observer): void {}

    public function runDailyCleanup(Mage_Cron_Model_Schedule $schedule): void {}
}
