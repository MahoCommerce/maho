<?php

/**
 * Maho
 *
 * @package    Mage_Oauth
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Oauth_Model_Resource_Token extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Initialize resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('oauth/token', 'entity_id');
    }

    /**
     * Clean up old authorized tokens for specified consumer-user pairs
     *
     * @param Mage_Oauth_Model_Token $exceptToken Token just created to exclude from delete
     * @return int The number of affected rows
     */
    public function cleanOldAuthorizedTokensExcept(Mage_Oauth_Model_Token $exceptToken)
    {
        if (!$exceptToken->getId() || !$exceptToken->getAuthorized()) {
            Mage::throwException('Invalid token to except');
        }
        $adapter = $this->_getWriteAdapter();
        $where   = $adapter->quoteInto(
            'authorized = 1 AND consumer_id = ?',
            $exceptToken->getConsumerId(),
        );
        $where .= $adapter->quoteInto(' AND entity_id <> ?', $exceptToken->getId());

        if ($exceptToken->getCustomerId()) {
            $where .= $adapter->quoteInto(' AND customer_id = ?', $exceptToken->getCustomerId());
        } elseif ($exceptToken->getAdminId()) {
            $where .= $adapter->quoteInto(' AND admin_id = ?', $exceptToken->getAdminId());
        } else {
            Mage::throwException('Invalid token to except');
        }
        return $adapter->delete($this->getMainTable(), $where);
    }

    /**
     * Delete old entries
     *
     * @param int $minutes
     * @return int
     */
    public function deleteOldEntries($minutes)
    {
        if ($minutes > 0) {
            $adapter = $this->_getWriteAdapter();

            return $adapter->delete(
                $this->getMainTable(),
                $adapter->quoteInto(
                    'type = "' . Mage_Oauth_Model_Token::TYPE_REQUEST . '" AND created_at <= ?',
                    date(Mage_Core_Model_Locale::DATETIME_FORMAT, time() - $minutes * 60),
                ),
            );
        }
        return 0;
    }
}
