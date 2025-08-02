<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Reviews extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_customer_reviews');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Review Activity'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'has_reviewed' => Mage::helper('customersegmentation')->__('Has Written Reviews'),
            'review_count' => Mage::helper('customersegmentation')->__('Number of Reviews Written'),
            'average_rating_given' => Mage::helper('customersegmentation')->__('Average Rating Given'),
            'last_review_date' => Mage::helper('customersegmentation')->__('Last Review Date'),
            'days_since_last_review' => Mage::helper('customersegmentation')->__('Days Since Last Review'),
            'approved_review_count' => Mage::helper('customersegmentation')->__('Number of Approved Reviews'),
            'helpful_votes_received' => Mage::helper('customersegmentation')->__('Helpful Votes Received'),
            'review_product_count' => Mage::helper('customersegmentation')->__('Number of Different Products Reviewed'),
            'has_photos_in_reviews' => Mage::helper('customersegmentation')->__('Has Uploaded Photos in Reviews'),
            'review_length_average' => Mage::helper('customersegmentation')->__('Average Review Length (words)'),
        ];

        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'has_reviewed', 'has_photos_in_reviews' => 'select',
            'review_count', 'average_rating_given', 'days_since_last_review', 'approved_review_count', 'helpful_votes_received', 'review_product_count', 'review_length_average' => 'numeric',
            'last_review_date' => 'date',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'has_reviewed', 'has_photos_in_reviews' => 'select',
            'last_review_date' => 'date',
            default => 'text',
        };
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        $options = [];
        $options = match ($this->getAttribute()) {
            'has_reviewed', 'has_photos_in_reviews' => [
                ['value' => '1', 'label' => Mage::helper('customersegmentation')->__('Yes')],
                ['value' => '0', 'label' => Mage::helper('customersegmentation')->__('No')],
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
            'has_reviewed' => $this->buildHasReviewedCondition($adapter, $operator, $value),
            'review_count' => $this->buildReviewCountCondition($adapter, $operator, $value),
            'average_rating_given' => $this->buildAverageRatingCondition($adapter, $operator, $value),
            'last_review_date' => $this->buildLastReviewDateCondition($adapter, $operator, $value),
            'days_since_last_review' => $this->buildDaysSinceLastReviewCondition($adapter, $operator, $value),
            'approved_review_count' => $this->buildApprovedReviewCountCondition($adapter, $operator, $value),
            'helpful_votes_received' => $this->buildHelpfulVotesCondition($adapter, $operator, $value),
            'review_product_count' => $this->buildReviewProductCountCondition($adapter, $operator, $value),
            'has_photos_in_reviews' => $this->buildHasPhotosCondition($adapter, $operator, $value),
            'review_length_average' => $this->buildReviewLengthCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function buildHasReviewedCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $reviewTable = $this->getReviewTable();
        $condition = 'e.entity_id IN (SELECT DISTINCT customer_id FROM ' . $reviewTable . ' WHERE customer_id IS NOT NULL)';

        if (($operator == '=' && $value == '0') || ($operator == '!=' && $value == '1')) {
            $condition = 'e.entity_id NOT IN (SELECT DISTINCT customer_id FROM ' . $reviewTable . ' WHERE customer_id IS NOT NULL)';
        }

        return $condition;
    }

    protected function buildReviewCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $reviewTable = $this->getReviewTable();
        $subselect = $adapter->select()
            ->from(['r' => $reviewTable], ['customer_id', 'review_count' => 'COUNT(*)'])
            ->where('r.customer_id IS NOT NULL')
            ->group('r.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'review_count', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildAverageRatingCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $reviewTable = $this->getReviewTable();
        $ratingTable = $this->getRatingVoteTable();
        $ratingOptionTable = $this->getRatingOptionTable();

        $subselect = $adapter->select()
            ->from(['r' => $reviewTable], ['customer_id'])
            ->join(['rv' => $ratingTable], 'r.review_id = rv.review_id', [])
            ->join(['ro' => $ratingOptionTable], 'rv.option_id = ro.option_id', ['avg_rating' => 'AVG(ro.value)'])
            ->where('r.customer_id IS NOT NULL')
            ->group('r.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'avg_rating', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildLastReviewDateCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $reviewTable = $this->getReviewTable();
        $subselect = $adapter->select()
            ->from(['r' => $reviewTable], ['customer_id', 'last_review' => 'MAX(r.created_at)'])
            ->where('r.customer_id IS NOT NULL')
            ->group('r.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'last_review', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildDaysSinceLastReviewCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $reviewTable = $this->getReviewTable();
        $subselect = $adapter->select()
            ->from(['r' => $reviewTable], ['customer_id', 'last_review' => 'MAX(r.created_at)'])
            ->where('r.customer_id IS NOT NULL')
            ->group('r.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'DATEDIFF(NOW(), last_review)', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildApprovedReviewCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $reviewTable = $this->getReviewTable();
        $subselect = $adapter->select()
            ->from(['r' => $reviewTable], ['customer_id', 'approved_count' => 'COUNT(*)'])
            ->where('r.customer_id IS NOT NULL')
            ->where('r.status_id = ?', Mage_Review_Model_Review::STATUS_APPROVED)
            ->group('r.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'approved_count', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildHelpfulVotesCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        // This would require a review voting/helpful system
        // For now, we'll use review detail table if it has helpful votes
        $reviewTable = $this->getReviewTable();
        $detailTable = $this->getReviewDetailTable();

        $subselect = $adapter->select()
            ->from(['r' => $reviewTable], ['customer_id'])
            ->join(['rd' => $detailTable], 'r.review_id = rd.review_id', ['helpful_votes' => 'SUM(COALESCE(rd.helpful_count, 0))'])
            ->where('r.customer_id IS NOT NULL')
            ->group('r.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'helpful_votes', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildReviewProductCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $reviewTable = $this->getReviewTable();
        $subselect = $adapter->select()
            ->from(['r' => $reviewTable], ['customer_id', 'product_count' => 'COUNT(DISTINCT r.entity_pk_value)'])
            ->where('r.customer_id IS NOT NULL')
            ->where('r.entity_id = ?', Mage::getModel('review/review')->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
            ->group('r.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'product_count', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildHasPhotosCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        // This would require custom implementation for review photos
        // For now, return a placeholder condition
        return '1=1';
    }

    protected function buildReviewLengthCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $reviewTable = $this->getReviewTable();
        $detailTable = $this->getReviewDetailTable();

        $subselect = $adapter->select()
            ->from(['r' => $reviewTable], ['customer_id'])
            ->join(['rd' => $detailTable], 'r.review_id = rd.review_id', ['avg_length' => 'AVG(LENGTH(rd.detail) - LENGTH(REPLACE(rd.detail, " ", "")) + 1)'])
            ->where('r.customer_id IS NOT NULL')
            ->group('r.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'avg_length', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function getReviewTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('review/review');
    }

    protected function getReviewDetailTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('review/review_detail');
    }

    protected function getRatingVoteTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('rating/rating_option_vote');
    }

    protected function getRatingOptionTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('rating/rating_option');
    }
}
