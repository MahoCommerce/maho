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

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'dev:module:list',
    description: 'List installed modules with declared and core_resource versions',
)]
class DevModuleList extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addArgument(
            'filter',
            InputArgument::OPTIONAL,
            'Case-insensitive substring to match against module names',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $filter = $input->getArgument('filter');
        $filter = is_string($filter) ? strtolower($filter) : null;

        $moduleResources = [];
        foreach (Mage::getConfig()->getNode('global/resources')->children() as $resName => $resource) {
            if (!$resource->setup || !$resource->setup->module) {
                continue;
            }
            $moduleResources[(string) $resource->setup->module][] = (string) $resName;
        }

        /** @var \Mage_Core_Model_Resource_Resource $resourceModel */
        $resourceModel = Mage::getResourceSingleton('core/resource');

        $rows = [];
        foreach (Mage::getConfig()->getNode('modules')->children() as $moduleName => $moduleNode) {
            $moduleName = (string) $moduleName;
            if ($filter !== null && !str_contains(strtolower($moduleName), $filter)) {
                continue;
            }

            $active = in_array((string) $moduleNode->active, ['true', '1'], true);
            $codePool = (string) $moduleNode->codePool ?: '—';
            $declared = (string) $moduleNode->version ?: '—';
            $resources = $moduleResources[$moduleName] ?? [];

            if ($resources === []) {
                $rows[] = [
                    $moduleName,
                    $active ? '<fg=green>active</>' : '<fg=red>inactive</>',
                    $codePool,
                    $declared,
                    '—',
                    '—',
                    '—',
                    '—',
                ];
                continue;
            }

            foreach ($resources as $resName) {
                $dbVer = $resourceModel->getDbVersion($resName);
                $dataVer = $resourceModel->getDataVersion($resName);
                $mahoVer = $resourceModel->getMahoVersion($resName);

                $rows[] = [
                    $moduleName,
                    $active ? '<fg=green>active</>' : '<fg=red>inactive</>',
                    $codePool,
                    $this->renderDeclared($declared, $dbVer),
                    $resName,
                    $dbVer === false ? '<fg=yellow>not installed</>' : (string) $dbVer,
                    $dataVer === false ? '—' : (string) $dataVer,
                    $mahoVer === false ? '—' : (string) $mahoVer,
                ];
            }
        }

        if ($rows === []) {
            $output->writeln('No modules found.');
            return Command::SUCCESS;
        }

        usort($rows, fn(array $a, array $b): int => strcmp($a[0] . $a[4], $b[0] . $b[4]));

        $table = new Table($output);
        $table->setHeaders(['module', 'status', 'codePool', 'declared', 'resource', 'db', 'data', 'maho']);
        $table->setRows($rows);
        $table->render();

        return Command::SUCCESS;
    }

    private function renderDeclared(string $declared, string|false $dbVer): string
    {
        if ($declared === '—' || $dbVer === false || $dbVer === '') {
            return $declared;
        }
        return $declared === $dbVer ? $declared : "<fg=yellow>{$declared} (pending)</>";
    }
}
