<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Index
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Abstract lock storage
 *
 * @category   Mage
 * @package    Mage_Index
 */
interface Mage_Index_Model_Lock_Storage_Interface
{
    /**
     * Set named lock
     *
     * @param string $lockName
     * @return bool
     */
    public function setLock($lockName);

    /**
     * Release named lock
     *
     * @param string $lockName
     * @return bool
     */
    public function releaseLock($lockName);

    /**
     * Check whether the lock exists
     *
     * @param string $lockName
     * @return bool
     */
    public function isLockExists($lockName);
}
