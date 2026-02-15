<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Destination extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_destination';
        $this->_blockGroup = 'feedmanager';
        $this->_headerText = Mage::helper('feedmanager')->__('Upload Destinations');
        $this->_addButtonLabel = Mage::helper('feedmanager')->__('Add New Destination');
        parent::__construct();
    }
}
