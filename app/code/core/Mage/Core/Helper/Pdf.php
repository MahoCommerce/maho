<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Helper_Pdf extends Mage_Core_Helper_Abstract
{
    public function formatPrice(float $price, ?Mage_Core_Model_Store $store = null): string
    {
        if (!$store) {
            $store = Mage::app()->getStore();
        }
        return $store->formatPrice($price, false);
    }

    public function formatDate(string $date, ?string $format = null, ?Mage_Core_Model_Store $store = null): string
    {
        if (!$store) {
            $store = Mage::app()->getStore();
        }

        if (!$format) {
            $format = Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM);
        }

        return Mage::app()->getLocale()->date($date, null, $store->getLocaleCode())->format($format);
    }

    public function getLogoUrl(?Mage_Core_Model_Store $store = null): ?string
    {
        if (!$store) {
            $store = Mage::app()->getStore();
        }

        $logoFile = $store->getConfig('sales/identity/logo');
        if ($logoFile) {
            return Mage::getBaseUrl('media') . 'sales/store/logo/' . $logoFile;
        }

        return null;
    }

    public function getLogoPath(?Mage_Core_Model_Store $store = null): ?string
    {
        if (!$store) {
            $store = Mage::app()->getStore();
        }

        $logoFile = $store->getConfig('sales/identity/logo');
        if ($logoFile) {
            $logoPath = Mage::getBaseDir('media') . DS . 'sales' . DS . 'store' . DS . 'logo' . DS . $logoFile;
            if (file_exists($logoPath)) {
                return $logoPath;
            }
        }

        return null;
    }

    public function getLogoBase64(?Mage_Core_Model_Store $store = null): ?string
    {
        $logoPath = $this->getLogoPath($store);
        if ($logoPath && file_exists($logoPath)) {
            $imageData = file_get_contents($logoPath);
            $mimeType = mime_content_type($logoPath);
            return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        }

        return null;
    }

    public function convertLineBlocksToHtml(array $lineBlocks): string
    {
        $html = '';

        foreach ($lineBlocks as $lineBlock) {
            if (!isset($lineBlock['lines']) || empty($lineBlock['lines'])) {
                continue;
            }

            $isHeader = $lineBlock['table_header'] ?? false;
            $height = $lineBlock['height'] ?? 5;

            $html .= '<div class="line-block" style="margin-bottom: ' . $height . 'pt;">';

            foreach ($lineBlock['lines'] as $line) {
                $html .= '<div class="line-row">';

                foreach ($line as $column) {
                    $text = $column['text'] ?? '';
                    $feed = $column['feed'] ?? 0;
                    $align = $column['align'] ?? 'left';
                    $width = $column['width'] ?? '';

                    $style = 'text-align: ' . $align . ';';
                    if ($width) {
                        $style .= ' width: ' . $width . ';';
                    }

                    $class = $isHeader ? 'line-column header' : 'line-column';

                    $html .= '<span class="' . $class . '" style="' . $style . '">' .
                             $this->escapeHtml($text) . '</span>';
                }

                $html .= '</div>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    public function getItemOptions(Mage_Sales_Model_Order_Item $item): array
    {
        $options = [];

        if ($item->getProductOptions()) {
            $productOptions = $item->getProductOptions();

            if (isset($productOptions['options'])) {
                foreach ($productOptions['options'] as $option) {
                    $options[] = [
                        'label' => $option['label'],
                        'value' => $option['value'],
                    ];
                }
            }

            if (isset($productOptions['additional_options'])) {
                foreach ($productOptions['additional_options'] as $option) {
                    $options[] = [
                        'label' => $option['label'],
                        'value' => $option['value'],
                    ];
                }
            }

            if (isset($productOptions['attributes_info'])) {
                foreach ($productOptions['attributes_info'] as $option) {
                    $options[] = [
                        'label' => $option['label'],
                        'value' => $option['value'],
                    ];
                }
            }
        }

        return $options;
    }

    public function getTotalDisplayOptions(): array
    {
        return [
            'subtotal' => [
                'title' => $this->__('Subtotal'),
                'font_size' => '7pt',
                'display_zero' => true,
                'sort_order' => 100,
            ],
            'discount' => [
                'title' => $this->__('Discount'),
                'font_size' => '7pt',
                'display_zero' => false,
                'sort_order' => 200,
            ],
            'shipping' => [
                'title' => $this->__('Shipping & Handling'),
                'font_size' => '7pt',
                'display_zero' => true,
                'sort_order' => 300,
            ],
            'tax' => [
                'title' => $this->__('Tax'),
                'font_size' => '7pt',
                'display_zero' => false,
                'sort_order' => 400,
            ],
            'grand_total' => [
                'title' => $this->__('Grand Total'),
                'font_size' => '9pt',
                'display_zero' => true,
                'sort_order' => 500,
            ],
        ];
    }

    public function getPaperSizeOptions(): array
    {
        return [
            'a4' => $this->__('A4'),
            'letter' => $this->__('Letter'),
            'legal' => $this->__('Legal'),
            'a3' => $this->__('A3'),
            'a5' => $this->__('A5'),
        ];
    }
}
