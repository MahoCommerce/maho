<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

        $request = $context['request'] ?? null;
        $body = $request ? json_decode($request->getContent(), true) : [];

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
            $tmpPath = tempnam(sys_get_temp_dir(), 'maho_media_');
            file_put_contents($tmpPath, $decoded);
            // Rename with proper extension
            $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';
            $newPath = $tmpPath . '.' . $ext;
            rename($tmpPath, $newPath);
            $tmpPath = $newPath;
        } elseif ($imageUrl !== null) {
            // Validate URL and get resolved IP to prevent DNS rebinding SSRF
            $validatedIp = $this->validateImageUrl($imageUrl);
            // Replace hostname with validated IP for the actual request
            $parsedUrl = parse_url($imageUrl);
            $originalHost = $parsedUrl['host'] ?? '';
            $ipUrl = str_replace($originalHost, $validatedIp, $imageUrl);
            $context = stream_context_create(['http' => [
                'header' => "Host: {$originalHost}\r\n",
                'timeout' => 10,
            ]]);
            $imageData = @file_get_contents($ipUrl, false, $context);
            if ($imageData === false) {
                throw new BadRequestHttpException('Failed to download image from URL');
            }
            $ext = pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg';
            $tmpPath = tempnam(sys_get_temp_dir(), 'maho_media_') . '.' . $ext;
            file_put_contents($tmpPath, $imageData);
        } else {
            throw new BadRequestHttpException('Either base64, imageData, or imageUrl is required');
        }

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
            if ($tmpPath && file_exists($tmpPath)) {
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
            $backend = $product->getResource()->getAttribute('media_gallery')->getBackend();
            $backend->updateImage($product, $file, $updateData);
        }

        // Update role assignments
        $types = $body['types'] ?? null;
        if (is_array($types) && $file !== null) {
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
}
