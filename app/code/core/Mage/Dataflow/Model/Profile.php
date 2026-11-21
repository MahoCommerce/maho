<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

/**
 * Convert profile
 *
 * @package    Mage_Dataflow
 *
 * @method Mage_Dataflow_Model_Resource_Profile _getResource()
 * @method Mage_Dataflow_Model_Resource_Profile getResource()
 */
class Mage_Dataflow_Model_Profile extends Mage_Core_Model_Abstract
{
    public const DEFAULT_EXPORT_PATH = 'var/export';
    public const DEFAULT_EXPORT_FILENAME = 'export_';

    /**
     * The entity types of the profile wizard, with the adapter of each.
     */
    public const GUI_ADAPTERS = [
        'product' => 'catalog/convert_adapter_product',
        'customer' => 'customer/convert_adapter_customer',
    ];

    /**
     * Product table permanent attributes
     *
     * @var array
     */
    protected $_productTablePermanentAttributes = ['sku'];

    /**
     * Customer table permanent attributes
     *
     * @var array
     */
    protected $_customerTablePermanentAttributes = ['email', 'website'];

    #[\Override]
    protected function _construct()
    {
        $this->_init('dataflow/profile');
    }

    #[\Override]
    protected function _afterLoad()
    {
        $guiData = '';
        $storedGuiData = $this->getGuiData();
        if (is_string($storedGuiData) && $storedGuiData !== '') {
            try {
                $guiData = Mage::helper('core/unserializeArray')
                    ->unserialize($storedGuiData);
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
        $this->setGuiData($guiData);

        return parent::_afterLoad();
    }

    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();
        $actionsXML = $this->getData('actions_xml');
        if ($actionsXML !== null && (string) $actionsXML !== ''
            && @simplexml_load_string('<data>' . $actionsXML . '</data>', null, LIBXML_NOERROR) === false
        ) {
            Mage::throwException(Mage::helper('dataflow')->__('Actions XML is not valid.'));
        }

        if (is_array($this->getGuiData())) {
            $data = $this->getData();
            $guiData = $this->getGuiData();
            $charSingleList = ['\\', '/', '.', '!', '@', '#', '$', '%', '&', '*', '~', '^'];
            if (isset($guiData['file']['type']) && $guiData['file']['type'] == 'file') {
                if (empty($guiData['file']['path'])
                    || (strlen($guiData['file']['path']) == 1
                    && in_array($guiData['file']['path'], $charSingleList))
                ) {
                    $guiData['file']['path'] = self::DEFAULT_EXPORT_PATH;
                }
                if (empty($guiData['file']['filename'])) {
                    $guiData['file']['filename'] = self::DEFAULT_EXPORT_FILENAME . $data['entity_type']
                        . '.' . ($guiData['parse']['type'] == 'csv' ? $guiData['parse']['type'] : 'xml');
                }

                if (Mage::helper('dataflow')->getStorageLocation((string) $guiData['file']['path']) === null) {
                    Mage::throwException(
                        Mage::helper('dataflow')->__('Path "%s" is not allowed. Files must be in var/export or var/import.', $guiData['file']['path']),
                    );
                }

                $this->setGuiData($guiData);
            }
            $this->_parseGuiData();

            $this->setGuiData(serialize($this->getGuiData()));
        }

        if ($this->_getResource()->isProfileExists($this->getName(), $this->getId())) {
            Mage::throwException(Mage::helper('dataflow')->__('Profile with the same name already exists.'));
        }
        return $this;
    }

    #[\Override]
    protected function _afterSave()
    {
        if ($this->getGuiData() && is_string($this->getGuiData())) {
            try {
                $guiData = Mage::helper('core/unserializeArray')
                    ->unserialize($this->getGuiData());
                $this->setGuiData($guiData);
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        $profileHistory = Mage::getModel('dataflow/profile_history');

        $adminUserId = $this->getAdminUserId();
        if ($adminUserId) {
            $profileHistory->setUserId($adminUserId);
        }

        $profileHistory
            ->setProfileId((int) $this->getId())
            ->setActionCode($this->getOrigData('profile_id') ? 'update' : 'create')
            ->save();
        $csvParser = new \Maho\File\Csv();
        $delimiter = trim($this->getData('gui_data/parse/delimiter') ?? '');
        if ($delimiter) {
            $csvParser->setDelimiter($delimiter);
        }
        $xmlParser = new DOMDocument();
        $newUploadedFilenames = [];
        $helper = Mage::helper('dataflow');

        if (isset($_FILES['file_1']['tmp_name']) || isset($_FILES['file_2']['tmp_name'])
            || isset($_FILES['file_3']['tmp_name'])
        ) {
            for ($index = 0; $index < 3; $index++) {
                if ($_FILES['file_' . ($index + 1)]['tmp_name']) {
                    $uploader = $this->_createUploader('file_' . ($index + 1));
                    $uploader->setAllowedExtensions(['csv','xml']);
                    $path = Mage::getBaseDir('tmp') . '/';
                    $uploader->save($path);
                    $uploadFile = $uploader->getUploadedFileName();

                    if ($this->_isCsvUpload($uploadFile)) {
                        $fileData = $csvParser->getData($path . $uploadFile);
                        $fileData = array_shift($fileData);
                    } else {
                        try {
                            $xmlParser->loadXML(file_get_contents($path . $uploadFile));
                            $cells = $this->getNode($xmlParser, 'Worksheet')->item(0);
                            $cells = $this->getNode($cells, 'Row')->item(0);
                            $cells = $this->getNode($cells, 'Cell');
                            $fileData = [];
                            foreach ($cells as $cell) {
                                $fileData[] = $this->getNode($cell, 'Data')->item(0)->nodeValue;
                            }
                        } catch (Exception) {
                            foreach ($newUploadedFilenames as $v) {
                                $helper->getUploadMount()->delete((string) $helper->getUploadPath($v));
                            }
                            unlink($path . $uploadFile);
                            Mage::throwException(
                                Mage::helper('Dataflow')->__(
                                    'Upload failed. Wrong data format in file: %s.',
                                    $uploadFile,
                                ),
                            );
                        }
                    }

                    if ($this->_data['entity_type'] == 'customer') {
                        $attributes = $this->_customerTablePermanentAttributes;
                    } else {
                        $attributes = $this->_productTablePermanentAttributes;
                    }
                    $colsAbsent = array_diff($attributes, $fileData);
                    if ($colsAbsent) {
                        foreach ($newUploadedFilenames as $v) {
                            $helper->getUploadMount()->delete((string) $helper->getUploadPath($v));
                        }
                        unlink($path . $uploadFile);
                        Mage::throwException(
                            Mage::helper('Dataflow')->__(
                                'Upload failed. Can not find required columns: %s in file %s.',
                                implode(', ', $colsAbsent),
                                $uploadFile,
                            ),
                        );
                    }
                    if ($uploadFile) {
                        $newFilename = 'import-' . date('YmdHis') . '-' . ($index + 1) . '_' . $uploadFile;
                        rename($path . $uploadFile, $path . $newFilename);
                        $newUploadedFilenames[] = $newFilename;
                    }
                }
                //BOM deleting for UTF files
                if (isset($path, $newFilename)) {
                    $contents = file_get_contents($path . $newFilename);
                    if (ord($contents[0]) == 0xEF && ord($contents[1]) == 0xBB && ord($contents[2]) == 0xBF) {
                        $contents = substr($contents, 3);
                        file_put_contents($path . $newFilename, $contents);
                    }
                    unset($contents);
                    $helper->storeUpload($path . $newFilename, $newFilename);
                    unset($newFilename);
                }
            }
        }
        parent::_afterSave();
        return $this;
    }

    protected function _isCsvUpload(string $uploadFile): bool
    {
        return strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION)) === 'csv';
    }

    protected function _createUploader(string $fieldName): Mage_Core_Model_File_Uploader
    {
        return Mage::getModel('core/file_uploader', $fieldName);
    }

    /**
     * Run profile
     *
     * @return $this
     */
    public function run()
    {
        /**
         * Save history
         */
        Mage::getModel('dataflow/profile_history')
            ->setProfileId($this->getId())
            ->setActionCode('run')
            ->setUserId($this->getAdminUserId())
            ->save();

        /**
         * Prepare xml convert profile actions data
         */
        $xml = '<convert version="1.0"><profile name="default">' . $this->getActionsXml()
             . '</profile></convert>';
        $profile = Mage::getModel('core/convert')
            ->importXml($xml)
            ->getProfile('default');
        /** @var Mage_Dataflow_Model_Convert_Profile $profile */

        try {
            $batch = Mage::getSingleton('dataflow/batch')
                ->setProfileId($this->getId())
                ->setStoreId($this->getStoreId())
                ->save();
            $this->setBatchId($batch->getId());

            $profile->setDataflowProfile($this->getData());
            $profile->run();
        } catch (Exception $e) {
            Mage::logException($e);
            $exception = new Mage_Dataflow_Model_Convert_Exception($e->getMessage());
            $exception->setLevel(Mage_Dataflow_Model_Convert_Exception::FATAL);
            $profile->addException($exception);
        }

        $this->setExceptions($profile->getExceptions());
        return $this;
    }

    public function _parseGuiData(): static
    {
        $nl = "\r\n";
        $import = $this->getDirection() === 'import';
        $p = $this->getGuiData();
        if (!is_string($this->getEntityType()) || !isset(self::GUI_ADAPTERS[$this->getEntityType()])) {
            Mage::throwException(Mage::helper('dataflow')->__('Invalid entity type.'));
        }

        if ($this->getDataTransfer() === 'interactive') {
            $interactiveXml = '<action type="dataflow/convert_adapter_http" method="'
                . ($import ? 'load' : 'save') . '">' . $nl;
            $interactiveXml .= '</action>';

            $fileXml = '';
        } else {
            $interactiveXml = '';

            $fileXml = '<action type="dataflow/convert_adapter_io" method="'
                . ($import ? 'load' : 'save') . '">' . $nl;
            if (!in_array($p['file']['type'] ?? null, ['file', 'ftp'], true)) {
                Mage::throwException(Mage::helper('dataflow')->__('Invalid file type.'));
            }

            $fileXml .= '    <var name="type">' . $p['file']['type'] . '</var>' . $nl;
            $fileXml .= '    <var name="path">' . $this->_escapeGuiXml($p['file']['path'] ?? '') . '</var>' . $nl;
            $fileXml .= '    <var name="filename"><![CDATA[' . $this->_escapeGuiCdata($p['file']['filename'] ?? '') . ']]></var>' . $nl;
            if ($p['file']['type'] === 'ftp') {
                $hostArr = explode(':', is_scalar($p['file']['host'] ?? null) ? (string) $p['file']['host'] : '');
                $fileXml .= '    <var name="host"><![CDATA[' . $this->_escapeGuiCdata($hostArr[0]) . ']]></var>' . $nl;
                if (isset($hostArr[1])) {
                    $fileXml .= '    <var name="port"><![CDATA[' . $this->_escapeGuiCdata($hostArr[1]) . ']]></var>' . $nl;
                }
                if (!empty($p['file']['passive'])) {
                    $fileXml .= '    <var name="passive">true</var>' . $nl;
                }
                if ((!empty($p['file']['file_mode']))
                        && ($p['file']['file_mode'] == FTP_ASCII || $p['file']['file_mode'] == FTP_BINARY)
                ) {
                    $fileXml .= '    <var name="file_mode">' . (int) $p['file']['file_mode'] . '</var>' . $nl;
                }
                if (!empty($p['file']['user'])) {
                    $fileXml .= '    <var name="user"><![CDATA[' . $this->_escapeGuiCdata($p['file']['user']) . ']]></var>' . $nl;
                }
                if (!empty($p['file']['password'])) {
                    $fileXml .= '    <var name="password"><![CDATA[' . $this->_escapeGuiCdata($p['file']['password']) . ']]></var>' . $nl;
                }
            }
            if ($import) {
                $fileXml .= '    <var name="format"><![CDATA[' . $this->_escapeGuiCdata($p['parse']['type'] ?? '') . ']]></var>' . $nl;
            }
            $fileXml .= '</action>' . $nl . $nl;
        }

        switch ($p['parse']['type'] ?? null) {
            case 'excel_xml':
                $parseFileXml = '<action type="dataflow/convert_parser_xml_excel" method="'
                    . ($import ? 'parse' : 'unparse') . '">' . $nl;
                $parseFileXml .= '    <var name="single_sheet"><![CDATA['
                    . $this->_escapeGuiCdata($p['parse']['single_sheet'] ?? '')
                    . ']]></var>' . $nl;
                break;

            case 'csv':
                $parseFileXml = '<action type="dataflow/convert_parser_csv" method="'
                    . ($import ? 'parse' : 'unparse') . '">' . $nl;
                $parseFileXml .= '    <var name="delimiter"><![CDATA['
                    . $this->_escapeGuiCdata($p['parse']['delimiter'] ?? '') . ']]></var>' . $nl;
                $parseFileXml .= '    <var name="enclose"><![CDATA['
                    . $this->_escapeGuiCdata($p['parse']['enclose'] ?? '') . ']]></var>' . $nl;
                break;

            default:
                Mage::throwException(Mage::helper('dataflow')->__('Invalid file format.'));
        }
        $parseFileXml .= '    <var name="fieldnames">' . $this->_escapeGuiXml($p['parse']['fieldnames'] ?? '') . '</var>' . $nl;
        $parseFileXmlInter = $parseFileXml;
        $parseFileXml .= '</action>' . $nl . $nl;

        $mapXml = '';

        if (isset($p['map']) && is_array($p['map'])) {
            foreach ($p['map'] as $side => $fields) {
                if (!is_array($fields)) {
                    continue;
                }
                foreach ($fields['db'] as $i => $k) {
                    if ($k == '' || $k == '0') {
                        unset($p['map'][$side]['db'][$i]);
                        unset($p['map'][$side]['file'][$i]);
                    }
                }
            }
        }
        $mapXml .= '<action type="dataflow/convert_mapper_column" method="map">' . $nl;
        $map = $p['map'][$this->getEntityType()];
        if (count($map['db'])) {
            $from = $map[$import ? 'file' : 'db'];
            $to = $map[$import ? 'db' : 'file'];
            $mapXml .= '    <var name="map">' . $nl;
            $parseFileXmlInter .= '    <var name="map">' . $nl;
            foreach ($from as $i => $f) {
                $mapEntry = '        <map name="' . $this->_escapeGuiXml($f) . '"><![CDATA['
                    . $this->_escapeGuiCdata($to[$i] ?? '') . ']]></map>' . $nl;
                $mapXml .= $mapEntry;
                $parseFileXmlInter .= $mapEntry;
            }
            $mapXml .= '    </var>' . $nl;
            $parseFileXmlInter .= '    </var>' . $nl;
        }
        if (!empty($p['map']['only_specified'])) {
            $onlySpecified = $this->_escapeGuiXml($p['map']['only_specified']);
            $mapXml .= '    <var name="_only_specified">' . $onlySpecified . '</var>' . $nl;
            $parseFileXmlInter .= '    <var name="_only_specified">' . $onlySpecified . '</var>' . $nl;
        }
        $mapXml .= '</action>' . $nl . $nl;

        $parsers = [
            'product' => 'catalog/convert_parser_product',
            'customer' => 'customer/convert_parser_customer',
        ];

        $storeId = (int) $this->getStoreId();
        if ($import) {
            $parseFileXmlInter .= '    <var name="store"><![CDATA[' . $storeId . ']]></var>' . $nl;
        } else {
            $parseDataXml = '<action type="' . $parsers[$this->getEntityType()] . '" method="unparse">' . $nl;
            $parseDataXml .= '    <var name="store"><![CDATA[' . $storeId . ']]></var>' . $nl;
            if (isset($p['export']['add_url_field'])) {
                $parseDataXml .= '    <var name="url_field"><![CDATA['
                    . $this->_escapeGuiCdata($p['export']['add_url_field']) . ']]></var>' . $nl;
            }
            $parseDataXml .= '</action>' . $nl . $nl;
        }

        $adapters = self::GUI_ADAPTERS;

        if ($import) {
            $entityXml = '<action type="' . $adapters[$this->getEntityType()] . '" method="save">' . $nl;
            $entityXml .= '    <var name="store"><![CDATA[' . $storeId . ']]></var>' . $nl;
            $entityXml .= '</action>' . $nl . $nl;
        } else {
            $entityXml = '<action type="' . $adapters[$this->getEntityType()] . '" method="load">' . $nl;
            $entityXml .= '    <var name="store"><![CDATA[' . $storeId . ']]></var>' . $nl;
            foreach ((array) ($p[$this->getEntityType()]['filter'] ?? []) as $f => $v) {
                if (empty($v)) {
                    continue;
                }
                if (is_scalar($v)) {
                    $filterXml = '    <var name="filter/' . $this->_escapeGuiXml($f) . '"><![CDATA['
                        . $this->_escapeGuiCdata($v) . ']]></var>' . $nl;
                    $entityXml .= $filterXml;
                    $parseFileXmlInter .= $filterXml;
                } elseif (is_array($v)) {
                    foreach ($v as $a => $b) {
                        if (!is_scalar($b) || (string) $b === '') {
                            continue;
                        }
                        $filterXml = '    <var name="filter/' . $this->_escapeGuiXml($f) . '/' . $this->_escapeGuiXml($a)
                            . '"><![CDATA[' . $this->_escapeGuiCdata($b) . ']]></var>' . $nl;
                        $entityXml .= $filterXml;
                        $parseFileXmlInter .= $filterXml;
                    }
                }
            }
            $entityXml .= '</action>' . $nl . $nl;
        }

        // Need to rewrite the whole xml action format
        if ($import) {
            $numberOfRecords = $p['import']['number_of_records'] ?? 1;
            $decimalSeparator = $p['import']['decimal_separator'] ?? ' . ';
            $parseFileXmlInter .= '    <var name="number_of_records">'
                . $this->_escapeGuiXml($numberOfRecords) . '</var>' . $nl;
            $parseFileXmlInter .= '    <var name="decimal_separator"><![CDATA['
                . $this->_escapeGuiCdata($decimalSeparator) . ']]></var>' . $nl;
            if ($this->getDataTransfer() === 'interactive') {
                $xml = $parseFileXmlInter;
                $xml .= '    <var name="adapter">' . $adapters[$this->getEntityType()] . '</var>' . $nl;
                $xml .= '    <var name="method">parse</var>' . $nl;
                $xml .= '</action>';
            } else {
                $xml = $fileXml;
                $xml .= $parseFileXmlInter;
                $xml .= '    <var name="adapter">' . $adapters[$this->getEntityType()] . '</var>' . $nl;
                $xml .= '    <var name="method">parse</var>' . $nl;
                $xml .= '</action>';
            }
        } else {
            $xml = $entityXml . $parseDataXml . $mapXml . $parseFileXml . $fileXml . $interactiveXml;
        }

        $this->setGuiData($p);
        $this->setActionsXml($xml);
        return $this;
    }

    /**
     * Escape a value of the profile wizard for the content of an XML element or for an XML attribute in double quotes.
     */
    protected function _escapeGuiXml(mixed $value): string
    {
        return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escape a value of the profile wizard for a CDATA section. A "]]>" in the value closes the section.
     */
    protected function _escapeGuiCdata(mixed $value): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', is_scalar($value) ? (string) $value : '');
    }

    /**
     * Get node from xml object
     *
     * @param object $xmlObject
     * @param string $nodeName
     * @return object
     * @throws Exception
     */
    protected function getNode($xmlObject, $nodeName)
    {
        if ($xmlObject != null) {
            return $xmlObject->getElementsByTagName($nodeName);
        }
        Mage::throwException(Mage::helper('Dataflow')->__('Invalid node.'));
    }

    public function getActionsXml(): ?string
    {
        $value = $this->getData('actions_xml');
        return $value === null ? null : (string) $value;
    }

    public function setActionsXml(?string $value): static
    {
        return $this->setData('actions_xml', $value);
    }

    public function getAdminUserId(): ?int
    {
        $value = $this->getData('admin_user_id');
        return $value === null ? null : (int) $value;
    }

    public function setAdminUserId(?int $value): static
    {
        return $this->setData('admin_user_id', $value);
    }

    public function getDataTransfer(): ?string
    {
        $value = $this->getData('data_transfer');
        return $value === null ? null : (string) $value;
    }

    public function setDataTransfer(?string $value): static
    {
        return $this->setData('data_transfer', $value);
    }

    public function getDirection(): ?string
    {
        $value = $this->getData('direction');
        return $value === null ? null : (string) $value;
    }

    public function setDirection(?string $value): static
    {
        return $this->setData('direction', $value);
    }

    public function getEntityType(): ?string
    {
        $value = $this->getData('entity_type');
        return $value === null ? null : (string) $value;
    }

    public function setEntityType(?string $value): static
    {
        return $this->setData('entity_type', $value);
    }

    public function getGuiData(): array|string|null
    {
        return $this->getData('gui_data');
    }

    public function setGuiData(array|string|null $value): static
    {
        return $this->setData('gui_data', $value);
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

}
