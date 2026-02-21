<?php

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Dataflow_Model_Convert_Adapter_Http_Curl extends Mage_Dataflow_Model_Convert_Adapter_Abstract
{
    #[\Override]
    public function load()
    {
        // we expect <var name="uri">http://...</var>
        $uri = $this->getVar('uri');

        // validate input parameter
        if (!Mage::helper('core')->isValidUrl($uri)) {
            $this->addException("Expecting a valid 'uri' parameter");
        }

        // use Symfony HttpClient
        $client = \Maho\Http\Client::create();

        try {
            // send GET request and read the remote file
            $response = $client->request('GET', $uri);
            $data = trim($response->getContent());
        } catch (Exception $e) {
            $this->addException('Error fetching data from URI: ' . $e->getMessage());
            return $this;
        }

        // save contents into container
        $this->setData($data);

        return $this;
    }

    #[\Override]
    public function save()
    {
        // no save implemented
        return $this;
    }
}
