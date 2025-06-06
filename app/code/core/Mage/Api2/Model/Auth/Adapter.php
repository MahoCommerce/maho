<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Api2_Model_Auth_Adapter
{
    /**
     * Adapter models
     *
     * @var array
     */
    protected $_adapters = [];

    /**
     * Load adapters configuration and create adapters models
     *
     * @return $this
     * @throws Exception
     */
    protected function _initAdapters()
    {
        /** @var Mage_Api2_Helper_Data $helper */
        $helper = Mage::helper('api2');

        foreach ($helper->getAuthAdapters(true) as $adapterKey => $adapterParams) {
            $adapterModel = Mage::getModel($adapterParams['model']);

            if (!$adapterModel instanceof Mage_Api2_Model_Auth_Adapter_Abstract) {
                throw new Exception('Authentication adapter must to extend Mage_Api2_Model_Auth_Adapter_Abstract');
            }
            $this->_adapters[$adapterKey] = $adapterModel;
        }
        if (!$this->_adapters) {
            throw new Exception('No active authentication adapters found');
        }
        return $this;
    }

    /**
     * Process request and figure out an API user type and its identifier
     *
     * Returns stdClass object with two properties: type and id
     *
     * @return stdClass
     */
    public function getUserParams(Mage_Api2_Model_Request $request)
    {
        $this->_initAdapters();

        foreach ($this->_adapters as $adapterModel) {
            /** @var Mage_Api2_Model_Auth_Adapter_Abstract $adapterModel */
            if ($adapterModel->isApplicableToRequest($request)) {
                $userParams = $adapterModel->getUserParams($request);

                if ($userParams->type !== null) {
                    return $userParams;
                }
                throw new Mage_Api2_Exception('Can not determine user type', Mage_Api2_Model_Server::HTTP_UNAUTHORIZED);
            }
        }
        return (object) ['type' => Mage_Api2_Model_Auth::DEFAULT_USER_TYPE, 'id' => null];
    }
}
