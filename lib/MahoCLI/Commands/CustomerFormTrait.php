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
     * Refuse a code in $reserved: the command gives that attribute with its own argument or option.
     *
     * @param list<string> $pairs
     * @param list<string> $reserved
     * @return array<string, string>
     * @throws \InvalidArgumentException
     */
    private function parseAttributeValues(array $pairs, Mage_Customer_Model_Form $form, array $reserved): array
    {
        $attributes = $form->getAttributes();
        $result = [];
        foreach ($pairs as $pair) {
            [$code, $value] = array_pad(explode('=', $pair, 2), 2, null);
            $code = trim($code);
            if ($value === null || $code === '') {
                throw new \InvalidArgumentException("Give the attribute as code=value: $pair");
            }
            if (!isset($attributes[$code])) {
                throw new \InvalidArgumentException("Attribute not found: $code");
            }
            if (in_array($code, $reserved, true)) {
                throw new \InvalidArgumentException("Give $code with its argument or option, not with --attribute");
            }
            $result[$code] = $value;
        }
        return $result;
    }

    /**
     * In an interactive terminal, ask for each required attribute of $form that $data does not hold.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function askMissingAttributes(InputInterface $input, SymfonyStyle $io, Mage_Customer_Model_Form $form, array $data): array
    {
        if (!$input->isInteractive()) {
            return $data;
        }

        foreach ($form->getAttributes() as $code => $attribute) {
            if (!$attribute->getIsRequired() || array_key_exists($code, $data)) {
                continue;
            }

            $label = $attribute->getStoreLabel() . ':';
            if ($attribute->usesSource()) {
                $options = [];
                foreach ($attribute->getSource()->getAllOptions() as $option) {
                    if ($option['value'] !== '' && $option['value'] !== null) {
                        $text = (string) $option['label'];
                        while (in_array($text, $options, true)) {
                            $text .= " ({$option['value']})";
                        }
                        $options[(string) $option['value']] = $text;
                    }
                }
                $multiSelect = $attribute->getFrontendInput() === 'multiselect';
                $answers = (array) $io->choice($label, array_values($options), multiSelect: $multiSelect);
                $data[$code] = implode(',', array_map(fn($answer) => array_search($answer, $options, true), $answers));
            } else {
                $data[$code] = (string) $io->ask($label);
            }
        }

        return $data;
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
