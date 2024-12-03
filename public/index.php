<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

define('MAHO_ROOT_DIR', dirname(__DIR__));
define('MAHO_PUBLIC_DIR', __DIR__);

require '../vendor/autoload.php';

#Varien_Profiler::enable();

umask(0);

/* Store or website code */
$mageRunCode = $_SERVER['MAGE_RUN_CODE'] ?? '';

/* Run store or run website */
$mageRunType = $_SERVER['MAGE_RUN_TYPE'] ?? 'store';

$maintenanceFile = BP . '/maintenance.flag';
$maintenanceIpFile = BP . '/maintenance.ip';
if (file_exists($maintenanceFile)) {
    $maintenanceBypass = false;
    if (is_readable($maintenanceIpFile) && $maintenanceIpFileContents = file_get_contents($maintenanceIpFile)) {
        /* Use Mage to get remote IP (in order to respect remote_addr_headers xml config) */
        Mage::init($mageRunCode, $mageRunType);
        $currentIp = Mage::helper('core/http')->getRemoteAddr();
        $allowedIps = preg_split('/[\ \n\,]+/', $maintenanceIpFileContents, 0, PREG_SPLIT_NO_EMPTY);
        if ($allowedIps) {
            $maintenanceBypass = in_array($currentIp, $allowedIps, true);
        }
    }
    if (!$maintenanceBypass) {
        Maho::errorReport();
        exit;
    }

    // remove config cache to make the system check for DB updates
    $config = Mage::app()->getConfig();
    $config->getCache()->remove($config->getCacheId());
}

Mage::run($mageRunCode, $mageRunType);
