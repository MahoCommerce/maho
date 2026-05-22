<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Block_Adminhtml_Usage_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('aiUsageGrid');
        $this->setDefaultSort('period_date');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): static
    {
        $this->setCollection(Mage::getModel('ai/usage')->getCollection());
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): static
    {
        $helper = Mage::helper('ai');

        $this->addColumn('period_date', [
            'header' => $helper->__('Date'),
            'index'  => 'period_date',
            'width'  => '110px',
            'type'   => 'date',
        ]);

        $this->addColumn('consumer', [
            'header' => $helper->__('Consumer'),
            'index'  => 'consumer',
        ]);

        $this->addColumn('platform', [
            'header' => $helper->__('Platform'),
            'index'  => 'platform',
            'width'  => '100px',
        ]);

        $this->addColumn('model', [
            'header' => $helper->__('Model'),
            'index'  => 'model',
        ]);

        $this->addColumn('store_id', [
            'header' => $helper->__('Store'),
            'index'  => 'store_id',
            'width'  => '70px',
            'type'   => 'number',
        ]);

        $this->addColumn('request_count', [
            'header' => $helper->__('Calls'),
            'index'  => 'request_count',
            'width'  => '70px',
            'type'   => 'number',
        ]);

        $this->addColumn('input_tokens', [
            'header' => $helper->__('In Tokens'),
            'index'  => 'input_tokens',
            'width'  => '90px',
            'type'   => 'number',
        ]);

        $this->addColumn('output_tokens', [
            'header' => $helper->__('Out Tokens'),
            'index'  => 'output_tokens',
            'width'  => '90px',
            'type'   => 'number',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return '';
    }
}
