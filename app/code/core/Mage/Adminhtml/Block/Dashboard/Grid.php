<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Dashboard_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Setting default for every grid on dashboard
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('dashboard/grid.phtml');
        $this->setDefaultLimit(5);
    }
}
