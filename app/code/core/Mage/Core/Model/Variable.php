<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

/**
 * @method Mage_Core_Model_Resource_Variable _getResource()
 * @method Mage_Core_Model_Resource_Variable getResource()
 */
class Mage_Core_Model_Variable extends Mage_Core_Model_Abstract
{
    public const string TYPE_TEXT = 'text';
    public const string TYPE_HTML = 'html';

    /**
     * A variable code starts with a letter and holds only letters, digits, underscores, hyphens and slashes.
     * The admin form checks the same rule with validate-xml-identifier.
     */
    public const string CODE_PATTERN = '/^[a-z][a-z0-9_\/-]*$/iD';

    protected ?int $_storeId = 0;

    /**
     * Internal Constructor
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_init('core/variable');
    }

    /**
     * Setter
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId($storeId)
    {
        $this->_storeId = $storeId;
        return $this;
    }

    /**
     * Getter
     *
     * @return int
     */
    public function getStoreId()
    {
        return $this->_storeId;
    }

    /**
     * Load variable by code
     *
     * @param string $code
     * @return $this
     */
    public function loadByCode($code)
    {
        $this->getResource()->loadByCode($this, $code);
        // isCodeAllowed() compares the code with the original data, as after load()
        $this->setOrigData();
        return $this;
    }

    /**
     * Return variable value depend on given type
     *
     * @param string $type
     * @return string
     */
    public function getValue($type = null)
    {
        $type ??= self::TYPE_HTML;
        if ($type == self::TYPE_TEXT || !(strlen((string) $this->getData('html_value')))) {
            $value = $this->getData('plain_value');
            //escape html if type is html, but html value is not defined
            if ($type == self::TYPE_HTML) {
                $value = nl2br(Mage::helper('core')->escapeHtml((string) $value));
            }
            return $value;
        }
        return $this->getData('html_value');
    }

    /**
     * Validation of object data. Checking for unique variable code
     *
     * @return bool | string
     */
    public function validate()
    {
        if ($this->getCode() && $this->getName()) {
            if (!$this->isCodeAllowed()) {
                return $this->getInvalidCodeMessage();
            }
            $variable = $this->getResource()->getVariableByCode($this->getCode());
            if (!empty($variable) && $variable['variable_id'] != $this->getId()) {
                return Mage::helper('core')->__('Variable Code must be unique.');
            }
            return true;
        }
        return Mage::helper('core')->__('Validation has failed.');
    }

    public static function isValidCode(mixed $code): bool
    {
        return is_string($code) && preg_match(self::CODE_PATTERN, $code) === 1;
    }

    /**
     * @throws Mage_Core_Exception_Input when the code is new or changed and is not a valid variable code
     */
    #[\Override]
    protected function _beforeSave()
    {
        if (!$this->isCodeAllowed()) {
            new Mage_Core_Exception_Input()->addError('code', $this->getInvalidCodeMessage())->throwIfErrors();
        }
        return parent::_beforeSave();
    }

    /**
     * Return true when the code is valid. An existing variable keeps a code that it has from before this rule,
     * when the code does not change.
     */
    protected function isCodeAllowed(): bool
    {
        if (self::isValidCode($this->getData('code'))) {
            return true;
        }
        return $this->getId() !== null
            && $this->getOrigData('code') !== null
            && (string) $this->getOrigData('code') === (string) $this->getData('code');
    }

    protected function getInvalidCodeMessage(): string
    {
        return Mage::helper('core')->__(
            'Variable Code must start with a letter and contain only letters, digits, underscores, hyphens and slashes.',
        );
    }

    /**
     * Retrieve variables option array
     *
     * @param bool $withGroup
     * @return array
     */
    public function getVariablesOptionArray($withGroup = false)
    {
        /** @var Mage_Core_Model_Resource_Variable_Collection $collection */
        $collection = $this->getCollection();
        $variables = [];
        foreach ($collection->toOptionArray() as $variable) {
            $variables[] = [
                'value' => '{{customVar code=' . $variable['value'] . '}}',
                'label' => Mage::helper('core')->escapeHtml($variable['label']),
            ];
        }
        if ($withGroup && $variables) {
            $variables = [
                'label' => Mage::helper('core')->__('Custom Variables'),
                'value' => $variables,
            ];
        }
        return $variables;
    }

    public function getCode(): ?string
    {
        $value = $this->getData('code');
        return $value === null ? null : (string) $value;
    }

    public function setCode(?string $value): static
    {
        return $this->setData('code', $value);
    }

    public function getHtmlValue(): ?string
    {
        $value = $this->getData('html_value');
        return $value === null ? null : (string) $value;
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

    public function getPlainValue(): ?string
    {
        $value = $this->getData('plain_value');
        return $value === null ? null : (string) $value;
    }

    public function getUseDefaultValue(): ?bool
    {
        $value = $this->getData('use_default_value');
        return $value === null ? null : (bool) $value;
    }

}
