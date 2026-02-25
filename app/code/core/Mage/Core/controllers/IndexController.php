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

        if (!isset($params['_baseFile'])) {
            $this->getResponse()->setHttpResponseCode(400);
            return;
        }

        // Path traversal protection
        $realSrc = realpath($params['_baseFile']);
        if ($realSrc === false) {
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $allowedPrefixes = array_filter([
            realpath(Mage::getBaseDir('media')),
            realpath(Mage::getBaseDir('skin')),
        ]);

        $allowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($realSrc, $prefix . DIRECTORY_SEPARATOR)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        // Hydrate the model from signed params and process
        /** @var Mage_Catalog_Model_Product_Image $model */
        $model = Mage::getModel('catalog/product_image');
        $model->setTransformParams($params);

        $baseMediaPath = Mage::getSingleton('catalog/product_media_config')->getBaseMediaPath();
        $relativeFile = str_replace($baseMediaPath, '', $realSrc);
        if (!str_starts_with($relativeFile, '/')) {
            $relativeFile = '/' . basename($realSrc);
        }
        $model->setBaseFile($relativeFile);

        if (!$model->isCached()) {
            if ($params['_angle'] != 0) {
                $model->rotate($params['_angle']);
            }
            $model->resize();
            if (isset($params['_watermarkFile'])) {
                $model->setWatermark($params['_watermarkFile']);
            }
            $model->saveFile();
        }

        // Serve the image
        $file = $model->getNewFile();
        $mime = match ((int) Mage::getStoreConfig('system/media_storage_configuration/image_file_type')) {
            IMAGETYPE_AVIF => 'image/avif',
            IMAGETYPE_GIF  => 'image/gif',
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_PNG  => 'image/png',
            default        => 'image/webp',
        };

        $this->getResponse()
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Length', (string) filesize($file))
            ->setHeader('Cache-Control', 'public, max-age=31536000, immutable')
            ->setBody(file_get_contents($file));
    }
}
