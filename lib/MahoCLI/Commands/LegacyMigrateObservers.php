<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use DOMElement;
use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'legacy:migrate-observers',
    description: 'Migrate XML <events> observer declarations in user modules to #[Maho\\Config\\Observer] attributes',
)]
class LegacyMigrateObservers extends BaseMahoCommand
{
    use LegacyMigrateTrait;

    private const SCOPE_TO_AREA = [
        'global' => null,
        'frontend' => 'frontend',
        'adminhtml' => 'adminhtml',
        'crontab' => 'crontab',
        'install' => 'install',
    ];

    #[\Override]
    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Preview the changes without writing any files',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        // Force a fresh config load so newly-added module XML is visible to alias resolution
        Mage::app()->getConfig()->reinit();

        $dryRun = (bool) $input->getOption('dry-run');
        if ($dryRun) {
            $output->writeln('<comment>Dry run: no files will be modified.</comment>');
            $output->writeln('');
        }

        $totalMigrated = 0;
        $totalSkipped = 0;

        foreach ($this->findUserConfigXmlFiles() as $entry) {
            $module = $entry['module'];
            $configPath = $entry['path'];

            $dom = $this->loadConfigXmlAsDom($configPath);
            if ($dom === null) {
                $output->writeln(sprintf('<error>Could not parse %s</error>', $configPath));
                continue;
            }

            $observerNodes = $this->findObserverNodes($dom);
            if ($observerNodes === []) {
                continue;
            }

            $output->writeln(sprintf('<info>%s</info> (%s)', $module, $configPath));

            foreach ($observerNodes as $observerEntry) {
                $area = $observerEntry['area'];
                $event = $observerEntry['event'];
                $observerNode = $observerEntry['node'];
                $observerId = $observerNode->localName;

                $classAlias = $this->textChild($observerNode, 'class');
                $methodName = $this->textChild($observerNode, 'method');
                $type = $this->textChild($observerNode, 'type');

                if ($classAlias === '' || $methodName === '') {
                    $output->writeln(sprintf(
                        '  <comment>skip</comment> %s/%s: missing <class> or <method>',
                        $event,
                        $observerId,
                    ));
                    $totalSkipped++;
                    continue;
                }

                $className = $this->resolveClass($classAlias);
                if ($className === null) {
                    $output->writeln(sprintf(
                        '  <comment>skip</comment> %s/%s: unable to resolve class alias "%s"',
                        $event,
                        $observerId,
                        $classAlias,
                    ));
                    $totalSkipped++;
                    continue;
                }

                $classFile = $this->findClassFile($className);
                if ($classFile === null) {
                    $output->writeln(sprintf(
                        '  <comment>skip</comment> %s/%s: cannot locate file for class %s',
                        $event,
                        $observerId,
                        $className,
                    ));
                    $totalSkipped++;
                    continue;
                }

                $attributeLine = $this->buildAttributeLine($event, $area, $type, $observerId);

                if ($dryRun) {
                    $output->writeln(sprintf('  would add %s', $attributeLine));
                    $output->writeln(sprintf('    on %s::%s in %s', $className, $methodName, $this->relativePath($classFile)));
                } else {
                    $ok = $this->insertMethodAttribute($classFile, $methodName, $attributeLine);
                    if (!$ok) {
                        $output->writeln(sprintf(
                            '  <error>fail</error> %s/%s: method %s::%s not found',
                            $event,
                            $observerId,
                            $className,
                            $methodName,
                        ));
                        $totalSkipped++;
                        continue;
                    }
                    $output->writeln(sprintf(
                        '  <info>migrated</info> %s/%s -> %s::%s',
                        $event,
                        $observerId,
                        $className,
                        $methodName,
                    ));
                }

                // Detach the <observer> node and bubble up through any now-empty
                // wrappers (<observers>, the event name tag, <events>, the area
                // scope). Stops naturally when an ancestor still has children.
                $this->detachAndPrune($observerNode);
                $totalMigrated++;
            }

            if (!$dryRun) {
                $this->saveConfigXml($dom, $configPath);
            }

            $output->writeln('');
        }

        $output->writeln(sprintf(
            '<info>Done.</info> Migrated: %d, skipped: %d.',
            $totalMigrated,
            $totalSkipped,
        ));

        if ($dryRun && $totalMigrated > 0) {
            $output->writeln('Re-run without --dry-run to apply.');
        } elseif (!$dryRun && $totalMigrated > 0) {
            $output->writeln('Run <info>composer dump-autoload</info> to compile the new attributes.');
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<array{area: ?string, event: string, node: DOMElement}>
     */
    private function findObserverNodes(\DOMDocument $dom): array
    {
        $found = [];
        $config = $dom->documentElement;
        if ($config === null) {
            return $found;
        }

        foreach (self::SCOPE_TO_AREA as $scope => $area) {
            $scopeNode = $this->firstChildElement($config, $scope);
            if ($scopeNode === null) {
                continue;
            }
            $eventsNode = $this->firstChildElement($scopeNode, 'events');
            if ($eventsNode === null) {
                continue;
            }
            foreach ($eventsNode->childNodes as $eventNode) {
                if (!$eventNode instanceof DOMElement) {
                    continue;
                }
                $eventName = $eventNode->localName;
                $observersNode = $this->firstChildElement($eventNode, 'observers');
                if ($observersNode === null) {
                    continue;
                }
                foreach ($observersNode->childNodes as $observerNode) {
                    if (!$observerNode instanceof DOMElement) {
                        continue;
                    }
                    $found[] = [
                        'area' => $area,
                        'event' => $eventName,
                        'node' => $observerNode,
                    ];
                }
            }
        }
        return $found;
    }

    private function firstChildElement(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }
        return null;
    }

    private function textChild(DOMElement $parent, string $localName): string
    {
        $child = $this->firstChildElement($parent, $localName);
        return $child === null ? '' : trim($child->textContent);
    }

    private function resolveClass(string $aliasOrClass): ?string
    {
        // Class alias: looks like 'group/key'
        if (str_contains($aliasOrClass, '/')) {
            $resolved = (string) Mage::getConfig()->getModelClassName($aliasOrClass);
            // getModelClassName returns the input unchanged when it can't resolve
            if ($resolved === $aliasOrClass || $resolved === '') {
                return null;
            }
            return $resolved;
        }
        // Already a class name
        return $aliasOrClass;
    }

    private function buildAttributeLine(
        string $event,
        ?string $area,
        string $type,
        string $observerId,
    ): string {
        $args = [var_export($event, true)];
        if ($area !== null) {
            $args[] = 'area: ' . var_export($area, true);
        }
        if ($type === 'singleton') {
            $args[] = "type: 'singleton'";
        }
        // Preserve the XML <observer_name> as an explicit id so that any
        // <replaces> references in third-party modules continue to resolve.
        // The compiler's auto-id is "alias::method" which never matches the
        // legacy XML name, so we always set it explicitly here.
        $args[] = 'id: ' . var_export($observerId, true);
        return '#[\\Maho\\Config\\Observer(' . implode(', ', $args) . ')]';
    }

    private function relativePath(string $absolute): string
    {
        $root = MAHO_ROOT_DIR . '/';
        if (str_starts_with($absolute, $root)) {
            return substr($absolute, strlen($root));
        }
        return $absolute;
    }
}
