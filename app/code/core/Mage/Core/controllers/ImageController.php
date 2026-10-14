<?php

/**
 * Creates a resized image that a page links to but that does not exist.
 *
 * The save of an entity, the import and image:resize create the files before a page links to
 * them. A file can still go missing: a resize failed, or an extension wrote an image with SQL.
 * The web server serves a file that exists and sends a miss to this route: the rewrite rule in
 * public/.htaccess, or a try_files in nginx. On a bucket, the CDN can send a miss here at the same
 * path, and the file goes to the bucket.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_ImageController extends Mage_Core_Controller_Front_Action
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
        '/media/{path}',
        name: 'core.image.create',
        methods: ['GET', 'HEAD'],
        requirements: ['path' => '.+/cache/[0-9a-f]{12}/\d+/.+'],
    )]
    public function createAction(): void
    {
        $response = $this->getResponse();
        $path = (string) $this->getRequest()->getParam('path', '');
        $binary = Mage::getSingleton('core/image_resizer')->createFileAtPath($path);
        if ($binary === null) {
            $response->setHeader('Cache-Control', 'no-store', true)->setHttpResponseCode(404);
            return;
        }

        $response
            ->setHeader('Content-Type', new finfo(FILEINFO_MIME_TYPE)->buffer($binary) ?: 'application/octet-stream', true)
            ->setHeader('Content-Length', (string) strlen($binary), true)
            ->setHeader('Cache-Control', 'public, max-age=31536000', true)
            ->setBody($binary);
    }
}
