<?php

/**
 * Remove the storefront layout updates of widget instances that generateLayoutUpdateXml() does not give.
 *
 * This removes the layout updates of instances whose type no enabled module declares in a widget.xml,
 * and the layout updates whose XML does not have the shape of the declared type of the instance.
 * The widget instances stay: a save of an instance makes its layout updates again.
 *
 * The script writes each removed layout update to var/log/widget_instance_cleanup.log before it deletes
 * it, with its XML, handle, sort order and store links. It also writes there the layout updates that
 * have a storefront link and belong to no widget instance, but it does not delete them. When it cannot
 * write the log, the upgrade stops before it deletes the batch, and the next run continues from there.
 *
 * The script reads the widget types of the modules that are enabled when it runs. When a module that
 * gives widgets is disabled at that time, the layout updates of its widget instances are removed.
 * To get them back, enable the module and save each of these widget instances again.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Widget
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Read the widget types of the modules that are enabled now, not a list that a cache kept from before the upgrade
Mage::app()->removeCache('widget_config');
Mage::getSingleton('widget/widget')->resetDeclaredTypes();

// Mage::log() hides a failed write, and a removal must not run before its record is in the log
$logFile = Mage::getBaseDir('log') . '/widget_instance_cleanup.log';
$log = static function (string $message) use ($logFile): void {
    $logDir = dirname($logFile);
    $line = Mage_Core_Model_Locale::nowUtc() . ' ' . $message . PHP_EOL;
    if ((!is_dir($logDir) && !@mkdir($logDir, 0o777, true) && !is_dir($logDir))
        || @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) === false
    ) {
        throw new RuntimeException(sprintf('Cannot write %s. Make it writable and run the upgrade again.', $logFile));
    }
};
// A stored type or XML can contain quotes, markup or line breaks
$encode = static fn(mixed $data): string => json_encode(
    $data,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
);

/** @var Mage_Widget_Model_Resource_Widget_Instance $resource */
$resource = Mage::getResourceSingleton('widget/widget_instance');

$resource->deleteUnexpectedLayoutUpdates(static function (array $removals) use ($log, $encode): void {
    foreach ($removals as $removal) {
        $advice = $removal['reason'] === Mage_Widget_Model_Widget_Instance::LAYOUT_UPDATE_UNDECLARED_TYPE
            ? 'If the module that gives this widget type was disabled during the upgrade, enable it and save the widget instance again. If not, delete the widget instance.'
            : 'Examine the widget instance and save it again to make its layout updates again.';
        $log(sprintf(
            'Removed layout update #%d of widget instance #%d (type %s): %s. %s Removed layout update: %s',
            $removal['layout_update_id'],
            $removal['instance_id'],
            $encode($removal['type']),
            $removal['reason'],
            $advice,
            $encode($removal),
        ));
    }
});

$resource->reportUnownedLayoutUpdates(static function (array $layoutUpdates) use ($log, $encode): void {
    foreach ($layoutUpdates as $layoutUpdate) {
        $log(sprintf(
            'Kept layout update #%d: it has a storefront link, it belongs to no widget instance, and generateLayoutUpdateXml() does not give its XML. Examine it, and delete it if no installed module owns it. Layout update: %s',
            $layoutUpdate['layout_update_id'],
            $encode($layoutUpdate),
        ));
    }
});

// Clean the caches every time: a run that stopped part way deleted rows that the cache can still hold
$cacheTypes = Mage::getConfig()->getNode(Mage_Widget_Model_Widget_Instance::XML_NODE_RELATED_CACHE);
if ($cacheTypes) {
    foreach (array_keys($cacheTypes->asArray()) as $cacheType) {
        Mage::app()->getCache()->cleanType($cacheType);
    }
}

$installer->endSetup();
