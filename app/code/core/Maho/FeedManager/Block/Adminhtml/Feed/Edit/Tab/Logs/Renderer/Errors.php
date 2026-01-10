<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Logs_Renderer_Errors extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Maho\DataObject $row): string
    {
        $errorMessages = $row->getData($this->getColumn()->getIndex());

        if (empty($errorMessages)) {
            return '<span style="color: green;">None</span>';
        }

        $errors = Mage::helper('core')->jsonDecode($errorMessages);
        if (!is_array($errors) || empty($errors)) {
            return '<span style="color: green;">None</span>';
        }

        $count = count($errors);
        $preview = htmlspecialchars(substr($errors[0], 0, 50));

        if ($count === 1) {
            return '<span style="color: red;" title="' . htmlspecialchars($errors[0]) . '">' . $preview . '</span>';
        }

        return '<span style="color: red;" title="' . htmlspecialchars(implode("\n", $errors)) . '">' .
            $count . ' errors: ' . $preview . '...</span>';
    }
}
