<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Api2_Model_Renderer_Xml_Writer
{
    /**
     * Root node in XML output
     */
    public const XML_ROOT_NODE = 'magento_api';

    /**
     * Configuration data
     */
    protected array $config;

    /**
     * Constructor
     */
    public function __construct(array $options = [])
    {
        if (isset($options['config'])) {
            $this->config = $options['config'];
        }
    }

    /**
     * Render array data into XML config string
     */
    public function render(): string
    {
        $xml = new SimpleXMLElement('<' . self::XML_ROOT_NODE . '/>');

        if (isset($this->config)) {
            $this->addBranch($this->config, $xml);
        }

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
    }

    /**
     * Add array data to XML element
     */
    protected function addBranch(array $data, SimpleXMLElement $parent): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $parent->addChild($key);
                $this->addBranch($value, $child);
            } else {
                $parent->addChild($key, htmlspecialchars((string) $value, ENT_XML1, 'UTF-8'));
            }
        }
    }
}
