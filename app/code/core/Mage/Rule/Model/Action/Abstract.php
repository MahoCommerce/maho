<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rule
 */

declare(strict_types=1);

abstract class Mage_Rule_Model_Action_Abstract extends \Maho\DataObject implements Mage_Rule_Model_Action_Interface
{
    /**
     * Flag to enable translation for loadOperatorOptions/loadValueOptions/loadAggregatorOptions/getDefaultOperatorOptions
     * It's useless to translate these data on frontend
     *
     * @var bool
     */
    protected static $translate;

    public function __construct()
    {
        if (!is_bool(static::$translate)) {
            static::$translate = Mage::app()->getStore()->isAdmin();
        }

        parent::__construct();
        $this->loadAttributeOptions()->loadOperatorOptions()->loadValueOptions();

        foreach (array_keys($this->getAttributeOption()) as $attr) {
            $this->setAttribute($attr);
            break;
        }
        foreach (array_keys($this->getOperatorOption()) as $operator) {
            $this->setOperator($operator);
            break;
        }
    }

    /**
     * @return \Maho\Data\Form
     */
    public function getForm()
    {
        return $this->getRule()->getForm();
    }

    /**
     * @return array
     */
    public function asArray(array $arrAttributes = [])
    {
        return [
            'type' => $this->getType(),
            'attribute' => $this->getAttribute(),
            'operator' => $this->getOperator(),
            'value' => $this->getValue(),
        ];
    }

    /**
     * @return string
     */
    public function asXml()
    {
        return '<type>' . $this->getType() . '</type>'
            . '<attribute>' . $this->getAttribute() . '</attribute>'
            . '<operator>' . $this->getOperator() . '</operator>'
            . '<value>' . $this->getValue() . '</value>';
    }

    /**
     * @return $this
     */
    public function loadArray(array $arr)
    {
        $this->addData([
            'type' => $arr['type'],
            'attribute' => $arr['attribute'],
            'operator' => $arr['operator'],
            'value' => $arr['value'],
        ]);
        $this->loadAttributeOptions();
        $this->loadOperatorOptions();
        $this->loadValueOptions();
        return $this;
    }

    /**
     * @return $this
     */
    public function loadAttributeOptions()
    {
        $this->setAttributeOption([]);
        return $this;
    }

    /**
     * @return array
     */
    public function getAttributeSelectOptions()
    {
        $opt = [];
        foreach ($this->getAttributeOption() as $k => $v) {
            $opt[] = ['value' => $k, 'label' => $v];
        }
        return $opt;
    }

    /**
     * @return mixed
     */
    public function getAttributeName()
    {
        return $this->getAttributeOption()[$this->getAttribute()] ?? null;
    }

    /**
     * @return $this
     */
    public function loadOperatorOptions()
    {
        $this->setOperatorOption([
            '='  => static::$translate ? Mage::helper('rule')->__('to') : 'to',
            '+=' => static::$translate ? Mage::helper('rule')->__('by') : 'by',
        ]);
        return $this;
    }

    /**
     * @return array
     */
    public function getOperatorSelectOptions()
    {
        $opt = [];
        foreach ($this->getOperatorOption() as $k => $v) {
            $opt[] = ['value' => $k, 'label' => $v];
        }
        return $opt;
    }

    /**
     * @return array
     */
    public function getOperatorName()
    {
        return $this->getOperatorOption()[$this->getOperator()] ?? null;
    }

    /**
     * @return $this
     */
    public function loadValueOptions()
    {
        $this->setValueOption([]);
        return $this;
    }

    /**
     * @return array
     */
    public function getValueSelectOptions()
    {
        $opt = [];
        foreach ($this->getValueOption() as $k => $v) {
            $opt[] = ['value' => $k, 'label' => $v];
        }
        return $opt;
    }

    /**
     * @return string
     */
    public function getValueName()
    {
        $value = $this->getValue();
        return !empty($value) || (string) $value === '0' ? $value : '...';
    }

    /**
     * @return array
     */
    public function getNewChildSelectOptions()
    {
        return [
            ['value' => '', 'label' => Mage::helper('rule')->__('Please choose an action to add...')],
        ];
    }

    /**
     * @return string
     */
    public function getNewChildName()
    {
        return $this->getAddLinkHtml();
    }

    /**
     * @return string
     */
    public function asHtml()
    {
        return '';
    }

    /**
     * @return string
     */
    public function asHtmlRecursive()
    {
        return $this->asHtml();
    }

    /**
     * @return \Maho\Data\Form\Element\AbstractElement
     */
    public function getTypeElement()
    {
        return $this->getForm()->addField('action:' . $this->getId() . ':type', 'hidden', [
            'name' => 'rule[actions][' . $this->getId() . '][type]',
            'value' => $this->getType(),
            'no_span' => true,
        ]);
    }

    /**
     * @return \Maho\Data\Form\Element\AbstractElement
     */
    public function getAttributeElement()
    {
        $element = $this->getForm()->addField('action:' . $this->getId() . ':attribute', 'select', [
            'name' => 'rule[actions][' . $this->getId() . '][attribute]',
            'values' => $this->getAttributeSelectOptions(),
            'value' => $this->getAttribute(),
            'value_name' => $this->getAttributeName(),
        ]);

        $renderer = Mage::getBlockSingleton('rule/editable');
        if ($renderer instanceof \Maho\Data\Form\Element\Renderer\RendererInterface) {
            $element->setRenderer($renderer);
        }

        return $element;
    }

    /**
     * @return \Maho\Data\Form\Element\AbstractElement
     */
    public function getOperatorElement()
    {
        $element = $this->getForm()->addField('action:' . $this->getId() . ':operator', 'select', [
            'name' => 'rule[actions][' . $this->getId() . '][operator]',
            'values' => $this->getOperatorSelectOptions(),
            'value' => $this->getOperator(),
            'value_name' => $this->getOperatorName(),
        ]);

        $renderer = Mage::getBlockSingleton('rule/editable');
        if ($renderer instanceof \Maho\Data\Form\Element\Renderer\RendererInterface) {
            $element->setRenderer($renderer);
        }

        return $element;
    }

    /**
     * @return \Maho\Data\Form\Element\AbstractElement
     */
    public function getValueElement()
    {
        $element = $this->getForm()->addField('action:' . $this->getId() . ':value', 'text', [
            'name' => 'rule[actions][' . $this->getId() . '][value]',
            'value' => $this->getValue(),
            'value_name' => $this->getValueName(),
        ]);

        $renderer = Mage::getBlockSingleton('rule/editable');
        if ($renderer instanceof \Maho\Data\Form\Element\Renderer\RendererInterface) {
            $element->setRenderer($renderer);
        }

        return $element;
    }

    /**
     * @return string
     */
    public function getAddLinkHtml()
    {
        $icon = Mage::helper('core')->getIconSvg('circle-plus');
        return '<span class="rule-param-add">' . $icon . '</span>';
    }

    /**
     * @return string
     */
    public function getRemoveLinkHtml()
    {
        $icon = Mage::helper('core')->getIconSvg('circle-x');
        return '<span class="rule-param"><a href="javascript:void(0)" class="rule-param-remove">' . $icon . '</a></span>';
    }

    /**
     * @param string $format
     * @return string
     */
    public function asString($format = '')
    {
        return '';
    }

    /**
     * @param int $level
     * @return string
     */
    public function asStringRecursive($level = 0)
    {
        return str_pad('', $level * 3, ' ', STR_PAD_LEFT) . $this->asString();
    }

    /**
     * @return $this
     */
    public function process()
    {
        return $this;
    }

    public function getAttribute(): ?string
    {
        $value = $this->getData('attribute');
        return $value === null ? null : (string) $value;
    }

    public function setAttribute(?string $value): static
    {
        return $this->setData('attribute', $value);
    }

    public function getAttributeOption(): ?array
    {
        return $this->getData('attribute_option');
    }

    public function setAttributeOption(?array $value): static
    {
        return $this->setData('attribute_option', $value);
    }

    public function getOperator(): ?string
    {
        $value = $this->getData('operator');
        return $value === null ? null : (string) $value;
    }

    public function setOperator(?string $value): static
    {
        return $this->setData('operator', $value);
    }

    public function getOperatorOption(): ?array
    {
        return $this->getData('operator_option');
    }

    public function setOperatorOption(?array $value): static
    {
        return $this->setData('operator_option', $value);
    }

    public function getRule(): ?Mage_Rule_Model_Abstract
    {
        return $this->getData('rule');
    }

    public function getType(): ?string
    {
        $value = $this->getData('type');
        return $value === null ? null : (string) $value;
    }

    public function getValue(): ?string
    {
        $value = $this->getData('value');
        return $value === null ? null : (string) $value;
    }

    public function getValueOption(): ?array
    {
        return $this->getData('value_option');
    }

    public function setValueOption(?array $value): static
    {
        return $this->setData('value_option', $value);
    }

}
