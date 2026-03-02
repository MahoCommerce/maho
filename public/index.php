<?php

/**
 * Maho
 *
 * @package    Mage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

define('MAHO_ROOT_DIR', dirname(__DIR__));
define('MAHO_PUBLIC_DIR', __DIR__);

// Early exit for missing static assets — avoids full bootstrap just to return a 404.
// Web servers should handle this natively, but this is a safety net for environments
// where that's not configured (php -S dev server, FrankenPHP defaults, shared hosting).
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
if ($requestPath !== '/') {
    $ext = strtolower(pathinfo($requestPath, PATHINFO_EXTENSION));
    $staticExts = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'svgz', 'ico', 'bmp', 'apng',
        'css', 'js', 'mjs', 'map', 'woff', 'woff2', 'ttf', 'otf', 'eot',
        'mp3', 'mp4', 'ogg', 'webm', 'wav', 'flac', 'aac', 'm4a', 'm4v', 'ogv', 'mov',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'zip', 'gz', 'tar', 'rar', '7z',
    ];
    if (in_array($ext, $staticExts, true)) {
        http_response_code(404);
        exit;
    }
}

require MAHO_ROOT_DIR . '/vendor/autoload.php';

#\Maho\Profiler::enable();

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
        Maho::maintenancePage();
    }

    // remove config cache to make the system check for DB updates
    $config = Mage::app()->getConfig();
    $config->getCache()->remove($config->getCacheId());
}

Mage::run($mageRunCode, $mageRunType);
