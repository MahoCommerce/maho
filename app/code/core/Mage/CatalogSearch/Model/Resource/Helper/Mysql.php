<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
     * Prepare Terms
     *
     * @param string $str The source string
     * @param int $maxWordLength
     * @return array (0=>words, 1=>terms)
     */
    public function prepareTerms($str, $maxWordLength = 0)
    {
        $boolWords = [
            '+' => '+',
            '-' => '-',
            '|' => '|',
            '<' => '<',
            '>' => '>',
            '~' => '~',
            '*' => '*',
        ];
        $brackets = [
            '('       => '(',
            ')'       => ')',
        ];
        $words = [0 => ''];
        $terms = [];
        preg_match_all('/([\(\)]|[\"\'][^"\']*[\"\']|[^\s\"\(\)]*)/uis', $str, $matches);
        $isOpenBracket = 0;
        foreach ($matches[1] as $word) {
            $word = trim($word);
            if (strlen($word)) {
                $word = str_replace('"', '', $word);
                $isBool = in_array(strtoupper($word), $boolWords);
                $isBracket = in_array($word, $brackets);
                if (!$isBool && !$isBracket) {
                    $terms[$word] = $word;
                    $word = '"' . $word . '"';
                    $words[] = $word;
                } elseif ($isBracket) {
                    if ($word === '(') {
                        $isOpenBracket++;
                    } else {
                        $isOpenBracket--;
                    }
                    $words[] = $word;
                } elseif ($isBool) {
                    $words[] = $word;
                }
            }
        }
        if ($isOpenBracket > 0) {
            $words[] = sprintf("%')" . $isOpenBracket . 's', '');
        } elseif ($isOpenBracket < 0) {
            $words[0] = sprintf("%'(" . $isOpenBracket . 's', '');
        }
        if ($maxWordLength && count($terms) > $maxWordLength) {
            $terms = array_slice($terms, 0, $maxWordLength);
        }
        return [$words, $terms];
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
