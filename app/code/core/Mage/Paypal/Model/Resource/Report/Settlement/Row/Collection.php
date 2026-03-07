<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Paypal_Model_Resource_Report_Settlement_Row_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Resource initializing
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('paypal/report_settlement_row');
    }

    /**
     * Join reports info table
     *
     * @return $this
     */
    #[\Override]
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()
            ->join(
                ['report' => $this->getTable('paypal/settlement_report')],
                'report.report_id = main_table.report_id',
                ['report.account_id', 'report.report_date'],
            );
        return $this;
    }

    /**
     * Filter items collection by account ID
     *
     * @param string $accountId
     * @return $this
     */
    public function addAccountFilter($accountId)
    {
        $this->getSelect()->where('report.account_id = ?', $accountId);
        return $this;
    }
}
