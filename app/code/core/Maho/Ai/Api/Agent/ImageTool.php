<?php

/**
 * The generate_image tool: an image from a prompt, saved into the media library.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Agent;

use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\AI\Platform\Tool\ToolEffectHints;

/**
 * The tool exists only when image generation is on in the AI configuration. It is a write,
 * since it costs money and adds a file, so the administrator confirms the prompt. The file
 * lands in the wysiwyg storage under ai/, where the editor's image browser lists it, and the
 * result gives the directive that puts it into page content.
 */
final class ImageTool
{
    public const NAME = 'generate_image';
    public const TITLE = 'Generate an image';
    private const FOLDER = 'ai';

    public function isAvailable(): bool
    {
        return \Mage::helper('ai')->isEnabled() && \Mage::getStoreConfigFlag('ai/image/enabled')
            && \Mage::getSingleton('admin/session')->isAllowed('cms/media_gallery');
    }

    /** The tool calls an image provider, which costs money, and stores the image. */
    public static function effectHints(): ToolEffectHints
    {
        return new ToolEffectHints(readOnlyHint: false, destructiveHint: false, openWorldHint: true);
    }

    public function tool(): Tool
    {
        return new Tool(
            new ExecutionReference(self::class, 'generate'),
            self::NAME,
            'Generate an image from a description and save it in the media library, for a page, a block, a banner or a product. Describe the subject, the style, the mood and the format in one paragraph. The result gives the media directive to put the image into content: <img src="{{media url=\"wysiwyg/ai/name.png\"}}" alt="...">.',
            [
                'type' => 'object',
                'properties' => [
                    'prompt' => ['type' => 'string', 'description' => 'What the image shows, in one paragraph.', 'maxLength' => 2000],
                    'file_name' => ['type' => 'string', 'description' => 'A short file name without extension, letters, digits and dashes, for example christmas-hero.', 'maxLength' => 60],
                    'width' => ['type' => 'integer', 'description' => 'Width in pixels; the provider rounds it to a size it supports.', 'minimum' => 256, 'maximum' => 2048],
                    'height' => ['type' => 'integer', 'description' => 'Height in pixels.', 'minimum' => 256, 'maximum' => 2048],
                ],
                'required' => ['prompt', 'file_name'],
                'additionalProperties' => false,
            ],
            effectHints: self::effectHints(),
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string}
     */
    public function generate(array $arguments): array
    {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'text' => 'Image generation is off in System > Configuration > AI.'];
        }
        $prompt = trim((string) ($arguments['prompt'] ?? ''));
        if ($prompt === '') {
            return ['ok' => false, 'text' => 'The prompt is empty.'];
        }
        $name = strtolower(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', (string) ($arguments['file_name'] ?? '')), '-'));
        if ($name === '') {
            $name = 'image-' . date('Ymd-His');
        }
        $options = [];
        foreach (['width', 'height'] as $dimension) {
            if (isset($arguments[$dimension]) && is_numeric($arguments[$dimension])) {
                $options[$dimension] = (int) $arguments[$dimension];
            }
        }

        try {
            $source = \Mage::helper('ai')->generateImage($prompt, $options, consumer: 'assistant');
            $bytes = str_starts_with($source, 'data:')
                ? base64_decode((string) preg_replace('/^data:[^,]*,/', '', $source), true)
                : (string) file_get_contents($source);
        } catch (\Throwable $e) {
            return ['ok' => false, 'text' => 'The image could not be generated: ' . $e->getMessage()];
        }
        if (!is_string($bytes) || $bytes === '' || @getimagesizefromstring($bytes) === false) {
            return ['ok' => false, 'text' => 'The provider returned no image.'];
        }

        try {
            $image = \Mage::getService('cms/media')->saveImage(self::FOLDER, $bytes, $name);
        } catch (\Mage_Core_Exception|\League\Flysystem\FilesystemException) {
            return ['ok' => false, 'text' => 'The image cannot be saved in the media library.'];
        }
        $mediaPath = $image['path'];

        return ['ok' => true, 'text' => sprintf(
            'Image saved as %s (%dx%d). URL: %s. Directive for page content: <img src="{{media url=\\"%s\\"}}" alt="">. It is in the media library under wysiwyg/%s.',
            $mediaPath,
            $image['width'],
            $image['height'],
            \Mage::helper('cms/wysiwyg_images')->getMount()->publicUrl($mediaPath),
            $mediaPath,
            self::FOLDER,
        )];
    }
}
