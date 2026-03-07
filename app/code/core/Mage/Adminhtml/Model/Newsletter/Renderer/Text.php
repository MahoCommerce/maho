<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_Newsletter_Renderer_Text implements \Maho\Data\Form\Element\Renderer\RendererInterface
{
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $html = '<tr><td class="label">' . "\n";
        if ($element->getLabel()) {
            $html .= '<label for="' . $element->getHtmlId() . '">' . $element->getLabel() . '</label>' . "\n";
        }
        $html .= '</td><td class="value">
<iframe src="' . $element->getValue() . '" id="' . $element->getHtmlId() . '" frameborder="0" class="template-preview"> </iframe>';
        $html .= '</td><td></td></tr>' . "\n";

        return $html;
    }
}
