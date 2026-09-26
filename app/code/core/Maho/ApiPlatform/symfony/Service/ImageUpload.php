<?php

/**
 * Checks an image that an API request sends as base64 and gives it to an attribute backend.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Service;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * The request body has two fields: base64 (the image file) and filename (for example photo.jpg).
 * The backend saves the file with a LocalFileUploader, so the file name, the folder and the
 * image validator are the same as for an upload in the admin.
 */
final class ImageUpload
{
    /** The largest decoded image that an upload accepts, in bytes. */
    public const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    private const IMAGE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_AVIF];

    /**
     * Check the request body, write the image to a temporary file and give an uploader
     * for that file to $save. The temporary file is always deleted.
     *
     * @template T
     * @param array<mixed> $body the request body
     * @param list<string> $allowedExtensions the file extensions that the backend accepts
     * @param callable(\Mage_Core_Model_File_Uploader): T $save saves the file of the uploader
     * @return T the return value of $save
     * @throws BadRequestHttpException when the body is not a valid image
     * @throws UnprocessableEntityHttpException when the image cannot be saved
     */
    public static function save(array $body, array $allowedExtensions, callable $save): mixed
    {
        $base64 = $body['base64'] ?? null;
        $fileName = $body['filename'] ?? null;
        if (!is_string($base64) || $base64 === '') {
            throw new BadRequestHttpException('base64 is required');
        }
        if (!is_string($fileName) || trim($fileName) === '') {
            throw new BadRequestHttpException('filename is required');
        }

        $tooLarge = sprintf('The image is larger than %d MB', self::MAX_IMAGE_BYTES / 1024 / 1024);
        // Four base64 characters hold three bytes, so a longer string cannot decode to an allowed size
        if (strlen($base64) > (int) ceil(self::MAX_IMAGE_BYTES / 3) * 4) {
            throw new BadRequestHttpException($tooLarge);
        }
        $decoded = base64_decode($base64, true);
        if ($decoded === false || $decoded === '') {
            throw new BadRequestHttpException('Invalid base64 image data');
        }
        if (strlen($decoded) > self::MAX_IMAGE_BYTES) {
            throw new BadRequestHttpException($tooLarge);
        }

        $fileName = basename(str_replace('\\', '/', $fileName));
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new BadRequestHttpException('The filename extension must be one of: ' . implode(', ', $allowedExtensions));
        }

        $tmpPath = self::writeTempFile($decoded);
        try {
            $info = \Maho\Io::getImageSize($tmpPath);
            if ($info === false || !in_array($info[2], self::IMAGE_TYPES, true)) {
                throw new BadRequestHttpException('The data is not a valid JPEG, PNG, GIF, WEBP or AVIF image');
            }

            return $save(new LocalFileUploader($tmpPath, $fileName));
        } catch (\Mage_Core_Exception $e) {
            // The image validator of the uploader rejects the file
            throw new BadRequestHttpException($e->getMessage());
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Mage::logException($e);
            throw new UnprocessableEntityHttpException('Failed to save the image: ' . $e->getMessage());
        } finally {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    private static function writeTempFile(string $data): string
    {
        $path = tempnam(\Mage::getBaseDir('tmp'), 'api_image_');
        if ($path === false || file_put_contents($path, $data) === false) {
            if ($path !== false) {
                @unlink($path);
            }
            throw new UnprocessableEntityHttpException('Failed to create a temporary file for the image');
        }

        return $path;
    }
}
