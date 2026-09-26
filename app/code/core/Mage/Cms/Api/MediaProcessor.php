<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

namespace Mage\Cms\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Mage;
use Mage_Core_Model_File_Uploader;
use Mage_Core_Model_Store;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Media State Processor.
 *
 * Handles file uploads (POST) and deletions (DELETE) for the media gallery.
 * Requires JWT authentication with media/write permission.
 *
 * @implements ProcessorInterface<Media, Media|null>
 */
final class MediaProcessor implements ProcessorInterface
{
    use AuthenticationTrait;

    public function __construct(
        Security $security,
        private readonly RequestStack $requestStack,
    ) {
        $this->security = $security;
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Media
    {
        $user = $this->requireUser();

        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($uriVariables['path'], $user);
        }

        return $this->handleUpload($user);
    }

    private function handleUpload(ApiUser $user): Media
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new BadRequestHttpException('No valid file uploaded');
        }

        // Storage::uploadFile() expects $_FILES['image']
        $_FILES['image'] = $_FILES['file'];

        $helper = Mage::helper('cms/wysiwyg_images');
        $storage = $helper->getStorage();
        $mount = $helper->getMount();

        $targetDir = $helper->resolveFolder($request->request->get('folder', 'wysiwyg'))
            ?? throw new BadRequestHttpException('Invalid folder path');
        $mount->createDirectory($targetDir);

        $result = $storage->uploadFile($targetDir, 'image');
        if (!$result) {
            throw new UnprocessableEntityHttpException('Failed to upload file');
        }

        $uploadedPath = $result['path'] . '/' . $result['file'];

        // Convert to configured image format using Intervention Image
        $targetType = Mage::getStoreConfigAsInt('system/media_storage_configuration/image_file_type') ?: IMAGETYPE_WEBP;
        $targetExt = image_type_to_extension($targetType, false);
        $quality = Mage::getStoreConfigAsInt('system/media_storage_configuration/image_quality');

        $customFilename = $request->request->get('filename');
        $baseName = $customFilename
            ? pathinfo(Mage_Core_Model_File_Uploader::getCorrectFileName($customFilename . '.' . $targetExt), PATHINFO_FILENAME)
            : pathinfo($result['file'], PATHINFO_FILENAME);

        $targetFilename = $baseName . '.' . $targetExt;
        $targetPath = $result['path'] . '/' . $targetFilename;
        $counter = 1;
        while ($targetPath !== $uploadedPath && $mount->fileExists($targetPath)) {
            $targetFilename = $baseName . '_' . $counter . '.' . $targetExt;
            $targetPath = $result['path'] . '/' . $targetFilename;
            $counter++;
        }

        $image = \Maho::getImageManager()->decodeBinary($mount->read($uploadedPath));
        $encoded = (string) $image->encodeUsingPath($targetPath, quality: $quality);
        $mount->write($targetPath, $encoded);

        if ($uploadedPath !== $targetPath) {
            $mount->delete($uploadedPath);
        }

        $this->logActivity('upload', $targetPath, $user);

        $media = new Media();
        $media->url = $mount->publicUrl($targetPath);
        $media->directive = sprintf('{{media url="%s"}}', $targetPath);
        $media->size = strlen($encoded);
        $media->dimensions = ['width' => $image->width(), 'height' => $image->height()];
        $media->filename = $targetFilename;
        $media->path = $targetPath;

        return $media;
    }

    private function handleDelete(string $path, ApiUser $user): null
    {
        $helper = Mage::helper('cms/wysiwyg_images');
        $root = $helper->getStorageRootPath();
        $relative = preg_replace('#^' . preg_quote($root, '#') . '(/|$)#', '', str_replace('\\', '/', $helper->correctPath($path)));
        $fullPath = $relative === '' || $relative === null ? null : \Maho\Io::getPathWithinMount($helper->getMount(), $root, $relative);

        if ($fullPath === null || !$helper->getMount()->fileExists($fullPath)) {
            throw new NotFoundHttpException('File not found');
        }

        $helper->getStorage()->deleteFile($fullPath);

        $this->logActivity('delete', $path, $user);

        return null;
    }

    private function logActivity(string $action, string $path, ApiUser $user): void
    {
        try {
            /** @var \Maho_AdminActivityLog_Model_Activity $activity */
            $activity = Mage::getModel('adminactivitylog/activity');
            $activity->logActivity([
                'entity_type' => 'cms/media',
                'action' => $action,
                'entity_id' => 0,
                'old_data' => $action === 'delete' ? ['path' => $path] : null,
                'new_data' => $action === 'upload' ? ['path' => $path] : null,
                'api_user_id' => $user->getApiUserId(),
                'username' => 'API: ' . $user->getUserIdentifier(),
            ]);
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }
}
