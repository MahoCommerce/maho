<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogSearch_Model_Resource_Helper_Sqlite extends Mage_Eav_Model_Resource_Helper_Sqlite
{
    /**
     * Join information for using full text search
     * SQLite uses FTS5 for full text search, but we fall back to LIKE for compatibility
     *
     * @param string $table
     * @param string $alias
     * @param Maho\Db\Select $select
     * @return Maho\Db\Expr $select
     */
    public function chooseFulltext($table, $alias, $select)
    {
        // SQLite doesn't have native MATCH AGAINST like MySQL
        // Use a simple LIKE-based relevance score
        $field = new Maho\Db\Expr('CASE WHEN ' . $alias . '.data_index LIKE :query THEN 1 ELSE 0 END');
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
     * SQLite doesn't have FIELD() function, use CASE expression instead
     *
     * @param string $fieldName
     *
     * @return string
     */
    public function getFieldOrderExpression($fieldName, array $orderedIds)
    {
        if (empty($orderedIds)) {
            return '0';
        }

        $fieldName = $this->_getWriteAdapter()->quoteIdentifier($fieldName);
        $cases = [];
        foreach ($orderedIds as $position => $id) {
            $cases[] = sprintf('WHEN %s = %s THEN %d', $fieldName, $this->_getReadAdapter()->quote($id), $position);
        }
        return 'CASE ' . implode(' ', $cases) . ' ELSE ' . count($orderedIds) . ' END';
    }
}
