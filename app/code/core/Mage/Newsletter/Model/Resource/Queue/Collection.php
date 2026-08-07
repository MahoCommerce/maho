<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

class Mage_Newsletter_Model_Resource_Queue_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * True when subscribers info joined
     *
     * @var bool
     */
    protected $_addSubscribersFlag   = false;

    /**
     * True when filtered by store
     *
     * @var bool
     */
    protected $_isStoreFilter        = false;

    /**
     * Initializes collection
     */
    #[\Override]
    protected function _construct()
    {
        $this->_map['fields']['queue_id'] = 'main_table.queue_id';
        $this->_init('newsletter/queue');
    }

    /**
     * @return $this
     */
    public function addTemplateInfo()
    {
        $this->getSelect()->joinLeft(
            ['template' => $this->getTable('template')],
            'template.template_id=main_table.template_id',
            ['template_subject','template_sender_name','template_sender_email'],
        );
        $this->_joinedTables['template'] = true;
        return $this;
    }

    /**
     * Adds subscribers info to selelect
     *
     * @return $this
     */
    protected function _addSubscriberInfoToSelect()
    {
        $this->getSelect()->columns([
            'subscribers_sent'  => new Maho\Db\Expr($this->_getSubscribersSentExpr('main_table.queue_id')),
            'subscribers_total' => new Maho\Db\Expr(
                $this->_getSubscribersTotalExpr('main_table.queue_id', 'main_table.queue_status'),
            ),
        ]);
        return $this;
    }

    /**
     * Recipients already mailed, as a scalar correlated on $queueIdColumn.
     */
    protected function _getSubscribersSentExpr(string $queueIdColumn): string
    {
        $select = $this->getConnection()->select()
            ->from(['qls' => $this->getTable('newsletter/queue_link')], 'COUNT(qls.queue_link_id)')
            ->where("qls.queue_id = $queueIdColumn")
            ->where('qls.letter_sent_at IS NOT NULL');

        return sprintf('(%s)', $select->assemble());
    }

    /**
     * Recipients are only linked once the first batch goes out, so a campaign
     * with nothing linked yet reports the audience it would reach today. The
     * grid column and the grid filter both come from here, or they would
     * disagree about the very same number.
     */
    protected function _getSubscribersTotalExpr(string $queueIdColumn, string $statusColumn): string
    {
        $linked = sprintf('(%s)', $this->getConnection()->select()
            ->from(['qlt' => $this->getTable('newsletter/queue_link')], 'COUNT(qlt.queue_link_id)')
            ->where("qlt.queue_id = $queueIdColumn")
            ->assemble());

        $projected = sprintf('(%s)', $this->getConnection()->select()
            ->from(['qsl' => $this->getTable('newsletter/queue_store_link')], 'COUNT(*)')
            ->join(['sub' => $this->getTable('newsletter/subscriber')], 'sub.store_id = qsl.store_id', [])
            ->where("qsl.queue_id = $queueIdColumn")
            ->where('sub.subscriber_status = ?', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
            ->assemble());

        return sprintf(
            '(CASE WHEN %s = %d AND %s = 0 THEN %s ELSE %s END)',
            $statusColumn,
            Mage_Newsletter_Model_Queue::STATUS_NEVER,
            $linked,
            $projected,
            $linked,
        );
    }

    /**
     * Adds subscribers info to select and loads collection
     */
    #[\Override]
    public function load($printQuery = false, $logQuery = false)
    {
        if ($this->_addSubscribersFlag && !$this->isLoaded()) {
            $this->_addSubscriberInfoToSelect();
        }
        return parent::load($printQuery, $logQuery);
    }

    /**
     * Joines subscribers information
     *
     * @return $this
     */
    public function addSubscribersInfo()
    {
        $this->_addSubscribersFlag = true;
        return $this;
    }

    /**
     * Checks if field is 'subscribers_total', 'subscribers_sent'
     * to add specific filter or adds reguler filter
     */
    #[\Override]
    public function addFieldToFilter($field, $condition = null)
    {
        if (in_array($field, ['subscribers_total', 'subscribers_sent'])) {
            $this->addFieldToFilter('main_table.queue_id', ['in' => $this->_getIdsFromLink($field, $condition)]);
            return $this;
        }
        return parent::addFieldToFilter($field, $condition);
    }

    /**
     * Campaign ids whose recipient count matches, using the same expressions the
     * grid displays: counting queue_link rows here would hide every campaign
     * that has not started yet, the ones showing a projected audience.
     *
     * @param string $field
     * @param mixed $condition
     * @return array
     */
    protected function _getIdsFromLink($field, $condition)
    {
        $expr = $field === 'subscribers_sent'
            ? $this->_getSubscribersSentExpr('q.queue_id')
            : $this->_getSubscribersTotalExpr('q.queue_id', 'q.queue_status');

        $select = $this->getConnection()->select()
            ->from(['q' => $this->getMainTable()], 'queue_id')
            ->where($this->_getConditionSql($expr, $condition));

        $idList = $this->getConnection()->fetchCol($select);

        if (count($idList)) {
            return $idList;
        }

        return [0];
    }

    /**
     * Set filter for queue by subscriber.
     *
     * @param int $subscriberId
     * @return $this
     */
    public function addSubscriberFilter($subscriberId)
    {
        $this->getSelect()->join(
            ['link' => $this->getTable('newsletter/queue_link')],
            'main_table.queue_id=link.queue_id',
            ['letter_sent_at'],
        )
        ->where('link.subscriber_id = ?', $subscriberId);

        return $this;
    }

    /**
     * Add filter by only ready for sending item
     *
     * @return $this
     */
    public function addOnlyForSendingFilter()
    {
        $this->getSelect()
            ->where('main_table.queue_status in (?)', [Mage_Newsletter_Model_Queue::STATUS_SENDING,
                Mage_Newsletter_Model_Queue::STATUS_NEVER])
            ->where('main_table.queue_start_at <= ?', Mage::app()->getLocale()->formatDateForDb('now'))
            ->where('main_table.queue_start_at IS NOT NULL');

        return $this;
    }

    /**
     * Add filter by only not sent items
     *
     * @return $this
     */
    public function addOnlyUnsentFilter()
    {
        $this->addFieldToFilter('main_table.queue_status', Mage_Newsletter_Model_Queue::STATUS_NEVER);

        return $this;
    }

    #[\Override]
    public function toOptionArray(): array
    {
        return $this->_toOptionArray('queue_id', 'template_subject');
    }

    /**
     * Filter collection by specified store ids
     *
     * @param array|int $storeIds
     * @return $this
     */
    public function addStoreFilter($storeIds)
    {
        if (!$this->_isStoreFilter) {
            $this->getSelect()->joinInner(
                ['store_link' => $this->getTable('newsletter/queue_store_link')],
                'main_table.queue_id = store_link.queue_id',
                [],
            )
            ->where('store_link.store_id IN (?)', $storeIds)
            ->group('main_table.queue_id');
            $this->_isStoreFilter = true;
        }
        return $this;
    }
}
