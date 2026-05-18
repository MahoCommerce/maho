<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Builds provider instances for the seven built-in platforms (OpenAI,
 * Anthropic, Google, Mistral, OpenRouter, Ollama, Generic) by delegating
 * to Maho_Ai_Model_Platform_Symfony - a single adapter class that wraps
 * the matching symfony/ai-platform Bridge.
 *
 * Community providers (registered in global/ai/providers/{code} via
 * config XML) flow through createFromRegistry() and supply their own
 * factory class implementing ProviderFactoryInterface.
 *
 * @see https://github.com/MahoCommerce/maho/issues/468
 */
class Maho_Ai_Model_Platform_Factory
{
    /**
     * Create a chat provider for the given platform.
     *
     * @throws Mage_Core_Exception if platform is unknown or unconfigured
     */
    public function create(?string $platformCode = null, ?int $storeId = null): Maho_Ai_Model_Platform_ProviderInterface
    {
        $platformCode ??= $this->getDefaultPlatform($storeId);

        if ($this->isBuiltIn($platformCode)) {
            return $this->createSymfonyShim($platformCode, $storeId);
        }

        return $this->createFromRegistry(
            $platformCode,
            $storeId,
            'chat',
            Maho_Ai_Model_Platform_ProviderInterface::class,
        );
    }

    public function getDefaultPlatform(?int $storeId = null): string
    {
        return (string) Mage::getStoreConfig('maho_ai/general/default_platform', $storeId)
            ?: Maho_Ai_Model_Platform::OPENAI;
    }

    /**
     * Create an embed provider. The Symfony shim implements all three
     * interfaces (chat / embed / image) so we can return the same
     * instance shape regardless of capability requested.
     *
     * @throws Mage_Core_Exception if the platform doesn't support embeddings
     */
    public function createEmbed(?string $platformCode = null, ?int $storeId = null): Maho_Ai_Model_Platform_EmbedProviderInterface
    {
        $platformCode ??= (string) Mage::getStoreConfig('maho_ai/embed/default_platform', $storeId)
            ?: Maho_Ai_Model_Platform::OPENAI;

        if ($this->isBuiltIn($platformCode)) {
            // Symfony shim implements all three interfaces; no further
            // capability check needed for built-in providers.
            return $this->createSymfonyShim($platformCode, $storeId);
        }

        $provider = $this->createFromRegistry(
            $platformCode,
            $storeId,
            'embed',
            Maho_Ai_Model_Platform_EmbedProviderInterface::class,
        );
        return $provider;
    }

    /**
     * Create an image provider. Same shim shape as embed/chat.
     *
     * @throws Mage_Core_Exception if the platform doesn't support image gen
     */
    public function createImage(?string $platformCode = null, ?int $storeId = null): Maho_Ai_Model_Platform_ImageProviderInterface
    {
        $platformCode ??= (string) Mage::getStoreConfig('maho_ai/image/default_platform', $storeId)
            ?: Maho_Ai_Model_Platform::OPENAI;

        if ($this->isBuiltIn($platformCode)) {
            // Symfony shim implements all three interfaces; the actual
            // image-capability check happens inside the shim when it tries
            // to invoke a model that the underlying bridge doesn't support.
            return $this->createSymfonyShim($platformCode, $storeId);
        }

        $provider = $this->createFromRegistry(
            $platformCode,
            $storeId,
            'image',
            Maho_Ai_Model_Platform_ImageProviderInterface::class,
        );
        return $provider;
    }

    /**
     * Create a video provider. Symfony AI Platform doesn't ship video
     * bridges yet (as of 0.7), so this path is registry-only - community
     * providers like NanoGPT plug in via the config XML registry.
     *
     * @throws Mage_Core_Exception if no provider configured
     */
    public function createVideo(?string $platformCode = null, ?int $storeId = null): Maho_Ai_Model_Platform_VideoProviderInterface
    {
        $platformCode ??= (string) Mage::getStoreConfig('maho_ai/video/default_platform', $storeId);
        if (!$platformCode) {
            throw new Mage_Core_Exception('No video provider configured.');
        }

        $provider = $this->createFromRegistry(
            $platformCode,
            $storeId,
            'video',
            Maho_Ai_Model_Platform_VideoProviderInterface::class,
        );
        return $provider;
    }

    private function isBuiltIn(string $platformCode): bool
    {
        return in_array($platformCode, [
            Maho_Ai_Model_Platform::OPENAI,
            Maho_Ai_Model_Platform::ANTHROPIC,
            Maho_Ai_Model_Platform::GOOGLE,
            Maho_Ai_Model_Platform::MISTRAL,
            Maho_Ai_Model_Platform::OPENROUTER,
            Maho_Ai_Model_Platform::OLLAMA,
            Maho_Ai_Model_Platform::GENERIC,
        ], true);
    }

    private function createSymfonyShim(string $platformCode, ?int $storeId): Maho_Ai_Model_Platform_Symfony
    {
        $package = Maho_Ai_Model_Platform::PACKAGES[$platformCode] ?? null;
        if ($package !== null && !\Composer\InstalledVersions::isInstalled($package)) {
            throw new Mage_Core_Exception(sprintf(
                'Maho AI provider "%s" requires the %s Composer package. Install it with: composer require %s',
                $platformCode,
                $package,
                $package,
            ));
        }

        return match ($platformCode) {
            Maho_Ai_Model_Platform::OPENAI     => Maho_Ai_Model_Platform_Symfony::createForOpenAi($storeId),
            Maho_Ai_Model_Platform::ANTHROPIC  => Maho_Ai_Model_Platform_Symfony::createForAnthropic($storeId),
            Maho_Ai_Model_Platform::GOOGLE     => Maho_Ai_Model_Platform_Symfony::createForGoogle($storeId),
            Maho_Ai_Model_Platform::MISTRAL    => Maho_Ai_Model_Platform_Symfony::createForMistral($storeId),
            Maho_Ai_Model_Platform::OPENROUTER => Maho_Ai_Model_Platform_Symfony::createForOpenRouter($storeId),
            Maho_Ai_Model_Platform::OLLAMA     => Maho_Ai_Model_Platform_Symfony::createForOllama($storeId),
            Maho_Ai_Model_Platform::GENERIC    => Maho_Ai_Model_Platform_Symfony::createForGeneric($storeId),
            // Unreachable in practice (isBuiltIn() gates entry into this
            // method), but keeps PHPStan satisfied that the match is total.
            default => throw new Mage_Core_Exception("Platform '{$platformCode}' is not a built-in Symfony-shim platform."),
        };
    }

    /**
     * Community providers register via global/ai/providers/{code} and supply
     * their own factory_class implementing ProviderFactoryInterface.
     *
     * @throws Mage_Core_Exception
     */
    private function createFromRegistry(
        string $platformCode,
        ?int $storeId,
        string $capability,
        string $requiredInterface,
    ): object {
        $config = Maho_Ai_Model_Platform::getProviderConfig($platformCode);
        if (!$config) {
            throw new Mage_Core_Exception("Unknown AI platform: {$platformCode}");
        }

        $capabilities = array_map('trim', explode(',', (string) ($config->capabilities ?? '')));
        if (!in_array($capability, $capabilities, true)) {
            throw new Mage_Core_Exception("Platform '{$platformCode}' does not support {$capability}.");
        }

        if (!$config->factory_class) {
            throw new Mage_Core_Exception(
                "Provider '{$platformCode}' has no factory_class configured.",
            );
        }

        $factoryClass = (string) $config->factory_class;
        $factory = new $factoryClass();
        if (!($factory instanceof Maho_Ai_Model_Platform_ProviderFactoryInterface)) {
            throw new Mage_Core_Exception(
                "Factory class '{$factoryClass}' must implement ProviderFactoryInterface.",
            );
        }
        $provider = $factory->create($storeId);

        if (!($provider instanceof $requiredInterface)) {
            $shortInterface = basename(str_replace('_', '/', $requiredInterface));
            throw new Mage_Core_Exception(
                "Platform '{$platformCode}' does not implement {$shortInterface}.",
            );
        }

        return $provider;
    }
}
