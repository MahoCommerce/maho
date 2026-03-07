<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ContentVersion
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ContentVersion_Model_Resource_Version extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('contentversion/version', 'version_id');
    }

    /**
     * Insert a new version row with an atomically computed version_number.
     *
     * Uses INSERT...SELECT COALESCE(MAX(version_number),0)+1 via Maho's
     * cross-database insertFromSelect() so concurrent saves for the same
     * entity cannot produce duplicate numbers. The unique index on
     * (entity_type, entity_id, version_number) acts as a final safety net;
     * on duplicate key the insert is retried with a fresh MAX().
     */
    public function insertWithNextVersionNumber(array $data): int
    {
        $adapter = $this->_getWriteAdapter();
        $table = $this->getMainTable();

        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Compute next version number via subquery to handle the first-version case
                // (SELECT FROM table WHERE ... returns 0 rows when no versions exist yet,
                // causing INSERT...SELECT to insert nothing). Using a derived subquery ensures
                // exactly one row is always returned.
                $maxExpr = $adapter->select()
                    ->from($table, [new Maho\Db\Expr('COALESCE(MAX(version_number), 0)')])
                    ->where('entity_type = ?', $data['entity_type'])
                    ->where('entity_id = ?', $data['entity_id']);

                $select = $adapter->select()
                    ->from(
                        ['t' => new Maho\Db\Expr('(SELECT 1 AS dummy)')],
                        [
                            'entity_type' => new Maho\Db\Expr($adapter->quote($data['entity_type'])),
                            'entity_id' => new Maho\Db\Expr($adapter->quote($data['entity_id'])),
                            'version_number' => new Maho\Db\Expr('(' . $maxExpr . ') + 1'),
                            'content_data' => new Maho\Db\Expr($adapter->quote($data['content_data'])),
                            'editor' => new Maho\Db\Expr($adapter->quote($data['editor'])),
                        ],
                    );

                $query = $select->insertFromSelect(
                    $table,
                    ['entity_type', 'entity_id', 'version_number', 'content_data', 'editor'],
                    false,
                );
                $adapter->query($query);

                return (int) $adapter->lastInsertId($table);
            } catch (\Exception $e) {
                // Retry on duplicate key (unique index collision from concurrent save)
                if ($attempt >= $maxRetries || !str_contains($e->getMessage(), 'Duplicate entry')) {
                    throw $e;
                }
            }
        }

        // Unreachable, but satisfies static analysis
        throw new \RuntimeException('Failed to insert version after retries');
    }
}
