<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

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
