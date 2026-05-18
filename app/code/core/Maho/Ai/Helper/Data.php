<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Synchronous single-shot completion.
     *
     * Usage:
     *   $response = Mage::helper('ai')->invoke(
     *       userMessage: 'Write a product description for tennis racquet',
     *       systemPrompt: 'You are an e-commerce copywriter.',
     *   );
     *
     * @param string|array<array{role: string, content: string}> $userMessage
     *   Either a plain string (treated as single user message) or a full
     *   messages array. **The string form is for untrusted input — only
     *   user-supplied text — and runs through the injection validator.** The
     *   array form is intended for trusted callers passing curated
     *   conversation history: only entries with `role === 'user'` are
     *   validated; system/assistant entries are forwarded as-is. Do not
     *   build the array form straight from end-user input.
     * @param array<string, mixed> $options
     *   Supports: temperature, max_tokens, model, is_html (bool — enables HTML sanitization).
     *
     * @throws Mage_Core_Exception if AI is disabled, not configured, or injection detected
     */
    public function invoke(
        string|array $userMessage,
        ?string $systemPrompt = null,
        ?string $platform = null,
        ?string $model = null,
        array $options = [],
        ?int $storeId = null,
        string $consumer = '_direct',
    ): string {
        if (!$this->isEnabled($storeId)) {
            throw new Mage_Core_Exception('Maho AI is disabled.');
        }

        // Build messages array
        $messages = [];
        if ($systemPrompt !== null) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        if (is_string($userMessage)) {
            // Validate user-supplied text for injection
            $validation = $this->getInputValidator()->validate($userMessage);
            if (!$validation['safe']) {
                throw new Mage_Core_Exception('AI request rejected: ' . $validation['reason']);
            }
            $messages[] = ['role' => 'user', 'content' => $userMessage];
        } else {
            // Validate each user message in the array
            foreach ($userMessage as $msg) {
                if ($msg['role'] === 'user') {
                    $validation = $this->getInputValidator()->validate((string) $msg['content']);
                    if (!$validation['safe']) {
                        throw new Mage_Core_Exception('AI request rejected: ' . $validation['reason']);
                    }
                }
            }
            $messages = array_merge($messages, $userMessage);
        }

        if ($model !== null) {
            $options['model'] = $model;
        }

        // Create provider and call
        $provider = $this->getFactory()->create($platform, $storeId);
        try {
            $response = $provider->complete($messages, $options);
        } catch (\Symfony\AI\Platform\Exception\ExceptionInterface $e) {
            throw $this->translateProviderException($e, $provider->getPlatformCode());
        }

        // Sanitize output
        $isHtml = (bool) ($options['is_html'] ?? false);
        $metadata = [];
        $response = $this->getOutputSanitizer()->sanitize($response, $isHtml, $metadata);

        // Log request if enabled
        $this->logRequest(
            consumer: $consumer,
            platform: $provider->getPlatformCode(),
            model: $provider->getLastModel(),
            tokenUsage: $provider->getLastTokenUsage(),
            storeId: $storeId ?? 0,
        );

        return $response;
    }

    /**
     * Submit a task to the async queue.
     *
     * @param array{
     *   consumer: string,
     *   action: string,
     *   system_prompt?: string,
     *   messages: array<array{role: string, content: string}>,
     *   context?: array<string, mixed>,
     *   callback_class?: string,
     *   callback_method?: string,
     *   priority?: string,
     *   platform?: string,
     *   model?: string,
     *   max_retries?: int,
     *   store_id?: int,
     * } $data
     *
     * @return int Task ID
     */
    public function submitTask(array $data): int
    {
        if (!$this->isEnabled($data['store_id'] ?? null)) {
            throw new Mage_Core_Exception('Maho AI is disabled.');
        }

        // Validate user-role messages for injection — mirrors the array-form
        // path in invoke(). Without this the async queue would be a bypass
        // around the same guard that protects synchronous calls.
        foreach ($data['messages'] ?? [] as $msg) {
            if (($msg['role'] ?? null) === 'user') {
                $validation = $this->getInputValidator()->validate((string) ($msg['content'] ?? ''));
                if (!$validation['safe']) {
                    throw new Mage_Core_Exception('AI request rejected: ' . $validation['reason']);
                }
            }
        }

        $task = Mage::getModel('ai/task');
        $task->setData([
            'consumer'        => $data['consumer'],
            'action'          => $data['action'] ?? 'generate',
            'status'          => 'pending',
            'priority'        => $data['priority'] ?? 'background',
            'platform'        => $data['platform'] ?? null,
            'model'           => $data['model'] ?? null,
            'system_prompt'   => $data['system_prompt'] ?? null,
            'messages'        => Mage::helper('core')->jsonEncode($data['messages'] ?? []),
            'context'         => isset($data['context']) ? Mage::helper('core')->jsonEncode($data['context']) : null,
            'callback_class'  => $data['callback_class'] ?? null,
            'callback_method' => $data['callback_method'] ?? null,
            'max_retries'     => $data['max_retries'] ?? 3,
            'store_id'        => $data['store_id'] ?? 0,
            'admin_user_id'   => Mage::getSingleton('admin/session')->isLoggedIn()
                ? (int) Mage::getSingleton('admin/session')->getUser()->getId()
                : null,
        ]);
        $task->save();

        return (int) $task->getId();
    }

    /**
     * Synchronous embedding.
     *
     * Returns a single float[] when $text is a string, or float[][] when $text is an array.
     *
     * @param string|string[] $text
     * @param array<string, mixed> $options  Supports: dimensions (int), model (string)
     * @return float[]|float[][]
     * @throws Mage_Core_Exception if AI or embed is disabled / not configured
     */
    public function embed(
        string|array $text,
        ?string $platform = null,
        ?string $model = null,
        array $options = [],
        ?int $storeId = null,
        string $consumer = '_direct',
    ): array {
        if (!$this->isEnabled($storeId)) {
            throw new Mage_Core_Exception('Maho AI is disabled.');
        }
        if (!Mage::getStoreConfigFlag('maho_ai/embed/enabled', $storeId)) {
            throw new Mage_Core_Exception('Maho AI embeddings are disabled.');
        }

        if ($model !== null) {
            $options['model'] = $model;
        }

        // Apply target dimensions from config if not overridden
        if (!isset($options['dimensions'])) {
            $targetDims = (int) Mage::getStoreConfig('maho_ai/embed/target_dimensions', $storeId);
            if ($targetDims > 0) {
                $options['dimensions'] = $targetDims;
            }
        }

        $provider = $this->getFactory()->createEmbed($platform, $storeId);
        try {
            $vectors = $provider->embed($text, $options);
        } catch (\Symfony\AI\Platform\Exception\ExceptionInterface $e) {
            throw $this->translateProviderException($e, $provider->getEmbedPlatformCode());
        }

        $this->logRequest(
            consumer: $consumer,
            platform: $provider->getEmbedPlatformCode(),
            model: $provider->getLastEmbedModel(),
            tokenUsage: [...$provider->getLastEmbedTokenUsage(), 'output' => 0],
            storeId: $storeId ?? 0,
        );

        return is_string($text) ? ($vectors[0] ?? []) : $vectors;
    }

    /**
     * Submit an embedding task to the async queue.
     *
     * When entity_type + entity_id are provided, the runner automatically saves
     * the resulting vector to maho_ai_vector on completion.
     *
     * @param array{
     *   consumer: string,
     *   text: string,
     *   entity_type?: string,
     *   entity_id?: int,
     *   platform?: string,
     *   model?: string,
     *   priority?: string,
     *   store_id?: int,
     *   callback_class?: string,
     *   callback_method?: string,
     *   max_retries?: int,
     * } $data
     * @return int Task ID
     */
    public function submitEmbedTask(array $data): int
    {
        if (!$this->isEnabled($data['store_id'] ?? null)) {
            throw new Mage_Core_Exception('Maho AI is disabled.');
        }

        $context = array_filter([
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id'   => $data['entity_id'] ?? null,
        ]);

        $task = Mage::getModel('ai/task');
        $task->setData([
            'consumer'        => $data['consumer'],
            'action'          => $data['action'] ?? 'embed',
            'task_type'       => Maho_Ai_Model_Task::TYPE_EMBEDDING,
            'status'          => Maho_Ai_Model_Task::STATUS_PENDING,
            'priority'        => $data['priority'] ?? Maho_Ai_Model_Task::PRIORITY_BACKGROUND,
            'platform'        => $data['platform'] ?? null,
            'model'           => $data['model'] ?? null,
            'messages'        => Mage::helper('core')->jsonEncode([['role' => 'user', 'content' => $data['text'] ?? '']]),
            'context'         => $context ? Mage::helper('core')->jsonEncode($context) : null,
            'callback_class'  => $data['callback_class'] ?? null,
            'callback_method' => $data['callback_method'] ?? null,
            'max_retries'     => $data['max_retries'] ?? 3,
            'store_id'        => $data['store_id'] ?? 0,
        ]);
        $task->save();

        return (int) $task->getId();
    }

    /**
     * Synchronous image generation.
     *
     * Returns a URL for OpenAI DALL-E, or a data URI for Google Imagen.
     * Falls back to a configurable placeholder URL if image generation is disabled
     * and maho_ai/image/fallback_placeholder is enabled.
     *
     * @param array<string, mixed> $options  Supports: width, height, quality, style, model
     * @throws Mage_Core_Exception if AI is disabled and placeholder fallback is off
     */
    public function generateImage(
        string $prompt,
        array $options = [],
        ?string $platform = null,
        ?string $model = null,
        ?int $storeId = null,
        string $consumer = '_direct',
    ): string {
        if (!$this->isEnabled($storeId)) {
            throw new Mage_Core_Exception('Maho AI is disabled.');
        }

        if (!Mage::getStoreConfigFlag('maho_ai/image/enabled', $storeId)) {
            return $this->getPlaceholderUrl($options, $storeId);
        }

        // Validate prompt for blocked patterns (injection patterns less relevant for
        // image APIs, but respects the admin-configured blocked_patterns list)
        $validation = $this->getInputValidator()->validate($prompt);
        if (!$validation['safe']) {
            throw new Mage_Core_Exception('AI request rejected: ' . $validation['reason']);
        }

        if ($model !== null) {
            $options['model'] = $model;
        }

        $provider = $this->getFactory()->createImage($platform, $storeId);
        try {
            $url = $provider->generateImage($prompt, $options);
        } catch (\Symfony\AI\Platform\Exception\ExceptionInterface $e) {
            throw $this->translateProviderException($e, $provider->getImagePlatformCode());
        }

        $this->logRequest(
            consumer: $consumer,
            platform: $provider->getImagePlatformCode(),
            model: $provider->getLastImageModel(),
            tokenUsage: ['input' => 0, 'output' => 0],
            storeId: $storeId ?? 0,
        );

        return $url;
    }

    /**
     * Submit an image generation task to the async queue.
     *
     * @param array{
     *   consumer: string,
     *   prompt: string,
     *   options?: array<string, mixed>,
     *   platform?: string,
     *   model?: string,
     *   context?: array<string, mixed>,
     *   priority?: string,
     *   store_id?: int,
     *   callback_class?: string,
     *   callback_method?: string,
     *   max_retries?: int,
     * } $data
     * @return int Task ID
     */
    public function submitImageTask(array $data): int
    {
        if (!$this->isEnabled($data['store_id'] ?? null)) {
            throw new Mage_Core_Exception('Maho AI is disabled.');
        }

        // Mirror sync generateImage(): respect the admin blocked_patterns list.
        $validation = $this->getInputValidator()->validate((string) ($data['prompt'] ?? ''));
        if (!$validation['safe']) {
            throw new Mage_Core_Exception('AI request rejected: ' . $validation['reason']);
        }

        $task = Mage::getModel('ai/task');
        $task->setData([
            'consumer'        => $data['consumer'],
            'action'          => $data['action'] ?? 'generate_image',
            'task_type'       => Maho_Ai_Model_Task::TYPE_IMAGE,
            'status'          => Maho_Ai_Model_Task::STATUS_PENDING,
            'priority'        => $data['priority'] ?? Maho_Ai_Model_Task::PRIORITY_BACKGROUND,
            'platform'        => $data['platform'] ?? null,
            'model'           => $data['model'] ?? null,
            'messages'        => Mage::helper('core')->jsonEncode([['role' => 'user', 'content' => $data['prompt'] ?? '']]),
            'context'         => isset($data['context']) ? Mage::helper('core')->jsonEncode($data['context']) : null,
            'callback_class'  => $data['callback_class'] ?? null,
            'callback_method' => $data['callback_method'] ?? null,
            'max_retries'     => $data['max_retries'] ?? 3,
            'store_id'        => $data['store_id'] ?? 0,
        ]);
        $task->save();

        return (int) $task->getId();
    }

    /**
     * Build a placeholder image URL using the configured pattern.
     * Pattern supports {w} and {h} tokens.
     */
    private function getPlaceholderUrl(array $options, ?int $storeId): string
    {
        if (!Mage::getStoreConfigFlag('maho_ai/image/fallback_placeholder', $storeId)) {
            throw new Mage_Core_Exception('Maho AI image generation is disabled.');
        }

        $w       = (int) ($options['width']  ?? Mage::getStoreConfig('maho_ai/image/placeholder_width', $storeId) ?: 800);
        $h       = (int) ($options['height'] ?? Mage::getStoreConfig('maho_ai/image/placeholder_height', $storeId) ?: 600);
        $pattern = (string) Mage::getStoreConfig('maho_ai/image/placeholder_url', $storeId)
            ?: 'https://placehold.co/{w}x{h}';

        return str_replace(['{w}', '{h}'], [(string) $w, (string) $h], $pattern);
    }

    public function isEnabled(?int $storeId = null): bool
    {
        if (!Mage::getStoreConfigFlag('maho_ai/general/enabled', $storeId)) {
            return false;
        }
        // symfony/ai-platform is the load-bearing base package - without it
        // the helpers can't function regardless of the admin toggle.
        return \Composer\InstalledVersions::isInstalled('symfony/ai-platform');
    }

    private function getFactory(): Maho_Ai_Model_Platform_Factory
    {
        return Mage::getSingleton('ai/platform_factory');
    }

    /**
     * Turn a Symfony AI Platform exception into a user-friendly
     * Mage_Core_Exception. The provider's raw message is preserved (it is
     * usually the actionable bit, e.g. "max_output_tokens: Extra inputs are
     * not permitted") and the original throwable is logged so the full
     * stack remains in the exception log for debugging.
     */
    private function translateProviderException(
        \Symfony\AI\Platform\Exception\ExceptionInterface $e,
        string $platformCode,
    ): Mage_Core_Exception {
        Mage::logException($e);

        $prefix = match (true) {
            $e instanceof \Symfony\AI\Platform\Exception\AuthenticationException
                => 'Authentication failed',
            $e instanceof \Symfony\AI\Platform\Exception\RateLimitExceededException
                => 'Rate limit exceeded',
            $e instanceof \Symfony\AI\Platform\Exception\ContentFilterException
                => 'Request blocked by content filter',
            $e instanceof \Symfony\AI\Platform\Exception\ModelNotFoundException
                => 'Model not found',
            $e instanceof \Symfony\AI\Platform\Exception\ExceedContextSizeException
                => 'Prompt exceeds the model context size',
            default => 'AI request failed',
        };

        return new Mage_Core_Exception(sprintf(
            '%s (%s): %s',
            $prefix,
            $platformCode,
            $e->getMessage(),
        ));
    }

    private function getInputValidator(): Maho_Ai_Model_Safety_InputValidator
    {
        return Mage::getSingleton('ai/safety_inputValidator');
    }

    private function getOutputSanitizer(): Maho_Ai_Model_Safety_OutputSanitizer
    {
        return Mage::getSingleton('ai/safety_outputSanitizer');
    }

    private function logRequest(
        string $consumer,
        string $platform,
        string $model,
        array $tokenUsage,
        int $storeId,
    ): void {
        if (!Mage::getStoreConfigFlag('maho_ai/general/log_requests')) {
            return;
        }

        Mage::log(
            sprintf(
                'consumer=%s platform=%s model=%s in=%d out=%d',
                $consumer,
                $platform,
                $model,
                $tokenUsage['input'],
                $tokenUsage['output'],
            ),
            Mage::LOG_INFO,
            'maho_ai.log',
        );

        // Persist a per-day aggregated row so the admin Usage grid surfaces
        // synchronous calls (image gen, completions). Wrapped in try/catch:
        // usage logging is observability — never fatal the originating AI
        // call if persistence trips.
        try {
            Mage::getModel('ai/usage')->recordCall(
                consumer: $consumer,
                platform: $platform,
                model: $model,
                storeId: $storeId,
                inputTokens: (int) ($tokenUsage['input'] ?? 0),
                outputTokens: (int) ($tokenUsage['output'] ?? 0),
            );
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }
}
