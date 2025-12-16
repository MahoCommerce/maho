<?php

require 'vendor/autoload.php';
Mage::app();

$productResource = Mage::getResourceModel('catalog/product_collection');
$productIds = $productResource->getAllIds();

$total = count($productIds);
$maxIterations = $total;
/**
 * Format bytes in a readable string (e.g. 12.45 MB).
 */
$formatBytes = static function ($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max((int)$bytes, 0);
    $precision = 2;
    $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
};

for ($i=0; $i<50; $i++) {
    foreach ($productIds as $index => $productId) {
        $product = Mage::getModel('catalog/product')->load($productId);

        $currentRealUsage = memory_get_usage();
        echo sprintf(
            "[%d/%d] Product ID %s | real: %s\n",
            $index + 1,
            $total,
            $productId,
            $formatBytes($currentRealUsage)
        );

        $product->clearInstance();
    }
}
