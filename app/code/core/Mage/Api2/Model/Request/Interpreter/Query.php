<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Request content interpreter query adapter
 *
 * @category   Mage
 * @package    Mage_Api2
 */
class Mage_Api2_Model_Request_Interpreter_Query implements Mage_Api2_Model_Request_Interpreter_Interface
{
    /**
     * URI validate pattern
     */
    public const URI_VALIDATE_PATTERN = "/^(?:%[[:xdigit:]]{2}|[A-Za-z0-9-_.!~*'()\[\];\/?:@&=+$,])*$/";

    /**
     * Parse request body into array of params
     *
     * @param string $body  Posted content from request
     * @return array        Return always array
     * @throws Exception|Mage_Api2_Exception
     */
    #[\Override]
    public function interpret($body)
    {
        if (!is_string($body)) {
            throw new Exception(sprintf('Invalid data type "%s". String expected.', gettype($body)));
        }

        if (!$this->_validateQuery($body)) {
            throw new Mage_Api2_Exception(
                'Invalid data type. Check Content-Type.',
                Mage_Api2_Model_Server::HTTP_BAD_REQUEST
            );
        }

        $data = [];
        parse_str($body, $data);
        return $data;
    }

    /**
     * Returns true if and only if the query string passes validation.
     *
     * @param  string $query The query to validate
     * @return bool
     * @link   http://www.faqs.org/rfcs/rfc2396.html
     */
    protected function _validateQuery($query)
    {
        return preg_match(self::URI_VALIDATE_PATTERN, $query);
    }
}
