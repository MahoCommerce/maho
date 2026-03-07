<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Category_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('feedmanagerCategoryGrid');
        $this->setPagerVisibility(false);
        $this->setFilterVisibility(false);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $totalCategories = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToFilter('level', ['gt' => 1])
            ->getSize();

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $adapter->select()
            ->from(
                Mage::getSingleton('core/resource')->getTableName('feedmanager/category_mapping'),
                [
                    'platform',
                    'mapped_count' => new Maho\Db\Expr('COUNT(DISTINCT category_id)'),
                ],
            )
            ->group('platform');

        $mappedCounts = [];
        foreach ($adapter->fetchAll($select) as $row) {
            $mappedCounts[$row['platform']] = (int) $row['mapped_count'];
        }

        $collection = new Maho\Data\Collection();

        foreach (Maho_FeedManager_Model_Platform::getAvailablePlatforms() as $code) {
            $platformAdapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            if (!$platformAdapter) {
                continue;
            }

            $mapped = $mappedCounts[$code] ?? 0;
            $coverage = $totalCategories > 0
                ? round(($mapped / $totalCategories) * 100, 1)
                : 0;

            $item = new Maho\DataObject([
                'platform' => $code,
                'platform_name' => $platformAdapter->getName(),
                'mapped_count' => $mapped,
                'total_categories' => $totalCategories,
                'coverage' => $coverage,
                'supports_mapping' => $platformAdapter->supportsCategoryMapping(),
            ]);
            $collection->addItem($item);
        }

        $this->setCollection($collection); /** @phpstan-ignore argument.type */
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('platform_name', [
            'header' => $this->__('Platform'),
            'index' => 'platform_name',
            'filter' => false,
            'sortable' => false,
        ]);

        $this->addColumn('mapped_count', [
            'header' => $this->__('Mapped'),
            'index' => 'mapped_count',
            'type' => 'number',
            'align' => 'right',
            'filter' => false,
            'sortable' => false,
        ]);

        $this->addColumn('total_categories', [
            'header' => $this->__('Total Categories'),
            'index' => 'total_categories',
            'type' => 'number',
            'align' => 'right',
            'filter' => false,
            'sortable' => false,
        ]);

        $this->addColumn('coverage', [
            'header' => $this->__('Coverage'),
            'index' => 'coverage',
            'filter' => false,
            'sortable' => false,
            'frame_callback' => [$this, 'decorateCoverage'],
        ]);

        $this->addColumn('action', [
            'header' => $this->__('Action'),
            'index' => 'platform',
            'width' => '120px',
            'filter' => false,
            'sortable' => false,
            'frame_callback' => [$this, 'decorateAction'],
        ]);

        return parent::_prepareColumns();
    }

    /**
     * Coverage percentage decorator with color coding
     */
    public function decorateCoverage(string $value, Maho\DataObject $row, Mage_Adminhtml_Block_Widget_Grid_Column $column, bool $isExport): string
    {
        $pct = (float) $value;
        if ($pct >= 80) {
            $class = 'feedmanager-status feedmanager-status-enabled';
        } elseif ($pct >= 40) {
            $class = 'feedmanager-status feedmanager-status-pending';
        } elseif ($pct > 0) {
            $class = 'feedmanager-status feedmanager-status-disabled';
        } else {
            $class = 'feedmanager-status';
        }
        return '<span class="' . $class . '">' . $pct . '%</span>';
    }

    /**
     * Action column decorator
     */
    public function decorateAction(string $value, Maho\DataObject $row, Mage_Adminhtml_Block_Widget_Grid_Column $column, bool $isExport): string
    {
        if (!$row->getSupportsMapping()) {
            return '<span class="feedmanager-status">' . $this->escapeHtml($this->__('Not required')) . '</span>';
        }
        $url = $this->getUrl('*/*/edit', ['platform' => $row->getPlatform()]);
        return '<a href="' . $this->escapeHtml($url) . '">' . $this->escapeHtml($this->__('Edit Mapping')) . '</a>';
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        if (!$row->getSupportsMapping()) {
            return '';
        }
        return $this->getUrl('*/*/edit', ['platform' => $row->getPlatform()]);
    }
}
