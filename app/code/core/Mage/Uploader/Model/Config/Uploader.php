<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Uploader
 */

declare(strict_types=1);

/**
 * Uploader Instance Config Parameters
 *
 * @package    Mage_Uploader
 *
 * @method $this setTarget(string $url)
 *      The target URL for the POST request.
 * @method $this setFileParameterName(string $fileUploadParam)
 *      The name to use for the image data in the POST request
 * @method $this setQuery(array $additionalQuery)
 *      Extra query params to include in the target URL
 * @method $this setHeaders(array $headers)
 *      Extra headers to include in the POST request
 * @method $this setSingleFile(bool $isSingleFile)
 *      Enable single file upload.
 */

class Mage_Uploader_Model_Config_Uploader extends Mage_Uploader_Model_Config_Abstract
{
    /**
     * Set default values for uploader
     */
    #[\Override]
    protected function _construct()
    {
        $this->setFileParameterName('file');
    }
}
