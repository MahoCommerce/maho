<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Intelligence
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_Event
{
    private const AREAS = ['global', 'frontend', 'adminhtml', 'crontab', 'install'];

    private ?array $cachedEvents = null;

    /**
     * Get all events keyed by area, then event name, with observer details.
     * Merges XML-defined observers (legacy / custom projects) with PHP-attribute
     * observers compiled into vendor/composer/maho_attributes.php.
     *
     * Observers within an event are returned in source-grouping order (XML first,
     * then attribute). This is not the runtime dispatch order, which is determined
     * by per-observer id/before/after semantics.
     *
     * Default observer `type` differs by source ('singleton' for XML, 'model' for
     * attribute) to match each system's own default.
     */
    public function getAllEvents(): array
    {
        if ($this->cachedEvents !== null) {
            return $this->cachedEvents;
        }

        $config = Mage::getConfig();
        $result = [];

        foreach (self::AREAS as $area) {
            $eventsNode = $config->getNode("{$area}/events");
            if (!$eventsNode) {
                continue;
            }

            foreach ($eventsNode->children() as $event) {
                $eventName = strtolower($event->getName());

                if (!isset($event->observers)) {
                    continue;
                }

                foreach ($event->observers->children() as $observer) {
                    $result[$area][$eventName]['event'] = $eventName;
                    $result[$area][$eventName]['area'] = $area;
                    $result[$area][$eventName]['observers'][] = [
                        'name' => $observer->getName(),
                        'class' => (string) ($observer->class ?? ''),
                        'method' => (string) ($observer->method ?? ''),
                        'type' => (string) ($observer->type ?? 'singleton'),
                        'module' => null,
                        'alias' => null,
                        'source' => 'xml',
                    ];
                }
            }
        }

        $compiledObservers = Maho::getCompiledAttributes()['observers'] ?? [];
        foreach ($compiledObservers as $area => $events) {
            foreach ($events as $eventName => $observers) {
                $eventName = strtolower($eventName);
                foreach ($observers as $observer) {
                    $result[$area][$eventName]['event'] = $eventName;
                    $result[$area][$eventName]['area'] = $area;
                    $result[$area][$eventName]['observers'][] = [
                        'name' => $observer['name'] ?? '',
                        'class' => $observer['class'] ?? '',
                        'method' => $observer['method'] ?? '',
                        'type' => $observer['type'] ?? 'model',
                        'module' => $observer['module'] ?? null,
                        'alias' => $observer['alias'] ?? null,
                        'source' => 'attribute',
                    ];
                }
            }
        }

        foreach ($result as &$areaEvents) {
            ksort($areaEvents, SORT_NATURAL | SORT_FLAG_CASE);
        }

        $this->cachedEvents = $result;
        return $result;
    }

    /**
     * Get observers for a specific event across all areas
     */
    public function getObserversForEvent(string $eventName): array
    {
        $eventName = strtolower($eventName);
        $allEvents = $this->getAllEvents();
        $result = [];

        foreach ($allEvents as $area => $events) {
            if (isset($events[$eventName])) {
                $result[$area] = $events[$eventName]['observers'];
            }
        }

        return $result;
    }
}
