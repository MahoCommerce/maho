<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Helper_Product_Url extends Mage_Core_Helper_Url
{
    protected $_moduleName = 'Mage_Catalog';

    /** @var array<string, bool>|null */
    protected static ?array $_transliteratorIds = null;

    /** @var array<string, Transliterator|null> */
    protected static array $_transliterators = [];

    /**
     * Symbol convert table
     *
     * @var array
     */
    protected $_convertTable = ['&amp;' => 'and', '@' => 'at', '©' => 'c', '®' => 'r', '™' => 'tm'];

    /**
     * Check additional instruction for conversion table in configuration
     */
    public function __construct()
    {
        $convertNode = Mage::getConfig()->getNode('default/url/convert');
        if ($convertNode) {
            foreach ($convertNode->children() as $node) {
                $this->_convertTable[(string) $node->from] = (string) $node->to;
            }
        }
    }

    /**
     * Get chars conversion table
     *
     * @return array
     */
    public function getConvertTable()
    {
        return $this->_convertTable;
    }

    /**
     * Process string based on conversion table
     *
     * @param   string $string
     * @param   null|string $locale
     * @param   bool $lower
     * @return  string
     */
    public function format($string, $locale = null, $lower = true)
    {
        if ($string === null) {
            return '';
        }

        $string = strtr($string, $this->getConvertTable());

        if (!empty($locale)) {
            self::$_transliteratorIds ??= array_fill_keys(transliterator_list_ids() ?: [], true);
            $code = str_replace('_', '-', strtolower($locale)) . '_Latn/BGN';
            if (isset(self::$_transliteratorIds[$code])) {
                return static::transliterate($code . '; Any-Latin; Latin-ASCII; [^\u001F-\u007f] remove' . ($lower ? '; Lower()' : ''), $string);
            }
        }

        return static::transliterate('Any-Latin; Latin-ASCII; [^\u001F-\u007f] remove' . ($lower ? '; Lower()' : ''), $string);
    }

    protected static function transliterate(string $rules, string $string): string
    {
        // null is cached too, so an unusable rule set is not retried on every call
        if (!array_key_exists($rules, self::$_transliterators)) {
            self::$_transliterators[$rules] = Transliterator::create($rules);
        }

        $result = self::$_transliterators[$rules]?->transliterate($string);

        return is_string($result) ? $result : $string;
    }
}
