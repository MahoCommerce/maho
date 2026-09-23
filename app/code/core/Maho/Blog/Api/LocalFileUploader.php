<?php

/**
 * Uploader for a file that is already on the server, such as an image that an API request sends as base64.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

namespace Maho\Blog\Api;

/**
 * The parent reads the file from $_FILES and moves it with move_uploaded_file(), which accepts
 * only files of an HTTP form upload. This class takes a local path and moves it with rename().
 * The file name, the extension check and the validators of the parent stay the same.
 */
final class LocalFileUploader extends \Mage_Core_Model_File_Uploader
{
    public function __construct(string $path, string $fileName)
    {
        $size = filesize($path);
        $this->_file = [
            'name' => $fileName,
            'type' => (string) mime_content_type($path),
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => $size === false ? 0 : $size,
        ];
        $this->_uploadType = self::SINGLE_STYLE;
        $this->_fileExists = true;
    }

    #[\Override]
    protected function _moveFile($tmpPath, $destPath)
    {
        return rename($tmpPath, $destPath);
    }
}
