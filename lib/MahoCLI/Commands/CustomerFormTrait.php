<?php

/**
 * Validate and save the attributes of a customer or an address with the rules of an admin form.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Core_Model_Abstract;
use Mage_Customer_Model_Form;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait CustomerFormTrait
{
    /**
     * Return the admin form with the code $formCode. The admin validates the attributes of a customer
     * with the form 'adminhtml_customer' and the attributes of an address with 'adminhtml_customer_address'.
     */
    private function getCustomerForm(string $formCode, Mage_Core_Model_Abstract $entity): Mage_Customer_Model_Form
    {
        return Mage::getModel('customer/form')
            ->setFormCode($formCode)
            ->setEntity($entity)
            ->ignoreInvisible(false);
    }

    /**
     * Turn the values of the --attribute option, each one as code=value, into an array keyed by the code.
     *
     * @param list<string> $values
     * @return array<string, string>
     * @throws \InvalidArgumentException
     */
    private function parseAttributeValues(array $values, Mage_Customer_Model_Form $form): array
    {
        $attributes = $form->getAttributes();
        $result = [];
        foreach ($values as $value) {
            [$code, $data] = array_pad(explode('=', $value, 2), 2, null);
            $code = trim($code);
            if ($data === null || $code === '') {
                throw new \InvalidArgumentException("Give the attribute as code=value: $value");
            }
            if (!isset($attributes[$code])) {
                throw new \InvalidArgumentException("Attribute not found: $code");
            }
            $result[$code] = $data;
        }
        return $result;
    }

    /**
     * Ask for each required attribute of $form that is not in $known, and add the answers to the
     * --attribute option.
     *
     * @param list<string> $known
     */
    private function askRequiredAttributes(InputInterface $input, SymfonyStyle $io, Mage_Customer_Model_Form $form, array $known): void
    {
        $values = $input->getOption('attribute');
        try {
            $known = [...$known, ...array_keys($this->parseAttributeValues($values, $form))];
        } catch (\InvalidArgumentException) {
            return;
        }

        foreach ($form->getAttributes() as $code => $attribute) {
            if (!$attribute->getIsRequired() || in_array($code, $known, true)) {
                continue;
            }

            $label = $attribute->getStoreLabel() . ':';
            if ($attribute->usesSource()) {
                $options = [];
                foreach ($attribute->getSource()->getAllOptions() as $option) {
                    if ($option['value'] !== '' && $option['value'] !== null) {
                        $options[(string) $option['value']] = (string) $option['label'];
                    }
                }
                $multiSelect = $attribute->getFrontendInput() === 'multiselect';
                $answers = (array) $io->choice($label, array_values($options), multiSelect: $multiSelect);
                $value = implode(',', array_map(fn($answer) => array_search($answer, $options, true), $answers));
            } else {
                $value = (string) $io->ask($label);
            }
            $values[] = "$code=$value";
        }

        $input->setOption('attribute', $values);
    }

    /**
     * Check $data against the rules of $form, then write it into the entity of $form. Return the errors.
     *
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private function validateAndCompactData(Mage_Customer_Model_Form $form, array $data): array
    {
        $errors = $form->validateData($data);
        $form->compactData($data);
        return $errors === true ? [] : array_values($errors);
    }
}
