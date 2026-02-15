<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Logs_Renderer_Errors extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Maho\DataObject $row): string
    {
        $errorMessages = $row->getData($this->getColumn()->getIndex());

        if (empty($errorMessages)) {
            return '<span class="grid-severity-notice"><span>' . $this->__('None') . '</span></span>';
        }

        $errors = Mage::helper('core')->jsonDecode($errorMessages);
        if (!is_array($errors) || empty($errors)) {
            return '<span class="grid-severity-notice"><span>' . $this->__('None') . '</span></span>';
        }

        $count = count($errors);
        $firstError = is_array($errors[0]) ? ($errors[0]['message'] ?? '') : $errors[0];
        $preview = $this->escapeHtml($this->truncateText($firstError, 50));
        $fullText = $this->escapeHtml($this->formatErrorsForTooltip($errors));

        if ($count === 1) {
            return '<span class="grid-severity-critical" title="' . $fullText . '"><span>' . $preview . '</span></span>';
        }

        return '<span class="grid-severity-critical" title="' . $fullText . '"><span>' .
            $this->__('%d errors', $count) . ': ' . $preview . '...</span></span>';
    }

    /**
     * Truncate text to specified length
     */
    protected function truncateText(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . '...';
    }

    /**
     * Format errors array for tooltip display
     */
    protected function formatErrorsForTooltip(array $errors): string
    {
        $messages = [];
        foreach ($errors as $error) {
            $messages[] = is_array($error) ? ($error['message'] ?? '') : $error;
        }
        return implode("\n", $messages);
    }
}
