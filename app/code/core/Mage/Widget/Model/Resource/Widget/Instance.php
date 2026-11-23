<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Widget
 */

/**
 * @phpstan-type LayoutLink array{store_id: int, area: string, package: string, theme: string}
 * @phpstan-type LayoutUpdate array{layout_update_id: int, handle: string, sort_order: int, xml: string, links: list<LayoutLink>}
 * @phpstan-type LayoutUpdateRemoval array{layout_update_id: int, handle: string, sort_order: int, xml: string, links: list<LayoutLink>, instance_id: int, type: string, reason: string}
 * @extends Mage_Core_Model_Resource_Db_Abstract<Mage_Widget_Model_Widget_Instance>
 */
class Mage_Widget_Model_Resource_Widget_Instance extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('widget/widget_instance', 'instance_id');
    }

    #[\Override]
    protected function _afterLoad(Mage_Core_Model_Abstract $object)
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getTable('widget/widget_instance_page'))
            ->where('instance_id = ?', (int) $object->getId());
        $result = $adapter->fetchAll($select);
        $object->setData('page_groups', $result);
        return parent::_afterLoad($object);
    }

    /**
     * @param Mage_Widget_Model_Widget_Instance $object
     * @throws Doctrine\DBAL\Exception
     */
    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        $pageTable         = $this->getTable('widget/widget_instance_page');
        $pageLayoutTable   = $this->getTable('widget/widget_instance_page_layout');
        $readAdapter       = $this->_getReadAdapter();
        $writeAdapter      = $this->_getWriteAdapter();

        $select = $readAdapter->select()
            ->from($pageTable, 'page_id')
            ->where('instance_id = ?', (int) $object->getId());
        $pageIds = $readAdapter->fetchCol($select);

        $removePageIds = array_diff($pageIds, $object->getData('page_group_ids'));

        if (is_array($pageIds) && count($pageIds) > 0) {
            $inCond = $readAdapter->prepareSqlCondition('page_id', ['in' => $pageIds]);

            $select = $readAdapter->select()
                ->from($pageLayoutTable, 'layout_update_id')
                ->where($inCond);
            $removeLayoutUpdateIds = $readAdapter->fetchCol($select);

            $writeAdapter->delete($pageLayoutTable, $inCond);
            $this->_deleteLayoutUpdates($removeLayoutUpdateIds);
        }

        $this->_deleteWidgetInstancePages($removePageIds);

        foreach ($object->getData('page_groups') as $pageGroup) {
            $pageLayoutUpdateIds = $this->_saveLayoutUpdates($object, $pageGroup);
            $data = [
                'page_group'      => $pageGroup['group'],
                'layout_handle'   => $pageGroup['layout_handle'],
                'block_reference' => $pageGroup['block_reference'],
                'page_for'        => $pageGroup['for'],
                'entities'        => $pageGroup['entities'],
                'page_template'   => $pageGroup['template'],
            ];
            $pageId = $pageGroup['page_id'];
            if (in_array($pageGroup['page_id'], $pageIds)) {
                $writeAdapter->update($pageTable, $data, ['page_id = ?' => (int) $pageId]);
            } else {
                $writeAdapter->insert(
                    $pageTable,
                    array_merge(
                        ['instance_id' => $object->getId()],
                        $data,
                    ),
                );
                $pageId = $writeAdapter->lastInsertId($pageTable);
            }
            foreach ($pageLayoutUpdateIds as $layoutUpdateId) {
                $writeAdapter->insert($pageLayoutTable, [
                    'page_id' => $pageId,
                    'layout_update_id' => $layoutUpdateId,
                ]);
            }
        }

        return parent::_afterSave($object);
    }

    /**
     * Prepare and save layout updates data
     *
     * @param Mage_Widget_Model_Widget_Instance $widgetInstance
     * @param array $pageGroupData
     * @return array of inserted layout updates ids
     */
    protected function _saveLayoutUpdates($widgetInstance, $pageGroupData)
    {
        $writeAdapter          = $this->_getWriteAdapter();
        $pageLayoutUpdateIds   = [];
        $storeIds              = $this->_prepareStoreIds($widgetInstance->getStoreIds());
        $layoutUpdateTable     = $this->getTable('core/layout_update');
        $layoutUpdateLinkTable = $this->getTable('core/layout_link');

        foreach ($pageGroupData['layout_handle_updates'] as $handle) {
            $xml = $widgetInstance->generateLayoutUpdateXml(
                $pageGroupData['block_reference'],
                $pageGroupData['template'],
            );
            $insert = [
                'handle'     => $handle,
                'xml'        => $xml,
            ];
            if ($widgetInstance->getSortOrder() !== null) {
                $insert['sort_order'] = $widgetInstance->getSortOrder();
            }

            $writeAdapter->insert($layoutUpdateTable, $insert);
            $layoutUpdateId = $writeAdapter->lastInsertId($layoutUpdateTable);
            $pageLayoutUpdateIds[] = $layoutUpdateId;

            $data = [];
            foreach ($storeIds as $storeId) {
                $data[] = [
                    'store_id'         => $storeId,
                    'area'             => $widgetInstance->getArea(),
                    'package'          => $widgetInstance->getPackage(),
                    'theme'            => $widgetInstance->getTheme(),
                    'layout_update_id' => $layoutUpdateId];
            }
            $writeAdapter->insertMultiple($layoutUpdateLinkTable, $data);
        }
        return $pageLayoutUpdateIds;
    }

    /**
     * Prepare store ids.
     * If one of store id is default (0) return all store ids
     *
     * @param array $storeIds
     * @return array
     */
    protected function _prepareStoreIds($storeIds)
    {
        if (in_array('0', $storeIds)) {
            $storeIds = [0];
        }
        return $storeIds;
    }

    /**
     * Perform actions before object delete.
     * Collect page ids and layout update ids and set to object for further delete
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeDelete(Mage_Core_Model_Abstract $object)
    {
        $writeAdapter = $this->_getWriteAdapter();
        $select = $writeAdapter->select()
            ->from(['main_table' => $this->getTable('widget/widget_instance_page')], [])
            ->joinInner(
                ['layout_page_table' => $this->getTable('widget/widget_instance_page_layout')],
                'layout_page_table.page_id = main_table.page_id',
                ['layout_update_id'],
            )
            ->where('main_table.instance_id=?', $object->getId());
        $result = $writeAdapter->fetchCol($select);
        $object->setLayoutUpdateIdsToDelete($result);
        return $this;
    }

    /**
     * Delete layout updates by layout update ids collected in _beforeSave
     */
    #[\Override]
    protected function _afterDelete(Mage_Core_Model_Abstract $object)
    {
        $this->_deleteLayoutUpdates($object->getLayoutUpdateIdsToDelete());
        return parent::_afterDelete($object);
    }

    /**
     * Delete widget instance pages by given ids
     *
     * @param array $pageIds
     * @return $this
     */
    protected function _deleteWidgetInstancePages($pageIds)
    {
        $writeAdapter = $this->_getWriteAdapter();
        if ($pageIds) {
            $inCond = $writeAdapter->prepareSqlCondition('page_id', [
                'in' => $pageIds,
            ]);
            $writeAdapter->delete(
                $this->getTable('widget/widget_instance_page'),
                $inCond,
            );
        }
        return $this;
    }

    /**
     * Delete layout updates by given ids
     *
     * @param array $layoutUpdateIds
     * @return $this
     */
    protected function _deleteLayoutUpdates($layoutUpdateIds)
    {
        $writeAdapter = $this->_getWriteAdapter();
        if ($layoutUpdateIds) {
            $inCond = $writeAdapter->prepareSqlCondition('layout_update_id', [
                'in' => $layoutUpdateIds,
            ]);
            $writeAdapter->delete(
                $this->getTable('core/layout_update'),
                $inCond,
            );
        }
        return $this;
    }

    /**
     * Delete the layout updates of widget instances that generateLayoutUpdateXml() does not give
     *
     * This removes a layout update when no widget.xml of an enabled module declares the type of its
     * widget instance, or when its XML does not have the shape that
     * Mage_Widget_Model_Widget_Instance::generateLayoutUpdateXml() gives for that type. The widget
     * instance and its page rows stay: a save of the instance makes its layout updates again.
     *
     * The method reads the layout updates in batches, in the order of their IDs. It gives each batch to
     * $beforeDelete before it deletes the batch. It deletes the links of each layout update itself,
     * because setup scripts run with the foreign key checks off.
     *
     * @param (callable(list<LayoutUpdateRemoval>): void)|null $beforeDelete
     * @return int the number of deleted layout updates
     */
    public function deleteUnexpectedLayoutUpdates(?callable $beforeDelete = null, int $batchSize = 200): int
    {
        $adapter = $this->_getWriteAdapter();
        $batchSize = max(1, $batchSize);
        $deleted = 0;
        $lastId = 0;

        /** @var Mage_Widget_Model_Widget_Instance $checker */
        $checker = Mage::getModel('widget/widget_instance');

        do {
            $ids = array_map(intval(...), $adapter->fetchCol(
                $adapter->select()
                    ->distinct()
                    ->from(['page_layout' => $this->getTable('widget/widget_instance_page_layout')], ['layout_update_id'])
                    ->join(['page' => $this->getTable('widget/widget_instance_page')], 'page.page_id = page_layout.page_id', [])
                    ->join(['instance' => $this->getMainTable()], 'instance.instance_id = page.instance_id', [])
                    ->where('page_layout.layout_update_id > ?', $lastId)
                    ->order('page_layout.layout_update_id ' . \Maho\Db\Select::SQL_ASC)
                    ->limit($batchSize),
            ));
            $fetched = count($ids);
            if ($fetched === 0) {
                break;
            }

            $lastId = max($ids);

            $rows = $adapter->fetchAll(
                $adapter->select()
                    ->from(['layout_update' => $this->getTable('core/layout_update')], ['layout_update_id', 'handle', 'xml', 'sort_order'])
                    ->join(['page_layout' => $this->getTable('widget/widget_instance_page_layout')], 'page_layout.layout_update_id = layout_update.layout_update_id', [])
                    ->join(['page' => $this->getTable('widget/widget_instance_page')], 'page.page_id = page_layout.page_id', [])
                    ->join(['instance' => $this->getMainTable()], 'instance.instance_id = page.instance_id', ['instance_id', 'instance_type'])
                    ->where('layout_update.layout_update_id IN (?)', $ids)
                    ->order(['layout_update.layout_update_id ' . \Maho\Db\Select::SQL_ASC, 'instance.instance_id ' . \Maho\Db\Select::SQL_ASC]),
            );

            $removals = [];
            foreach ($rows as $row) {
                $layoutUpdateId = (int) $row['layout_update_id'];
                if (isset($removals[$layoutUpdateId])) {
                    continue;
                }

                // Several instances can share a layout update: it goes when one of them does not give it
                $type = (string) $row['instance_type'];
                $reason = $checker->setType($type)->getLayoutUpdateRemovalReason((string) $row['xml']);
                if ($reason === null) {
                    continue;
                }

                $removals[$layoutUpdateId] = [
                    'layout_update_id' => $layoutUpdateId,
                    'handle' => (string) $row['handle'],
                    'sort_order' => (int) $row['sort_order'],
                    'xml' => (string) $row['xml'],
                    'links' => [],
                    'instance_id' => (int) $row['instance_id'],
                    'type' => $type,
                    'reason' => $reason,
                ];
            }

            if ($removals === []) {
                continue;
            }

            $links = $this->_fetchLayoutLinks(array_keys($removals));
            foreach (array_keys($removals) as $layoutUpdateId) {
                $removals[$layoutUpdateId]['links'] = $links[$layoutUpdateId] ?? [];
            }

            if ($beforeDelete !== null) {
                $beforeDelete(array_values($removals));
            }

            $this->_deleteLayoutUpdatesWithLinks(array_keys($removals));
            $deleted += count($removals);
        } while ($fetched === $batchSize);

        return $deleted;
    }

    /**
     * Find the layout updates that have a storefront link, belong to no widget instance, and do not have the shape of generateLayoutUpdateXml()
     *
     * These rows still show on the storefront, but no admin page shows them. The method only reports
     * them: other modules also write core_layout_update, so a row with no widget instance is not
     * always a widget row. It does not report an empty update, or an update with the shape of a
     * declared widget type, which a widget instance deleted outside the admin can leave.
     *
     * @param callable(list<LayoutUpdate>): void $report
     * @return int the number of reported layout updates
     */
    public function reportUnownedLayoutUpdates(callable $report, int $batchSize = 200): int
    {
        $adapter = $this->_getWriteAdapter();
        $batchSize = max(1, $batchSize);
        $reported = 0;
        $lastId = 0;

        /** @var Mage_Widget_Model_Widget_Instance $checker */
        $checker = Mage::getModel('widget/widget_instance');

        do {
            $linked = $adapter->select()
                ->from(['link' => $this->getTable('core/layout_link')], []);
            $owned = $adapter->select()
                ->from(['page_layout' => $this->getTable('widget/widget_instance_page_layout')], [])
                ->join(['page' => $this->getTable('widget/widget_instance_page')], 'page.page_id = page_layout.page_id', [])
                ->join(['instance' => $this->getMainTable()], 'instance.instance_id = page.instance_id', []);

            $rows = $adapter->fetchAll(
                $adapter->select()
                    ->from(['layout_update' => $this->getTable('core/layout_update')], ['layout_update_id', 'handle', 'xml', 'sort_order'])
                    ->where('layout_update.layout_update_id > ?', $lastId)
                    ->exists($linked, 'link.layout_update_id = layout_update.layout_update_id')
                    ->exists($owned, 'page_layout.layout_update_id = layout_update.layout_update_id', false)
                    ->order('layout_update.layout_update_id ' . \Maho\Db\Select::SQL_ASC)
                    ->limit($batchSize),
            );
            $fetched = count($rows);
            if ($fetched === 0) {
                break;
            }

            $found = [];
            foreach ($rows as $row) {
                $layoutUpdateId = (int) $row['layout_update_id'];
                $lastId = max($lastId, $layoutUpdateId);

                $xml = (string) $row['xml'];
                $type = $checker->getGeneratedLayoutUpdateBlockType($xml);
                if (trim($xml) === '' || ($type !== null && $checker->setType($type)->isTypeDeclared())) {
                    continue;
                }

                $found[$layoutUpdateId] = [
                    'layout_update_id' => $layoutUpdateId,
                    'handle' => (string) $row['handle'],
                    'sort_order' => (int) $row['sort_order'],
                    'xml' => $xml,
                    'links' => [],
                ];
            }

            if ($found === []) {
                continue;
            }

            $links = $this->_fetchLayoutLinks(array_keys($found));
            foreach (array_keys($found) as $layoutUpdateId) {
                $found[$layoutUpdateId]['links'] = $links[$layoutUpdateId] ?? [];
            }

            $report(array_values($found));
            $reported += count($found);
        } while ($fetched === $batchSize);

        return $reported;
    }

    /**
     * Return the stores and themes of each layout update, by layout update ID
     *
     * @param list<int> $layoutUpdateIds
     * @return array<int, list<LayoutLink>>
     */
    protected function _fetchLayoutLinks(array $layoutUpdateIds): array
    {
        $adapter = $this->_getWriteAdapter();
        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from($this->getTable('core/layout_link'), ['layout_update_id', 'store_id', 'area', 'package', 'theme'])
                ->where('layout_update_id IN (?)', $layoutUpdateIds)
                ->order('layout_link_id ' . \Maho\Db\Select::SQL_ASC),
        );

        $links = [];
        foreach ($rows as $row) {
            $links[(int) $row['layout_update_id']][] = [
                'store_id' => (int) $row['store_id'],
                'area' => (string) $row['area'],
                'package' => (string) $row['package'],
                'theme' => (string) $row['theme'],
            ];
        }

        return $links;
    }

    /**
     * Delete layout updates, their store links and their widget page rows in one transaction
     *
     * Unlike _deleteLayoutUpdates(), this method does not need ON DELETE CASCADE, so it also works
     * when the foreign key checks are off, as in setup scripts.
     *
     * @param list<int> $layoutUpdateIds
     */
    protected function _deleteLayoutUpdatesWithLinks(array $layoutUpdateIds): static
    {
        if ($layoutUpdateIds === []) {
            return $this;
        }

        $adapter = $this->_getWriteAdapter();
        $where = ['layout_update_id IN (?)' => $layoutUpdateIds];
        $adapter->beginTransaction();
        try {
            $adapter->delete($this->getTable('core/layout_link'), $where);
            $adapter->delete($this->getTable('widget/widget_instance_page_layout'), $where);
            $adapter->delete($this->getTable('core/layout_update'), $where);
            $adapter->commit();
        } catch (\Throwable $throwable) {
            $adapter->rollBack();
            throw $throwable;
        }

        return $this;
    }

    /**
     * Get store ids to which specified item is assigned
     *
     * @param int $id
     * @return array
     */
    public function lookupStoreIds($id)
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), 'store_ids')
            ->where("{$this->getIdFieldName()} = ?", (int) $id);
        $storeIds = $adapter->fetchOne($select);
        return $storeIds ? explode(',', $storeIds) : [];
    }

    /**
     * The text values in the parameters of every widget instance, for the Tailwind build.
     *
     * @return Generator<int, string>
     */
    #[Maho\Config\TailwindSource('widget_instance', model: Mage_Widget_Model_Widget_Instance::class, fields: ['widget_parameters'])]
    public function getTailwindContent(): Generator
    {
        $rows = Mage::getResourceSingleton('core/design_content')
            ->readColumns($this->getMainTable(), $this->getIdFieldName(), ['widget_parameters']);
        foreach ($rows as $value) {
            try {
                $parameters = Mage::helper('core/unserializeArray')->unserialize($value);
            } catch (Exception) {
                continue;
            }
            if (!is_array($parameters)) {
                continue;
            }
            $strings = [];
            array_walk_recursive($parameters, function (mixed $parameter) use (&$strings): void {
                if (is_string($parameter)) {
                    $strings[] = $parameter;
                }
            });
            yield from $strings;
        }
    }
}
