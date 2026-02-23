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

namespace Maho\Media\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage;
use Mage_Cms_Model_Wysiwyg_Config;
use Mage_Core_Model_Store;
use Maho\Media\Api\Resource\Media;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Media State Provider
 *
 * Lists files in a media folder.
 * Requires JWT authentication with media/read permission.
 *
 * @implements ProviderInterface<Media>
 */
final class MediaProvider implements ProviderInterface
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'];

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {}

    /**
     * @return array<Media>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof ApiUser || !$user->hasPermission('media/read')) {
            throw new AccessDeniedHttpException('Missing permission: media/read');
        }

        return $this->listFiles();
    }

    /**
     * @return array<Media>
     */
    private function listFiles(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        $folder = $request?->query->get('folder', 'wysiwyg') ?? 'wysiwyg';
        $folder = $this->sanitizeFolderPath($folder);

        $mediaDir = Mage::getConfig()->getOptions()->getMediaDir();
        $wysiwygDir = $mediaDir . DS . Mage_Cms_Model_Wysiwyg_Config::IMAGE_DIRECTORY;
        $targetDir = $wysiwygDir;

        if ($folder !== 'wysiwyg' && $folder !== '') {
            $subFolder = preg_replace('#^wysiwyg/?#', '', $folder);
            if ($subFolder) {
                $targetDir = $wysiwygDir . DS . str_replace('/', DS, $subFolder);
            }
        }

        $realWysiwygDir = realpath($wysiwygDir);
        if (!$realWysiwygDir) {
            return [];
        }

        if (!is_dir($targetDir)) {
            throw new BadRequestHttpException('Folder does not exist');
        }

        $realTargetDir = realpath($targetDir);
        if (!$realTargetDir || !str_starts_with($realTargetDir, $realWysiwygDir)) {
            throw new BadRequestHttpException('Invalid folder path. Must be within wysiwyg/');
        }

        $mediaUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);

        $files = [];
        $entries = scandir($realTargetDir);

        if ($entries === false) {
            return [];
        }

        foreach ($entries as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            $fullPath = $realTargetDir . DS . $filename;

            if (!is_file($fullPath)) {
                continue;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            $relativePath = str_replace($mediaDir . DS, '', $fullPath);
            $relativePath = str_replace(DS, '/', $relativePath);

            $fileUrl = $mediaUrl . $relativePath;
            $directive = sprintf('{{media url="%s"}}', $relativePath);

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

        usort($files, fn(Media $a, Media $b) => strcasecmp($a->filename ?? '', $b->filename ?? ''));

        return $files;
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
}
