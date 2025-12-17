<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Core_Model_Resource_Helper_Abstract
{
    /**
     * Read adapter instance
     *
     * @var Maho\Db\Adapter\AdapterInterface
     */
    protected $_readAdapter;

    /**
     * Write adapter instance
     *
     * @var Maho\Db\Adapter\AdapterInterface
     */
    protected $_writeAdapter;

    /**
     * Resource helper module prefix
     *
     * @var string
     */
    protected $_modulePrefix;

    /**
     * Initialize resource helper instance
     *
     * @param string $module
     */
    public function __construct($module)
    {
        $this->_modulePrefix = (string) $module;
    }

    /**
     * Retrieve connection for read data
     *
     * @return Maho\Db\Adapter\AdapterInterface
     */
    protected function _getReadAdapter()
    {
        if ($this->_readAdapter === null) {
            $this->_readAdapter = $this->_getConnection('read');
        }

        return $this->_readAdapter;
    }

    /**
     * Retrieve connection for write data
     *
     * @return Maho\Db\Adapter\AdapterInterface
     */
    protected function _getWriteAdapter()
    {
        if ($this->_writeAdapter === null) {
            $this->_writeAdapter = $this->_getConnection('write');
        }

        return $this->_writeAdapter;
    }

    /**
     * Retrieves connection to the resource
     *
     * @param string $name
     * @return Maho\Db\Adapter\AdapterInterface
     */
    protected function _getConnection($name)
    {
        $connection = sprintf('%s_%s', $this->_modulePrefix, $name);
        /** @var Mage_Core_Model_Resource $resource */
        $resource   = Mage::getSingleton('core/resource');

        return $resource->getConnection($connection);
    }

    /**
     * Escapes value, that participates in LIKE, with '\' symbol.
     * Note: this func cannot be used on its own, because different RDBMS may use different default escape symbols,
     * so you should either use addLikeEscape() to produce LIKE construction, or add escape symbol on your own.
     *
     * By default escapes '_', '%' and '\' symbols. If some masking symbols must not be escaped, then you can set
     * appropriate options in $options.
     *
     * $options can contain following flags:
     * - 'allow_symbol_mask' - the '_' symbol will not be escaped
     * - 'allow_string_mask' - the '%' symbol will not be escaped
     * - 'position' ('any', 'start', 'end') - expression will be formed so that $value will be found at position within string,
     *     by default when nothing set - string must be fully matched with $value
     *
     * @param string $value
     * @param array $options
     * @return string
     */
    public function escapeLikeValue($value, $options = [])
    {
        $value = str_replace('\\', '\\\\', $value);

        $from = [];
        $to = [];
        if (empty($options['allow_symbol_mask'])) {
            $from[] = '_';
            $to[] = '\_';
        }
        if (empty($options['allow_string_mask'])) {
            $from[] = '%';
            $to[] = '\%';
        }
        if ($from) {
            $value = str_replace($from, $to, $value);
        }

        if (isset($options['position'])) {
            switch ($options['position']) {
                case 'any':
                    $value = '%' . $value . '%';
                    break;
                case 'start':
                    $value = $value . '%';
                    break;
                case 'end':
                    $value = '%' . $value;
                    break;
            }
        }

        return $value;
    }

    /**
     * Escapes, quotes and adds escape symbol to LIKE expression.
     * For options and escaping see escapeLikeValue().
     *
     * @param string $value
     * @param array $options
     * @return Maho\Db\Expr
     *
     * @see escapeLikeValue()
     */
    abstract public function addLikeEscape($value, $options = []);

    /**
     * Cast a field or expression to text for comparison/concatenation
     *
     * @param Maho\Db\Expr|string $field
     * @return Maho\Db\Expr
     */
    abstract public function castField($field);

    /**
     * Returns case insensitive LIKE construction.
     * For options and escaping see escapeLikeValue().
     *
     * @param string $field
     * @param string $value
     * @param array $options
     * @return Maho\Db\Expr
     *
     * @see escapeLikeValue()
     */
    public function getCILike($field, $value, $options = [])
    {
        $quotedField = $this->_getReadAdapter()->quoteIdentifier($field);
        return new Maho\Db\Expr($quotedField . ' LIKE ' . $this->addLikeEscape($value, $options));
    }
}
