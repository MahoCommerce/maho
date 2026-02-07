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

class Mage_Install_WizardController extends Mage_Install_Controller_Action
{
    #[\Override]
    public function preDispatch()
    {
        if (Mage::isInstalled()) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            $this->_redirect('/');
            return;
        }
        $this->setFlag('', self::FLAG_NO_CHECK_INSTALLATION, true);
        parent::preDispatch();
    }

    protected function _getInstaller(): Mage_Install_Model_Installer
    {
        return Mage::getSingleton('install/installer');
    }

    protected function _getWizard(): Mage_Install_Model_Wizard
    {
        return Mage::getSingleton('install/wizard');
    }

    protected function _prepareLayout(): self
    {
        $this->loadLayout('install_wizard');
        $step = $this->_getWizard()->getStepByRequest($this->getRequest());
        if ($step) {
            $step->setActive(true);
        }

        $leftBlock = $this->getLayout()->createBlock('install/progress', 'install.progress');
        $this->getLayout()->getBlock('left')->append($leftBlock);
        return $this;
    }

    protected function _checkIfInstalled(): bool
    {
        if ($this->_getInstaller()->isApplicationInstalled()) {
            $this->getResponse()->setRedirect(Mage::getBaseUrl())->sendResponse();
            exit;
        }
        return true;
    }

    public function indexAction(): void
    {
        $this->_forward('license');
    }

    public function licenseAction(): void
    {
        $this->_checkIfInstalled();

        $this->setFlag('', self::FLAG_NO_DISPATCH_BLOCK_EVENT, true);
        $this->setFlag('', self::FLAG_NO_POST_DISPATCH, true);

        $this->_prepareLayout();
        $this->_initLayoutMessages('install/session');

        $this->getLayout()->getBlock('content')->append(
            $this->getLayout()->createBlock('install/license', 'install.license'),
        );

        $this->renderLayout();
    }

    public function licensePostAction(): void
    {
        $this->_checkIfInstalled();

        $agree = $this->getRequest()->getPost('agree');
        if ($agree && $step = $this->_getWizard()->getStepByName('license')) {
            $this->getResponse()->setRedirect($step->getNextUrl());
        } else {
            $this->_redirect('install');
        }
    }

    public function localeAction(): void
    {
        $this->_checkIfInstalled();
        $this->setFlag('', self::FLAG_NO_DISPATCH_BLOCK_EVENT, true);
        $this->setFlag('', self::FLAG_NO_POST_DISPATCH, true);

        $this->_prepareLayout();
        $this->_initLayoutMessages('install/session');
        $this->getLayout()->getBlock('content')->append(
            $this->getLayout()->createBlock('install/locale', 'install.locale'),
        );

        $this->renderLayout();
    }

    public function localeChangeAction(): void
    {
        $this->_checkIfInstalled();

        $locale = $this->getRequest()->getParam('locale');
        $timezone = $this->getRequest()->getParam('timezone');
        $currency = $this->getRequest()->getParam('currency');
        if ($locale) {
            Mage::getSingleton('install/session')->setLocale($locale);
            Mage::getSingleton('install/session')->setTimezone($timezone);
            Mage::getSingleton('install/session')->setCurrency($currency);
        }

        $this->_redirect('*/*/locale');
    }

    public function localePostAction(): void
    {
        $this->_checkIfInstalled();
        $step = $this->_getWizard()->getStepByName('locale');

        $session = Mage::getSingleton('install/session');

        if ($data = $this->getRequest()->getPost('configuration')) {
            $session->setLocaleData($data);
        }

        $localization = $this->getRequest()->getPost('localization');
        $session->setLocalizationData($localization ?: []);

        $this->getResponse()->setRedirect($step->getNextUrl());
    }

    public function configurationAction(): void
    {
        $this->_checkIfInstalled();
        $this->_getInstaller()->checkServer();

        $this->setFlag('', self::FLAG_NO_DISPATCH_BLOCK_EVENT, true);
        $this->setFlag('', self::FLAG_NO_POST_DISPATCH, true);

        if ($data = $this->getRequest()->getQuery('configuration')) {
            Mage::getSingleton('install/session')->setLocaleData($data);
        }

        $this->_prepareLayout();
        $this->_initLayoutMessages('install/session');
        $this->getLayout()->getBlock('content')->append(
            $this->getLayout()->createBlock('install/configuration', 'install.configuration'),
        );

        $this->renderLayout();
    }

    /**
     * @return Mage_Core_Controller_Varien_Action|void
     */
    public function configurationPostAction()
    {
        $this->_checkIfInstalled();
        $step = $this->_getWizard()->getStepByName('configuration');

        $config             = $this->getRequest()->getPost('config');
        $connectionConfig   = $this->getRequest()->getPost('connection');

        if ($config && $connectionConfig && isset($connectionConfig[$config['db_engine']])) {
            $config['unsecure_base_url'] = Mage::helper('core/url')->encodePunycode($config['unsecure_base_url']);
            $config['secure_base_url'] = Mage::helper('core/url')->encodePunycode($config['unsecure_base_url']);
            $data = array_merge($config, $connectionConfig[$config['db_engine']]);

            Mage::getSingleton('install/session')
                ->setConfigData($data);
            try {
                $this->_getInstaller()->installConfig($data);
                $this->_redirect('*/*/installDb');
                return $this;
            } catch (Exception $e) {
                Mage::getSingleton('install/session')->addError($e->getMessage());
                $this->getResponse()->setRedirect($step->getUrl());
            }
        }
        $this->getResponse()->setRedirect($step->getUrl());
    }

    public function installDbAction(): void
    {
        $this->_checkIfInstalled();
        $step = $this->_getWizard()->getStepByName('configuration');
        try {
            $this->_getInstaller()->installDb();
            /**
             * Clear session config data
             */
            Mage::getSingleton('install/session')->getConfigData(true);

            Mage::app()->getStore()->resetConfig();

            $this->getResponse()->setRedirect(Mage::getUrl($step->getNextUrlPath()));
        } catch (Exception $e) {
            Mage::getSingleton('install/session')->addError($e->getMessage());
            $this->getResponse()->setRedirect($step->getUrl());
        }
    }

    public function sampledataAction(): void
    {
        $this->_checkIfInstalled();

        // Clear any previous progress state when loading the page
        Mage::getSingleton('install/installer_sampleData')->clearProgress();

        $this->setFlag('', self::FLAG_NO_DISPATCH_BLOCK_EVENT, true);
        $this->setFlag('', self::FLAG_NO_POST_DISPATCH, true);

        $this->_prepareLayout();
        $this->_initLayoutMessages('install/session');

        $this->getLayout()->getBlock('content')->append(
            $this->getLayout()->createBlock('install/sampleData', 'install.sampledata'),
        );

        $this->renderLayout();
    }

    public function sampledataPostAction(): void
    {
        $this->_checkIfInstalled();

        /** @var Mage_Install_Model_Installer_SampleData $installer */
        $installer = Mage::getSingleton('install/installer_sampleData');

        // Clear any previous progress
        $installer->clearProgress();

        // Clear all output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Send response immediately with Connection: close header
        $json = Mage::helper('core')->jsonEncode(['success' => true]);
        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($json));
        header('Connection: close');
        echo $json;

        // Flush output to client
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            flush();
        }

        // Continue processing in background
        ignore_user_abort(true);
        set_time_limit(0);

        // Close session to allow progress polling requests to proceed
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Now run the installation
        // Errors are written to progress file by install() method
        try {
            $installer->install();
        } catch (Exception $e) {
            Mage::logException($e);
            // Error already written to progress file
        }
    }

    public function sampledataProgressAction(): void
    {
        $this->_checkIfInstalled();

        $this->getResponse()->setHeader('Content-Type', 'application/json', true);

        /** @var Mage_Install_Model_Installer_SampleData $installer */
        $installer = Mage::getSingleton('install/installer_sampleData');
        $progress = $installer->getProgress();

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($progress));
    }

    public function sampledataSkipAction(): void
    {
        $this->_checkIfInstalled();

        // Clear any progress file
        /** @var Mage_Install_Model_Installer_SampleData $installer */
        $installer = Mage::getSingleton('install/installer_sampleData');
        $installer->clearProgress();

        $step = $this->_getWizard()->getStepByName('sampledata');
        $this->getResponse()->setRedirect($step->getNextUrl());
    }

    /**
     * Reindex all - only available during installation
     */
    public function reindexAction(): void
    {
        $this->_checkIfInstalled();

        $this->getResponse()->setHeader('Content-Type', 'application/json', true);

        try {
            /** @var Mage_Index_Model_Resource_Process_Collection $indexCollection */
            $indexCollection = Mage::getResourceModel('index/process_collection');

            foreach ($indexCollection as $index) {
                /** @var Mage_Index_Model_Process $index */
                if ($index->isLocked()) {
                    $index->unlock();
                }
                $index->reindexEverything();
            }

            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(['success' => true]));
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode([
                'success' => false,
                'error' => $e->getMessage(),
            ]));
        }
    }

    /**
     * Flush cache - only available during installation
     */
    public function cacheflushAction(): void
    {
        $this->_checkIfInstalled();

        $this->getResponse()->setHeader('Content-Type', 'application/json', true);

        try {
            Mage::app()->getCache()->flush();

            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(['success' => true]));
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode([
                'success' => false,
                'error' => $e->getMessage(),
            ]));
        }
    }

    public function administratorAction(): void
    {
        $this->_checkIfInstalled();

        $this->_prepareLayout();
        $this->_initLayoutMessages('install/session');

        $this->getLayout()->getBlock('content')->append(
            $this->getLayout()->createBlock('install/administrator', 'install.administrator'),
        );
        $this->renderLayout();
    }

    /**
     * @return false|void
     */
    public function administratorPostAction()
    {
        $this->_checkIfInstalled();

        $step = Mage::getSingleton('install/wizard')->getStepByName('administrator');
        $adminData = $this->getRequest()->getPost('administrator');

        $errors = [];

        //preparing admin user model with data and validate it
        $user = $this->_getInstaller()->validateAndPrepareAdministrator($adminData);
        if (is_array($user)) {
            $errors = $user;
        }

        if (!empty($errors)) {
            Mage::getSingleton('install/session')->setAdminData($adminData);
            $this->getResponse()->setRedirect($step->getUrl());
            return false;
        }

        try {
            $this->_getInstaller()->createAdministrator($user);
            $this->_getInstaller()->installEnryptionKey();
        } catch (Exception $e) {
            Mage::getSingleton('install/session')
                ->setAdminData($adminData)
                ->addError($e->getMessage());
            $this->getResponse()->setRedirect($step->getUrl());
            return false;
        }
        $this->getResponse()->setRedirect($step->getNextUrl());
    }

    public function completeAction(): void
    {
        $this->_checkIfInstalled();

        $date = (string) Mage::getConfig()->getNode('global/install/date');
        if ($date !== Mage_Install_Model_Installer_Config::TMP_INSTALL_DATE_VALUE) {
            $this->_redirect('*/*');
            return;
        }

        $this->runLocalizationActions();

        $this->_getInstaller()->finish();

        $this->_prepareLayout();
        $this->_initLayoutMessages('install/session');

        $this->getLayout()->getBlock('content')->append(
            $this->getLayout()->createBlock('install/complete', 'install.complete'),
        );
        $this->renderLayout();
        Mage::getSingleton('install/session')->clear();
    }

    private function runLocalizationActions(): void
    {
        $session = Mage::getSingleton('install/session');
        $localization = $session->getLocalizationData();

        if (empty($localization)) {
            return;
        }

        $locale = (string) $session->getLocale();
        if (!$locale || $locale === 'en_US') {
            return;
        }

        $parsed = \Locale::parseLocale($locale);
        $countryCode = $parsed['region'] ?? null;

        if (!$countryCode) {
            return;
        }

        if (!empty($localization['import_regions'])) {
            try {
                $importer = new \MahoCLI\Commands\SysDirectoryRegionsImport();
                $result = $importer->importRegionsData($countryCode, [
                    'locales' => $locale,
                ]);

                if (!$result['success']) {
                    Mage::log('Failed to import regions: ' . ($result['error'] ?? 'Unknown error'), Mage::LOG_WARNING);
                }
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

    }

    public function checkHostAction(): void
    {
        $this->getResponse()->setHeader('Transfer-encoding', '', true);
        $this->getResponse()->setBody(Mage_Install_Model_Installer::INSTALLER_HOST_RESPONSE);
    }

    public function checkSecureHostAction(): void
    {
        $this->getResponse()->setHeader('Transfer-encoding', '', true);
        $this->getResponse()->setBody(Mage_Install_Model_Installer::INSTALLER_HOST_RESPONSE);
    }
}
