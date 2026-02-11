<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage;
use Mage_Cms_Model_Wysiwyg_Config;
use Mage_Core_Model_Store;
use Mage_Oauth_Model_Consumer;
use Maho\ApiPlatform\ApiResource\Admin\Media;
use Maho\ApiPlatform\Security\AdminApiAuthenticator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Media State Provider for Admin API
 *
 * Lists files in a media folder.
 *
 * @implements ProviderInterface<Media>
 */
final class MediaProvider implements ProviderInterface
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'];

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    /**
     * @return array<Media>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $consumer = $request?->attributes->get(AdminApiAuthenticator::CONSUMER_ATTRIBUTE);

        if (!$consumer || !$consumer->hasPermission('media')) {
            throw new AccessDeniedHttpException('Token does not have permission for media');
        }

        return $this->listFiles();
    }

    /**
     * @return array<Media>
     */
    private function listFiles(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        // Get and validate folder
        $folder = $request?->query->get('folder', 'wysiwyg') ?? 'wysiwyg';
        $folder = $this->sanitizeFolderPath($folder);

        // Get media directory
        $mediaDir = Mage::getConfig()->getOptions()->getMediaDir();
        $wysiwygDir = $mediaDir . DS . Mage_Cms_Model_Wysiwyg_Config::IMAGE_DIRECTORY;
        $targetDir = $wysiwygDir;

        if ($folder !== 'wysiwyg' && $folder !== '') {
            // Remove leading 'wysiwyg/' if present
            $subFolder = preg_replace('#^wysiwyg/?#', '', $folder);
            if ($subFolder) {
                $targetDir = $wysiwygDir . DS . str_replace('/', DS, $subFolder);
            }
        }

        // Validate target directory is within wysiwyg
        $realWysiwygDir = realpath($wysiwygDir);
        if (!$realWysiwygDir) {
            // Wysiwyg directory doesn't exist yet, return empty
            return [];
        }

        if (!is_dir($targetDir)) {
            throw new BadRequestHttpException('Folder does not exist');
        }

        $realTargetDir = realpath($targetDir);
        if (!$realTargetDir || !str_starts_with($realTargetDir, $realWysiwygDir)) {
            throw new BadRequestHttpException('Invalid folder path. Must be within wysiwyg/');
        }

        // Build media URL base
        $mediaUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);

        // List files using scandir
        $files = [];
        $entries = scandir($realTargetDir);

        if ($entries === false) {
            return [];
        }

        foreach ($entries as $filename) {
            // Skip . and ..
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            $fullPath = $realTargetDir . DS . $filename;

            // Skip directories
            if (!is_file($fullPath)) {
                continue;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // Only include allowed image types
            if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            // Build relative path
            $relativePath = str_replace($mediaDir . DS, '', $fullPath);
            $relativePath = str_replace(DS, '/', $relativePath);

            // Build URL
            $fileUrl = $mediaUrl . $relativePath;

            // Build directive
            $directive = sprintf('{{media url="%s"}}', $relativePath);

            // Get dimensions
            $imageSize = \Maho\Io::getImageSize($fullPath);
            $dimensions = null;
            if ($imageSize) {
                $dimensions = [
                    'width' => $imageSize[0],
                    'height' => $imageSize[1],
                ];
            }

            $media = new Media();
            $media->url = $fileUrl;
            $media->directive = $directive;
            $media->size = (int) filesize($fullPath);
            $media->dimensions = $dimensions;
            $media->filename = $filename;
            $media->path = $relativePath;

            $files[] = $media;
        }

        // Sort by filename
        usort($files, fn(Media $a, Media $b) => strcasecmp($a->filename ?? '', $b->filename ?? ''));

        return $files;
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
}
