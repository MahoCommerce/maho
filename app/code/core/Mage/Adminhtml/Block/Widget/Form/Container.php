<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Widget_Form_Container extends Mage_Adminhtml_Block_Widget_Container
{
    protected string $_objectId = 'id';
    protected array $_formScripts = [];
    protected array $_formInitScripts = [];
    protected ?string $_mode = 'edit';
    protected ?string $_blockGroup = 'adminhtml';

    protected ?string $_gridNavigationId = null;
    protected string $_gridNavigationRoute = '*/*/view';
    protected ?array $_gridNavigation = null;

    public function __construct()
    {
        parent::__construct();

        if (!$this->hasData('template')) {
            $this->setTemplate('widget/form/container.phtml');
        }

        $this->_addButton('back', [
            'label'     => Mage::helper('adminhtml')->__('Back'),
            'onclick'   => Mage::helper('core/js')->getSetLocationJs($this->getBackUrl()),
            'class'     => 'back',
        ], -1);
        $this->_addButton('reset', [
            'label'     => Mage::helper('adminhtml')->__('Reset'),
            'onclick'   => 'setLocation(window.location.href)',
        ], -1);

        $objId = $this->getRequest()->getParam($this->_objectId);

        if (!empty($objId)) {
            $this->_addButton('delete', [
                'label'     => Mage::helper('adminhtml')->__('Delete'),
                'class'     => 'delete',
                'onclick'   => Mage::helper('core/js')->getDeleteConfirmJs($this->getDeleteUrl()),
            ]);
        }

        $this->_addButton('save', [
            'label'     => Mage::helper('adminhtml')->__('Save'),
            'onclick'   => 'editForm.submit();',
            'class'     => 'save',
        ], 1);
    }

    public function getGridNavigation(): ?array
    {
        if ($this->_gridNavigation !== null) {
            return $this->_gridNavigation ?: null;
        }

        $this->_gridNavigation = [];

        $currentId = (int) $this->getRequest()->getParam($this->_objectId);
        if (!$this->_gridNavigationId || !$currentId) {
            return null;
        }

        $navData = Mage::getSingleton('adminhtml/session')->getData($this->_gridNavigationId . '_nav');
        if (!$navData || empty($navData['sql']) || !isset($navData['directions'])) {
            return null;
        }

        try {
            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $from = "({$navData['sql']}) AS _nav";
            $id = $navData['id_field'];
            $bind = $navData['bind'];
            $keys = array_map(static fn(int $index): string => "nav_sort{$index}", array_keys($navData['directions']));
            $current = $adapter->fetchRow('SELECT ' . implode(', ', $keys) . " FROM {$from} WHERE {$id} = ?", [...$bind, $currentId]);
            if (!$current) {
                return null;
            }

            $nullsLast = $adapter instanceof \Maho\Db\Adapter\Pdo\Pgsql;
            [$before, $beforeBind] = $this->navigationCondition($keys, $navData['directions'], $current, true, $nullsLast);
            [$after, $afterBind] = $this->navigationCondition($keys, $navData['directions'], $current, false, $nullsLast);
            $order = static fn(bool $reverse): string => implode(', ', array_map(
                static fn(string $key, string $direction): string => $key . (($direction === 'DESC') !== $reverse ? ' DESC' : ' ASC'),
                $keys,
                $navData['directions'],
            ));

            $prevId = $adapter->fetchOne("SELECT {$id} FROM {$from} WHERE {$before} ORDER BY {$order(true)} LIMIT 1", [...$bind, ...$beforeBind]);
            $nextId = $adapter->fetchOne("SELECT {$id} FROM {$from} WHERE {$after} ORDER BY {$order(false)} LIMIT 1", [...$bind, ...$afterBind]);
            $prevId = $prevId === false || $prevId === null ? null : (int) $prevId;
            $nextId = $nextId === false || $nextId === null ? null : (int) $nextId;

            $this->_gridNavigation = [
                'prev_id'  => $prevId,
                'next_id'  => $nextId,
                'prev_url' => $prevId !== null ? $this->getNavigationUrl($prevId) : null,
                'next_url' => $nextId !== null ? $this->getNavigationUrl($nextId) : null,
                'position' => (int) $adapter->fetchOne("SELECT COUNT(*) FROM {$from} WHERE {$before}", [...$bind, ...$beforeBind]) + 1,
                'total'    => (int) $adapter->fetchOne("SELECT COUNT(*) FROM {$from}", $bind),
            ];

            return $this->_gridNavigation;
        } catch (\Exception $e) {
            Mage::log('Grid navigation error: ' . $e->getMessage(), Mage::LOG_WARNING);
            return null;
        }
    }

    /**
     * The SQL condition of the rows that the grid shows before ($before) or after the current row, with its values.
     * NULL sorts as the smallest value on MySQL and SQLite, and as the largest on PostgreSQL ($nullsLast).
     *
     * @param list<string> $keys the sort columns, in the order of the grid
     * @param list<string> $directions ASC or DESC for each sort column
     * @param array<string, mixed> $current the sort values of the current row
     * @return array{0: string, 1: list<mixed>}
     */
    protected function navigationCondition(array $keys, array $directions, array $current, bool $before, bool $nullsLast): array
    {
        $any = $bind = $same = $sameBind = [];
        foreach ($keys as $index => $key) {
            $value = $current[$key] ?? null;
            $smaller = ($directions[$index] === 'DESC') !== $before;
            $condition = match (true) {
                $value === null => $smaller === $nullsLast ? "{$key} IS NOT NULL" : null,
                $smaller => $nullsLast ? "{$key} < ?" : "({$key} IS NULL OR {$key} < ?)",
                default => $nullsLast ? "({$key} IS NULL OR {$key} > ?)" : "{$key} > ?",
            };
            if ($condition !== null) {
                $any[] = '(' . implode(' AND ', [...$same, $condition]) . ')';
                array_push($bind, ...$sameBind, ...($value === null ? [] : [$value]));
            }
            $same[] = $value === null ? "{$key} IS NULL" : "{$key} = ?";
            if ($value !== null) {
                $sameBind[] = $value;
            }
        }
        return [$any === [] ? '1 = 0' : implode(' OR ', $any), $bind];
    }

    protected function getNavigationUrl(int $entityId): string
    {
        return $this->getUrl($this->_gridNavigationRoute, [$this->_objectId => $entityId]);
    }

    #[\Override]
    protected function _prepareLayout()
    {
        if ($this->_blockGroup && $this->_controller && $this->_mode) {
            $this->setChild('form', $this->getLayout()->createBlock($this->_blockGroup
                . '/'
                . $this->_controller
                . '_'
                . $this->_mode
                . '_form'));
        }
        return parent::_prepareLayout();
    }

    /**
     * Get URL for back (reset) button
     *
     * @return string
     */
    public function getBackUrl()
    {
        return $this->getUrl('*/*/');
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getDeleteUrl()
    {
        return $this->getUrl('*/*/delete', [
            $this->_objectId => $this->getRequest()->getParam($this->_objectId),
        ]);
    }

    /**
     * Get form save URL
     *
     * @see getFormActionUrl()
     * @return string
     */
    #[\Deprecated]
    public function getSaveUrl()
    {
        return $this->getFormActionUrl();
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        if ($this->hasFormActionUrl()) {
            return $this->getData('form_action_url');
        }
        return $this->getUrl('*/' . $this->_controller . '/save');
    }

    /**
     * @return string
     */
    public function getFormHtml()
    {
        $this->getChild('form')->setData('action', $this->getFormActionUrl());
        return $this->getChildHtml('form');
    }

    /**
     * @return string
     */
    public function getFormInitScripts()
    {
        if (!empty($this->_formInitScripts) && is_array($this->_formInitScripts)) {
            return '<script>' . implode("\n", $this->_formInitScripts) . '</script>';
        }
        return '';
    }

    /**
     * @return string
     */
    public function getFormScripts()
    {
        if (!empty($this->_formScripts) && is_array($this->_formScripts)) {
            return '<script>' . implode("\n", $this->_formScripts) . '</script>';
        }
        return '';
    }

    /**
     * @return string
     */
    public function getHeaderWidth()
    {
        return '';
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderHtml()
    {
        return '<h3 class="' . $this->getHeaderCssClass() . '">' . $this->getHeaderText() . '</h3>';
    }

    /**
     * Set data object and pass it to form
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    public function setDataObject($object)
    {
        $this->getChild('form')->setDataObject($object);
        return $this->setData('data_object', $object);
    }
}
