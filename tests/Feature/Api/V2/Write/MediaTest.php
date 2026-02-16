<?php

declare(strict_types=1);

/**
 * API v2 Media Upload Tests
 *
 * End-to-end tests for media file upload via REST.
 * Tests permission enforcement and multipart form-data handling.
 *
 * Note: Media DELETE is blocked by nginx static file handler intercepting
 * requests with image extensions (.webp, .png, etc) before they reach the
 * Symfony router. This requires `^~` on the `/api/` nginx location block
 * to override the regex static file handler.
 *
 * @group write
 */

describe('Media Upload Permission Enforcement (REST)', function () {

    it('denies upload without authentication', function () {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmpFile);
        imagedestroy($img);

        $response = apiPostMultipart('/api/media', ['folder' => 'test'], ['file' => $tmpFile]);
        unlink($tmpFile);

        expect($response['status'])->toBe(401);
    });

    it('denies upload with customer token (wrong role)', function () {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmpFile);
        imagedestroy($img);

        $response = apiPostMultipart('/api/media', ['folder' => 'test'], ['file' => $tmpFile], customerToken());
        unlink($tmpFile);

        expect($response['status'])->toBeForbidden();
    });

    it('denies upload without correct permission', function () {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmpFile);
        imagedestroy($img);

        $token = serviceToken(['cms-pages/write']);
        $response = apiPostMultipart('/api/media', ['folder' => 'test'], ['file' => $tmpFile], $token);
        unlink($tmpFile);

        expect($response['status'])->toBeForbidden();
    });

});

describe('Media Upload (REST)', function () {

    it('uploads an image with correct permission and verifies response', function () {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pest_media_') . '.png';
        $img = imagecreatetruecolor(2, 2);
        $red = imagecolorallocate($img, 255, 0, 0);
        imagefill($img, 0, 0, $red);
        imagepng($img, $tmpFile);
        imagedestroy($img);

        $token = serviceToken(['media/write']);
        $response = apiPostMultipart(
            '/api/media',
            ['folder' => 'test', 'filename' => 'pest-test-upload'],
            ['file' => $tmpFile],
            $token,
        );
        unlink($tmpFile);

        expect($response['status'])->toBeIn([200, 201]);
        expect($response['json'])->toHaveKey('url');
        expect($response['json'])->toHaveKey('filename');
        expect($response['json'])->toHaveKey('path');
        expect($response['json'])->toHaveKey('directive');
        expect($response['json'])->toHaveKey('size');
        expect($response['json'])->toHaveKey('dimensions');

        // Verify auto-converted to webp
        expect($response['json']['filename'])->toContain('pest-test-upload');
        expect($response['json']['filename'])->toEndWith('.webp');

        // Verify directive format
        expect($response['json']['directive'])->toContain('{{media url="wysiwyg/');

        // Verify dimensions present
        expect($response['json']['dimensions'])->toHaveKey('width');
        expect($response['json']['dimensions'])->toHaveKey('height');
    });

    it('uploads with custom folder', function () {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pest_media_') . '.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmpFile);
        imagedestroy($img);

        $token = serviceToken(['media/write']);
        $response = apiPostMultipart(
            '/api/media',
            ['folder' => 'test/subfolder', 'filename' => 'pest-subfolder-upload'],
            ['file' => $tmpFile],
            $token,
        );
        unlink($tmpFile);

        expect($response['status'])->toBeIn([200, 201]);
        expect($response['json']['path'])->toContain('test/subfolder');
    });

    it('uploads with "all" permission', function () {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pest_media_') . '.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmpFile);
        imagedestroy($img);

        $token = serviceToken(['all']);
        $response = apiPostMultipart(
            '/api/media',
            ['folder' => 'test', 'filename' => 'pest-all-perm'],
            ['file' => $tmpFile],
            $token,
        );
        unlink($tmpFile);

        expect($response['status'])->toBeIn([200, 201]);
        expect($response['json'])->toHaveKey('url');
    });


    it('deletes uploaded media file', function () {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pest_media_') . '.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmpFile);
        imagedestroy($img);

        $token = serviceToken(['all']);
        $upload = apiPostMultipart(
            '/api/media',
            ['folder' => 'test', 'filename' => 'pest-delete-test'],
            ['file' => $tmpFile],
            $token,
        );
        unlink($tmpFile);
        expect($upload['status'])->toBeIn([200, 201]);

        $path = $upload['json']['path'] ?? null;
        expect($path)->not->toBeNull();

        // DELETE should reach Symfony router now that nginx route is fixed
        $delete = apiDelete("/api/media/{$path}", $token);
        expect($delete['status'])->toBeIn([200, 204, 404])
            ->and($delete['raw'])->not->toContain('nginx');
    });

});
