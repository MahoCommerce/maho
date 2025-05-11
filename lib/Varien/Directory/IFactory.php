<?php

/**
 * Maho
 *
 * @package    Varien_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

interface Varien_Directory_IFactory
{
    public function getFilesName(&$files);
    public function getFilesPaths(&$paths);
    public function getFilesObj(&$objs);
    public function useFilter($useFilter);
    public function getDirsName(&$dirs);
    public function toArray(&$arr);
    public function toXml(&$xml, $recursionLevel = 0, $addOpenTag = true, $rootName = 'Struct');
}
