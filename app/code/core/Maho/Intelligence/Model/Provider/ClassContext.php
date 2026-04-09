<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_ClassContext
{
    /**
     * Get full context for a class alias: resolution, rewrites, events,
     * class hierarchy, and module info.
     */
    public function getContext(string $type, string $alias): array
    {
        $classAliasProvider = Mage::getModel('intelligence/provider_classAlias');
        $resolved = $classAliasProvider->resolveAlias($type, $alias);

        $result = [
            'alias' => $alias,
            'type' => $type,
            'class' => $resolved['class'],
            'file' => $resolved['file'],
            'rewritten_by' => $resolved['rewritten_by'] ?? null,
        ];

        if (isset($resolved['error'])) {
            $result['error'] = $resolved['error'];
            return $result;
        }

        $className = $resolved['class'];

        if ($className && class_exists($className)) {
            $result['hierarchy'] = $this->getClassHierarchy($className);
            $result['module'] = $this->detectModule($className);
        }

        $result['events_observed'] = $this->findObservedEvents($alias, $className);
        $result['all_rewrites_for_group'] = $this->findGroupRewrites($type, $alias);

        return $result;
    }

    private function getClassHierarchy(string $className): array
    {
        $hierarchy = [
            'parents' => [],
            'interfaces' => [],
        ];

        $parents = [];
        $current = $className;
        while ($parent = get_parent_class($current)) {
            $parents[] = $parent;
            $current = $parent;
        }
        $hierarchy['parents'] = $parents;

        $allInterfaces = class_implements($className) ?: [];
        $hierarchy['interfaces'] = array_values($allInterfaces);

        return $hierarchy;
    }

    private function detectModule(string $className): ?string
    {
        if (preg_match('/^(Mage_[A-Za-z]+|Maho_[A-Za-z]+)_/', $className, $m)) {
            return $m[1];
        }
        return null;
    }

    private function findObservedEvents(string $alias, ?string $className): array
    {
        if ($className === null) {
            return [];
        }

        $eventProvider = Mage::getModel('intelligence/provider_event');
        $allEvents = $eventProvider->getAllEvents();
        $observed = [];

        foreach ($allEvents as $area => $events) {
            foreach ($events as $eventName => $event) {
                foreach ($event['observers'] as $observer) {
                    $observerClass = $observer['class'];
                    if ($observerClass === $alias || $observerClass === $className) {
                        $observed[] = [
                            'event' => $eventName,
                            'area' => $area,
                            'method' => $observer['method'],
                            'observer_name' => $observer['name'],
                        ];
                    }
                }
            }
        }

        return $observed;
    }

    private function findGroupRewrites(string $type, string $alias): array
    {
        $group = explode('/', $alias)[0];
        $config = Mage::getConfig();
        $typePlural = $type === 'resource_model' ? 'models' : "{$type}s";

        $groupNode = $config->getNode("global/{$typePlural}/{$group}");
        if (!$groupNode || !isset($groupNode->rewrite)) {
            return [];
        }

        $rewrites = [];
        foreach ($groupNode->rewrite->children() as $class => $rewriteClass) {
            $rewrites["{$group}/{$class}"] = (string) $rewriteClass;
        }

        return $rewrites;
    }
}
