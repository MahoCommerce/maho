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
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

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
        $this->requireUser();

        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($uriVariables['path']);
        }

        return $this->handleUpload();
    }

    private function handleUpload(): Media
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new \Mage_Core_Exception_Input()->addError('file', Mage::helper('cms')->__('No valid file uploaded'));
        }

        $filename = $request?->request->get('filename');
        $result = $this->mediaService()->upload(
            (string) $request?->request->get('folder', 'wysiwyg'),
            'file',
            $filename === null ? null : (string) $filename,
        );
        $this->logActivity('upload', $result['path']);

        $media = new Media();
        $media->url = Mage::helper('cms/wysiwyg_images')->getMount()->publicUrl($result['path']);
        $media->directive = sprintf('{{media url="%s"}}', $result['path']);
        $media->size = $result['size'];
        $media->dimensions = ['width' => $result['width'], 'height' => $result['height']];
        $media->filename = $result['file'];
        $media->path = $result['path'];

        return $media;
    }

    private function handleDelete(string $path): null
    {
        $path = str_replace('\\', '/', $path);
        $service = $this->mediaService();
        $service->delete($service->getFolder(dirname($path)), basename($path));
        $this->logActivity('delete', $path);

        return null;
    }

    private function mediaService(): \Mage_Cms_Service_Media
    {
        /** @var \Mage_Cms_Service_Media */
        return Mage::getService('cms/media');
    }

    private function logActivity(string $action, string $path): void
    {
        try {
            /** @var \Maho_AdminActivityLog_Model_Activity $activity */
            $activity = Mage::getModel('adminactivitylog/activity');
            $activity->logActivity([
                'entity_type' => 'cms/media',
                'action_type' => $action,
                'entity_id' => 0,
                'old_data' => $action === 'delete' ? ['path' => $path] : null,
                'new_data' => $action === 'upload' ? ['path' => $path] : null,
            ]);
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }
}
