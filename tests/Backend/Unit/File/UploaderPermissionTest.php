<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class MediaUploadPermissionProbe extends Maho\File\Uploader
{
    public function __construct(string $tmpPath, string $name)
    {
        $this->_file = [
            'tmp_name' => $tmpPath,
            'name' => $name,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpPath),
            'type' => 'text/plain',
        ];
        $this->_fileExists = true;
    }

    protected function _validateFile()
    {
        return true;
    }

    protected function _moveFile($tmpPath, $destPath)
    {
        return rename($tmpPath, $destPath);
    }
}

beforeEach(function () {
    $this->directory = sys_get_temp_dir() . '/maho-upload-' . uniqid();
    mkdir($this->directory, 0755);
    $this->source = tempnam(sys_get_temp_dir(), 'maho-upload-source-');
    file_put_contents($this->source, 'uploaded');
    chmod($this->source, 0644);
});

afterEach(function () {
    if (is_file($this->directory . '/upload.txt')) {
        unlink($this->directory . '/upload.txt');
    }
    if (is_file($this->source)) {
        unlink($this->source);
    }
    if (is_dir($this->directory)) {
        rmdir($this->directory);
    }
});

it('does not grant other-write permission to a local upload', function () {
    $uploader = new MediaUploadPermissionProbe($this->source, 'upload.txt');

    $result = $uploader->save($this->directory);

    expect($result)->not->toBeFalse();
    $mode = fileperms($this->directory . '/upload.txt') & 0777;
    expect($mode & 0002)->toBe(0)
        ->and($mode & 0600)->toBe(0600);
});
