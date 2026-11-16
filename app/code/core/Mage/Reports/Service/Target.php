<?php

/**
 * The monthly targets of the metrics of the report explorer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Service_Target
{
    public function newTarget(): Mage_Reports_Model_Target
    {
        return Mage::getModel('reports/target')->setStoreId(0);
    }

    /** @throws Mage_Core_Exception_NoSuchEntity */
    public function getById(int $id): Mage_Reports_Model_Target
    {
        $target = Mage::getModel('reports/target')->load($id);
        if ($target->getId() === null) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('reports')->__('The target with ID %s does not exist.', $id));
        }
        return $target;
    }

    /**
     * Check and save a target. A target of a metric that the caller may not read, or of a metric whose values do not
     * add up (such as an average), is refused. A second target for the same metric, store and month replaces the first.
     *
     * @throws Mage_Core_Exception_Input
     */
    public function save(Mage_Reports_Model_Target $target, Mage_Reports_Model_Explorer_Access $access): Mage_Reports_Model_Target
    {
        $helper = Mage::helper('reports');
        $errors = new Mage_Core_Exception_Input($helper->__('The target is not valid.'));
        $dataset = Mage::getSingleton('reports/explorer_registry')->getDataset((string) $target->getDataset());
        $metric = (string) $target->getMetric();
        if ($dataset === null || !$access->allowsDataset($dataset)) {
            $errors->addError('dataset', $helper->__('The dataset "%s" does not exist.', (string) $target->getDataset()));
        } elseif (!$dataset->hasMetric($metric) || $dataset->getMetric($metric)->hidden) {
            $errors->addError('metric', $helper->__('The metric "%s" does not exist.', $metric));
        } elseif (!$dataset->isAdditive($metric)) {
            $errors->addError('metric', $helper->__('A target needs a metric that adds up over the days, such as a sum or a count.'));
        }
        $month = DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $target->getMonth(), 0, 7) . '-01');
        if ($month === false || !preg_match('/^\d{4}-\d{2}(-\d{2})?$/', (string) $target->getMonth())) {
            $errors->addError('month', $helper->__('The month must be YYYY-MM.'));
        }
        $storeId = (int) $target->getStoreId();
        if ($storeId !== 0 && (!array_key_exists($storeId, Mage::app()->getStores()) || ($access->storeIds !== null && !in_array($storeId, $access->storeIds, true)))) {
            $errors->addError('storeId', $helper->__('The store view with ID %d does not exist.', $storeId));
        }
        if ($target->getValue() === null || !is_finite($target->getValue())) {
            $errors->addError('value', $helper->__('The target value is required.'));
        }
        $errors->throwIfErrors();

        $existing = Mage::getResourceModel('reports/target_collection')
            ->addFieldToFilter('dataset', $target->getDataset())
            ->addFieldToFilter('metric', $metric)
            ->addFieldToFilter('store_id', $storeId)
            ->addFieldToFilter('month', $month->format('Y-m-d'))
            ->getFirstItem();
        if ($existing instanceof Mage_Reports_Model_Target && $existing->getId() && (int) $existing->getId() !== (int) $target->getId()) {
            $existing->delete();
        }
        $target->setMonth($month->format('Y-m-d'))->setStoreId($storeId)->save();
        return $target;
    }

    public function delete(Mage_Reports_Model_Target $target): void
    {
        $target->delete();
    }

    /**
     * The targets of a metric by month, for one store view or for every store view with store 0.
     *
     * @return array<string, float> the values by month, as Y-m
     */
    public function monthly(string $dataset, string $metric, int $storeId): array
    {
        $values = [];
        $collection = Mage::getResourceModel('reports/target_collection')
            ->addFieldToFilter('dataset', $dataset)
            ->addFieldToFilter('metric', $metric)
            ->addFieldToFilter('store_id', $storeId);
        foreach ($collection as $target) {
            if ($target instanceof Mage_Reports_Model_Target) {
                $values[substr((string) $target->getMonth(), 0, 7)] = (float) $target->getValue();
            }
        }
        return $values;
    }

    /**
     * Every target of a dataset, newest month first.
     *
     * @return list<Mage_Reports_Model_Target>
     */
    public function listFor(string $dataset, ?string $metric = null): array
    {
        $collection = Mage::getResourceModel('reports/target_collection')
            ->addFieldToFilter('dataset', $dataset)
            ->setOrder('month', 'DESC');
        if ($metric !== null) {
            $collection->addFieldToFilter('metric', $metric);
        }
        return array_values(array_filter($collection->getItems(), static fn($item): bool => $item instanceof Mage_Reports_Model_Target));
    }
}
