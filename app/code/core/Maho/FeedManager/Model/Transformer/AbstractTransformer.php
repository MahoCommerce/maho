<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Abstract Transformer
 *
 * Base implementation for transformers
 */
abstract class Maho_FeedManager_Model_Transformer_AbstractTransformer implements Maho_FeedManager_Model_Transformer_TransformerInterface
{
    protected string $_code = '';
    protected string $_name = '';
    protected string $_description = '';
    protected array $_optionDefinitions = [];

    #[\Override]
    public function getCode(): string
    {
        return $this->_code;
    }

    #[\Override]
    public function getName(): string
    {
        return $this->_name;
    }

    #[\Override]
    public function getDescription(): string
    {
        return $this->_description;
    }

    #[\Override]
    public function getOptionDefinitions(): array
    {
        return $this->_optionDefinitions;
    }

    #[\Override]
    public function validateOptions(array $options): array
    {
        $errors = [];

        foreach ($this->_optionDefinitions as $key => $definition) {
            if (!empty($definition['required']) && empty($options[$key])) {
                $errors[] = "Missing required option: {$definition['label']}";
            }
        }

        return $errors;
    }

    /**
     * Get option value with default
     */
    protected function _getOption(array $options, string $key, mixed $default = null): mixed
    {
        return $options[$key] ?? $default;
    }
}
