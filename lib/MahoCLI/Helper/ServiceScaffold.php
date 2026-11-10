<?php

/**
 * Write a service class of a module, and declare its class group in the config.xml of the module.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

use Mage;

class ServiceScaffold
{
    public const MODULE_PATTERN = '/^[A-Z][A-Za-z0-9]*_[A-Z][A-Za-z0-9]*$/';
    public const NOUN_PATTERN = '/^[A-Z][A-Za-z0-9]*(?:_[A-Z][A-Za-z0-9]*)*$/';
    public const MODEL_PATTERN = '#^[a-z0-9_]+/[a-z0-9_]+$#';

    public readonly string $className;
    public readonly string $alias;
    public readonly string $file;
    public readonly string $configFile;
    public readonly ?string $modelClass;
    public readonly string $newMethod;
    public readonly string $notFoundMessage;
    private readonly string $classPrefix;
    private readonly string $group;
    private readonly string $helperGroup;

    /**
     * @throws \InvalidArgumentException when $module, $noun or $modelAlias has the wrong form
     * @throws \RuntimeException when the module, its config.xml or the model does not exist
     */
    public function __construct(
        public readonly string $module,
        public readonly string $noun,
        public readonly ?string $modelAlias = null,
    ) {
        if (!preg_match(self::MODULE_PATTERN, $module)) {
            throw new \InvalidArgumentException('The module name must have the form Vendor_Module (e.g., Mage_Contacts).');
        }
        if (!preg_match(self::NOUN_PATTERN, $noun)) {
            throw new \InvalidArgumentException('The noun must be StudlyCase, with _ between folders (e.g., Message or Product_Option).');
        }
        if ($modelAlias !== null && !preg_match(self::MODEL_PATTERN, $modelAlias)) {
            throw new \InvalidArgumentException('The model alias must have the form group/model (e.g., contacts/message).');
        }

        $config = Mage::getConfig();
        if (!$config->getNode('modules/' . $module)) {
            throw new \RuntimeException("The module {$module} is not declared. Look in app/etc/modules/.");
        }
        $moduleDir = rtrim($config->getModuleDir('', $module), '/');
        $this->configFile = $moduleDir . '/etc/config.xml';
        $xml = is_file($this->configFile) ? simplexml_load_file($this->configFile) : false;
        if ($xml === false) {
            throw new \RuntimeException("The file {$this->configFile} does not exist or is not valid XML.");
        }

        if ($modelAlias !== null) {
            $modelClass = $config->getModelClassName($modelAlias);
            if (!class_exists($modelClass) || !is_subclass_of($modelClass, \Mage_Core_Model_Abstract::class)) {
                throw new \RuntimeException("The model alias {$modelAlias} does not give a model class.");
            }
            $this->modelClass = $modelClass;
        } else {
            $this->modelClass = null;
        }

        $this->classPrefix = $module . '_Service';
        $short = strtolower(substr($module, strpos($module, '_') + 1));
        $this->group = self::findGroup($xml, 'services', $this->classPrefix)
            ?? self::findGroup($xml, 'models', $module . '_Model')
            ?? $short;
        $this->helperGroup = self::findGroup($xml, 'helpers', $module . '_Helper') ?? 'core';

        $parts = explode('_', $noun);
        $last = (string) end($parts);
        $this->className = $this->classPrefix . '_' . $noun;
        $this->alias = $this->group . '/' . implode('_', array_map(lcfirst(...), $parts));
        $this->file = $moduleDir . '/Service/' . implode('/', $parts) . '.php';
        $this->newMethod = 'new' . $last;
        $this->notFoundMessage = 'This ' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $last)) . ' no longer exists.';
    }

    /**
     * The holder of the generated SPDX-FileCopyrightText line: Maho for a Mage_ or a Maho_ module, the vendor for
     * any other module.
     */
    public static function copyrightHolder(string $module): string
    {
        $vendor = substr($module, 0, (int) strpos($module, '_'));
        return in_array($vendor, ['Maho', 'Mage'], true) ? 'Maho <https://mahocommerce.com>' : $vendor;
    }

    public function exists(): bool
    {
        return is_file($this->file);
    }

    public function isDeclared(): bool
    {
        $xml = simplexml_load_file($this->configFile);
        return $xml !== false && self::findGroup($xml, 'services', $this->classPrefix) !== null;
    }

    public function render(): string
    {
        $header = <<<PHP
            <?php

            /**
             * SPDX-FileCopyrightText: {$this->copyrightLine()}
             * SPDX-License-Identifier: OSL-3.0
             * @package {$this->module}
             */

            declare(strict_types=1);

            class {$this->className}
            {

            PHP;

        if ($this->modelClass === null || $this->modelAlias === null) {
            return $header . "}\n";
        }

        $model = $this->modelClass;
        $var = '$' . lcfirst((string) substr($this->newMethod, 3));
        if ($var === '$this') {
            $var = '$model';
        }
        $message = var_export($this->notFoundMessage, true);

        return $header . <<<PHP
                public function {$this->newMethod}(): {$model}
                {
                    /** @var {$model} {$var} */
                    {$var} = Mage::getModel('{$this->modelAlias}');
                    return {$var};
                }

                /**
                 * @throws Mage_Core_Exception_NoSuchEntity
                 */
                public function getById(int \$id): {$model}
                {
                    {$var} = \$this->{$this->newMethod}();
                    try {
                        {$var}->load(\$id);
                    } catch (\\Doctrine\\DBAL\\Exception\\DriverException) {
                        // An ID that the column cannot hold matches no row
                    }
                    if (!{$var}->getId()) {
                        throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('{$this->helperGroup}')->__({$message}));
                    }
                    return {$var};
                }

                public function save({$model} {$var}): {$model}
                {
                    {$var}->save();
                    return {$var};
                }

                public function delete({$model} {$var}): void
                {
                    {$var}->delete();
                }
            }

            PHP;
    }

    /**
     * Write the service class. Overwrite an existing class only with $force.
     *
     * @throws \RuntimeException when the class exists and $force is false, or when a write fails
     */
    public function write(bool $force = false): void
    {
        if ($this->exists() && !$force) {
            throw new \RuntimeException("The service class exists: {$this->file}. Use --force to write it again.");
        }
        $dir = dirname($this->file);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create the directory {$dir}.");
        }
        if (file_put_contents($this->file, $this->render()) === false) {
            throw new \RuntimeException("Cannot write {$this->file}.");
        }
    }

    /**
     * Add the class group of the service under <global><services> in the config.xml of the module.
     *
     * @return bool false when the config.xml declares the group already
     * @throws \RuntimeException when another class uses the group name, or when the file cannot be changed
     */
    public function declareGroup(): bool
    {
        $xml = simplexml_load_file($this->configFile);
        if ($xml === false) {
            throw new \RuntimeException("The file {$this->configFile} is not valid XML.");
        }
        if (self::findGroup($xml, 'services', $this->classPrefix) !== null) {
            return false;
        }
        if (isset($xml->global->services->{$this->group})) {
            throw new \RuntimeException("The service group {$this->group} in {$this->configFile} has another class. Edit the file by hand.");
        }

        $content = (string) file_get_contents($this->configFile);
        $group = "<{$this->group}>\n    <class>{$this->classPrefix}</class>\n</{$this->group}>";

        if (preg_match('#^([ \t]*)</services>#m', $content, $match, PREG_OFFSET_CAPTURE)) {
            $indent = $match[1][0];
            $content = substr_replace($content, self::indent($group, $indent . '    ') . "\n", $match[0][1], 0);
        } elseif (preg_match('#^([ \t]*)<global>[ \t]*\n#m', $content, $match, PREG_OFFSET_CAPTURE)) {
            $indent = $match[1][0];
            $services = "<services>\n" . self::indent($group, '    ') . "\n</services>";
            $content = substr_replace($content, self::indent($services, $indent . '    ') . "\n", $match[0][1] + strlen($match[0][0]), 0);
        } elseif (!isset($xml->global) && preg_match('#^</config>#m', $content, $match, PREG_OFFSET_CAPTURE)) {
            $globalBlock = "<global>\n    <services>\n" . self::indent($group, '        ') . "\n    </services>\n</global>";
            $content = substr_replace($content, self::indent($globalBlock, '    ') . "\n", $match[0][1], 0);
        } else {
            throw new \RuntimeException("Cannot find where to add <services> in {$this->configFile}. Edit the file by hand.");
        }

        $previous = libxml_use_internal_errors(true);
        $valid = simplexml_load_string($content) !== false;
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$valid || file_put_contents($this->configFile, $content) === false) {
            throw new \RuntimeException("Cannot add <services> to {$this->configFile}. Edit the file by hand.");
        }
        return true;
    }

    private function copyrightLine(): string
    {
        return date('Y') . ' ' . self::copyrightHolder($this->module);
    }

    /**
     * The name of the group under <global><$type> whose class is $class, or null.
     */
    private static function findGroup(\SimpleXMLElement $xml, string $type, string $class): ?string
    {
        foreach ($xml->xpath("/config/global/{$type}/*") ?: [] as $node) {
            if (trim((string) $node->class) === $class) {
                return $node->getName();
            }
        }
        return null;
    }

    private static function indent(string $text, string $indent): string
    {
        return $indent . str_replace("\n", "\n" . $indent, $text);
    }
}
