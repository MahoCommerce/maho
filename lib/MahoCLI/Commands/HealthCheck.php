<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
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
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'health-check',
    description: 'Health check your Maho project',
)]
class HealthCheck extends BaseMahoCommand
{
    public const LEGACY_CORE_FILES = [
        'app/bootstrap.php',
        'app/Mage.php',
        'app/code/core',
    ];

    public const DEPRECATED_FOLDERS = [
        'app/code/core/Zend',
        'lib/Cm',
        'lib/Credis',
        'lib/mcryptcompat',
        'lib/Pelago',
        'lib/phpseclib',
        'lib/Zend',
        'skin',
    ];

    public const LEGACY_XML_SCAN_DIRS = [
        'app/code/local',
        'app/code/community',
    ];

    private const DESIGN_PATH = 'app/design/frontend';
    private const SKIN_PATH = 'public/skin/frontend';

    private const UNDECRYPTABLE_ADVICE = 'They read as empty at runtime. This usually means the database was '
        . 'encrypted under a different key (a Magento/OpenMage import, or a copy from another install): restore '
        . 'the key those values were encrypted with in app/etc/local.xml, then run '
        . '"./maho sys:encryptionkey:regenerate". Otherwise, if they were stored unencrypted, re-enter them.';

    private const LEGACY_ENCRYPTOR_ADVICE = 'This store runs on the legacy mcrypt encryptor '
        . '(mahocommerce/module-mcrypt-compat), so its key is an mcrypt one and libsodium key rules do not apply. '
        . 'Encryption works, but that module is a migration aid: run "./maho sys:encryptionkey:regenerate" to '
        . 'move to a libsodium key, then remove it.';

    /** Blowfish, the cipher module-mcrypt-compat defaults to, takes no longer key. */
    private const MCRYPT_MAX_KEY_LENGTH = 56;

    /**
     * Mapping of deprecated Varien_ classes to their Maho\ replacements
     */
    private const VARIEN_TO_MAHO_MAP = [
        'Varien_Convert' => \Maho\Convert::class,
        'Varien_Convert_Action_Abstract' => \Maho\Convert\Action\AbstractAction::class,
        'Varien_Convert_Action_Interface' => \Maho\Convert\Action\ActionInterface::class,
        'Varien_Convert_Container_Abstract' => \Maho\Convert\Container\AbstractContainer::class,
        'Varien_Convert_Container_Interface' => \Maho\Convert\Container\ContainerInterface::class,
        'Varien_Convert_Mapper_Abstract' => \Maho\Convert\Mapper\AbstractMapper::class,
        'Varien_Convert_Mapper_Interface' => \Maho\Convert\Mapper\MapperInterface::class,
        'Varien_Convert_Parser_Abstract' => \Maho\Convert\Parser\AbstractParser::class,
        'Varien_Convert_Parser_Interface' => \Maho\Convert\Parser\ParserInterface::class,
        'Varien_Convert_Profile_Abstract' => \Maho\Convert\Profile\AbstractProfile::class,
        'Varien_Data_Collection' => \Maho\Data\Collection::class,
        'Varien_Data_Collection_Db' => \Maho\Data\Collection\Db::class,
        'Varien_Data_Collection_Filesystem' => \Maho\Data\Collection\Filesystem::class,
        'Varien_Data_Form' => \Maho\Data\Form::class,
        'Varien_Data_Form_Abstract' => \Maho\Data\Form\AbstractForm::class,
        'Varien_Data_Form_Element_Abstract' => \Maho\Data\Form\Element\AbstractElement::class,
        'Varien_Data_Form_Element_Renderer_Interface' => \Maho\Data\Form\Element\Renderer\RendererInterface::class,
        'Varien_Data_Form_Filter_Interface' => \Maho\Data\Form\Filter\FilterInterface::class,
        'Varien_Data_Tree' => \Maho\Data\Tree::class,
        'Varien_Data_Tree_Node' => \Maho\Data\Tree\Node::class,
        'Varien_Data_Tree_Node_Collection' => \Maho\Data\Tree\Node\Collection::class,
        'Varien_Db_Adapter_Interface' => \Maho\Db\Adapter\AdapterInterface::class,
        'Varien_Db_Adapter_Pdo_Mysql' => \Maho\Db\Adapter\Pdo\Mysql::class,
        'Varien_Db_Ddl_Table' => \Maho\Db\Ddl\Table::class,
        'Varien_Db_Expr' => \Maho\Db\Expr::class,
        'Varien_Db_Select' => \Maho\Db\Select::class,
        'Varien_Db_Helper' => \Maho\Db\Helper::class,
        'Varien_Event' => \Maho\Event::class,
        'Varien_Event_Collection' => \Maho\Event\Collection::class,
        'Varien_Event_Observer' => \Maho\Event\Observer::class,
        'Varien_Event_Observer_Collection' => \Maho\Event\Observer\Collection::class,
        'Varien_File_Csv' => \Maho\File\Csv::class,
        'Varien_File_Uploader' => \Maho\File\Uploader::class,
        'Varien_Filter_Array' => \Maho\Filter\ArrayFilter::class,
        'Varien_Filter_Object' => \Maho\Filter\ObjectFilter::class,
        'Varien_Filter_Template' => \Maho\Filter\Template::class,
        'Varien_Filter_Template_Tokenizer_Abstract' => \Maho\Filter\Template\Tokenizer\AbstractTokenizer::class,
        'Varien_Io_Abstract' => \Maho\Io::class,
        'Varien_Io_File' => \Maho\Io\File::class,
        'Varien_Io_Ftp' => \Maho\Io\Ftp::class,
        'Varien_Io_Interface' => \Maho\Io\IoInterface::class,
        'Varien_Io_Sftp' => \Maho\Io\Sftp::class,
        'Varien_Object' => \Maho\DataObject::class,
        'Varien_Object_Cache' => \Maho\DataObject\Cache::class,
        'Varien_Object_Mapper' => \Maho\DataObject\Mapper::class, // @phpstan-ignore classConstant.deprecatedClass
        'Varien_Simplexml_Config' => \Maho\Simplexml\Config::class,
        'Varien_Simplexml_Element' => \Maho\Simplexml\Element::class,
        'Varien_Exception' => \Maho\Exception::class,
        'Varien_Profiler' => \Maho\Profiler::class,
    ];

    public static function isComposerAutoloaderOptimized(): bool
    {
        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof \Composer\Autoload\ClassLoader) {
                return isset($autoloader[0]->getClassMap()['Mage_Core_Model_App']);
            }
        }
        return false;
    }

    /**
     * @param array<string> $paths
     * @return array<string>
     */
    public static function findExistingPaths(array $paths): array
    {
        $existing = [];
        foreach ($paths as $path) {
            if (file_exists(MAHO_ROOT_DIR . "/{$path}")) {
                $existing[] = $path;
            }
        }
        return $existing;
    }

    /**
     * @return array<string>
     */
    public static function findOrphanedResourceIds(string $type): array
    {
        $rulesResource = Mage::getResourceModel("{$type}/rules");
        if (!method_exists($rulesResource, 'getOrphanedResourcesCollection')) {
            throw new \RuntimeException("Unable to load {$type}/rules resource model");
        }

        $collection = $rulesResource->getOrphanedResourcesCollection();
        $orphanedIds = [];
        foreach ($collection as $item) {
            $orphanedIds[] = $item->getResourceId();
        }
        return $orphanedIds;
    }

    /**
     * Tables still on a non-transactional engine, name => engine. Empty on
     * PostgreSQL and SQLite, which have no storage-engine concept.
     *
     * @return array<string, string>
     */
    public static function findLegacyEngineTables(): array
    {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        if (!($adapter instanceof \Maho\Db\Adapter\Pdo\Mysql)) {
            return [];
        }

        return \Maho\Db\Schema\Applier::legacyEngineTables(
            $adapter->getConnection(),
            \Maho\Db\Schema\Collector::tablePrefix(),
        );
    }

    /**
     * Tables holding enough reclaimable free space to be worth a rebuild.
     * Works on all three backends, each measuring its own form of bloat.
     *
     * @return list<array{table: string, total: int, reclaimable: int, ratio: float, detail: string}>
     */
    public static function findBloatedTables(): array
    {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');

        return \MahoCLI\Helper\TableBloatScanner::scan($adapter, \Maho\Db\Schema\Collector::tablePrefix());
    }

    /**
     * Scans user code (app/code/local and app/code/community) for legacy XML config
     * declarations that have PHP-attribute equivalents introduced in v26.5.
     *
     * @return array{
     *     routes: list<array{module: string, file: string, frontName: string, area: string}>,
     *     observers: list<array{module: string, file: string, count: int}>,
     *     cron: list<array{module: string, file: string, count: int}>
     * }
     */
    public static function findLegacyXmlConfig(): array
    {
        $findings = ['routes' => [], 'observers' => [], 'cron' => []];

        foreach (self::LEGACY_XML_SCAN_DIRS as $dir) {
            $base = MAHO_ROOT_DIR . '/' . $dir;
            if (!is_dir($base)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->getFilename() !== 'config.xml') {
                    continue;
                }

                $relPath = str_replace(MAHO_ROOT_DIR . '/', '', $file->getPathname());
                $parts = explode('/', $relPath);
                // Expected layout: app/code/{pool}/{Vendor}/{Module}/etc/config.xml
                if (count($parts) < 7 || $parts[5] !== 'etc') {
                    continue;
                }
                $moduleName = $parts[3] . '_' . $parts[4];

                libxml_use_internal_errors(true);
                $xml = simplexml_load_file($file->getPathname());
                libxml_clear_errors();
                if ($xml === false) {
                    continue;
                }

                // Legacy router declarations: <{area}><routers><X><use>{type}</use>
                $areaUseMap = ['frontend' => 'standard', 'admin' => 'admin', 'install' => 'install'];
                foreach ($areaUseMap as $area => $expectedUse) {
                    if (!isset($xml->{$area}->routers)) {
                        continue;
                    }
                    foreach ($xml->{$area}->routers->children() as $routerCode => $routerNode) {
                        $use = (string) ($routerNode->use ?? '');
                        if ($use !== $expectedUse) {
                            continue;
                        }
                        $frontName = (string) ($routerNode->args->frontName ?? $routerCode);
                        $findings['routes'][] = [
                            'module' => $moduleName,
                            'file' => $relPath,
                            'frontName' => $frontName,
                            'area' => $area,
                        ];
                    }
                }

                // Legacy observer declarations: <events> blocks under any scope
                $observerCount = 0;
                foreach (['global', 'frontend', 'adminhtml', 'crontab'] as $scope) {
                    if (!isset($xml->{$scope}->events)) {
                        continue;
                    }
                    foreach ($xml->{$scope}->events->children() as $eventNode) {
                        if (isset($eventNode->observers)) {
                            $observerCount += count($eventNode->observers->children());
                        }
                    }
                }
                if ($observerCount > 0) {
                    $findings['observers'][] = [
                        'module' => $moduleName,
                        'file' => $relPath,
                        'count' => $observerCount,
                    ];
                }

                // Legacy cron declarations: <crontab><jobs><X><run>
                $cronCount = 0;
                if (isset($xml->crontab->jobs)) {
                    foreach ($xml->crontab->jobs->children() as $jobNode) {
                        if (isset($jobNode->run)) {
                            $cronCount++;
                        }
                    }
                }
                if ($cronCount > 0) {
                    $findings['cron'][] = [
                        'module' => $moduleName,
                        'file' => $relPath,
                        'count' => $cronCount,
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * Validate the configured encryption key, returning the reason it is unusable
     * or null when it is a proper libsodium key. Magento/OpenMage stores carry an
     * mcrypt key (32 hex characters by default, but the installer took any key up
     * to the Blowfish maximum): copied into local.xml as-is it is the wrong length
     * and often not even hex, so `Mage::getEncryptionKeyAsBinary()` throws on every
     * encrypt/decrypt.
     */
    public static function findEncryptionKeyIssue(#[\SensitiveParameter] string $key): ?string
    {
        if ($key === '') {
            return 'No encryption key configured in app/etc/local.xml (<crypt><key>). '
                . 'Nothing can be encrypted or decrypted without it.';
        }
        // An mcrypt key is the right key for an mcrypt encryptor, so judge it by that
        // cipher's rules. Under that module Mage_Core_Model_Encryption is its class,
        // which has neither validateKeyAsHex() nor the KEY_LENGTH_* constants below.
        if (self::isLegacyEncryptionActive()) {
            return self::findLegacyEncryptionKeyIssue($key);
        }
        if (Mage::helper('core')->validateKeyAsHex($key)) {
            return null;
        }
        if (strlen($key) !== \Mage_Core_Model_Encryption::KEY_LENGTH_HEX) {
            return sprintf(
                'The key in app/etc/local.xml is %d characters long, a libsodium key is %d hexadecimal ones. '
                . 'Stores migrated from Magento/OpenMage carry an mcrypt key (32 characters by default), which '
                . 'makes every encrypt/decrypt call fail. Install mahocommerce/module-mcrypt-compat, then run '
                . '"./maho sys:encryptionkey:regenerate" to re-encrypt your data under a new libsodium key.',
                strlen($key),
                \Mage_Core_Model_Encryption::KEY_LENGTH_HEX,
            );
        }

        return sprintf(
            'The key in app/etc/local.xml is %d characters long but is not hexadecimal, so it is not a libsodium '
            . 'key and every encrypt/decrypt call fails. Restore the key your data was encrypted under, or run '
            . '"./maho sys:encryptionkey:regenerate" if there is no encrypted data left to keep.',
            \Mage_Core_Model_Encryption::KEY_LENGTH_HEX,
        );
    }

    /**
     * True when the store still runs on the legacy mcrypt encryptor rather than libsodium.
     */
    public static function isLegacyEncryptionActive(): bool
    {
        return Mage::helper('core')->isLegacyEncryptor();
    }

    /**
     * Prove the store can encrypt and read the value back, rather than inferring it from
     * the key's shape: an encryptor can be configured, and its key the right shape, while
     * the thing that does the work is missing. mahocommerce/module-mcrypt-compat copied
     * into a code pool by hand rather than required through Composer is exactly that, its
     * mcrypt_* functions come from phpseclib/mcrypt_compat and every call fails without it.
     */
    public static function findEncryptionFailure(): ?string
    {
        $probe = 'maho-health-check-probe';
        try {
            $helper = Mage::helper('core');
            if ($helper->decrypt($helper->encrypt($probe)) === $probe) {
                return null;
            }
            $reason = 'a test value did not survive an encrypt/decrypt round trip';
        } catch (\Throwable $e) {
            $reason = sprintf('encrypting a test value failed with %s: %s', $e::class, $e->getMessage());
        }

        if (self::isLegacyEncryptionActive() && !\Composer\InstalledVersions::isInstalled('phpseclib/mcrypt_compat')) {
            return sprintf(
                'Encryption is not working: %s. The store loads the legacy mcrypt encryptor, but '
                . 'phpseclib/mcrypt_compat is not installed, so the mcrypt functions it calls do not exist. '
                . 'Install the compatibility module through Composer with '
                . '"composer require mahocommerce/module-mcrypt-compat", which pulls it in.',
                $reason,
            );
        }

        return sprintf('Encryption is not working: %s.', $reason);
    }

    /**
     * Judge the key by the legacy encryptor's rules: Blowfish takes any key up to
     * MCRYPT_MAX_KEY_LENGTH bytes and nothing longer. Two states are broken rather
     * than merely dated, and both are silent at runtime, so the check must name them.
     */
    private static function findLegacyEncryptionKeyIssue(#[\SensitiveParameter] string $key): ?string
    {
        if (self::looksLikeSodiumKey($key)) {
            return 'The key in app/etc/local.xml is already a libsodium key, but the store still loads the '
                . 'legacy mcrypt encryptor from mahocommerce/module-mcrypt-compat, which cannot take a key '
                . 'this long: every encrypt and decrypt call throws. The key regeneration is done, so finish '
                . 'it with "composer remove mahocommerce/module-mcrypt-compat".';
        }
        if (strlen($key) > self::MCRYPT_MAX_KEY_LENGTH) {
            return sprintf(
                'The key in app/etc/local.xml is %d characters long, but the legacy mcrypt encryptor from '
                . 'mahocommerce/module-mcrypt-compat (Blowfish) takes at most %d, so every encrypt and '
                . 'decrypt call throws. Restore the key this store was encrypted under.',
                strlen($key),
                self::MCRYPT_MAX_KEY_LENGTH,
            );
        }

        return null;
    }

    /**
     * Shape test only, and deliberately not routed through the encryptor: under the
     * compat module that object has no validateKeyAsHex() to ask.
     */
    private static function looksLikeSodiumKey(#[\SensitiveParameter] string $key): bool
    {
        return strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES * 2 && ctype_xdigit($key);
    }

    /**
     * Find encrypted values that no longer open under the current key, which is what
     * an imported Magento/OpenMage database looks like next to a freshly generated
     * key. Decryption yields an empty string rather than an error, so payment and
     * SMTP credentials read as blank instead of failing loudly.
     *
     * Scans core_config_data and admin_user only: both are small and bounded, and a
     * key mismatch fails every row anyway.
     *
     * @return list<string>
     */
    public static function findUndecryptableData(): array
    {
        // Values under the legacy encryptor are not sodium ciphertext and the key is
        // not hex, so getEncryptionKeyAsBinary() would throw before the first row.
        if (self::isLegacyEncryptionActive()) {
            return [];
        }
        if (self::findEncryptionKeyIssue(Mage::getEncryptionKeyAsHex()) !== null) {
            return [];
        }

        $key = Mage::getEncryptionKeyAsBinary();
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $failures = [];

        $encryptedPaths = Mage::helper('core')->getEncryptedConfigPaths();
        if (!empty($encryptedPaths)) {
            $configTable = $resource->getTableName('core_config_data');
            $select = $read->select()
                ->from($configTable, ['config_id', 'path', 'value'])
                ->where('value IS NOT NULL')
                ->where("value != ''")
                ->where('path IN (?)', $encryptedPaths);
            foreach ($read->fetchAll($select) as $row) {
                if (!self::canDecrypt($key, (string) $row['value'])) {
                    $failures[] = sprintf('%s #%s (%s)', $configTable, $row['config_id'], $row['path']);
                }
            }
        }

        $adminTable = $resource->getTableName('admin_user');
        $select = $read->select()
            ->from($adminTable, ['user_id', 'twofa_secret'])
            ->where('twofa_secret IS NOT NULL')
            ->where("twofa_secret != ''");
        foreach ($read->fetchAll($select) as $row) {
            if (!self::canDecrypt($key, (string) $row['twofa_secret'])) {
                $failures[] = sprintf('%s #%s (twofa_secret)', $adminTable, $row['user_id']);
            }
        }
        sodium_memzero($key);

        return $failures;
    }

    /**
     * Decrypt without going through the encryptor, which logs an exception per
     * failure: a health check must not fill exception.log with its own findings.
     */
    private static function canDecrypt(#[\SensitiveParameter] string $key, string $value): bool
    {
        try {
            $decoded = sodium_base642bin($value, SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (\SodiumException) {
            return false;
        }
        if (strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return false;
        }

        return sodium_crypto_secretbox_open(
            substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $key,
        ) !== false;
    }

    /**
     * @param list<string> $failures
     */
    private static function formatUndecryptableSummary(array $failures): string
    {
        $shown = array_slice($failures, 0, 10);
        return sprintf(
            '%d encrypted value(s) cannot be decrypted with the current key: %s%s. %s',
            count($failures),
            implode(', ', $shown),
            count($failures) > count($shown) ? ', ...' : '',
            self::UNDECRYPTABLE_ADVICE,
        );
    }

    /**
     * Detect a legacy `<admin><routers><adminhtml><args><frontName>` declaration
     * in `app/etc/local.xml`. The constant `Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_ROUTER_FRONTNAME`
     * now resolves to `admin/base_path`; an entry at the old path is silently ignored,
     * leaving the admin reachable only at `/admin/...` regardless of the value declared here.
     *
     * @return ?array{file: string, frontName: string}
     */
    public static function findLegacyLocalXmlAdminPath(): ?array
    {
        $file = MAHO_ROOT_DIR . '/app/etc/local.xml';
        if (!is_file($file)) {
            return null;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file);
        libxml_clear_errors();
        if ($xml === false) {
            return null;
        }

        $frontName = (string) ($xml->admin->routers->adminhtml->args->frontName ?? '');
        if ($frontName === '') {
            return null;
        }

        return ['file' => 'app/etc/local.xml', 'frontName' => $frontName];
    }

    /**
     * @param list<array{module: string, file?: string, frontName?: string, area?: string, count?: int}> $findings
     */
    private static function formatLegacyXmlSummary(string $label, array $findings, string $attribute): string
    {
        $modules = array_values(array_unique(array_column($findings, 'module')));
        return sprintf(
            'Found legacy XML %s in %d module(s): %s. Migrate to %s attributes.',
            $label,
            count($modules),
            implode(', ', $modules),
            $attribute,
        );
    }

    /**
     * @return array<int, array{check: string, severity: string, details: string}>
     */
    public static function getCheckResults(): array
    {
        $checks = [];

        $isOptimized = self::isComposerAutoloaderOptimized();
        $checks[] = [
            'check' => 'Composer Autoloader',
            'severity' => $isOptimized ? 'warning' : 'ok',
            'details' => $isOptimized ? 'Optimized autoloader detected. This is fine for production, but may cause issues during development. Run "composer dump" to fix.' : '',
        ];

        $legacyFiles = self::findExistingPaths(self::LEGACY_CORE_FILES);
        $checks[] = [
            'check' => 'Legacy Core Files',
            'severity' => empty($legacyFiles) ? 'ok' : 'error',
            'details' => empty($legacyFiles) ? '' : 'Found old Magento/OpenMage files: ' . implode(', ', $legacyFiles) . '. These should be removed.',
        ];

        $deprecatedFolders = self::findExistingPaths(self::DEPRECATED_FOLDERS);
        $checks[] = [
            'check' => 'Deprecated Folders',
            'severity' => empty($deprecatedFolders) ? 'ok' : 'error',
            'details' => empty($deprecatedFolders) ? '' : 'Found deprecated folders: ' . implode(', ', $deprecatedFolders) . '. Remove them to avoid unpredictable behavior.',
        ];

        foreach (['admin' => 'Admin', 'api' => 'API'] as $type => $label) {
            try {
                $orphanedIds = self::findOrphanedResourceIds($type);
                $checks[] = [
                    'check' => "{$label} Orphaned Role Resources",
                    'severity' => empty($orphanedIds) ? 'ok' : 'warning',
                    'details' => empty($orphanedIds) ? '' : 'Found ' . count($orphanedIds) . ' orphaned resource(s): ' . implode(', ', $orphanedIds),
                ];
            } catch (\Exception) {
                $checks[] = [
                    'check' => "{$label} Orphaned Role Resources",
                    'severity' => 'error',
                    'details' => 'Unable to check orphaned resources.',
                ];
            }
        }

        try {
            $legacyEngines = self::findLegacyEngineTables();
            $checks[] = [
                'check' => 'Table Storage Engines',
                'severity' => empty($legacyEngines) ? 'ok' : 'warning',
                'details' => empty($legacyEngines) ? '' : sprintf(
                    '%d table(s) are not InnoDB (%s). Writing them inside a transaction fails on MySQL 8.4+, '
                    . 'where enforce_gtid_consistency defaults to ON. Run "./maho migrate" to convert them.',
                    count($legacyEngines),
                    implode(', ', array_keys($legacyEngines)),
                ),
            ];
        } catch (\Exception) {
            $checks[] = [
                'check' => 'Table Storage Engines',
                'severity' => 'error',
                'details' => 'Unable to check table storage engines.',
            ];
        }

        try {
            $bloated = self::findBloatedTables();
            $checks[] = [
                'check' => 'Table Optimization',
                'severity' => empty($bloated) ? 'ok' : 'warning',
                'details' => empty($bloated) ? '' : sprintf(
                    '%d table(s) hold ~%s of reclaimable free space (%s). Bloat this size usually means rows were '
                    . 'purged in bulk, or that a cleanup job (./maho log:clean) is not running. Run "./maho db:optimize" '
                    . 'during a maintenance window to return the space to the filesystem.',
                    count($bloated),
                    Mage::helper('core')->formatFileSize((int) array_sum(array_column($bloated, 'reclaimable'))),
                    implode(', ', array_column($bloated, 'table')),
                ),
            ];
        } catch (\Exception) {
            $checks[] = [
                'check' => 'Table Optimization',
                'severity' => 'error',
                'details' => 'Unable to check table optimization.',
            ];
        }

        $legacyXml = self::findLegacyXmlConfig();

        $checks[] = [
            'check' => 'Legacy XML Routing',
            'severity' => empty($legacyXml['routes']) ? 'ok' : 'warning',
            'details' => empty($legacyXml['routes']) ? '' : self::formatLegacyXmlSummary(
                'route declarations',
                $legacyXml['routes'],
                '#[Maho\\Config\\Route]',
            ),
        ];

        $checks[] = [
            'check' => 'Legacy XML Observers',
            'severity' => empty($legacyXml['observers']) ? 'ok' : 'warning',
            'details' => empty($legacyXml['observers']) ? '' : self::formatLegacyXmlSummary(
                'observer declarations',
                $legacyXml['observers'],
                '#[Maho\\Config\\Observer]',
            ),
        ];

        $checks[] = [
            'check' => 'Legacy XML Cron Jobs',
            'severity' => empty($legacyXml['cron']) ? 'ok' : 'warning',
            'details' => empty($legacyXml['cron']) ? '' : self::formatLegacyXmlSummary(
                'cron job declarations',
                $legacyXml['cron'],
                '#[Maho\\Config\\CronJob]',
            ),
        ];

        // The verdict is measured, not inferred; findEncryptionKeyIssue() only explains it.
        $failure = self::findEncryptionFailure();
        $keyIssue = $failure === null ? null : (self::findEncryptionKeyIssue(Mage::getEncryptionKeyAsHex()) ?? $failure);
        $legacyOk = $failure === null && self::isLegacyEncryptionActive();
        $checks[] = [
            'check' => 'Encryption Key',
            'severity' => $legacyOk ? 'warning' : ($failure === null ? 'ok' : 'error'),
            'details' => $legacyOk ? self::LEGACY_ENCRYPTOR_ADVICE : ($keyIssue ?? ''),
        ];

        if ($legacyOk) {
            $checks[] = [
                'check' => 'Encrypted Data',
                'severity' => 'warning',
                'details' => 'Not checked: the store is still on the legacy mcrypt encryptor.',
            ];
        } elseif ($keyIssue !== null) {
            $checks[] = [
                'check' => 'Encrypted Data',
                'severity' => 'warning',
                'details' => 'Not checked: nothing can be decrypted until the encryption key is fixed.',
            ];
        } else {
            try {
                $undecryptable = self::findUndecryptableData();
                $checks[] = [
                    'check' => 'Encrypted Data',
                    'severity' => empty($undecryptable) ? 'ok' : 'error',
                    'details' => empty($undecryptable) ? '' : self::formatUndecryptableSummary($undecryptable),
                ];
            } catch (\Exception) {
                $checks[] = [
                    'check' => 'Encrypted Data',
                    'severity' => 'error',
                    'details' => 'Unable to check encrypted data.',
                ];
            }
        }

        $legacyAdminPath = self::findLegacyLocalXmlAdminPath();
        $checks[] = [
            'check' => 'Legacy Admin Frontname in local.xml',
            'severity' => $legacyAdminPath === null ? 'ok' : 'warning',
            'details' => $legacyAdminPath === null ? '' : sprintf(
                'Found <admin><routers><adminhtml><args><frontName>%s</frontName> in %s. This node is ignored; '
                . 'replace it with <admin><base_path>%s</base_path> (or run `./maho legacy:migrate-routes`).',
                $legacyAdminPath['frontName'],
                $legacyAdminPath['file'],
                $legacyAdminPath['frontName'],
            ),
        ];

        return $checks;
    }

    /**
     * Check frontend themes for common issues
     *
     * @return array{errors: array<string>, warnings: array<string>}
     */
    protected function checkFrontendThemes(): array
    {
        $errors = [];
        $warnings = [];

        // Get themes from all packages (including vendor) for parent validation
        $packageThemes = $this->getAllThemesFromPackages();
        $allThemes = $packageThemes['all'];
        $allDesignThemes = $packageThemes['design'];
        $allSkinThemes = $packageThemes['skin'];

        // Get themes only from the project root for checking issues
        // (we don't want to report issues in vendor packages)
        $projectDesignThemes = $this->getThemesFromProjectPath(self::DESIGN_PATH);
        $projectSkinThemes = $this->getThemesFromProjectPath(self::SKIN_PATH);
        $projectThemes = array_unique(array_merge($projectDesignThemes, $projectSkinThemes));

        foreach ($projectThemes as $theme) {
            [$package, $themeName] = explode('/', $theme);

            // Check for orphaned directories
            // A project skin/design directory is not orphaned if a matching counterpart
            // exists in any package (including vendor)
            $hasDesignInProject = in_array($theme, $projectDesignThemes, true);
            $hasSkinInProject = in_array($theme, $projectSkinThemes, true);
            $hasDesignAnywhere = in_array($theme, $allDesignThemes, true);
            $hasSkinAnywhere = in_array($theme, $allSkinThemes, true);

            if ($hasDesignInProject && !$hasSkinInProject && !$hasSkinAnywhere) {
                $warnings[] = "{$theme}: Missing skin directory (expected: " . self::SKIN_PATH . "/{$theme}/)";
            } elseif (!$hasDesignInProject && $hasSkinInProject && !$hasDesignAnywhere) {
                $warnings[] = "{$theme}: Orphaned skin directory (no matching design folder at " . self::DESIGN_PATH . "/{$theme}/)";
            }

            // Check theme.xml if design directory exists in project
            if ($hasDesignInProject) {
                $themeXmlPath = MAHO_ROOT_DIR . '/' . self::DESIGN_PATH . "/{$package}/{$themeName}/etc/theme.xml";

                if (file_exists($themeXmlPath)) {
                    // Validate against ALL themes (including vendor) for parent checking
                    $themeXmlErrors = $this->validateThemeXml($themeXmlPath, $theme, $allThemes);
                    $errors = array_merge($errors, $themeXmlErrors);
                }
            }
        }

        // Check for circular inheritance (only for project themes, but considering all available parents)
        $circularErrors = $this->checkCircularInheritance($projectThemes, $allThemes);
        $errors = array_merge($errors, $circularErrors);

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Get all theme identifiers from all installed packages (including vendor)
     *
     * @return array{design: array<string>, skin: array<string>, all: array<string>}
     */
    private function getAllThemesFromPackages(): array
    {
        $designThemes = [];
        $skinThemes = [];

        foreach (\Maho::listDirectories(self::DESIGN_PATH) as $packageName) {
            foreach (\Maho::listDirectories(self::DESIGN_PATH . '/' . $packageName) as $themeName) {
                $designThemes[] = "{$packageName}/{$themeName}";
            }
        }

        foreach (\Maho::listDirectories(self::SKIN_PATH) as $packageName) {
            foreach (\Maho::listDirectories(self::SKIN_PATH . '/' . $packageName) as $themeName) {
                $skinThemes[] = "{$packageName}/{$themeName}";
            }
        }

        return [
            'design' => array_unique($designThemes),
            'skin' => array_unique($skinThemes),
            'all' => array_unique(array_merge($designThemes, $skinThemes)),
        ];
    }

    /**
     * Get theme identifiers from the project root only (not vendor)
     *
     * @return array<string>
     */
    private function getThemesFromProjectPath(string $relativePath): array
    {
        $themes = [];
        $basePath = MAHO_ROOT_DIR . '/' . $relativePath;

        if (!is_dir($basePath)) {
            return $themes;
        }

        $packages = glob($basePath . '/*', GLOB_ONLYDIR);
        foreach ($packages as $packagePath) {
            $packageName = basename($packagePath);
            $themeDirs = glob($packagePath . '/*', GLOB_ONLYDIR);

            foreach ($themeDirs as $themePath) {
                $themeName = basename($themePath);
                $themes[] = "{$packageName}/{$themeName}";
            }
        }

        return $themes;
    }

    /**
     * Validate theme.xml file
     *
     * @param array<string> $allThemes
     * @return array<string>
     */
    private function validateThemeXml(string $themeXmlPath, string $theme, array $allThemes): array
    {
        $errors = [];

        // Check XML syntax
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($themeXmlPath);

        if ($xml === false) {
            $xmlErrors = libxml_get_errors();
            $errorMessages = [];
            foreach ($xmlErrors as $error) {
                $errorMessages[] = trim($error->message);
            }
            libxml_clear_errors();
            $errors[] = "{$theme}: Invalid theme.xml syntax - " . implode(', ', $errorMessages);
            return $errors;
        }

        libxml_clear_errors();

        // Check parent theme reference
        if (isset($xml->parent)) {
            $parentTheme = (string) $xml->parent;

            if (!empty($parentTheme) && !in_array($parentTheme, $allThemes, true)) {
                $errors[] = "{$theme}: Parent theme '{$parentTheme}' does not exist";
            }
        }

        return $errors;
    }

    /**
     * Check for circular inheritance in themes
     *
     * @param array<string> $projectThemes Themes in the project to check
     * @param array<string> $allThemes All available themes (including vendor)
     * @return array<string>
     */
    private function checkCircularInheritance(array $projectThemes, array $allThemes): array
    {
        $errors = [];
        $parentMap = [];

        // Build parent map for all themes (need to know parent relationships across all packages)
        foreach ($allThemes as $theme) {
            [$package, $themeName] = explode('/', $theme);

            // Find theme.xml across all packages
            $themeXmlPath = \Maho::findFile(self::DESIGN_PATH . "/{$package}/{$themeName}/etc/theme.xml");

            if ($themeXmlPath !== false) {
                libxml_use_internal_errors(true);
                $xml = simplexml_load_file($themeXmlPath);
                libxml_clear_errors();

                if ($xml !== false && isset($xml->parent)) {
                    $parentMap[$theme] = (string) $xml->parent;
                }
            }
        }

        // Only check for cycles starting from project themes
        foreach ($projectThemes as $theme) {
            if (!isset($parentMap[$theme])) {
                continue;
            }

            $visited = [$theme];
            $current = $parentMap[$theme];

            while (!empty($current)) {
                if (in_array($current, $visited, true)) {
                    $cycle = array_slice($visited, array_search($current, $visited, true));
                    $cycle[] = $current;
                    $errors[] = "{$theme}: Circular inheritance detected (" . implode(' → ', $cycle) . ')';
                    break;
                }
                $visited[] = $current;
                $current = $parentMap[$current] ?? null;
            }
        }

        return array_unique($errors);
    }

    /**
     * Check for usage of deprecated Varien_ classes in user code
     *
     * @return array<string, array<string, array<int>>>
     */
    protected function checkVarienClassUsage(): array
    {
        $findings = [];

        // Directories to scan (user code, not vendor or Maho core)
        $scanDirs = [
            'app/code/local',
            'app/code/community',
        ];

        foreach ($scanDirs as $dir) {
            $fullPath = MAHO_ROOT_DIR . '/' . $dir;
            if (!is_dir($fullPath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = file_get_contents($file->getPathname());
                if ($content === false) {
                    continue;
                }

                $relativePath = str_replace(MAHO_ROOT_DIR . '/', '', $file->getPathname());
                $lines = explode("\n", $content);

                foreach ($lines as $lineNum => $line) {
                    // Look for Varien_ class references
                    if (preg_match_all('/\bVarien_[A-Za-z_]+/', $line, $matches)) {
                        foreach ($matches[0] as $match) {
                            // Only report if it's a known Varien class that has a Maho replacement
                            $classKey = $this->findVarienClassKey($match);
                            if ($classKey !== null) {
                                $findings[$relativePath][$classKey][] = $lineNum + 1;
                            }
                        }
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * Find the matching Varien class key for a given class name
     * Handles both exact matches and partial matches (e.g., Varien_Data_Form_Element_Text)
     */
    private function findVarienClassKey(string $className): ?string
    {
        // Check for exact match first
        if (isset(self::VARIEN_TO_MAHO_MAP[$className])) {
            return $className;
        }

        // Check if it starts with any known Varien class prefix
        // Sort by length descending to match most specific first
        $keys = array_keys(self::VARIEN_TO_MAHO_MAP);
        usort($keys, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($keys as $key) {
            if (str_starts_with($className, $key . '_') || $className === $key) {
                return $key;
            }
        }

        // It's a Varien_ class but not in our map - still report it generically
        if (str_starts_with($className, 'Varien_')) {
            return $className;
        }

        return null;
    }

    /**
     * Format Varien class usage findings for output
     *
     * @param array<string, array<string, array<int>>> $findings
     * @return array<string>
     */
    private function formatVarienFindings(array $findings): array
    {
        $output = [];

        foreach ($findings as $file => $classes) {
            $classDetails = [];
            foreach ($classes as $className => $lines) {
                $replacement = self::VARIEN_TO_MAHO_MAP[$className] ?? 'Maho\\*';
                $lineList = implode(', ', array_unique($lines));
                $classDetails[] = "{$className} → {$replacement} (lines: {$lineList})";
            }
            $output[] = "{$file}:";
            foreach ($classDetails as $detail) {
                $output[] = "    {$detail}";
            }
        }

        return $output;
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption(
            'check-zero-dates',
            null,
            InputOption::VALUE_NONE,
            'Also scan every date column for stored zero-date values (full table scans, slow on large stores)',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hasErrors = false;

        // Check for use-include-path in composer.json
        $output->write('Checking composer.json... ');
        if (self::isComposerAutoloaderOptimized()) {
            $hasErrors = true;
            $output->writeln('');
            $output->writeln('<comment>Warning: Optimized autoloader detected.</comment>');
            $output->writeln('Ignore if you are in a production environment, otherwise run: composer dump');
            $output->writeln('');
        } else {
            $output->writeln('<info>OK</info>');
        }

        // Check for M1 core files
        $output->write('Checking Magento/OpenMage core... ');
        $existingFolders = self::findExistingPaths(self::LEGACY_CORE_FILES);

        if (empty($existingFolders)) {
            $output->writeln('<info>OK</info>');
        } else {
            $hasErrors = true;
            $output->writeln('');
            $output->writeln('<error>Error: Detected files/folder from an old Magento/OpenMage core:</error>');
            foreach ($existingFolders as $folder) {
                $output->writeln('- ' . $folder);
            }
            $output->writeln('Make sure you delete them,');
            $output->writeln('unless you need to override a specific file from the core (not advisable).');
            $output->writeln('');
        }

        // Check for custom API
        $output->write('Checking custom APIs... ');
        exec('grep -ir -l -E "urn:Magento|urn:OpenMage" . --include="*.xml"', $matchingFiles, $returnCode);

        if (empty($matchingFiles)) {
            $output->writeln('<info>OK</info>');
        } else {
            $hasErrors = true;
            $output->writeln('');
            $output->writeln('<error>Error: Found "urn:Magento" or "urn:OpenMage" in the following files:</error>');
            foreach ($matchingFiles as $file) {
                $output->writeln('- ' . substr($file, 2));
            }
            $output->writeln('Replace all occurrences of "urn:Magento" or "urn:OpenMage" with "urn:Maho".');
            $output->writeln('');
        }

        // Check for deprecated folders
        $output->write('Checking for deprecated folders... ');
        $existingFolders = self::findExistingPaths(self::DEPRECATED_FOLDERS);
        if (empty($existingFolders)) {
            $output->writeln('<info>OK</info>');
        } else {
            $hasErrors = true;
            $output->writeln('');
            $output->writeln('<error>Error: Found deprecated folders:</error>');
            foreach ($existingFolders as $folder) {
                $output->writeln('- ' . $folder);
            }
            $output->writeln('You should remove them to avoid unpredictable behaviors.');
            $output->writeln('');
        }

        // Check frontend themes
        $output->write('Checking frontend themes... ');
        $themeResults = $this->checkFrontendThemes();

        if (empty($themeResults['errors']) && empty($themeResults['warnings'])) {
            $output->writeln('<info>OK</info>');
        } else {
            if (!empty($themeResults['errors'])) {
                $hasErrors = true;
                $output->writeln('');
                $output->writeln('<error>Error: Frontend theme issues found:</error>');
                foreach ($themeResults['errors'] as $error) {
                    $output->writeln('- ' . $error);
                }
            }
            if (!empty($themeResults['warnings'])) {
                if (empty($themeResults['errors'])) {
                    $output->writeln('');
                }
                $output->writeln('<comment>Warning: Frontend theme warnings:</comment>');
                foreach ($themeResults['warnings'] as $warning) {
                    $output->writeln('- ' . $warning);
                }
            }
            $output->writeln('');
        }

        // Check for deprecated Varien_ class usage
        $output->write('Checking for deprecated Varien_ classes... ');
        $varienFindings = $this->checkVarienClassUsage();

        if (empty($varienFindings)) {
            $output->writeln('<info>OK</info>');
        } else {
            $output->writeln('');
            $output->writeln('<comment>Warning: Found deprecated Varien_ class usage:</comment>');
            $output->writeln('These classes have been migrated to the Maho\\ namespace.');
            $output->writeln('Class aliases exist for backward compatibility, but you should migrate to the new classes.');
            $output->writeln('');
            foreach ($this->formatVarienFindings($varienFindings) as $line) {
                $output->writeln($line);
            }
            $output->writeln('');
            $output->writeln('See: https://github.com/MahoCommerce/maho/pull/340');
            $output->writeln('');
        }

        // Check for legacy XML config (routes, observers, cron jobs)
        $output->write('Checking for legacy XML config... ');
        $legacyXml = self::findLegacyXmlConfig();
        $totalLegacy = count($legacyXml['routes']) + count($legacyXml['observers']) + count($legacyXml['cron']);

        if ($totalLegacy === 0) {
            $output->writeln('<info>OK</info>');
        } else {
            $output->writeln('');
            $output->writeln('<comment>Warning: Found legacy XML configuration in user modules:</comment>');
            $output->writeln('These declarations still work via a back-compatibility shim, but should be migrated to PHP attributes.');
            $output->writeln('');

            if (!empty($legacyXml['routes'])) {
                $output->writeln('<comment>Legacy XML routes (migrate to #[Maho\Config\Route]):</comment>');
                foreach ($legacyXml['routes'] as $r) {
                    $output->writeln(sprintf('  - %s (%s area, frontName: %s) in %s', $r['module'], $r['area'], $r['frontName'], $r['file']));
                }
                $output->writeln('');
            }

            if (!empty($legacyXml['observers'])) {
                $output->writeln('<comment>Legacy XML observers (migrate to #[Maho\Config\Observer]):</comment>');
                foreach ($legacyXml['observers'] as $o) {
                    $output->writeln(sprintf('  - %s (%d observer(s)) in %s', $o['module'], $o['count'], $o['file']));
                }
                $output->writeln('');
            }

            if (!empty($legacyXml['cron'])) {
                $output->writeln('<comment>Legacy XML cron jobs (migrate to #[Maho\Config\CronJob]):</comment>');
                foreach ($legacyXml['cron'] as $c) {
                    $output->writeln(sprintf('  - %s (%d job(s)) in %s', $c['module'], $c['count'], $c['file']));
                }
                $output->writeln('');
            }

            $output->writeln('See: https://mahocommerce.com/routing/');
            $output->writeln('');
        }

        // Check for a legacy admin frontName declaration in app/etc/local.xml
        $output->write('Checking app/etc/local.xml admin frontName... ');
        $legacyAdminPath = self::findLegacyLocalXmlAdminPath();
        if ($legacyAdminPath === null) {
            $output->writeln('<info>OK</info>');
        } else {
            $output->writeln('');
            $output->writeln(sprintf(
                '<comment>Warning: Legacy admin frontName found in %s.</comment>',
                $legacyAdminPath['file'],
            ));
            $output->writeln(sprintf(
                '  <admin><routers><adminhtml><args><frontName>%s</frontName>... is no longer honored.',
                $legacyAdminPath['frontName'],
            ));
            $output->writeln(sprintf(
                '  Replace it with <admin><base_path>%s</base_path>, or run: ./maho legacy:migrate-routes',
                $legacyAdminPath['frontName'],
            ));
            $output->writeln('');
        }

        // Checks below need the application (and its database) bootstrapped
        $this->initMaho();

        if (!$this->checkEncryption($output)) {
            $hasErrors = true;
        }

        $this->checkOrphanedResources($input, $output, Mage::getResourceModel('admin/rules'), 'admin');
        $this->checkOrphanedResources($input, $output, Mage::getResourceModel('api/rules'), 'API');

        $this->checkZeroDates($output, (bool) $input->getOption('check-zero-dates'));
        $this->checkTableEngines($output);
        $this->checkTableBloat($output);

        if ($hasErrors) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Report an unusable encryption key, and data that no longer decrypts under it.
     * Returns false when either check failed.
     */
    private function checkEncryption(OutputInterface $output): bool
    {
        $output->write('Checking encryption key... ');
        $failure = self::findEncryptionFailure();
        $keyIssue = $failure === null ? null : (self::findEncryptionKeyIssue(Mage::getEncryptionKeyAsHex()) ?? $failure);
        if ($failure === null && self::isLegacyEncryptionActive()) {
            $output->writeln('<comment>LEGACY</comment>');
            $output->writeln(wordwrap(self::LEGACY_ENCRYPTOR_ADVICE, 100));
            $output->writeln('Checking encrypted data... <comment>SKIPPED (legacy mcrypt encryptor)</comment>');
            $output->writeln('');
            return true;
        }

        if ($keyIssue !== null) {
            $output->writeln('');
            $output->writeln('<error>Error: ' . $keyIssue . '</error>');
            $output->writeln('Checking encrypted data... <comment>SKIPPED (fix the key first)</comment>');
            $output->writeln('');
            return false;
        }
        $output->writeln('<info>OK</info>');

        $output->write('Checking encrypted data... ');
        try {
            $undecryptable = self::findUndecryptableData();
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>Error: unable to check encrypted data: ' . $e->getMessage() . '</error>');
            $output->writeln('');
            return false;
        }
        if (empty($undecryptable)) {
            $output->writeln('<info>OK</info>');
            return true;
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<error>Error: %d encrypted value(s) cannot be decrypted with the current key:</error>',
            count($undecryptable),
        ));
        foreach ($undecryptable as $failure) {
            $output->writeln('- ' . $failure);
        }
        $output->writeln(wordwrap(self::UNDECRYPTABLE_ADVICE, 100));
        $output->writeln('');

        return false;
    }

    /**
     * Detect legacy zero dates that are incompatible with the strict SQL_MODE
     * baseline (NO_ZERO_DATE): stored '0000-00-00' values make any later UPDATE
     * of those rows fail, and zero-date column defaults make INSERTs omitting
     * the column fail. Typically found on stores migrated from Magento/OpenMage.
     *
     * The DEFAULT check is a single indexed information_schema query and always
     * runs. The stored-value scan is a full table scan per date column (slow on
     * large stores), so it runs only when explicitly requested via $scanValues.
     */
    private function checkZeroDates(OutputInterface $output, bool $scanValues): void
    {
        $output->write('Checking for legacy zero dates... ');

        $adapter = \Mage::getSingleton('core/resource')->getConnection('core_read');
        if (!($adapter instanceof \Maho\Db\Adapter\Pdo\Mysql)) {
            $output->writeln('<info>OK (MySQL/MariaDB only check)</info>');
            return;
        }

        $badDefaults = \MahoCLI\Helper\ZeroDateScanner::findZeroDateDefaults($adapter);
        $badValues = $scanValues ? \MahoCLI\Helper\ZeroDateScanner::findZeroDateValues($adapter) : [];

        if (empty($badDefaults) && empty($badValues)) {
            $output->writeln('<info>OK</info>');
            if (!$scanValues) {
                $output->writeln('(DEFAULTs only; add --check-zero-dates to also scan stored values, slow on large stores)');
            }
            return;
        }

        $output->writeln('');
        $output->writeln('<comment>Warning: Found legacy zero dates, which strict SQL_MODE (NO_ZERO_DATE) rejects:</comment>');
        if (!empty($badDefaults)) {
            $output->writeln('Columns with a zero-date DEFAULT (INSERTs omitting the column will fail):');
            foreach ($badDefaults as $finding) {
                $output->writeln('- ' . $finding['table'] . '.' . $finding['column']);
            }
        }
        if (!empty($badValues)) {
            $output->writeln('Columns containing zero-date values (UPDATEs rewriting those rows will fail):');
            foreach ($badValues as $finding) {
                $output->writeln(sprintf('- %s.%s (%d row(s))', $finding['table'], $finding['column'], $finding['rows']));
            }
        }
        $output->writeln('Run: ./maho legacy:fix-zero-dates');
        $output->writeln('To temporarily restore the old behavior, set <sql_mode></sql_mode> on the');
        $output->writeln('connection in app/etc/local.xml while you clean up.');
        $output->writeln('');
    }

    /**
     * Detect tables still on a non-transactional storage engine, which the
     * indexers and checkout write inside transactions: that fails with SQLSTATE
     * 1785 on MySQL 8.4+, where enforce_gtid_consistency defaults to ON.
     */
    private function checkTableEngines(OutputInterface $output): void
    {
        $output->write('Checking table storage engines... ');

        $adapter = \Mage::getSingleton('core/resource')->getConnection('core_read');
        if (!($adapter instanceof \Maho\Db\Adapter\Pdo\Mysql)) {
            $output->writeln('<info>OK (MySQL/MariaDB only check)</info>');
            return;
        }

        $tables = self::findLegacyEngineTables();
        if ($tables === []) {
            $output->writeln('<info>OK</info>');
            return;
        }

        // Warned about either way: a store on 8.0 today is a store on 8.4+ after
        // one upgrade, and MariaDB (which has no such variable) still holds no
        // foreign keys on MyISAM and still empties MEMORY on restart.
        $enforced = false;
        try {
            $enforced = (string) $adapter->fetchOne('SELECT @@enforce_gtid_consistency') === 'ON';
        } catch (\Exception) {
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<comment>Warning: %d table(s) are not InnoDB, so writing them inside a transaction %s:</comment>',
            count($tables),
            $enforced ? 'fails on this server (enforce_gtid_consistency=ON)' : 'is unsafe, and fails outright on MySQL 8.4+',
        ));
        foreach ($tables as $table => $engine) {
            $output->writeln(sprintf('- %s (%s)', $table, $engine));
        }
        $output->writeln('Run: ./maho migrate');
        $output->writeln('');
    }

    /**
     * Detect tables whose on-disk footprint is mostly free space left behind by
     * bulk deletes. All three backends are covered, each with its own measure:
     * InnoDB's DATA_FREE, PostgreSQL's dead-tuple share, and SQLite's page
     * freelist. Detection is metadata-only; the rebuild that reclaims the space
     * is a maintenance-window operation, so it is never run from here.
     */
    private function checkTableBloat(OutputInterface $output): void
    {
        $output->write('Checking table optimization... ');

        $adapter = \Mage::getSingleton('core/resource')->getConnection('core_read');
        $tables = \MahoCLI\Helper\TableBloatScanner::scan($adapter, \Maho\Db\Schema\Collector::tablePrefix());

        if ($tables === []) {
            $output->writeln('<info>OK</info>');
            $reason = \MahoCLI\Helper\TableBloatScanner::reclaimUnavailableReason($adapter);
            if ($reason !== null) {
                $output->writeln('(not measurable here: ' . $reason . ')');
            }
            return;
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<comment>Warning: %d table(s) hold reclaimable free space:</comment>',
            count($tables),
        ));
        $helper = Mage::helper('core');
        foreach ($tables as $table) {
            $output->writeln(sprintf(
                '- %s: %s of %s reclaimable (%d%%)%s',
                $table['table'],
                $helper->formatFileSize($table['reclaimable']),
                $helper->formatFileSize($table['total']),
                (int) round($table['ratio'] * 100),
                $table['detail'] === '' ? '' : ', ' . $table['detail'],
            ));
        }
        $output->writeln('Bloat this size usually means rows were purged in bulk, or that a cleanup job is not');
        $output->writeln('running: check ./maho log:status and ./maho log:clean before rebuilding.');
        $output->writeln('Run: ./maho db:optimize (during a maintenance window)');
        $output->writeln('');
    }

    private function checkOrphanedResources(
        InputInterface $input,
        OutputInterface $output,
        \Mage_Admin_Model_Resource_Rules|\Mage_Api_Model_Resource_Rules $rulesResource,
        string $label,
    ): void {
        $output->write("Checking for orphaned {$label} role resources... ");

        $collection = $rulesResource->getOrphanedResourcesCollection();

        $orphanedIds = [];
        foreach ($collection as $item) {
            $orphanedIds[] = $item->getResourceId();
        }

        if ($orphanedIds === []) {
            $output->writeln('<info>OK</info>');
            return;
        }

        $output->writeln('');
        $output->writeln('<comment>Warning: Found ' . count($orphanedIds) . " orphaned {$label} role resource(s):</comment>");
        foreach ($orphanedIds as $resource) {
            $output->writeln('  - ' . $resource);
        }

        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "<question>Do you want to delete these orphaned {$label} role resources? [y/N]</question> ",
            false,
        );
        if ($helper->ask($input, $output, $question)) {
            $deleted = $rulesResource->deleteOrphanedResources($orphanedIds);
            $output->writeln("<info>Deleted {$deleted} orphaned {$label} role resource rule(s).</info>");
        }
        $output->writeln('');
    }
}
