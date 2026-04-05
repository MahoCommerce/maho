<?php

/**
 * Maho
 *
 * @package    Mage_Cron
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * In-memory collection for configured cron jobs grid.
 * Extends Collection\Db to satisfy the Grid widget's type requirements,
 * but does not perform any database queries.
 */
class Mage_Cron_Model_Resource_ConfiguredJobs_Collection extends \Maho\Data\Collection\Db
{
    #[\Override]
    public function load($printQuery = false, $logQuery = false)
    {
        return $this;
    }

    #[\Override]
    public function getSize()
    {
        return count($this->getItems());
    }
}
