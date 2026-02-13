<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\State\Admin;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Mage;
use Mage_Cms_Model_Wysiwyg_Config;
use Mage_Core_Model_File_Uploader;
use Mage_Core_Model_Store;
use Maho\ApiPlatform\ApiResource\Admin\Media;
use Maho\ApiPlatform\Security\AdminApiAuthenticator;
use Maho\ApiPlatform\Security\AdminApiUser;
use Maho\Io\File as IoFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Media State Processor for Admin API
 *
 * Handles file uploads (POST) and deletions (DELETE) for the media gallery.
 *
 * @implements ProcessorInterface<Media, Media|null>
 */
final class MediaProcessor implements ProcessorInterface
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const WEBP_QUALITY = 85;

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Media
    {
        $request = $this->requestStack->getCurrentRequest();
        $user = $request?->attributes->get(AdminApiAuthenticator::CONSUMER_ATTRIBUTE);

        if (!$user instanceof AdminApiUser || !$user->hasPermission('admin/media/write')) {
            throw new AccessDeniedHttpException('Token does not have write permission for media');
        }

        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($uriVariables['path'], $user);
        }

        return $this->handleUpload($user);
    }

    private function handleUpload(AdminApiUser $user): Media
    {
        $request = $this->requestStack->getCurrentRequest();

        // Get uploaded file
        $uploadedFile = $request?->files->get('file');
        if (!$uploadedFile || !$uploadedFile->isValid()) {
            $error = $uploadedFile?->getErrorMessage() ?? 'No file uploaded';
            throw new BadRequestHttpException('File upload failed: ' . $error);
        }

        // Validate file size
        if ($uploadedFile->getSize() > self::MAX_FILE_SIZE) {
            throw new BadRequestHttpException(
                sprintf('File size exceeds maximum allowed size of %d MB', self::MAX_FILE_SIZE / 1024 / 1024),
            );
        }

        // Validate extension
        $originalExtension = strtolower($uploadedFile->getClientOriginalExtension());
        if (!in_array($originalExtension, self::ALLOWED_EXTENSIONS, true)) {
            throw new BadRequestHttpException(
                'Invalid file type. Allowed types: ' . implode(', ', self::ALLOWED_EXTENSIONS),
            );
        }

        // Get and validate folder
        $folder = $request->request->get('folder', 'wysiwyg');
        $folder = $this->sanitizeFolderPath($folder);

        // Get media directory
        $mediaDir = Mage::getConfig()->getOptions()->getMediaDir();
        $wysiwygDir = $mediaDir . DS . Mage_Cms_Model_Wysiwyg_Config::IMAGE_DIRECTORY;
        $targetDir = $wysiwygDir;

        if ($folder !== 'wysiwyg' && $folder !== '') {
            // Remove leading 'wysiwyg/' if present
            $subFolder = preg_replace('#^wysiwyg/?#', '', $folder);
            if ($subFolder) {
                $targetDir = $wysiwygDir . DS . $subFolder;
            }
        }

        // Validate target directory is within wysiwyg
        $realWysiwygDir = realpath($wysiwygDir);
        if (!$realWysiwygDir) {
            // Create wysiwyg directory if it doesn't exist
            $io = new IoFile();
            $io->checkAndCreateFolder($wysiwygDir);
            $realWysiwygDir = realpath($wysiwygDir);
        }

        // Create target directory if needed
        if (!is_dir($targetDir)) {
            $io = new IoFile();
            $io->checkAndCreateFolder($targetDir);
        }

        $realTargetDir = realpath($targetDir);
        if (!$realTargetDir || !str_starts_with($realTargetDir, $realWysiwygDir)) {
            throw new BadRequestHttpException('Invalid folder path. Must be within wysiwyg/');
        }

        // Determine filename
        $customFilename = $request->request->get('filename');
        if ($customFilename) {
            // Sanitize custom filename (remove extension if provided)
            $customFilename = pathinfo($customFilename, PATHINFO_FILENAME);
            $baseFilename = Mage_Core_Model_File_Uploader::getCorrectFileName($customFilename . '.webp');
            $baseFilename = pathinfo($baseFilename, PATHINFO_FILENAME);
        } else {
            // Use original filename
            $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $baseFilename = Mage_Core_Model_File_Uploader::getCorrectFileName($originalName . '.tmp');
            $baseFilename = pathinfo($baseFilename, PATHINFO_FILENAME);
        }

        // Generate unique filename if exists
        $finalFilename = $baseFilename . '.webp';
        $destPath = $realTargetDir . DS . $finalFilename;
        $counter = 1;
        while (file_exists($destPath)) {
            $finalFilename = $baseFilename . '_' . $counter . '.webp';
            $destPath = $realTargetDir . DS . $finalFilename;
            $counter++;
        }

        // Convert to WebP
        $tmpPath = $uploadedFile->getPathname();
        $this->convertToWebp($tmpPath, $destPath);

        // Get image dimensions
        $imageSize = \Maho\Io::getImageSize($destPath);
        $dimensions = null;
        if ($imageSize) {
            $dimensions = [
                'width' => $imageSize[0],
                'height' => $imageSize[1],
            ];
        }

        // Build relative path for directive
        $relativePath = str_replace($mediaDir . DS, '', $destPath);
        $relativePath = str_replace(DS, '/', $relativePath);

        // Build URL
        $mediaUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
        $fileUrl = $mediaUrl . $relativePath;

        // Build directive
        $directive = sprintf('{{media url="%s"}}', $relativePath);

        // Log activity
        $this->logActivity('upload', $relativePath, $user);

        // Return result
        $media = new Media();
        $media->url = $fileUrl;
        $media->directive = $directive;
        $media->size = filesize($destPath);
        $media->dimensions = $dimensions;
        $media->filename = $finalFilename;
        $media->path = $relativePath;

        return $media;
    }

    private function handleDelete(string $path, AdminApiUser $user): null
    {
        // Sanitize path
        $path = $this->sanitizeFolderPath($path);

        // Ensure path starts with wysiwyg/
        if (!str_starts_with($path, 'wysiwyg/')) {
            throw new BadRequestHttpException('Path must be within wysiwyg/');
        }

        // Get full path
        $mediaDir = Mage::getConfig()->getOptions()->getMediaDir();
        $fullPath = $mediaDir . DS . str_replace('/', DS, $path);

        // Validate path is within wysiwyg
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

        // Delete the file
        $io = new IoFile();
        if (!$io->rm($realFullPath)) {
            throw new UnprocessableEntityHttpException('Failed to delete file');
        }

        // Also try to delete thumbnail if exists
        $thumbsDir = $wysiwygDir . DS . '.thumbs';
        $relativePath = str_replace($realWysiwygDir, '', $realFullPath);
        $thumbPath = $thumbsDir . $relativePath;
        if (file_exists($thumbPath)) {
            $io->rm($thumbPath);
        }

        // Log activity
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

        // Preserve transparency
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Get dimensions for re-sampling (helps remove potential malicious data)
        $width = imagesx($image);
        $height = imagesy($image);

        // Create a clean copy
        $cleanImage = imagecreatetruecolor($width, $height);
        if ($cleanImage === false) {
            imagedestroy($image);
            throw new UnprocessableEntityHttpException('Failed to process image');
        }

        // Handle transparency for WebP
        imagealphablending($cleanImage, false);
        imagesavealpha($cleanImage, true);
        $transparent = imagecolorallocatealpha($cleanImage, 0, 0, 0, 127);
        imagefill($cleanImage, 0, 0, $transparent);

        // Copy and resample
        imagecopyresampled($cleanImage, $image, 0, 0, 0, 0, $width, $height, $width, $height);

        // Save as WebP
        $quality = Mage::getStoreConfigAsInt('system/media_storage_configuration/image_quality') ?: self::WEBP_QUALITY;
        if (!imagewebp($cleanImage, $destPath, $quality)) {
            imagedestroy($image);
            imagedestroy($cleanImage);
            throw new UnprocessableEntityHttpException('Failed to save image as WebP');
        }

        imagedestroy($image);
        imagedestroy($cleanImage);
    }

    private function sanitizeFolderPath(string $path): string
    {
        // Remove null bytes and directory traversal attempts
        $path = str_replace(chr(0), '', $path);
        $path = preg_replace('#(^|[\\\\/])\.\.($|[\\\\/])#', '', $path);

        // Normalize slashes
        $path = str_replace('\\', '/', $path);

        // Remove leading/trailing slashes
        $path = trim($path, '/');

        // Remove multiple consecutive slashes
        $path = preg_replace('#/+#', '/', $path);

        return $path;
    }

    private function logActivity(string $action, string $path, AdminApiUser $user): void
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
                'consumer_id' => $user->getConsumer()->getId(),
                'username' => 'API: ' . $user->getConsumerName(),
            ]);
        } catch (\Exception $e) {
            // Log but don't fail the request
            Mage::logException($e);
        }
    }
}
