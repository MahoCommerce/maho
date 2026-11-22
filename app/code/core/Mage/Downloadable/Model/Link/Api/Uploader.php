<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

class Mage_Downloadable_Model_Link_Api_Uploader extends \Maho\ApiPlatform\Service\LocalFileUploader
{
    /**
     * Filename prefix
     *
     * @var string
     */
    protected $_filePrefix = 'Api';

    public const string DEFAULT_FILE_TYPE = 'application/octet-stream';

    /**
     * @param array{name: string, base64_content: string, type?: string} $file The file of the API request
     */
    public function __construct(array $file)
    {
        $tmpFileName = tempnam(sys_get_temp_dir(), $this->_filePrefix);
        $io = new \Maho\Io\File();
        $io->open(['path' => sys_get_temp_dir()]);
        $io->streamOpen($tmpFileName);
        $io->streamWrite(base64_decode($file['base64_content']));
        $io->streamClose();

        parent::__construct($tmpFileName, $file['name']);
        $this->_file['type'] = $file['type'] ?? self::DEFAULT_FILE_TYPE;
    }
}
