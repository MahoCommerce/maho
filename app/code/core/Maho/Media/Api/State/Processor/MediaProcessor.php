<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Media\Api\State\Processor;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Mage;
use Mage_Cms_Model_Wysiwyg_Config;
use Mage_Core_Model_File_Uploader;
use Mage_Core_Model_Store;
use Maho\Media\Api\Resource\Media;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\Io\File as IoFile;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const WEBP_QUALITY = 85;

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {}

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

        $uploadedFile = $request?->files->get('file');
        if (!$uploadedFile || !$uploadedFile->isValid()) {
            $error = $uploadedFile?->getErrorMessage() ?? 'No file uploaded';
            throw new BadRequestHttpException('File upload failed: ' . $error);
        }

        if ($uploadedFile->getSize() > self::MAX_FILE_SIZE) {
            throw new BadRequestHttpException(
                sprintf('File size exceeds maximum allowed size of %d MB', self::MAX_FILE_SIZE / 1024 / 1024),
            );
        }

        $originalExtension = strtolower($uploadedFile->getClientOriginalExtension());
        if (!in_array($originalExtension, self::ALLOWED_EXTENSIONS, true)) {
            throw new BadRequestHttpException(
                'Invalid file type. Allowed types: ' . implode(', ', self::ALLOWED_EXTENSIONS),
            );
        }

        $folder = $request->request->get('folder', 'wysiwyg');
        $folder = $this->sanitizeFolderPath($folder);

        $mediaDir = Mage::getConfig()->getOptions()->getMediaDir();
        $wysiwygDir = $mediaDir . DS . Mage_Cms_Model_Wysiwyg_Config::IMAGE_DIRECTORY;
        $targetDir = $wysiwygDir;

        if ($folder !== 'wysiwyg' && $folder !== '') {
            $subFolder = preg_replace('#^wysiwyg/?#', '', $folder);
            if ($subFolder) {
                $targetDir = $wysiwygDir . DS . $subFolder;
            }
        }

        $realWysiwygDir = realpath($wysiwygDir);
        if (!$realWysiwygDir) {
            $io = new IoFile();
            $io->checkAndCreateFolder($wysiwygDir);
            $realWysiwygDir = realpath($wysiwygDir);
        }

        if (!is_dir($targetDir)) {
            $io = new IoFile();
            $io->checkAndCreateFolder($targetDir);
        }

        $realTargetDir = realpath($targetDir);
        if (!$realTargetDir || !str_starts_with($realTargetDir, $realWysiwygDir)) {
            throw new BadRequestHttpException('Invalid folder path. Must be within wysiwyg/');
        }

        $customFilename = $request->request->get('filename');
        if ($customFilename) {
            $customFilename = pathinfo($customFilename, PATHINFO_FILENAME);
            $baseFilename = Mage_Core_Model_File_Uploader::getCorrectFileName($customFilename . '.webp');
            $baseFilename = pathinfo($baseFilename, PATHINFO_FILENAME);
        } else {
            $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $baseFilename = Mage_Core_Model_File_Uploader::getCorrectFileName($originalName . '.tmp');
            $baseFilename = pathinfo($baseFilename, PATHINFO_FILENAME);
        }

        $finalFilename = $baseFilename . '.webp';
        $destPath = $realTargetDir . DS . $finalFilename;
        $counter = 1;
        while (file_exists($destPath)) {
            $finalFilename = $baseFilename . '_' . $counter . '.webp';
            $destPath = $realTargetDir . DS . $finalFilename;
            $counter++;
        }

        $tmpPath = $uploadedFile->getPathname();
        $this->convertToWebp($tmpPath, $destPath);

        $imageSize = \Maho\Io::getImageSize($destPath);
        $dimensions = null;
        if ($imageSize) {
            $dimensions = [
                'width' => $imageSize[0],
                'height' => $imageSize[1],
            ];
        }

        $relativePath = str_replace($mediaDir . DS, '', $destPath);
        $relativePath = str_replace(DS, '/', $relativePath);

        $mediaUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
        $fileUrl = $mediaUrl . $relativePath;

        $directive = sprintf('{{media url="%s"}}', $relativePath);

        $this->logActivity('upload', $relativePath, $user);

        $media = new Media();
        $media->url = $fileUrl;
        $media->directive = $directive;
        $media->size = filesize($destPath);
        $media->dimensions = $dimensions;
        $media->filename = $finalFilename;
        $media->path = $relativePath;

        return $media;
    }

    private function handleDelete(string $path, ApiUser $user): null
    {
        $path = $this->sanitizeFolderPath($path);

        if (!str_starts_with($path, 'wysiwyg/')) {
            throw new BadRequestHttpException('Path must be within wysiwyg/');
        }

        $mediaDir = Mage::getConfig()->getOptions()->getMediaDir();
        $fullPath = $mediaDir . DS . str_replace('/', DS, $path);

        $wysiwygDir = $mediaDir . DS . Mage_Cms_Model_Wysiwyg_Config::IMAGE_DIRECTORY;
        $realWysiwygDir = realpath($wysiwygDir);
        $realFullPath = realpath($fullPath);

        if (!$realFullPath) {
            throw new NotFoundHttpException('File not found');
        }

        if (!str_starts_with($realFullPath, $realWysiwygDir)) {
            throw new BadRequestHttpException('Path must be within wysiwyg/');
        }

        if (!is_file($realFullPath)) {
            throw new NotFoundHttpException('File not found');
        }

        $io = new IoFile();
        if (!$io->rm($realFullPath)) {
            throw new UnprocessableEntityHttpException('Failed to delete file');
        }

        $thumbsDir = $wysiwygDir . DS . '.thumbs';
        $relativePath = str_replace($realWysiwygDir, '', $realFullPath);
        $thumbPath = $thumbsDir . $relativePath;
        if (file_exists($thumbPath)) {
            $io->rm($thumbPath);
        }

        $this->logActivity('delete', $path, $user);

        return null;
    }

    private function convertToWebp(string $sourcePath, string $destPath): void
    {
        $imageData = file_get_contents($sourcePath);
        if ($imageData === false) {
            throw new UnprocessableEntityHttpException('Failed to read uploaded file');
        }

        $image = imagecreatefromstring($imageData);
        if ($image === false) {
            throw new UnprocessableEntityHttpException('Invalid image file');
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $width = imagesx($image);
        $height = imagesy($image);

        $cleanImage = imagecreatetruecolor($width, $height);
        if ($cleanImage === false) {
            imagedestroy($image);
            throw new UnprocessableEntityHttpException('Failed to process image');
        }

        imagealphablending($cleanImage, false);
        imagesavealpha($cleanImage, true);
        $transparent = imagecolorallocatealpha($cleanImage, 0, 0, 0, 127);
        imagefill($cleanImage, 0, 0, $transparent);

        imagecopyresampled($cleanImage, $image, 0, 0, 0, 0, $width, $height, $width, $height);

        $quality = Mage::getStoreConfigAsInt('system/media_storage_configuration/image_quality') ?: self::WEBP_QUALITY;
        if (!imagewebp($cleanImage, $destPath, $quality)) {
            imagedestroy($image);
            imagedestroy($cleanImage);
            throw new UnprocessableEntityHttpException('Failed to save image as WebP');
        }

        imagedestroy($image);
        imagedestroy($cleanImage);
    }

    private function getAuthorizedUser(): ApiUser
    {
        $user = $this->security->getUser();

        if (!$user instanceof ApiUser) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        return $user;
    }

    private function requirePermission(ApiUser $user, string $permission): void
    {
        if (!$user->hasPermission($permission)) {
            throw new AccessDeniedHttpException("Missing permission: {$permission}");
        }
    }

    private function sanitizeFolderPath(string $path): string
    {
        $path = str_replace(chr(0), '', $path);
        $path = preg_replace('#(^|[\\\\/])\.\.($|[\\\\/])#', '', $path);
        $path = str_replace('\\', '/', $path);
        $path = trim($path, '/');
        $path = preg_replace('#/+#', '/', $path);

        return $path;
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
