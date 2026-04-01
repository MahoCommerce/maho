<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_Event
{
    private const AREAS = ['global', 'frontend', 'adminhtml', 'crontab'];

    /**
     * Get all events keyed by area, then event name, with observer details
     */
    public function getAllEvents(): array
    {
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

                $observers = [];
                foreach ($event->observers->children() as $observer) {
                    $observers[] = [
                        'name' => $observer->getName(),
                        'class' => (string) ($observer->class ?? ''),
                        'method' => (string) ($observer->method ?? ''),
                        'type' => (string) ($observer->type ?? 'singleton'),
                    ];
                }

                $result[$area][$eventName] = [
                    'event' => $eventName,
                    'area' => $area,
                    'observers' => $observers,
                ];
            }
        }

        foreach ($result as &$areaEvents) {
            ksort($areaEvents);
        }
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
