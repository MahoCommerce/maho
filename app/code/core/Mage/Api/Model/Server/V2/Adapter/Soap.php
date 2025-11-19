<?php

/**
 * Maho
 *
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Laminas\Soap\Exception\ExceptionInterface as LaminasSoapException;

class Mage_Api_Model_Server_V2_Adapter_Soap extends Mage_Api_Model_Server_Adapter_Soap
{
    /**
     * Get wsdl config
     *
     * @return Mage_Api_Model_Wsdl_Config
     */
    #[\Override]
    protected function _getWsdlConfig()
    {
        return Mage::getModel('api/wsdl_config');
    }

    /**
     * Run webservice
     *
     * @return Mage_Api_Model_Server_Adapter_Soap
     * @throws SoapFault
     */
    #[\Override]
    public function run()
    {
        $apiConfigCharset = Mage::getStoreConfig('api/config/charset');

        if ($this->getController()->getRequest()->getParam('wsdl') !== null) {
            $this->wsdlConfig->setHandler($this->getHandler())
                ->init();

            $this->getController()->getResponse()
                ->clearHeaders()
                ->setHeader('Content-Type', 'text/xml; charset=' . $apiConfigCharset)
                ->setBody(
                    preg_replace(
                        '/<\?xml version="([^\"]+)"([^\>]+)>/i',
                        '<?xml version="$1" encoding="' . $apiConfigCharset . '"?>',
                        $this->wsdlConfig->getWsdlContent(),
                    ),
                );
        } else {
            try {
                $this->_instantiateServer();

                $content = str_replace(
                    '><',
                    ">\n<",
                    preg_replace(
                        '/<\?xml version="([^\"]+)"([^\>]+)>/i',
                        '<?xml version="$1" encoding="' . $apiConfigCharset . '"?>',
                        $this->_soap->handle(),
                    ),
                );
                $this->getController()->getResponse()
                    ->clearHeaders()
                    ->setHeader('Content-Type', 'text/xml; charset=' . $apiConfigCharset)
                    ->setHeader('Content-Length', strlen($content), true)
                    ->setBody($content);
            } catch (LaminasSoapException $e) {
                $this->fault($e->getCode(), $e->getMessage());
            } catch (Exception $e) {
                $this->fault($e->getCode(), $e->getMessage());
            }
        }

        return $this;
    }
}
