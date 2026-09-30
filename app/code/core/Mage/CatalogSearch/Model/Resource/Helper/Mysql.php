<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
 */

class Mage_CatalogSearch_Model_Resource_Helper_Mysql extends Mage_Eav_Model_Resource_Helper_Mysql
{
    /**
     * Join information for using full text search
     *
     * @param string $table
     * @param string $alias
     * @param Maho\Db\Select $select
     * @return Maho\Db\Expr $select
     */
    public function chooseFulltext($table, $alias, $select)
    {
        $field = new Maho\Db\Expr('MATCH (' . $alias . '.data_index) AGAINST (:query IN BOOLEAN MODE)');
        $select->columns(['relevance' => $field]);
        return $field;
    }

    /**
     * Split $str into words for MATCH ... AGAINST (:query IN BOOLEAN MODE).
     *
     * Each word goes into double quotes, so MySQL never reads a character of it as a boolean operator.
     * A word that holds only operator or bracket characters, or that is shorter than $minWordLength, is dropped.
     *
     * @param string $str The source string
     * @param int $maxWordLength The maximum number of words
     * @return array (0=>words, 1=>terms)
     */
    public function prepareTerms($str, $maxWordLength = 0, int $minWordLength = 0)
    {
        $terms = [];
        preg_match_all('/([\(\)]|[\"\'][^"\']*[\"\']|[^\s\"\(\)]*)/uis', $str, $matches);
        foreach ($matches[1] as $word) {
            $word = str_replace('"', '', trim($word));
            if (strspn($word, '+-|<>~*()') === strlen($word) || mb_strlen($word) < $minWordLength) {
                continue;
            }
            $terms[$word] = $word;
        }
        if ($maxWordLength && count($terms) > $maxWordLength) {
            $terms = array_slice($terms, 0, $maxWordLength);
        }
        return [array_map(fn($term) => '"' . $term . '"', array_values($terms)), $terms];
    }

    /**
     * Use sql compatible with Full Text indexes
     *
     * @param mixed $table The table to insert data into.
     * @param array $data Column-value pairs or array of column-value pairs.
     * @param array $fields update fields pairs or values
     * @return int The number of affected rows.
     */
    public function insertOnDuplicate($table, array $data, array $fields = [])
    {
        return $this->_getWriteAdapter()->insertOnDuplicate($table, $data, $fields);
    }

    /**
     * Get field expression for order by
     *
     * @param string $fieldName
     *
     * @return string
     */
    public function getFieldOrderExpression($fieldName, array $orderedIds)
    {
        $fieldName = $this->_getWriteAdapter()->quoteIdentifier($fieldName);
        return "FIELD({$fieldName}, {$this->_getReadAdapter()->quote($orderedIds)})";
    }
}
