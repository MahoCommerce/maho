<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Index
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
interface Mage_Index_Model_Resource_Helper_Lock_Interface
{
    /**
     * Timeout for lock get proc.
     */
    public const LOCK_GET_TIMEOUT = 5;

    /**
     * Set lock
     *
     * @param string $name
     * @return bool
     */
    public function setLock($name);

    /**
     * Release lock
     *
     * @param string $name
     * @return bool
     */
    public function releaseLock($name);

    /**
     * Is lock exists
     *
     * @param string $name
     * @return bool
     */
    public function isLocked($name);

    /**
     * @return $this
     */
    public function setWriteAdapter(Maho\Db\Adapter\AdapterInterface $adapter);
}
