<?php

/**
 * Maho
 *
 * @package    Mage_Oauth
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Oauth_Model_Consumer_Validator_KeyLength
{
    public const TOO_SHORT = 'tooShort';
    public const TOO_LONG = 'tooLong';
    public const INVALID = 'invalid';
    /**
     * Key name
     *
     * @var string
     */
    protected $_name = 'Key';
    
    /**
     * Minimum length
     *
     * @var int
     */
    protected $_min;
    
    /**
     * Maximum length
     *
     * @var int
     */
    protected $_max;
    
    /**
     * Encoding
     *
     * @var string
     */
    protected $_encoding = 'utf-8';
    
    /**
     * Last error message
     *
     * @var string|null
     */
    protected $_lastError;

    /**
     * Sets validator options
     *
     * @param  int|array|Zend_Config $options
     */
    public function __construct($options = [])
    {
        $args = func_get_args();
        if (!is_array($options)) {
            $options = $args;
            if (!isset($options[1])) {
                $options[1] = 'utf-8';
            }
            $this->_min = $this->_max = $options[0];
            $this->_encoding = $options[1];
            return;
        } else {
            if (isset($options['length'])) {
                $options['max'] =
                $options['min'] = $options['length'];
            }
            if (isset($options['name'])) {
                $this->_name = $options['name'];
            }
            if (isset($options['min'])) {
                $this->_min = $options['min'];
            }
            if (isset($options['max'])) {
                $this->_max = $options['max'];
            }
            if (isset($options['encoding'])) {
                $this->_encoding = $options['encoding'];
            }
        }
    }

    /**
     * Init validation failure message template definitions
     *
     * @return $this
     */
    protected function _initMessageTemplates()
    {
        $_messageTemplates[self::TOO_LONG] =
            Mage::helper('oauth')->__("%name% '%value%' is too long. It must has length %min% symbols.");
        $_messageTemplates[self::TOO_SHORT] =
            Mage::helper('oauth')->__("%name% '%value%' is too short. It must has length %min% symbols.");

        return $this;
    }

    /**
     * Additional variables available for validation failure messages
     *
     * @var array
     */
    protected $_messageVariables = [
        'min'  => '_min',
        'max'  => '_max',
        'name' => '_name',
    ];

    /**
     * Set length
     *
     * @param int $length
     * @return $this
     */
    public function setLength($length)
    {
        $this->_max = $length;
        $this->_min = $length;
        return $this;
    }

    /**
     * Set length
     *
     * @return int
     */
    public function getLength()
    {
        return $this->_min;
    }

    /**
     * Defined by Zend_Validate_Interface
     *
     * Returns true if and only if the string length of $value is at least the min option and
     * no greater than the max option (when the max option is not null).
     *
     * @param  string $value
     * @return bool
     */
    public function isValid($value)
    {
        $length = iconv_strlen($value, $this->_encoding);
        
        if ($this->_min !== null && $length < $this->_min) {
            $this->_lastError = Mage::helper('oauth')->__('%s \'%s\' is too short. It must has length %s symbols.', $this->_name, $value, $this->_min);
            return false;
        }
        
        if ($this->_max !== null && $length > $this->_max) {
            $this->_lastError = Mage::helper('oauth')->__('%s \'%s\' is too long. It must has length %s symbols.', $this->_name, $value, $this->_max);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get last error message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->_lastError ?? '';
    }

    /**
     * Set key name
     *
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->_name = $name;
        return $this;
    }

    /**
     * Get key name
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }
}
