<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Data\Form\Element;

use Maho\Data\Form\Element\AbstractElement;

class Color extends AbstractElement
{
    public const VALIDATION_REGEX_WITH_HASH = '/^#[a-f0-9]{6}$/i';
    public const VALIDATION_REGEX_WITHOUT_HASH = '/^[a-f0-9]{6}$/i';

    /**
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setType('text');
        $this->setExtType('textfield');
    }

    /**
     * @return array
     */
    #[\Override]
    public function getHtmlAttributes()
    {
        return ['type', 'title', 'class', 'style', 'oninput', 'disabled', 'readonly', 'tabindex'];
    }

    /**
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $id = $this->getHtmlId();
        $originalData = $this->getData('original_data') ?? [];
        $with_hash = strtolower((string) ($originalData['with_hash'] ?? 1));

        if (!empty($with_hash) && $with_hash !== 'false' && $with_hash !== 'off') {
            $oninput = "document.getElementById('{$id}').value = this.value";
            $regex = self::VALIDATION_REGEX_WITH_HASH;
            $this->setOninput("document.getElementById('{$id}:html5').value = {$regex}.test(this.value) ? this.value : '#000000'");
            $this->addClass('validate-hex-color-hash');
        } else {
            $oninput = "document.getElementById('{$id}').value = this.value.substring(1)";
            $regex = self::VALIDATION_REGEX_WITHOUT_HASH;
            $this->setOninput("document.getElementById('{$id}:html5').value = {$regex}.test(this.value) ? '#'+this.value : '#000000'");
            $this->addClass('validate-hex-color');
        }

        $html = '<input type="color" id="' . $id . ':html5" class="input-color-html5" '
            . 'value="#' . trim($this->getEscapedValue(), '#') . '" oninput="' . $oninput . '" '
            . '/>' . "\n";

        $this->addClass('input-color');
        $html .= parent::getElementHtml();
        return $html;
    }
}
