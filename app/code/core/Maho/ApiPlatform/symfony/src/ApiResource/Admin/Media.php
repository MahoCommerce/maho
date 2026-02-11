<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\ApiResource\Admin;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use Maho\ApiPlatform\State\Admin\MediaProcessor;
use Maho\ApiPlatform\State\Admin\MediaProvider;

#[ApiResource(
    uriTemplate: '/admin/media',
    shortName: 'AdminMedia',
    operations: [
        new Post(
            processor: MediaProcessor::class,
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(
                summary: 'Upload an image',
                description: 'Uploads an image file, auto-converts to WebP. Max 10MB. Allowed types: jpg, jpeg, png, gif, webp.',
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
            openapi: new Operation(
                summary: 'List files in folder',
                description: 'Lists image files in a media folder within wysiwyg/',
            ),
        ),
    ],
    security: "is_granted('ROLE_ADMIN_API')",
)]
#[ApiResource(
    uriTemplate: '/admin/media/{path}',
    shortName: 'AdminMedia',
    operations: [
        new Delete(
            processor: MediaProcessor::class,
            openapi: new Operation(
                summary: 'Delete a media file',
                description: 'Deletes a file from the media folder. Path must be within wysiwyg/.',
            ),
            requirements: ['path' => '.+'],
        ),
    ],
    security: "is_granted('ROLE_ADMIN_API')",
)]
class Media
{
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
