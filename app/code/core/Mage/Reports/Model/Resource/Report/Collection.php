<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Reports_Model_Resource_Report_Collection
{
    /**
     * From value
     *
     * @var DateTime
     */
    protected $_from;

    /**
     * To value
     *
     * @var DateTime
     */
    protected $_to;

    /**
     * Report period
     *
     * @var int
     */
    protected $_period;

    /**
     * Model object
     *
     * @var Mage_Reports_Model_Report
     */
    protected $_model;

    /**
     * Intervals
     *
     * @var array
     */
    protected $_intervals;

    /**
     * Page size
     *
     * @var int
     */
    protected $_pageSize;

    /**
     * Array of store ids
     *
     * @var array
     */
    protected $_storeIds;

    protected function _construct() {}

    /**
     * Set period
     *
     * @param int $period
     * @return $this
     */
    public function setPeriod($period)
    {
        $this->_period = $period;
        return $this;
    }

    /**
     * Set interval
     *
     * @param DateTime $from
     * @param DateTime $to
     * @return $this
     */
    public function setInterval($from, $to)
    {
        $this->_from = $from;
        $this->_to   = $to;

        return $this;
    }

    /**
     * Get intervals
     *
     * @return array
     */
    public function getIntervals()
    {
        if (!$this->_intervals) {
            $this->_intervals = [];
            if (!$this->_from && !$this->_to) {
                return $this->_intervals;
            }
            $dateStart  = new DateTime($this->_from->format(Mage_Core_Model_Locale::DATETIME_FORMAT));
            $dateEnd    = new DateTime($this->_to->format(Mage_Core_Model_Locale::DATETIME_FORMAT));

            $time = [];
            $firstInterval = true;
            while ($dateStart <= $dateEnd) {
                switch ($this->_period) {
                    case Mage_Reports_Helper_Data::REPORT_PERIOD_TYPE_DAY:
                        $time['title'] = $dateStart->format(Mage::app()->getLocale()->getDateFormat());
                        $time['start'] = $dateStart->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                        $time['end'] = $dateStart->format(Mage_Core_Model_Locale::DATE_FORMAT . ' 23:59:59');
                        $dateStart->modify('+1 day');
                        break;
                    case Mage_Reports_Helper_Data::REPORT_PERIOD_TYPE_MONTH:
                        $time['title'] = $dateStart->format('m/Y');
                        $time['start'] = ($firstInterval) ? $dateStart->format(Mage_Core_Model_Locale::DATE_FORMAT . ' 00:00:00')
                            : $dateStart->format('Y-m-01 00:00:00');

                        $lastInterval = ($dateStart->format('n') == $dateEnd->format('n') && $dateStart->format('Y') == $dateEnd->format('Y'));

                        $time['end'] = ($lastInterval) ? $dateStart->setDate((int) $dateStart->format('Y'), (int) $dateStart->format('n'), (int) $dateEnd->format('j'))
                            ->format(Mage_Core_Model_Locale::DATE_FORMAT . ' 23:59:59')
                            : $dateStart->format('Y-m-' . date('t', $dateStart->getTimestamp()) . ' 23:59:59');

                        $dateStart->modify('+1 month');

                        if ($dateStart->format('n') == $dateEnd->format('n') && $dateStart->format('Y') == $dateEnd->format('Y')) {
                            $dateStart->setDate((int) $dateStart->format('Y'), (int) $dateStart->format('n'), 1);
                        }

                        $firstInterval = false;
                        break;
                    case Mage_Reports_Helper_Data::REPORT_PERIOD_TYPE_YEAR:
                        $time['title'] = $dateStart->format('Y');
                        $time['start'] = ($firstInterval) ? $dateStart->format(Mage_Core_Model_Locale::DATE_FORMAT . ' 00:00:00')
                            : $dateStart->format('Y-01-01 00:00:00');

                        $lastInterval = ($dateStart->format('Y') == $dateEnd->format('Y'));

                        $time['end'] = ($lastInterval) ? $dateStart->setDate((int) $dateStart->format('Y'), (int) $dateEnd->format('n'), (int) $dateEnd->format('j'))
                            ->format(Mage_Core_Model_Locale::DATE_FORMAT . ' 23:59:59')
                            : $dateStart->format('Y-12-31 23:59:59');
                        $dateStart->modify('+1 year');

                        if ($dateStart->format('Y') == $dateEnd->format('Y')) {
                            $dateStart->setDate((int) $dateStart->format('Y'), 1, 1);
                        }

                        $firstInterval = false;
                        break;
                }
                $this->_intervals[$time['title']] = $time;
            }
        }
        return  $this->_intervals;
    }

    /**
     * Return date periods
     *
     * @return array
     */
    public function getPeriods()
    {
        return [
            'day'   => Mage::helper('reports')->__('Day'),
            'month' => Mage::helper('reports')->__('Month'),
            'year'  => Mage::helper('reports')->__('Year'),
        ];
    }

    /**
     * Set store ids
     *
     * @param array $storeIds
     * @return $this
     */
    public function setStoreIds($storeIds)
    {
        $this->_storeIds = $storeIds;
        return $this;
    }

    /**
     * Get store ids
     *
     * @return array
     */
    public function getStoreIds()
    {
        return $this->_storeIds;
    }

    /**
     * Get size
     *
     * @return int
     */
    public function getSize()
    {
        return count($this->getIntervals());
    }

    /**
     * Set page size
     *
     * @param int $size
     * @return $this
     */
    public function setPageSize($size)
    {
        $this->_pageSize = $size;
        return $this;
    }

    /**
     * Get page size
     *
     * @return int
     */
    public function getPageSize()
    {
        return $this->_pageSize;
    }

    /**
     * Init report
     *
     * @param string $modelClass
     * @return $this
     */
    public function initReport($modelClass)
    {
        $this->_model = Mage::getModel('reports/report')
            ->setPageSize($this->getPageSize())
            ->setStoreIds($this->getStoreIds())
            ->initCollection($modelClass);

        return $this;
    }

    /**
     * get report full
     *
     * @param string $from
     * @param string $to
     * @return Mage_Reports_Model_Report
     */
    public function getReportFull($from, $to)
    {
        return $this->_model->getReportFull($this->timeShift($from), $this->timeShift($to));
    }

    /**
     * Get report
     *
     * @param string $from
     * @param string $to
     * @return Mage_Reports_Model_Report
     */
    public function getReport($from, $to)
    {
        return $this->_model->getReport($this->timeShift($from), $this->timeShift($to));
    }

    /**
     * Retrieve time shift
     *
     * @param string $datetime
     * @return string
     */
    public function timeShift($datetime)
    {
        return Mage::app()->getLocale()
            ->utcDate(null, $datetime, true, Mage_Core_Model_Locale::DATETIME_FORMAT)
            ->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
    }
}
