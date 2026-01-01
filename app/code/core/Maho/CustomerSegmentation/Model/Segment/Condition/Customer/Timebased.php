<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Timebased extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    protected $_inputType = 'numeric';

    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_customer_timebased');
        $this->setValue(null);
        $this->loadAttributeOptions();
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return ['value' => $this->getType(), 'label' => Mage::helper('customersegmentation')->__('Customer Time-based')];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'days_since_last_login' => Mage::helper('customersegmentation')->__('Days Since Last Login'),
            'days_since_last_order' => Mage::helper('customersegmentation')->__('Days Since Last Order'),
            'days_inactive' => Mage::helper('customersegmentation')->__('Days Inactive (No Login or Order)'),
            'days_since_first_order' => Mage::helper('customersegmentation')->__('Days Since First Order'),
            'order_frequency_days' => Mage::helper('customersegmentation')->__('Average Days Between Orders'),
            'days_without_purchase' => Mage::helper('customersegmentation')->__('Days Without Purchase'),
        ];

        asort($attributes);
        $this->setAttributeOption($attributes);
        return $this;
    }


    #[\Override]
    public function getValueElementType(): string
    {
        return 'text';
    }


    #[\Override]
    public function getInputType(): string
    {
        return 'numeric';
    }

    #[\Override]
    public function getValueElementHtml(): string
    {
        $html = parent::getValueElementHtml();
        $html .= ' ' . Mage::helper('customersegmentation')->__('days');
        return $html;
    }

    #[\Override]
    public function getAttributeName(): string
    {
        $attributeName = parent::getAttributeName();
        return Mage::helper('customersegmentation')->__('Customer Time-based') . ':' . ' ' . $attributeName;
    }

    #[\Override]
    public function getConditionsSql(\Maho\Db\Adapter\AdapterInterface $adapter, ?int $websiteId = null): string|false
    {
        return $this->getSubfilterSql('e.entity_id', true, $websiteId);
    }

    public function getSubfilterSql(string $fieldName, bool $requireValid, ?int $website): string
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = (int) $this->getValue();

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $now = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

        switch ($attribute) {
            case 'days_since_last_login':
                $logTable = $resource->getTableName('log/customer');
                $dateDiff = $adapter->getDateDiffSql("'{$now}'", 'MAX(l.login_at)');
                $select = $adapter->select()
                    ->from(['l' => $logTable], ['customer_id', 'days' => $dateDiff])
                    ->where('l.customer_id IS NOT NULL')
                    ->group('l.customer_id')
                    ->having("days {$operator} {$value}");
                break;

            case 'days_since_last_order':
                $orderTable = $resource->getTableName('sales/order');
                $dateDiff = $adapter->getDateDiffSql("'{$now}'", 'MAX(o.created_at)');
                $select = $adapter->select()
                    ->from(['o' => $orderTable], ['customer_id', 'days' => $dateDiff])
                    ->where('o.customer_id IS NOT NULL')
                    ->where('o.state NOT IN (?)', ['canceled'])
                    ->group('o.customer_id')
                    ->having("days {$operator} {$value}");

                if ($website) {
                    $select->where('o.store_id IN (?)', Mage::app()->getWebsite($website)->getStoreIds());
                }
                break;

            case 'days_inactive':
                $logTable = $resource->getTableName('log/customer');
                $orderTable = $resource->getTableName('sales/order');
                $customerTable = $resource->getTableName('customer/entity');

                // Get the most recent activity (login or order), using registration date as fallback
                $dateDiff = $adapter->getDateDiffSql("'{$now}'", 'GREATEST(COALESCE(MAX(l.login_at), MAX(c.created_at)), COALESCE(MAX(o.created_at), MAX(c.created_at)))');
                $select = $adapter->select()
                    ->from(['c' => $customerTable], ['entity_id'])
                    ->where('c.created_at IS NOT NULL') // Exclude customers with invalid registration dates
                    ->joinLeft(
                        ['l' => $logTable],
                        'c.entity_id = l.customer_id',
                        ['last_login' => 'MAX(l.login_at)'],
                    )
                    ->joinLeft(
                        ['o' => $orderTable],
                        "c.entity_id = o.customer_id AND o.state NOT IN ('canceled')",
                        ['last_order' => 'MAX(o.created_at)'],
                    )
                    ->columns([
                        'customer_id' => 'c.entity_id',
                        // Use MAX(c.created_at) to satisfy ONLY_FULL_GROUP_BY mode - there's only one created_at per customer anyway
                        'days' => $dateDiff,
                    ])
                    ->group('c.entity_id')
                    ->having("days {$operator} {$value}");

                if ($website) {
                    $select->where('o.store_id IN (?) OR o.store_id IS NULL', Mage::app()->getWebsite($website)->getStoreIds());
                }
                break;

            case 'days_since_first_order':
                $orderTable = $resource->getTableName('sales/order');
                $dateDiff = $adapter->getDateDiffSql("'{$now}'", 'MIN(o.created_at)');
                $select = $adapter->select()
                    ->from(['o' => $orderTable], ['customer_id', 'days' => $dateDiff])
                    ->where('o.customer_id IS NOT NULL')
                    ->where('o.state NOT IN (?)', ['canceled'])
                    ->group('o.customer_id')
                    ->having("days {$operator} {$value}");

                if ($website) {
                    $select->where('o.store_id IN (?)', Mage::app()->getWebsite($website)->getStoreIds());
                }
                break;

            case 'order_frequency_days':
                $orderTable = $resource->getTableName('sales/order');
                // Calculate average days between orders
                $dateDiff = $adapter->getDateDiffSql('MAX(o.created_at)', 'MIN(o.created_at)');
                $select = $adapter->select()
                    ->from(['o' => $orderTable], [
                        'customer_id',
                        'days' => new Maho\Db\Expr("({$dateDiff}) / GREATEST(COUNT(*) - 1, 1)"),
                    ])
                    ->where('o.customer_id IS NOT NULL')
                    ->where('o.state NOT IN (?)', ['canceled'])
                    ->group('o.customer_id')
                    ->having('COUNT(*) > 1')  // Need at least 2 orders to calculate frequency
                    ->having("days {$operator} {$value}");

                if ($website) {
                    $select->where('o.store_id IN (?)', Mage::app()->getWebsite($website)->getStoreIds());
                }
                break;

            case 'days_without_purchase':
                $orderTable = $resource->getTableName('sales/order');
                $customerTable = $resource->getTableName('customer/entity');

                // Get customers who haven't purchased in X days
                $lastOrderSelect = $adapter->select()
                    ->from(['o' => $orderTable], ['customer_id', 'last_order' => 'MAX(o.created_at)'])
                    ->where('o.customer_id IS NOT NULL')
                    ->where('o.state NOT IN (?)', ['canceled'])
                    ->group('o.customer_id');

                if ($website) {
                    $lastOrderSelect->where('o.store_id IN (?)', Mage::app()->getWebsite($website)->getStoreIds());
                }

                $dateDiff = $adapter->getDateDiffSql("'{$now}'", 'lo.last_order');
                $select = $adapter->select()
                    ->from(['lo' => new Maho\Db\Expr("({$lastOrderSelect})")], ['customer_id'])
                    ->where("{$dateDiff} {$operator} {$value}");

                // Also include customers with no orders if operator allows
                if (in_array($operator, ['>=', '>'])) {
                    $noOrderSelect = $adapter->select()
                        ->from(['c' => $customerTable], ['entity_id'])
                        ->joinLeft(
                            ['o' => $orderTable],
                            "c.entity_id = o.customer_id AND o.state NOT IN ('canceled')",
                            [],
                        )
                        ->where('o.entity_id IS NULL');

                    $unionSelect = $adapter->select()->union([$select, $noOrderSelect]);
                    $select = $adapter->select()
                        ->from(['u' => new Maho\Db\Expr("({$unionSelect})")], ['customer_id']);
                }
                break;

            default:
                return $requireValid ? 'FALSE' : 'TRUE';
        }

        // Build the final condition
        $customerIds = $adapter->select()
            ->from(['timedata' => new Maho\Db\Expr("({$select})")], ['customer_id']);

        if ($requireValid) {
            return $adapter->quoteInto("{$fieldName} IN (?)", new Maho\Db\Expr((string) $customerIds));
        }
        return $adapter->quoteInto("{$fieldName} NOT IN (?) OR {$fieldName} IS NULL", new Maho\Db\Expr((string) $customerIds));
    }

    #[\Override]
    public function asHtml(): string
    {
        $html = $this->getTypeElement()->getHtml()
            . Mage::helper('customersegmentation')->__(
                'If %s %s %s',
                $this->getAttributeElement()->getHtml(),
                $this->getOperatorElement()->getHtml(),
                $this->getValueElement()->getHtml(),
            );

        if ($this->getId() != '1') {
            $html .= $this->getRemoveLinkHtml();
        }

        return $html;
    }
}
