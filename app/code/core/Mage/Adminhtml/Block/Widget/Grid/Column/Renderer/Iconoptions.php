<?php

/**
 * Renders an options column as compact icons with the option label as a tooltip.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Iconoptions extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Options
{
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $value = $row->getData($this->getColumn()->getIndex());
        $icons = $this->getColumn()->getIcons();

        if ($value === null || $value === '' || !is_array($icons) || !isset($icons[$value])) {
            return parent::render($row);
        }

        $icon = self::normalizeIcon($icons[$value]);
        $label = $this->_getOptionLabel($value);

        $svg = '';
        foreach ($icon['icons'] as $name) {
            $svg .= $this->getIconSvg($name, $icon['variant'], 'img');
        }

        $class = 'grid-icon-value' . ($icon['class'] !== '' ? ' ' . $icon['class'] : '');

        return sprintf(
            '<span class="%s" title="%s" aria-label="%s">%s</span>',
            $this->escapeHtml($class),
            $this->quoteEscape($label),
            $this->quoteEscape($label),
            $svg,
        );
    }

    /**
     * Always export the readable label, never the icon markup.
     */
    #[\Override]
    public function renderExport(\Maho\DataObject $row)
    {
        return parent::render($row);
    }

    protected function _getOptionLabel(mixed $value): string
    {
        $options = $this->getColumn()->getOptions();
        if (is_array($options) && isset($options[$value])) {
            return (string) $options[$value];
        }
        return (string) $value;
    }

    /**
     * Normalize an icon definition into ['icons' => string[], 'variant' => string, 'class' => string].
     *
     * Accepts a single icon name, a list of icon names, or an associative
     * descriptor with 'icon' (string|string[]), 'variant' and 'class' keys.
     */
    public static function normalizeIcon(mixed $definition): array
    {
        if (is_string($definition)) {
            return ['icons' => [$definition], 'variant' => 'outline', 'class' => ''];
        }

        if (is_array($definition) && array_is_list($definition)) {
            return ['icons' => $definition, 'variant' => 'outline', 'class' => ''];
        }

        $icon = $definition['icon'] ?? [];
        return [
            'icons' => is_array($icon) ? $icon : [$icon],
            'variant' => $definition['variant'] ?? 'outline',
            'class' => $definition['class'] ?? '',
        ];
    }
}
