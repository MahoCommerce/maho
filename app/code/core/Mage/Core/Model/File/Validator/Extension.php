<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * File extension validator
 *
 * Validates file extensions against forbidden and optionally allowed lists
 */
class Mage_Core_Model_File_Validator_Extension
{
    public const FORBIDDEN_EXTENSION = 'forbiddenExtension';
    public const NOT_ALLOWED_EXTENSION = 'notAllowedExtension';

    /**
     * Forbidden file extensions (from configuration)
     *
     * @var array<string>
     */
    protected array $forbiddenExtensions = [];

    /**
     * Allowed file extensions (optional whitelist)
     *
     * @var array<string>|null
     */
    protected ?array $allowedExtensions = null;

    /**
     * Message templates
     *
     * @var array<string, string>
     */
    protected array $messageTemplates = [];

    /**
     * Validation messages
     *
     * @var array<string>
     */
    protected array $messages = [];

    /**
     * Configuration path for forbidden extensions
     */
    protected string $forbiddenConfigPath = 'catalog/custom_options/forbidden_extensions';

    public function __construct(mixed $forbiddenConfigPath = null)
    {
        // Handle both direct instantiation and Mage::getModel() calls
        if (is_string($forbiddenConfigPath)) {
            $this->forbiddenConfigPath = $forbiddenConfigPath;
        }
        $this->initMessageTemplates();
        $this->initForbiddenExtensions();
    }

    /**
     * Initialize message templates with translations
     */
    protected function initMessageTemplates(): self
    {
        $this->messageTemplates = [
            self::FORBIDDEN_EXTENSION => Mage::helper('core')->__('The file extension "%s" is not allowed for security reasons.'),
            self::NOT_ALLOWED_EXTENSION => Mage::helper('core')->__('The file extension "%s" is not allowed. Allowed extensions: %s'),
        ];
        return $this;
    }

    /**
     * Initialize forbidden file extensions from configuration
     */
    protected function initForbiddenExtensions(): self
    {
        $forbiddenConfig = Mage::getStoreConfig($this->forbiddenConfigPath);
        if ($forbiddenConfig) {
            $extensions = array_map('trim', explode(',', $forbiddenConfig));
            $this->forbiddenExtensions = array_map('strtolower', $extensions);
        }
        return $this;
    }

    /**
     * Set allowed extensions (whitelist)
     *
     * @param array<string>|null $extensions
     */
    public function setAllowedExtensions(?array $extensions): self
    {
        if ($extensions !== null) {
            $this->allowedExtensions = array_map('strtolower', array_map('trim', $extensions));
        } else {
            $this->allowedExtensions = null;
        }
        return $this;
    }

    /**
     * Get allowed extensions
     *
     * @return array<string>|null
     */
    public function getAllowedExtensions(): ?array
    {
        return $this->allowedExtensions;
    }

    /**
     * Get forbidden extensions
     *
     * @return array<string>
     */
    public function getForbiddenExtensions(): array
    {
        return $this->forbiddenExtensions;
    }

    /**
     * Validate file extension
     *
     * @param string $fileNameOrExtension File name or extension to validate
     */
    public function isValid(string $fileNameOrExtension): bool
    {
        $this->messages = [];

        // Extract extension if full filename provided
        if (str_contains($fileNameOrExtension, '.')) {
            $extension = strtolower(pathinfo($fileNameOrExtension, PATHINFO_EXTENSION));
        } else {
            $extension = strtolower(trim($fileNameOrExtension));
        }

        // ALWAYS check forbidden list first (security priority)
        if (!empty($this->forbiddenExtensions) && in_array($extension, $this->forbiddenExtensions)) {
            $this->messages[] = sprintf($this->messageTemplates[self::FORBIDDEN_EXTENSION], $extension);
            return false;
        }

        // If allowed list specified, check against it
        if ($this->allowedExtensions !== null) {
            if (!in_array($extension, $this->allowedExtensions)) {
                $this->messages[] = sprintf(
                    $this->messageTemplates[self::NOT_ALLOWED_EXTENSION],
                    $extension,
                    implode(', ', $this->allowedExtensions),
                );
                return false;
            }
        }

        // If no specific allowed list, any non-forbidden extension is valid
        return true;
    }

    /**
     * Get validation failure messages
     *
     * @return array<string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
