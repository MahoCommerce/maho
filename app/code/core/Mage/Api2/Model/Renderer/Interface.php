<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Webservice API2 renderer adapter interface
 *
 * @category   Mage
 * @package    Mage_Api2
 */
interface Mage_Api2_Model_Renderer_Interface
{
    /**
     * Render content in a certain format
     *
     * @param array|object $data
     * @return string
     */
    public function render($data);

    /**
     * Get MIME type generated by renderer
     *
     * @return string
     */
    public function getMimeType();
}
