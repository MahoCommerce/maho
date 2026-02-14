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

    public function getNextVersionNumber(string $entityType, int $entityId): int
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), [new Maho\Db\Expr('MAX(version_number)')])
            ->where('entity_type = ?', $entityType)
            ->where('entity_id = ?', $entityId);

        $max = (int) $adapter->fetchOne($select);
        return $max + 1;
    }
}
