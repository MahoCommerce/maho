<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_SpeculationRules
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_SpeculationRules_Block_Adminhtml_System_Config_Info extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Render the field without scope info
     */
    #[\Override]
    public function render(Varien_Data_Form_Element_Abstract $element): string
    {
        $id = $element->getHtmlId();

        $html = '<tr id="row_' . $id . '">' . "\n";
        $html .= '<td colspan="4">' . "\n";
        $html .= $this->_getElementHtml($element);
        $html .= '</td>' . "\n";
        $html .= '</tr>' . "\n";

        return $html;
    }

    /**
     * Render element HTML
     */
    #[\Override]
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element): string
    {
        return '
<div style="background:#f8f9fa; border:1px solid #dee2e6; border-radius:4px; padding:15px; margin:10px 0;">
    <h3 style="margin-top:0; color:#495057;">Quick Setup Guide</h3>

    <div style="margin-bottom:15px;">
        <h4 style="color:#6c757d; margin-bottom:5px;">1. Choose Your Strategy:</h4>
        <ul style="margin:5px 0; padding-left:20px;">
            <li><strong>Performance-focused:</strong> Use Prerender for critical pages (home, key categories)</li>
            <li><strong>Balanced:</strong> Use Prefetch for most links with Moderate eagerness</li>
            <li><strong>Conservative:</strong> Use Prefetch with Conservative eagerness for all links</li>
        </ul>
    </div>

    <div style="margin-bottom:15px;">
        <h4 style="color:#6c757d; margin-bottom:5px;">2. Common Selector Examples:</h4>
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="padding:5px; border:1px solid #dee2e6;"><code>.product-item a</code></td>
                <td style="padding:5px; border:1px solid #dee2e6;">Product grid links</td>
            </tr>
            <tr>
                <td style="padding:5px; border:1px solid #dee2e6;"><code>.nav-menu > li > a</code></td>
                <td style="padding:5px; border:1px solid #dee2e6;">Top-level navigation</td>
            </tr>
            <tr>
                <td style="padding:5px; border:1px solid #dee2e6;"><code>a[href*="/customer/"]</code></td>
                <td style="padding:5px; border:1px solid #dee2e6;">Customer account links</td>
            </tr>
            <tr>
                <td style="padding:5px; border:1px solid #dee2e6;"><code>.pages a</code></td>
                <td style="padding:5px; border:1px solid #dee2e6;">Pagination links</td>
            </tr>
        </table>
    </div>
</div>';
    }
}
