<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogSearch_Model_Resource_Helper_Pgsql extends Mage_Eav_Model_Resource_Helper_Pgsql
{
    /**
     * Join information for using full text search
     *
     * PostgreSQL uses ts_rank with to_tsvector/to_tsquery for full-text search
     *
     * @param string $table
     * @param string $alias
     * @param Maho\Db\Select $select
     * @return Maho\Db\Expr $select
     */
    public function chooseFulltext($table, $alias, $select)
    {
        // PostgreSQL full-text search using ts_rank
        // Note: This requires the data_index column to be searchable text
        $field = new Maho\Db\Expr(
            'ts_rank(to_tsvector(\'simple\', ' . $alias . '.data_index), plainto_tsquery(\'simple\', :query))',
        );
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
        $words = [0 => ''];
        $terms = [];

        // Simple word extraction for PostgreSQL full-text search
        preg_match_all('/[\w]+/u', $str, $matches);

        foreach ($matches[0] as $word) {
            $word = trim($word);
            if (strlen($word)) {
                $terms[$word] = $word;
                $words[] = $word;
            }
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
     * PostgreSQL doesn't have FIELD() function, use CASE WHEN instead
     *
     * @param string $fieldName
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
