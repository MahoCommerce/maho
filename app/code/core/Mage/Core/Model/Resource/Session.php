<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2016-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Resource_Session implements SessionHandlerInterface
{
    /**
     * Session data table name
     *
     * @var string
     */
    protected $_sessionTable;

    /**
     * Database read connection
     *
     * @var Varien_Db_Adapter_Interface
     */
    protected $_read;

    /**
     * Database write connection
     *
     * @var Varien_Db_Adapter_Interface
     */
    protected $_write;

    /**
     * Automatic cleaning factor of expired sessions
     * value zero means no automatic cleaning, one means automatic cleaning each time a session is closed, and x>1 means
     * cleaning once in x calls
     *
     * @var int
     */
    protected $_automaticCleaningFactor    = 50;

    public function __construct()
    {
        $resource = Mage::getSingleton('core/resource');
        $this->_sessionTable = $resource->getTableName('core/session');
        $this->_read         = $resource->getConnection('core_read');
        $this->_write        = $resource->getConnection('core_write');
    }

    public function __destruct()
    {
        session_write_close();
    }

    /**
     * Retrieve session life time
     *
     * @return int
     */
    public function getLifeTime()
    {
        return Mage::getSingleton('core/cookie')->getLifetime();
    }

    /**
     * Check DB connection
     *
     * @return bool
     */
    public function hasConnection()
    {
        if (!$this->_read) {
            return false;
        }
        if (!$this->_read->isTableExists($this->_sessionTable)) {
            return false;
        }

        return true;
    }

    /**
     * Setup save handler
     *
     * @return $this
     */
    public function setSaveHandler()
    {
        if ($this->hasConnection()) {
            session_set_save_handler(
                [$this, 'open'],
                [$this, 'close'],
                [$this, 'read'],
                [$this, 'write'],
                [$this, 'destroy'],
                [$this, 'gc'],
            );
        } else {
            session_save_path(Mage::getBaseDir('session'));
        }
        return $this;
    }

    /**
     * Adds session handler via static call
     */
    public static function setStaticSaveHandler()
    {
        $handler = new self();
        $handler->setSaveHandler();
    }

    /**
     * Open session
     *
     * @param string $savePath ignored
     * @param string $sessName ignored
     */
    #[\Override]
    public function open($savePath, $sessName): bool
    {
        return true;
    }

    /**
     * Close session
     */
    #[\Override]
    public function close(): bool
    {
        $this->gc($this->getLifeTime());

        return true;
    }

    /**
     * Fetch session data
     *
     * @param string $sessId
     * @return string
     */
    #[\Override]
    public function read($sessId): string|false
    {
        $select = $this->_read->select()
            ->from($this->_sessionTable, ['session_data'])
            ->where('session_id = :session_id')
            ->where('session_expires > :session_expires');
        $bind = [
            'session_id'      => $sessId,
            'session_expires' => Varien_Date::toTimestamp(true),
        ];

        // https://www.php.net/manual/en/sessionhandlerinterface.read.php#128107
        return (string) $this->_read->fetchOne($select, $bind);
    }

    /**
     * Update session
     *
     * @param string $sessId
     * @param string $sessData
     */
    #[\Override]
    public function write($sessId, $sessData): bool
    {
        $bindValues = [
            'session_id'      => $sessId,
        ];
        $select = $this->_write->select()
            ->from($this->_sessionTable)
            ->where('session_id = :session_id');
        $exists = $this->_read->fetchOne($select, $bindValues);

        $bind = [
            'session_expires' => Varien_Date::toTimestamp(true) + $this->getLifeTime(),
            'session_data' => $sessData,
        ];
        if ($exists) {
            $where = [
                'session_id=?' => $sessId,
            ];
            $this->_write->update($this->_sessionTable, $bind, $where);
        } else {
            $bind['session_id'] = $sessId;
            $this->_write->insert($this->_sessionTable, $bind);
        }

        return true;
    }

    /**
     * Destroy session
     *
     * @param string $sessId
     */
    #[\Override]
    public function destroy($sessId): bool
    {
        $where = ['session_id = ?' => $sessId];
        $this->_write->delete($this->_sessionTable, $where);
        return true;
    }

    /**
     * Garbage collection
     *
     * @param int $sessMaxLifeTime ignored
     * @return int|false
     */
    #[\Override]
    public function gc($sessMaxLifeTime): int|false
    {
        if ($this->_automaticCleaningFactor > 0) {
            if ($this->_automaticCleaningFactor == 1 || random_int(1, $this->_automaticCleaningFactor) == 1) {
                $where = ['session_expires < ?' => Varien_Date::toTimestamp(true)];
                return $this->_write->delete($this->_sessionTable, $where);
            }
        }
        return 0;
    }
}
