---
title: Artificial Intelligence
description: Maho's AI module routes a single helper to OpenAI, Anthropic, Google, Mistral, Ollama and more for product descriptions, reporting and image generation.
---

# Artificial Intelligence <span class="version-badge">v26.5+</span>

---

## 1. Introduction

Maho's **Artificial Intelligence** module is the first-party AI platform. It provides a unified `Mage::helper('ai')->invoke()` entry point that routes calls through [Symfony AI Platform](https://symfony.com/doc/current/ai/components/platform.html) bridges to OpenAI, Anthropic, Google, Mistral, OpenRouter, Ollama, or any OpenAI-compatible endpoint. Consumer modules use the same helper to add features like AI-generated product descriptions, natural-language reporting, or image generation without owning the per-provider plumbing.

### Built-in Providers

| Platform | Bridge | Capabilities |
|---|---|---|
| **OpenAI** | `Symfony\AI\Platform\Bridge\OpenAi` | Chat, Embeddings, Image |
| **Anthropic** | `Symfony\AI\Platform\Bridge\Anthropic` | Chat |
| **Google** | `Symfony\AI\Platform\Bridge\Gemini` | Chat, Embeddings |
| **Mistral** | `Symfony\AI\Platform\Bridge\Mistral` | Chat, Embeddings |
| **OpenRouter** | `Symfony\AI\Platform\Bridge\OpenRouter` | Chat, Image (meta-provider for GPT/Claude/Gemini/Llama via single key) |
| **Ollama** | `Symfony\AI\Platform\Bridge\Ollama` | Chat, Embeddings (local / self-hosted) |
| **Generic** | `Symfony\AI\Platform\Bridge\OpenAi` (custom base URL) | Chat, Embeddings, Image (LiteLLM, vLLM, any OpenAI-compatible endpoint) |

### Key Features

- **Single helper API** - `Mage::helper('ai')->invoke()` returns a string; same call shape across every provider
- **Sync or async** - call inline for interactive flows, queue for batch / long-running work
- **Async task queue** - cron sweep processes pending tasks with retry, timeout recovery, and callback dispatch
- **Safety guardrails** - prompt-injection patterns, configurable regex blocklist, output sanitisation, PII detection
- **Token telemetry** - per-task usage, aggregated daily
- **Vector storage** - `ai_vector` table for embedding-backed lookup / RAG
- **Admin UI** - Task History grid, Usage grid, Queue All Embeddings page, System Configuration with auto model-fetch
- **Extensible** - community providers plug in by extending `Maho_Ai_Model_Platform_Symfony` or registering a factory
- **Store-scoped configuration** - different stores can use different platforms / models / credentials

---

## 2. Getting Started

### Module Configuration

!!! note "Renamed in 26.9"
    The store-config section, the ACL resources, the cron ids and the three database tables lost the `maho_` vendor prefix in Maho 26.9: `maho_ai/general/enabled` became `ai/general/enabled`, `maho_ai_task` became `ai_task`, and so on. `./maho migrate` renames the tables, moves the `core_config_data` paths and moves the `admin_rule` resources for you. Update any custom code that reads a `maho_ai/...` config path or queries a `maho_ai_*` table.

Navigate to **System > Configuration > AI**. Configuration is grouped into the sections below; each provider has its own sub-fieldset for API key (encrypted via `ai/system_config_backend_apiKey`) and per-platform default model.

| Section | Purpose |
|---|---|
| **General** | Master enable, default chat platform, per-platform API keys + chat models, request logging |
| **Embed** | Master enable, default embed platform, per-platform embed models, auto-embed toggles |
| **Image** | Master enable, default image platform, per-platform image models, placeholder fallback |
| **Safety** | Input validator + output sanitiser toggles, blocked-pattern regex list, PII detection |
| **Queue** | Master enable, cron schedule, batch size, retry timeout |

Saving an API key (or the Ollama base URL) auto-fetches the provider's `/models` endpoint and caches the result, so dropdowns populate themselves on reload - no manual refresh.

### Picking a Default Platform

1. **System > Configuration > AI > General**
2. Set **Enabled** to **Yes**
3. Set **Default Platform** (e.g. `OpenAI`)
4. In the **OpenAI** sub-section, paste your API key into **API Key**
5. Save - the backend fetches OpenAI's model list in the same request, so the **Default Model** dropdown is populated on reload
6. Pick the **Default Model** (e.g. `gpt-4o-mini`) and Save again

Test from any controller or CLI:

```php
$response = Mage::helper('ai')->invoke(
    userMessage: 'Say hello.',
);
```

---

## 3. The `invoke()` API

The single entry point every consumer module uses.

### Signature

```php
public function invoke(
    string|array $userMessage,
    ?string $systemPrompt = null,
    ?string $platform = null,
    ?string $model = null,
    array $options = [],
    ?int $storeId = null,
    string $consumer = '_direct',
): string;
```

`$userMessage` accepts either a plain string (treated as a single user message and run through the injection validator) **or** a full messages array of `[['role' => 'user'|'system'|'assistant', 'content' => '...']]`. In array form, only `role === 'user'` entries are validated. **Never build the array form straight from end-user input** - the unvalidated `system`/`assistant` slots are for trusted, curated content.

### Examples

**Simple completion** - uses configured defaults:

```php
$response = Mage::helper('ai')->invoke(
    userMessage: 'Summarise this product description in 30 words: ' . $product->getDescription(),
);
```

**With system prompt** - sets the model's role / tone:

```php
$response = Mage::helper('ai')->invoke(
    userMessage: 'Write a product description for: ' . $product->getName(),
    systemPrompt: 'You are an e-commerce copywriter. Be concise and persuasive.',
);
```

**Override platform and model** - bypass defaults for this call:

```php
$response = Mage::helper('ai')->invoke(
    userMessage: $prompt,
    platform: Maho_Ai_Model_Platform::ANTHROPIC,
    model: 'claude-sonnet-4-6',
);
```

**With options** - temperature, max-tokens, HTML sanitisation:

```php
$response = Mage::helper('ai')->invoke(
    userMessage: $prompt,
    options: [
        'temperature' => 0.7,
        'max_tokens'  => 500,
        'is_html'     => true,  // run output through OutputSanitizer for safe HTML
    ],
);
```

### Model Resolution Order

When `$model` isn't passed explicitly:

1. If `$model` parameter is set → use it
2. Else use `ai/general/{platform}_model` for the selected platform (or `ai/embed/{platform}_model` / `ai/image/{platform}_model` for those capabilities)

A platform that has no configured default for the requested capability throws `Mage_Core_Exception("No {capability} model configured for {platform}")`.

---

## 4. Async Task Queue

For long-running calls, catalog-wide batch jobs, or anything you don't want to block a controller request - submit to the queue and let cron handle it.

### Submitting a Task

```php
$taskId = Mage::helper('ai')->submitTask([
    'consumer'        => 'catalog_product',
    'action'          => 'generate_description',
    'messages'        => [
        ['role' => 'user', 'content' => 'Write a description for: ' . $product->getName()],
    ],
    'platform'        => Maho_Ai_Model_Platform::ANTHROPIC,
    'model'           => 'claude-sonnet-4-6',
    'callback_class'  => 'My_Module_Model_Callback',  // must implement Maho_Ai_Model_TaskCallbackInterface
    'callback_method' => 'onComplete',
    'max_retries'     => 3,
    'priority'        => Maho_Ai_Model_Task::PRIORITY_BACKGROUND,
]);
```

Two specialised helpers exist for non-chat task types:

- `submitEmbedTask([...])` - submits an embedding task and (when `entity_type` + `entity_id` are passed in `data`) auto-persists the resulting vector to `ai_vector` on completion.
- `submitImageTask([...])` - submits an image-generation task. The prompt is run through the InputValidator (mirroring sync `generateImage()`).

User-role messages on `submitTask()` and the prompt on `submitImageTask()` go through the InputValidator just like their sync counterparts - the async path is **not** a bypass.

### Task Lifecycle

`pending` → `processing` → `complete` / `failed` / `cancelled`

| State | Meaning |
|---|---|
| `pending` | Queued, awaiting cron pickup |
| `processing` | Cron has picked it up and is calling the provider |
| `complete` | Provider returned successfully; callback fired |
| `failed` | Provider returned an error after `max_retries` attempts; callback fires with the error message in `$response` |
| `cancelled` | Cancelled by admin |

### Cron Behaviour

- Sweep runs on the schedule defined in `ai/queue/cron_schedule` (default `*/2 * * * *`)
- Processes up to `ai/queue/max_tasks_per_run` per run (default 10)
- **Stuck-task recovery**: anything in `processing` longer than `ai/queue/task_timeout` seconds (default 120) is re-queued; tasks past `max_retries` are marked `failed`
- **Weekly cleanup**: `complete` / `failed` / `cancelled` rows older than 90 days are dropped every Sunday at 03:00; abandoned `pending` rows older than 90 days are also cleared

### Callbacks

When a task completes (or fails after exhausting retries), the configured callback class is instantiated and the method is called with the task and the response (or error message). The callback class **must implement `Maho_Ai_Model_TaskCallbackInterface`** - this is a deliberate guard against arbitrary class instantiation from a crafted task row.

```php
class My_Module_Model_Callback implements Maho_Ai_Model_TaskCallbackInterface
{
    public function onComplete(Maho_Ai_Model_Task $task, string $response): void
    {
        if ($task->getData('status') === Maho_Ai_Model_Task::STATUS_FAILED) {
            // $response holds the error message
            return;
        }
        $product = Mage::getModel('catalog/product')->load(/* ... */);
        $product->setData('description', $response)->save();
    }
}
```

---

## 5. Safety

Both guardrails are **on by default** and apply to both sync and async entry points.

### InputValidator

Runs before each `invoke()`, `submitTask()`, sync `generateImage()`, and `submitImageTask()`. Checks user-role content against:

- **15 known prompt-injection patterns** (e.g. "ignore previous instructions", "DAN mode", role-rewrite attempts)
- **Configurable regex blocklist** - admin-set patterns in `ai/safety/blocked_patterns` (one per line)
- **Base64-payload heuristic** - flags long base64-like strings that may hide encoded instructions

When a request matches a pattern, the validator throws `Mage_Core_Exception` with `'AI request rejected: ...'`. Catch this in your consumer to surface a friendly error.

Sync `embed()` does not run the validator - embeddings are intended for trusted internal text (product names, descriptions, etc.) rather than untrusted user input. The same applies to `submitEmbedTask()`.

### OutputSanitizer

Runs against model responses. With `options['is_html'] === true` it:

- Strips dangerous tags (`<script>`, `<style>`, `<iframe>`, `<object>`, `<embed>`)
- Strips `on*=` event-handler attributes and `javascript:` / unsafe `data:` URLs from `href` / `src`
- **PII detection** (when `ai/safety/pii_detection` is enabled) - emails, credit-card-like numbers, AU phone numbers are flagged in the AI log but not blocked (the model may have produced them legitimately).

---

## 6. Embeddings + Vector Storage

For RAG, semantic search, similarity lookup, etc.

### Generating Embeddings

```php
// Single string → float[]
$vector = Mage::helper('ai')->embed(
    text: 'Some text to embed',
    platform: Maho_Ai_Model_Platform::OPENAI,
    model: 'text-embedding-3-small',
);

// Array of strings → float[][]
$vectors = Mage::helper('ai')->embed(
    text: ['First text to embed', 'Second text to embed'],
);
```

If `ai/embed/target_dimensions` is set, it is passed through as the `dimensions` option (for providers that support reduced-dimension embeddings like OpenAI's `text-embedding-3-*`).

### Storing in `ai_vector`

```php
/** @var Maho_Ai_Model_Resource_Vector $resource */
$resource = Mage::getResourceSingleton('ai/vector');
$resource->saveForEntity(
    entityType: 'catalog_product',
    entityId:   (int) $product->getId(),
    storeId:    (int) $product->getStoreId(),
    vector:     $vector,
    dimensions: count($vector),
    platform:   Maho_Ai_Model_Platform::OPENAI,
    model:      'text-embedding-3-small',
);
```

`saveForEntity()` is an upsert: storing again for the same `entity_type` / `entity_id` / `store_id` triple replaces the previous row.

Fetching back:

```php
$data = Mage::getResourceSingleton('ai/vector')
    ->getForEntity('catalog_product', (int) $product->getId(), (int) $product->getStoreId());
// $data is array{vector, model, platform, dimensions, updated_at}|null
```

For async embedding + auto-persist in one call, use `submitEmbedTask()` with `entity_type` + `entity_id` in the data.

---

## 7. Image Generation

```php
$imageUrl = Mage::helper('ai')->generateImage(
    prompt: 'A minimalist product photo of a black coffee mug on a white background',
    platform: Maho_Ai_Model_Platform::OPENAI,
    model: 'dall-e-3',
    options: ['size' => '1024x1024'],
);
```

Returns a data URI by default (portable across providers regardless of upload behaviour). When the image capability is disabled and `ai/image/fallback_placeholder` is on, returns a placeholder URL from `ai/image/placeholder_url`.

---

## 8. Usage Telemetry

Every sync `invoke()` / `embed()` / `generateImage()` call records a row to `ai_usage` (when `ai/general/log_requests` is enabled). Async tasks record their own per-task usage on the task row; a nightly cron (05 minutes past midnight) aggregates the previous day's completed task rows into `ai_usage` grouped by consumer / platform / model / store.

| Field | Description |
|---|---|
| `consumer` | Module name passed by the caller (`'_direct'` for inline calls without a consumer) |
| `platform` | Provider code (openai, anthropic, ...) |
| `model` | Resolved model string |
| `input_tokens` / `output_tokens` | Reported by the provider |
| `request_count` | Number of calls in this aggregate |
| `period_date` | Aggregation date |

Async calls show up in the Usage grid starting the day after they complete (because aggregation is nightly). Sync calls appear immediately.

---

## 9. Admin UI

Four admin pages under **System > AI** (and a configuration page at **System > Configuration > AI**):

### Task History

Full async queue grid - filter by status / consumer / platform / model, sort by creation time / tokens. Click any row to view full prompt, response, and stack trace (for failures).

### Usage

Daily aggregated grid - token totals per consumer / platform / model. Filter by date range to see usage patterns.

### Queue All Embeddings

Tools for vector store maintenance: queue embedding tasks for an entity type, clear vectors, recompute usage aggregates.

### System Configuration > AI

Per-provider API keys (encrypted), default models, safety toggles, queue settings. Saving an API key (or the Ollama base URL) auto-fetches the provider's `/models` endpoint and caches the result, so dropdowns stay current as providers ship new models. Re-save the key to force a refresh.

ACL is granular: `system/ai/tasks`, `system/ai/usage`, `system/ai/reindex` are separate resources, so an admin role with general config access doesn't automatically get permission to queue paid embedding tasks.

---

## 10. Dev Guide: Extending With Community Providers

The recommended pattern for adding a new provider: **extend `Maho_Ai_Model_Platform_Symfony`**. You inherit everything Maho-side (encrypted config wiring, model resolution, token-usage capture in `{input, output}` shape, custom `ModelCatalog` plumbing, exception types) and only override what's actually different about your provider.

### Why extend `Maho_Ai_Model_Platform_Symfony`?

Symfony AI Platform's bridges already do the HTTP work for OpenAI, Anthropic, Gemini, Mistral, OpenRouter, Ollama, and any OpenAI-compatible endpoint (NanoGPT, LiteLLM, vLLM, Azure OpenAI, etc.). The Maho shim sits on top of that and adds:

- Encrypted API key retrieval from store config
- Model resolution order (`ai/{capability}/{platform}_model` → constructor defaults)
- Token-usage normalisation to Maho's `{input, output}` shape
- Custom `ModelCatalog` so admin-set model IDs not in Symfony's built-in catalog still resolve (e.g. dated variants like `gpt-4o-mini-2024-07-18`)
- Maho's `Mage_Core_Exception` from provider errors instead of Symfony's exception hierarchy

The shim is non-final and exposes the following `protected` extension surface:

| Member | Purpose |
|---|---|
| `protected readonly PlatformInterface $platform` | The Symfony bridge instance you built in the subclass constructor |
| `protected readonly string $platformCode` | Your provider code (`'nanogpt'`, `'azureopenai'`, ...) |
| `protected readonly string $defaultChatModel` / `$defaultEmbedModel` / `$defaultImageModel` | Defaults passed through the constructor |
| `protected string $lastModel` / `$lastEmbedModel` / `$lastImageModel` | Update from your overrides so `getLast…Model()` reports correctly |
| `protected array $lastTokenUsage` / `$lastEmbedTokenUsage` | Update from your overrides so `getLast…TokenUsage()` reports correctly |
| `protected function buildMessageBag(array $messages): MessageBag` | Maho `[{role, content}]` → Symfony `MessageBag` |
| `protected function mapChatOptions(array $options): array` | Maho options → Symfony invoke options |
| `protected function mapEmbedOptions(array $options): array` | (same, for embeddings) |
| `protected function mapImageOptions(array $options): array` | (same, for image gen) |
| `protected function captureChatMetadata(DeferredResult $deferred, string $model): void` | Pulls token usage + model from the response and stores them |
| `protected function extractTokenUsage(DeferredResult $deferred): ?TokenUsage` | Token-usage extraction only - for custom flows |

### Step 1: Provider Class

For an OpenAI-compatible chat endpoint at a custom host (the NanoGPT pattern):

```php
// app/code/community/My/Module/Model/Platform/Foo.php
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;

class My_Module_Model_Platform_Foo extends Maho_Ai_Model_Platform_Symfony
{
    public function __construct(string $apiKey, string $defaultChatModel)
    {
        $catalog = new \Symfony\AI\Platform\Bridge\OpenAi\ModelCatalog([
            $defaultChatModel => [
                'class'        => \Symfony\AI\Platform\Bridge\OpenAi\Gpt::class,
                'capabilities' => [
                    \Symfony\AI\Platform\Capability::INPUT_MESSAGES,
                    \Symfony\AI\Platform\Capability::OUTPUT_TEXT,
                ],
            ],
        ]);

        parent::__construct(
            platform:         OpenAiPlatformFactory::create($apiKey, host: 'api.foo.example/v1', modelCatalog: $catalog),
            platformCode:     'foo',
            defaultChatModel: $defaultChatModel,
        );
    }
}
```

Chat works out of the box, inherited from the parent. For a custom-shaped image endpoint (where the provider accepts extra fields the OpenAI bridge doesn't expose), override `generateImage()`:

```php
    #[\Override]
    public function generateImage(string $prompt, array $options = []): string
    {
        $model = (string) ($options['model'] ?? $this->defaultImageModel);
        $this->lastImageModel = $model;

        $response = \Symfony\Component\HttpClient\HttpClient::create()->request('POST', 'https://api.foo.example/v1/images', [
            'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
            'json'    => ['model' => $model, 'prompt' => $prompt, /* ... */],
        ]);
        $data = $response->toArray(false);
        return $data['data'][0]['url'] ?? '';
    }
```

For a fully custom non-OpenAI-shaped endpoint (e.g. video, where Symfony has no bridge), implement the relevant `Maho_Ai_Model_Platform_*Interface` and add direct HTTP logic.

### Step 2: Factory Class

Builds the provider from store config and returns the instance to `Maho_Ai_Model_Platform_Factory`:

```php
// app/code/community/My/Module/Model/Platform/Foo/Factory.php
class My_Module_Model_Platform_Foo_Factory
    implements Maho_Ai_Model_Platform_ProviderFactoryInterface
{
    #[\Override]
    public function create(?int $storeId = null): Maho_Ai_Model_Platform_ProviderInterface
    {
        $apiKey = (string) Mage::helper('core')->decrypt(
            (string) Mage::getStoreConfig('ai/general/foo_api_key', $storeId),
        );
        if ($apiKey === '') {
            throw new Mage_Core_Exception('Foo API key is not configured.');
        }

        return new My_Module_Model_Platform_Foo(
            apiKey:           $apiKey,
            defaultChatModel: (string) Mage::getStoreConfig('ai/general/foo_model', $storeId) ?: 'foo-default',
        );
    }
}
```

### Step 3: Register in `config.xml`

```xml
<config>
    <global>
        <ai>
            <providers>
                <foo>
                    <label>Foo AI</label>
                    <factory_class>My_Module_Model_Platform_Foo_Factory</factory_class>
                    <capabilities>chat</capabilities>
                    <sort_order>100</sort_order>
                </foo>
            </providers>
        </ai>
    </global>
</config>
```

Done. `Mage::helper('ai')->invoke(platform: 'foo')` now works, and `foo` appears in the System Configuration default-platform dropdown.

---

## 11. Troubleshooting

### "AI request rejected: ..."

The InputValidator matched a known injection pattern or admin-configured blocklist regex. The specific reason is included in the exception message and in `var/log/ai.log`. Review the blocked pattern list under **System > Configuration > AI > Safety**.

### Tasks stuck in `processing`

The stuck-task recovery cron resets anything older than `ai/queue/task_timeout` seconds (default 120). It runs on the same schedule as the queue sweep (`ai/queue/cron_schedule`), so adjusting the sweep frequency also affects how quickly stuck tasks are recovered.

### Model dropdown is empty / outdated

Re-save the provider's API key in **System > Configuration > AI > General** - the `ai/system_config_backend_apiKey` backend re-fetches the provider's `/models` endpoint on every save where the value actually changed. For community providers, register a `<model_fetcher_class>` in your config XML implementing `Maho_Ai_Model_Platform_ModelFetcherInterface`; otherwise the field falls back to a free-text input.

### "No image model configured for {platform}"

The selected platform has no default image model set in store config. Either pick a different platform for the image call (e.g. `openai` for DALL·E), or wire up an image model under **System > Configuration > AI > Image > {platform}**.

### Checking Logs

| File | What's in it |
|---|---|
| `var/log/ai.log` | Request logging (enable in System Config), validator rejections, provider errors, callback failures |
| Task History grid | Full prompt + response + stack trace for every async task |

### Getting Help

- Issue tracker: [github.com/MahoCommerce/maho/issues](https://github.com/MahoCommerce/maho/issues)
- Reference issue: [#468 Feature: Base AI Module](https://github.com/MahoCommerce/maho/issues/468)
