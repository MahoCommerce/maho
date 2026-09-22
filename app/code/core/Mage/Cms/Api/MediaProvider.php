<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

namespace Mage\Cms\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage;
use Mage_Cms_Model_Wysiwyg_Config;
use Mage_Core_Model_Store;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Maho\Io\File as IoFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Media State Provider.
 *
 * Lists files in a media folder.
 * Requires JWT authentication with media/read permission.
 *
 * @implements ProviderInterface<Media>
 */
final class MediaProvider implements ProviderInterface
{
    use AuthenticationTrait;

    public function __construct(
        Security $security,
        private readonly RequestStack $requestStack,
    ) {
        $this->security = $security;
    }

    /**
     * @return array<Media>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->listFiles();
    }

    /**
     * @return array<Media>
     */
    private function listFiles(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        $folder = $request?->query->get('folder', 'wysiwyg') ?? 'wysiwyg';
        $helper = Mage::helper('cms/wysiwyg_images');
        $mount = $helper->getMount();
        $root = $helper->getStorageRootPath();

        $segments = array_filter(
            explode('/', str_replace('\\', '/', $helper->correctPath($folder))),
            static fn(string $segment): bool => $segment !== '..' && $segment !== '.' && $segment !== '',
        );
        $folder = implode('/', $segments);
        $targetDir = $root;
        if ($folder !== $root && $folder !== '') {
            $subFolder = preg_replace('#^' . preg_quote($root, '#') . '/?#', '', $folder);
            if ($subFolder) {
                $targetDir = \Maho\Io::getPathWithinMount($mount, $root, $subFolder)
                    ?? throw new BadRequestHttpException('Invalid folder path. Must be within wysiwyg/');
            }
        }

        if (!$mount->directoryExists($targetDir)) {
            if ($targetDir === $root) {
                return [];
            }
            throw new BadRequestHttpException('Folder does not exist');
        }

        $localRoot = $mount->localRoot();
        $files = [];
        foreach ($mount->listContents($targetDir, false) as $entry) {
            if (!$entry instanceof \League\Flysystem\FileAttributes) {
                continue;
            }
            $filename = basename($entry->path());
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($extension, IoFile::ALLOWED_IMAGES_EXTENSIONS, true)) {
                continue;
            }

            $dimensions = null;
            if ($localRoot !== null) {
                $imageSize = \Maho\Io::getImageSize($localRoot . '/' . $entry->path());
                if ($imageSize) {
                    $dimensions = ['width' => $imageSize[0], 'height' => $imageSize[1]];
                }
            }

            $media = new Media();
            $media->url = $mount->publicUrl($entry->path());
            $media->directive = sprintf('{{media url="%s"}}', $entry->path());
            $media->size = $entry->fileSize();
            $media->dimensions = $dimensions;
            $media->filename = $filename;
            $media->path = $entry->path();

            $files[] = $media;
        }

        usort($files, fn(Media $a, Media $b) => strcasecmp($a->filename ?? '', $b->filename ?? ''));

        return $files;
    }

}
