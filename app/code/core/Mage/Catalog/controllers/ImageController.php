<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Deferred product image resize controller.
 *
 * On cache miss, Mage_Catalog_Helper_Image returns a signed URL pointing here
 * instead of generating the thumbnail synchronously during page render.
 * The browser then fetches images in parallel, each request generating and
 * caching one thumbnail.
 */
class Mage_Catalog_ImageController extends Mage_Core_Controller_Front_Action
{
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

        $requiredKeys = ['src', 'w', 'h', 'q', 'fmt', 'ar', 'fr', 'tr', 'co', 'bg', 'an', 'sub', 'sid', 'ph'];
        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $params)) {
                $this->getResponse()->setHttpResponseCode(400);
                return;
            }
        }

        // Path traversal protection
        $realSrc = realpath($params['src']);
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
        $model->setDestinationSubdir($params['sub']);
        $model->setWidth($params['w']);
        $model->setHeight($params['h']);
        $model->setQuality($params['q']);
        $model->setKeepAspectRatio($params['ar']);
        $model->setKeepFrame($params['fr']);
        $model->setKeepTransparency($params['tr']);
        $model->setConstrainOnly($params['co']);
        $model->setBackgroundColor(array_map(
            fn($hex) => hexdec($hex),
            str_split($params['bg'], 2),
        ));
        $model->setAngle($params['an']);

        if (isset($params['wm'])) {
            $model->setWatermarkFile($params['wm']);
            $model->setWatermarkImageOpacity($params['wmo']);
            $model->setWatermarkPosition($params['wmp']);
            if ($params['wmw']) {
                $model->setWatermarkWidth($params['wmw']);
            }
            if ($params['wmh']) {
                $model->setWatermarkHeigth($params['wmh']);
            }
        }

        $baseMediaPath = Mage::getSingleton('catalog/product_media_config')->getBaseMediaPath();
        $relativeFile = str_replace($baseMediaPath, '', $realSrc);
        if (!str_starts_with($relativeFile, '/')) {
            $relativeFile = '/' . basename($realSrc);
        }
        $model->setBaseFile($relativeFile);

        if (!$model->isCached()) {
            if ($params['an'] != 0) {
                $model->rotate($params['an']);
            }
            $model->resize();
            if (isset($params['wm'])) {
                $model->setWatermark($params['wm']);
            }
            $model->saveFile();
        }

        // Serve the image
        $file = $model->getNewFile();
        $mime = match ((int) $params['fmt']) {
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
