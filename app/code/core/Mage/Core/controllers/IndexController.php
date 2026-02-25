<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(): void
    {
        $this->_forward('noRoute');
    }

    /**
     * Deferred image resize endpoint.
     *
     * On cache miss, Mage_Catalog_Helper_Image returns a signed URL pointing here
     * instead of generating the thumbnail synchronously during page render.
     * The browser then fetches images in parallel, each request generating and
     * caching one thumbnail.
     */
    public function resizeAction(): void
    {
        $t = $this->getRequest()->getParam('t', '');
        $s = $this->getRequest()->getParam('s', '');

        if ($t === '' || $s === '') {
            $this->getResponse()->setHttpResponseCode(400);
            return;
        }

        $params = Maho::verifyImageResizeRequest($t, $s, Mage::getEncryptionKeyAsHex());
        if ($params === null) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        if (!isset($params['_sourceFile']) || !is_string($params['_sourceFile'])) {
            $this->getResponse()->setHttpResponseCode(400);
            return;
        }

        // Reconstruct the absolute path from the relative file and known base media path
        $baseMediaPath = Mage::getSingleton('catalog/product_media_config')->getBaseMediaPath();
        $realSrc = realpath($baseMediaPath . $params['_sourceFile']);

        // Path traversal protection — resolved path must stay within media/
        if ($realSrc === false || !str_starts_with($realSrc, realpath($baseMediaPath) . DIRECTORY_SEPARATOR)) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        // Hydrate the model from signed params and process
        /** @var Mage_Catalog_Model_Product_Image $model */
        $model = Mage::getModel('catalog/product_image');
        $model->setTransformParams($params);
        $model->setBaseFile($params['_sourceFile']);

        if (!$model->isCached()) {
            $encoded = $model->saveFile();

            $this->getResponse()
                ->setHeader('Content-Type', $encoded->mediaType())
                ->setHeader('Content-Length', (string) $encoded->size())
                ->setBody((string) $encoded);
            return;
        }

        // Serve cached file from disk (race condition path)
        $file = $model->getNewFile();
        $this->getResponse()
            ->setHeader('Content-Type', mime_content_type($file))
            ->setHeader('Content-Length', (string) filesize($file))
            ->setBody(file_get_contents($file));
    }
}
