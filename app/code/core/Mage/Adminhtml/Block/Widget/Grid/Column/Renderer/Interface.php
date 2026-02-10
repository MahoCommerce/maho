<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
interface Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Interface
{
    /**
     * Set column for renderer
     *
     * @abstract
     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column
     * @return void
     */
    public function setColumn($column);

    /**
     * Returns row associated with the renderer
     *
     * @abstract
     * @return Mage_Adminhtml_Block_Widget_Grid_Column
     */
    public function getColumn();

    /**
     * Renders grid column
     */
    public function render(\Maho\DataObject $row);
}
