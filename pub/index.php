<?php

define('MAGENTO_ROOT', dirname(__DIR__));

$maintenanceFile = 'maintenance.flag';
$maintenanceIpFile = 'maintenance.ip';

if (file_exists(MAGENTO_ROOT . DIRECTORY_SEPARATOR . 'app/bootstrap.php')) {
    require MAGENTO_ROOT . '/app/bootstrap.php';
    require MAGENTO_ROOT . '/app/Mage.php';
} else {
    require MAGENTO_ROOT . '/vendor/mahocommerce/maho/app/bootstrap.php';
    require MAGENTO_ROOT . '/vendor/mahocommerce/maho/app/Mage.php';
}

#Varien_Profiler::enable();

umask(0);

/* Store or website code */
$mageRunCode = $_SERVER['MAGE_RUN_CODE'] ?? '';

/* Run store or run website */
$mageRunType = $_SERVER['MAGE_RUN_TYPE'] ?? 'store';

if (file_exists($maintenanceFile)) {
    $maintenanceBypass = false;

    if (is_readable($maintenanceIpFile)) {
        /* Use Mage to get remote IP (in order to respect remote_addr_headers xml config) */
        Mage::init($mageRunCode, $mageRunType);
        $currentIp = Mage::helper('core/http')->getRemoteAddr();
        $allowedIps = preg_split('/[\ \n\,]+/', file_get_contents($maintenanceIpFile), 0, PREG_SPLIT_NO_EMPTY);
        $maintenanceBypass = in_array($currentIp, $allowedIps, true);
    }
    if (!$maintenanceBypass) {
        include_once Mage::findFileInIncludePath('errors/503.php');
        exit;
    }

    // remove config cache to make the system check for DB updates
    $config = Mage::app()->getConfig();
    $config->getCache()->remove($config->getCacheId());
}

Mage::run($mageRunCode, $mageRunType);
