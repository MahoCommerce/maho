<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Dashboard_Graph extends Mage_Adminhtml_Block_Dashboard_Abstract
{
    /**
     * All series
     *
     * @var array
     */
    protected $_allSeries = [];

    /**
     * Axis labels
     *
     * @var array
     */
    protected $_axisLabels = [];

    /**
     * Axis maps
     *
     * @var array
     */
    protected $_axisMaps = [];

    /**
     * Data rows
     *
     * @var array
     */
    protected $_dataRows = [];

    /**
     * Chart width
     *
     * @var string
     */
    protected $_width = '587';

    /**
     * Chart height
     *
     * @var string
     */
    protected $_height = '300';

    /**
     * Html identifier
     *
     * @var string
     */
    protected $_htmlId = '';

    protected $_max;
    protected $_min;

    /**
     * Initialize object
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('dashboard/graph.phtml');
    }

    /**
     * Get tab template
     *
     * @return string
     */
    protected function _getTabTemplate()
    {
        return 'dashboard/graph.phtml';
    }

    /**
     * Set data rows
     *
     * @param mixed $rows
     */
    public function setDataRows($rows)
    {
        $this->_dataRows = (array) $rows;
    }

    /**
     * Add series
     *
     * @param string $seriesId
     */
    public function addSeries($seriesId, array $options)
    {
        $this->_allSeries[$seriesId] = $options;
    }

    /**
     * Get series
     *
     * @param string $seriesId
     * @return mixed
     */
    public function getSeries($seriesId)
    {
        return $this->_allSeries[$seriesId] ?? false;
    }

    public function getAllSeries(): array
    {
        return $this->_allSeries;
    }

    public function getAxisLabels(string $axis): array
    {
        return $this->_axisLabels[$axis];
    }

    /**
     * @throws Mage_Core_Model_Store_Exception
     */
    public function processData(): array
    {
        $params = [];

        $this->_allSeries = $this->getRowsData($this->_dataRows);
        foreach ($this->_axisMaps as $axis => $attr) {
            $this->setAxisLabels($axis, $this->getRowsData($attr, true));
        }

        $timezoneLocal = new DateTimeZone(Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE));

        /** @var array{0: DateTime, 1: DateTime} $dateRange */
        $dateRange = Mage::getResourceModel('reports/order_collection')
            ->getDateRange($this->getDataHelper()->getParam('period'), '', '', true);
        [$dateStart, $dateEnd] = $dateRange;

        // Convert to DateTimeImmutable to prevent mutation of original objects
        $dateStart = $dateStart instanceof DateTimeImmutable
            ? $dateStart->setTimezone($timezoneLocal)
            : DateTimeImmutable::createFromMutable($dateStart)->setTimezone($timezoneLocal);
        $dateEnd = $dateEnd instanceof DateTimeImmutable
            ? $dateEnd->setTimezone($timezoneLocal)
            : DateTimeImmutable::createFromMutable($dateEnd)->setTimezone($timezoneLocal);

        $d = '';
        $dates = [];
        $datas = [];

        while ($dateStart < $dateEnd) {
            switch ($this->getDataHelper()->getParam('period')) {
                case '24h':
                    $d = $dateStart->format('Y-m-d H:00');
                    $dateStart = $dateStart->modify('+1 hour');
                    break;
                case '7d':
                case '1m':
                    $d = $dateStart->format(Mage_Core_Model_Locale::DATE_FORMAT);
                    $dateStart = $dateStart->modify('+1 day');
                    break;
                case '3m':
                case '6m':
                    // For 3m/6m, the database groups by month (Y-m format)
                    // We need to match that format and skip to next month
                    $d = $dateStart->format('Y-m');
                    $dateStart = $dateStart->modify('+1 month');
                    break;
                case '1y':
                case '2y':
                    $d = $dateStart->format('Y-m');
                    $dateStart = $dateStart->modify('+1 month');
                    break;
            }
            foreach (array_keys($this->getAllSeries()) as $index) {
                if (in_array($d, $this->_axisLabels['x'])) {
                    $datas[$index][] = (float) array_shift($this->_allSeries[$index]);
                } else {
                    $datas[$index][] = 0;
                }
            }
            $dates[] = $d;
        }

        // setting skip step
        if (count($dates) > 8 && count($dates) < 15) {
            $c = 1;
        } elseif (count($dates) >= 15) {
            $c = 2;
        } else {
            $c = 0;
        }

        $this->_axisLabels['x'] = $dates;
        $this->_allSeries = $datas;

        // Image-Charts Awesome data format values
        $params['chd'] = 'a:';
        $dataDelimiter = ',';
        $dataSetdelimiter = '|';
        $dataMissing = '_';
        $localmaxlength = [];
        $localmaxvalue = [];
        $localminvalue = [];

        // process each string in the array, and find the max length
        foreach ($this->getAllSeries() as $index => $serie) {
            $localmaxlength[$index] = count($serie);
            $localmaxvalue[$index] = max($serie);
            $localminvalue[$index] = min($serie);
        }

        if (is_numeric($this->_max)) {
            $maxvalue = $this->_max;
        } else {
            $maxvalue = max($localmaxvalue);
        }
        if (is_numeric($this->_min)) {
            $minvalue = $this->_min;
        } else {
            $minvalue = min($localminvalue);
        }

        // default values
        $yLabels = [];
        $miny = 0;
        $maxy = 0;
        $yorigin = 0;

        if ($minvalue >= 0 && $maxvalue >= 0) {
            $miny = 0;
            if ($maxvalue > 10) {
                $p = 10 ** $this->_getPow($maxvalue);
                $maxy = (ceil($maxvalue / $p)) * $p;
                $yLabels = range($miny, $maxy, $p);
            } else {
                $maxy = ceil($maxvalue + 1);
                $yLabels = range($miny, $maxy, 1);
            }
        }

        $chartdata = [];

        foreach ($this->getAllSeries() as $serie) {
            $thisdataarray = $serie;
            $thisdataarrayCount = count($thisdataarray);
            for ($j = 0; $j < $thisdataarrayCount; $j++) {
                $currentvalue = $thisdataarray[$j];
                if (is_numeric($currentvalue)) {
                    $ylocation = $yorigin + $currentvalue;
                    $chartdata[] = $ylocation . $dataDelimiter;
                } else {
                    $chartdata[] = $dataMissing . $dataDelimiter;
                }
            }
            $chartdata[] = $dataSetdelimiter;
        }
        $buffer = implode('', $chartdata);

        $buffer = rtrim($buffer, $dataSetdelimiter);
        $buffer = rtrim($buffer, $dataDelimiter);
        $buffer = str_replace(($dataDelimiter . $dataSetdelimiter), $dataSetdelimiter, $buffer);

        $params['chd'] .= $buffer;

        $valueBuffer = [];

        if (count($this->_axisLabels)) {
            $params['chxt'] = implode(',', array_keys($this->_axisLabels));
            $indexid = 0;
            foreach (array_keys($this->_axisLabels) as $idx) {
                if ($idx === 'x') {
                    // format date
                    foreach ($this->_axisLabels[$idx] as $_index => $_label) {
                        if ($_label != '') {
                            switch ($this->getDataHelper()->getParam('period')) {
                                case '24h':
                                    $dateTime = DateTime::createFromFormat('Y-m-d H:00', $_label) ?: new DateTime($_label);
                                    $this->_axisLabels[$idx][$_index] = $this->formatTime(
                                        $dateTime,
                                        'short',
                                    );
                                    break;
                                case '7d':
                                case '1m':
                                    $this->_axisLabels[$idx][$_index] = $this->formatDate(
                                        DateTime::createFromFormat(Mage_Core_Model_Locale::DATE_FORMAT, $_label) ?: new DateTime($_label),
                                    );
                                    break;
                                case '1y':
                                case '2y':
                                    $formats = Mage::app()->getLocale()->getTranslationList('datetime');
                                    $format = $formats['yyMM'] ?? 'MM/yyyy';
                                    $format = str_replace(['yyyy', 'yy', 'MM'], ['Y', 'y', 'm'], $format);
                                    $this->_axisLabels[$idx][$_index] = date($format, strtotime($_label));
                                    break;
                            }
                        } else {
                            $this->_axisLabels[$idx][$_index] = '';
                        }
                    }

                    $tmpstring = implode('|', $this->_axisLabels[$idx]);

                    $valueBuffer[] = $indexid . ':|' . $tmpstring;
                    if (count($this->_axisLabels[$idx]) > 1) {
                        $deltaX = 100 / (count($this->_axisLabels[$idx]) - 1);
                    } else {
                        $deltaX = 100;
                    }
                } elseif ($idx === 'y') {
                    $valueBuffer[] = $indexid . ':|' . implode('|', $yLabels);
                    if (count($yLabels) - 1) {
                        $deltaY = 100 / (count($yLabels) - 1);
                    } else {
                        $deltaY = 100;
                    }
                }
                $indexid++;
            }
            $params['chxl'] = implode('|', $valueBuffer);
        }

        return $params;
    }

    /**
     * Get rows data
     *
     * @param array $attributes
     * @param bool $single
     * @return array
     */
    protected function getRowsData($attributes, $single = false)
    {
        $items = $this->getCollection()->getItems();
        $options = [];
        foreach ($items as $item) {
            if ($single) {
                $options[] = max(0, $item->getData($attributes));
            } else {
                foreach ((array) $attributes as $attr) {
                    $options[$attr][] = max(0, $item->getData($attr));
                }
            }
        }
        return $options;
    }

    /**
     * Set axis labels
     *
     * @param string $axis
     * @param array $labels
     */
    public function setAxisLabels($axis, $labels)
    {
        $this->_axisLabels[$axis] = $labels;
    }

    /**
     * Set html id
     *
     * @param string $htmlId
     */
    public function setHtmlId($htmlId)
    {
        $this->_htmlId = $htmlId;
    }

    /**
     * Get html id
     *
     * @return string
     */
    #[\Override]
    public function getHtmlId()
    {
        return $this->_htmlId;
    }

    /**
     * Return pow
     *
     * @param int $number
     * @return int
     */
    protected function _getPow($number)
    {
        $pow = 0;
        while ($number >= 10) {
            $number = $number / 10;
            $pow++;
        }
        return $pow;
    }

    /**
     * Return chart width
     *
     * @return string
     */
    protected function getWidth()
    {
        return $this->_width;
    }

    /**
     * Return chart height
     *
     * @return string
     */
    protected function getHeight()
    {
        return $this->_height;
    }

    /**
     * Prepare chart data
     *
     * @return void
     * @throws Exception
     */
    #[\Override]
    protected function _prepareData()
    {
        /** @var Mage_Adminhtml_Helper_Dashboard_Data $helper */
        $helper = $this->helper('adminhtml/dashboard_data');
        $availablePeriods = array_keys($helper->getDatePeriods());
        $period = $this->getRequest()->getParam('period');

        $this->getDataHelper()->setParam(
            'period',
            ($period && in_array($period, $availablePeriods)) ? $period : '24h',
        );
    }
}
