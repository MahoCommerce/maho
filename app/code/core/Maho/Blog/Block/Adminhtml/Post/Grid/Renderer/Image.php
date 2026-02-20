<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Block_Adminhtml_Post_Grid_Renderer_Image extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $value = $row->getData($this->getColumn()->getIndex());

        if (!$value) {
            return '';
        }

        $imageUrl = Mage::getBaseUrl('media') . 'blog/' . $value;

        return sprintf(
            '<img src="%s" alt="%s" style="width: 50px; height: 50px; object-fit: cover;" />',
            $this->escapeHtml($imageUrl),
            $this->escapeHtml($row->getTitle()),
        );
    }
}
