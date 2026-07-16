<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dev:api:resource:create',
    description: 'Scaffold an API Platform resource (DTO + provider) for a module, wiring it into the REST + GraphQL API',
)]
class CreateApiResource extends BaseMahoCommand
{
    use ApiResourceNaming;

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Module name (e.g., Maho_Blog)')
            ->addOption('resource', 'r', InputOption::VALUE_REQUIRED, 'Resource short name in StudlyCase (e.g., BlogPost)')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Model alias (e.g., blog/post)')
            ->addOption('route', null, InputOption::VALUE_OPTIONAL, 'REST URI base (e.g., /blog-posts); defaults to the kebab-cased plural of the resource')
            ->addOption('section', null, InputOption::VALUE_OPTIONAL, 'Admin permission section (defaults to the module name)')
            ->addOption('with-processor', null, InputOption::VALUE_NONE, 'Also generate a custom Processor stub instead of reusing the shared CrudProcessor')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the generated files to stdout instead of writing them');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $io = new SymfonyStyle($input, $output);
        $io->title('API Resource Generator');

        // ---- Module ----
        $module = $input->getOption('module')
            ?: $io->ask('Module name (e.g., Maho_Blog)', null, function ($v) {
                if (!$v || !preg_match('/^[A-Z][A-Za-z0-9]+_[A-Z][A-Za-z0-9]+$/', $v)) {
                    throw new \RuntimeException('Module name must look like Vendor_Module (e.g., Maho_Blog)');
                }
                return $v;
            });

        $moduleConfig = Mage::getConfig()->getNode('modules/' . $module);
        if (!$moduleConfig) {
            $io->error("Module '{$module}' is not declared. Check app/etc/modules/ and that it is active.");
            return Command::FAILURE;
        }

        $moduleDir = Mage::getConfig()->getModuleDir('', $module);
        if (!is_dir($moduleDir)) {
            $io->error("Module directory not found: {$moduleDir}");
            return Command::FAILURE;
        }

        // ---- Resource short name ----
        $resourceName = $input->getOption('resource')
            ?: $io->ask('Resource short name in StudlyCase (e.g., BlogPost)', null, function ($v) {
                if (!$v || !preg_match('/^[A-Z][A-Za-z0-9]+$/', $v)) {
                    throw new \RuntimeException('Resource name must be StudlyCase (e.g., BlogPost)');
                }
                return $v;
            });
        if (!preg_match('/^[A-Z][A-Za-z0-9]+$/', $resourceName)) {
            $io->error('Resource name must be StudlyCase (e.g., BlogPost)');
            return Command::INVALID;
        }

        // ---- Model alias ----
        $modelAlias = $input->getOption('model')
            ?: $io->ask('Model alias (e.g., blog/post)', null, function ($v) {
                if (!$v || !preg_match('#^[a-z0-9_]+/[a-z0-9_]+$#', $v)) {
                    throw new \RuntimeException('Model alias must look like group/model (e.g., blog/post)');
                }
                return $v;
            });

        $model = Mage::getModel($modelAlias);
        if (!is_object($model)) {
            $io->error("Model alias '{$modelAlias}' does not resolve to a model class.");
            return Command::FAILURE;
        }

        // ---- Derived names ----
        $namespace = str_replace('_', '\\', $module) . '\\Api';
        $providerClass = $resourceName . 'Provider';
        $processorClass = $resourceName . 'Processor';
        // Derive the id exactly as ApiPermissionCompiler will, so the security
        // expressions below reference the same permission id the compiler bakes
        // into vendor/composer/maho_api_permissions.php (route base == id, as in
        // the core resources). Irregular plurals should pass an explicit --route
        // and set mahoId in the generated attribute afterwards.
        $mahoId = $this->deriveApiResourceId($resourceName);
        $route = $input->getOption('route') ?: '/' . $mahoId;
        $route = '/' . ltrim((string) $route, '/');
        $section = $input->getOption('section') ?: substr($module, (int) strpos($module, '_') + 1);
        $withProcessor = (bool) $input->getOption('with-processor');

        // ---- Introspect the model to pre-fill typed properties ----
        [$properties, $note] = $this->buildProperties($model, $modelAlias);
        if ($note) {
            $io->note($note);
        }

        // ---- Render ----
        $processorRef = $withProcessor ? $processorClass : 'CrudProcessor';
        $processorImport = $withProcessor ? '' : "use Maho\\ApiPlatform\\CrudProcessor;\n";

        $tokens = [
            '{{YEAR}}' => date('Y'),
            '{{PACKAGE}}' => $module,
            '{{NAMESPACE}}' => $namespace,
            '{{SHORT_NAME}}' => $resourceName,
            '{{DESCRIPTION}}' => $resourceName . ' resource',
            '{{SECTION}}' => $section,
            '{{ROUTE}}' => $route,
            '{{ID}}' => $mahoId,
            '{{MODEL}}' => $modelAlias,
            '{{PROVIDER_CLASS}}' => $providerClass,
            '{{PROCESSOR_CLASS}}' => $processorRef,
            '{{PROCESSOR_IMPORT}}' => $processorImport,
            '{{PROPERTIES}}' => $properties,
        ];

        $files = [
            "{$resourceName}.php" => strtr($this->resourceTemplate(), $tokens),
            "{$providerClass}.php" => strtr($this->providerTemplate(), $tokens),
        ];
        if ($withProcessor) {
            $files["{$processorClass}.php"] = strtr($this->processorTemplate(), $tokens);
        }

        $apiDir = rtrim($moduleDir, '/') . '/Api';

        if ($input->getOption('dry-run')) {
            foreach ($files as $name => $content) {
                $io->section($apiDir . '/' . $name);
                $output->writeln($content);
            }
            return Command::SUCCESS;
        }

        if (!is_dir($apiDir) && !mkdir($apiDir, 0755, true) && !is_dir($apiDir)) {
            $io->error("Failed to create directory: {$apiDir}");
            return Command::FAILURE;
        }

        $force = (bool) $input->getOption('force');
        foreach ($files as $name => $content) {
            $path = $apiDir . '/' . $name;
            if (file_exists($path) && !$force) {
                $io->error("File already exists (use --force to overwrite): {$path}");
                return Command::FAILURE;
            }
            if (file_put_contents($path, $content) === false) {
                $io->error("Failed to write: {$path}");
                return Command::FAILURE;
            }
            $io->writeln("  <info>created</info> {$path}");
        }

        $io->success("API resource '{$resourceName}' scaffolded in {$module}.");
        $io->section('Next steps');
        $io->listing([
            'composer dump-autoload   (compiles the permission registry from the #[ApiResource] attribute)',
            './maho cache:flush       (refreshes API resource discovery)',
        ]);
        $io->text([
            "Then the resource is live at REST <comment>{$route}</comment> and via GraphQL,",
            "with ACL entries under the <comment>{$section}</comment> section of the admin role editor.",
            "Reads are gated behind <comment>{$mahoId}/read</comment> by default; change the read",
            "operations' security to <comment>'true'</comment> to make them public.",
        ]);

        return Command::SUCCESS;
    }

    /**
     * Introspect the model's table and return [propertiesBlock, note].
     * Falls back to a stub for EAV entities (attributes aren't table columns).
     *
     * @return array{0: string, 1: ?string}
     */
    private function buildProperties(object $model, string $modelAlias): array
    {
        $stub = "    // TODO: declare typed public properties, e.g.:\n"
              . "    // public string \$title = '';\n"
              . '    // public ?string $description = null;';

        $resource = null;
        try {
            $resource = $model->getResource();
        } catch (\Throwable) {
            // no resource model
        }
        if (!is_object($resource)) {
            return [$stub, "Could not resolve a resource model for '{$modelAlias}'; emitted a property stub."];
        }

        if ($resource instanceof \Mage_Eav_Model_Entity_Abstract) {
            return [
                $stub,
                "'{$modelAlias}' is an EAV entity; its attributes are not plain table columns. "
                . 'Emitted a stub; declare the properties you want to expose and consider a custom '
                . 'provider (see Mage/Catalog/Api/ProductProvider.php).',
            ];
        }

        try {
            $table = $resource->getMainTable();
            $pk = $resource->getIdFieldName();
            $columns = Mage::getSingleton('core/resource')
                ->getConnection('core_read')
                ->describeTable($table);
        } catch (\Throwable $e) {
            return [$stub, "Could not introspect the table for '{$modelAlias}' ({$e->getMessage()}); emitted a stub."];
        }

        $lines = [];
        foreach ($columns as $col) {
            $name = $col['COLUMN_NAME'];
            if ($name === $pk || !empty($col['PRIMARY'])) {
                continue; // covered by the identifier `id` property
            }

            $prop = $this->snakeToCamel($name);
            $phpType = $this->mapType((string) $col['DATA_TYPE']);
            $readOnly = in_array($name, ['created_at', 'updated_at'], true);

            if ($readOnly) {
                $lines[] = '    #[ApiProperty(writable: false)]';
                $lines[] = sprintf('    public ?%s $%s = null;', $phpType, $prop);
            } elseif (!empty($col['NULLABLE'])) {
                $lines[] = sprintf('    public ?%s $%s = null;', $phpType, $prop);
            } else {
                $lines[] = sprintf('    public %s $%s = %s;', $phpType, $prop, $this->typeDefault($phpType));
            }
        }

        if ($lines === []) {
            return [$stub, null];
        }

        return [implode("\n", $lines), null];
    }

    private function mapType(string $dataType): string
    {
        $t = strtolower($dataType);
        return match (true) {
            str_contains($t, 'int') => 'int',
            in_array($t, ['decimal', 'float', 'double', 'numeric', 'real'], true) => 'float',
            default => 'string',
        };
    }

    private function typeDefault(string $phpType): string
    {
        return match ($phpType) {
            'int' => '0',
            'float' => '0.0',
            default => "''",
        };
    }

    private function snakeToCamel(string $name): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
    }

    private function resourceTemplate(): string
    {
        return <<<'TPL'
<?php

/**
 * SPDX-FileCopyrightText: {{YEAR}}
 * SPDX-License-Identifier: OSL-3.0
 * @package {{PACKAGE}}
 */

declare(strict_types=1);

namespace {{NAMESPACE}};

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudResource;
{{PROCESSOR_IMPORT}}use Maho\Config\ApiResource;

#[ApiResource(
    shortName: '{{SHORT_NAME}}',
    description: '{{DESCRIPTION}}',
    mahoSection: '{{SECTION}}',
    provider: {{PROVIDER_CLASS}}::class,
    operations: [
        new Get(
            uriTemplate: '{{ROUTE}}/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('{{ID}}/read')",
            description: 'Get a {{SHORT_NAME}} by ID',
        ),
        new GetCollection(
            uriTemplate: '{{ROUTE}}',
            security: "is_granted('ROLE_ADMIN') or is_granted('{{ID}}/read')",
            description: 'Get {{SHORT_NAME}} collection',
        ),
        new Post(
            uriTemplate: '{{ROUTE}}',
            processor: {{PROCESSOR_CLASS}}::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('{{ID}}/write')",
            description: 'Create a {{SHORT_NAME}}',
        ),
        new Put(
            uriTemplate: '{{ROUTE}}/{id}',
            processor: {{PROCESSOR_CLASS}}::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('{{ID}}/write')",
            description: 'Update a {{SHORT_NAME}}',
        ),
        new Delete(
            uriTemplate: '{{ROUTE}}/{id}',
            processor: {{PROCESSOR_CLASS}}::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('{{ID}}/delete')",
            description: 'Delete a {{SHORT_NAME}}',
        ),
    ],
    graphQlOperations: [
        new Query(
            security: "is_granted('ROLE_ADMIN') or is_granted('{{ID}}/read')",
            name: 'item_query',
            description: 'Get a {{SHORT_NAME}} by ID',
        ),
        new QueryCollection(
            security: "is_granted('ROLE_ADMIN') or is_granted('{{ID}}/read')",
            name: 'collection_query',
            description: 'Get {{SHORT_NAME}} collection',
        ),
    ],
)]
class {{SHORT_NAME}} extends CrudResource
{
    public const MODEL = '{{MODEL}}';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

{{PROPERTIES}}
}

TPL;
    }

    private function providerTemplate(): string
    {
        return <<<'TPL'
<?php

/**
 * SPDX-FileCopyrightText: {{YEAR}}
 * SPDX-License-Identifier: OSL-3.0
 * @package {{PACKAGE}}
 */

declare(strict_types=1);

namespace {{NAMESPACE}};

use Maho\ApiPlatform\CrudProvider;

/**
 * {{SHORT_NAME}} API provider.
 *
 * Model loading, field mapping and DTO construction are handled by
 * CrudResource/CrudProvider. Override the hooks below to add visibility rules,
 * custom collection filters or named lookups.
 */
final class {{PROVIDER_CLASS}} extends CrudProvider
{
    // Default sort order for collections:
    // protected array $defaultSort = ['created_at' => 'DESC'];

    // Restrict which records are visible, e.g. hide disabled/store-scoped rows:
    // #[\Override]
    // protected function applyCollectionFilters(object $collection, array $filters): void
    // {
    //     parent::applyCollectionFilters($collection, $filters);
    //     $collection->addFieldToFilter('is_active', 1);
    // }
}

TPL;
    }

    private function processorTemplate(): string
    {
        return <<<'TPL'
<?php

/**
 * SPDX-FileCopyrightText: {{YEAR}}
 * SPDX-License-Identifier: OSL-3.0
 * @package {{PACKAGE}}
 */

declare(strict_types=1);

namespace {{NAMESPACE}};

use Maho\ApiPlatform\CrudProcessor;

/**
 * {{SHORT_NAME}} API write processor.
 *
 * Extends the shared CrudProcessor. Override its hooks to add validation or
 * custom persistence around create/update/delete.
 */
final class {{SHORT_NAME}}Processor extends CrudProcessor
{
    // Override process() (or the persist hooks) for custom write logic.
}

TPL;
    }
}
