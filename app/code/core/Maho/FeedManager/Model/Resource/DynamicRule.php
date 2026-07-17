<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * Dynamic Rule Resource Model
 */
class Maho_FeedManager_Model_Resource_DynamicRule extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/dynamic_rule', 'rule_id');
    }

    /**
     * Load rule by code
     */
    public function loadByCode(Maho_FeedManager_Model_DynamicRule $rule, string $code): self
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable())
            ->where('code = ?', $code);

        $data = $adapter->fetchRow($select);

        if ($data) {
            $rule->setData($data);
        }

        return $this;
    }
}
