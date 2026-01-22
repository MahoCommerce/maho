<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Trait for sending JSON responses in admin controllers
 */
trait Maho_FeedManager_Controller_Adminhtml_JsonResponseTrait
{
    /**
     * Send a JSON response
     */
    protected function _sendJsonResponse(array $data): void
    {
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(Mage::helper('core')->jsonEncode($data));
    }
}
