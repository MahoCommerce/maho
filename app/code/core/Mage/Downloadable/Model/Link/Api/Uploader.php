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

    /**
     * Default file type
     */
    public const DEFAULT_FILE_TYPE = 'application/octet-stream';

    /**
     * @param array $file The file name, type and base64 content of the API request
     * @throws Exception
     */
    public function __construct($file)
    {
        if (!is_array($file)) {
            throw new Exception('', 'file_data_not_correct');
        }

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
