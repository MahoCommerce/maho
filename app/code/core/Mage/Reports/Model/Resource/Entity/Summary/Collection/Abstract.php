<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Reports_Model_Resource_Entity_Summary_Collection_Abstract extends \Maho\Data\Collection
{
    /**
     * Entity collection for summaries
     *
     * @var Mage_Eav_Model_Entity_Collection_Abstract
     */
    protected $_entityCollection;

    /**
     * Filters the summaries by some period
     *
     * @param string $periodType
     * @param string|int|null $customStart
     * @param string|int|null $customEnd
     * @return $this
     */
    public function setSelectPeriod($periodType, $customStart = null, $customEnd = null)
    {
        switch ($periodType) {
            case '24h':
                $customStart = time() - 86400;
                $customEnd   = time();
                break;

            case '7d':
                $customStart = time() - 604800;
                $customEnd   = time();
                break;

            case '30d':
                $customStart = time() - 2592000;
                $customEnd   = time();
                break;

            case '1y':
                $customStart = time() - 31536000;
                $customEnd   = time();
                break;

            default:
                if (is_string($customStart)) {
                    $customStart = strtotime($customStart);
                }
                if (is_string($customEnd)) {
                    $customEnd = strtotime($customEnd);
                }
                break;
        }

        return $this;
    }

    /**
     * Set date period
     *
     * @param int $period
     * @return $this
     */
    public function setDatePeriod($period)
    {
        return $this;
    }

    /**
     * Set store filter
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreFilter($storeId)
    {
        return $this;
    }

    /**
     * Return collection for summaries
     *
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    public function getCollection()
    {
        if (empty($this->_entityCollection)) {
            $this->_initCollection();
        }
        return $this->_entityCollection;
    }

    /**
     * Init collection
     *
     * @return $this
     */
    protected function _initCollection()
    {
        return $this;
    }
}
