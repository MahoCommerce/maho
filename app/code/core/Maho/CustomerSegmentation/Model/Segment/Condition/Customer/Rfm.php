<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Rfm extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_customer_rfm');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('RFM Analysis'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'recency_score' => Mage::helper('customersegmentation')->__('Recency Score (1-5)'),
            'recency_days' => Mage::helper('customersegmentation')->__('Days Since Last Purchase'),
            'frequency_score' => Mage::helper('customersegmentation')->__('Frequency Score (1-5)'),
            'frequency_count' => Mage::helper('customersegmentation')->__('Total Number of Orders'),
            'monetary_score' => Mage::helper('customersegmentation')->__('Monetary Score (1-5)'),
            'monetary_value' => Mage::helper('customersegmentation')->__('Total Lifetime Value'),
            'rfm_score' => Mage::helper('customersegmentation')->__('Combined RFM Score'),
            'rfm_segment' => Mage::helper('customersegmentation')->__('RFM Segment'),
            'average_days_between_orders' => Mage::helper('customersegmentation')->__('Average Days Between Orders'),
            'customer_value_segment' => Mage::helper('customersegmentation')->__('Customer Value Segment'),
        ];

        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'recency_score', 'frequency_score', 'monetary_score', 'rfm_score', 'recency_days', 'frequency_count', 'monetary_value', 'average_days_between_orders' => 'numeric',
            'rfm_segment', 'customer_value_segment' => 'select',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'rfm_segment', 'customer_value_segment' => 'select',
            default => 'text',
        };
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        $options = [];
        $options = match ($this->getAttribute()) {
            'rfm_segment' => [
                ['value' => 'champions', 'label' => Mage::helper('customersegmentation')->__('Champions (555, 554, 544, 545, 454, 455, 445)')],
                ['value' => 'loyal_customers', 'label' => Mage::helper('customersegmentation')->__('Loyal Customers (543, 444, 435, 355, 354, 345, 344, 335)')],
                ['value' => 'potential_loyalists', 'label' => Mage::helper('customersegmentation')->__('Potential Loyalists (553, 551, 552, 541, 542, 533, 532, 531, 452, 451)')],
                ['value' => 'new_customers', 'label' => Mage::helper('customersegmentation')->__('New Customers (512, 511, 422, 421, 412, 411, 311)')],
                ['value' => 'promising', 'label' => Mage::helper('customersegmentation')->__('Promising (525, 524, 523, 522, 521, 515, 514, 513, 425, 424, 413, 414, 415, 315, 314, 313)')],
                ['value' => 'need_attention', 'label' => Mage::helper('customersegmentation')->__('Need Attention (535, 534, 443, 434, 343, 334, 325, 324)')],
                ['value' => 'about_to_sleep', 'label' => Mage::helper('customersegmentation')->__('About to Sleep (331, 321, 312, 221, 213, 231, 241, 251)')],
                ['value' => 'at_risk', 'label' => Mage::helper('customersegmentation')->__('At Risk (255, 254, 245, 244, 253, 252, 243, 242, 235, 234, 225, 224, 153, 152, 145, 143, 142, 135, 134, 133, 125, 124)')],
                ['value' => 'cant_lose', 'label' => Mage::helper('customersegmentation')->__('Cannot Lose Them (155, 154, 144, 214, 215, 115, 114, 113)')],
                ['value' => 'hibernating', 'label' => Mage::helper('customersegmentation')->__('Hibernating (332, 322, 231, 241, 151, 141, 131, 121, 122, 123, 132, 141, 151)')],
                ['value' => 'lost', 'label' => Mage::helper('customersegmentation')->__('Lost (111, 112, 121, 131, 141, 151)')],
            ],
            'customer_value_segment' => [
                ['value' => 'high_value', 'label' => Mage::helper('customersegmentation')->__('High Value')],
                ['value' => 'medium_value', 'label' => Mage::helper('customersegmentation')->__('Medium Value')],
                ['value' => 'low_value', 'label' => Mage::helper('customersegmentation')->__('Low Value')],
            ],
            default => $options,
        };
        return $options;
    }

    #[\Override]
    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();
        return match ($attribute) {
            'recency_score' => $this->buildRecencyScoreCondition($adapter, $operator, $value),
            'recency_days' => $this->buildRecencyDaysCondition($adapter, $operator, $value),
            'frequency_score' => $this->buildFrequencyScoreCondition($adapter, $operator, $value),
            'frequency_count' => $this->buildFrequencyCountCondition($adapter, $operator, $value),
            'monetary_score' => $this->buildMonetaryScoreCondition($adapter, $operator, $value),
            'monetary_value' => $this->buildMonetaryValueCondition($adapter, $operator, $value),
            'rfm_score' => $this->buildRfmScoreCondition($adapter, $operator, $value),
            'rfm_segment' => $this->buildRfmSegmentCondition($adapter, $operator, $value),
            'average_days_between_orders' => $this->buildAverageDaysBetweenOrdersCondition($adapter, $operator, $value),
            'customer_value_segment' => $this->buildCustomerValueSegmentCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function buildRecencyScoreCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        // Calculate recency score based on quintiles
        $recencySubselect = $this->getRecencyScoreSubselect($adapter);

        $subselect = $adapter->select()
            ->from(['rfm' => $recencySubselect], ['customer_id'])
            ->where($this->_buildSqlCondition($adapter, 'rfm.recency_score', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildRecencyDaysCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id', 'days_since' => 'DATEDIFF(NOW(), MAX(o.created_at))'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'days_since', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildFrequencyScoreCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $frequencySubselect = $this->getFrequencyScoreSubselect($adapter);

        $subselect = $adapter->select()
            ->from(['rfm' => $frequencySubselect], ['customer_id'])
            ->where($this->_buildSqlCondition($adapter, 'rfm.frequency_score', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildFrequencyCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id', 'order_count' => 'COUNT(*)'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'order_count', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildMonetaryScoreCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $monetarySubselect = $this->getMonetaryScoreSubselect($adapter);

        $subselect = $adapter->select()
            ->from(['rfm' => $monetarySubselect], ['customer_id'])
            ->where($this->_buildSqlCondition($adapter, 'rfm.monetary_score', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildMonetaryValueCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id', 'total_value' => 'SUM(o.grand_total)'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'total_value', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildRfmScoreCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $rfmSubselect = $this->getCombinedRfmScoreSubselect($adapter);

        $subselect = $adapter->select()
            ->from(['rfm' => $rfmSubselect], ['customer_id'])
            ->where($this->_buildSqlCondition($adapter, 'rfm.rfm_score', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildRfmSegmentCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $rfmSubselect = $this->getCombinedRfmScoreSubselect($adapter);

        // Map RFM scores to segments
        $segmentMap = $this->getRfmSegmentMap();
        $scores = $segmentMap[$value] ?? [];

        if (empty($scores)) {
            return '1=0'; // No matching segment
        }

        $subselect = $adapter->select()
            ->from(['rfm' => $rfmSubselect], ['customer_id'])
            ->where('CONCAT(rfm.recency_score, rfm.frequency_score, rfm.monetary_score) IN (?)', $scores);

        if ($operator == '!=') {
            return 'e.entity_id NOT IN (' . $subselect . ')';
        }

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildAverageDaysBetweenOrdersCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        // Calculate average days between orders for customers with 2+ orders
        $subselect = $adapter->select()
            ->from(['o1' => $this->getOrderTable()], ['customer_id'])
            ->join(
                ['o2' => $this->getOrderTable()],
                'o1.customer_id = o2.customer_id AND o1.created_at < o2.created_at',
                [],
            )
            ->where('o1.customer_id IS NOT NULL')
            ->where('o1.state NOT IN (?)', ['canceled'])
            ->where('o2.state NOT IN (?)', ['canceled'])
            ->group('o1.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'AVG(DATEDIFF(o2.created_at, o1.created_at))', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildCustomerValueSegmentCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        // Define value thresholds (could be made configurable)
        $highValueThreshold = 1000;
        $mediumValueThreshold = 300;

        $monetarySubselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id', 'total_value' => 'SUM(o.grand_total)'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id');

        $condition = '';
        switch ($value) {
            case 'high_value':
                $condition = 'total_value >= ' . $highValueThreshold;
                break;
            case 'medium_value':
                $condition = 'total_value >= ' . $mediumValueThreshold . ' AND total_value < ' . $highValueThreshold;
                break;
            case 'low_value':
                $condition = 'total_value < ' . $mediumValueThreshold;
                break;
        }

        $subselect = $adapter->select()
            ->from(['m' => $monetarySubselect], ['customer_id'])
            ->where($condition);

        if ($operator == '!=') {
            return 'e.entity_id NOT IN (' . $subselect . ')';
        }

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function getRecencyScoreSubselect(Varien_Db_Adapter_Interface $adapter): Zend_Db_Select
    {
        return $adapter->select()
            ->from(['o' => $this->getOrderTable()], [
                'customer_id',
                'recency_score' => 'NTILE(5) OVER (ORDER BY MAX(o.created_at) DESC)',
            ])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id');
    }

    protected function getFrequencyScoreSubselect(Varien_Db_Adapter_Interface $adapter): Zend_Db_Select
    {
        return $adapter->select()
            ->from(['o' => $this->getOrderTable()], [
                'customer_id',
                'frequency_score' => 'NTILE(5) OVER (ORDER BY COUNT(*) DESC)',
            ])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id');
    }

    protected function getMonetaryScoreSubselect(Varien_Db_Adapter_Interface $adapter): Zend_Db_Select
    {
        return $adapter->select()
            ->from(['o' => $this->getOrderTable()], [
                'customer_id',
                'monetary_score' => 'NTILE(5) OVER (ORDER BY SUM(o.grand_total) DESC)',
            ])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id');
    }

    protected function getCombinedRfmScoreSubselect(Varien_Db_Adapter_Interface $adapter): Zend_Db_Select
    {
        $recency = $this->getRecencyScoreSubselect($adapter);
        $frequency = $this->getFrequencyScoreSubselect($adapter);
        $monetary = $this->getMonetaryScoreSubselect($adapter);

        return $adapter->select()
            ->from(['r' => $recency], ['customer_id', 'recency_score'])
            ->join(['f' => $frequency], 'r.customer_id = f.customer_id', ['frequency_score'])
            ->join(['m' => $monetary], 'r.customer_id = m.customer_id', ['monetary_score'])
            ->columns(['rfm_score' => 'r.recency_score * 100 + f.frequency_score * 10 + m.monetary_score']);
    }

    protected function getRfmSegmentMap(): array
    {
        return [
            'champions' => ['555', '554', '544', '545', '454', '455', '445'],
            'loyal_customers' => ['543', '444', '435', '355', '354', '345', '344', '335'],
            'potential_loyalists' => ['553', '551', '552', '541', '542', '533', '532', '531', '452', '451'],
            'new_customers' => ['512', '511', '422', '421', '412', '411', '311'],
            'promising' => ['525', '524', '523', '522', '521', '515', '514', '513', '425', '424', '413', '414', '415', '315', '314', '313'],
            'need_attention' => ['535', '534', '443', '434', '343', '334', '325', '324'],
            'about_to_sleep' => ['331', '321', '312', '221', '213', '231', '241', '251'],
            'at_risk' => ['255', '254', '245', '244', '253', '252', '243', '242', '235', '234', '225', '224', '153', '152', '145', '143', '142', '135', '134', '133', '125', '124'],
            'cant_lose' => ['155', '154', '144', '214', '215', '115', '114', '113'],
            'hibernating' => ['332', '322', '231', '241', '151', '141', '131', '121', '122', '123', '132', '141', '151'],
            'lost' => ['111', '112', '121', '131', '141', '151'],
        ];
    }
}
