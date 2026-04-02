<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
 * Media State Processor
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
        $user = $this->getAuthorizedUser();
        $this->requirePermission($user, 'media/write');

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

        // Resolve target directory within wysiwyg storage root
        $folder = $request->request->get('folder', 'wysiwyg');
        $folder = $helper->correctPath($folder);
        $targetDir = $helper->getStorageRoot();

        if ($folder !== 'wysiwyg' && $folder !== '') {
            $subFolder = preg_replace('#^wysiwyg/?#', '', $folder);
            if ($subFolder) {
                $targetDir .= $subFolder;
            }
        }

        $io = new \Maho\Io\File();
        $io->checkAndCreateFolder($targetDir);

        $realStorageRoot = realpath($helper->getStorageRoot());
        $realTargetDir = realpath($targetDir);
        if (!$realTargetDir || !str_starts_with($realTargetDir, $realStorageRoot)) {
            throw new BadRequestHttpException('Invalid folder path');
        }

        $result = $storage->uploadFile($realTargetDir, 'image');
        if (!$result) {
            throw new UnprocessableEntityHttpException('Failed to upload file');
        }

        $uploadedPath = $result['path'] . DS . $result['file'];

        // Convert to configured image format using Intervention Image
        $targetType = Mage::getStoreConfigAsInt('system/media_storage_configuration/image_file_type') ?: IMAGETYPE_WEBP;
        $targetExt = image_type_to_extension($targetType, false);
        $quality = Mage::getStoreConfigAsInt('system/media_storage_configuration/image_quality');

        $customFilename = $request->request->get('filename');
        $baseName = $customFilename
            ? pathinfo(Mage_Core_Model_File_Uploader::getCorrectFileName($customFilename . '.' . $targetExt), PATHINFO_FILENAME)
            : pathinfo($result['file'], PATHINFO_FILENAME);

        $targetFilename = $baseName . '.' . $targetExt;
        $targetPath = $result['path'] . DS . $targetFilename;
        $counter = 1;
        while (file_exists($targetPath) && $targetPath !== $uploadedPath) {
            $targetFilename = $baseName . '_' . $counter . '.' . $targetExt;
            $targetPath = $result['path'] . DS . $targetFilename;
            $counter++;
        }

        \Maho::getImageManager()->decodePath($uploadedPath)->save($targetPath, quality: $quality);

        if ($uploadedPath !== $targetPath && file_exists($uploadedPath)) {
            unlink($uploadedPath);
        }

        // Build response
        $mediaDir = Mage::getConfig()->getOptions()->getMediaDir();
        $relativePath = str_replace(DS, '/', str_replace($mediaDir . DS, '', $targetPath));
        $imageSize = \Maho\Io::getImageSize($targetPath);

        $this->logActivity('upload', $relativePath, $user);

        $media = new Media();
        $media->url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . $relativePath;
        $media->directive = sprintf('{{media url="%s"}}', $relativePath);
        $media->size = filesize($targetPath);
        $media->dimensions = $imageSize ? ['width' => $imageSize[0], 'height' => $imageSize[1]] : null;
        $media->filename = $targetFilename;
        $media->path = $relativePath;

        return $media;
    }

    private function handleDelete(string $path, ApiUser $user): null
    {
        $helper = Mage::helper('cms/wysiwyg_images');
        $storageRoot = realpath($helper->getStorageRoot());
        $fullPath = realpath($storageRoot . DS . $helper->correctPath($path));

        if (!$fullPath || !is_file($fullPath) || !str_starts_with($fullPath, $storageRoot)) {
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
