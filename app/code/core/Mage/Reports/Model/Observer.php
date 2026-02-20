<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 */

class Mage_Reports_Model_Observer
{
    /**
     * Refresh viewed report statistics for last day
     *
     * @param Mage_Cron_Model_Schedule $schedule
     * @return $this
     */
    public function aggregateReportsReportProductViewedData($schedule)
    {
        Mage::app()->getLocale()->emulate(0);
        $currentDate = Mage::app()->getLocale()->dateMutable();
        $date = $currentDate->modify('-25 hours');
        Mage::getResourceModel('reports/report_product_viewed')->aggregate($date);
        Mage::app()->getLocale()->revert();
        return $this;
    }
}
