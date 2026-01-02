<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Install_Model_Installer_Console extends Mage_Install_Model_Installer_Abstract
{
    /**
     * Available options
     *
     * @var array|null
     */
    protected $_options;

    /**
     * Script arguments
     *
     * @var array
     */
    protected $_args = [];

    /**
     * Installer data model to store data between installations steps
     *
     * @var Mage_Install_Model_Installer_Data|Mage_Install_Model_Session|null
     */
    protected $_dataModel;

    /**
     * Current application
     *
     * @var Mage_Core_Model_App
     */
    protected $_app;

    /**
     * Get available options list
     *
     * @return array
     */
    protected function _getOptions()
    {
        if (is_null($this->_options)) {
            $this->_options = [
                'license_agreement_accepted'    => ['required' => true, 'comment' => ''],
                'locale'              => ['required' => true, 'comment' => ''],
                'timezone'            => ['required' => true, 'comment' => ''],
                'default_currency'    => ['required' => true, 'comment' => ''],
                'db_engine'           => ['comment' => ''],
                'db_host'             => ['required' => true, 'comment' => ''],
                'db_name'             => ['required' => true, 'comment' => ''],
                'db_user'             => ['required' => true, 'comment' => ''],
                'db_pass'             => ['comment' => ''],
                'db_prefix'           => ['comment' => ''],
                'url'                 => ['required' => true, 'comment' => ''],
                'use_secure'        => ['required' => true, 'comment' => ''],
                'secure_base_url'   => ['required' => true, 'comment' => ''],
                'use_secure_admin'  => ['required' => true, 'comment' => ''],
                'admin_lastname'    => ['required' => true, 'comment' => ''],
                'admin_firstname'   => ['required' => true, 'comment' => ''],
                'admin_email'       => ['required' => true, 'comment' => ''],
                'admin_username'    => ['required' => true, 'comment' => ''],
                'admin_password'    => ['required' => true, 'comment' => ''],
                'encryption_key'    => ['comment' => ''],
                'session_save'      => ['comment' => ''],
                'admin_frontname'   => ['comment' => ''],
            ];
        }
        return $this->_options;
    }

    /**
     * Set and validate arguments
     *
     * @param array $args
     * @return bool
     */
    public function setArgs($args = null)
    {
        if (empty($args)) {
            // take server args
            $args = $_SERVER['argv'];
        }

        /**
         * Parse arguments
         * Supports both --key value and --key=value formats
         */
        $currentArg = false;
        $match = false;
        foreach ($args as $arg) {
            if (preg_match('/^--([^=]+)=(.*)$/', $arg, $match)) {
                // argument with value in --key=value format
                $args[$match[1]] = $match[2];
                $currentArg = false;
            } elseif (preg_match('/^--(.*)$/', $arg, $match)) {
                // argument name
                $currentArg = $match[1];
                // in case if argument doesn't need a value
                $args[$currentArg] = true;
            } else {
                // argument value
                if ($currentArg) {
                    $args[$currentArg] = $arg;
                }
                $currentArg = false;
            }
        }

        if (isset($args['get_options'])) {
            $this->printOptions();
            return false;
        }

        /**
         * Check required arguments
         * Some parameters are not required for SQLite
         */
        $isSqlite = isset($args['db_engine']) && $args['db_engine'] === 'sqlite';
        $sqliteOptionalParams = ['db_host', 'db_user', 'use_secure', 'secure_base_url', 'use_secure_admin'];

        foreach ($this->_getOptions() as $name => $option) {
            // Skip validation for SQLite-optional parameters
            if ($isSqlite && in_array($name, $sqliteOptionalParams)) {
                continue;
            }
            if (isset($option['required']) && $option['required'] && !isset($args[$name])) {
                $error = 'ERROR: ' . 'You should provide the value for --' . $name . ' parameter';
                if (!empty($option['comment'])) {
                    $error .= ': ' . $option['comment'];
                }
                $this->addError($error);
            }
        }

        if ($this->hasErrors()) {
            return false;
        }

        /**
         * Validate license agreement acceptance
         */
        if (!$this->_checkFlag($args['license_agreement_accepted'])) {
            $this->addError(
                'ERROR: You have to accept Maho license agreement terms and conditions to continue installation',
            );
            return false;
        }

        /**
         * Set args values
         */
        foreach (array_keys($this->_getOptions()) as $name) {
            $this->_args[$name] = $args[$name] ?? '';
        }

        return true;
    }

    /**
     * Add error
     *
     * @param string $error
     * @return $this
     */
    public function addError($error)
    {
        $this->_getDataModel()->addError($error);
        return $this;
    }

    /**
     * Check if there were any errors
     *
     * @return bool
     */
    public function hasErrors()
    {
        return (count($this->_getDataModel()->getErrors()) > 0);
    }

    /**
     * Get all errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_getDataModel()->getErrors();
    }

    /**
     * Check flag value
     *
     * Returns true for 'yes', 1, 'true'
     * Case insensitive
     *
     * @param string $value
     * @return bool
     */
    protected function _checkFlag($value)
    {
        return ($value == 1)
            || preg_match('/^(yes|y|true)$/i', $value);
    }

    /**
     * Get data model (used to store data between installation steps
     *
     * @return Mage_Install_Model_Installer_Data
     */
    protected function _getDataModel()
    {
        if (is_null($this->_dataModel)) {
            $this->_dataModel = Mage::getModel('install/installer_data');
        }
        return $this->_dataModel;
    }

    /**
     * Init installation
     *
     * @return bool
     */
    public function init(Mage_Core_Model_App $app)
    {
        $this->_app = $app;
        $this->_getInstaller()->setDataModel($this->_getDataModel());

        /**
         * Check if already installed
         */
        if (Mage::isInstalled()) {
            $this->addError('ERROR: Maho is already installed');
            return false;
        }

        return true;
    }

    /**
     * Prepare data and save it in data model
     *
     * @return $this
     */
    protected function _prepareData()
    {
        /**
         * Locale settings
         */
        $this->_getDataModel()->setLocaleData([
            'locale'            => $this->_args['locale'],
            'timezone'          => $this->_args['timezone'],
            'currency'          => $this->_args['default_currency'],
        ]);

        /**
         * Database and web config
         */
        $this->_getDataModel()->setConfigData([
            'db_engine'           => $this->_args['db_engine'],
            'db_host'             => $this->_args['db_host'],
            'db_name'             => $this->_args['db_name'],
            'db_user'             => $this->_args['db_user'],
            'db_pass'             => $this->_args['db_pass'],
            'db_prefix'           => $this->_args['db_prefix'],
            'use_secure'          => $this->_checkFlag($this->_args['use_secure']),
            'unsecure_base_url'   => $this->_args['url'],
            'secure_base_url'     => $this->_args['secure_base_url'],
            'use_secure_admin'    => $this->_checkFlag($this->_args['use_secure_admin']),
            'session_save'        => $this->_checkSessionSave($this->_args['session_save']),
            'admin_frontname'     => $this->_checkAdminFrontname($this->_args['admin_frontname']),
        ]);

        /**
         * Primary admin user
         */
        $this->_getDataModel()->setAdminData([
            'firstname'         => $this->_args['admin_firstname'],
            'lastname'          => $this->_args['admin_lastname'],
            'email'             => $this->_args['admin_email'],
            'username'          => $this->_args['admin_username'],
            'new_password'      => $this->_args['admin_password'],
        ]);

        return $this;
    }

    /**
     * Install Magento
     *
     * @return bool
     */
    public function install()
    {
        try {
            /**
             * Check if already installed
             */
            if (Mage::isInstalled()) {
                $this->addError('ERROR: Maho is already installed');
                return false;
            }

            /**
             * Prepare data
             */
            $this->_prepareData();

            if ($this->hasErrors()) {
                return false;
            }

            $installer = $this->_getInstaller();

            /**
             * Install configuration
             */
            $installer->installConfig($this->_getDataModel()->getConfigData());

            // @phpstan-ignore if.alwaysFalse (defensive check - errors can be added by sub-components)
            if ($this->hasErrors()) {
                return false;
            }

            /**
             * Reinitialize configuration (to use new config data)
             */

            $this->_app->cleanCache();
            Mage::getConfig()->reinit();

            /**
             * Install database
             */
            $installer->installDb();

            // @phpstan-ignore if.alwaysFalse (defensive check - errors can be added by sub-components)
            if ($this->hasErrors()) {
                return false;
            }

            // apply data updates
            Mage_Core_Model_Resource_Setup::applyAllDataUpdates();
            Mage_Core_Model_Resource_Setup::applyAllMahoUpdates();

            /**
             * Validate entered data for administrator user
             */
            $user = $installer->validateAndPrepareAdministrator($this->_getDataModel()->getAdminData());

            // @phpstan-ignore if.alwaysFalse (defensive check - errors can be added by sub-components)
            if ($this->hasErrors()) {
                return false;
            }

            /**
             * Create primary administrator user
             */
            $installer->createAdministrator($user);

            // @phpstan-ignore if.alwaysFalse (defensive check - errors can be added by sub-components)
            if ($this->hasErrors()) {
                return false;
            }

            /**
             * Save encryption key or create if empty
             */
            $installer->installEnryptionKey();

            // @phpstan-ignore if.alwaysFalse (defensive check - errors can be added by sub-components)
            if ($this->hasErrors()) {
                return false;
            }

            /**
             * Installation finish
             */
            $installer->finish();

            // @phpstan-ignore if.alwaysFalse (defensive check - errors can be added by sub-components)
            if ($this->hasErrors()) {
                return false;
            }

            /**
             * Change directories mode to be writable by apache user
             */
            @chmod('var/cache', 0777);
            @chmod('var/session', 0777);
        } catch (Exception $e) {
            $this->addError('ERROR: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Print available currency, locale and timezone options
     *
     * @return $this
     */
    public function printOptions()
    {
        $options = [
            'locale'    => $this->_app->getLocale()->getOptionLocales(),
            'currency'  => $this->_app->getLocale()->getOptionCurrencies(),
            'timezone'  => $this->_app->getLocale()->getOptionTimezones(),
        ];
        var_export($options);
        return $this;
    }

    /**
     * Check if installer is run in shell, and redirect if run on web
     *
     * @param string $url fallback url to redirect to
     * @return bool
     */
    public function checkConsole($url = null)
    {
        if (defined('STDIN') && defined('STDOUT') && (defined('STDERR'))) {
            return true;
        }
        if (is_null($url)) {
            $url = preg_replace('/install\.php/i', '', Mage::getBaseUrl());
            $url = preg_replace('/\/\/$/', '/', $url);
        }
        header('Location: ' . $url);
        return false;
    }
}
