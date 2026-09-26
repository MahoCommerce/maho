<?php

/**
 * Creates a resized product image that is not in the cache yet.
 *
 * The web server serves a cache file that exists. A miss falls through to this route: the
 * rewrite rule in public/.htaccess, a try_files in nginx, or the 404 fallback of a CDN in
 * front of a bucket. The route reads the source from the media mount, resizes it, writes
 * the result back to the mount, and returns the bytes. Two nodes can resize the same image
 * at the same time. The output is identical, so no lock is necessary.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Product_ImageController extends Mage_Core_Controller_Front_Action
{
    #[\Override]
    public function preDispatch()
    {
        $this->setFlag('', self::FLAG_NO_START_SESSION, 1);
        $this->setFlag('', self::FLAG_NO_PRE_DISPATCH, 1);
        $this->setFlag('', self::FLAG_NO_POST_DISPATCH, 1);

        return parent::preDispatch();
    }

    #[Maho\Config\Route(
        '/media/catalog/product/cache/{path}',
        name: 'catalog.product.image',
        methods: ['GET', 'HEAD'],
        requirements: ['path' => '.+'],
    )]
    public function cacheAction(): void
    {
        $response = $this->getResponse();
        $response->setHeader('Cache-Control', 'no-store', true);

        $path = (string) $this->getRequest()->getParam('path', '');
        $image = Mage::getSingleton('catalog/product_image_variant')
            ->createImage(Mage_Catalog_Model_Product_Image::CACHE_DIRECTORY . '/' . $path);
        if ($image === null) {
            $response->setHttpResponseCode(404);
            return;
        }

        if (!$image->sourceExists()) {
            $response->setRedirect($image->getPlaceholderUrl(), 302);
            return;
        }

        $binary = $image->getCacheBinary();
        $response
            ->setHeader('Content-Type', image_type_to_mime_type(Maho::getConfiguredImageType()), true)
            ->setHeader('Content-Length', (string) strlen($binary), true)
            ->setHeader('Cache-Control', 'public, max-age=31536000', true)
            ->setBody($binary);
    }
}
