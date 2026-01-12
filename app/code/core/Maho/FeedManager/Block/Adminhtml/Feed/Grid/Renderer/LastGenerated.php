<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Last generated renderer - relative time with full datetime on hover
 */
class Maho_FeedManager_Block_Adminhtml_Feed_Grid_Renderer_LastGenerated extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Maho\DataObject $row): string
    {
        $value = $row->getData($this->getColumn()->getIndex());

        if (!$value) {
            return '<span class="grid-severity-minor"><span>' . $this->__('Never') . '</span></span>';
        }

        $timestamp = strtotime($value);
        $relativeTime = $this->_getRelativeTime($timestamp);
        $fullDate = Mage::helper('core')->formatDate($value, 'medium', true);

        return '<span title="' . $this->escapeHtml($fullDate) . '">'
            . $this->escapeHtml($relativeTime)
            . '</span>';
    }

    /**
     * Get human-readable relative time
     */
    protected function _getRelativeTime(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'Just now';
        }

        if ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
        }

        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }

        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }

        return date('M j, Y', $timestamp);
    }
}
