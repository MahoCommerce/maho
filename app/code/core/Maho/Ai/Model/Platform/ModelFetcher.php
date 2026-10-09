<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Maho\Http\Client as HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class Maho_Ai_Model_Platform_ModelFetcher
{
    private ?HttpClientInterface $httpClient = null;

    public function setHttpClient(HttpClientInterface $httpClient): static
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * Fetch available models for the given provider.
     * Returns array of ['value' => model_id, 'label' => display_name].
     *
     * @return list<array{value: string, label: string}>
     * @throws Mage_Core_Exception
     */
    public function fetchForProvider(string $provider, string $capability = 'chat'): array
    {
        return match ($provider) {
            'openai'     => $this->fetchOpenAi(),
            'anthropic'  => $this->fetchAnthropic(),
            'google'     => $this->fetchGoogle(),
            'mistral'    => $this->fetchMistral(),
            'openrouter' => $this->fetchOpenRouter(),
            'ollama'     => $this->fetchOllama(),
            default      => $this->fetchFromRegistry($provider, $capability),
        };
    }

    /**
     * Fetch the model list for the given provider and persist it to
     * core_config_data under ai/models_cache/{provider}. Called from
     * the API-key backend models on save so the dropdown auto-populates
     * without a manual refresh button.
     *
     * Surfaces success/failure to the admin session. Never throws —
     * a transient provider error shouldn't fail the config save.
     */
    public function refreshCache(string $provider): void
    {
        // The Generic OpenAI-compatible provider doesn't have a known
        // /models endpoint shape; skip silently.
        if ($provider === 'generic') {
            return;
        }

        try {
            $models = $this->fetchForProvider($provider);

            Mage::getModel('core/config')->saveConfig(
                "ai/models_cache/{$provider}",
                Mage::helper('core')->jsonEncode($models),
            );
            Mage::app()->getCache()->cleanType('config');

            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('ai')->__('AI: refreshed %d models for %s.', count($models), $provider),
            );
        } catch (\Throwable $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addWarning(
                Mage::helper('ai')->__('AI: could not refresh models for %s. See exception.log.', $provider),
            );
        }
    }

    private function getConfig(string $path): string
    {
        return (string) Mage::getStoreConfig($path);
    }

    private function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient ??= HttpClient::create();
    }

    /**
     * Return the JSON body of $response. If the status is 400 or more, throw the error message of the provider.
     *
     * The message never contains the request URL, because the Google URL contains the API key.
     *
     * @throws Mage_Core_Exception
     */
    private function decode(ResponseInterface $response, string $provider): array
    {
        $status = $response->getStatusCode();
        if ($status < 400) {
            return $response->toArray();
        }

        $content = $response->getContent(false);
        Mage::log(
            sprintf(
                'model fetch failed: provider=%s status=%d request_id=%s body=%s',
                $provider,
                $status,
                $response->getHeaders(false)['request-id'][0] ?? '-',
                mb_strimwidth($content, 0, 2000, '...'),
            ),
            Mage::LOG_DEBUG,
            'ai.log',
        );

        $body = json_decode($content, true);
        $error = is_array($body) ? ($body['error'] ?? $body) : null;
        $message = is_array($error) ? ($error['message'] ?? $error['detail'] ?? null) : $error;
        if (!is_string($message) || trim($message) === '') {
            throw new Mage_Core_Exception("{$provider} returned HTTP {$status}.");
        }

        throw new Mage_Core_Exception("{$provider} returned HTTP {$status}: " . mb_strimwidth(trim($message), 0, 300, '...'));
    }

    /** For fields stored with backend_model=adminhtml/system_config_backend_encrypted. */
    private function getEncryptedConfig(string $path): string
    {
        return (string) Mage::helper('core')->decrypt($this->getConfig($path));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function fetchOpenAi(): array
    {
        $apiKey = $this->getEncryptedConfig('ai/general/openai_api_key');
        if (!$apiKey) {
            throw new Mage_Core_Exception('OpenAI API key is not configured.');
        }

        $response = $this->getHttpClient()->request('GET', 'https://api.openai.com/v1/models', [
            'headers' => ['Authorization' => "Bearer {$apiKey}"],
            'timeout' => 10,
        ]);

        $data = $this->decode($response, 'OpenAI');
        $models = [];
        foreach ($data['data'] ?? [] as $model) {
            $id = $model['id'];
            // Include chat-capable models only
            if (
                str_starts_with($id, 'gpt-')
                || str_starts_with($id, 'o1')
                || str_starts_with($id, 'o3')
                || str_starts_with($id, 'chatgpt-')
            ) {
                $models[] = ['value' => $id, 'label' => $id];
            }
        }

        usort($models, fn($a, $b) => strcmp($a['value'], $b['value']));
        return $models;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function fetchAnthropic(): array
    {
        $apiKey = $this->getEncryptedConfig('ai/general/anthropic_api_key');
        if (!$apiKey) {
            throw new Mage_Core_Exception('Anthropic API key is not configured.');
        }

        $response = $this->getHttpClient()->request('GET', 'https://api.anthropic.com/v1/models', [
            'headers' => [
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                ...Maho_Ai_Model_Platform_Symfony::anthropicWorkspaceHeaders(null),
            ],
            'timeout' => 10,
        ]);

        $data = $this->decode($response, 'Anthropic');
        $models = [];
        foreach ($data['data'] ?? [] as $model) {
            $id = $model['id'];
            if (str_contains($id, 'claude')) {
                $models[] = ['value' => $id, 'label' => $model['display_name'] ?? $id];
            }
        }

        // Most recent first (Anthropic returns them sorted already, but ensure it)
        usort($models, fn($a, $b) => strcmp($b['value'], $a['value']));
        return $models;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function fetchGoogle(): array
    {
        $apiKey = $this->getEncryptedConfig('ai/general/google_api_key');
        if (!$apiKey) {
            throw new Mage_Core_Exception('Google AI API key is not configured.');
        }

        $response = $this->getHttpClient()->request(
            'GET',
            "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}",
            ['timeout' => 10],
        );

        $data = $this->decode($response, 'Google');
        $models = [];
        foreach ($data['models'] ?? [] as $model) {
            $methods = $model['supportedGenerationMethods'] ?? [];
            if (!in_array('generateContent', $methods)) {
                continue;
            }
            // Strip "models/" prefix from name
            $id = str_replace('models/', '', $model['name']);
            $models[] = ['value' => $id, 'label' => $model['displayName'] ?? $id];
        }

        usort($models, fn($a, $b) => strcmp($a['value'], $b['value']));
        return $models;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function fetchMistral(): array
    {
        $apiKey = $this->getEncryptedConfig('ai/general/mistral_api_key');
        if (!$apiKey) {
            throw new Mage_Core_Exception('Mistral API key is not configured.');
        }

        $response = $this->getHttpClient()->request('GET', 'https://api.mistral.ai/v1/models', [
            'headers' => ['Authorization' => "Bearer {$apiKey}"],
            'timeout' => 10,
        ]);

        $data = $this->decode($response, 'Mistral');
        $models = [];
        foreach ($data['data'] ?? [] as $model) {
            $id = $model['id'];
            // Skip embedding models
            if (str_contains($id, 'embed')) {
                continue;
            }
            $models[] = ['value' => $id, 'label' => $id];
        }

        usort($models, fn($a, $b) => strcmp($a['value'], $b['value']));
        return $models;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function fetchOpenRouter(): array
    {
        $response = $this->getHttpClient()->request('GET', 'https://openrouter.ai/api/v1/models', [
            'timeout' => 15,
        ]);

        $data = $this->decode($response, 'OpenRouter');
        $models = [];
        foreach ($data['data'] ?? [] as $model) {
            $id = $model['id'];
            $name = $model['name'] ?? $id;
            $models[] = ['value' => $id, 'label' => $name];
        }

        usort($models, fn($a, $b) => strcmp($a['value'], $b['value']));
        return $models;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function fetchOllama(): array
    {
        $baseUrl = $this->getConfig('ai/general/ollama_base_url') ?: 'http://localhost:11434';
        $baseUrl = rtrim($baseUrl, '/');

        $response = $this->getHttpClient()->request('GET', "{$baseUrl}/api/tags", ['timeout' => 5]);

        $data = $this->decode($response, 'Ollama');
        $models = [];
        foreach ($data['models'] ?? [] as $model) {
            $id = $model['name'];
            $models[] = ['value' => $id, 'label' => $id];
        }

        usort($models, fn($a, $b) => strcmp($a['value'], $b['value']));
        return $models;
    }

    /**
     * Fetch models from a community provider's registered model fetcher.
     *
     * @return list<array{value: string, label: string}>
     * @throws Mage_Core_Exception
     */
    private function fetchFromRegistry(string $provider, string $capability): array
    {
        $config = Maho_Ai_Model_Platform::getProviderConfig($provider);
        if (!$config) {
            throw new Mage_Core_Exception("Unknown AI platform: {$provider}");
        }

        // Built-in providers can use model_fetcher_method (mirrors factory_method pattern)
        $fetcherMethod = (string) ($config->model_fetcher_method ?? '');
        if ($fetcherMethod && method_exists($this, $fetcherMethod)) {
            return $this->$fetcherMethod();
        }

        // Community providers use model_fetcher_class
        $fetcherClass = (string) ($config->model_fetcher_class ?? '');
        if (!$fetcherClass) {
            throw new Mage_Core_Exception("No model fetcher for provider: {$provider}");
        }

        $fetcher = new $fetcherClass();
        if (!($fetcher instanceof Maho_Ai_Model_Platform_ModelFetcherInterface)) {
            throw new Mage_Core_Exception(
                "Model fetcher '{$fetcherClass}' must implement ModelFetcherInterface.",
            );
        }

        return $fetcher->fetchModels($capability);
    }
}
