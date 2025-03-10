<?php

/**
 * Maho
 *
 * @package    Varien_Image
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Varien_Image_Adapter
{
    public const ADAPTER_GD    = 'GD';
    public const ADAPTER_GD2   = 'GD2';
    public const ADAPTER_IM    = 'IMAGEMAGIC';

    public static function factory($adapter)
    {
        return match ($adapter) {
            self::ADAPTER_GD => new Varien_Image_Adapter_Gd(),
            self::ADAPTER_GD2 => new Varien_Image_Adapter_Gd2(),
            self::ADAPTER_IM => new Varien_Image_Adapter_Imagemagic(),
            default => throw new Exception('Invalid adapter selected.'),
        };
    }
}
