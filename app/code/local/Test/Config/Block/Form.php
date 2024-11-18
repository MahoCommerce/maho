<?php
class Test_Config_Block_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form([
            'id' => 'edit_form',
            'action' => $this->getData('action'),
            'method' => 'post'
        ]);

        /** @var Mage_Adminhtml_Block_Widget_Form_Element_Dependence $block */
        $block = $this->getLayout()->createBlock('adminhtml/widget_form_element_dependence');

        $yesno = Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray();

        $fieldset = $form->addFieldset('test_1', ['legend' => Mage::helper('catalog')->__('Merge Test')]);
        $fieldset->addField('test_1_info', 'info', [
            'label' => <<<HTML
                <h3>Result field will show if:</h3>
                <code>source == "Bar" || source == "Baz"</code>
                <br>
            HTML
        ]);
        $fieldset->addField('test_1_source', 'select', [
            'name' => 'test_1_source',
            'label' => $this->__('Source'),
            'values' => [
                '0' => 'Foo',
                '1' => 'Bar',
                '2' => 'Baz',
            ],
        ]);
        $fieldset->addField('test_1_result', 'text', [
            'name' => 'test_1_result',
            'label' => $this->__('Result'),
        ]);
        $block
            ->addFieldDependence('test_1_result', 'test_1_source', '1')
            ->addFieldDependence('test_1_result', 'test_1_source', '2');

        ////////////////////////////////////////////////////////////////////////////////

        $fieldset = $form->addFieldset('test_2', ['legend' => Mage::helper('catalog')->__('Complex Test 1')]);
        $fieldset->addField('test_2_info', 'info', [
            'label' => <<<HTML
                <h3>Result field will show if:</h3>
                <code>(source_1 == "Yes" || source_2 == "Yes") && (source_3 == "Yes" || source_4 == "Yes")</code>
                <br>
            HTML
        ]);
        $fieldset->addField('test_2_source_1', 'select', [
            'name' => 'test_2_source_1',
            'label' => $this->__('Source 1'),
            'values' => $yesno,
        ]);
        $fieldset->addField('test_2_source_2', 'select', [
            'name' => 'test_2_source_2',
            'label' => $this->__('Source 2'),
            'values' => $yesno,
        ]);
        $fieldset->addField('test_2_source_3', 'select', [
            'name' => 'test_2_source_3',
            'label' => $this->__('Source 3'),
            'values' => $yesno,
        ]);
        $fieldset->addField('test_2_source_4', 'select', [
            'name' => 'test_2_source_4',
            'label' => $this->__('Source 4'),
            'values' => $yesno,
        ]);
        $fieldset->addField('test_2_result', 'text', [
            'name' => 'test_2_result',
            'label' => $this->__('Result'),
        ]);
        $block
            ->addComplexFieldDependence('test_2_result', $block::MODE_OR, [
                'test_2_source_1' => '1',
                'test_2_source_2' => '1',
            ])
            ->addComplexFieldDependence('test_2_result', $block::MODE_OR, [
                'test_2_source_3' => '1',
                'test_2_source_4' => '1',
            ]);

        ////////////////////////////////////////////////////////////////////////////////

        $fieldset = $form->addFieldset('test_3', ['legend' => Mage::helper('catalog')->__('Complex Test 2')]);
        $fieldset->addField('test_3_info', 'info', [
            'label' => <<<HTML
                <h3>Result field will show if:</h3>
                <code>(source_1 == "Yes" && source_2 == "Yes") || (source_3 == "Yes" && source_4 == "Yes")</code>
                <br>
            HTML
        ]);
        $fieldset->addField('test_3_source_1', 'select', [
            'name' => 'test_3_source_1',
            'label' => $this->__('Source 1'),
            'values' => $yesno,
        ]);
        $fieldset->addField('test_3_source_2', 'select', [
            'name' => 'test_3_source_2',
            'label' => $this->__('Source 2'),
            'values' => $yesno,
        ]);
        $fieldset->addField('test_3_source_3', 'select', [
            'name' => 'test_3_source_3',
            'label' => $this->__('Source 3'),
            'values' => $yesno,
        ]);
        $fieldset->addField('test_3_source_4', 'select', [
            'name' => 'test_3_source_4',
            'label' => $this->__('Source 4'),
            'values' => $yesno,
        ]);
        $fieldset->addField('test_3_result', 'text', [
            'name' => 'test_3_result',
            'label' => $this->__('Result'),
        ]);
        $block
            ->addComplexFieldDependence('test_3_result', $block::MODE_OR, [
                $block->createCondition($block::MODE_AND, [
                    'test_3_source_1' => '1',
                    'test_3_source_2' => '1',
                ]),
                $block->createCondition($block::MODE_AND, [
                    'test_3_source_3' => '1',
                    'test_3_source_4' => '1',
                ]),
            ]);

        ////////////////////////////////////////////////////////////////////////////////

        $fieldset = $form->addFieldset('test_4', ['legend' => Mage::helper('catalog')->__('Raw Test')]);
        $fieldset->addField('test_4_info', 'info', [
            'label' => <<<HTML
                <h3>Result field will show if:</h3>
                <code>(source_1 == "Yes" && source_2 == "Yes") || (source_3 == "Yes" && source_4 == "Yes")</code>
                <br>
            HTML
        ]);
        $fieldset->addField('test_4_source_1', 'select', [
            'name' => 'test_4_source_1',
            'label' => $this->__('Source 1'),
            'values' => $yesno,
        ]);
        $fieldset->addField('test_4_source_2', 'select', [
            'name' => 'test_4_source_2',
            'label' => $this->__('Source 2'),
            'values' => $yesno,
        ]);
        $fieldset->addField('test_4_source_3', 'select', [
            'name' => 'test_4_source_3',
            'label' => $this->__('Source 3'),
            'values' => $yesno,
        ]);
        $fieldset->addField('test_4_source_4', 'select', [
            'name' => 'test_4_source_4',
            'label' => $this->__('Source 4'),
            'values' => $yesno,
        ]);
        $fieldset->addField('test_4_result', 'text', [
            'name' => 'test_4_result',
            'label' => $this->__('Result'),
        ]);
        $block
            ->setRawFieldDependence('test_4_result', [
                [
                    'operator' => $block::MODE_OR,
                    'condition' => [
                        [
                            'operator' => $block::MODE_AND,
                            'condition' => [
                                'test_4_source_1' => '1',
                                'test_4_source_2' => '1',
                            ]
                        ],
                        [
                            'operator' => $block::MODE_AND,
                            'condition' => [
                                'test_4_source_3' => '1',
                                'test_4_source_4' => '1',
                            ]
                        ],
                    ]
                ],
            ]);

        ////////////////////////////////////////////////////////////////////////////////

        $this->setChild('form_after', $block);

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
