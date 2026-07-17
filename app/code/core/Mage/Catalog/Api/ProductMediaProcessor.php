<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 */
final class ProductMediaProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly ProductMediaProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductMedia|array|null
    {
        $user = $this->getAuthorizedUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        // Enforce website scope for store-restricted API users on every
        // sub-resource write/delete (mirrors ProductProcessor's main CRUD check).
        $this->authorizeProductWebsites($this->loadProduct($productId), $user);

        $request = $context['request'] ?? null;
        $body = $this->parseRequestBody($request);

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'products/delete');
            $valueId = (int) ($body['valueId'] ?? $body['value_id'] ?? $body['id'] ?? 0);
            if ($valueId <= 0) {
                $valueId = (int) ($request?->query->get('valueId') ?? 0);
            }
            return $this->handleDelete($productId, $valueId);
        }

        $this->requirePermission($user, 'products/write');

        if ($operation instanceof Post) {
            return $this->handleUpload($productId, $body);
        }

        return $this->handleUpdate($productId, $body);
    }

    private function handleUpload(int $productId, array $body): ProductMedia
    {
        $product = $this->loadProduct($productId);

        // Support base64-encoded image data
        $base64 = $body['base64'] ?? $body['imageData'] ?? $body['image_data'] ?? null;
        $imageUrl = $body['imageUrl'] ?? $body['image_url'] ?? null;
        $filename = $body['filename'] ?? 'upload.jpg';
        $types = $body['types'] ?? [];
        $label = $body['label'] ?? null;

        $tmpPath = null;

        if ($base64 !== null) {
            // Decode base64 to temp file
            $decoded = base64_decode($base64, true);
            if ($decoded === false) {
                throw new BadRequestHttpException('Invalid base64 image data');
            }
            $ext = $this->sanitizeImageExtension(pathinfo($filename, PATHINFO_EXTENSION));
            $tmpPath = $this->writeTempImage($decoded, $ext);
        } elseif ($imageUrl !== null) {
            // Validate URL and get resolved IP to prevent DNS rebinding SSRF
            $validatedIp = $this->validateImageUrl($imageUrl);
            // Rebuild the URL from its parsed components using the validated IP
            // as the authority. Reconstructing (rather than str_replace) avoids
            // accidentally rewriting the host string where it appears in the
            // path/query, and pins the fetch to the IP that was just validated,
            // closing the DNS-rebinding TOCTOU window.
            $parsedUrl = parse_url($imageUrl);
            $originalHost = $parsedUrl['host'] ?? '';
            $scheme = $parsedUrl['scheme'] ?? 'http';
            $ipAuthority = str_contains($validatedIp, ':') ? "[{$validatedIp}]" : $validatedIp;
            if (isset($parsedUrl['port'])) {
                $ipAuthority .= ':' . $parsedUrl['port'];
            }
            $ipUrl = $scheme . '://' . $ipAuthority
                . ($parsedUrl['path'] ?? '')
                . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '');
            // Pin the fetch to the validated IP and forbid redirects: without
            // follow_location=0 / max_redirects=0 a server at the validated
            // (public) IP could 30x-redirect to an internal host (e.g. the
            // cloud metadata endpoint or 127.0.0.1), defeating the SSRF guard
            // that only validated the original host's IP.
            $context = stream_context_create([
                'http' => [
                    'header' => "Host: {$originalHost}\r\n",
                    'timeout' => 10,
                    'follow_location' => 0,
                    'max_redirects' => 0,
                ],
                // The URL is pinned to the validated IP, so for HTTPS the TLS
                // layer would otherwise validate the certificate against the IP
                // (SNI/peer name) and fail. Pin verification to the original host
                // name and keep peer verification on so a host at the validated
                // IP can't present an arbitrary certificate.
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'SNI_enabled' => true,
                    'peer_name' => $originalHost,
                ],
            ]);
            $imageData = @file_get_contents($ipUrl, false, $context);
            if ($imageData === false) {
                throw new BadRequestHttpException('Failed to download image from URL');
            }
            $ext = $this->sanitizeImageExtension(pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
            $tmpPath = $this->writeTempImage($imageData, $ext);
        } else {
            throw new BadRequestHttpException('Either base64, imageData, or imageUrl is required');
        }

        // The gallery backend only checks the extension; verify the bytes are
        // actually a supported image so a renamed non-image can't be stored.
        $this->assertImageFile($tmpPath);

        try {
            // Unset stock_data to prevent saving stock when we only want media
            $product->unsetData('stock_data');
            $product->addImageToMediaGallery(
                $tmpPath,
                empty($types) ? null : $types,
                false,
                false,
            );

            // Set label if provided via the gallery backend API
            if ($label !== null) {
                $gallery = $product->getData('media_gallery');
                if (!empty($gallery['images'])) {
                    $lastImage = end($gallery['images']);
                    $file = $lastImage['file'] ?? null;
                    if ($file) {
                        /** @var \Mage_Catalog_Model_Product_Attribute_Backend_Media $backend */
                        $backend = $product->getResource()->getAttribute('media_gallery')->getBackend();
                        $backend->updateImage($product, $file, ['label' => $label]);
                    }
                }
            }

            $product->save();
        } catch (\Throwable $e) {
            \Mage::logException($e);
            throw new UnprocessableEntityHttpException('Failed to upload image: ' . $e->getMessage());
        } finally {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }

        // Get the last added image
        $product = $this->loadProduct($productId);
        $images = $this->provider->getMediaGallery($product);
        if (!empty($images)) {
            return end($images);
        }

        $dto = new ProductMedia();
        $dto->id = 0;
        return $dto;
    }

    private function handleUpdate(int $productId, array $body): array
    {
        $product = $this->loadProduct($productId);

        $valueId = (int) ($body['valueId'] ?? $body['value_id'] ?? $body['id'] ?? 0);
        if ($valueId <= 0) {
            throw new BadRequestHttpException('valueId is required');
        }

        // Find the image file path by value_id
        $gallery = $product->getData('media_gallery');
        $images = $gallery['images'] ?? [];
        $file = null;

        foreach ($images as $image) {
            if ((int) ($image['value_id'] ?? 0) === $valueId) {
                $file = $image['file'] ?? null;
                break;
            }
        }

        if ($file === null) {
            throw new NotFoundHttpException('Gallery image not found');
        }

        // Use the gallery backend API to update image attributes
        $updateData = [];
        if (isset($body['label'])) {
            $updateData['label'] = $body['label'];
        }
        if (isset($body['position'])) {
            $updateData['position'] = (int) $body['position'];
        }
        if (isset($body['disabled'])) {
            $updateData['disabled'] = $body['disabled'] ? 1 : 0;
        }
        if (!empty($updateData)) {
            /** @var \Mage_Catalog_Model_Product_Attribute_Backend_Media $backend */
            $backend = $product->getResource()->getAttribute('media_gallery')->getBackend();
            $backend->updateImage($product, $file, $updateData);
        }

        // Update role assignments
        $types = $body['types'] ?? null;
        if (is_array($types)) {
            if (in_array('image', $types, true)) {
                $product->setData('image', $file);
            }
            if (in_array('small_image', $types, true)) {
                $product->setData('small_image', $file);
            }
            if (in_array('thumbnail', $types, true)) {
                $product->setData('thumbnail', $file);
            }
        }

        try {
            $product->unsetData('stock_data');
            $product->save();
        } catch (\Throwable $e) {
            \Mage::logException($e);
            throw new UnprocessableEntityHttpException('Failed to update image: ' . $e->getMessage());
        }

        return $this->provider->getMediaGallery($this->loadProduct($productId));
    }

    private function handleDelete(int $productId, int $valueId): null
    {
        $product = $this->loadProduct($productId);

        if ($valueId <= 0) {
            throw new BadRequestHttpException('valueId is required');
        }

        $gallery = $product->getData('media_gallery');
        $images = $gallery['images'] ?? [];
        $found = false;

        foreach ($images as &$image) {
            if ((int) ($image['value_id'] ?? 0) === $valueId) {
                $image['removed'] = 1;
                $found = true;
                break;
            }
        }
        unset($image);

        if (!$found) {
            throw new NotFoundHttpException('Gallery image not found');
        }

        $gallery['images'] = $images;
        $product->setData('media_gallery', $gallery);

        try {
            $product->unsetData('stock_data');
            $product->save();
        } catch (\Throwable $e) {
            \Mage::logException($e);
            throw new UnprocessableEntityHttpException('Failed to delete image: ' . $e->getMessage());
        }

        return null;
    }


    /**
     * Validate image URL to prevent SSRF attacks.
     * Returns the validated IP to use for the actual request (prevents DNS rebinding).
     */
    private function validateImageUrl(string $url): string
    {
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? '';
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new BadRequestHttpException('imageUrl must use http or https scheme');
        }

        $host = $parsed['host'] ?? '';
        if ($host === '') {
            throw new BadRequestHttpException('Invalid imageUrl');
        }

        // Explicitly reject IPv6 literals in private/reserved ranges. gethostbyname()
        // is IPv4-only and FILTER_FLAG_NO_PRIV_RANGE doesn't cover IPv6, so without
        // this guard a target like [::1] or [fd00::1] would only be blocked as a
        // side effect of gethostbyname() returning it unchanged. Make it explicit.
        $bareHost = str_starts_with($host, '[') ? substr($host, 1, -1) : $host;
        if (filter_var($bareHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            && filter_var($bareHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
        ) {
            throw new BadRequestHttpException('imageUrl cannot point to private or reserved IP addresses');
        }

        // Block private/internal IP ranges
        $ip = gethostbyname($host);
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            throw new BadRequestHttpException('Could not resolve imageUrl host');
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new BadRequestHttpException('imageUrl cannot point to private or reserved IP addresses');
        }

        return $ip;
    }

    /**
     * Reject anything that isn't a supported raster image. Deletes the temp
     * file on rejection so a failed upload leaves nothing behind.
     */
    /**
     * Constrain a user-supplied extension to a known image allowlist.
     *
     * The temp file is named with this extension before the magic-byte check in
     * assertImageFile() runs, so an untrusted filename/URL must never be able to
     * give the temp file an executable suffix (e.g. .php). Anything outside the
     * allowlist falls back to 'jpg'.
     */
    /**
     * Write image bytes to a temp file named with the given extension.
     *
     * Uses tempnam() to create the file, then atomically renames it to the
     * extension-suffixed name (the gallery backend keys off the extension). If
     * the rename fails the bare tempnam file is unlinked before throwing so no
     * temp file leaks. Returns the final, extension-suffixed path.
     */
    private function writeTempImage(string $data, string $ext): string
    {
        $tmpPath = tempnam(\Mage::getBaseDir('tmp'), 'maho_media_');
        if ($tmpPath === false) {
            throw new UnprocessableEntityHttpException('Failed to create temporary file for upload');
        }
        file_put_contents($tmpPath, $data);
        $newPath = $tmpPath . '.' . $ext;
        if (!rename($tmpPath, $newPath)) {
            @unlink($tmpPath);
            throw new UnprocessableEntityHttpException('Failed to prepare uploaded image');
        }
        return $newPath;
    }

    private function sanitizeImageExtension(string $ext): string
    {
        $ext = strtolower($ext);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
        return in_array($ext, $allowed, true) ? $ext : 'jpg';
    }

    private function assertImageFile(string $path): void
    {
        $info = @\Maho\Io::getImageSize($path);
        $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_AVIF];
        if ($info === false || !in_array($info[2], $allowed, true)) {
            @unlink($path);
            throw new BadRequestHttpException('Uploaded data is not a valid JPEG, PNG, GIF, WEBP or AVIF image');
        }
    }
}
