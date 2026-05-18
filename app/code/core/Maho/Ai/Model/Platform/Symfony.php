<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

/**
 * Adapter that wraps a Symfony AI PlatformInterface and presents it as a
 * Maho_Ai provider — implementing all three Maho provider interfaces (chat,
 * embed, image) so a single instance can serve every capability the
 * underlying Symfony bridge supports.
 *
 * Created via the per-platform static constructors (createForOpenAi etc.)
 * which read API keys + default models from Maho store config.
 */
class Maho_Ai_Model_Platform_Symfony implements
    Maho_Ai_Model_Platform_ProviderInterface,
    Maho_Ai_Model_Platform_EmbedProviderInterface,
    Maho_Ai_Model_Platform_ImageProviderInterface
{
    protected string $lastModel = '';
    /** @var array{input: int, output: int} */
    protected array $lastTokenUsage = ['input' => 0, 'output' => 0];

    protected string $lastEmbedModel = '';
    /** @var array{input: int} */
    protected array $lastEmbedTokenUsage = ['input' => 0];

    protected string $lastImageModel = '';

    /**
     * Subclasses should instantiate the appropriate Symfony AI Platform
     * bridge (e.g. for a custom OpenAI-compatible host) and pass it as
     * $platform. The five readonly fields stay protected so subclasses
     * can read defaults / platform code without re-declaring them.
     */
    public function __construct(
        protected readonly PlatformInterface $platform,
        protected readonly string $platformCode,
        protected readonly string $defaultChatModel = '',
        protected readonly string $defaultEmbedModel = '',
        protected readonly string $defaultImageModel = '',
    ) {}

    #[\Override]
    public function complete(array $messages, array $options = []): string
    {
        $model = (string) ($options['model'] ?? $this->defaultChatModel);
        if ($model === '') {
            throw new Mage_Core_Exception('No chat model configured for ' . $this->platformCode);
        }
        $bag = $this->buildMessageBag($messages);
        $deferred = $this->platform->invoke($model, $bag, $this->mapChatOptions($options));
        $text = $deferred->asText();
        $this->captureChatMetadata($deferred, $model);
        return $text;
    }

    #[\Override]
    public function getLastTokenUsage(): array
    {
        return $this->lastTokenUsage;
    }

    #[\Override]
    public function getPlatformCode(): string
    {
        return $this->platformCode;
    }

    #[\Override]
    public function getLastModel(): string
    {
        return $this->lastModel;
    }

    #[\Override]
    public function embed(string|array $input, array $options = []): array
    {
        $model = (string) ($options['model'] ?? $this->defaultEmbedModel);
        if ($model === '') {
            throw new Mage_Core_Exception('No embed model configured for ' . $this->platformCode);
        }

        $items = is_array($input) ? array_values($input) : [$input];
        $vectors = [];
        $tokenSum = 0;

        foreach ($items as $text) {
            $deferred = $this->platform->invoke($model, (string) $text, $this->mapEmbedOptions($options));
            // asVectors() returns Vector[] — one Vector per input. Unwrap to
            // float[] each so the Maho contract (float[][]) is satisfied.
            foreach ($deferred->asVectors() as $vector) {
                $vectors[] = $vector->getData();
            }
            $tu = $this->extractTokenUsage($deferred);
            if ($tu) {
                $tokenSum += (int) ($tu->getPromptTokens() ?? 0);
            }
        }

        $this->lastEmbedModel = $model;
        $this->lastEmbedTokenUsage = ['input' => $tokenSum];

        return $vectors;
    }

    #[\Override]
    public function getLastEmbedTokenUsage(): array
    {
        return $this->lastEmbedTokenUsage;
    }

    #[\Override]
    public function getEmbedPlatformCode(): string
    {
        return $this->platformCode;
    }

    #[\Override]
    public function getLastEmbedModel(): string
    {
        return $this->lastEmbedModel;
    }

    #[\Override]
    public function generateImage(string $prompt, array $options = []): string
    {
        $model = (string) ($options['model'] ?? $this->defaultImageModel);
        if ($model === '') {
            throw new Mage_Core_Exception('No image model configured for ' . $this->platformCode);
        }
        $deferred = $this->platform->invoke($model, $prompt, $this->mapImageOptions($options));
        $this->lastImageModel = $model;
        // Maho contract is "URL or data URI". Data URI is the safer default
        // because it works for every bridge regardless of upload behavior.
        return $deferred->asDataUri();
    }

    #[\Override]
    public function getImagePlatformCode(): string
    {
        return $this->platformCode;
    }

    #[\Override]
    public function getLastImageModel(): string
    {
        return $this->lastImageModel;
    }

    public static function createForOpenAi(?int $storeId): self
    {
        $apiKey = (string) Mage::helper('core')->decrypt(
            (string) Mage::getStoreConfig('maho_ai/general/openai_api_key', $storeId),
        );
        if ($apiKey === '') {
            throw new Mage_Core_Exception('OpenAI API key is not configured.');
        }

        $chatModel  = (string) Mage::getStoreConfig('maho_ai/general/openai_model', $storeId);
        $embedModel = (string) Mage::getStoreConfig('maho_ai/embed/openai_model', $storeId);
        $imageModel = (string) Mage::getStoreConfig('maho_ai/image/openai_model', $storeId);

        // Symfony's ModelCatalog is a fixed list of known names. Register our
        // configured models on top so admin-set custom IDs (e.g. dated GPT
        // variants like "gpt-5.4-mini-2026-03-17") still resolve. Capabilities
        // mirror the bridge family — registering under Gpt::class already
        // asserts "this is a GPT-family chat model", and every modern GPT
        // supports these. Downstream consumers gating features on catalog
        // capability checks (tool calling, vision) shouldn't be misled into
        // thinking a dated variant is less capable than its base model.
        $additional = [];
        if ($chatModel !== '') {
            $additional[$chatModel] = [
                'class' => \Symfony\AI\Platform\Bridge\OpenAi\Gpt::class,
                'capabilities' => [
                    \Symfony\AI\Platform\Capability::INPUT_MESSAGES,
                    \Symfony\AI\Platform\Capability::OUTPUT_TEXT,
                    \Symfony\AI\Platform\Capability::OUTPUT_STREAMING,
                    \Symfony\AI\Platform\Capability::TOOL_CALLING,
                    \Symfony\AI\Platform\Capability::INPUT_IMAGE,
                    \Symfony\AI\Platform\Capability::OUTPUT_STRUCTURED,
                ],
            ];
        }
        if ($embedModel !== '') {
            $additional[$embedModel] = [
                'class' => \Symfony\AI\Platform\Bridge\OpenAi\Embeddings::class,
                'capabilities' => [\Symfony\AI\Platform\Capability::INPUT_MULTIPLE],
            ];
        }
        if ($imageModel !== '') {
            $additional[$imageModel] = [
                'class' => \Symfony\AI\Platform\Bridge\OpenAi\DallE::class,
                'capabilities' => [\Symfony\AI\Platform\Capability::OUTPUT_IMAGE],
            ];
        }
        $catalog = new \Symfony\AI\Platform\Bridge\OpenAi\ModelCatalog($additional);

        return new self(
            platform: \Symfony\AI\Platform\Bridge\OpenAi\Factory::createPlatform($apiKey, modelCatalog: $catalog),
            platformCode: Maho_Ai_Model_Platform::OPENAI,
            defaultChatModel: $chatModel,
            defaultEmbedModel: $embedModel,
            defaultImageModel: $imageModel,
        );
    }

    public static function createForAnthropic(?int $storeId): self
    {
        $apiKey = (string) Mage::helper('core')->decrypt(
            (string) Mage::getStoreConfig('maho_ai/general/anthropic_api_key', $storeId),
        );
        if ($apiKey === '') {
            throw new Mage_Core_Exception('Anthropic API key is not configured.');
        }
        $chatModel = (string) Mage::getStoreConfig('maho_ai/general/anthropic_model', $storeId);

        // Capabilities mirror the Claude bridge family — see note in createForOpenAi().
        $additional = [];
        if ($chatModel !== '') {
            $additional[$chatModel] = [
                'class' => \Symfony\AI\Platform\Bridge\Anthropic\Claude::class,
                'capabilities' => [
                    \Symfony\AI\Platform\Capability::INPUT_MESSAGES,
                    \Symfony\AI\Platform\Capability::OUTPUT_TEXT,
                    \Symfony\AI\Platform\Capability::OUTPUT_STREAMING,
                    \Symfony\AI\Platform\Capability::TOOL_CALLING,
                    \Symfony\AI\Platform\Capability::INPUT_IMAGE,
                ],
            ];
        }
        $catalog = new \Symfony\AI\Platform\Bridge\Anthropic\ModelCatalog($additional);

        return new self(
            platform: \Symfony\AI\Platform\Bridge\Anthropic\Factory::createPlatform($apiKey, modelCatalog: $catalog),
            platformCode: Maho_Ai_Model_Platform::ANTHROPIC,
            defaultChatModel: $chatModel,
        );
    }

    public static function createForGoogle(?int $storeId): self
    {
        $apiKey = (string) Mage::helper('core')->decrypt(
            (string) Mage::getStoreConfig('maho_ai/general/google_api_key', $storeId),
        );
        if ($apiKey === '') {
            throw new Mage_Core_Exception('Google AI API key is not configured.');
        }
        $chatModel  = (string) Mage::getStoreConfig('maho_ai/general/google_model', $storeId);
        $embedModel = (string) Mage::getStoreConfig('maho_ai/embed/google_model', $storeId);

        // Capabilities mirror the Gemini bridge family — see note in createForOpenAi().
        $additional = [];
        if ($chatModel !== '') {
            $additional[$chatModel] = [
                'class' => \Symfony\AI\Platform\Bridge\Gemini\Gemini::class,
                'capabilities' => [
                    \Symfony\AI\Platform\Capability::INPUT_MESSAGES,
                    \Symfony\AI\Platform\Capability::OUTPUT_TEXT,
                    \Symfony\AI\Platform\Capability::OUTPUT_STREAMING,
                    \Symfony\AI\Platform\Capability::TOOL_CALLING,
                    \Symfony\AI\Platform\Capability::INPUT_IMAGE,
                ],
            ];
        }
        if ($embedModel !== '') {
            $additional[$embedModel] = [
                'class' => \Symfony\AI\Platform\Bridge\Gemini\Embeddings::class,
                'capabilities' => [\Symfony\AI\Platform\Capability::INPUT_MULTIPLE],
            ];
        }
        $catalog = new \Symfony\AI\Platform\Bridge\Gemini\ModelCatalog($additional);

        return new self(
            platform: \Symfony\AI\Platform\Bridge\Gemini\Factory::createPlatform($apiKey, modelCatalog: $catalog),
            platformCode: Maho_Ai_Model_Platform::GOOGLE,
            defaultChatModel: $chatModel,
            defaultEmbedModel: $embedModel,
        );
    }

    public static function createForMistral(?int $storeId): self
    {
        $apiKey = (string) Mage::helper('core')->decrypt(
            (string) Mage::getStoreConfig('maho_ai/general/mistral_api_key', $storeId),
        );
        if ($apiKey === '') {
            throw new Mage_Core_Exception('Mistral API key is not configured.');
        }
        $chatModel  = (string) Mage::getStoreConfig('maho_ai/general/mistral_model', $storeId);
        $embedModel = (string) Mage::getStoreConfig('maho_ai/embed/mistral_model', $storeId)
            ?: 'mistral-embed';

        // Capabilities mirror the Mistral bridge family — see note in createForOpenAi().
        $additional = [];
        if ($chatModel !== '') {
            $additional[$chatModel] = [
                'class' => \Symfony\AI\Platform\Bridge\Mistral\Mistral::class,
                'capabilities' => [
                    \Symfony\AI\Platform\Capability::INPUT_MESSAGES,
                    \Symfony\AI\Platform\Capability::OUTPUT_TEXT,
                    \Symfony\AI\Platform\Capability::OUTPUT_STREAMING,
                    \Symfony\AI\Platform\Capability::TOOL_CALLING,
                ],
            ];
        }
        $additional[$embedModel] = [
            'class' => \Symfony\AI\Platform\Bridge\Mistral\Embeddings::class,
            'capabilities' => [\Symfony\AI\Platform\Capability::INPUT_MULTIPLE],
        ];
        $catalog = new \Symfony\AI\Platform\Bridge\Mistral\ModelCatalog($additional);

        return new self(
            platform: \Symfony\AI\Platform\Bridge\Mistral\Factory::createPlatform($apiKey, modelCatalog: $catalog),
            platformCode: Maho_Ai_Model_Platform::MISTRAL,
            defaultChatModel: $chatModel,
            defaultEmbedModel: $embedModel,
        );
    }

    public static function createForOllama(?int $storeId): self
    {
        $endpoint = (string) Mage::getStoreConfig('maho_ai/general/ollama_base_url', $storeId)
            ?: 'http://localhost:11434';
        $chatModel  = (string) Mage::getStoreConfig('maho_ai/general/ollama_model', $storeId);
        $embedModel = (string) Mage::getStoreConfig('maho_ai/embed/ollama_model', $storeId)
            ?: 'nomic-embed-text';

        return new self(
            platform: \Symfony\AI\Platform\Bridge\Ollama\Factory::createPlatform($endpoint),
            platformCode: Maho_Ai_Model_Platform::OLLAMA,
            defaultChatModel: $chatModel,
            defaultEmbedModel: $embedModel,
        );
    }

    public static function createForOpenRouter(?int $storeId): self
    {
        $apiKey = (string) Mage::helper('core')->decrypt(
            (string) Mage::getStoreConfig('maho_ai/general/openrouter_api_key', $storeId),
        );
        if ($apiKey === '') {
            throw new Mage_Core_Exception('OpenRouter API key is not configured.');
        }
        $chatModel = (string) Mage::getStoreConfig('maho_ai/general/openrouter_model', $storeId);

        return new self(
            platform: \Symfony\AI\Platform\Bridge\OpenRouter\Factory::createPlatform($apiKey),
            platformCode: Maho_Ai_Model_Platform::OPENROUTER,
            defaultChatModel: $chatModel,
        );
    }

    public static function createForGeneric(?int $storeId): self
    {
        $baseUrl = (string) Mage::getStoreConfig('maho_ai/general/generic_base_url', $storeId);
        if ($baseUrl === '') {
            throw new Mage_Core_Exception('Generic provider base URL is not configured.');
        }
        $apiKey = (string) Mage::helper('core')->decrypt(
            (string) Mage::getStoreConfig('maho_ai/general/generic_api_key', $storeId),
        );
        $chatModel  = (string) Mage::getStoreConfig('maho_ai/general/generic_model', $storeId);
        $embedModel = (string) Mage::getStoreConfig('maho_ai/embed/generic_model', $storeId);
        $imageModel = (string) Mage::getStoreConfig('maho_ai/image/generic_model', $storeId);

        return new self(
            platform: \Symfony\AI\Platform\Bridge\Generic\Factory::createPlatform($baseUrl, $apiKey ?: null),
            platformCode: Maho_Ai_Model_Platform::GENERIC,
            defaultChatModel: $chatModel,
            defaultEmbedModel: $embedModel,
            defaultImageModel: $imageModel,
        );
    }

    /** @param array<array{role: string, content: string}> $messages */
    protected function buildMessageBag(array $messages): MessageBag
    {
        $bag = new MessageBag();
        foreach ($messages as $m) {
            $role = (string) ($m['role'] ?? 'user');
            $content = (string) ($m['content'] ?? '');
            $bag->add(match ($role) {
                'system'    => Message::forSystem($content),
                'assistant' => Message::ofAssistant($content),
                default     => Message::ofUser($content),
            });
        }
        return $bag;
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    protected function mapChatOptions(array $options): array
    {
        $out = [];
        if (isset($options['temperature'])) {
            $out['temperature'] = $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            // Every Symfony AI bridge except OpenAI Responses accepts
            // max_tokens; Anthropic's strict-validation bridge rejects
            // unknown keys, so max_output_tokens must only go to OpenAI.
            $out['max_tokens'] = $options['max_tokens'];
            if ($this->platformCode === Maho_Ai_Model_Platform::OPENAI) {
                $out['max_output_tokens'] = $options['max_tokens'];
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    protected function mapEmbedOptions(array $options): array
    {
        $out = [];
        if (isset($options['dimensions'])) {
            $out['dimensions'] = $options['dimensions'];
        }
        return $out;
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    protected function mapImageOptions(array $options): array
    {
        $out = [];
        if (isset($options['width'], $options['height'])) {
            $out['size'] = $options['width'] . 'x' . $options['height'];
        }
        if (isset($options['quality'])) {
            $out['quality'] = $options['quality'];
        }
        if (isset($options['style'])) {
            $out['style'] = $options['style'];
        }
        return $out;
    }

    protected function captureChatMetadata(DeferredResult $deferred, string $model): void
    {
        $this->lastModel = $model;
        $this->lastTokenUsage = ['input' => 0, 'output' => 0];
        $tu = $this->extractTokenUsage($deferred);
        if ($tu) {
            $this->lastTokenUsage = [
                'input'  => (int) ($tu->getPromptTokens() ?? 0),
                'output' => (int) ($tu->getCompletionTokens() ?? 0),
            ];
        }
    }

    protected function extractTokenUsage(DeferredResult $deferred): ?TokenUsage
    {
        try {
            $metadata = $deferred->getResult()->getMetadata();
            foreach ($metadata as $key => $value) {
                if ($value instanceof TokenUsage) {
                    return $value;
                }
            }
        } catch (\Throwable $e) {
            // Token usage is best-effort; never fail the request because
            // metadata extraction had a problem.
            Mage::logException($e);
        }
        return null;
    }
}
