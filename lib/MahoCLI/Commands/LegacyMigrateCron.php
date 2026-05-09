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
    name: 'legacy:migrate-cron',
    description: 'Migrate XML <crontab><jobs> declarations in user modules to #[Maho\\Config\\CronJob] attributes',
)]
class LegacyMigrateCron extends BaseMahoCommand
{
    use LegacyMigrateTrait;

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

            $jobNodes = $this->findJobNodes($dom);
            if ($jobNodes === []) {
                continue;
            }

            $output->writeln(sprintf('<info>%s</info> (%s)', $module, $configPath));

            foreach ($jobNodes as $jobNode) {
                $jobId = $jobNode->localName;

                $runModel = $this->textGrandchild($jobNode, 'run', 'model');
                if ($runModel === '' || !str_contains($runModel, '::')) {
                    $output->writeln(sprintf(
                        '  <comment>skip</comment> %s: missing or malformed <run><model>',
                        $jobId,
                    ));
                    $totalSkipped++;
                    continue;
                }

                [$classAlias, $methodName] = explode('::', $runModel, 2);
                $classAlias = trim($classAlias);
                $methodName = trim($methodName);

                $cronExpr = $this->textGrandchild($jobNode, 'schedule', 'cron_expr');
                $configPathExpr = $this->textGrandchild($jobNode, 'schedule', 'config_path');

                $className = $this->resolveClass($classAlias);
                if ($className === null) {
                    $output->writeln(sprintf(
                        '  <comment>skip</comment> %s: unable to resolve class alias "%s"',
                        $jobId,
                        $classAlias,
                    ));
                    $totalSkipped++;
                    continue;
                }

                $classFile = $this->findClassFile($className);
                if ($classFile === null) {
                    $output->writeln(sprintf(
                        '  <comment>skip</comment> %s: cannot locate file for class %s',
                        $jobId,
                        $className,
                    ));
                    $totalSkipped++;
                    continue;
                }

                $attributeLine = $this->buildAttributeLine($jobId, $cronExpr, $configPathExpr);

                if ($dryRun) {
                    $output->writeln(sprintf('  would add %s', $attributeLine));
                    $output->writeln(sprintf('    on %s::%s in %s', $className, $methodName, $this->relativePath($classFile)));
                } else {
                    $ok = $this->insertMethodAttribute($classFile, $methodName, $attributeLine);
                    if (!$ok) {
                        $output->writeln(sprintf(
                            '  <error>fail</error> %s: method %s::%s not found',
                            $jobId,
                            $className,
                            $methodName,
                        ));
                        $totalSkipped++;
                        continue;
                    }
                    $output->writeln(sprintf(
                        '  <info>migrated</info> %s -> %s::%s',
                        $jobId,
                        $className,
                        $methodName,
                    ));
                }

                // Bubble up through <jobs> and <crontab> if they become empty.
                $this->detachAndPrune($jobNode);
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
     * @return list<DOMElement>
     */
    private function findJobNodes(\DOMDocument $dom): array
    {
        $found = [];
        $config = $dom->documentElement;
        if ($config === null) {
            return $found;
        }
        $crontab = $this->firstChildElement($config, 'crontab');
        if ($crontab === null) {
            return $found;
        }
        $jobs = $this->firstChildElement($crontab, 'jobs');
        if ($jobs === null) {
            return $found;
        }
        foreach ($jobs->childNodes as $jobNode) {
            if (!$jobNode instanceof DOMElement) {
                continue;
            }
            // Only treat as legacy if a <run> child is present
            if ($this->firstChildElement($jobNode, 'run') === null) {
                continue;
            }
            $found[] = $jobNode;
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

    private function textGrandchild(DOMElement $parent, string $childName, string $grandchildName): string
    {
        $child = $this->firstChildElement($parent, $childName);
        if ($child === null) {
            return '';
        }
        $grand = $this->firstChildElement($child, $grandchildName);
        return $grand === null ? '' : trim($grand->textContent);
    }

    private function resolveClass(string $aliasOrClass): ?string
    {
        if (str_contains($aliasOrClass, '/')) {
            $resolved = (string) Mage::getConfig()->getModelClassName($aliasOrClass);
            if ($resolved === $aliasOrClass || $resolved === '') {
                return null;
            }
            return $resolved;
        }
        return $aliasOrClass;
    }

    private function buildAttributeLine(string $jobId, string $cronExpr, string $configPath): string
    {
        $args = [var_export($jobId, true)];
        if ($cronExpr !== '') {
            $args[] = 'schedule: ' . var_export($cronExpr, true);
        } elseif ($configPath !== '') {
            $args[] = 'configPath: ' . var_export($configPath, true);
        }
        return '#[\\Maho\\Config\\CronJob(' . implode(', ', $args) . ')]';
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
