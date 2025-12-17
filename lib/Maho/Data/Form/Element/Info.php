<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Data\Form\Element;

use Maho\Data\Form\Element\AbstractElement;

class Info extends AbstractElement
{
    /**
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this
            ->setType('info')
            ->unsScope()
            ->unsCanUseDefaultValue()
            ->unsCanUseWebsiteValue();
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHtml()
    {
        $id = $this->getHtmlId();
        $label = $this->getLabel();
        $html = '<tr class="' . $id . '"><td class="label" colspan="99"><label>' . $label . '</label></td></tr>';
        return $html;
    }
}
