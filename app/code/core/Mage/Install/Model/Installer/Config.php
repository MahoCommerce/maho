<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Install_Model_Installer_Config extends Mage_Install_Model_Installer_Abstract
{
    public const TMP_INSTALL_DATE_VALUE = 'd-d-d-d-d';
    public const TMP_ENCRYPT_KEY_VALUE = 'k-k-k-k-k';

    /**
     * Path to local configuration file
     */
    protected string $_localConfigFile;

    /** @var array<string, mixed> */
    protected array $_configData = [];

    public function __construct()
    {
        $this->_localConfigFile = Mage::getBaseDir('etc') . DS . 'local.xml';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setConfigData(array $data): self
    {
        $this->_configData = $data;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigData(): array
    {
        return $this->_configData;
    }

    public function install(): void
    {
        $data = $this->getConfigData();
        foreach (Mage::getModel('core/config')->getDistroServerVars() as $index => $value) {
            if (!isset($data[$index])) {
                $data[$index] = $value;
            }
        }

        if (isset($data['unsecure_base_url'])) {
            $data['unsecure_base_url'] .= str_ends_with($data['unsecure_base_url'], '/') ? '' : '/';
            if (!str_starts_with($data['unsecure_base_url'], 'http')) {
                $data['unsecure_base_url'] = 'http://' . $data['unsecure_base_url'];
            }
        }
        if (isset($data['secure_base_url'])) {
            $data['secure_base_url'] .= str_ends_with($data['secure_base_url'], '/') ? '' : '/';
            if (!str_starts_with($data['secure_base_url'], 'http')) {
                $data['secure_base_url'] = 'https://' . $data['secure_base_url'];
            }
        }

        $data['date']   = self::TMP_INSTALL_DATE_VALUE;
        $data['key']    = self::TMP_ENCRYPT_KEY_VALUE;
        $data['var_dir'] = $data['root_dir'] . '/var';

        $data['use_script_name'] = isset($data['use_script_name']) ? 'true' : 'false';

        $this->_getInstaller()->getDataModel()->setConfigData($data);

        $template = file_get_contents(Maho::findFile(Mage::getBaseDir('etc') . DS . 'local.xml.template'));
        foreach ($data as $index => $value) {
            $template = str_replace('{{' . $index . '}}', '<![CDATA[' . $value . ']]>', $template);
        }

        $localXmlDir = dirname($this->_localConfigFile);
        if (!file_exists($localXmlDir)) {
            if (!mkdir($localXmlDir, 0777, true)) {
                Mage::throwException("Error creating $localXmlDir folder.");
            }
        }
        if (!is_writable($localXmlDir)) {
            Mage::throwException("$localXmlDir is not writable.");
        }

        file_put_contents($this->_localConfigFile, $template);
        chmod($this->_localConfigFile, 0777);
    }

    public function getFormData(): Maho\DataObject
    {
        $baseUrl = Mage::helper('core/url')->decodePunycode(Mage::getBaseUrl('web'));
        $urlData = parse_url($baseUrl);
        if (!isset($urlData['scheme'])) {
            $urlData['scheme'] = $_SERVER['REQUEST_SCHEME'] ?? 'https';
        }

        $baseUrl = Mage::helper('core/url')->buildUrl($urlData);
        $baseSecureUrl = str_replace('http://', 'https://', $baseUrl);
        $connectDefault = Mage::getConfig()
                ->getResourceConnectionConfig(Mage_Core_Model_Resource::DEFAULT_SETUP_RESOURCE);

        return Mage::getModel('varien/object')
            ->setDbHost($connectDefault->host)
            ->setDbName($connectDefault->dbname)
            ->setDbUser($connectDefault->username)
            ->setDbModel($connectDefault->model)
            ->setDbPass('')
            ->setSecureBaseUrl($baseSecureUrl)
            ->setUnsecureBaseUrl($baseUrl)
            ->setAdminFrontname('admin')
            ->setEnableCharts('1');
    }

    /**
     * @param array $data
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function _checkHostsInfo($data)
    {
        $url  = $data['protocol'] . '://' . $data['host'] . ':' . $data['port'] . $data['base_path'];
        $surl = $data['secure_protocol'] . '://' . $data['secure_host'] . ':' . $data['secure_port']
            . $data['secure_base_path'];

        $this->_checkUrl($url);
        $this->_checkUrl($surl, true);

        return $this;
    }

    /**
     * @param string $url
     * @param bool $secure
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function _checkUrl($url, $secure = false)
    {
        $prefix = $secure ? 'install/wizard/checkSecureHost/' : 'install/wizard/checkHost/';
        try {
            $client = \Maho\Http\Client::create();
            $response = $client->request('GET', $url . 'index.php/' . $prefix);
            $body = $response->getContent();
        } catch (Exception $e) {
            $this->_getInstaller()->getDataModel()
                ->addError(Mage::helper('install')->__('The URL "%s" is not accessible.', $url));
            throw $e;
        }

        if ($body != Mage_Install_Model_Installer::INSTALLER_HOST_RESPONSE) {
            $this->_getInstaller()->getDataModel()
                ->addError(Mage::helper('install')->__('The URL "%s" is invalid.', $url));
            Mage::throwException(Mage::helper('install')->__('Response from server isn\'t valid.'));
        }
        return $this;
    }

    public function replaceTmpInstallDate(?string $date = null): self
    {
        $stamp    = strtotime((string) $date);
        $localXml = file_get_contents($this->_localConfigFile);
        $localXml = str_replace(self::TMP_INSTALL_DATE_VALUE, date('r', $stamp ?: time()), $localXml);
        file_put_contents($this->_localConfigFile, $localXml);

        return $this;
    }

    public function replaceTmpEncryptKey(): self
    {
        $key = Mage::generateEncryptionKeyAsHex();
        $localXml = file_get_contents($this->_localConfigFile);
        $localXml = str_replace(self::TMP_ENCRYPT_KEY_VALUE, $key, $localXml);
        $localXml = str_replace('{{key_date}}', date(Mage_Core_Model_Locale::DATE_FORMAT), $localXml);
        file_put_contents($this->_localConfigFile, $localXml);

        return $this;
    }
}
