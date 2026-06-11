<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

namespace Mage\Cms\Api;

use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;

// First-of-two repeated ApiResource attributes on Media — handles `/media` and
// `/media/{path}` separately because the post/list flow and the delete flow
// have different uriTemplates. Both share the same maho permission identity.
#[ApiResource(
    mahoId: 'media',
    uriTemplate: '/media',
    shortName: 'Media',
    operations: [
        new Post(
            processor: MediaProcessor::class,
            inputFormats: ['multipart' => ['multipart/form-data']],
            deserialize: false,
            security: "is_granted('ROLE_API_USER')",
            openapi: new Operation(
                summary: 'Upload an image',
                description: 'Uploads an image file, auto-converts to the configured image format.',
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => ['type' => 'string', 'format' => 'binary', 'description' => 'Image file to upload'],
                                    'folder' => ['type' => 'string', 'default' => 'wysiwyg', 'description' => 'Target folder within wysiwyg/'],
                                    'filename' => ['type' => 'string', 'description' => 'Optional custom filename (without extension)'],
                                ],
                                'required' => ['file'],
                            ],
                        ],
                    ]),
                ),
            ),
        ),
        new GetCollection(
            provider: MediaProvider::class,
            security: "is_granted('ROLE_API_USER')",
            openapi: new Operation(
                summary: 'List files in folder',
                description: 'Lists image files in a media folder within wysiwyg/',
            ),
        ),
    ],
)]
#[ApiResource(
    mahoId: 'media',
    mahoSection: 'Content',
    mahoOperations: ['read' => 'List', 'write' => 'Upload', 'delete' => 'Delete'],
    uriTemplate: '/media/{path}',
    shortName: 'Media',
    operations: [
        new Delete(
            processor: MediaProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            openapi: new Operation(
                summary: 'Delete a media file',
                description: 'Deletes a file from the media folder. Path must be within wysiwyg/.',
            ),
            requirements: ['path' => '.+'],
        ),
    ],
)]
class Media extends \Maho\ApiPlatform\Resource
{
    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_Cms_Wysiwyg_ImagesController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Cms_Wysiwyg_ImagesController::ADMIN_RESOURCE;

    /** @var string|null URL to access the uploaded file */
    public ?string $url = null;

    /** @var string|null Media directive for use in CMS content, e.g. {{media url="wysiwyg/image.webp"}} */
    public ?string $directive = null;

    /** @var int|null File size in bytes */
    public ?int $size = null;

    /** @var array{width: int, height: int}|null Image dimensions */
    public ?array $dimensions = null;

    /** @var string|null Final filename after sanitization */
    public ?string $filename = null;

    /** @var string|null Relative path within media directory */
    public ?string $path = null;
}
