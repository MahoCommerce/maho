<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Model_Resource_Giftcard extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('giftcard/giftcard', 'giftcard_id');
    }

    /**
     * Load gift card by code
     *
     * @return $this
     */
    public function loadByCode(Maho_Giftcard_Model_Giftcard $object, string $code): self
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable())
            ->where('code = ?', $code);

        $data = $adapter->fetchRow($select);

        if ($data) {
            $object->setData($data);
        }

        $this->_afterLoad($object);

        return $this;
    }

    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        // Set timestamps in UTC
        $now = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
        if (!$object->getId()) {
            $object->setCreatedAt($now);
        }
        $object->setUpdatedAt($now);

        return parent::_beforeSave($object);
    }
}
