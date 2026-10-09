<?php

/**
 * WordPress AI Client types (the WordPress\\AiClient namespaces and their scoped
 * dependencies), extracted from php-stubs/wordpress-stubs v7.1.2. PHPStan scans
 * this file; it is never loaded at runtime, where WordPress provides the classes.
 *
 * Drop it once szepeviktor/phpstan-wordpress allows wordpress-stubs 7.x.
 */

// phpcs:ignoreFile

namespace WordPress\AiClientDependencies\Psr\SimpleCache {
    interface CacheInterface
    {
        /**
         * Fetches a value from the cache.
         *
         * @param string $key     The unique key of this item in the cache.
         * @param mixed  $default Default value to return if the key does not exist.
         *
         * @return mixed The value of the item from the cache, or $default in case of cache miss.
         *
         * @throws \Psr\SimpleCache\InvalidArgumentException
         *   MUST be thrown if the $key string is not a legal value.
         */
        public function get($key, $default = null);
        /**
         * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
         *
         * @param string                 $key   The key of the item to store.
         * @param mixed                  $value The value of the item to store, must be serializable.
         * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
         *                                      the driver supports TTL then the library may set a default value
         *                                      for it or let the driver take care of that.
         *
         * @return bool True on success and false on failure.
         *
         * @throws \Psr\SimpleCache\InvalidArgumentException
         *   MUST be thrown if the $key string is not a legal value.
         */
        public function set($key, $value, $ttl = null);
        /**
         * Delete an item from the cache by its unique key.
         *
         * @param string $key The unique cache key of the item to delete.
         *
         * @return bool True if the item was successfully removed. False if there was an error.
         *
         * @throws \Psr\SimpleCache\InvalidArgumentException
         *   MUST be thrown if the $key string is not a legal value.
         */
        public function delete($key);
        /**
         * Wipes clean the entire cache's keys.
         *
         * @return bool True on success and false on failure.
         */
        public function clear();
        /**
         * Obtains multiple cache items by their unique keys.
         *
         * @param iterable $keys    A list of keys that can obtained in a single operation.
         * @param mixed    $default Default value to return for keys that do not exist.
         *
         * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
         *
         * @throws \Psr\SimpleCache\InvalidArgumentException
         *   MUST be thrown if $keys is neither an array nor a Traversable,
         *   or if any of the $keys are not a legal value.
         */
        public function getMultiple($keys, $default = null);
        /**
         * Persists a set of key => value pairs in the cache, with an optional TTL.
         *
         * @param iterable               $values A list of key => value pairs for a multiple-set operation.
         * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
         *                                       the driver supports TTL then the library may set a default value
         *                                       for it or let the driver take care of that.
         *
         * @return bool True on success and false on failure.
         *
         * @throws \Psr\SimpleCache\InvalidArgumentException
         *   MUST be thrown if $values is neither an array nor a Traversable,
         *   or if any of the $values are not a legal value.
         */
        public function setMultiple($values, $ttl = null);
        /**
         * Deletes multiple cache items in a single operation.
         *
         * @param iterable $keys A list of string-based keys to be deleted.
         *
         * @return bool True if the items were successfully removed. False if there was an error.
         *
         * @throws \Psr\SimpleCache\InvalidArgumentException
         *   MUST be thrown if $keys is neither an array nor a Traversable,
         *   or if any of the $keys are not a legal value.
         */
        public function deleteMultiple($keys);
        /**
         * Determines whether an item is present in the cache.
         *
         * NOTE: It is recommended that has() is only to be used for cache warming type purposes
         * and not to be used within your live applications operations for get/set, as this method
         * is subject to a race condition where your has() will return true and immediately after,
         * another script can remove it making the state of your app out of date.
         *
         * @param string $key The cache item key.
         *
         * @return bool
         *
         * @throws \Psr\SimpleCache\InvalidArgumentException
         *   MUST be thrown if the $key string is not a legal value.
         */
        public function has($key);
    }
}
namespace WordPress\AiClientDependencies\Http\Discovery\Strategy {
    /**
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     */
    interface DiscoveryStrategy
    {
        /**
         * Find a resource of a specific type.
         *
         * @param string $type
         *
         * @return array The return value is always an array with zero or more elements. Each
         *               element is an array with two keys ['class' => string, 'condition' => mixed].
         *
         * @throws \WordPress\AiClientDependencies\Http\Discovery\Exception\StrategyUnavailableException if we cannot use this strategy
         */
        public static function getCandidates($type);
    }
}
namespace WordPress\AiClient\Providers\Http\Abstracts {
    /**
     * Abstract discovery strategy for HTTP client implementations.
     *
     * Provides a base for registering custom HTTP client implementations
     * with HTTPlug's discovery mechanism. Subclasses must implement
     * the createClient() method to provide their specific PSR-18
     * HTTP client instance using the provided Psr17Factory.
     *
     * @since 1.1.0
     */
    abstract class AbstractClientDiscoveryStrategy implements \WordPress\AiClientDependencies\Http\Discovery\Strategy\DiscoveryStrategy
    {
        /**
         * Initializes and registers the discovery strategy.
         *
         * @since 1.1.0
         *
         * @return void
         */
        public static function init(): void
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 1.1.0
         *
         * @param string $type The type of discovery.
         * @return array<array<string, mixed>> The discovery candidates.
         */
        public static function getCandidates($type)
        {
        }
        /**
         * Creates an instance of the HTTP client.
         *
         * Subclasses must implement this method to return their specific
         * PSR-18 HTTP client instance. The provided Psr17Factory implements
         * all PSR-17 interfaces (RequestFactory, ResponseFactory, StreamFactory,
         * etc.) and can be used to satisfy client constructor dependencies.
         *
         * @since 1.1.0
         *
         * @param \WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory $psr17Factory The PSR-17 factory for creating HTTP messages.
         * @return \WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface The PSR-18 HTTP client.
         */
        abstract protected static function createClient(\WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory $psr17Factory): \WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface;
    }
}
namespace WordPress\AiClientDependencies\Psr\EventDispatcher {
    /**
     * Defines a dispatcher for events.
     */
    interface EventDispatcherInterface
    {
        /**
         * Provide all relevant listeners with an event to process.
         *
         * @param object $event
         *   The object to process.
         *
         * @return object
         *   The Event that was passed, now modified by listeners.
         */
        public function dispatch(object $event);
    }
}
namespace WordPress\AiClientDependencies\Psr\Http\Client {
    interface ClientInterface
    {
        /**
         * Sends a PSR-7 request and returns a PSR-7 response.
         *
         * @param \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface $request
         *
         * @return \WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface
         *
         * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
         */
        public function sendRequest(\WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface $request): \WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface;
    }
}
namespace WordPress\AiClient\Providers\Http\Contracts {
    /**
     * Interface for HTTP clients that support per-request transport options.
     *
     * Extends the capabilities of PSR-18 clients by allowing custom transport
     * configuration such as timeouts and redirect handling on each request.
     *
     * @since 0.2.0
     */
    interface ClientWithOptionsInterface
    {
        /**
         * Sends an HTTP request with the given transport options.
         *
         * @since 0.2.0
         *
         * @param \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface $request The PSR-7 request to send.
         * @param \WordPress\AiClient\Providers\Http\DTO\RequestOptions $options The request transport options. Must not be null.
         * @return \WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface The PSR-7 response received.
         */
        public function sendRequestWithOptions(\WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface $request, \WordPress\AiClient\Providers\Http\DTO\RequestOptions $options): \WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface;
    }
}
namespace WordPress\AiClient {
    /**
     * Main AI Client class providing both fluent and traditional APIs for AI operations.
     *
     * This class serves as the primary entry point for AI operations, offering:
     * - Fluent API for easy-to-read chained method calls
     * - Traditional API for array-based configuration (WordPress style)
     * - Integration with provider registry for model discovery
     * - Support for three model specification approaches
     *
     * All model requirements analysis and capability matching is handled
     * automatically by the PromptBuilder, which provides intelligent model
     * discovery based on prompt content and configuration.
     *
     * ## Model Specification Approaches
     *
     * ### 1. Specific Model Instance
     * Use a specific ModelInterface instance when you know exactly which model to use:
     * ```php
     * $model = $registry->getProvider('openai')->getModel('gpt-4');
     * $result = AiClient::generateTextResult('What is PHP?', $model);
     * ```
     *
     * ### 2. ModelConfig for Auto-Discovery
     * Use ModelConfig to specify requirements and let the system discover the best model:
     * ```php
     * $config = new ModelConfig();
     * $config->setTemperature(0.7);
     * $config->setMaxTokens(150);
     *
     * $result = AiClient::generateTextResult('What is PHP?', $config);
     * ```
     *
     * ### 3. Automatic Discovery (Default)
     * Pass null or omit the parameter for intelligent model discovery based on prompt content:
     * ```php
     * // System analyzes prompt and selects appropriate model automatically
     * $result = AiClient::generateTextResult('What is PHP?');
     * $imageResult = AiClient::generateImageResult('A sunset over mountains');
     * ```
     *
     * ## Fluent API Examples
     * ```php
     * // Fluent API with automatic model discovery
     * $result = AiClient::prompt('Generate an image of a sunset')
     *     ->usingTemperature(0.7)
     *     ->generateImageResult();
     *
     * // Fluent API with specific model
     * $result = AiClient::prompt('What is PHP?')
     *     ->usingModel($specificModel)
     *     ->usingTemperature(0.5)
     *     ->generateTextResult();
     *
     * // Fluent API with model configuration
     * $result = AiClient::prompt('Explain quantum physics')
     *     ->usingModelConfig($config)
     *     ->generateTextResult();
     * ```
     *
     * @since 0.1.0
     *
     * @phpstan-import-type Prompt from \WordPress\AiClient\Builders\PromptBuilder
     *
     * phpcs:ignore Generic.Files.LineLength.TooLong
     */
    class AiClient
    {
        /**
         * @var string The version of the AI Client.
         */
        public const VERSION = '1.3.1';
        /**
         * Gets the default provider registry instance.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\ProviderRegistry The default provider registry.
         */
        public static function defaultRegistry(): \WordPress\AiClient\Providers\ProviderRegistry
        {
        }
        /**
         * Sets the event dispatcher for prompt lifecycle events.
         *
         * The event dispatcher will be used to dispatch BeforeGenerateResultEvent and
         * AfterGenerateResultEvent during prompt generation.
         *
         * @since 0.4.0
         *
         * @param \WordPress\AiClientDependencies\Psr\EventDispatcher\EventDispatcherInterface|null $dispatcher The event dispatcher, or null to disable.
         * @return void
         */
        public static function setEventDispatcher(?\WordPress\AiClientDependencies\Psr\EventDispatcher\EventDispatcherInterface $dispatcher): void
        {
        }
        /**
         * Gets the event dispatcher for prompt lifecycle events.
         *
         * @since 0.4.0
         *
         * @return \WordPress\AiClientDependencies\Psr\EventDispatcher\EventDispatcherInterface|null The event dispatcher, or null if not set.
         */
        public static function getEventDispatcher(): ?\WordPress\AiClientDependencies\Psr\EventDispatcher\EventDispatcherInterface
        {
        }
        /**
         * Sets the PSR-16 cache for storing and retrieving cached data.
         *
         * The cache can be used to store AI responses and other data to avoid
         * redundant API calls and improve performance.
         *
         * @since 0.4.0
         *
         * @param \WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface|null $cache The PSR-16 cache instance, or null to disable caching.
         * @return void
         */
        public static function setCache(?\WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface $cache): void
        {
        }
        /**
         * Gets the PSR-16 cache instance.
         *
         * @since 0.4.0
         *
         * @return \WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface|null The cache instance, or null if not set.
         */
        public static function getCache(): ?\WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface
        {
        }
        /**
         * Checks if a provider is configured and available for use.
         *
         * Supports multiple input formats for developer convenience:
         * - ProviderAvailabilityInterface: Direct availability check
         * - string (provider ID): e.g., AiClient::isConfigured('openai')
         * - string (class name): e.g., AiClient::isConfigured(OpenAiProvider::class)
         *
         * When using string input, this method leverages the ProviderRegistry's centralized
         * dependency management, ensuring HttpTransporter and authentication are properly
         * injected into availability instances.
         *
         * @since 0.1.0
         * @since 0.2.0 Now supports being passed a provider ID or class name.
         *
         * @param \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface|string|class-string<\WordPress\AiClient\Providers\Contracts\ProviderInterface> $availabilityOrIdOrClassName
         *        The provider availability instance, provider ID, or provider class name.
         * @return bool True if the provider is configured and available, false otherwise.
         */
        public static function isConfigured($availabilityOrIdOrClassName): bool
        {
        }
        /**
         * Creates a new prompt builder for fluent API usage.
         *
         * Returns a PromptBuilder instance configured with the specified or default registry.
         * The traditional API methods in this class delegate to PromptBuilder
         * for all generation logic.
         *
         * @since 0.1.0
         *
         * @param Prompt $prompt Optional initial prompt content.
         * @param \WordPress\AiClient\Providers\ProviderRegistry|null $registry Optional custom registry. If null, uses default.
         * @return \WordPress\AiClient\Builders\PromptBuilder The prompt builder instance.
         */
        public static function prompt($prompt = null, ?\WordPress\AiClient\Providers\ProviderRegistry $registry = null): \WordPress\AiClient\Builders\PromptBuilder
        {
        }
        /**
         * Generates content using a unified API that automatically detects model capabilities.
         *
         * When no model is provided, this method delegates to PromptBuilder for intelligent
         * model discovery based on prompt content and configuration. When a model is provided,
         * it infers the capability from the model's interfaces and delegates to the capability-based method.
         *
         * @since 0.1.0
         *
         * @param Prompt $prompt The prompt content.
         * @param \WordPress\AiClient\Providers\Models\Contracts\ModelInterface|\WordPress\AiClient\Providers\Models\DTO\ModelConfig $modelOrConfig Specific model to use, or model configuration
         *                                                  for auto-discovery.
         * @param \WordPress\AiClient\Providers\ProviderRegistry|null $registry Optional custom registry. If null, uses default.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The generation result.
         *
         * @throws \InvalidArgumentException If the provided model doesn't support any known generation type.
         * @throws \RuntimeException If no suitable model can be found for the prompt.
         */
        public static function generateResult($prompt, $modelOrConfig, ?\WordPress\AiClient\Providers\ProviderRegistry $registry = null): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Generates text using the traditional API approach.
         *
         * @since 0.1.0
         *
         * @param Prompt $prompt The prompt content.
         * @param \WordPress\AiClient\Providers\Models\Contracts\ModelInterface|\WordPress\AiClient\Providers\Models\DTO\ModelConfig|null $modelOrConfig Optional specific model to use,
         *                                                        or model configuration for auto-discovery,
         *                                                        or null for defaults.
         * @param \WordPress\AiClient\Providers\ProviderRegistry|null $registry Optional custom registry. If null, uses default.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The generation result.
         *
         * @throws \InvalidArgumentException If the prompt format is invalid.
         * @throws \RuntimeException If no suitable model is found.
         */
        public static function generateTextResult($prompt, $modelOrConfig = null, ?\WordPress\AiClient\Providers\ProviderRegistry $registry = null): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Generates an image using the traditional API approach.
         *
         * @since 0.1.0
         *
         * @param Prompt $prompt The prompt content.
         * @param \WordPress\AiClient\Providers\Models\Contracts\ModelInterface|\WordPress\AiClient\Providers\Models\DTO\ModelConfig|null $modelOrConfig Optional specific model to use,
         *                                                        or model configuration for auto-discovery,
         *                                                        or null for defaults.
         * @param \WordPress\AiClient\Providers\ProviderRegistry|null $registry Optional custom registry. If null, uses default.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The generation result.
         *
         * @throws \InvalidArgumentException If the prompt format is invalid.
         * @throws \RuntimeException If no suitable model is found.
         */
        public static function generateImageResult($prompt, $modelOrConfig = null, ?\WordPress\AiClient\Providers\ProviderRegistry $registry = null): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Converts text to speech using the traditional API approach.
         *
         * @since 0.1.0
         *
         * @param Prompt $prompt The prompt content.
         * @param \WordPress\AiClient\Providers\Models\Contracts\ModelInterface|\WordPress\AiClient\Providers\Models\DTO\ModelConfig|null $modelOrConfig Optional specific model to use,
         *                                                        or model configuration for auto-discovery,
         *                                                        or null for defaults.
         * @param \WordPress\AiClient\Providers\ProviderRegistry|null $registry Optional custom registry. If null, uses default.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The generation result.
         *
         * @throws \InvalidArgumentException If the prompt format is invalid.
         * @throws \RuntimeException If no suitable model is found.
         */
        public static function convertTextToSpeechResult($prompt, $modelOrConfig = null, ?\WordPress\AiClient\Providers\ProviderRegistry $registry = null): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Generates speech using the traditional API approach.
         *
         * @since 0.1.0
         *
         * @param Prompt $prompt The prompt content.
         * @param \WordPress\AiClient\Providers\Models\Contracts\ModelInterface|\WordPress\AiClient\Providers\Models\DTO\ModelConfig|null $modelOrConfig Optional specific model to use,
         *                                                        or model configuration for auto-discovery,
         *                                                        or null for defaults.
         * @param \WordPress\AiClient\Providers\ProviderRegistry|null $registry Optional custom registry. If null, uses default.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The generation result.
         *
         * @throws \InvalidArgumentException If the prompt format is invalid.
         * @throws \RuntimeException If no suitable model is found.
         */
        public static function generateSpeechResult($prompt, $modelOrConfig = null, ?\WordPress\AiClient\Providers\ProviderRegistry $registry = null): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Generates a video using the traditional API approach.
         *
         * @since 1.3.0
         *
         * @param Prompt $prompt The prompt content.
         * @param \WordPress\AiClient\Providers\Models\Contracts\ModelInterface|\WordPress\AiClient\Providers\Models\DTO\ModelConfig|null $modelOrConfig Optional specific model to use,
         *                                                        or model configuration for auto-discovery,
         *                                                        or null for defaults.
         * @param \WordPress\AiClient\Providers\ProviderRegistry|null $registry Optional custom registry. If null, uses default.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The generation result.
         *
         * @throws \InvalidArgumentException If the prompt format is invalid.
         * @throws \RuntimeException If no suitable model is found.
         */
        public static function generateVideoResult($prompt, $modelOrConfig = null, ?\WordPress\AiClient\Providers\ProviderRegistry $registry = null): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Creates a new message builder for fluent API usage.
         *
         * This method will be implemented once MessageBuilder is available.
         * MessageBuilder will provide a fluent interface for constructing complex
         * messages with multiple parts, attachments, and metadata.
         *
         * @since 0.1.0
         *
         * @param string|null $text Optional initial message text.
         * @return object MessageBuilder instance (type will be updated when MessageBuilder is available).
         *
         * @throws \RuntimeException When MessageBuilder is not yet available.
         */
        public static function message(?string $text = null)
        {
        }
    }
}
namespace WordPress\AiClient\Builders {
    /**
     * Fluent builder for constructing AI messages.
     *
     * This class provides a fluent interface for building messages with various
     * content types including text, files, function calls, and function responses.
     *
     * @since 0.2.0
     *
     * @phpstan-import-type MessagePartArrayShape from \WordPress\AiClient\Messages\DTO\MessagePart
     *
     * @phpstan-type Input string|\WordPress\AiClient\Messages\DTO\MessagePart|MessagePartArrayShape|\WordPress\AiClient\Files\DTO\File|\WordPress\AiClient\Tools\DTO\FunctionCall|\WordPress\AiClient\Tools\DTO\FunctionResponse|null
     */
    class MessageBuilder
    {
        /**
         * @var \WordPress\AiClient\Messages\Enums\MessageRoleEnum|null The role of the message sender.
         */
        protected ?\WordPress\AiClient\Messages\Enums\MessageRoleEnum $role = null;
        /**
         * @var list<\WordPress\AiClient\Messages\DTO\MessagePart> The parts that make up the message.
         */
        protected array $parts = [];
        /**
         * Constructor.
         *
         * @since 0.2.0
         *
         * @param Input $input Optional initial content.
         * @param \WordPress\AiClient\Messages\Enums\MessageRoleEnum|null $role Optional role.
         * @phpstan-return void
         */
        public function __construct($input = null, ?\WordPress\AiClient\Messages\Enums\MessageRoleEnum $role = null)
        {
        }
        /**
         * Creates a deep clone of this builder.
         *
         * Clones all MessagePart objects in the parts array to ensure
         * the cloned builder is independent of the original.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
        /**
         * Sets the role of the message sender.
         *
         * @since 0.2.0
         *
         * @param \WordPress\AiClient\Messages\Enums\MessageRoleEnum $role The role to set.
         * @return self
         */
        public function usingRole(\WordPress\AiClient\Messages\Enums\MessageRoleEnum $role): self
        {
        }
        /**
         * Sets the role to user.
         *
         * @since 0.2.0
         *
         * @return self
         */
        public function usingUserRole(): self
        {
        }
        /**
         * Sets the role to model.
         *
         * @since 0.2.0
         *
         * @return self
         */
        public function usingModelRole(): self
        {
        }
        /**
         * Adds text content to the message.
         *
         * @since 0.2.0
         *
         * @param string $text The text to add.
         * @return self
         * @throws \InvalidArgumentException If the text is empty.
         */
        public function withText(string $text): self
        {
        }
        /**
         * Adds a file to the message.
         *
         * Accepts:
         * - File object
         * - URL string (remote file)
         * - Base64-encoded data string
         * - Data URI string (data:mime/type;base64,data)
         * - Local file path string
         *
         * @since 0.2.0
         *
         * @param string|\WordPress\AiClient\Files\DTO\File $file The file to add.
         * @param string|null $mimeType Optional MIME type (ignored if File object provided).
         * @return self
         * @throws \InvalidArgumentException If the file is invalid.
         */
        public function withFile($file, ?string $mimeType = null): self
        {
        }
        /**
         * Adds a function call to the message.
         *
         * @since 0.2.0
         *
         * @param \WordPress\AiClient\Tools\DTO\FunctionCall $functionCall The function call to add.
         * @return self
         */
        public function withFunctionCall(\WordPress\AiClient\Tools\DTO\FunctionCall $functionCall): self
        {
        }
        /**
         * Adds a function response to the message.
         *
         * @since 0.2.0
         *
         * @param \WordPress\AiClient\Tools\DTO\FunctionResponse $functionResponse The function response to add.
         * @return self
         */
        public function withFunctionResponse(\WordPress\AiClient\Tools\DTO\FunctionResponse $functionResponse): self
        {
        }
        /**
         * Adds multiple message parts to the message.
         *
         * @since 0.2.0
         *
         * @param \WordPress\AiClient\Messages\DTO\MessagePart ...$parts The message parts to add.
         * @return self
         */
        public function withMessageParts(\WordPress\AiClient\Messages\DTO\MessagePart ...$parts): self
        {
        }
        /**
         * Builds and returns the Message object.
         *
         * @since 0.2.0
         *
         * @return \WordPress\AiClient\Messages\DTO\Message The built message.
         * @throws \InvalidArgumentException If the message validation fails.
         */
        public function get(): \WordPress\AiClient\Messages\DTO\Message
        {
        }
    }
    /**
     * Fluent builder for constructing AI prompts.
     *
     * This class provides a fluent interface for building prompts with various
     * content types and model configurations. It automatically infers model
     * requirements based on the features used in the prompt.
     *
     * @since 0.1.0
     *
     * @phpstan-import-type MessageArrayShape from \WordPress\AiClient\Messages\DTO\Message
     * @phpstan-import-type MessagePartArrayShape from \WordPress\AiClient\Messages\DTO\MessagePart
     *
     * @phpstan-type Prompt string|\WordPress\AiClient\Messages\DTO\MessagePart|\WordPress\AiClient\Messages\DTO\Message|MessageArrayShape|list<string|\WordPress\AiClient\Messages\DTO\MessagePart|MessagePartArrayShape>|list<\WordPress\AiClient\Messages\DTO\Message>|null
     */
    class PromptBuilder
    {
        /**
         * @var list<\WordPress\AiClient\Messages\DTO\Message> The messages in the conversation.
         */
        protected array $messages = [];
        /**
         * @var \WordPress\AiClient\Providers\Models\Contracts\ModelInterface|null The model to use for generation.
         */
        protected ?\WordPress\AiClient\Providers\Models\Contracts\ModelInterface $model = null;
        /**
         * @var list<string> Ordered list of preference keys to check when selecting a model.
         */
        protected array $modelPreferenceKeys = [];
        /**
         * @var string|null The provider ID or class name.
         */
        protected ?string $providerIdOrClassName = null;
        /**
         * @var \WordPress\AiClient\Providers\Models\DTO\ModelConfig The model configuration.
         */
        protected \WordPress\AiClient\Providers\Models\DTO\ModelConfig $modelConfig;
        /**
         * @var \WordPress\AiClient\Providers\Http\DTO\RequestOptions|null The request options for HTTP transport.
         */
        protected ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions $requestOptions = null;
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\ProviderRegistry $registry The provider registry for finding suitable models.
         * @param Prompt $prompt Optional initial prompt content.
         * @param \WordPress\AiClientDependencies\Psr\EventDispatcher\EventDispatcherInterface|null $eventDispatcher Optional event dispatcher for lifecycle events.
         * @phpstan-return void
         */
        public function __construct(\WordPress\AiClient\Providers\ProviderRegistry $registry, $prompt = null, ?\WordPress\AiClientDependencies\Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher = null)
        {
        }
        /**
         * Creates a deep clone of this builder.
         *
         * Clones all mutable state including messages, model configuration, and request options.
         * Service objects (registry, model, event dispatcher) are intentionally NOT cloned
         * as they are shared dependencies.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
        /**
         * Adds text to the current message.
         *
         * @since 0.1.0
         *
         * @param string $text The text to add.
         * @return self
         */
        public function withText(string $text): self
        {
        }
        /**
         * Adds a file to the current message.
         *
         * Accepts:
         * - File object
         * - URL string (remote file)
         * - Base64-encoded data string
         * - Data URI string (data:mime/type;base64,data)
         * - Local file path string
         *
         * @since 0.1.0
         *
         * @param string|\WordPress\AiClient\Files\DTO\File $file The file (File object or string representation).
         * @param string|null $mimeType The MIME type (optional, ignored if File object provided).
         * @return self
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the file is invalid or MIME type cannot be determined.
         */
        public function withFile($file, ?string $mimeType = null): self
        {
        }
        /**
         * Adds a function response to the current message.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Tools\DTO\FunctionResponse $functionResponse The function response.
         * @return self
         */
        public function withFunctionResponse(\WordPress\AiClient\Tools\DTO\FunctionResponse $functionResponse): self
        {
        }
        /**
         * Adds message parts to the current message.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Messages\DTO\MessagePart ...$parts The message parts to add.
         * @return self
         */
        public function withMessageParts(\WordPress\AiClient\Messages\DTO\MessagePart ...$parts): self
        {
        }
        /**
         * Adds conversation history messages.
         *
         * Historical messages are prepended to the beginning of the message list,
         * before the current message being built.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Messages\DTO\Message ...$messages The messages to add to history.
         * @return self
         */
        public function withHistory(\WordPress\AiClient\Messages\DTO\Message ...$messages): self
        {
        }
        /**
         * Sets the model to use for generation.
         *
         * The model's configuration will be merged with the builder's configuration,
         * with the builder's configuration taking precedence for any overlapping settings.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Models\Contracts\ModelInterface $model The model to use.
         * @return self
         */
        public function usingModel(\WordPress\AiClient\Providers\Models\Contracts\ModelInterface $model): self
        {
        }
        /**
         * Sets preferred models to evaluate in order.
         *
         * @since 0.2.0
         *
         * @param string|\WordPress\AiClient\Providers\Models\Contracts\ModelInterface|array{0:string,1:string} ...$preferredModels The preferred models as model IDs,
         * model instances, or [provider ID, model ID] tuples. For broader compatibility, it is recommended you specify
         * only model IDs or model instances, as that will allow for different providers that expose the same model to be
         * considered.
         * @return self
         *
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException When a preferred model has an invalid type or identifier.
         */
        public function usingModelPreference(...$preferredModels): self
        {
        }
        /**
         * Sets the model configuration.
         *
         * Merges the provided configuration with the builder's configuration,
         * with builder configuration taking precedence.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config The model configuration to merge.
         * @return self
         */
        public function usingModelConfig(\WordPress\AiClient\Providers\Models\DTO\ModelConfig $config): self
        {
        }
        /**
         * Sets the provider to use for generation.
         *
         * @since 0.1.0
         *
         * @param string $providerIdOrClassName The provider ID or class name.
         * @return self
         */
        public function usingProvider(string $providerIdOrClassName): self
        {
        }
        /**
         * Sets the system instruction.
         *
         * System instructions are stored in the model configuration and guide
         * the AI model's behavior throughout the conversation.
         *
         * @since 0.1.0
         *
         * @param string $systemInstruction The system instruction text.
         * @return self
         */
        public function usingSystemInstruction(string $systemInstruction): self
        {
        }
        /**
         * Sets the maximum number of tokens to generate.
         *
         * @since 0.1.0
         *
         * @param int $maxTokens The maximum number of tokens.
         * @return self
         */
        public function usingMaxTokens(int $maxTokens): self
        {
        }
        /**
         * Sets the temperature for generation.
         *
         * @since 0.1.0
         *
         * @param float $temperature The temperature value.
         * @return self
         */
        public function usingTemperature(float $temperature): self
        {
        }
        /**
         * Sets the top-p value for generation.
         *
         * @since 0.1.0
         *
         * @param float $topP The top-p value.
         * @return self
         */
        public function usingTopP(float $topP): self
        {
        }
        /**
         * Sets the top-k value for generation.
         *
         * @since 0.1.0
         *
         * @param int $topK The top-k value.
         * @return self
         */
        public function usingTopK(int $topK): self
        {
        }
        /**
         * Sets stop sequences for generation.
         *
         * @since 0.1.0
         *
         * @param string ...$stopSequences The stop sequences.
         * @return self
         */
        public function usingStopSequences(string ...$stopSequences): self
        {
        }
        /**
         * Sets the number of candidates to generate.
         *
         * @since 0.1.0
         *
         * @param int $candidateCount The number of candidates.
         * @return self
         */
        public function usingCandidateCount(int $candidateCount): self
        {
        }
        /**
         * Sets the function declarations available to the model.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Tools\DTO\FunctionDeclaration ...$functionDeclarations The function declarations.
         * @return self
         */
        public function usingFunctionDeclarations(\WordPress\AiClient\Tools\DTO\FunctionDeclaration ...$functionDeclarations): self
        {
        }
        /**
         * Sets the presence penalty for generation.
         *
         * @since 0.1.0
         *
         * @param float $presencePenalty The presence penalty value.
         * @return self
         */
        public function usingPresencePenalty(float $presencePenalty): self
        {
        }
        /**
         * Sets the frequency penalty for generation.
         *
         * @since 0.1.0
         *
         * @param float $frequencyPenalty The frequency penalty value.
         * @return self
         */
        public function usingFrequencyPenalty(float $frequencyPenalty): self
        {
        }
        /**
         * Sets the web search configuration.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Tools\DTO\WebSearch $webSearch The web search configuration.
         * @return self
         */
        public function usingWebSearch(\WordPress\AiClient\Tools\DTO\WebSearch $webSearch): self
        {
        }
        /**
         * Sets the request options for HTTP transport.
         *
         * @since 0.3.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\RequestOptions $requestOptions The request options.
         * @return self
         */
        public function usingRequestOptions(\WordPress\AiClient\Providers\Http\DTO\RequestOptions $requestOptions): self
        {
        }
        /**
         * Sets the top log probabilities configuration.
         *
         * If $topLogprobs is null, enables log probabilities.
         * If $topLogprobs has a value, enables log probabilities and sets the number of top log probabilities to return.
         *
         * @since 0.1.0
         *
         * @param int|null $topLogprobs The number of top log probabilities to return, or null to enable log probabilities.
         * @return self
         */
        public function usingTopLogprobs(?int $topLogprobs = null): self
        {
        }
        /**
         * Sets the output MIME type.
         *
         * @since 0.1.0
         *
         * @param string $mimeType The MIME type.
         * @return self
         */
        public function asOutputMimeType(string $mimeType): self
        {
        }
        /**
         * Sets the output schema.
         *
         * @since 0.1.0
         *
         * @param array<string, mixed> $schema The output schema.
         * @return self
         */
        public function asOutputSchema(array $schema): self
        {
        }
        /**
         * Sets the output modalities.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Messages\Enums\ModalityEnum ...$modalities The output modalities.
         * @return self
         */
        public function asOutputModalities(\WordPress\AiClient\Messages\Enums\ModalityEnum ...$modalities): self
        {
        }
        /**
         * Sets the output file type.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Files\Enums\FileTypeEnum $fileType The output file type.
         * @return self
         */
        public function asOutputFileType(\WordPress\AiClient\Files\Enums\FileTypeEnum $fileType): self
        {
        }
        /**
         * Sets the output media orientation.
         *
         * @since 1.3.0
         *
         * @param \WordPress\AiClient\Files\Enums\MediaOrientationEnum $orientation The output media orientation.
         * @return self
         */
        public function asOutputMediaOrientation(\WordPress\AiClient\Files\Enums\MediaOrientationEnum $orientation): self
        {
        }
        /**
         * Sets the output media aspect ratio.
         *
         * If set, this supersedes the output media orientation, as it is a more
         * specific configuration.
         *
         * @since 1.3.0
         *
         * @param string $aspectRatio The aspect ratio (e.g. "16:9", "3:2").
         * @return self
         */
        public function asOutputMediaAspectRatio(string $aspectRatio): self
        {
        }
        /**
         * Sets the output speech voice.
         *
         * @since 1.3.0
         *
         * @param string $voice The output speech voice.
         * @return self
         */
        public function asOutputSpeechVoice(string $voice): self
        {
        }
        /**
         * Configures the prompt for JSON response output.
         *
         * @since 0.1.0
         *
         * @param array<string, mixed>|null $schema Optional JSON schema.
         * @return self
         */
        public function asJsonResponse(?array $schema = null): self
        {
        }
        /**
         * Checks if the current prompt is supported by the selected model.
         *
         * @since 0.1.0
         * @since 0.3.0 Method visibility changed to public.
         *
         * @param \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum|null $capability Optional capability to check support for.
         * @return bool True if supported, false otherwise.
         */
        public function isSupported(?\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum $capability = null): bool
        {
        }
        /**
         * Checks if the prompt is supported for text generation.
         *
         * @since 0.1.0
         *
         * @return bool True if text generation is supported.
         */
        public function isSupportedForTextGeneration(): bool
        {
        }
        /**
         * Checks if the prompt is supported for image generation.
         *
         * @since 0.1.0
         *
         * @return bool True if image generation is supported.
         */
        public function isSupportedForImageGeneration(): bool
        {
        }
        /**
         * Checks if the prompt is supported for text to speech conversion.
         *
         * @since 0.1.0
         *
         * @return bool True if text to speech conversion is supported.
         */
        public function isSupportedForTextToSpeechConversion(): bool
        {
        }
        /**
         * Checks if the prompt is supported for video generation.
         *
         * @since 0.1.0
         *
         * @return bool True if video generation is supported.
         */
        public function isSupportedForVideoGeneration(): bool
        {
        }
        /**
         * Checks if the prompt is supported for speech generation.
         *
         * @since 0.1.0
         *
         * @return bool True if speech generation is supported.
         */
        public function isSupportedForSpeechGeneration(): bool
        {
        }
        /**
         * Checks if the prompt is supported for music generation.
         *
         * @since 0.1.0
         *
         * @return bool True if music generation is supported.
         */
        public function isSupportedForMusicGeneration(): bool
        {
        }
        /**
         * Checks if the prompt is supported for embedding generation.
         *
         * @since 0.1.0
         *
         * @return bool True if embedding generation is supported.
         */
        public function isSupportedForEmbeddingGeneration(): bool
        {
        }
        /**
         * Generates a result from the prompt.
         *
         * This is the primary execution method that generates a result (containing
         * potentially multiple candidates) based on the specified capability or
         * the configured output modality.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum|null $capability Optional capability to use for generation.
         *                                        If null, capability is inferred from output modality.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The generated result containing candidates.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If the model doesn't support the required capability.
         */
        public function generateResult(?\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum $capability = null): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Generates a text result from the prompt.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The generated result containing text candidates.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If the model doesn't support text generation.
         */
        public function generateTextResult(): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Generates an image result from the prompt.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The generated result containing image candidates.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If the model doesn't support image generation.
         */
        public function generateImageResult(): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Generates a speech result from the prompt.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The generated result containing speech audio candidates.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If the model doesn't support speech generation.
         */
        public function generateSpeechResult(): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Converts text to speech and returns the result.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The generated result containing speech audio candidates.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If the model doesn't support text-to-speech conversion.
         */
        public function convertTextToSpeechResult(): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Generates a video result from the prompt.
         *
         * @since 1.3.0
         *
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The generated result containing video candidates.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If the model doesn't support video generation.
         */
        public function generateVideoResult(): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Generates text from the prompt.
         *
         * @since 0.1.0
         *
         * @return string The generated text.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         */
        public function generateText(): string
        {
        }
        /**
         * Generates multiple text candidates from the prompt.
         *
         * @since 0.1.0
         *
         * @param int|null $candidateCount The number of candidates to generate.
         * @return list<string> The generated texts.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         */
        public function generateTexts(?int $candidateCount = null): array
        {
        }
        /**
         * Generates an image from the prompt.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Files\DTO\File The generated image file.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If no image is generated.
         */
        public function generateImage(): \WordPress\AiClient\Files\DTO\File
        {
        }
        /**
         * Generates multiple images from the prompt.
         *
         * @since 0.1.0
         *
         * @param int|null $candidateCount The number of images to generate.
         * @return list<\WordPress\AiClient\Files\DTO\File> The generated image files.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If no images are generated.
         */
        public function generateImages(?int $candidateCount = null): array
        {
        }
        /**
         * Converts text to speech.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Files\DTO\File The generated speech audio file.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If no audio is generated.
         */
        public function convertTextToSpeech(): \WordPress\AiClient\Files\DTO\File
        {
        }
        /**
         * Converts text to multiple speech outputs.
         *
         * @since 0.1.0
         *
         * @param int|null $candidateCount The number of speech outputs to generate.
         * @return list<\WordPress\AiClient\Files\DTO\File> The generated speech audio files.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If no audio is generated.
         */
        public function convertTextToSpeeches(?int $candidateCount = null): array
        {
        }
        /**
         * Generates speech from the prompt.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Files\DTO\File The generated speech audio file.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If no audio is generated.
         */
        public function generateSpeech(): \WordPress\AiClient\Files\DTO\File
        {
        }
        /**
         * Generates multiple speech outputs from the prompt.
         *
         * @since 0.1.0
         *
         * @param int|null $candidateCount The number of speech outputs to generate.
         * @return list<\WordPress\AiClient\Files\DTO\File> The generated speech audio files.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If no audio is generated.
         */
        public function generateSpeeches(?int $candidateCount = null): array
        {
        }
        /**
         * Generates a video from the prompt.
         *
         * @since 1.3.0
         *
         * @return \WordPress\AiClient\Files\DTO\File The generated video file.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If no video is generated.
         */
        public function generateVideo(): \WordPress\AiClient\Files\DTO\File
        {
        }
        /**
         * Generates multiple videos from the prompt.
         *
         * @since 1.3.0
         *
         * @param int|null $candidateCount The number of videos to generate.
         * @return list<\WordPress\AiClient\Files\DTO\File> The generated video files.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt or model validation fails.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If no videos are generated.
         */
        public function generateVideos(?int $candidateCount = null): array
        {
        }
        /**
         * Appends a MessagePart to the messages array.
         *
         * If the last message has a user role, the part is added to it.
         * Otherwise, a new UserMessage is created with the part.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Messages\DTO\MessagePart $part The part to append.
         * @return void
         */
        protected function appendPartToMessages(\WordPress\AiClient\Messages\DTO\MessagePart $part): void
        {
        }
    }
}
namespace WordPress\AiClient\Common\Contracts {
    /**
     * Interface for objects that support array transformation.
     *
     * @since 0.1.0
     *
     * @template TArrayShape of array<string, mixed>
     */
    interface WithArrayTransformationInterface
    {
        /**
         * Converts the object to an array representation.
         *
         * @since 0.1.0
         *
         * @return TArrayShape The array representation.
         */
        public function toArray(): array;
        /**
         * Creates an instance from array data.
         *
         * @since 0.1.0
         *
         * @param TArrayShape $array The array data.
         * @return self<TArrayShape> The created instance.
         */
        public static function fromArray(array $array): self;
        /**
         * Checks if the array is a valid shape for this object.
         *
         * @since 0.1.0
         *
         * @param array<mixed> $array The array to check.
         * @return bool True if the array is a valid shape.
         * @phpstan-assert-if-true TArrayShape $array
         */
        public static function isArrayShape(array $array): bool;
    }
    /**
     * Interface for objects that can provide their JSON schema representation.
     *
     * This interface is implemented by DTOs to provide a consistent way to retrieve
     * their JSON schema for validation and serialization purposes.
     *
     * @since 0.1.0
     */
    interface WithJsonSchemaInterface
    {
        /**
         * Gets the JSON schema representation of the object.
         *
         * @since 0.1.0
         *
         * @return array<string, mixed> The JSON schema as an associative array.
         */
        public static function getJsonSchema(): array;
    }
}
namespace WordPress\AiClient\Common {
    /**
     * Abstract base class for all Data Value Objects in the AI Client.
     *
     * This abstract class consolidates the common functionality needed by all
     * data transfer objects:
     * - Array transformation for data manipulation
     * - JSON schema support for validation and documentation
     * - JSON serialization with proper empty object handling
     *
     * All DTOs in the AI Client should extend this class to ensure
     * consistent behavior across the codebase.
     *
     * @since 0.1.0
     *
     * @template TArrayShape of array<string, mixed>
     * @implements \WordPress\AiClient\Common\Contracts\WithArrayTransformationInterface<TArrayShape>
     */
    abstract class AbstractDataTransferObject implements \WordPress\AiClient\Common\Contracts\WithArrayTransformationInterface, \WordPress\AiClient\Common\Contracts\WithJsonSchemaInterface, \JsonSerializable
    {
        /**
         * Validates that required keys exist in the array data.
         *
         * @since 0.1.0
         *
         * @param array<mixed> $data The array data to validate.
         * @param string[] $requiredKeys The keys that must be present.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If any required key is missing.
         */
        protected static function validateFromArrayData(array $data, array $requiredKeys): void
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function isArrayShape(array $array): bool
        {
        }
        /**
         * Converts the object to a JSON-serializable format.
         *
         * This method uses the toArray() method and then processes the result
         * based on the JSON schema to ensure proper object representation for
         * empty arrays.
         *
         * @since 0.1.0
         *
         * @return mixed The JSON-serializable representation.
         */
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
        }
    }
    /**
     * Abstract base class for enum-like behavior in PHP 7.4.
     *
     * This class provides enum-like functionality for PHP versions that don't support native enums.
     * Child classes should define uppercase snake_case constants for enum values.
     *
     * @example
     * class PersonEnum extends AbstractEnum {
     *     public const FIRST_NAME = 'first';
     *     public const LAST_NAME = 'last';
     * }
     *
     * // Usage:
     * $enum = PersonEnum::from('first'); // Creates instance with value 'first'
     * $enum = PersonEnum::tryFrom('invalid'); // Returns null
     * $enum = PersonEnum::firstName(); // Creates instance with value 'first'
     * $enum->name; // 'FIRST_NAME'
     * $enum->value; // 'first'
     * $enum->equals('first'); // Returns true
     * $enum->is(PersonEnum::firstName()); // Returns true
     * PersonEnum::cases(); // Returns array of all enum instances
     *
     * @property-read string $value The value of the enum instance.
     * @property-read string $name The name of the enum constant.
     *
     * @since 0.1.0
     */
    abstract class AbstractEnum implements \JsonSerializable
    {
        /**
         * Provides read-only access to properties.
         *
         * @since 0.1.0
         *
         * @param string $property The property name.
         * @return mixed The property value.
         * @throws \BadMethodCallException If property doesn't exist.
         */
        final public function __get(string $property)
        {
        }
        /**
         * Prevents property modification.
         *
         * @since 0.1.0
         *
         * @param string $property The property name.
         * @param mixed $value The value to set.
         * @throws \BadMethodCallException Always, as enum properties are read-only.
         */
        final public function __set(string $property, $value): void
        {
        }
        /**
         * Creates an enum instance from a value, throws exception if invalid.
         *
         * @since 0.1.0
         *
         * @param string $value The enum value.
         * @return static The enum instance.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the value is not valid.
         */
        final public static function from(string $value): self
        {
        }
        /**
         * Tries to create an enum instance from a value, returns null if invalid.
         *
         * @since 0.1.0
         *
         * @param string $value The enum value.
         * @return static|null The enum instance or null.
         */
        final public static function tryFrom(string $value): ?self
        {
        }
        /**
         * Gets all enum cases.
         *
         * @since 0.1.0
         *
         * @return static[] Array of all enum instances.
         */
        final public static function cases(): array
        {
        }
        /**
         * Checks if this enum has the same value as the given value.
         *
         * @since 0.1.0
         *
         * @param string|self $other The value or enum to compare.
         * @return bool True if values are equal.
         */
        final public function equals($other): bool
        {
        }
        /**
         * Checks if this enum is the same instance type and value as another enum.
         *
         * @since 0.1.0
         *
         * @param self $other The other enum to compare.
         * @return bool True if enums are identical.
         */
        final public function is(self $other): bool
        {
        }
        /**
         * Gets all valid values for this enum.
         *
         * @since 0.1.0
         *
         * @return string[] List of all enum values.
         */
        final public static function getValues(): array
        {
        }
        /**
         * Checks if a value is valid for this enum.
         *
         * @since 0.1.0
         *
         * @param string $value The value to check.
         * @return bool True if value is valid.
         */
        final public static function isValidValue(string $value): bool
        {
        }
        /**
         * Gets all constants for this enum class.
         *
         * @since 0.1.0
         *
         * @return array<string, string> Map of constant names to values.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If invalid constant found.
         */
        final protected static function getConstants(): array
        {
        }
        /**
         * Determines the class enumerations by reflecting on class constants.
         *
         * This method can be overridden by subclasses to customize how
         * enumerations are determined (e.g., to add dynamic constants).
         *
         * @since 0.1.0
         *
         * @param class-string $className The fully qualified class name.
         * @return array<string, string> Map of constant names to values.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If invalid constant found.
         */
        protected static function determineClassEnumerations(string $className): array
        {
        }
        /**
         * Handles dynamic method calls for enum checking.
         *
         * @since 0.1.0
         *
         * @param string $name The method name.
         * @param array<mixed> $arguments The method arguments.
         * @return bool True if the enum value matches.
         * @throws \BadMethodCallException If the method doesn't exist.
         */
        final public function __call(string $name, array $arguments): bool
        {
        }
        /**
         * Handles static method calls for enum creation.
         *
         * @since 0.1.0
         *
         * @param string $name The method name.
         * @param array<mixed> $arguments The method arguments.
         * @return static The enum instance.
         * @throws \BadMethodCallException If the method doesn't exist.
         */
        final public static function __callStatic(string $name, array $arguments): self
        {
        }
        /**
         * Returns string representation of the enum.
         *
         * @since 0.1.0
         *
         * @return string The enum value.
         */
        final public function __toString(): string
        {
        }
        /**
         * Converts the enum to a JSON-serializable format.
         *
         * @since 0.1.0
         *
         * @return string The enum value.
         */
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
        }
    }
}
namespace WordPress\AiClient\Common\Contracts {
    /**
     * Base interface for all AI Client exceptions.
     *
     * This interface allows callers to catch all AI Client specific exceptions
     * with a single catch statement.
     *
     * @since 0.2.0
     */
    interface AiClientExceptionInterface extends \Throwable
    {
    }
    /**
     * Interface for objects that cache data.
     *
     * @since 0.4.0
     */
    interface CachesDataInterface
    {
        /**
         * Invalidates all caches managed by this object.
         *
         * @since 0.4.0
         *
         * @return void
         */
        public function invalidateCaches(): void;
    }
}
namespace WordPress\AiClient\Common\Exception {
    /**
     * Exception thrown when an invalid argument is provided.
     *
     * This extends PHP's built-in InvalidArgumentException while implementing
     * the AI Client exception interface for consistent catch handling.
     *
     * @since 0.2.0
     */
    class InvalidArgumentException extends \InvalidArgumentException implements \WordPress\AiClient\Common\Contracts\AiClientExceptionInterface
    {
    }
    /**
     * Exception thrown for runtime errors.
     *
     * This extends PHP's built-in RuntimeException while implementing
     * the AI Client exception interface for consistent catch handling.
     *
     * @since 0.2.0
     */
    class RuntimeException extends \RuntimeException implements \WordPress\AiClient\Common\Contracts\AiClientExceptionInterface
    {
    }
    /**
     * Exception thrown when a token limit is reached during prompt fulfillment.
     *
     * Providers should throw this exception when the token usage for a request
     * exceeds the allowed limit, whether that is the model's context window
     * or a configured maximum.
     *
     * @since 1.0.0
     */
    class TokenLimitReachedException extends \WordPress\AiClient\Common\Exception\RuntimeException
    {
        /**
         * Creates a new TokenLimitReachedException.
         *
         * @since 1.0.0
         *
         * @param string         $message   The exception message.
         * @param int|null       $maxTokens The token limit that was reached, if known.
         * @param \Throwable|null $previous  The previous throwable used for exception chaining.
         */
        public function __construct(string $message = '', ?int $maxTokens = null, ?\Throwable $previous = null)
        {
        }
        /**
         * Returns the token limit that was reached, if known.
         *
         * @since 1.0.0
         *
         * @return int|null The token limit, or null if not provided.
         */
        public function getMaxTokens(): ?int
        {
        }
    }
}
namespace WordPress\AiClient\Common\Traits {
    /**
     * Trait for objects that cache data using PSR-16 cache with in-memory fallback.
     *
     * When a PSR-16 cache is configured via AiClient::setCache(), data is stored persistently.
     * Otherwise, data is cached in-memory for the duration of the request.
     *
     * @since 0.4.0
     */
    trait WithDataCachingTrait
    {
        /**
         * In-memory cache used when no PSR-16 cache is configured.
         *
         * @since 0.4.0
         *
         * @var array<string, mixed>
         */
        private array $localCache = [];
        /**
         * Gets the cache key suffixes managed by this object.
         *
         * @since 0.4.0
         *
         * @return list<string> The cache key suffixes.
         */
        abstract protected function getCachedKeys(): array;
        /**
         * Gets the base cache key for this object.
         *
         * The base cache key is used as a prefix for all cache keys managed by this object.
         * It should be unique to the implementing class to avoid cache key collisions.
         *
         * @since 0.4.0
         *
         * @return string The base cache key.
         */
        abstract protected function getBaseCacheKey(): string;
        /**
         * Checks if a value exists in the cache.
         *
         * @since 0.4.0
         *
         * @param string $key The cache key suffix (will be appended to the base key).
         * @return bool True if the value exists in cache, false otherwise.
         */
        protected function hasCache(string $key): bool
        {
        }
        /**
         * Gets a value from the cache, or computes and caches it if not present.
         *
         * @since 0.4.0
         *
         * @param string                 $key      The cache key suffix (will be appended to the base key).
         * @param callable               $callback The callback to compute the value if not cached.
         * @param int|\DateInterval|null $ttl      The TTL for the cache entry, or null for default.
         *                                         Ignored for local cache.
         * @return mixed The cached or computed value.
         */
        protected function cached(string $key, callable $callback, $ttl = null)
        {
        }
        /**
         * Gets a value from the cache.
         *
         * @since 0.4.0
         *
         * @param string $key     The cache key suffix (will be appended to the base key).
         * @param mixed  $default The default value to return if the key does not exist.
         * @return mixed The cached value or the default value if not found.
         */
        protected function getCache(string $key, $default = null)
        {
        }
        /**
         * Sets a value in the cache.
         *
         * @since 0.4.0
         *
         * @param string                $key   The cache key suffix (will be appended to the base key).
         * @param mixed                 $value The value to cache.
         * @param int|\DateInterval|null $ttl   The TTL for the cache entry, or null for default. Ignored for local cache.
         * @return bool True on success, false on failure.
         */
        protected function setCache(string $key, $value, $ttl = null): bool
        {
        }
        /**
         * Invalidates all caches managed by this object.
         *
         * @since 0.4.0
         *
         * @return void
         */
        public function invalidateCaches(): void
        {
        }
        /**
         * Clears a value from the cache.
         *
         * @since 0.4.0
         *
         * @param string $key The cache key suffix (will be appended to the base key).
         * @return bool True on success, false on failure.
         */
        protected function clearCache(string $key): bool
        {
        }
        /**
         * Builds the full cache key by combining the base key with the suffix.
         *
         * @since 0.4.0
         *
         * @param string $key The cache key suffix.
         * @return string The full cache key.
         */
        private function buildCacheKey(string $key): string
        {
        }
    }
}
namespace WordPress\AiClient\Events {
    /**
     * Event dispatched after a prompt has been sent to the AI model and a response received.
     *
     * This event allows listeners to inspect the result of the model call for logging,
     * analytics, or other post-processing purposes. The result object is immutable.
     *
     * @since 0.4.0
     */
    class AfterGenerateResultEvent
    {
        /**
         * Constructor.
         *
         * @since 0.4.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $messages The messages that were sent to the model.
         * @param \WordPress\AiClient\Providers\Models\Contracts\ModelInterface $model The model that processed the prompt.
         * @param \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum|null $capability The capability that was used for generation.
         * @param \WordPress\AiClient\Results\DTO\GenerativeAiResult $result The result from the model.
         */
        public function __construct(array $messages, \WordPress\AiClient\Providers\Models\Contracts\ModelInterface $model, ?\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum $capability, \WordPress\AiClient\Results\DTO\GenerativeAiResult $result)
        {
        }
        /**
         * Gets the messages that were sent to the model.
         *
         * @since 0.4.0
         *
         * @return list<\WordPress\AiClient\Messages\DTO\Message> The messages.
         */
        public function getMessages(): array
        {
        }
        /**
         * Gets the model that processed the prompt.
         *
         * @since 0.4.0
         *
         * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface The model.
         */
        public function getModel(): \WordPress\AiClient\Providers\Models\Contracts\ModelInterface
        {
        }
        /**
         * Gets the capability that was used for generation.
         *
         * @since 0.4.0
         *
         * @return \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum|null The capability, or null if not specified.
         */
        public function getCapability(): ?\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum
        {
        }
        /**
         * Gets the result from the model.
         *
         * @since 0.4.0
         *
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The result.
         */
        public function getResult(): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Performs a deep clone of the event.
         *
         * This method ensures that message and result objects are cloned to prevent
         * modifications to the cloned event from affecting the original.
         * The model object is not cloned as it is a service object.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
    }
    /**
     * Event dispatched before a prompt is sent to the AI model.
     *
     * This event allows listeners to inspect and modify the messages before they
     * are sent to the model. The event is not stoppable, meaning the model call
     * will always proceed regardless of listener actions.
     *
     * @since 0.4.0
     */
    class BeforeGenerateResultEvent
    {
        /**
         * Constructor.
         *
         * @since 0.4.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $messages The messages to be sent to the model.
         * @param \WordPress\AiClient\Providers\Models\Contracts\ModelInterface $model The model that will process the prompt.
         * @param \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum|null $capability The capability being used for generation.
         */
        public function __construct(array $messages, \WordPress\AiClient\Providers\Models\Contracts\ModelInterface $model, ?\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum $capability)
        {
        }
        /**
         * Gets the messages to be sent to the model.
         *
         * @since 0.4.0
         *
         * @return list<\WordPress\AiClient\Messages\DTO\Message> The messages.
         */
        public function getMessages(): array
        {
        }
        /**
         * Gets the model that will process the prompt.
         *
         * @since 0.4.0
         *
         * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface The model.
         */
        public function getModel(): \WordPress\AiClient\Providers\Models\Contracts\ModelInterface
        {
        }
        /**
         * Gets the capability being used for generation.
         *
         * @since 0.4.0
         *
         * @return \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum|null The capability, or null if not specified.
         */
        public function getCapability(): ?\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum
        {
        }
        /**
         * Performs a deep clone of the event.
         *
         * This method ensures that message objects are cloned to prevent
         * modifications to the cloned event from affecting the original.
         * The model object is not cloned as it is a service object.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
    }
}
namespace WordPress\AiClient\Files\DTO {
    /**
     * Represents a file in the AI client.
     *
     * This DTO automatically detects whether a file is a URL, base64 data, or local file path
     * and handles them appropriately.
     *
     * @since 0.1.0
     *
     * @phpstan-type FileArrayShape array{
     *     fileType: string,
     *     url?: string,
     *     mimeType: string,
     *     base64Data?: string
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<FileArrayShape>
     */
    class File extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_FILE_TYPE = 'fileType';
        public const KEY_MIME_TYPE = 'mimeType';
        public const KEY_URL = 'url';
        public const KEY_BASE64_DATA = 'base64Data';
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param string $file The file string (URL, base64 data, or local path).
         * @param string|null $mimeType The MIME type of the file (optional).
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the file format is invalid or MIME type cannot be determined.
         * @phpstan-return void
         */
        public function __construct(string $file, ?string $mimeType = null)
        {
        }
        /**
         * Gets the file type.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Files\Enums\FileTypeEnum The file type.
         */
        public function getFileType(): \WordPress\AiClient\Files\Enums\FileTypeEnum
        {
        }
        /**
         * Checks if the file is an inline file.
         *
         * @since 0.1.0
         *
         * @return bool True if the file is inline (base64/data URI).
         */
        public function isInline(): bool
        {
        }
        /**
         * Checks if the file is a remote file.
         *
         * @since 0.1.0
         *
         * @return bool True if the file is remote (URL).
         */
        public function isRemote(): bool
        {
        }
        /**
         * Gets the URL for remote files.
         *
         * @since 0.1.0
         *
         * @return string|null The URL, or null if not a remote file.
         */
        public function getUrl(): ?string
        {
        }
        /**
         * Gets the base64-encoded data for inline files.
         *
         * @since 0.1.0
         *
         * @return string|null The plain base64-encoded data (without data URI prefix), or null if not an inline file.
         */
        public function getBase64Data(): ?string
        {
        }
        /**
         * Gets the data as a data URI for inline files.
         *
         * @since 0.1.0
         *
         * @return string|null The data URI in format: data:[mimeType];base64,[data], or null if not an inline file.
         */
        public function getDataUri(): ?string
        {
        }
        /**
         * Gets the MIME type of the file as a string.
         *
         * @since 0.1.0
         *
         * @return string The MIME type string value.
         */
        public function getMimeType(): string
        {
        }
        /**
         * Gets the MIME type object.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Files\ValueObjects\MimeType The MIME type object.
         */
        public function getMimeTypeObject(): \WordPress\AiClient\Files\ValueObjects\MimeType
        {
        }
        /**
         * Checks if the file is a video.
         *
         * @since 0.1.0
         *
         * @return bool True if the file is a video.
         */
        public function isVideo(): bool
        {
        }
        /**
         * Checks if the file is an image.
         *
         * @since 0.1.0
         *
         * @return bool True if the file is an image.
         */
        public function isImage(): bool
        {
        }
        /**
         * Checks if the file is audio.
         *
         * @since 0.1.0
         *
         * @return bool True if the file is audio.
         */
        public function isAudio(): bool
        {
        }
        /**
         * Checks if the file is text.
         *
         * @since 0.1.0
         *
         * @return bool True if the file is text.
         */
        public function isText(): bool
        {
        }
        /**
         * Checks if the file is a document.
         *
         * @since 0.1.0
         *
         * @return bool True if the file is a document.
         */
        public function isDocument(): bool
        {
        }
        /**
         * Checks if the file is a specific MIME type.
         *
         * @since 0.1.0
         *
         * @param string $type The mime type to check (e.g. 'image', 'text', 'video', 'audio').
         *
         * @return bool True if the file is of the specified type.
         */
        public function isMimeType(string $type): bool
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return FileArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
        /**
         * Performs a deep clone of the file.
         *
         * This method ensures that the MimeType value object is cloned to prevent
         * any shared references between the original and cloned file.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
    }
}
namespace WordPress\AiClient\Files\Enums {
    /**
     * Represents the type of file storage.
     *
     * @method static self inline() Returns the inline file type.
     * @method static self remote() Returns the remote file type.
     * @method bool isInline() Checks if this is an inline file type.
     * @method bool isRemote() Checks if this is a remote file type.
     *
     * @since 0.1.0
     */
    class FileTypeEnum extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * Inline file with base64-encoded data.
         *
         * @var string
         */
        public const INLINE = 'inline';
        /**
         * Remote file referenced by URL.
         *
         * @var string
         */
        public const REMOTE = 'remote';
    }
    /**
     * Represents the type of file storage.
     *
     * @method static self square() Returns the square orientation
     * @method static self landscape() Returns the landscape orientation.
     * @method static self portrait() Returns the portrait orientation.
     * @method bool isSquare() Checks if this is an square orientation
     * @method bool isLandscape() Checks if this is a landscape orientation.
     * @method bool isPortrait() Checks if this is a portrait orientation.
     *
     * @since 0.1.0
     */
    class MediaOrientationEnum extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * Square orientation.
         *
         * @var string
         */
        public const SQUARE = 'square';
        /**
         * Landscape orientation.
         *
         * @var string
         */
        public const LANDSCAPE = 'landscape';
        /**
         * Portrait orientation.
         *
         * @var string
         */
        public const PORTRAIT = 'portrait';
    }
}
namespace WordPress\AiClient\Files\ValueObjects {
    /**
     * Value object representing a MIME type.
     *
     * This immutable value object encapsulates MIME type validation and
     * provides convenient methods for checking MIME type categories.
     *
     * @since 0.1.0
     */
    final class MimeType
    {
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param string $value The MIME type value.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the MIME type is invalid.
         */
        public function __construct(string $value)
        {
        }
        /**
         * Gets the primary known file extension for this MIME type.
         *
         * @since 0.1.0
         *
         * @return string The file extension (without the dot).
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If no known extension exists for this MIME type.
         */
        public function toExtension(): string
        {
        }
        /**
         * Creates a MimeType from a file extension.
         *
         * @since 0.1.0
         *
         * @param string $extension The file extension (without the dot).
         * @return self The MimeType instance.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the extension is not recognized.
         */
        public static function fromExtension(string $extension): self
        {
        }
        /**
         * Checks if a MIME type string is valid.
         *
         * @since 0.1.0
         *
         * @param string $mimeType The MIME type to validate.
         * @return bool True if valid.
         */
        public static function isValid(string $mimeType): bool
        {
        }
        /**
         * Checks if this MIME type is a specific type.
         *
         * This method returns true when the stored MIME type begins with the
         * given prefix. For example, `"audio"` matches `"audio/mpeg"`.
         *
         * @since 0.1.0
         *
         * @param string $mimeType The MIME type prefix to check (e.g., "audio", "image").
         * @return bool True if this MIME type is of the specified type.
         */
        public function isType(string $mimeType): bool
        {
        }
        /**
         * Checks if this is an image MIME type.
         *
         * @since 0.1.0
         *
         * @return bool True if this is an image type.
         */
        public function isImage(): bool
        {
        }
        /**
         * Checks if this is an audio MIME type.
         *
         * @since 0.1.0
         *
         * @return bool True if this is an audio type.
         */
        public function isAudio(): bool
        {
        }
        /**
         * Checks if this is a video MIME type.
         *
         * @since 0.1.0
         *
         * @return bool True if this is a video type.
         */
        public function isVideo(): bool
        {
        }
        /**
         * Checks if this is a text MIME type.
         *
         * @since 0.1.0
         *
         * @return bool True if this is a text type.
         */
        public function isText(): bool
        {
        }
        /**
         * Checks if this is a document MIME type.
         *
         * @since 0.1.0
         *
         * @return bool True if this is a document type.
         */
        public function isDocument(): bool
        {
        }
        /**
         * Checks if this MIME type equals another.
         *
         * @since 0.1.0
         *
         * @param self|string $other The other MIME type to compare.
         * @return bool True if equal.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the other MIME type is invalid.
         */
        public function equals($other): bool
        {
        }
        /**
         * Gets the string representation of the MIME type.
         *
         * @since 0.1.0
         *
         * @return string The MIME type value.
         */
        public function __toString(): string
        {
        }
    }
}
namespace WordPress\AiClient\Messages\DTO {
    /**
     * Represents a message in an AI conversation.
     *
     * Messages are the fundamental unit of communication with AI models,
     * containing a role and one or more parts with different content types.
     *
     * @since 0.1.0
     *
     * @phpstan-import-type MessagePartArrayShape from MessagePart
     *
     * @phpstan-type MessageArrayShape array{
     *     role: string,
     *     parts: array<MessagePartArrayShape>
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<MessageArrayShape>
     */
    class Message extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_ROLE = 'role';
        public const KEY_PARTS = 'parts';
        /**
         * @var \WordPress\AiClient\Messages\Enums\MessageRoleEnum The role of the message sender.
         */
        protected \WordPress\AiClient\Messages\Enums\MessageRoleEnum $role;
        /**
         * @var MessagePart[] The parts that make up this message.
         */
        protected array $parts;
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Messages\Enums\MessageRoleEnum $role The role of the message sender.
         * @param MessagePart[] $parts The parts that make up this message.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If parts contain invalid content for the role.
         */
        public function __construct(\WordPress\AiClient\Messages\Enums\MessageRoleEnum $role, array $parts)
        {
        }
        /**
         * Gets the role of the message sender.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Messages\Enums\MessageRoleEnum The role.
         */
        public function getRole(): \WordPress\AiClient\Messages\Enums\MessageRoleEnum
        {
        }
        /**
         * Gets the message parts.
         *
         * @since 0.1.0
         *
         * @return MessagePart[] The message parts.
         */
        public function getParts(): array
        {
        }
        /**
         * Returns a new instance with the given part appended.
         *
         * @since 0.1.0
         *
         * @param MessagePart $part The part to append.
         * @return Message A new instance with the part appended.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the part is invalid for the role.
         */
        public function withPart(\WordPress\AiClient\Messages\DTO\MessagePart $part): \WordPress\AiClient\Messages\DTO\Message
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return MessageArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return self The specific message class based on the role.
         */
        final public static function fromArray(array $array): self
        {
        }
        /**
         * Performs a deep clone of the message.
         *
         * This method ensures that message part objects are cloned to prevent
         * modifications to the cloned message from affecting the original.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
    }
    /**
     * Represents a part of a message.
     *
     * Messages can contain multiple parts of different types, such as text, files,
     * function calls, etc. This DTO encapsulates one such part.
     *
     * @since 0.1.0
     *
     * @phpstan-import-type FileArrayShape from \WordPress\AiClient\Files\DTO\File
     * @phpstan-import-type FunctionCallArrayShape from \WordPress\AiClient\Tools\DTO\FunctionCall
     * @phpstan-import-type FunctionResponseArrayShape from \WordPress\AiClient\Tools\DTO\FunctionResponse
     *
     * @phpstan-type MessagePartArrayShape array{
     *     channel: string,
     *     type: string,
     *     thoughtSignature?: string,
     *     text?: string,
     *     file?: FileArrayShape,
     *     functionCall?: FunctionCallArrayShape,
     *     functionResponse?: FunctionResponseArrayShape
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<MessagePartArrayShape>
     */
    class MessagePart extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_CHANNEL = 'channel';
        public const KEY_TYPE = 'type';
        public const KEY_THOUGHT_SIGNATURE = 'thoughtSignature';
        public const KEY_TEXT = 'text';
        public const KEY_FILE = 'file';
        public const KEY_FUNCTION_CALL = 'functionCall';
        public const KEY_FUNCTION_RESPONSE = 'functionResponse';
        /**
         * Constructor that accepts various content types and infers the message part type.
         *
         * @since 0.1.0
         *
         * @param mixed $content The content of this message part.
         * @param \WordPress\AiClient\Messages\Enums\MessagePartChannelEnum|null $channel The channel this part belongs to. Defaults to CONTENT.
         * @param string|null $thoughtSignature Optional thought signature for extended thinking.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If an unsupported content type is provided.
         */
        public function __construct($content, ?\WordPress\AiClient\Messages\Enums\MessagePartChannelEnum $channel = null, ?string $thoughtSignature = null)
        {
        }
        /**
         * Gets the channel this message part belongs to.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Messages\Enums\MessagePartChannelEnum The channel.
         */
        public function getChannel(): \WordPress\AiClient\Messages\Enums\MessagePartChannelEnum
        {
        }
        /**
         * Gets the type of this message part.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Messages\Enums\MessagePartTypeEnum The type.
         */
        public function getType(): \WordPress\AiClient\Messages\Enums\MessagePartTypeEnum
        {
        }
        /**
         * Gets the thought signature.
         *
         * @since 1.3.0
         *
         * @return string|null The thought signature or null if not set.
         */
        public function getThoughtSignature(): ?string
        {
        }
        /**
         * Gets the text content.
         *
         * @since 0.1.0
         *
         * @return string|null The text content or null if not a text part.
         */
        public function getText(): ?string
        {
        }
        /**
         * Gets the file.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Files\DTO\File|null The file or null if not a file part.
         */
        public function getFile(): ?\WordPress\AiClient\Files\DTO\File
        {
        }
        /**
         * Gets the function call.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Tools\DTO\FunctionCall|null The function call or null if not a function call part.
         */
        public function getFunctionCall(): ?\WordPress\AiClient\Tools\DTO\FunctionCall
        {
        }
        /**
         * Gets the function response.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Tools\DTO\FunctionResponse|null The function response or null if not a function response part.
         */
        public function getFunctionResponse(): ?\WordPress\AiClient\Tools\DTO\FunctionResponse
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return MessagePartArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
        /**
         * Performs a deep clone of the message part.
         *
         * This method ensures that nested objects (file, function call, function response)
         * are cloned to prevent modifications to the cloned part from affecting the original.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
    }
    /**
     * Represents a message from the AI model.
     *
     * This is a convenience class that automatically sets the role to MODEL.
     * Model messages contain the AI's responses.
     *
     * Important: Do not rely on `instanceof ModelMessage` to determine the message role.
     * This is merely a helper class for construction. Always use `$message->getRole()`
     * to check the role of a message.
     *
     * @since 0.1.0
     */
    class ModelMessage extends \WordPress\AiClient\Messages\DTO\Message
    {
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param MessagePart[] $parts The parts that make up this message.
         */
        public function __construct(array $parts)
        {
        }
    }
    /**
     * Represents a message from a user.
     *
     * This is a convenience class that automatically sets the role to USER.
     *
     * Important: Do not rely on `instanceof UserMessage` to determine the message role.
     * This is merely a helper class for construction. Always use `$message->getRole()`
     * to check the role of a message.
     *
     * @since 0.1.0
     */
    class UserMessage extends \WordPress\AiClient\Messages\DTO\Message
    {
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param MessagePart[] $parts The parts that make up this message.
         */
        public function __construct(array $parts)
        {
        }
    }
}
namespace WordPress\AiClient\Messages\Enums {
    /**
     * Enum for message part channels.
     *
     * @since 0.1.0
     *
     * @method static self content() Creates an instance for CONTENT channel.
     * @method static self thought() Creates an instance for THOUGHT channel.
     * @method bool isContent() Checks if the channel is CONTENT.
     * @method bool isThought() Checks if the channel is THOUGHT.
     */
    class MessagePartChannelEnum extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * Regular (primary) content.
         */
        public const CONTENT = 'content';
        /**
         * Model thinking or reasoning.
         */
        public const THOUGHT = 'thought';
    }
    /**
     * Enum for message part types.
     *
     * @since 0.1.0
     *
     * @method static self text() Creates an instance for TEXT type.
     * @method static self file() Creates an instance for FILE type.
     * @method static self functionCall() Creates an instance for FUNCTION_CALL type.
     * @method static self functionResponse() Creates an instance for FUNCTION_RESPONSE type.
     * @method bool isText() Checks if the type is TEXT.
     * @method bool isFile() Checks if the type is FILE.
     * @method bool isFunctionCall() Checks if the type is FUNCTION_CALL.
     * @method bool isFunctionResponse() Checks if the type is FUNCTION_RESPONSE.
     */
    class MessagePartTypeEnum extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * Text content.
         */
        public const TEXT = 'text';
        /**
         * File content (inline or remote).
         */
        public const FILE = 'file';
        /**
         * Function call request.
         */
        public const FUNCTION_CALL = 'function_call';
        /**
         * Function response.
         */
        public const FUNCTION_RESPONSE = 'function_response';
    }
    /**
     * Enum for message roles in AI conversations.
     *
     * @since 0.1.0
     *
     * @method static self user() Creates an instance for USER role.
     * @method static self model() Creates an instance for MODEL role.
     * @method bool isUser() Checks if the role is USER.
     * @method bool isModel() Checks if the role is MODEL.
     */
    class MessageRoleEnum extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * User role - messages from the user.
         */
        public const USER = 'user';
        /**
         * Model role - messages from the AI model.
         */
        public const MODEL = 'model';
    }
    /**
     * Enum for input/output modalities.
     *
     * @since 0.1.0
     *
     * @method static self text() Creates an instance for TEXT modality.
     * @method static self document() Creates an instance for DOCUMENT modality.
     * @method static self image() Creates an instance for IMAGE modality.
     * @method static self audio() Creates an instance for AUDIO modality.
     * @method static self video() Creates an instance for VIDEO modality.
     * @method bool isText() Checks if the modality is TEXT.
     * @method bool isDocument() Checks if the modality is DOCUMENT.
     * @method bool isImage() Checks if the modality is IMAGE.
     * @method bool isAudio() Checks if the modality is AUDIO.
     * @method bool isVideo() Checks if the modality is VIDEO.
     */
    class ModalityEnum extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * Text modality.
         */
        public const TEXT = 'text';
        /**
         * Document modality (PDFs, Word docs, etc.).
         */
        public const DOCUMENT = 'document';
        /**
         * Image modality.
         */
        public const IMAGE = 'image';
        /**
         * Audio modality.
         */
        public const AUDIO = 'audio';
        /**
         * Video modality.
         */
        public const VIDEO = 'video';
    }
}
namespace WordPress\AiClient\Operations\Contracts {
    /**
     * Interface for AI operations.
     *
     * Operations represent long-running AI tasks that may not complete immediately.
     * They provide a way to track the progress and retrieve results asynchronously.
     *
     * @since 0.1.0
     */
    interface OperationInterface
    {
        /**
         * Gets the operation ID.
         *
         * @since 0.1.0
         *
         * @return string The unique operation identifier.
         */
        public function getId(): string;
        /**
         * Gets the current state of the operation.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Operations\Enums\OperationStateEnum The operation state.
         */
        public function getState(): \WordPress\AiClient\Operations\Enums\OperationStateEnum;
    }
}
namespace WordPress\AiClient\Operations\DTO {
    /**
     * Represents a long-running generative AI operation.
     *
     * This DTO tracks the progress of generative AI tasks that may not complete
     * immediately, providing access to the result once available.
     *
     * @since 0.1.0
     *
     * @phpstan-import-type GenerativeAiResultArrayShape from \WordPress\AiClient\Results\DTO\GenerativeAiResult
     *
     * @phpstan-type GenerativeAiOperationArrayShape array{id: string, state: string, result?: GenerativeAiResultArrayShape}
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<GenerativeAiOperationArrayShape>
     */
    class GenerativeAiOperation extends \WordPress\AiClient\Common\AbstractDataTransferObject implements \WordPress\AiClient\Operations\Contracts\OperationInterface
    {
        public const KEY_ID = 'id';
        public const KEY_STATE = 'state';
        public const KEY_RESULT = 'result';
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param string $id Unique identifier for this operation.
         * @param \WordPress\AiClient\Operations\Enums\OperationStateEnum $state The current state of the operation.
         * @param \WordPress\AiClient\Results\DTO\GenerativeAiResult|null $result The result once the operation completes.
         */
        public function __construct(string $id, \WordPress\AiClient\Operations\Enums\OperationStateEnum $state, ?\WordPress\AiClient\Results\DTO\GenerativeAiResult $result = null)
        {
        }
        /**
         * Creates a deep clone of this operation.
         *
         * Clones the result object if present to ensure the cloned
         * operation is independent of the original.
         * The state enum is immutable and can be safely shared.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function getId(): string
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function getState(): \WordPress\AiClient\Operations\Enums\OperationStateEnum
        {
        }
        /**
         * Gets the operation result.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult|null The result or null if not yet complete.
         */
        public function getResult(): ?\WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return GenerativeAiOperationArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
    }
}
namespace WordPress\AiClient\Operations\Enums {
    /**
     * Enum for operation states.
     *
     * @since 0.1.0
     *
     * @method static self starting() Creates an instance for STARTING state.
     * @method static self processing() Creates an instance for PROCESSING state.
     * @method static self succeeded() Creates an instance for SUCCEEDED state.
     * @method static self failed() Creates an instance for FAILED state.
     * @method static self canceled() Creates an instance for CANCELED state.
     * @method bool isStarting() Checks if the state is STARTING.
     * @method bool isProcessing() Checks if the state is PROCESSING.
     * @method bool isSucceeded() Checks if the state is SUCCEEDED.
     * @method bool isFailed() Checks if the state is FAILED.
     * @method bool isCanceled() Checks if the state is CANCELED.
     */
    class OperationStateEnum extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * Operation is starting.
         */
        public const STARTING = 'starting';
        /**
         * Operation is processing.
         */
        public const PROCESSING = 'processing';
        /**
         * Operation succeeded.
         */
        public const SUCCEEDED = 'succeeded';
        /**
         * Operation failed.
         */
        public const FAILED = 'failed';
        /**
         * Operation was canceled.
         */
        public const CANCELED = 'canceled';
    }
}
namespace WordPress\AiClient\Providers\Contracts {
    /**
     * Interface for AI providers.
     *
     * Providers represent AI services (Google, OpenAI, Anthropic, etc.)
     * and provide access to models, metadata, and availability information.
     *
     * @since 0.1.0
     */
    interface ProviderInterface
    {
        /**
         * Gets provider metadata.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\DTO\ProviderMetadata Provider metadata.
         */
        public static function metadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata;
        /**
         * Creates a model instance.
         *
         * @since 0.1.0
         *
         * @param string $modelId Model identifier.
         * @param ?\WordPress\AiClient\Providers\Models\DTO\ModelConfig $modelConfig Model configuration.
         * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface Model instance.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If model not found or configuration invalid.
         */
        public static function model(string $modelId, ?\WordPress\AiClient\Providers\Models\DTO\ModelConfig $modelConfig = null): \WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
        /**
         * Gets provider availability checker.
         *
         * @since 0.1.0
         *
         * @return ProviderAvailabilityInterface Provider availability checker.
         */
        public static function availability(): \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
        /**
         * Gets model metadata directory.
         *
         * @since 0.1.0
         *
         * @return ModelMetadataDirectoryInterface Model metadata directory.
         */
        public static function modelMetadataDirectory(): \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
    }
}
namespace WordPress\AiClient\Providers {
    /**
     * Base class for a provider.
     *
     * @since 0.1.0
     */
    abstract class AbstractProvider implements \WordPress\AiClient\Providers\Contracts\ProviderInterface
    {
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        final public static function metadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        final public static function model(string $modelId, ?\WordPress\AiClient\Providers\Models\DTO\ModelConfig $modelConfig = null): \WordPress\AiClient\Providers\Models\Contracts\ModelInterface
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        final public static function availability(): \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        final public static function modelMetadataDirectory(): \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface
        {
        }
        /**
         * Creates a model instance based on the given model metadata and provider metadata.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $modelMetadata The model metadata.
         * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata $providerMetadata The provider metadata.
         * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface The new model instance.
         */
        abstract protected static function createModel(\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $modelMetadata, \WordPress\AiClient\Providers\DTO\ProviderMetadata $providerMetadata): \WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
        /**
         * Creates the provider metadata instance.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\DTO\ProviderMetadata The provider metadata.
         */
        abstract protected static function createProviderMetadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata;
        /**
         * Creates the provider availability instance.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface The provider availability.
         */
        abstract protected static function createProviderAvailability(): \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
        /**
         * Creates the model metadata directory instance.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface The model metadata directory.
         */
        abstract protected static function createModelMetadataDirectory(): \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
    }
}
namespace WordPress\AiClient\Providers\Models\Contracts {
    /**
     * Interface for AI models.
     *
     * Models represent specific AI models from providers and define
     * their capabilities, configuration, and execution methods.
     *
     * @since 0.1.0
     */
    interface ModelInterface
    {
        /**
         * Gets model metadata.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata Model metadata.
         */
        public function metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
        /**
         * Returns the metadata for the model's provider.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\DTO\ProviderMetadata The provider metadata.
         */
        public function providerMetadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata;
        /**
         * Sets model configuration.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config Model configuration.
         * @return void
         */
        public function setConfig(\WordPress\AiClient\Providers\Models\DTO\ModelConfig $config): void;
        /**
         * Gets model configuration.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\Models\DTO\ModelConfig Current model configuration.
         */
        public function getConfig(): \WordPress\AiClient\Providers\Models\DTO\ModelConfig;
    }
}
namespace WordPress\AiClient\Providers\ApiBasedImplementation\Contracts {
    /**
     * Interface for API-based AI models that support HTTP transport configuration.
     *
     * This interface extends ModelInterface to add request options support
     * for models that communicate with external APIs via HTTP.
     *
     * @since 0.3.0
     */
    interface ApiBasedModelInterface extends \WordPress\AiClient\Providers\Models\Contracts\ModelInterface
    {
        /**
         * Sets the request options for HTTP transport.
         *
         * @since 0.3.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\RequestOptions $requestOptions The request options to use.
         * @return void
         */
        public function setRequestOptions(\WordPress\AiClient\Providers\Http\DTO\RequestOptions $requestOptions): void;
        /**
         * Gets the request options for HTTP transport.
         *
         * @since 0.3.0
         *
         * @return \WordPress\AiClient\Providers\Http\DTO\RequestOptions|null The request options, or null if not set.
         */
        public function getRequestOptions(): ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions;
    }
}
namespace WordPress\AiClient\Providers\Http\Contracts {
    /**
     * Interface for models that require HTTP transport capabilities.
     *
     * @since 0.1.0
     */
    interface WithHttpTransporterInterface
    {
        /**
         * Sets the HTTP transporter.
         *
         * @since 0.1.0
         *
         * @param HttpTransporterInterface $transporter The HTTP transporter instance.
         * @return void
         */
        public function setHttpTransporter(\WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface $transporter): void;
        /**
         * Returns the HTTP transporter.
         *
         * @since 0.1.0
         *
         * @return HttpTransporterInterface The HTTP transporter instance.
         */
        public function getHttpTransporter(): \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
    }
    /**
     * Interface for models that support request authentication.
     *
     * @since 0.1.0
     */
    interface WithRequestAuthenticationInterface
    {
        /**
         * Sets the request authentication.
         *
         * @since 0.1.0
         *
         * @param RequestAuthenticationInterface $authentication The authentication instance.
         * @return void
         */
        public function setRequestAuthentication(\WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface $authentication): void;
        /**
         * Returns the request authentication.
         *
         * @since 0.1.0
         *
         * @return RequestAuthenticationInterface The authentication instance.
         */
        public function getRequestAuthentication(): \WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
    }
}
namespace WordPress\AiClient\Providers\Http\Traits {
    /**
     * Trait for a class that implements WithHttpTransporterInterface.
     *
     * @since 0.1.0
     */
    trait WithHttpTransporterTrait
    {
        /**
         * @var \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface|null The HTTP transporter instance.
         */
        private ?\WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface $httpTransporter = null;
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function setHttpTransporter(\WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface $httpTransporter): void
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function getHttpTransporter(): \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface
        {
        }
    }
    /**
     * Trait for a class that implements WithRequestAuthenticationInterface.
     *
     * @since 0.1.0
     */
    trait WithRequestAuthenticationTrait
    {
        /**
         * @var \WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface|null The request authentication instance.
         */
        private ?\WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface $requestAuthentication = null;
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function setRequestAuthentication(\WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface $requestAuthentication): void
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function getRequestAuthentication(): \WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface
        {
        }
    }
}
namespace WordPress\AiClient\Providers\ApiBasedImplementation {
    /**
     * Base class for an API-based model for a provider.
     *
     * While this class contains no abstract methods, it is still abstract to ensure that each model class can actually
     * perform generative AI tasks by implementing the corresponding interfaces.
     *
     * @since 0.1.0
     */
    abstract class AbstractApiBasedModel implements \WordPress\AiClient\Providers\ApiBasedImplementation\Contracts\ApiBasedModelInterface, \WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface, \WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface
    {
        use \WordPress\AiClient\Providers\Http\Traits\WithHttpTransporterTrait;
        use \WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata The metadata for the model.
         * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata $providerMetadata The metadata for the model's provider.
         */
        public function __construct(\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata, \WordPress\AiClient\Providers\DTO\ProviderMetadata $providerMetadata)
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        final public function metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        final public function providerMetadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        final public function setConfig(\WordPress\AiClient\Providers\Models\DTO\ModelConfig $config): void
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        final public function getConfig(): \WordPress\AiClient\Providers\Models\DTO\ModelConfig
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.3.0
         */
        final public function setRequestOptions(\WordPress\AiClient\Providers\Http\DTO\RequestOptions $requestOptions): void
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.3.0
         */
        final public function getRequestOptions(): ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions
        {
        }
    }
}
namespace WordPress\AiClient\Providers\Contracts {
    /**
     * Interface for accessing model metadata within a provider.
     *
     * Provides methods to list, check, and retrieve model metadata
     * for all models supported by a provider.
     *
     * @since 0.1.0
     */
    interface ModelMetadataDirectoryInterface
    {
        /**
         * Lists all available model metadata.
         *
         * @since 0.1.0
         *
         * @return list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata> Array of model metadata.
         */
        public function listModelMetadata(): array;
        /**
         * Checks if metadata exists for a specific model.
         *
         * @since 0.1.0
         *
         * @param string $modelId Model identifier.
         * @return bool True if metadata exists, false otherwise.
         */
        public function hasModelMetadata(string $modelId): bool;
        /**
         * Gets metadata for a specific model.
         *
         * @since 0.1.0
         *
         * @param string $modelId Model identifier.
         * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata Model metadata.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If model metadata not found.
         */
        public function getModelMetadata(string $modelId): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
    }
}
namespace WordPress\AiClient\Providers\ApiBasedImplementation {
    /**
     * Base class for an API-based model metadata directory for a provider.
     *
     * @since 0.1.0
     */
    abstract class AbstractApiBasedModelMetadataDirectory implements \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface, \WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface, \WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface, \WordPress\AiClient\Common\Contracts\CachesDataInterface
    {
        use \WordPress\AiClient\Providers\Http\Traits\WithHttpTransporterTrait;
        use \WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;
        use \WordPress\AiClient\Common\Traits\WithDataCachingTrait;
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        final public function listModelMetadata(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        final public function hasModelMetadata(string $modelId): bool
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        final public function getModelMetadata(string $modelId): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.4.0
         */
        protected function getCachedKeys(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.4.0
         */
        protected function getBaseCacheKey(): string
        {
        }
        /**
         * Sends the API request to list models from the provider and returns the map of model ID to model metadata.
         *
         * @since 0.1.0
         *
         * @return array<string, \WordPress\AiClient\Providers\Models\DTO\ModelMetadata> Map of model ID to model metadata.
         */
        abstract protected function sendListModelsRequest(): array;
    }
    /**
     * Base class for API-based providers.
     *
     * This abstract class provides URL construction utilities for providers that
     * communicate with REST APIs. It standardizes the pattern of combining a base
     * URL with endpoint paths.
     *
     * @since 0.2.0
     */
    abstract class AbstractApiProvider extends \WordPress\AiClient\Providers\AbstractProvider
    {
        /**
         * Gets the base URL for the provider's API.
         *
         * The base URL should include the protocol and domain, and may include
         * the API version path (e.g., "https://api.example.com/v1").
         *
         * @since 0.2.0
         *
         * @return string The base URL for the provider's API.
         */
        abstract protected static function baseUrl(): string;
        /**
         * Constructs a full URL by combining the base URL with an optional path.
         *
         * This method ensures proper URL construction by:
         * - Using the provider's base URL
         * - Trimming leading slashes from the path to prevent double-slashes
         * - Joining the base URL and path with a single forward slash
         *
         * @since 0.2.0
         *
         * @param string $path Optional path to append to the base URL. Default empty string.
         * @return string The complete URL.
         */
        public static function url(string $path = ''): string
        {
        }
    }
}
namespace WordPress\AiClient\Providers\Contracts {
    /**
     * Interface for checking provider availability.
     *
     * Determines whether a provider is configured and available
     * for use based on API keys, credentials, or other requirements.
     *
     * @since 0.1.0
     */
    interface ProviderAvailabilityInterface
    {
        /**
         * Checks if the provider is configured.
         *
         * @since 0.1.0
         *
         * @return bool True if the provider is configured and available, false otherwise.
         */
        public function isConfigured(): bool;
    }
}
namespace WordPress\AiClient\Providers\ApiBasedImplementation {
    /**
     * Class to check availability for an API-based provider via a test request to the endpoint to generate text.
     *
     * This class should be used for cloud-based providers that do not offer a model listing endpoint, but do offer a
     * text generation endpoint which requires authentication. A minimal request to this endpoint is used to determine
     * if the provider is properly configured with valid credentials.
     *
     * @since 0.1.0
     */
    class GenerateTextApiBasedProviderAvailability implements \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface
    {
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Models\Contracts\ModelInterface $model The model to use for checking availability.
         */
        public function __construct(\WordPress\AiClient\Providers\Models\Contracts\ModelInterface $model)
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function isConfigured(): bool
        {
        }
    }
    /**
     * Class to check availability for an API-based provider via a test request to the endpoint to list models.
     *
     * This class should be used for cloud-based providers that offer a model listing endpoint which requires
     * authentication. A request to this endpoint is used to determine if the provider is properly configured
     * with valid credentials.
     *
     * @since 0.1.0
     */
    class ListModelsApiBasedProviderAvailability implements \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface
    {
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface $modelMetadataDirectory The model metadata directory to use for checking
         *                                                                availability.
         */
        public function __construct(\WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface $modelMetadataDirectory)
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function isConfigured(): bool
        {
        }
    }
}
namespace WordPress\AiClient\Providers\Contracts {
    /**
     * Interface for handling provider-level operations.
     *
     * Provides methods to retrieve and manage long-running operations
     * across all models within a provider. Operations are tracked at the
     * provider level rather than per-model.
     *
     * @since 0.1.0
     */
    interface ProviderOperationsHandlerInterface
    {
        /**
         * Gets an operation by ID.
         *
         * @since 0.1.0
         *
         * @param string $operationId Operation identifier.
         * @return \WordPress\AiClient\Operations\Contracts\OperationInterface The operation.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If operation not found.
         */
        public function getOperation(string $operationId): \WordPress\AiClient\Operations\Contracts\OperationInterface;
    }
    /**
     * Interface for providers that support operations handlers.
     *
     * Providers implementing this interface can return an operations handler
     * for managing long-running operations across all their models.
     *
     * @since 0.1.0
     */
    interface ProviderWithOperationsHandlerInterface
    {
        /**
         * Gets the operations handler for this provider.
         *
         * @since 0.1.0
         *
         * @return ProviderOperationsHandlerInterface The operations handler.
         */
        public static function operationsHandler(): \WordPress\AiClient\Providers\Contracts\ProviderOperationsHandlerInterface;
    }
}
namespace WordPress\AiClient\Providers\DTO {
    /**
     * Represents metadata about an AI provider.
     *
     * This class contains information about an AI provider, including its
     * unique identifier, display name, and type (cloud, server, or client).
     *
     * @since 0.1.0
     * @since 1.2.0 Added optional description property.
     * @since 1.3.0 Added optional logoPath property.
     *
     * @phpstan-type ProviderMetadataArrayShape array{
     *     id: string,
     *     name: string,
     *     description?: ?string,
     *     type: string,
     *     credentialsUrl?: ?string,
     *     authenticationMethod?: ?string,
     *     logoPath?: ?string
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<ProviderMetadataArrayShape>
     */
    class ProviderMetadata extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_ID = 'id';
        public const KEY_NAME = 'name';
        public const KEY_DESCRIPTION = 'description';
        public const KEY_TYPE = 'type';
        public const KEY_CREDENTIALS_URL = 'credentialsUrl';
        public const KEY_AUTHENTICATION_METHOD = 'authenticationMethod';
        public const KEY_LOGO_PATH = 'logoPath';
        /**
         * @var string The provider's unique identifier.
         */
        protected string $id;
        /**
         * @var string The provider's display name.
         */
        protected string $name;
        /**
         * @var string|null The provider's description.
         */
        protected ?string $description;
        /**
         * @var \WordPress\AiClient\Providers\Enums\ProviderTypeEnum The provider type.
         */
        protected \WordPress\AiClient\Providers\Enums\ProviderTypeEnum $type;
        /**
         * @var string|null The URL where users can get credentials.
         */
        protected ?string $credentialsUrl;
        /**
         * @var \WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod|null The authentication method.
         */
        protected ?\WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod $authenticationMethod;
        /**
         * @var string|null The full path to the provider's logo image file.
         */
        protected ?string $logoPath;
        /**
         * Constructor.
         *
         * @since 0.1.0
         * @since 1.2.0 Added optional $description parameter.
         * @since 1.3.0 Added optional $logoPath parameter.
         *
         * @param string $id The provider's unique identifier.
         * @param string $name The provider's display name.
         * @param \WordPress\AiClient\Providers\Enums\ProviderTypeEnum $type The provider type.
         * @param string|null $credentialsUrl The URL where users can get credentials.
         * @param \WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod|null $authenticationMethod The authentication method.
         * @param string|null $description The provider's description.
         * @param string|null $logoPath The full path to the provider's logo image file.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the provider ID contains invalid characters.
         */
        public function __construct(string $id, string $name, \WordPress\AiClient\Providers\Enums\ProviderTypeEnum $type, ?string $credentialsUrl = null, ?\WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod $authenticationMethod = null, ?string $description = null, ?string $logoPath = null)
        {
        }
        /**
         * Gets the provider's unique identifier.
         *
         * @since 0.1.0
         *
         * @return string The provider ID.
         */
        public function getId(): string
        {
        }
        /**
         * Gets the provider's display name.
         *
         * @since 0.1.0
         *
         * @return string The provider name.
         */
        public function getName(): string
        {
        }
        /**
         * Gets the provider's description.
         *
         * @since 1.2.0
         *
         * @return string|null The provider description.
         */
        public function getDescription(): ?string
        {
        }
        /**
         * Gets the provider type.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\Enums\ProviderTypeEnum The provider type.
         */
        public function getType(): \WordPress\AiClient\Providers\Enums\ProviderTypeEnum
        {
        }
        /**
         * Gets the credentials URL.
         *
         * @since 0.1.0
         *
         * @return string|null The credentials URL.
         */
        public function getCredentialsUrl(): ?string
        {
        }
        /**
         * Gets the authentication method.
         *
         * @since 0.4.0
         *
         * @return \WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod|null The authentication method.
         */
        public function getAuthenticationMethod(): ?\WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod
        {
        }
        /**
         * Gets the full path to the provider's logo image file.
         *
         * @since 1.3.0
         *
         * @return string|null The full path to the logo image file.
         */
        public function getLogoPath(): ?string
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         * @since 1.2.0 Added description to schema.
         * @since 1.3.0 Added logoPath to schema.
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         * @since 1.2.0 Added description to output.
         * @since 1.3.0 Added logoPath to output.
         *
         * @return ProviderMetadataArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         * @since 1.2.0 Added description support.
         * @since 1.3.0 Added logoPath support.
         */
        public static function fromArray(array $array): self
        {
        }
    }
    /**
     * Represents metadata about a provider and its available models.
     *
     * This class combines provider information with the models that
     * the provider offers, facilitating model discovery and selection.
     *
     * @since 0.1.0
     *
     * @phpstan-import-type ProviderMetadataArrayShape from ProviderMetadata
     * @phpstan-import-type ModelMetadataArrayShape from \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
     *
     * @phpstan-type ProviderModelsMetadataArrayShape array{
     *     provider: ProviderMetadataArrayShape,
     *     models: list<ModelMetadataArrayShape>
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<ProviderModelsMetadataArrayShape>
     */
    class ProviderModelsMetadata extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_PROVIDER = 'provider';
        public const KEY_MODELS = 'models';
        /**
         * @var ProviderMetadata The provider metadata.
         */
        protected \WordPress\AiClient\Providers\DTO\ProviderMetadata $provider;
        /**
         * @var list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata> The available models.
         */
        protected array $models;
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param ProviderMetadata $provider The provider metadata.
         * @param list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata> $models The available models.
         *
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If models is not a list.
         */
        public function __construct(\WordPress\AiClient\Providers\DTO\ProviderMetadata $provider, array $models)
        {
        }
        /**
         * Creates a deep clone of this metadata.
         *
         * Clones the provider metadata and all model metadata objects
         * to ensure the cloned instance is independent of the original.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
        /**
         * Gets the provider metadata.
         *
         * @since 0.1.0
         *
         * @return ProviderMetadata The provider metadata.
         */
        public function getProvider(): \WordPress\AiClient\Providers\DTO\ProviderMetadata
        {
        }
        /**
         * Gets the available models.
         *
         * @since 0.1.0
         *
         * @return list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata> The available models.
         */
        public function getModels(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return ProviderModelsMetadataArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
    }
}
namespace WordPress\AiClient\Providers\Enums {
    /**
     * Enum for provider types.
     *
     * @since 0.1.0
     *
     * @method static self cloud() Creates an instance for CLOUD type.
     * @method static self server() Creates an instance for SERVER type.
     * @method static self client() Creates an instance for CLIENT type.
     * @method bool isCloud() Checks if the type is CLOUD.
     * @method bool isServer() Checks if the type is SERVER.
     * @method bool isClient() Checks if the type is CLIENT.
     */
    class ProviderTypeEnum extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * Cloud-based AI provider (e.g. models available via external REST APIs).
         */
        public const CLOUD = 'cloud';
        /**
         * Server-side AI provider (e.g. self-hosted models).
         */
        public const SERVER = 'server';
        /**
         * Client-side AI provider (e.g. browser-based models).
         */
        public const CLIENT = 'client';
    }
    /**
     * Enum for tool types.
     *
     * @since 0.1.0
     *
     * @method static self functionDeclarations() Creates an instance for FUNCTION_DECLARATIONS type.
     * @method static self webSearch() Creates an instance for WEB_SEARCH type.
     * @method bool isFunctionDeclarations() Checks if the type is FUNCTION_DECLARATIONS.
     * @method bool isWebSearch() Checks if the type is WEB_SEARCH.
     */
    class ToolTypeEnum extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * Function declarations tool type.
         */
        public const FUNCTION_DECLARATIONS = 'function_declarations';
        /**
         * Web search tool type.
         */
        public const WEB_SEARCH = 'web_search';
    }
}
namespace WordPress\AiClient\Providers\Http\Collections {
    /**
     * Simple collection for managing HTTP headers with case-insensitive access.
     *
     * This class stores HTTP headers while preserving their original casing
     * and provides efficient case-insensitive lookups.
     *
     * @since 0.1.0
     */
    class HeadersCollection
    {
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param array<string, string|list<string>> $headers Initial headers.
         */
        public function __construct(array $headers = [])
        {
        }
        /**
         * Gets a specific header value.
         *
         * @since 0.1.0
         *
         * @param string $name The header name (case-insensitive).
         * @return list<string>|null The header value(s) or null if not found.
         */
        public function get(string $name): ?array
        {
        }
        /**
         * Gets all headers.
         *
         * @since 0.1.0
         *
         * @return array<string, list<string>> All headers with their original casing.
         */
        public function getAll(): array
        {
        }
        /**
         * Gets header values as a comma-separated string.
         *
         * @since 0.1.0
         *
         * @param string $name The header name (case-insensitive).
         * @return string|null The header values as a comma-separated string or null if not found.
         */
        public function getAsString(string $name): ?string
        {
        }
        /**
         * Checks if a header exists.
         *
         * @since 0.1.0
         *
         * @param string $name The header name (case-insensitive).
         * @return bool True if the header exists, false otherwise.
         */
        public function has(string $name): bool
        {
        }
        /**
         * Returns a new instance with the specified header.
         *
         * @since 0.1.0
         *
         * @param string $name The header name.
         * @param string|list<string> $value The header value(s).
         * @return self A new instance with the header.
         */
        public function withHeader(string $name, $value): self
        {
        }
    }
}
namespace WordPress\AiClient\Providers\Http\Contracts {
    /**
     * Interface for HTTP transport implementations.
     *
     * Handles sending HTTP requests and receiving responses using
     * PSR-7, PSR-17, and PSR-18 standards internally.
     *
     * @since 0.1.0
     */
    interface HttpTransporterInterface
    {
        /**
         * Sends an HTTP request and returns the response.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Request $request The request to send.
         * @param \WordPress\AiClient\Providers\Http\DTO\RequestOptions|null $options Optional transport options for the request.
         * @return \WordPress\AiClient\Providers\Http\DTO\Response The response received.
         */
        public function send(\WordPress\AiClient\Providers\Http\DTO\Request $request, ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions $options = null): \WordPress\AiClient\Providers\Http\DTO\Response;
    }
    /**
     * Interface for HTTP request authentication.
     *
     * @since 0.1.0
     */
    interface RequestAuthenticationInterface extends \WordPress\AiClient\Common\Contracts\WithJsonSchemaInterface
    {
        /**
         * Authenticates an HTTP request.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Request $request The request to authenticate.
         * @return \WordPress\AiClient\Providers\Http\DTO\Request The authenticated request.
         */
        public function authenticateRequest(\WordPress\AiClient\Providers\Http\DTO\Request $request): \WordPress\AiClient\Providers\Http\DTO\Request;
    }
}
namespace WordPress\AiClient\Providers\Http\DTO {
    /**
     * Class for HTTP request authentication using an API key.
     *
     * @since 0.1.0
     *
     * @phpstan-type ApiKeyRequestAuthenticationArrayShape array{
     *     apiKey: string
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<ApiKeyRequestAuthenticationArrayShape>
     */
    class ApiKeyRequestAuthentication extends \WordPress\AiClient\Common\AbstractDataTransferObject implements \WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface
    {
        public const KEY_API_KEY = 'apiKey';
        /**
         * @var string The API key used for authentication.
         */
        protected string $apiKey;
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param string $apiKey The API key used for authentication.
         */
        public function __construct(string $apiKey)
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function authenticateRequest(\WordPress\AiClient\Providers\Http\DTO\Request $request): \WordPress\AiClient\Providers\Http\DTO\Request
        {
        }
        /**
         * Gets the API key.
         *
         * @since 0.1.0
         *
         * @return string The API key.
         */
        public function getApiKey(): string
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @since 0.1.0
         *
         * @return ApiKeyRequestAuthenticationArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
    }
    /**
     * Represents an HTTP request.
     *
     * This class encapsulates HTTP request data that can be converted
     * to PSR-7 requests by the HTTP transporter.
     *
     * @since 0.1.0
     *
     * @phpstan-import-type RequestOptionsArrayShape from RequestOptions
     * @phpstan-type RequestArrayShape array{
     *     method: string,
     *     uri: string,
     *     headers: array<string, list<string>>,
     *     body?: string|null,
     *     options?: RequestOptionsArrayShape
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<RequestArrayShape>
     */
    class Request extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_METHOD = 'method';
        public const KEY_URI = 'uri';
        public const KEY_HEADERS = 'headers';
        public const KEY_BODY = 'body';
        public const KEY_OPTIONS = 'options';
        /**
         * @var \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum The HTTP method.
         */
        protected \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum $method;
        /**
         * @var string The request URI.
         */
        protected string $uri;
        /**
         * @var \WordPress\AiClient\Providers\Http\Collections\HeadersCollection The request headers.
         */
        protected \WordPress\AiClient\Providers\Http\Collections\HeadersCollection $headers;
        /**
         * @var array<string, mixed>|null The request data (for query params or form data).
         */
        protected ?array $data = null;
        /**
         * @var string|null The request body (raw string content).
         */
        protected ?string $body = null;
        /**
         * @var RequestOptions|null Request transport options.
         */
        protected ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions $options = null;
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum $method The HTTP method.
         * @param string $uri The request URI.
         * @param array<string, string|list<string>> $headers The request headers.
         * @param string|array<string, mixed>|null $data The request data.
         * @param RequestOptions|null $options The request transport options.
         *
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the URI is empty.
         */
        public function __construct(\WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum $method, string $uri, array $headers = [], $data = null, ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions $options = null)
        {
        }
        /**
         * Creates a deep clone of this request.
         *
         * Clones the headers collection and request options to ensure
         * the cloned request is independent of the original.
         * The HTTP method enum is immutable and can be safely shared.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
        /**
         * Gets the HTTP method.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum The HTTP method.
         */
        public function getMethod(): \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum
        {
        }
        /**
         * Gets the request URI.
         *
         * For GET requests with array data, appends the data as query parameters.
         *
         * @since 0.1.0
         *
         * @return string The URI.
         */
        public function getUri(): string
        {
        }
        /**
         * Gets the request headers.
         *
         * @since 0.1.0
         *
         * @return array<string, list<string>> The headers.
         */
        public function getHeaders(): array
        {
        }
        /**
         * Gets a specific header value.
         *
         * @since 0.1.0
         *
         * @param string $name The header name (case-insensitive).
         * @return list<string>|null The header value(s) or null if not found.
         */
        public function getHeader(string $name): ?array
        {
        }
        /**
         * Gets header values as a comma-separated string.
         *
         * @since 0.1.0
         *
         * @param string $name The header name (case-insensitive).
         * @return string|null The header values as a comma-separated string, or null if not found.
         */
        public function getHeaderAsString(string $name): ?string
        {
        }
        /**
         * Checks if a header exists.
         *
         * @since 0.1.0
         *
         * @param string $name The header name (case-insensitive).
         * @return bool True if the header exists, false otherwise.
         */
        public function hasHeader(string $name): bool
        {
        }
        /**
         * Gets the request body.
         *
         * For GET requests, returns null.
         * For POST/PUT/PATCH requests:
         * - If body is set, returns it as-is
         * - If data is set and Content-Type is JSON, returns JSON-encoded data
         * - If data is set and Content-Type is form, returns URL-encoded data
         *
         * @since 0.1.0
         *
         * @return string|null The body.
         * @throws \JsonException If the data cannot be encoded to JSON.
         */
        public function getBody(): ?string
        {
        }
        /**
         * Returns a new instance with the specified header.
         *
         * @since 0.1.0
         *
         * @param string $name The header name.
         * @param string|list<string> $value The header value(s).
         * @return self A new instance with the header.
         */
        public function withHeader(string $name, $value): self
        {
        }
        /**
         * Returns a new instance with the specified data.
         *
         * @since 0.1.0
         *
         * @param string|array<string, mixed> $data The request data.
         * @return self A new instance with the data.
         */
        public function withData($data): self
        {
        }
        /**
         * Gets the request data array.
         *
         * @since 0.1.0
         *
         * @return array<string, mixed>|null The request data array.
         */
        public function getData(): ?array
        {
        }
        /**
         * Gets the request options.
         *
         * @since 0.2.0
         *
         * @return RequestOptions|null Request transport options when configured.
         */
        public function getOptions(): ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions
        {
        }
        /**
         * Returns a new instance with the specified request options.
         *
         * @since 0.2.0
         *
         * @param RequestOptions|null $options The request options to apply.
         * @return self A new instance with the options.
         */
        public function withOptions(?\WordPress\AiClient\Providers\Http\DTO\RequestOptions $options): self
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return RequestArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
        /**
         * Creates a Request instance from a PSR-7 RequestInterface.
         *
         * @since 0.2.0
         *
         * @param \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface $psrRequest The PSR-7 request to convert.
         * @return self A new Request instance.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the HTTP method is not supported.
         */
        public static function fromPsrRequest(\WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface $psrRequest): self
        {
        }
    }
    /**
     * Represents optional HTTP transport configuration for a single request.
     *
     * Provides mutable setters for working with timeouts and redirect handling.
     *
     * @since 0.2.0
     *
     * @phpstan-type RequestOptionsArrayShape array{
     *     timeout?: float|null,
     *     connectTimeout?: float|null,
     *     maxRedirects?: int|null
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<RequestOptionsArrayShape>
     */
    class RequestOptions extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_TIMEOUT = 'timeout';
        public const KEY_CONNECT_TIMEOUT = 'connectTimeout';
        public const KEY_MAX_REDIRECTS = 'maxRedirects';
        /**
         * @var float|null Maximum duration in seconds to wait for the full response.
         */
        protected ?float $timeout = null;
        /**
         * @var float|null Maximum duration in seconds to wait for the initial connection.
         */
        protected ?float $connectTimeout = null;
        /**
         * @var int|null Maximum number of redirects to follow. 0 disables redirects, null is unspecified.
         */
        protected ?int $maxRedirects = null;
        /**
         * Sets the request timeout in seconds.
         *
         * @since 0.2.0
         *
         * @param float|null $timeout Timeout in seconds.
         * @return void
         *
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException When timeout is negative.
         */
        public function setTimeout(?float $timeout): void
        {
        }
        /**
         * Sets the connection timeout in seconds.
         *
         * @since 0.2.0
         *
         * @param float|null $timeout Connection timeout in seconds.
         * @return void
         *
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException When timeout is negative.
         */
        public function setConnectTimeout(?float $timeout): void
        {
        }
        /**
         * Sets the maximum number of redirects to follow.
         *
         * Set to 0 to disable redirects, null for unspecified, or a positive integer
         * to enable redirects with a maximum count.
         *
         * @since 0.2.0
         *
         * @param int|null $maxRedirects Maximum redirects to follow, or 0 to disable, or null for unspecified.
         * @return void
         *
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException When redirect count is negative.
         */
        public function setMaxRedirects(?int $maxRedirects): void
        {
        }
        /**
         * Gets the request timeout in seconds.
         *
         * @since 0.2.0
         *
         * @return float|null Timeout in seconds.
         */
        public function getTimeout(): ?float
        {
        }
        /**
         * Gets the connection timeout in seconds.
         *
         * @since 0.2.0
         *
         * @return float|null Connection timeout in seconds.
         */
        public function getConnectTimeout(): ?float
        {
        }
        /**
         * Checks whether redirects are allowed.
         *
         * @since 0.2.0
         *
         * @return bool|null True when redirects are allowed (maxRedirects > 0),
         *                   false when disabled (maxRedirects = 0),
         *                   null when unspecified (maxRedirects = null).
         */
        public function allowsRedirects(): ?bool
        {
        }
        /**
         * Gets the maximum number of redirects to follow.
         *
         * @since 0.2.0
         *
         * @return int|null Maximum redirects or null when not specified.
         */
        public function getMaxRedirects(): ?int
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.2.0
         *
         * @return RequestOptionsArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.2.0
         */
        public static function fromArray(array $array): self
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.2.0
         */
        public static function getJsonSchema(): array
        {
        }
    }
    /**
     * Represents an HTTP response.
     *
     * This class encapsulates HTTP response data that has been converted
     * from PSR-7 responses by the HTTP transporter.
     *
     * @since 0.1.0
     *
     * @phpstan-type ResponseArrayShape array{
     *     statusCode: int,
     *     headers: array<string, list<string>>,
     *     body?: string|null
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<ResponseArrayShape>
     */
    class Response extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_STATUS_CODE = 'statusCode';
        public const KEY_HEADERS = 'headers';
        public const KEY_BODY = 'body';
        /**
         * @var int The HTTP status code.
         */
        protected int $statusCode;
        /**
         * @var \WordPress\AiClient\Providers\Http\Collections\HeadersCollection The response headers.
         */
        protected \WordPress\AiClient\Providers\Http\Collections\HeadersCollection $headers;
        /**
         * @var string|null The response body.
         */
        protected ?string $body;
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param int $statusCode The HTTP status code.
         * @param array<string, string|list<string>> $headers The response headers.
         * @param string|null $body The response body.
         *
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the status code is invalid.
         */
        public function __construct(int $statusCode, array $headers, ?string $body = null)
        {
        }
        /**
         * Creates a deep clone of this response.
         *
         * Clones the headers collection to ensure the cloned
         * response is independent of the original.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
        /**
         * Gets the HTTP status code.
         *
         * @since 0.1.0
         *
         * @return int The status code.
         */
        public function getStatusCode(): int
        {
        }
        /**
         * Gets the response headers.
         *
         * @since 0.1.0
         *
         * @return array<string, list<string>> The headers.
         */
        public function getHeaders(): array
        {
        }
        /**
         * Gets a specific header value.
         *
         * @since 0.1.0
         *
         * @param string $name The header name (case-insensitive).
         * @return list<string>|null The header value(s) or null if not found.
         */
        public function getHeader(string $name): ?array
        {
        }
        /**
         * Gets header values as a comma-separated string.
         *
         * @since 0.1.0
         *
         * @param string $name The header name (case-insensitive).
         * @return string|null The header values as a comma-separated string or null if not found.
         */
        public function getHeaderAsString(string $name): ?string
        {
        }
        /**
         * Gets the response body.
         *
         * @since 0.1.0
         *
         * @return string|null The body.
         */
        public function getBody(): ?string
        {
        }
        /**
         * Checks if the response has a header.
         *
         * @since 0.1.0
         *
         * @param string $name The header name.
         * @return bool True if the header exists, false otherwise.
         */
        public function hasHeader(string $name): bool
        {
        }
        /**
         * Checks if the response indicates success.
         *
         * @since 0.1.0
         *
         * @return bool True if status code is 2xx, false otherwise.
         */
        public function isSuccessful(): bool
        {
        }
        /**
         * Gets the response data as an array.
         *
         * Attempts to decode the body as JSON. Returns null if the body
         * is empty or not valid JSON.
         *
         * @since 0.1.0
         *
         * @return array<string, mixed>|null The decoded data or null.
         */
        public function getData(): ?array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return ResponseArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
    }
}
namespace WordPress\AiClient\Providers\Http\Enums {
    /**
     * Represents HTTP request methods.
     *
     * @since 0.1.0
     *
     * @method static self GET()
     * @method static self POST()
     * @method static self PUT()
     * @method static self PATCH()
     * @method static self DELETE()
     * @method static self HEAD()
     * @method static self OPTIONS()
     * @method static self CONNECT()
     * @method static self TRACE()
     *
     * @method bool isGet()
     * @method bool isPost()
     * @method bool isPut()
     * @method bool isPatch()
     * @method bool isDelete()
     * @method bool isHead()
     * @method bool isOptions()
     * @method bool isConnect()
     * @method bool isTrace()
     */
    final class HttpMethodEnum extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * GET method for retrieving resources.
         *
         * @var string
         */
        public const GET = 'GET';
        /**
         * POST method for creating resources.
         *
         * @var string
         */
        public const POST = 'POST';
        /**
         * PUT method for updating/replacing resources.
         *
         * @var string
         */
        public const PUT = 'PUT';
        /**
         * PATCH method for partially updating resources.
         *
         * @var string
         */
        public const PATCH = 'PATCH';
        /**
         * DELETE method for removing resources.
         *
         * @var string
         */
        public const DELETE = 'DELETE';
        /**
         * HEAD method for retrieving headers only.
         *
         * @var string
         */
        public const HEAD = 'HEAD';
        /**
         * OPTIONS method for retrieving allowed methods.
         *
         * @var string
         */
        public const OPTIONS = 'OPTIONS';
        /**
         * CONNECT method for establishing tunnel.
         *
         * @var string
         */
        public const CONNECT = 'CONNECT';
        /**
         * TRACE method for diagnostic purposes.
         *
         * @var string
         */
        public const TRACE = 'TRACE';
        /**
         * Checks if this method is idempotent.
         *
         * @since 0.1.0
         *
         * @return bool True if the method is idempotent, false otherwise.
         */
        public function isIdempotent(): bool
        {
        }
        /**
         * Checks if this method typically has a request body.
         *
         * @since 0.1.0
         *
         * @return bool True if the method typically has a body, false otherwise.
         */
        public function hasBody(): bool
        {
        }
    }
    /**
     * Enum for request authentication methods.
     *
     * @since 0.4.0
     *
     * @method static self apiKey() Creates an instance for API_KEY method.
     * @method bool isApiKey() Checks if the method is API_KEY.
     */
    class RequestAuthenticationMethod extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * API key authentication.
         */
        public const API_KEY = 'api_key';
        /**
         * Gets the implementation class for the authentication method.
         *
         * @since 0.4.0
         *
         * @return class-string<\WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface&\WordPress\AiClient\Common\Contracts\WithArrayTransformationInterface> The implementation class.
         *
         * @phpstan-ignore missingType.generics
         */
        public function getImplementationClass(): string
        {
        }
    }
}
namespace WordPress\AiClient\Providers\Http\Exception {
    /**
     * Exception thrown for 4xx HTTP client errors.
     *
     * This represents errors where the client request was malformed,
     * unauthorized, forbidden, or otherwise invalid.
     *
     * @since 0.2.0
     */
    class ClientException extends \WordPress\AiClient\Common\Exception\InvalidArgumentException
    {
        /**
         * The request that failed.
         *
         * @var \WordPress\AiClient\Providers\Http\DTO\Request|null
         */
        protected ?\WordPress\AiClient\Providers\Http\DTO\Request $request = null;
        /**
         * Returns the request that failed as our Request DTO.
         *
         * @since 0.2.0
         *
         * @return \WordPress\AiClient\Providers\Http\DTO\Request
         * @throws \RuntimeException If no request is available
         */
        public function getRequest(): \WordPress\AiClient\Providers\Http\DTO\Request
        {
        }
        /**
         * Creates a ClientException from a client error response (4xx).
         *
         * This method extracts error details from common API response formats
         * and creates an exception with a descriptive message and status code.
         *
         * @since 0.2.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The HTTP response that failed.
         * @return self
         */
        public static function fromClientErrorResponse(\WordPress\AiClient\Providers\Http\DTO\Response $response): self
        {
        }
    }
    /**
     * Exception thrown for network-related errors.
     *
     * This includes HTTP transport errors, connection failures,
     * timeouts, and other network-related issues.
     *
     * @since 0.2.0
     */
    class NetworkException extends \WordPress\AiClient\Common\Exception\RuntimeException
    {
        /**
         * The request that failed.
         *
         * @var \WordPress\AiClient\Providers\Http\DTO\Request|null
         */
        protected ?\WordPress\AiClient\Providers\Http\DTO\Request $request = null;
        /**
         * Returns the request that failed as our Request DTO.
         *
         * @since 0.2.0
         *
         * @return \WordPress\AiClient\Providers\Http\DTO\Request
         * @throws \RuntimeException If no request is available
         */
        public function getRequest(): \WordPress\AiClient\Providers\Http\DTO\Request
        {
        }
        /**
         * Creates a NetworkException from a PSR-18 network exception.
         *
         * @since 0.2.0
         *
         * @param \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface $psrRequest The PSR-7 request that failed.
         * @param \Throwable $networkException The PSR-18 network exception.
         * @return self
         */
        public static function fromPsr18NetworkException(\WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface $psrRequest, \Throwable $networkException): self
        {
        }
    }
    /**
     * Exception thrown for 3xx HTTP redirect responses.
     *
     * This represents cases where the server indicates that the request
     * should be retried at a different location, but automatic redirect
     * handling was not successful or not enabled.
     *
     * @since 0.2.0
     */
    class RedirectException extends \WordPress\AiClient\Common\Exception\RuntimeException
    {
        /**
         * Creates a RedirectException from a redirect response.
         *
         * This method extracts redirect information from the response headers
         * and creates an exception with a descriptive message and status code.
         *
         * @since 0.2.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The HTTP redirect response.
         * @return self
         */
        public static function fromRedirectResponse(\WordPress\AiClient\Providers\Http\DTO\Response $response): self
        {
        }
    }
    /**
     * Exception class for HTTP response errors.
     *
     * This is used when response data is unexpected or malformed,
     * typically indicating that a provider changed in ways our code
     * is not aware of or when parsing response data fails.
     *
     * @since 0.1.0
     */
    class ResponseException extends \WordPress\AiClient\Common\Exception\RuntimeException
    {
        /**
         * Creates a ResponseException for missing expected data.
         *
         * @since 0.2.0
         *
         * @param string $apiName The name of the API/provider.
         * @param string $fieldName The field that was expected but missing.
         * @return self
         */
        public static function fromMissingData(string $apiName, string $fieldName): self
        {
        }
        /**
         * Creates a ResponseException from invalid data in an API response.
         *
         * @since 0.2.0
         *
         * @param string $apiName The name of the API service (e.g., 'OpenAI', 'Anthropic').
         * @param string $fieldName The field that was invalid.
         * @param string $message The specific error message describing the invalid data.
         * @return self
         */
        public static function fromInvalidData(string $apiName, string $fieldName, string $message): self
        {
        }
    }
    /**
     * Exception thrown for 5xx HTTP server errors.
     *
     * This represents errors where the server failed to fulfill
     * a valid request due to internal server errors.
     *
     * @since 0.2.0
     */
    class ServerException extends \WordPress\AiClient\Common\Exception\RuntimeException
    {
        /**
         * Creates a ServerException from a server error response.
         *
         * This method extracts error details from common API response formats
         * and creates an exception with a descriptive message and status code.
         *
         * @since 0.2.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The HTTP response that failed.
         * @return self
         */
        public static function fromServerErrorResponse(\WordPress\AiClient\Providers\Http\DTO\Response $response): self
        {
        }
    }
}
namespace WordPress\AiClient\Providers\Http {
    /**
     * HTTP transporter implementation using HTTPlug.
     *
     * This class handles the conversion between custom Request/Response
     * objects and PSR-7 messages, using HTTPlug for client abstraction
     * and PSR-17 factories for message creation.
     *
     * @since 0.1.0
     */
    class HttpTransporter implements \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface
    {
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface|null $client PSR-18 HTTP client.
         * @param \WordPress\AiClientDependencies\Psr\Http\Message\RequestFactoryInterface|null $requestFactory PSR-17 request factory.
         * @param \WordPress\AiClientDependencies\Psr\Http\Message\StreamFactoryInterface|null $streamFactory PSR-17 stream factory.
         */
        public function __construct(?\WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface $client = null, ?\WordPress\AiClientDependencies\Psr\Http\Message\RequestFactoryInterface $requestFactory = null, ?\WordPress\AiClientDependencies\Psr\Http\Message\StreamFactoryInterface $streamFactory = null)
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         * @since 0.2.0 Added optional RequestOptions parameter and ClientWithOptions support.
         */
        public function send(\WordPress\AiClient\Providers\Http\DTO\Request $request, ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions $options = null): \WordPress\AiClient\Providers\Http\DTO\Response
        {
        }
    }
    /**
     * Factory for creating HTTP transporters.
     *
     * Uses HTTPlug's Discovery component to automatically find
     * available HTTP clients and factories.
     *
     * @since 0.1.0
     */
    class HttpTransporterFactory
    {
        /**
         * Creates an HTTP transporter.
         *
         * Uses HTTPlug Discovery to automatically find PSR-18 client
         * and PSR-17 factories if not provided.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface The HTTP transporter.
         */
        public static function createTransporter(): \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface
        {
        }
    }
}
namespace WordPress\AiClient\Providers\Http\Util {
    /**
     * Utility for extracting error messages from API response data.
     *
     * Centralizes the logic for parsing common API error response formats
     * to avoid code duplication across exception classes.
     *
     * @since 0.2.0
     * @since 0.4.0 Moved from Utilities namespace to Util namespace.
     */
    class ErrorMessageExtractor
    {
        /**
         * Extracts error message from API response data.
         *
         * Handles common error response formats:
         * - { "error": { "message": "Error text" } }
         * - { "error": "Error text" }
         * - { "message": "Error text" }
         *
         * @since 0.2.0
         *
         * @param mixed $data The response data to extract error message from.
         * @return string|null The extracted error message, or null if none found.
         */
        public static function extractFromResponseData($data): ?string
        {
        }
    }
    /**
     * Class with static utility methods to process HTTP responses.
     *
     * @since 0.1.0
     */
    class ResponseUtil
    {
        /**
         * Throws an appropriate exception if the given response is not successful.
         *
         * This method checks the HTTP status code of the response and throws
         * the appropriate exception type based on the status code range:
         * - 3xx: RedirectException (redirect responses)
         * - 4xx: ClientException (client errors)
         * - 5xx: ServerException (server errors)
         * - Other unsuccessful responses: RuntimeException (invalid status codes)
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The HTTP response to check.
         * @throws \WordPress\AiClient\Providers\Http\Exception\RedirectException If the response indicates a redirect (3xx).
         * @throws \WordPress\AiClient\Providers\Http\Exception\ClientException If the response indicates a client error (4xx).
         * @throws \WordPress\AiClient\Providers\Http\Exception\ServerException If the response indicates a server error (5xx).
         * @throws \RuntimeException If the response has an invalid status code.
         */
        public static function throwIfNotSuccessful(\WordPress\AiClient\Providers\Http\DTO\Response $response): void
        {
        }
    }
}
namespace WordPress\AiClient\Providers\Models\DTO {
    /**
     * Represents configuration for an AI model.
     *
     * This class allows configuring various parameters for model behavior,
     * including output modalities, system instructions, generation parameters,
     * and tool integrations.
     *
     * @since 0.1.0
     *
     * @phpstan-import-type FunctionDeclarationArrayShape from \WordPress\AiClient\Tools\DTO\FunctionDeclaration
     * @phpstan-import-type WebSearchArrayShape from \WordPress\AiClient\Tools\DTO\WebSearch
     *
     * @phpstan-type ModelConfigArrayShape array{
     *     outputModalities?: list<string>,
     *     systemInstruction?: string,
     *     candidateCount?: int,
     *     maxTokens?: int,
     *     temperature?: float,
     *     topP?: float,
     *     topK?: int,
     *     stopSequences?: list<string>,
     *     presencePenalty?: float,
     *     frequencyPenalty?: float,
     *     logprobs?: bool,
     *     topLogprobs?: int,
     *     functionDeclarations?: list<FunctionDeclarationArrayShape>,
     *     webSearch?: WebSearchArrayShape,
     *     outputFileType?: string,
     *     outputMimeType?: string,
     *     outputSchema?: array<string, mixed>,
     *     outputMediaOrientation?: string,
     *     outputMediaAspectRatio?: string,
     *     outputSpeechVoice?: string,
     *     customOptions?: array<string, mixed>
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<ModelConfigArrayShape>
     */
    class ModelConfig extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_OUTPUT_MODALITIES = 'outputModalities';
        public const KEY_SYSTEM_INSTRUCTION = 'systemInstruction';
        public const KEY_CANDIDATE_COUNT = 'candidateCount';
        public const KEY_MAX_TOKENS = 'maxTokens';
        public const KEY_TEMPERATURE = 'temperature';
        public const KEY_TOP_P = 'topP';
        public const KEY_TOP_K = 'topK';
        public const KEY_STOP_SEQUENCES = 'stopSequences';
        public const KEY_PRESENCE_PENALTY = 'presencePenalty';
        public const KEY_FREQUENCY_PENALTY = 'frequencyPenalty';
        public const KEY_LOGPROBS = 'logprobs';
        public const KEY_TOP_LOGPROBS = 'topLogprobs';
        public const KEY_FUNCTION_DECLARATIONS = 'functionDeclarations';
        public const KEY_WEB_SEARCH = 'webSearch';
        public const KEY_OUTPUT_FILE_TYPE = 'outputFileType';
        public const KEY_OUTPUT_MIME_TYPE = 'outputMimeType';
        public const KEY_OUTPUT_SCHEMA = 'outputSchema';
        public const KEY_OUTPUT_MEDIA_ORIENTATION = 'outputMediaOrientation';
        public const KEY_OUTPUT_MEDIA_ASPECT_RATIO = 'outputMediaAspectRatio';
        public const KEY_OUTPUT_SPEECH_VOICE = 'outputSpeechVoice';
        public const KEY_CUSTOM_OPTIONS = 'customOptions';
        /*
         * Note: This key is not an actual model config key, but specified here for convenience.
         * It is relevant for model discovery, to determine which models support which input modalities.
         * The actual input modalities are part of the message sent to the model, not the model config.
         */
        public const KEY_INPUT_MODALITIES = 'inputModalities';
        /**
         * @var list<\WordPress\AiClient\Messages\Enums\ModalityEnum>|null Output modalities for the model.
         */
        protected ?array $outputModalities = null;
        /**
         * @var string|null System instruction for the model.
         */
        protected ?string $systemInstruction = null;
        /**
         * @var int|null Number of response candidates to generate.
         */
        protected ?int $candidateCount = null;
        /**
         * @var int|null Maximum number of tokens to generate.
         */
        protected ?int $maxTokens = null;
        /**
         * @var float|null Temperature for randomness (0.0 to 2.0).
         */
        protected ?float $temperature = null;
        /**
         * @var float|null Top-p nucleus sampling parameter.
         */
        protected ?float $topP = null;
        /**
         * @var int|null Top-k sampling parameter.
         */
        protected ?int $topK = null;
        /**
         * @var list<string>|null Stop sequences.
         */
        protected ?array $stopSequences = null;
        /**
         * @var float|null Presence penalty for reducing repetition.
         */
        protected ?float $presencePenalty = null;
        /**
         * @var float|null Frequency penalty for reducing repetition.
         */
        protected ?float $frequencyPenalty = null;
        /**
         * @var bool|null Whether to return log probabilities.
         */
        protected ?bool $logprobs = null;
        /**
         * @var int|null Number of top log probabilities to return.
         */
        protected ?int $topLogprobs = null;
        /**
         * @var list<\WordPress\AiClient\Tools\DTO\FunctionDeclaration>|null Function declarations available to the model.
         */
        protected ?array $functionDeclarations = null;
        /**
         * @var \WordPress\AiClient\Tools\DTO\WebSearch|null Web search configuration for the model.
         */
        protected ?\WordPress\AiClient\Tools\DTO\WebSearch $webSearch = null;
        /**
         * @var \WordPress\AiClient\Files\Enums\FileTypeEnum|null Output file type.
         */
        protected ?\WordPress\AiClient\Files\Enums\FileTypeEnum $outputFileType = null;
        /**
         * @var string|null Output MIME type.
         */
        protected ?string $outputMimeType = null;
        /**
         * @var array<string, mixed>|null Output schema (JSON schema).
         */
        protected ?array $outputSchema = null;
        /**
         * @var \WordPress\AiClient\Files\Enums\MediaOrientationEnum|null Output media orientation.
         */
        protected ?\WordPress\AiClient\Files\Enums\MediaOrientationEnum $outputMediaOrientation = null;
        /**
         * @var string|null Output media aspect ratio (e.g. 3:2, 16:9).
         */
        protected ?string $outputMediaAspectRatio = null;
        /**
         * @var string|null Output speech voice.
         */
        protected ?string $outputSpeechVoice = null;
        /**
         * @var array<string, mixed> Custom provider-specific options.
         */
        protected array $customOptions = [];
        /**
         * Creates a deep clone of this configuration.
         *
         * Clones nested objects (functionDeclarations, webSearch) to ensure
         * the cloned configuration is independent of the original.
         * Enum value objects (outputModalities, outputFileType, outputMediaOrientation)
         * are intentionally shared as they are immutable.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
        /**
         * Sets the output modalities.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Messages\Enums\ModalityEnum> $outputModalities The output modalities.
         *
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the array is not a list.
         */
        public function setOutputModalities(array $outputModalities): void
        {
        }
        /**
         * Gets the output modalities.
         *
         * @since 0.1.0
         *
         * @return list<\WordPress\AiClient\Messages\Enums\ModalityEnum>|null The output modalities.
         */
        public function getOutputModalities(): ?array
        {
        }
        /**
         * Sets the system instruction.
         *
         * @since 0.1.0
         *
         * @param string $systemInstruction The system instruction.
         */
        public function setSystemInstruction(string $systemInstruction): void
        {
        }
        /**
         * Gets the system instruction.
         *
         * @since 0.1.0
         *
         * @return string|null The system instruction.
         */
        public function getSystemInstruction(): ?string
        {
        }
        /**
         * Sets the candidate count.
         *
         * @since 0.1.0
         *
         * @param int $candidateCount The candidate count.
         */
        public function setCandidateCount(int $candidateCount): void
        {
        }
        /**
         * Gets the candidate count.
         *
         * @since 0.1.0
         *
         * @return int|null The candidate count.
         */
        public function getCandidateCount(): ?int
        {
        }
        /**
         * Sets the maximum tokens.
         *
         * @since 0.1.0
         *
         * @param int $maxTokens The maximum tokens.
         */
        public function setMaxTokens(int $maxTokens): void
        {
        }
        /**
         * Gets the maximum tokens.
         *
         * @since 0.1.0
         *
         * @return int|null The maximum tokens.
         */
        public function getMaxTokens(): ?int
        {
        }
        /**
         * Sets the temperature.
         *
         * @since 0.1.0
         *
         * @param float $temperature The temperature.
         */
        public function setTemperature(float $temperature): void
        {
        }
        /**
         * Gets the temperature.
         *
         * @since 0.1.0
         *
         * @return float|null The temperature.
         */
        public function getTemperature(): ?float
        {
        }
        /**
         * Sets the top-p parameter.
         *
         * @since 0.1.0
         *
         * @param float $topP The top-p parameter.
         */
        public function setTopP(float $topP): void
        {
        }
        /**
         * Gets the top-p parameter.
         *
         * @since 0.1.0
         *
         * @return float|null The top-p parameter.
         */
        public function getTopP(): ?float
        {
        }
        /**
         * Sets the top-k parameter.
         *
         * @since 0.1.0
         *
         * @param int $topK The top-k parameter.
         */
        public function setTopK(int $topK): void
        {
        }
        /**
         * Gets the top-k parameter.
         *
         * @since 0.1.0
         *
         * @return int|null The top-k parameter.
         */
        public function getTopK(): ?int
        {
        }
        /**
         * Sets the stop sequences.
         *
         * @since 0.1.0
         *
         * @param list<string> $stopSequences The stop sequences.
         *
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the array is not a list.
         */
        public function setStopSequences(array $stopSequences): void
        {
        }
        /**
         * Gets the stop sequences.
         *
         * @since 0.1.0
         *
         * @return list<string>|null The stop sequences.
         */
        public function getStopSequences(): ?array
        {
        }
        /**
         * Sets the presence penalty.
         *
         * @since 0.1.0
         *
         * @param float $presencePenalty The presence penalty.
         */
        public function setPresencePenalty(float $presencePenalty): void
        {
        }
        /**
         * Gets the presence penalty.
         *
         * @since 0.1.0
         *
         * @return float|null The presence penalty.
         */
        public function getPresencePenalty(): ?float
        {
        }
        /**
         * Sets the frequency penalty.
         *
         * @since 0.1.0
         *
         * @param float $frequencyPenalty The frequency penalty.
         */
        public function setFrequencyPenalty(float $frequencyPenalty): void
        {
        }
        /**
         * Gets the frequency penalty.
         *
         * @since 0.1.0
         *
         * @return float|null The frequency penalty.
         */
        public function getFrequencyPenalty(): ?float
        {
        }
        /**
         * Sets whether to return log probabilities.
         *
         * @since 0.1.0
         *
         * @param bool $logprobs Whether to return log probabilities.
         */
        public function setLogprobs(bool $logprobs): void
        {
        }
        /**
         * Gets whether to return log probabilities.
         *
         * @since 0.1.0
         *
         * @return bool|null Whether to return log probabilities.
         */
        public function getLogprobs(): ?bool
        {
        }
        /**
         * Sets the number of top log probabilities to return.
         *
         * @since 0.1.0
         *
         * @param int $topLogprobs The number of top log probabilities.
         */
        public function setTopLogprobs(int $topLogprobs): void
        {
        }
        /**
         * Gets the number of top log probabilities to return.
         *
         * @since 0.1.0
         *
         * @return int|null The number of top log probabilities.
         */
        public function getTopLogprobs(): ?int
        {
        }
        /**
         * Sets the function declarations.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Tools\DTO\FunctionDeclaration> $functionDeclarations The function declarations.
         *
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the array is not a list.
         */
        public function setFunctionDeclarations(array $functionDeclarations): void
        {
        }
        /**
         * Gets the function declarations.
         *
         * @since 0.1.0
         *
         * @return list<\WordPress\AiClient\Tools\DTO\FunctionDeclaration>|null The function declarations.
         */
        public function getFunctionDeclarations(): ?array
        {
        }
        /**
         * Sets the web search configuration.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Tools\DTO\WebSearch $webSearch The web search configuration.
         */
        public function setWebSearch(\WordPress\AiClient\Tools\DTO\WebSearch $webSearch): void
        {
        }
        /**
         * Gets the web search configuration.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Tools\DTO\WebSearch|null The web search configuration.
         */
        public function getWebSearch(): ?\WordPress\AiClient\Tools\DTO\WebSearch
        {
        }
        /**
         * Sets the output file type.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Files\Enums\FileTypeEnum $outputFileType The output file type.
         */
        public function setOutputFileType(\WordPress\AiClient\Files\Enums\FileTypeEnum $outputFileType): void
        {
        }
        /**
         * Gets the output file type.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Files\Enums\FileTypeEnum|null The output file type.
         */
        public function getOutputFileType(): ?\WordPress\AiClient\Files\Enums\FileTypeEnum
        {
        }
        /**
         * Sets the output MIME type.
         *
         * @since 0.1.0
         *
         * @param string $outputMimeType The output MIME type.
         */
        public function setOutputMimeType(string $outputMimeType): void
        {
        }
        /**
         * Gets the output MIME type.
         *
         * @since 0.1.0
         *
         * @return string|null The output MIME type.
         */
        public function getOutputMimeType(): ?string
        {
        }
        /**
         * Sets the output schema.
         *
         * When setting an output schema, this method automatically sets
         * the output MIME type to "application/json" if not already set.
         *
         * @since 0.1.0
         *
         * @param array<string, mixed> $outputSchema The output schema (JSON schema).
         */
        public function setOutputSchema(array $outputSchema): void
        {
        }
        /**
         * Gets the output schema.
         *
         * @since 0.1.0
         *
         * @return array<string, mixed>|null The output schema.
         */
        public function getOutputSchema(): ?array
        {
        }
        /**
         * Sets the output media orientation.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Files\Enums\MediaOrientationEnum $outputMediaOrientation The output media orientation.
         */
        public function setOutputMediaOrientation(\WordPress\AiClient\Files\Enums\MediaOrientationEnum $outputMediaOrientation): void
        {
        }
        /**
         * Gets the output media orientation.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Files\Enums\MediaOrientationEnum|null The output media orientation.
         */
        public function getOutputMediaOrientation(): ?\WordPress\AiClient\Files\Enums\MediaOrientationEnum
        {
        }
        /**
         * Sets the output media aspect ratio.
         *
         * If set, this supersedes the output media orientation, as it is a more specific configuration.
         *
         * @since 0.1.0
         *
         * @param string $outputMediaAspectRatio The output media aspect ratio (e.g. 3:2, 16:9).
         */
        public function setOutputMediaAspectRatio(string $outputMediaAspectRatio): void
        {
        }
        /**
         * Gets the output media aspect ratio.
         *
         * @since 0.1.0
         *
         * @return string|null The output media aspect ratio (e.g. 3:2, 16:9).
         */
        public function getOutputMediaAspectRatio(): ?string
        {
        }
        /**
         * Validates that the given media orientation and aspect ratio values do not conflict with each other.
         *
         * @since 0.4.0
         *
         * @param \WordPress\AiClient\Files\Enums\MediaOrientationEnum $orientation The desired media orientation.
         * @param string $aspectRatio The desired media aspect ratio.
         */
        protected function validateMediaOrientationAspectRatioCompatibility(\WordPress\AiClient\Files\Enums\MediaOrientationEnum $orientation, string $aspectRatio): void
        {
        }
        /**
         * Sets the output speech voice.
         *
         * @since 0.1.0
         *
         * @param string $outputSpeechVoice The output speech voice.
         */
        public function setOutputSpeechVoice(string $outputSpeechVoice): void
        {
        }
        /**
         * Gets the output speech voice.
         *
         * @since 0.1.0
         *
         * @return string|null The output speech voice.
         */
        public function getOutputSpeechVoice(): ?string
        {
        }
        /**
         * Sets a single custom option.
         *
         * @since 0.1.0
         *
         * @param string $key   The option key.
         * @param mixed  $value The option value.
         */
        public function setCustomOption(string $key, $value): void
        {
        }
        /**
         * Sets the custom options.
         *
         * @since 0.1.0
         *
         * @param array<string, mixed> $customOptions The custom options.
         */
        public function setCustomOptions(array $customOptions): void
        {
        }
        /**
         * Gets the custom options.
         *
         * @since 0.1.0
         *
         * @return array<string, mixed> The custom options.
         */
        public function getCustomOptions(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return ModelConfigArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
    }
    /**
     * Represents metadata about an AI model.
     *
     * This class contains information about a specific AI model, including
     * its identifier, display name, supported capabilities, and configuration options.
     *
     * @since 0.1.0
     *
     * @phpstan-import-type SupportedOptionArrayShape from SupportedOption
     *
     * @phpstan-type ModelMetadataArrayShape array{
     *     id: string,
     *     name: string,
     *     supportedCapabilities: list<string>,
     *     supportedOptions: list<SupportedOptionArrayShape>
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<ModelMetadataArrayShape>
     */
    class ModelMetadata extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_ID = 'id';
        public const KEY_NAME = 'name';
        public const KEY_SUPPORTED_CAPABILITIES = 'supportedCapabilities';
        public const KEY_SUPPORTED_OPTIONS = 'supportedOptions';
        /**
         * @var string The model's unique identifier.
         */
        protected string $id;
        /**
         * @var string The model's display name.
         */
        protected string $name;
        /**
         * @var list<\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum> The model's supported capabilities.
         */
        protected array $supportedCapabilities;
        /**
         * @var list<SupportedOption> The model's supported configuration options.
         */
        protected array $supportedOptions;
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param string $id The model's unique identifier.
         * @param string $name The model's display name.
         * @param list<\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum> $supportedCapabilities The model's supported capabilities.
         * @param list<SupportedOption> $supportedOptions The model's supported configuration options.
         *
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If arrays are not lists.
         */
        public function __construct(string $id, string $name, array $supportedCapabilities, array $supportedOptions)
        {
        }
        /**
         * Gets the model's unique identifier.
         *
         * @since 0.1.0
         *
         * @return string The model ID.
         */
        public function getId(): string
        {
        }
        /**
         * Gets the model's display name.
         *
         * @since 0.1.0
         *
         * @return string The model name.
         */
        public function getName(): string
        {
        }
        /**
         * Gets the model's supported capabilities.
         *
         * @since 0.1.0
         *
         * @return list<\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum> The supported capabilities.
         */
        public function getSupportedCapabilities(): array
        {
        }
        /**
         * Gets the model's supported configuration options.
         *
         * @since 0.1.0
         *
         * @return list<SupportedOption> The supported options.
         */
        public function getSupportedOptions(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return ModelMetadataArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
        /**
         * Performs a deep clone of the model metadata.
         *
         * This method ensures that supported option objects are cloned to prevent
         * modifications to the cloned metadata from affecting the original.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
    }
    /**
     * Represents requirements that implementing code has for AI model selection.
     *
     * This class defines the capabilities and options that a model must support
     * in order to be considered suitable for the implementing code's needs.
     *
     * @since 0.1.0
     *
     * @phpstan-import-type RequiredOptionArrayShape from RequiredOption
     *
     * @phpstan-type ModelRequirementsArrayShape array{
     *     requiredCapabilities: list<string>,
     *     requiredOptions: list<RequiredOptionArrayShape>
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<ModelRequirementsArrayShape>
     */
    class ModelRequirements extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_REQUIRED_CAPABILITIES = 'requiredCapabilities';
        public const KEY_REQUIRED_OPTIONS = 'requiredOptions';
        /**
         * @var list<\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum> The capabilities that the model must support.
         */
        protected array $requiredCapabilities;
        /**
         * @var list<RequiredOption> The options that the model must support with specific values.
         */
        protected array $requiredOptions;
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum> $requiredCapabilities The capabilities that the model must support.
         * @param list<RequiredOption> $requiredOptions The options that the model must support with specific values.
         *
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If arrays are not lists.
         */
        public function __construct(array $requiredCapabilities, array $requiredOptions)
        {
        }
        /**
         * Gets the capabilities that the model must support.
         *
         * @since 0.1.0
         *
         * @return list<\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum> The required capabilities.
         */
        public function getRequiredCapabilities(): array
        {
        }
        /**
         * Gets the options that the model must support with specific values.
         *
         * @since 0.1.0
         *
         * @return list<RequiredOption> The required options.
         */
        public function getRequiredOptions(): array
        {
        }
        /**
         * Checks whether the given model metadata meets these requirements.
         *
         * @since 0.2.0
         *
         * @param ModelMetadata $metadata The model metadata to check against.
         * @return bool True if the model meets all requirements, false otherwise.
         */
        public function areMetBy(\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata): bool
        {
        }
        /**
         * Creates ModelRequirements from prompt data and model configuration.
         *
         * @since 0.2.0
         *
         * @param \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum $capability The capability the model must support.
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $messages The messages in the conversation.
         * @param ModelConfig $modelConfig The model configuration.
         * @return self The created requirements.
         */
        public static function fromPromptData(\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum $capability, array $messages, \WordPress\AiClient\Providers\Models\DTO\ModelConfig $modelConfig): self
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return ModelRequirementsArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
    }
    /**
     * Represents an option that the implementing code requires the model to support.
     *
     * This class defines an option that the model must support with a specific value
     * for it to be considered suitable for the implementing code's requirements.
     *
     * @since 0.1.0
     *
     * @phpstan-type RequiredOptionArrayShape array{
     *     name: string,
     *     value: mixed
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<RequiredOptionArrayShape>
     */
    class RequiredOption extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_NAME = 'name';
        public const KEY_VALUE = 'value';
        /**
         * @var \WordPress\AiClient\Providers\Models\Enums\OptionEnum The option name.
         */
        protected \WordPress\AiClient\Providers\Models\Enums\OptionEnum $name;
        /**
         * @var mixed The value that the model must support for this option.
         */
        protected $value;
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Models\Enums\OptionEnum $name The option name.
         * @param mixed $value The value that the model must support for this option.
         */
        public function __construct(\WordPress\AiClient\Providers\Models\Enums\OptionEnum $name, $value)
        {
        }
        /**
         * Gets the option name.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\Models\Enums\OptionEnum The option name.
         */
        public function getName(): \WordPress\AiClient\Providers\Models\Enums\OptionEnum
        {
        }
        /**
         * Gets the value that the model must support for this option.
         *
         * @since 0.1.0
         *
         * @return mixed The value that the model must support.
         */
        public function getValue()
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return RequiredOptionArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
    }
    /**
     * Represents a supported configuration option for an AI model.
     *
     * This class defines an option that a model supports, including its name
     * and the values that are valid for that option.
     *
     * @since 0.1.0
     *
     * @phpstan-type SupportedOptionArrayShape array{
     *     name: string,
     *     supportedValues?: list<mixed>
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<SupportedOptionArrayShape>
     */
    class SupportedOption extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_NAME = 'name';
        public const KEY_SUPPORTED_VALUES = 'supportedValues';
        /**
         * @var \WordPress\AiClient\Providers\Models\Enums\OptionEnum The option name.
         */
        protected \WordPress\AiClient\Providers\Models\Enums\OptionEnum $name;
        /**
         * @var list<mixed>|null The supported values for this option.
         */
        protected ?array $supportedValues;
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Models\Enums\OptionEnum $name The option name.
         * @param list<mixed>|null $supportedValues The supported values for this option, or null if any value is supported.
         *
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If supportedValues is not null and not a list.
         */
        public function __construct(\WordPress\AiClient\Providers\Models\Enums\OptionEnum $name, ?array $supportedValues = null)
        {
        }
        /**
         * Gets the option name.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\Models\Enums\OptionEnum The option name.
         */
        public function getName(): \WordPress\AiClient\Providers\Models\Enums\OptionEnum
        {
        }
        /**
         * Checks if a value is supported for this option.
         *
         * @since 0.1.0
         *
         * @param mixed $value The value to check.
         * @return bool True if the value is supported, false otherwise.
         */
        public function isSupportedValue($value): bool
        {
        }
        /**
         * Gets the supported values for this option.
         *
         * @since 0.1.0
         *
         * @return list<mixed>|null The supported values, or null if any value is supported.
         */
        public function getSupportedValues(): ?array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return SupportedOptionArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
    }
}
namespace WordPress\AiClient\Providers\Models\Enums {
    /**
     * Enum for model capabilities.
     *
     * @since 0.1.0
     *
     * @method static self textGeneration() Creates an instance for TEXT_GENERATION capability.
     * @method static self imageGeneration() Creates an instance for IMAGE_GENERATION capability.
     * @method static self textToSpeechConversion() Creates an instance for TEXT_TO_SPEECH_CONVERSION capability.
     * @method static self speechGeneration() Creates an instance for SPEECH_GENERATION capability.
     * @method static self musicGeneration() Creates an instance for MUSIC_GENERATION capability.
     * @method static self videoGeneration() Creates an instance for VIDEO_GENERATION capability.
     * @method static self embeddingGeneration() Creates an instance for EMBEDDING_GENERATION capability.
     * @method static self chatHistory() Creates an instance for CHAT_HISTORY capability.
     * @method bool isTextGeneration() Checks if the capability is TEXT_GENERATION.
     * @method bool isImageGeneration() Checks if the capability is IMAGE_GENERATION.
     * @method bool isTextToSpeechConversion() Checks if the capability is TEXT_TO_SPEECH_CONVERSION.
     * @method bool isSpeechGeneration() Checks if the capability is SPEECH_GENERATION.
     * @method bool isMusicGeneration() Checks if the capability is MUSIC_GENERATION.
     * @method bool isVideoGeneration() Checks if the capability is VIDEO_GENERATION.
     * @method bool isEmbeddingGeneration() Checks if the capability is EMBEDDING_GENERATION.
     * @method bool isChatHistory() Checks if the capability is CHAT_HISTORY.
     */
    class CapabilityEnum extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * Text generation capability.
         */
        public const TEXT_GENERATION = 'text_generation';
        /**
         * Image generation capability.
         */
        public const IMAGE_GENERATION = 'image_generation';
        /**
         * Text to speech conversion capability.
         */
        public const TEXT_TO_SPEECH_CONVERSION = 'text_to_speech_conversion';
        /**
         * Speech generation capability.
         */
        public const SPEECH_GENERATION = 'speech_generation';
        /**
         * Music generation capability.
         */
        public const MUSIC_GENERATION = 'music_generation';
        /**
         * Video generation capability.
         */
        public const VIDEO_GENERATION = 'video_generation';
        /**
         * Embedding generation capability.
         */
        public const EMBEDDING_GENERATION = 'embedding_generation';
        /**
         * Chat history support capability.
         */
        public const CHAT_HISTORY = 'chat_history';
    }
    /**
     * Enum for model options.
     *
     * This enum dynamically includes all options from ModelConfig KEY_* constants
     * in addition to the explicitly defined constants below.
     *
     * Explicitly defined option (not in ModelConfig):
     * @method static self inputModalities() Creates an instance for INPUT_MODALITIES option.
     * @method bool isInputModalities() Checks if the option is INPUT_MODALITIES.
     *
     * Dynamically loaded from ModelConfig KEY_* constants:
     * @method static self candidateCount() Creates an instance for CANDIDATE_COUNT option.
     * @method static self customOptions() Creates an instance for CUSTOM_OPTIONS option.
     * @method static self frequencyPenalty() Creates an instance for FREQUENCY_PENALTY option.
     * @method static self functionDeclarations() Creates an instance for FUNCTION_DECLARATIONS option.
     * @method static self logprobs() Creates an instance for LOGPROBS option.
     * @method static self maxTokens() Creates an instance for MAX_TOKENS option.
     * @method static self outputFileType() Creates an instance for OUTPUT_FILE_TYPE option.
     * @method static self outputMediaAspectRatio() Creates an instance for OUTPUT_MEDIA_ASPECT_RATIO option.
     * @method static self outputMediaOrientation() Creates an instance for OUTPUT_MEDIA_ORIENTATION option.
     * @method static self outputMimeType() Creates an instance for OUTPUT_MIME_TYPE option.
     * @method static self outputModalities() Creates an instance for OUTPUT_MODALITIES option.
     * @method static self outputSchema() Creates an instance for OUTPUT_SCHEMA option.
     * @method static self outputSpeechVoice() Creates an instance for OUTPUT_SPEECH_VOICE option.
     * @method static self presencePenalty() Creates an instance for PRESENCE_PENALTY option.
     * @method static self stopSequences() Creates an instance for STOP_SEQUENCES option.
     * @method static self systemInstruction() Creates an instance for SYSTEM_INSTRUCTION option.
     * @method static self temperature() Creates an instance for TEMPERATURE option.
     * @method static self topK() Creates an instance for TOP_K option.
     * @method static self topLogprobs() Creates an instance for TOP_LOGPROBS option.
     * @method static self topP() Creates an instance for TOP_P option.
     * @method static self webSearch() Creates an instance for WEB_SEARCH option.
     * @method bool isCandidateCount() Checks if the option is CANDIDATE_COUNT.
     * @method bool isCustomOptions() Checks if the option is CUSTOM_OPTIONS.
     * @method bool isFrequencyPenalty() Checks if the option is FREQUENCY_PENALTY.
     * @method bool isFunctionDeclarations() Checks if the option is FUNCTION_DECLARATIONS.
     * @method bool isLogprobs() Checks if the option is LOGPROBS.
     * @method bool isMaxTokens() Checks if the option is MAX_TOKENS.
     * @method bool isOutputFileType() Checks if the option is OUTPUT_FILE_TYPE.
     * @method bool isOutputMediaAspectRatio() Checks if the option is OUTPUT_MEDIA_ASPECT_RATIO.
     * @method bool isOutputMediaOrientation() Checks if the option is OUTPUT_MEDIA_ORIENTATION.
     * @method bool isOutputMimeType() Checks if the option is OUTPUT_MIME_TYPE.
     * @method bool isOutputModalities() Checks if the option is OUTPUT_MODALITIES.
     * @method bool isOutputSchema() Checks if the option is OUTPUT_SCHEMA.
     * @method bool isOutputSpeechVoice() Checks if the option is OUTPUT_SPEECH_VOICE.
     * @method bool isPresencePenalty() Checks if the option is PRESENCE_PENALTY.
     * @method bool isStopSequences() Checks if the option is STOP_SEQUENCES.
     * @method bool isSystemInstruction() Checks if the option is SYSTEM_INSTRUCTION.
     * @method bool isTemperature() Checks if the option is TEMPERATURE.
     * @method bool isTopK() Checks if the option is TOP_K.
     * @method bool isTopLogprobs() Checks if the option is TOP_LOGPROBS.
     * @method bool isTopP() Checks if the option is TOP_P.
     * @method bool isWebSearch() Checks if the option is WEB_SEARCH.
     *
     * @since 0.1.0
     */
    class OptionEnum extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * Input modalities option.
         *
         * This constant is not in ModelConfig as it's derived from message content,
         * not configured directly.
         */
        public const INPUT_MODALITIES = 'input_modalities';
        /**
         * Determines the class enumerations by reflecting on class constants.
         *
         * Overrides the parent method to dynamically add constants from ModelConfig
         * that are prefixed with KEY_. These are transformed to remove the KEY_ prefix
         * and converted to snake_case values.
         *
         * @since 0.1.0
         *
         * @param class-string $className The fully qualified class name.
         * @return array<string, string> The enum constants.
         */
        protected static function determineClassEnumerations(string $className): array
        {
        }
    }
}
namespace WordPress\AiClient\Providers\Models\ImageGeneration\Contracts {
    /**
     * Interface for models that support image generation.
     *
     * Provides synchronous methods for generating images from text prompts.
     *
     * @since 0.1.0
     */
    interface ImageGenerationModelInterface
    {
        /**
         * Generates images from a prompt.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Array of messages containing the image generation prompt.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult Result containing generated images.
         */
        public function generateImageResult(array $prompt): \WordPress\AiClient\Results\DTO\GenerativeAiResult;
    }
    /**
     * Interface for models that support asynchronous image generation operations.
     *
     * Provides methods for initiating long-running image generation tasks.
     *
     * @since 0.1.0
     */
    interface ImageGenerationOperationModelInterface
    {
        /**
         * Creates an image generation operation.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Array of messages containing the image generation prompt.
         * @return \WordPress\AiClient\Operations\DTO\GenerativeAiOperation The initiated image generation operation.
         */
        public function generateImageOperation(array $prompt): \WordPress\AiClient\Operations\DTO\GenerativeAiOperation;
    }
}
namespace WordPress\AiClient\Providers\Models\SpeechGeneration\Contracts {
    /**
     * Interface for models that support speech generation.
     *
     * Provides synchronous methods for generating speech from prompts.
     *
     * @since 0.1.0
     */
    interface SpeechGenerationModelInterface
    {
        /**
         * Generates speech from a prompt.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Array of messages containing the speech generation prompt.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult Result containing generated speech audio.
         */
        public function generateSpeechResult(array $prompt): \WordPress\AiClient\Results\DTO\GenerativeAiResult;
    }
    /**
     * Interface for models that support asynchronous speech generation operations.
     *
     * Provides methods for initiating long-running speech generation tasks.
     *
     * @since 0.1.0
     */
    interface SpeechGenerationOperationModelInterface
    {
        /**
         * Creates a speech generation operation.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Array of messages containing the speech generation prompt.
         * @return \WordPress\AiClient\Operations\DTO\GenerativeAiOperation The initiated speech generation operation.
         */
        public function generateSpeechOperation(array $prompt): \WordPress\AiClient\Operations\DTO\GenerativeAiOperation;
    }
}
namespace WordPress\AiClient\Providers\Models\TextGeneration\Contracts {
    /**
     * Interface for models that support text generation.
     *
     * Provides synchronous and streaming methods for generating text from prompts.
     *
     * @since 0.1.0
     */
    interface TextGenerationModelInterface
    {
        /**
         * Generates text from a prompt.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Array of messages containing the text generation prompt.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult Result containing generated text.
         */
        public function generateTextResult(array $prompt): \WordPress\AiClient\Results\DTO\GenerativeAiResult;
    }
    /**
     * Interface for models that support asynchronous text generation operations.
     *
     * Provides methods for initiating long-running text generation tasks.
     *
     * @since 0.1.0
     */
    interface TextGenerationOperationModelInterface
    {
        /**
         * Creates a text generation operation.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Array of messages containing the text generation prompt.
         * @return \WordPress\AiClient\Operations\DTO\GenerativeAiOperation The initiated text generation operation.
         */
        public function generateTextOperation(array $prompt): \WordPress\AiClient\Operations\DTO\GenerativeAiOperation;
    }
}
namespace WordPress\AiClient\Providers\Models\TextToSpeechConversion\Contracts {
    /**
     * Interface for models that support text-to-speech conversion.
     *
     * Provides synchronous methods for converting text to speech audio.
     *
     * @since 0.1.0
     */
    interface TextToSpeechConversionModelInterface
    {
        /**
         * Converts text to speech.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Array of messages containing the text to convert to speech.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult Result containing generated speech audio.
         */
        public function convertTextToSpeechResult(array $prompt): \WordPress\AiClient\Results\DTO\GenerativeAiResult;
    }
    /**
     * Interface for models that support asynchronous text-to-speech conversion operations.
     *
     * Provides methods for initiating long-running text-to-speech conversion tasks.
     *
     * @since 0.1.0
     */
    interface TextToSpeechConversionOperationModelInterface
    {
        /**
         * Creates a text-to-speech conversion operation.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Array of messages containing the text to convert to speech.
         * @return \WordPress\AiClient\Operations\DTO\GenerativeAiOperation The initiated text-to-speech conversion operation.
         */
        public function convertTextToSpeechOperation(array $prompt): \WordPress\AiClient\Operations\DTO\GenerativeAiOperation;
    }
}
namespace WordPress\AiClient\Providers\Models\VideoGeneration\Contracts {
    /**
     * Interface for models that support video generation.
     *
     * Provides synchronous methods for generating videos from prompts.
     *
     * @since 1.3.0
     */
    interface VideoGenerationModelInterface
    {
        /**
         * Generates videos from a prompt.
         *
         * @since 1.3.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Array of messages containing the video generation prompt.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult Result containing generated videos.
         */
        public function generateVideoResult(array $prompt): \WordPress\AiClient\Results\DTO\GenerativeAiResult;
    }
    /**
     * Interface for models that support asynchronous video generation operations.
     *
     * Provides methods for initiating long-running video generation tasks.
     *
     * @since 1.3.0
     */
    interface VideoGenerationOperationModelInterface
    {
        /**
         * Creates a video generation operation.
         *
         * @since 1.3.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Array of messages containing the video generation prompt.
         * @return \WordPress\AiClient\Operations\DTO\GenerativeAiOperation The initiated video generation operation.
         */
        public function generateVideoOperation(array $prompt): \WordPress\AiClient\Operations\DTO\GenerativeAiOperation;
    }
}
namespace WordPress\AiClient\Providers\OpenAiCompatibleImplementation {
    /**
     * Base class for an image generation model for providers that implement OpenAI's API format.
     *
     * This abstract class is designed to work with any AI provider that offers an OpenAI-compatible
     * API endpoint for image generation, including but not limited to Anthropic, Google, and other
     * providers that have adopted OpenAI's image generation API specification as a standard interface.
     *
     * @since 0.1.0
     *
     * @phpstan-type ImageGenerationParams array{
     *     model: string,
     *     prompt: string,
     *     n?: int,
     *     response_format?: string,
     *     output_format?: string|null,
     *     size?: string,
     *     ...
     * }
     * @phpstan-type ChoiceData array{
     *     url?: string,
     *     b64_json?: string
     * }
     * @phpstan-type UsageData array{
     *     input_tokens?: int,
     *     output_tokens?: int,
     *     total_tokens?: int
     * }
     * @phpstan-type ResponseData array{
     *     id?: string,
     *     data?: list<ChoiceData>,
     *     usage?: UsageData
     * }
     */
    abstract class AbstractOpenAiCompatibleImageGenerationModel extends \WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel implements \WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface
    {
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function generateImageResult(array $prompt): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Prepares the given prompt and the model configuration into parameters for the API request.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt to generate an image for. Either a single message or a list of messages
         *                              from a chat. However as of today, OpenAI compatible image generation endpoints only
         *                              support a single user message.
         * @return ImageGenerationParams The parameters for the API request.
         */
        protected function prepareGenerateImageParams(array $prompt): array
        {
        }
        /**
         * Prepares the prompt parameter for the API request.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $messages The messages to prepare. However as of today, OpenAI compatible image generation
         *                                endpoints only support a single user message.
         * @return string The prepared prompt parameter.
         */
        protected function preparePromptParam(array $messages): string
        {
        }
        /**
         * Prepares the size parameter for the API request.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Files\Enums\MediaOrientationEnum|null $orientation The desired media orientation.
         * @param string|null $aspectRatio The desired media aspect ratio.
         * @return string The prepared size parameter.
         */
        protected function prepareSizeParam(?\WordPress\AiClient\Files\Enums\MediaOrientationEnum $orientation, ?string $aspectRatio): string
        {
        }
        /**
         * Creates a request object for the provider's API.
         *
         * Implementations should use $this->getRequestOptions() to attach any
         * configured request options to the Request.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum $method The HTTP method.
         * @param string $path The API endpoint path, relative to the base URI.
         * @param array<string, string|list<string>> $headers The request headers.
         * @param string|array<string, mixed>|null $data The request data.
         * @return \WordPress\AiClient\Providers\Http\DTO\Request The request object.
         */
        abstract protected function createRequest(\WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum $method, string $path, array $headers = [], $data = null): \WordPress\AiClient\Providers\Http\DTO\Request;
        /**
         * Throws an exception if the response is not successful.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The HTTP response to check.
         * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the response is not successful.
         */
        protected function throwIfNotSuccessful(\WordPress\AiClient\Providers\Http\DTO\Response $response): void
        {
        }
        /**
         * Parses the response from the API endpoint to a generative AI result.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The response from the API endpoint.
         * @param string   $expectedMimeType The expected MIME type the response is in.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The parsed generative AI result.
         */
        protected function parseResponseToGenerativeAiResult(\WordPress\AiClient\Providers\Http\DTO\Response $response, string $expectedMimeType = 'image/png'): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Parses a single choice from the API response into a Candidate object.
         *
         * @since 0.1.0
         *
         * @param ChoiceData $choiceData The choice data from the API response.
         * @param int $index The index of the choice in the choices array.
         * @param string   $expectedMimeType The expected MIME type the response is in.
         * @return \WordPress\AiClient\Results\DTO\Candidate The parsed candidate.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If the choice data is invalid.
         */
        protected function parseResponseChoiceToCandidate(array $choiceData, int $index, string $expectedMimeType = 'image/png'): \WordPress\AiClient\Results\DTO\Candidate
        {
        }
        /**
         * Extracts the result ID from the API response data.
         *
         * @since 0.4.0
         *
         * @param array<string, mixed> $responseData The response data from the API.
         * @return string The result ID.
         */
        protected function getResultId(array $responseData): string
        {
        }
    }
    /**
     * Base class for a model metadata directory for providers that implement OpenAI's API format.
     *
     * This abstract class is designed to work with any AI provider that offers an OpenAI-compatible
     * models listing endpoint, including but not limited to Anthropic, Google, and other
     * providers that have adopted OpenAI's models API specification as a standard interface.
     *
     * @since 0.1.0
     */
    abstract class AbstractOpenAiCompatibleModelMetadataDirectory extends \WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory
    {
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        protected function sendListModelsRequest(): array
        {
        }
        /**
         * Creates a request object for the provider's API.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum $method The HTTP method.
         * @param string $path The API endpoint path, relative to the base URI.
         * @param array<string, string|list<string>> $headers The request headers.
         * @param string|array<string, mixed>|null $data The request data.
         * @return \WordPress\AiClient\Providers\Http\DTO\Request The request object.
         */
        abstract protected function createRequest(\WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum $method, string $path, array $headers = [], $data = null): \WordPress\AiClient\Providers\Http\DTO\Request;
        /**
         * Throws an exception if the response is not successful.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The HTTP response to check.
         * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the response is not successful.
         */
        protected function throwIfNotSuccessful(\WordPress\AiClient\Providers\Http\DTO\Response $response): void
        {
        }
        /**
         * Parses the response from the API endpoint to list models into a list of model metadata objects.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The response from the API endpoint to list models.
         * @return list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata> List of model metadata objects.
         */
        abstract protected function parseResponseToModelMetadataList(\WordPress\AiClient\Providers\Http\DTO\Response $response): array;
    }
    /**
     * Base class for a text generation model for providers that implement OpenAI's API format.
     *
     * This abstract class is designed to work with any AI provider that offers an OpenAI-compatible
     * API endpoint, including but not limited to Anthropic, Google, and other providers
     * that have adopted OpenAI's API specification as a standard interface.
     *
     * @since 0.1.0
     *
     * @phpstan-type ToolCallData array{
     *     type?: string,
     *     id?: string,
     *     function?: array{
     *         name?: string,
     *         arguments: string|array<string, mixed>
     *     }
     * }
     * @phpstan-type MessageData array{
     *     role?: string,
     *     reasoning_content?: string,
     *     content?: string,
     *     tool_calls?: list<ToolCallData>
     * }
     * @phpstan-type ChoiceData array{
     *     message?: MessageData,
     *     finish_reason?: string
     * }
     * @phpstan-type UsageData array{
     *     prompt_tokens?: int,
     *     completion_tokens?: int,
     *     total_tokens?: int
     * }
     * @phpstan-type ResponseData array{
     *     id?: string,
     *     choices?: list<ChoiceData>,
     *     usage?: UsageData
     * }
     */
    abstract class AbstractOpenAiCompatibleTextGenerationModel extends \WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel implements \WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface
    {
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        final public function generateTextResult(array $prompt): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Prepares the given prompt and the model configuration into parameters for the API request.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt to generate text for. Either a single message or a list of messages
         *                              from a chat.
         * @return array<string, mixed> The parameters for the API request.
         */
        protected function prepareGenerateTextParams(array $prompt): array
        {
        }
        /**
         * Prepares the messages parameter for the API request.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Messages\DTO\Message> $messages The messages to prepare.
         * @param string|null $systemInstruction An optional system instruction to prepend to the messages.
         * @return list<array<string, mixed>> The prepared messages parameter.
         */
        protected function prepareMessagesParam(array $messages, ?string $systemInstruction = null): array
        {
        }
        /**
         * Returns the OpenAI API specific role string for the given message role.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Messages\Enums\MessageRoleEnum $role The message role.
         * @return string The role for the API request.
         */
        protected function getMessageRoleString(\WordPress\AiClient\Messages\Enums\MessageRoleEnum $role): string
        {
        }
        /**
         * Returns the OpenAI API specific content data for a message part.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Messages\DTO\MessagePart $part The message part to get the data for.
         * @return ?array<string, mixed> The data for the message content part, or null if not applicable.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the message part type or data is unsupported.
         */
        protected function getMessagePartContentData(\WordPress\AiClient\Messages\DTO\MessagePart $part): ?array
        {
        }
        /**
         * Returns the OpenAI API specific tool calls data for a message part.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Messages\DTO\MessagePart $part The message part to get the data for.
         * @return ?array<string, mixed> The data for the message tool call part, or null if not applicable.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the message part type or data is unsupported.
         */
        protected function getMessagePartToolCallData(\WordPress\AiClient\Messages\DTO\MessagePart $part): ?array
        {
        }
        /**
         * Validates that the given output modalities to ensure that at least one output modality is text.
         *
         * @since 0.1.0
         *
         * @param array<\WordPress\AiClient\Messages\Enums\ModalityEnum> $outputModalities The output modalities to validate.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If no text output modality is present.
         */
        protected function validateOutputModalities(array $outputModalities): void
        {
        }
        /**
         * Prepares the output modalities parameter for the API request.
         *
         * @since 0.1.0
         *
         * @param array<\WordPress\AiClient\Messages\Enums\ModalityEnum> $modalities The modalities to prepare.
         * @return list<string> The prepared modalities parameter.
         */
        protected function prepareOutputModalitiesParam(array $modalities): array
        {
        }
        /**
         * Prepares the tools parameter for the API request.
         *
         * @since 0.1.0
         *
         * @param list<\WordPress\AiClient\Tools\DTO\FunctionDeclaration> $functionDeclarations The function declarations.
         * @return list<array<string, mixed>> The prepared tools parameter.
         */
        protected function prepareToolsParam(array $functionDeclarations): array
        {
        }
        /**
         * Prepares the response format parameter for the API request.
         *
         * This is only called if the output MIME type is `application/json`.
         *
         * @since 0.1.0
         *
         * @param array<string, mixed>|null $outputSchema The output schema.
         * @return array<string, mixed> The prepared response format parameter.
         */
        protected function prepareResponseFormatParam(?array $outputSchema): array
        {
        }
        /**
         * Creates a request object for the provider's API.
         *
         * Implementations should use $this->getRequestOptions() to attach any
         * configured request options to the Request.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum $method The HTTP method.
         * @param string $path The API endpoint path, relative to the base URI.
         * @param array<string, string|list<string>> $headers The request headers.
         * @param string|array<string, mixed>|null $data The request data.
         * @return \WordPress\AiClient\Providers\Http\DTO\Request The request object.
         */
        abstract protected function createRequest(\WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum $method, string $path, array $headers = [], $data = null): \WordPress\AiClient\Providers\Http\DTO\Request;
        /**
         * Throws an exception if the response is not successful.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The HTTP response to check.
         * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the response is not successful.
         */
        protected function throwIfNotSuccessful(\WordPress\AiClient\Providers\Http\DTO\Response $response): void
        {
        }
        /**
         * Parses the response from the API endpoint to a generative AI result.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The response from the API endpoint.
         * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The parsed generative AI result.
         */
        protected function parseResponseToGenerativeAiResult(\WordPress\AiClient\Providers\Http\DTO\Response $response): \WordPress\AiClient\Results\DTO\GenerativeAiResult
        {
        }
        /**
         * Parses a single choice from the API response into a Candidate object.
         *
         * @since 0.1.0
         *
         * @param ChoiceData $choiceData The choice data from the API response.
         * @param int $index The index of the choice in the choices array.
         * @return \WordPress\AiClient\Results\DTO\Candidate The parsed candidate.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If the choice data is invalid.
         */
        protected function parseResponseChoiceToCandidate(array $choiceData, int $index): \WordPress\AiClient\Results\DTO\Candidate
        {
        }
        /**
         * Parses the message from a choice in the API response.
         *
         * @since 0.1.0
         *
         * @param MessageData $messageData The message data from the API response.
         * @param int $index The index of the choice in the choices array.
         * @return \WordPress\AiClient\Messages\DTO\Message The parsed message.
         */
        protected function parseResponseChoiceMessage(array $messageData, int $index): \WordPress\AiClient\Messages\DTO\Message
        {
        }
        /**
         * Parses the message parts from a choice in the API response.
         *
         * @since 0.1.0
         *
         * @param MessageData $messageData The message data from the API response.
         * @param int $index The index of the choice in the choices array.
         * @return \WordPress\AiClient\Messages\DTO\MessagePart[] The parsed message parts.
         */
        protected function parseResponseChoiceMessageParts(array $messageData, int $index): array
        {
        }
        /**
         * Parses a tool call part from the API response.
         *
         * @since 0.1.0
         *
         * @param ToolCallData $toolCallData The tool call data from the API response.
         * @return \WordPress\AiClient\Messages\DTO\MessagePart|null The parsed message part for the tool call, or null if not applicable.
         */
        protected function parseResponseChoiceMessageToolCallPart(array $toolCallData): ?\WordPress\AiClient\Messages\DTO\MessagePart
        {
        }
    }
}
namespace WordPress\AiClient\Providers {
    /**
     * Registry for managing AI providers and their models.
     *
     * This class provides a centralized way to register AI providers, discover
     * their capabilities, and find suitable models based on requirements.
     *
     * @since 0.1.0
     */
    class ProviderRegistry implements \WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface
    {
        use \WordPress\AiClient\Providers\Http\Traits\WithHttpTransporterTrait {
            setHttpTransporter as setHttpTransporterOriginal;
        }
        /**
         * Registers a provider class with the registry.
         *
         * @since 0.1.0
         *
         * @param class-string<\WordPress\AiClient\Providers\Contracts\ProviderInterface> $className The fully qualified provider class name implementing the
         * ProviderInterface
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the class doesn't exist or implement the required interface.
         */
        public function registerProvider(string $className): void
        {
        }
        /**
         * Gets a list of all registered provider IDs.
         *
         * @since 0.1.0
         *
         * @return list<string> List of registered provider IDs.
         */
        public function getRegisteredProviderIds(): array
        {
        }
        /**
         * Checks if a provider is registered.
         *
         * @since 0.1.0
         *
         * @param string|class-string<\WordPress\AiClient\Providers\Contracts\ProviderInterface> $idOrClassName The provider ID or class name to check.
         * @return bool True if the provider is registered.
         */
        public function hasProvider(string $idOrClassName): bool
        {
        }
        /**
         * Gets the class name for a registered provider.
         *
         * @since 0.1.0
         *
         * @param string|class-string<\WordPress\AiClient\Providers\Contracts\ProviderInterface> $idOrClassName The provider ID or class name.
         * @return class-string<\WordPress\AiClient\Providers\Contracts\ProviderInterface> The provider class name.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the provider is not registered.
         */
        public function getProviderClassName(string $idOrClassName): string
        {
        }
        /**
         * Gets the provider ID for a registered provider.
         *
         * @since 0.2.0
         *
         * @param string|class-string<\WordPress\AiClient\Providers\Contracts\ProviderInterface> $idOrClassName The provider ID or class name.
         * @return string The provider ID.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the provider is not registered.
         */
        public function getProviderId(string $idOrClassName): string
        {
        }
        /**
         * Checks if a provider is properly configured.
         *
         * @since 0.1.0
         *
         * @param string|class-string<\WordPress\AiClient\Providers\Contracts\ProviderInterface> $idOrClassName The provider ID or class name.
         * @return bool True if the provider is configured and ready to use.
         */
        public function isProviderConfigured(string $idOrClassName): bool
        {
        }
        /**
         * Finds models across all available providers that support the given requirements.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Models\DTO\ModelRequirements $modelRequirements The requirements to match against.
         * @return list<\WordPress\AiClient\Providers\DTO\ProviderModelsMetadata> List of provider models metadata that match requirements.
         */
        public function findModelsMetadataForSupport(\WordPress\AiClient\Providers\Models\DTO\ModelRequirements $modelRequirements): array
        {
        }
        /**
         * Finds models within a specific available provider that support the given requirements.
         *
         * @since 0.1.0
         *
         * @param string $idOrClassName The provider ID or class name.
         * @param \WordPress\AiClient\Providers\Models\DTO\ModelRequirements $modelRequirements The requirements to match against.
         * @return list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata> List of model metadata that match requirements.
         */
        public function findProviderModelsMetadataForSupport(string $idOrClassName, \WordPress\AiClient\Providers\Models\DTO\ModelRequirements $modelRequirements): array
        {
        }
        /**
         * Gets a configured model instance from a provider.
         *
         * @since 0.1.0
         *
         * @param string|class-string<\WordPress\AiClient\Providers\Contracts\ProviderInterface> $idOrClassName The provider ID or class name.
         * @param string $modelId The model identifier.
         * @param \WordPress\AiClient\Providers\Models\DTO\ModelConfig|null $modelConfig The model configuration.
         * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface The configured model instance.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If provider or model is not found.
         */
        public function getProviderModel(string $idOrClassName, string $modelId, ?\WordPress\AiClient\Providers\Models\DTO\ModelConfig $modelConfig = null): \WordPress\AiClient\Providers\Models\Contracts\ModelInterface
        {
        }
        /**
         * Binds dependencies to a model instance.
         *
         * This method injects required dependencies such as HTTP transporter
         * and authentication into model instances that need them.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Providers\Models\Contracts\ModelInterface $modelInstance The model instance to bind dependencies to.
         * @return void
         */
        public function bindModelDependencies(\WordPress\AiClient\Providers\Models\Contracts\ModelInterface $modelInstance): void
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function setHttpTransporter(\WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface $httpTransporter): void
        {
        }
        /**
         * Sets the request authentication instance for the given provider.
         *
         * @since 0.1.0
         *
         * @param string|class-string<\WordPress\AiClient\Providers\Contracts\ProviderInterface> $idOrClassName The provider ID or class name.
         * @param \WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface $requestAuthentication The request authentication instance.
         */
        public function setProviderRequestAuthentication(string $idOrClassName, \WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface $requestAuthentication): void
        {
        }
        /**
         * Gets the request authentication instance for the given provider, if set.
         *
         * @since 0.1.0
         *
         * @param string|class-string<\WordPress\AiClient\Providers\Contracts\ProviderInterface> $idOrClassName The provider ID or class name.
         * @return ?\WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface The request authentication instance, or null if not set.
         */
        public function getProviderRequestAuthentication(string $idOrClassName): ?\WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface
        {
        }
    }
}
namespace WordPress\AiClient\Results\Contracts {
    /**
     * Interface for AI operation results.
     *
     * Results contain the output from AI operations along with metadata
     * such as token usage and provider-specific information.
     *
     * @since 0.1.0
     */
    interface ResultInterface
    {
        /**
         * Gets the result ID.
         *
         * @since 0.1.0
         *
         * @return string The unique result identifier.
         */
        public function getId(): string;
        /**
         * Gets token usage information.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Results\DTO\TokenUsage Token usage statistics.
         */
        public function getTokenUsage(): \WordPress\AiClient\Results\DTO\TokenUsage;
        /**
         * Gets the provider metadata.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\DTO\ProviderMetadata The provider metadata.
         */
        public function getProviderMetadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata;
        /**
         * Gets the model metadata.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata The model metadata.
         */
        public function getModelMetadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
        /**
         * Gets provider-specific metadata.
         *
         * @since 0.1.0
         *
         * @return array<string, mixed> Provider metadata.
         */
        public function getAdditionalData(): array;
    }
}
namespace WordPress\AiClient\Results\DTO {
    /**
     * Represents a candidate response from an AI model.
     *
     * When generating content, AI models can produce multiple candidates.
     * Each candidate contains a message and metadata about why generation stopped.
     *
     * @since 0.1.0
     *
     * @phpstan-import-type MessageArrayShape from \WordPress\AiClient\Messages\DTO\Message
     *
     * @phpstan-type CandidateArrayShape array{message: MessageArrayShape, finishReason: string}
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<CandidateArrayShape>
     */
    class Candidate extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_MESSAGE = 'message';
        public const KEY_FINISH_REASON = 'finishReason';
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param \WordPress\AiClient\Messages\DTO\Message $message The generated message.
         * @param \WordPress\AiClient\Results\Enums\FinishReasonEnum $finishReason The reason generation stopped.
         */
        public function __construct(\WordPress\AiClient\Messages\DTO\Message $message, \WordPress\AiClient\Results\Enums\FinishReasonEnum $finishReason)
        {
        }
        /**
         * Gets the generated message.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Messages\DTO\Message The message.
         */
        public function getMessage(): \WordPress\AiClient\Messages\DTO\Message
        {
        }
        /**
         * Gets the finish reason.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Results\Enums\FinishReasonEnum The finish reason.
         */
        public function getFinishReason(): \WordPress\AiClient\Results\Enums\FinishReasonEnum
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return CandidateArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
        /**
         * Performs a deep clone of the candidate.
         *
         * This method ensures that the message object is cloned to prevent
         * modifications to the cloned candidate from affecting the original.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
    }
    /**
     * Represents the result of a generative AI operation.
     *
     * This DTO contains the generated candidates along with usage statistics
     * and metadata from the AI provider.
     *
     * @since 0.1.0
     *
     * @phpstan-import-type CandidateArrayShape from Candidate
     * @phpstan-import-type TokenUsageArrayShape from TokenUsage
     * @phpstan-import-type ProviderMetadataArrayShape from \WordPress\AiClient\Providers\DTO\ProviderMetadata
     * @phpstan-import-type ModelMetadataArrayShape from \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
     *
     * @phpstan-type GenerativeAiResultArrayShape array{
     *     id: string,
     *     candidates: array<CandidateArrayShape>,
     *     tokenUsage: TokenUsageArrayShape,
     *     providerMetadata: ProviderMetadataArrayShape,
     *     modelMetadata: ModelMetadataArrayShape,
     *     additionalData?: array<string, mixed>
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<GenerativeAiResultArrayShape>
     */
    class GenerativeAiResult extends \WordPress\AiClient\Common\AbstractDataTransferObject implements \WordPress\AiClient\Results\Contracts\ResultInterface
    {
        public const KEY_ID = 'id';
        public const KEY_CANDIDATES = 'candidates';
        public const KEY_TOKEN_USAGE = 'tokenUsage';
        public const KEY_PROVIDER_METADATA = 'providerMetadata';
        public const KEY_MODEL_METADATA = 'modelMetadata';
        public const KEY_ADDITIONAL_DATA = 'additionalData';
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param string $id Unique identifier for this result.
         * @param Candidate[] $candidates The generated candidates.
         * @param TokenUsage $tokenUsage Token usage statistics.
         * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata $providerMetadata Provider metadata.
         * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $modelMetadata Model metadata.
         * @param array<string, mixed> $additionalData Additional data.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If no candidates provided.
         */
        public function __construct(string $id, array $candidates, \WordPress\AiClient\Results\DTO\TokenUsage $tokenUsage, \WordPress\AiClient\Providers\DTO\ProviderMetadata $providerMetadata, \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $modelMetadata, array $additionalData = [])
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function getId(): string
        {
        }
        /**
         * Gets the generated candidates.
         *
         * @since 0.1.0
         *
         * @return Candidate[] The candidates.
         */
        public function getCandidates(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function getTokenUsage(): \WordPress\AiClient\Results\DTO\TokenUsage
        {
        }
        /**
         * Gets the provider metadata.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\DTO\ProviderMetadata The provider metadata.
         */
        public function getProviderMetadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata
        {
        }
        /**
         * Gets the model metadata.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata The model metadata.
         */
        public function getModelMetadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public function getAdditionalData(): array
        {
        }
        /**
         * Gets the total number of candidates.
         *
         * @since 0.1.0
         *
         * @return int The total number of candidates.
         */
        public function getCandidateCount(): int
        {
        }
        /**
         * Checks if the result has multiple candidates.
         *
         * @since 0.1.0
         *
         * @return bool True if there are multiple candidates, false otherwise.
         */
        public function hasMultipleCandidates(): bool
        {
        }
        /**
         * Converts the first candidate to text.
         *
         * Only text from the content channel is considered. Text within model thought or reasoning is ignored.
         *
         * @since 0.1.0
         *
         * @return string The text content.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If no text content.
         */
        public function toText(): string
        {
        }
        /**
         * Converts the first candidate to a file.
         *
         * Only files from the content channel are considered. Files within model thought or reasoning are ignored.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Files\DTO\File The file.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If no file content.
         */
        public function toFile(): \WordPress\AiClient\Files\DTO\File
        {
        }
        /**
         * Converts the first candidate to an image file.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Files\DTO\File The image file.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If no image content.
         */
        public function toImageFile(): \WordPress\AiClient\Files\DTO\File
        {
        }
        /**
         * Converts the first candidate to an audio file.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Files\DTO\File The audio file.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If no audio content.
         */
        public function toAudioFile(): \WordPress\AiClient\Files\DTO\File
        {
        }
        /**
         * Converts the first candidate to a video file.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Files\DTO\File The video file.
         * @throws \WordPress\AiClient\Common\Exception\RuntimeException If no video content.
         */
        public function toVideoFile(): \WordPress\AiClient\Files\DTO\File
        {
        }
        /**
         * Converts the first candidate to a message.
         *
         * @since 0.1.0
         *
         * @return \WordPress\AiClient\Messages\DTO\Message The message.
         */
        public function toMessage(): \WordPress\AiClient\Messages\DTO\Message
        {
        }
        /**
         * Converts all candidates to text.
         *
         * @since 0.1.0
         *
         * @return list<string> Array of text content.
         */
        public function toTexts(): array
        {
        }
        /**
         * Converts all candidates to files.
         *
         * @since 0.1.0
         *
         * @return list<\WordPress\AiClient\Files\DTO\File> Array of files.
         */
        public function toFiles(): array
        {
        }
        /**
         * Converts all candidates to image files.
         *
         * @since 0.1.0
         *
         * @return list<\WordPress\AiClient\Files\DTO\File> Array of image files.
         */
        public function toImageFiles(): array
        {
        }
        /**
         * Converts all candidates to audio files.
         *
         * @since 0.1.0
         *
         * @return list<\WordPress\AiClient\Files\DTO\File> Array of audio files.
         */
        public function toAudioFiles(): array
        {
        }
        /**
         * Converts all candidates to video files.
         *
         * @since 0.1.0
         *
         * @return list<\WordPress\AiClient\Files\DTO\File> Array of video files.
         */
        public function toVideoFiles(): array
        {
        }
        /**
         * Converts all candidates to messages.
         *
         * @since 0.1.0
         *
         * @return list<\WordPress\AiClient\Messages\DTO\Message> Array of messages.
         */
        public function toMessages(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return GenerativeAiResultArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
        /**
         * Performs a deep clone of the result.
         *
         * This method ensures that all nested objects (candidates, token usage, metadata)
         * are cloned to prevent modifications to the cloned result from affecting the original.
         *
         * @since 0.4.2
         */
        public function __clone()
        {
        }
    }
    /**
     * Represents token usage statistics for an AI operation.
     *
     * This DTO tracks the number of tokens used in prompts and completions,
     * which is important for monitoring usage and costs.
     *
     * Note that thought tokens are a subset of completion tokens, not additive.
     * In other words: completionTokens - thoughtTokens = tokens of actual output content.
     *
     * @since 0.1.0
     *
     * @phpstan-type TokenUsageArrayShape array{
     *     promptTokens: int,
     *     completionTokens: int,
     *     totalTokens: int,
     *     thoughtTokens?: int
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<TokenUsageArrayShape>
     */
    class TokenUsage extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_PROMPT_TOKENS = 'promptTokens';
        public const KEY_COMPLETION_TOKENS = 'completionTokens';
        public const KEY_TOTAL_TOKENS = 'totalTokens';
        public const KEY_THOUGHT_TOKENS = 'thoughtTokens';
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param int $promptTokens Number of tokens in the prompt.
         * @param int $completionTokens Number of tokens in the completion, including any thought tokens.
         * @param int $totalTokens Total number of tokens used.
         * @param int|null $thoughtTokens Number of tokens used for thinking, as a subset of completion tokens.
         */
        public function __construct(int $promptTokens, int $completionTokens, int $totalTokens, ?int $thoughtTokens = null)
        {
        }
        /**
         * Gets the number of prompt tokens.
         *
         * @since 0.1.0
         *
         * @return int The prompt token count.
         */
        public function getPromptTokens(): int
        {
        }
        /**
         * Gets the number of completion tokens, including any thought tokens.
         *
         * @since 0.1.0
         *
         * @return int The completion token count.
         */
        public function getCompletionTokens(): int
        {
        }
        /**
         * Gets the total number of tokens.
         *
         * @since 0.1.0
         *
         * @return int The total token count.
         */
        public function getTotalTokens(): int
        {
        }
        /**
         * Gets the number of thought tokens, which is a subset of the completion token count.
         *
         * @since 1.3.0
         *
         * @return int|null The thought token count or null if not available.
         */
        public function getThoughtTokens(): ?int
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return TokenUsageArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
    }
}
namespace WordPress\AiClient\Results\Enums {
    /**
     * Enum for finish reasons of AI generation.
     *
     * @since 0.1.0
     *
     * @method static self stop() Creates an instance for STOP reason.
     * @method static self length() Creates an instance for LENGTH reason.
     * @method static self contentFilter() Creates an instance for CONTENT_FILTER reason.
     * @method static self toolCalls() Creates an instance for TOOL_CALLS reason.
     * @method static self error() Creates an instance for ERROR reason.
     * @method bool isStop() Checks if the reason is STOP.
     * @method bool isLength() Checks if the reason is LENGTH.
     * @method bool isContentFilter() Checks if the reason is CONTENT_FILTER.
     * @method bool isToolCalls() Checks if the reason is TOOL_CALLS.
     * @method bool isError() Checks if the reason is ERROR.
     */
    class FinishReasonEnum extends \WordPress\AiClient\Common\AbstractEnum
    {
        /**
         * Generation stopped naturally.
         */
        public const STOP = 'stop';
        /**
         * Generation stopped due to max length.
         */
        public const LENGTH = 'length';
        /**
         * Generation stopped due to content filter.
         */
        public const CONTENT_FILTER = 'content_filter';
        /**
         * Generation stopped to make tool calls.
         */
        public const TOOL_CALLS = 'tool_calls';
        /**
         * Generation stopped due to error.
         */
        public const ERROR = 'error';
    }
}
namespace WordPress\AiClient\Tools\DTO {
    /**
     * Represents a function call request from an AI model.
     *
     * This DTO encapsulates information about a function that the AI model
     * wants to invoke, including the function name and its arguments.
     *
     * @since 0.1.0
     *
     * @phpstan-type FunctionCallArrayShape array{id?: string, name?: string, args?: mixed}
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<FunctionCallArrayShape>
     */
    class FunctionCall extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_ID = 'id';
        public const KEY_NAME = 'name';
        public const KEY_ARGS = 'args';
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param string|null $id Unique identifier for this function call.
         * @param string|null $name The name of the function to call.
         * @param mixed $args The arguments to pass to the function.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If neither id nor name is provided.
         */
        public function __construct(?string $id = null, ?string $name = null, $args = null)
        {
        }
        /**
         * Gets the function call ID.
         *
         * @since 0.1.0
         *
         * @return string|null The function call ID.
         */
        public function getId(): ?string
        {
        }
        /**
         * Gets the function name.
         *
         * @since 0.1.0
         *
         * @return string|null The function name.
         */
        public function getName(): ?string
        {
        }
        /**
         * Gets the function arguments.
         *
         * @since 0.1.0
         *
         * @return mixed The function arguments.
         */
        public function getArgs()
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return FunctionCallArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
    }
    /**
     * Represents a function declaration for AI models.
     *
     * This DTO describes a function that can be called by the AI model,
     * including its name, description, and parameter schema.
     *
     * @since 0.1.0
     *
     * @phpstan-type FunctionDeclarationArrayShape array{
     *     name: string,
     *     description: string,
     *     parameters?: array<string, mixed>
     * }
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<FunctionDeclarationArrayShape>
     */
    class FunctionDeclaration extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_NAME = 'name';
        public const KEY_DESCRIPTION = 'description';
        public const KEY_PARAMETERS = 'parameters';
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param string $name The name of the function.
         * @param string $description A description of what the function does.
         * @param array<string, mixed>|null $parameters The JSON schema for the function parameters.
         */
        public function __construct(string $name, string $description, ?array $parameters = null)
        {
        }
        /**
         * Gets the function name.
         *
         * @since 0.1.0
         *
         * @return string The function name.
         */
        public function getName(): string
        {
        }
        /**
         * Gets the function description.
         *
         * @since 0.1.0
         *
         * @return string The function description.
         */
        public function getDescription(): string
        {
        }
        /**
         * Gets the function parameters schema.
         *
         * @since 0.1.0
         *
         * @return array<string, mixed>|null The parameters schema.
         */
        public function getParameters(): ?array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return FunctionDeclarationArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
    }
    /**
     * Represents a response to a function call.
     *
     * This DTO encapsulates the result of executing a function that was
     * requested by the AI model through a FunctionCall.
     *
     * @since 0.1.0
     *
     * @phpstan-type FunctionResponseArrayShape array{id?: string, name?: string, response: mixed}
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<FunctionResponseArrayShape>
     */
    class FunctionResponse extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_ID = 'id';
        public const KEY_NAME = 'name';
        public const KEY_RESPONSE = 'response';
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param string|null $id The ID of the function call this is responding to.
         * @param string|null $name The name of the function that was called.
         * @param mixed $response The response data from the function.
         * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If neither id nor name is provided.
         */
        public function __construct(?string $id, ?string $name, $response)
        {
        }
        /**
         * Gets the function call ID.
         *
         * @since 0.1.0
         *
         * @return string|null The function call ID.
         */
        public function getId(): ?string
        {
        }
        /**
         * Gets the function name.
         *
         * @since 0.1.0
         *
         * @return string|null The function name.
         */
        public function getName(): ?string
        {
        }
        /**
         * Gets the function response.
         *
         * @since 0.1.0
         *
         * @return mixed The response data.
         */
        public function getResponse()
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return FunctionResponseArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
    }
    /**
     * Represents web search configuration for AI models.
     *
     * This DTO defines constraints for web searches that AI models can perform,
     * including allowed and disallowed domains.
     *
     * @since 0.1.0
     *
     * @phpstan-type WebSearchArrayShape array{allowedDomains?: string[], disallowedDomains?: string[]}
     *
     * @extends \WordPress\AiClient\Common\AbstractDataTransferObject<WebSearchArrayShape>
     */
    class WebSearch extends \WordPress\AiClient\Common\AbstractDataTransferObject
    {
        public const KEY_ALLOWED_DOMAINS = 'allowedDomains';
        public const KEY_DISALLOWED_DOMAINS = 'disallowedDomains';
        /**
         * Constructor.
         *
         * @since 0.1.0
         *
         * @param string[] $allowedDomains List of domains that are allowed for web search.
         * @param string[] $disallowedDomains List of domains that are disallowed for web search.
         */
        public function __construct(array $allowedDomains = [], array $disallowedDomains = [])
        {
        }
        /**
         * Gets the allowed domains.
         *
         * @since 0.1.0
         *
         * @return string[] The allowed domains.
         */
        public function getAllowedDomains(): array
        {
        }
        /**
         * Gets the disallowed domains.
         *
         * @since 0.1.0
         *
         * @return string[] The disallowed domains.
         */
        public function getDisallowedDomains(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function getJsonSchema(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         *
         * @return WebSearchArrayShape
         */
        public function toArray(): array
        {
        }
        /**
         * {@inheritDoc}
         *
         * @since 0.1.0
         */
        public static function fromArray(array $array): self
        {
        }
    }
}
namespace WordPress\AiClientDependencies\Http\Discovery {
    /**
     * Registry that based find results on class existence.
     *
     * @author David de Boer <david@ddeboer.nl>
     * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     */
    abstract class ClassDiscovery
    {
        /**
         * Finds a class.
         *
         * @param string $type
         *
         * @return string|\Closure
         *
         * @throws \WordPress\AiClientDependencies\Http\Discovery\Exception\DiscoveryFailedException
         */
        protected static function findOneByType($type)
        {
        }
        /**
         * Set new strategies and clear the cache.
         *
         * @param string[] $strategies list of fully qualified class names that implement DiscoveryStrategy
         */
        public static function setStrategies(array $strategies)
        {
        }
        /**
         * Returns the currently configured discovery strategies as fully qualified class names.
         *
         * @return string[]
         */
        public static function getStrategies(): iterable
        {
        }
        /**
         * Append a strategy at the end of the strategy queue.
         *
         * @param string $strategy Fully qualified class name of a DiscoveryStrategy
         */
        public static function appendStrategy($strategy)
        {
        }
        /**
         * Prepend a strategy at the beginning of the strategy queue.
         *
         * @param string $strategy Fully qualified class name to a DiscoveryStrategy
         */
        public static function prependStrategy($strategy)
        {
        }
        public static function clearCache()
        {
        }
        /**
         * Evaluates conditions to boolean.
         *
         * @return bool
         */
        protected static function evaluateCondition($condition)
        {
        }
        /**
         * Get an instance of the $class.
         *
         * @param string|\Closure $class a FQCN of a class or a closure that instantiate the class
         *
         * @return object
         *
         * @throws \WordPress\AiClientDependencies\Http\Discovery\Exception\ClassInstantiationFailedException
         */
        protected static function instantiateClass($class)
        {
        }
        /**
         * We need a "safe" version of PHP's "class_exists" because Magento has a bug
         * (or they call it a "feature"). Magento is throwing an exception if you do class_exists()
         * on a class that ends with "Factory" and if that file does not exits.
         *
         * This function catches all potential exceptions and makes sure to always return a boolean.
         *
         * @param string $class
         *
         * @return bool
         */
        public static function safeClassExists($class)
        {
        }
    }
    /**
     * An interface implemented by all discovery related exceptions.
     *
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     */
    interface Exception extends \Throwable
    {
    }
}
namespace WordPress\AiClientDependencies\Http\Discovery\Exception {
    /**
     * Thrown when a class fails to instantiate.
     *
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     */
    final class ClassInstantiationFailedException extends \RuntimeException implements \WordPress\AiClientDependencies\Http\Discovery\Exception
    {
    }
    /**
     * Thrown when all discovery strategies fails to find a resource.
     *
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     */
    final class DiscoveryFailedException extends \Exception implements \WordPress\AiClientDependencies\Http\Discovery\Exception
    {
        /**
         * @param string       $message
         * @param \Exception[] $exceptions
         */
        public function __construct($message, array $exceptions = [])
        {
        }
        /**
         * @param \Exception[] $exceptions
         */
        public static function create($exceptions)
        {
        }
        /**
         * @return \Exception[]
         */
        public function getExceptions()
        {
        }
    }
    /**
     * When we have used a strategy but no candidates provided by that strategy could be used.
     *
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     */
    final class NoCandidateFoundException extends \Exception implements \WordPress\AiClientDependencies\Http\Discovery\Exception
    {
        /**
         * @param string $strategy
         */
        public function __construct($strategy, array $candidates)
        {
        }
    }
    /**
     * Thrown when a discovery does not find any matches.
     *
     * @final do NOT extend this class, not final for BC reasons
     *
     * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
     */
    class NotFoundException extends \RuntimeException implements \WordPress\AiClientDependencies\Http\Discovery\Exception
    {
    }
    /**
     * This exception is thrown when we cannot use a discovery strategy. This is *not* thrown when
     * the discovery fails to find a class.
     *
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     */
    class StrategyUnavailableException extends \RuntimeException implements \WordPress\AiClientDependencies\Http\Discovery\Exception
    {
    }
    /**
     * Thrown when we can't use Puli for discovery.
     *
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     */
    final class PuliUnavailableException extends \WordPress\AiClientDependencies\Http\Discovery\Exception\StrategyUnavailableException
    {
    }
}
namespace WordPress\AiClientDependencies\Http\Discovery {
    /**
     * Finds PSR-17 factories.
     *
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     */
    final class Psr17FactoryDiscovery extends \WordPress\AiClientDependencies\Http\Discovery\ClassDiscovery
    {
        /**
         * @return \WordPress\AiClientDependencies\Psr\Http\Message\RequestFactoryInterface
         *
         * @throws \WordPress\AiClientDependencies\Http\Discovery\Exception\NotFoundException
         */
        public static function findRequestFactory()
        {
        }
        /**
         * @return \WordPress\AiClientDependencies\Psr\Http\Message\ResponseFactoryInterface
         *
         * @throws \WordPress\AiClientDependencies\Http\Discovery\Exception\NotFoundException
         */
        public static function findResponseFactory()
        {
        }
        /**
         * @return \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestFactoryInterface
         *
         * @throws \WordPress\AiClientDependencies\Http\Discovery\Exception\NotFoundException
         */
        public static function findServerRequestFactory()
        {
        }
        /**
         * @return \WordPress\AiClientDependencies\Psr\Http\Message\StreamFactoryInterface
         *
         * @throws \WordPress\AiClientDependencies\Http\Discovery\Exception\NotFoundException
         */
        public static function findStreamFactory()
        {
        }
        /**
         * @return \WordPress\AiClientDependencies\Psr\Http\Message\UploadedFileFactoryInterface
         *
         * @throws \WordPress\AiClientDependencies\Http\Discovery\Exception\NotFoundException
         */
        public static function findUploadedFileFactory()
        {
        }
        /**
         * @return \WordPress\AiClientDependencies\Psr\Http\Message\UriFactoryInterface
         *
         * @throws \WordPress\AiClientDependencies\Http\Discovery\Exception\NotFoundException
         */
        public static function findUriFactory()
        {
        }
        /**
         * @return \WordPress\AiClientDependencies\Psr\Http\Message\UriFactoryInterface
         *
         * @throws \WordPress\AiClientDependencies\Http\Discovery\Exception\NotFoundException
         *
         * @deprecated This will be removed in 2.0. Consider using the findUriFactory() method.
         */
        public static function findUrlFactory()
        {
        }
    }
    /**
     * Finds a PSR-18 HTTP Client.
     *
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     */
    final class Psr18ClientDiscovery extends \WordPress\AiClientDependencies\Http\Discovery\ClassDiscovery
    {
        /**
         * Finds a PSR-18 HTTP Client.
         *
         * @return \WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface
         *
         * @throws \WordPress\AiClientDependencies\Http\Discovery\Exception\NotFoundException
         */
        public static function find()
        {
        }
    }
}
namespace WordPress\AiClientDependencies\Http\Discovery\Strategy {
    /**
     * @internal
     *
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     *
     * Don't miss updating src/Composer/Plugin.php when adding a new supported class.
     */
    final class CommonClassesStrategy implements \WordPress\AiClientDependencies\Http\Discovery\Strategy\DiscoveryStrategy
    {
        public static function getCandidates($type)
        {
        }
        public static function buzzInstantiate()
        {
        }
        public static function symfonyPsr18Instantiate()
        {
        }
        public static function isGuzzleImplementingPsr18()
        {
        }
        public static function isSymfonyImplementingHttpClient()
        {
        }
        /**
         * Can be used as a condition.
         *
         * @return bool
         */
        public static function isPsr17FactoryInstalled()
        {
        }
    }
    /**
     * @internal
     *
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     *
     * Don't miss updating src/Composer/Plugin.php when adding a new supported class.
     */
    final class CommonPsr17ClassesStrategy implements \WordPress\AiClientDependencies\Http\Discovery\Strategy\DiscoveryStrategy
    {
        public static function getCandidates($type)
        {
        }
    }
    /**
     * Find candidates using Puli.
     *
     * @internal
     *
     * @final
     *
     * @author David de Boer <david@ddeboer.nl>
     * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
     */
    class PuliBetaStrategy implements \WordPress\AiClientDependencies\Http\Discovery\Strategy\DiscoveryStrategy
    {
        /**
         * @var \WordPress\AiClientDependencies\Puli\GeneratedPuliFactory
         */
        protected static $puliFactory;
        /**
         * @var \WordPress\AiClientDependencies\Puli\Discovery\Api\Discovery
         */
        protected static $puliDiscovery;
        public static function getCandidates($type)
        {
        }
    }
}
namespace WordPress\AiClientDependencies\Psr\Http\Message {
    interface RequestFactoryInterface
    {
        /**
         * Create a new request.
         *
         * @param string $method The HTTP method associated with the request.
         * @param UriInterface|string $uri The URI associated with the request. If
         *     the value is a string, the factory MUST create a UriInterface
         *     instance based on it.
         *
         * @return RequestInterface
         */
        public function createRequest(string $method, $uri): \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface;
    }
    interface ResponseFactoryInterface
    {
        /**
         * Create a new response.
         *
         * @param int $code HTTP status code; defaults to 200
         * @param string $reasonPhrase Reason phrase to associate with status code
         *     in generated response; if none is provided implementations MAY use
         *     the defaults as suggested in the HTTP specification.
         *
         * @return ResponseInterface
         */
        public function createResponse(int $code = 200, string $reasonPhrase = ''): \WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface;
    }
    interface ServerRequestFactoryInterface
    {
        /**
         * Create a new server request.
         *
         * Note that server-params are taken precisely as given - no parsing/processing
         * of the given values is performed, and, in particular, no attempt is made to
         * determine the HTTP method or URI, which must be provided explicitly.
         *
         * @param string $method The HTTP method associated with the request.
         * @param UriInterface|string $uri The URI associated with the request. If
         *     the value is a string, the factory MUST create a UriInterface
         *     instance based on it.
         * @param array $serverParams Array of SAPI parameters with which to seed
         *     the generated request instance.
         *
         * @return ServerRequestInterface
         */
        public function createServerRequest(string $method, $uri, array $serverParams = []): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface;
    }
    interface StreamFactoryInterface
    {
        /**
         * Create a new stream from a string.
         *
         * The stream SHOULD be created with a temporary resource.
         *
         * @param string $content String content with which to populate the stream.
         *
         * @return StreamInterface
         */
        public function createStream(string $content = ''): \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface;
        /**
         * Create a stream from an existing file.
         *
         * The file MUST be opened using the given mode, which may be any mode
         * supported by the `fopen` function.
         *
         * The `$filename` MAY be any string supported by `fopen()`.
         *
         * @param string $filename Filename or stream URI to use as basis of stream.
         * @param string $mode Mode with which to open the underlying filename/stream.
         *
         * @return StreamInterface
         * @throws \RuntimeException If the file cannot be opened.
         * @throws \InvalidArgumentException If the mode is invalid.
         */
        public function createStreamFromFile(string $filename, string $mode = 'r'): \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface;
        /**
         * Create a new stream from an existing resource.
         *
         * The stream MUST be readable and may be writable.
         *
         * @param resource $resource PHP resource to use as basis of stream.
         *
         * @return StreamInterface
         */
        public function createStreamFromResource($resource): \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface;
    }
    interface UploadedFileFactoryInterface
    {
        /**
         * Create a new uploaded file.
         *
         * If a size is not provided it will be determined by checking the size of
         * the file.
         *
         * @see http://php.net/manual/features.file-upload.post-method.php
         * @see http://php.net/manual/features.file-upload.errors.php
         *
         * @param StreamInterface $stream Underlying stream representing the
         *     uploaded file content.
         * @param int|null $size in bytes
         * @param int $error PHP file upload error
         * @param string|null $clientFilename Filename as provided by the client, if any.
         * @param string|null $clientMediaType Media type as provided by the client, if any.
         *
         * @return UploadedFileInterface
         *
         * @throws \InvalidArgumentException If the file resource is not readable.
         */
        public function createUploadedFile(\WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface $stream, ?int $size = null, int $error = \UPLOAD_ERR_OK, ?string $clientFilename = null, ?string $clientMediaType = null): \WordPress\AiClientDependencies\Psr\Http\Message\UploadedFileInterface;
    }
    interface UriFactoryInterface
    {
        /**
         * Create a new URI.
         *
         * @param string $uri
         *
         * @return UriInterface
         *
         * @throws \InvalidArgumentException If the given URI cannot be parsed.
         */
        public function createUri(string $uri = ''): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface;
    }
}
namespace WordPress\AiClientDependencies\Nyholm\Psr7\Factory {
    /**
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     * @author Martijn van der Ven <martijn@vanderven.se>
     *
     * @final This class should never be extended. See https://github.com/Nyholm/psr7/blob/master/doc/final.md
     */
    class Psr17Factory implements \WordPress\AiClientDependencies\Psr\Http\Message\RequestFactoryInterface, \WordPress\AiClientDependencies\Psr\Http\Message\ResponseFactoryInterface, \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestFactoryInterface, \WordPress\AiClientDependencies\Psr\Http\Message\StreamFactoryInterface, \WordPress\AiClientDependencies\Psr\Http\Message\UploadedFileFactoryInterface, \WordPress\AiClientDependencies\Psr\Http\Message\UriFactoryInterface
    {
        public function createRequest(string $method, $uri): \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface
        {
        }
        public function createResponse(int $code = 200, string $reasonPhrase = ''): \WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface
        {
        }
        public function createStream(string $content = ''): \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface
        {
        }
        public function createStreamFromFile(string $filename, string $mode = 'r'): \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface
        {
        }
        public function createStreamFromResource($resource): \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface
        {
        }
        public function createUploadedFile(\WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface $stream, ?int $size = null, int $error = \UPLOAD_ERR_OK, ?string $clientFilename = null, ?string $clientMediaType = null): \WordPress\AiClientDependencies\Psr\Http\Message\UploadedFileInterface
        {
        }
        public function createUri(string $uri = ''): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface
        {
        }
        public function createServerRequest(string $method, $uri, array $serverParams = []): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface
        {
        }
    }
}
namespace WordPress\AiClientDependencies\Nyholm\Psr7 {
    /**
     * Trait implementing functionality common to requests and responses.
     *
     * @author Michael Dowling and contributors to guzzlehttp/psr7
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     * @author Martijn van der Ven <martijn@vanderven.se>
     *
     * @internal should not be used outside of Nyholm/Psr7 as it does not fall under our BC promise
     */
    trait MessageTrait
    {
        /** @var array Map of all registered headers, as original name => array of values */
        private $headers = [];
        /** @var array Map of lowercase header name => original name at registration */
        private $headerNames = [];
        /** @var string */
        private $protocol = '1.1';
        /** @var \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface|null */
        private $stream;
        public function getProtocolVersion(): string
        {
        }
        /**
         * @return static
         */
        public function withProtocolVersion($version): \WordPress\AiClientDependencies\Psr\Http\Message\MessageInterface
        {
        }
        public function getHeaders(): array
        {
        }
        public function hasHeader($header): bool
        {
        }
        public function getHeader($header): array
        {
        }
        public function getHeaderLine($header): string
        {
        }
        /**
         * @return static
         */
        public function withHeader($header, $value): \WordPress\AiClientDependencies\Psr\Http\Message\MessageInterface
        {
        }
        /**
         * @return static
         */
        public function withAddedHeader($header, $value): \WordPress\AiClientDependencies\Psr\Http\Message\MessageInterface
        {
        }
        /**
         * @return static
         */
        public function withoutHeader($header): \WordPress\AiClientDependencies\Psr\Http\Message\MessageInterface
        {
        }
        public function getBody(): \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface
        {
        }
        /**
         * @return static
         */
        public function withBody(\WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface $body): \WordPress\AiClientDependencies\Psr\Http\Message\MessageInterface
        {
        }
        private function setHeaders(array $headers): void
        {
        }
        /**
         * Make sure the header complies with RFC 7230.
         *
         * Header names must be a non-empty string consisting of token characters.
         *
         * Header values must be strings consisting of visible characters with all optional
         * leading and trailing whitespace stripped. This method will always strip such
         * optional whitespace. Note that the method does not allow folding whitespace within
         * the values as this was deprecated for almost all instances by the RFC.
         *
         * header-field = field-name ":" OWS field-value OWS
         * field-name   = 1*( "!" / "#" / "$" / "%" / "&" / "'" / "*" / "+" / "-" / "." / "^"
         *              / "_" / "`" / "|" / "~" / %x30-39 / ( %x41-5A / %x61-7A ) )
         * OWS          = *( SP / HTAB )
         * field-value  = *( ( %x21-7E / %x80-FF ) [ 1*( SP / HTAB ) ( %x21-7E / %x80-FF ) ] )
         *
         * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
         */
        private function validateAndTrimHeader($header, $values): array
        {
        }
    }
}
namespace WordPress\AiClientDependencies\Psr\Http\Message {
    /**
     * HTTP messages consist of requests from a client to a server and responses
     * from a server to a client. This interface defines the methods common to
     * each.
     *
     * Messages are considered immutable; all methods that might change state MUST
     * be implemented such that they retain the internal state of the current
     * message and return an instance that contains the changed state.
     *
     * @link http://www.ietf.org/rfc/rfc7230.txt
     * @link http://www.ietf.org/rfc/rfc7231.txt
     */
    interface MessageInterface
    {
        /**
         * Retrieves the HTTP protocol version as a string.
         *
         * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
         *
         * @return string HTTP protocol version.
         */
        public function getProtocolVersion(): string;
        /**
         * Return an instance with the specified HTTP protocol version.
         *
         * The version string MUST contain only the HTTP version number (e.g.,
         * "1.1", "1.0").
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that has the
         * new protocol version.
         *
         * @param string $version HTTP protocol version
         * @return static
         */
        public function withProtocolVersion(string $version): \WordPress\AiClientDependencies\Psr\Http\Message\MessageInterface;
        /**
         * Retrieves all message header values.
         *
         * The keys represent the header name as it will be sent over the wire, and
         * each value is an array of strings associated with the header.
         *
         *     // Represent the headers as a string
         *     foreach ($message->getHeaders() as $name => $values) {
         *         echo $name . ": " . implode(", ", $values);
         *     }
         *
         *     // Emit headers iteratively:
         *     foreach ($message->getHeaders() as $name => $values) {
         *         foreach ($values as $value) {
         *             header(sprintf('%s: %s', $name, $value), false);
         *         }
         *     }
         *
         * While header names are not case-sensitive, getHeaders() will preserve the
         * exact case in which headers were originally specified.
         *
         * @return string[][] Returns an associative array of the message's headers. Each
         *     key MUST be a header name, and each value MUST be an array of strings
         *     for that header.
         */
        public function getHeaders(): array;
        /**
         * Checks if a header exists by the given case-insensitive name.
         *
         * @param string $name Case-insensitive header field name.
         * @return bool Returns true if any header names match the given header
         *     name using a case-insensitive string comparison. Returns false if
         *     no matching header name is found in the message.
         */
        public function hasHeader(string $name): bool;
        /**
         * Retrieves a message header value by the given case-insensitive name.
         *
         * This method returns an array of all the header values of the given
         * case-insensitive header name.
         *
         * If the header does not appear in the message, this method MUST return an
         * empty array.
         *
         * @param string $name Case-insensitive header field name.
         * @return string[] An array of string values as provided for the given
         *    header. If the header does not appear in the message, this method MUST
         *    return an empty array.
         */
        public function getHeader(string $name): array;
        /**
         * Retrieves a comma-separated string of the values for a single header.
         *
         * This method returns all of the header values of the given
         * case-insensitive header name as a string concatenated together using
         * a comma.
         *
         * NOTE: Not all header values may be appropriately represented using
         * comma concatenation. For such headers, use getHeader() instead
         * and supply your own delimiter when concatenating.
         *
         * If the header does not appear in the message, this method MUST return
         * an empty string.
         *
         * @param string $name Case-insensitive header field name.
         * @return string A string of values as provided for the given header
         *    concatenated together using a comma. If the header does not appear in
         *    the message, this method MUST return an empty string.
         */
        public function getHeaderLine(string $name): string;
        /**
         * Return an instance with the provided value replacing the specified header.
         *
         * While header names are case-insensitive, the casing of the header will
         * be preserved by this function, and returned from getHeaders().
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that has the
         * new and/or updated header and value.
         *
         * @param string $name Case-insensitive header field name.
         * @param string|string[] $value Header value(s).
         * @return static
         * @throws \InvalidArgumentException for invalid header names or values.
         */
        public function withHeader(string $name, $value): \WordPress\AiClientDependencies\Psr\Http\Message\MessageInterface;
        /**
         * Return an instance with the specified header appended with the given value.
         *
         * Existing values for the specified header will be maintained. The new
         * value(s) will be appended to the existing list. If the header did not
         * exist previously, it will be added.
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that has the
         * new header and/or value.
         *
         * @param string $name Case-insensitive header field name to add.
         * @param string|string[] $value Header value(s).
         * @return static
         * @throws \InvalidArgumentException for invalid header names or values.
         */
        public function withAddedHeader(string $name, $value): \WordPress\AiClientDependencies\Psr\Http\Message\MessageInterface;
        /**
         * Return an instance without the specified header.
         *
         * Header resolution MUST be done without case-sensitivity.
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that removes
         * the named header.
         *
         * @param string $name Case-insensitive header field name to remove.
         * @return static
         */
        public function withoutHeader(string $name): \WordPress\AiClientDependencies\Psr\Http\Message\MessageInterface;
        /**
         * Gets the body of the message.
         *
         * @return StreamInterface Returns the body as a stream.
         */
        public function getBody(): \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface;
        /**
         * Return an instance with the specified message body.
         *
         * The body MUST be a StreamInterface object.
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return a new instance that has the
         * new body stream.
         *
         * @param StreamInterface $body Body.
         * @return static
         * @throws \InvalidArgumentException When the body is not valid.
         */
        public function withBody(\WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface $body): \WordPress\AiClientDependencies\Psr\Http\Message\MessageInterface;
    }
    /**
     * Representation of an outgoing, client-side request.
     *
     * Per the HTTP specification, this interface includes properties for
     * each of the following:
     *
     * - Protocol version
     * - HTTP method
     * - URI
     * - Headers
     * - Message body
     *
     * During construction, implementations MUST attempt to set the Host header from
     * a provided URI if no Host header is provided.
     *
     * Requests are considered immutable; all methods that might change state MUST
     * be implemented such that they retain the internal state of the current
     * message and return an instance that contains the changed state.
     */
    interface RequestInterface extends \WordPress\AiClientDependencies\Psr\Http\Message\MessageInterface
    {
        /**
         * Retrieves the message's request target.
         *
         * Retrieves the message's request-target either as it will appear (for
         * clients), as it appeared at request (for servers), or as it was
         * specified for the instance (see withRequestTarget()).
         *
         * In most cases, this will be the origin-form of the composed URI,
         * unless a value was provided to the concrete implementation (see
         * withRequestTarget() below).
         *
         * If no URI is available, and no request-target has been specifically
         * provided, this method MUST return the string "/".
         *
         * @return string
         */
        public function getRequestTarget(): string;
        /**
         * Return an instance with the specific request-target.
         *
         * If the request needs a non-origin-form request-target — e.g., for
         * specifying an absolute-form, authority-form, or asterisk-form —
         * this method may be used to create an instance with the specified
         * request-target, verbatim.
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that has the
         * changed request target.
         *
         * @link http://tools.ietf.org/html/rfc7230#section-5.3 (for the various
         *     request-target forms allowed in request messages)
         * @param string $requestTarget
         * @return static
         */
        public function withRequestTarget(string $requestTarget): \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface;
        /**
         * Retrieves the HTTP method of the request.
         *
         * @return string Returns the request method.
         */
        public function getMethod(): string;
        /**
         * Return an instance with the provided HTTP method.
         *
         * While HTTP method names are typically all uppercase characters, HTTP
         * method names are case-sensitive and thus implementations SHOULD NOT
         * modify the given string.
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that has the
         * changed request method.
         *
         * @param string $method Case-sensitive method.
         * @return static
         * @throws \InvalidArgumentException for invalid HTTP methods.
         */
        public function withMethod(string $method): \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface;
        /**
         * Retrieves the URI instance.
         *
         * This method MUST return a UriInterface instance.
         *
         * @link http://tools.ietf.org/html/rfc3986#section-4.3
         * @return UriInterface Returns a UriInterface instance
         *     representing the URI of the request.
         */
        public function getUri(): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface;
        /**
         * Returns an instance with the provided URI.
         *
         * This method MUST update the Host header of the returned request by
         * default if the URI contains a host component. If the URI does not
         * contain a host component, any pre-existing Host header MUST be carried
         * over to the returned request.
         *
         * You can opt-in to preserving the original state of the Host header by
         * setting `$preserveHost` to `true`. When `$preserveHost` is set to
         * `true`, this method interacts with the Host header in the following ways:
         *
         * - If the Host header is missing or empty, and the new URI contains
         *   a host component, this method MUST update the Host header in the returned
         *   request.
         * - If the Host header is missing or empty, and the new URI does not contain a
         *   host component, this method MUST NOT update the Host header in the returned
         *   request.
         * - If a Host header is present and non-empty, this method MUST NOT update
         *   the Host header in the returned request.
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that has the
         * new UriInterface instance.
         *
         * @link http://tools.ietf.org/html/rfc3986#section-4.3
         * @param UriInterface $uri New request URI to use.
         * @param bool $preserveHost Preserve the original state of the Host header.
         * @return static
         */
        public function withUri(\WordPress\AiClientDependencies\Psr\Http\Message\UriInterface $uri, bool $preserveHost = \false): \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface;
    }
}
namespace WordPress\AiClientDependencies\Nyholm\Psr7 {
    /**
     * @author Michael Dowling and contributors to guzzlehttp/psr7
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     * @author Martijn van der Ven <martijn@vanderven.se>
     *
     * @internal should not be used outside of Nyholm/Psr7 as it does not fall under our BC promise
     */
    trait RequestTrait
    {
        /** @var string */
        private $method;
        /** @var string|null */
        private $requestTarget;
        /** @var \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface|null */
        private $uri;
        public function getRequestTarget(): string
        {
        }
        /**
         * @return static
         */
        public function withRequestTarget($requestTarget): \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface
        {
        }
        public function getMethod(): string
        {
        }
        /**
         * @return static
         */
        public function withMethod($method): \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface
        {
        }
        public function getUri(): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface
        {
        }
        /**
         * @return static
         */
        public function withUri(\WordPress\AiClientDependencies\Psr\Http\Message\UriInterface $uri, $preserveHost = \false): \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface
        {
        }
        private function updateHostFromUri(): void
        {
        }
    }
    /**
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     * @author Martijn van der Ven <martijn@vanderven.se>
     *
     * @final This class should never be extended. See https://github.com/Nyholm/psr7/blob/master/doc/final.md
     */
    class Request implements \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface
    {
        use \WordPress\AiClientDependencies\Nyholm\Psr7\MessageTrait;
        use \WordPress\AiClientDependencies\Nyholm\Psr7\RequestTrait;
        /**
         * @param string $method HTTP method
         * @param string|\WordPress\AiClientDependencies\Psr\Http\Message\UriInterface $uri URI
         * @param array $headers Request headers
         * @param string|resource|\WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface|null $body Request body
         * @param string $version Protocol version
         */
        public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1')
        {
        }
    }
}
namespace WordPress\AiClientDependencies\Psr\Http\Message {
    /**
     * Representation of an outgoing, server-side response.
     *
     * Per the HTTP specification, this interface includes properties for
     * each of the following:
     *
     * - Protocol version
     * - Status code and reason phrase
     * - Headers
     * - Message body
     *
     * Responses are considered immutable; all methods that might change state MUST
     * be implemented such that they retain the internal state of the current
     * message and return an instance that contains the changed state.
     */
    interface ResponseInterface extends \WordPress\AiClientDependencies\Psr\Http\Message\MessageInterface
    {
        /**
         * Gets the response status code.
         *
         * The status code is a 3-digit integer result code of the server's attempt
         * to understand and satisfy the request.
         *
         * @return int Status code.
         */
        public function getStatusCode(): int;
        /**
         * Return an instance with the specified status code and, optionally, reason phrase.
         *
         * If no reason phrase is specified, implementations MAY choose to default
         * to the RFC 7231 or IANA recommended reason phrase for the response's
         * status code.
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that has the
         * updated status and reason phrase.
         *
         * @link http://tools.ietf.org/html/rfc7231#section-6
         * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
         * @param int $code The 3-digit integer result code to set.
         * @param string $reasonPhrase The reason phrase to use with the
         *     provided status code; if none is provided, implementations MAY
         *     use the defaults as suggested in the HTTP specification.
         * @return static
         * @throws \InvalidArgumentException For invalid status code arguments.
         */
        public function withStatus(int $code, string $reasonPhrase = ''): \WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface;
        /**
         * Gets the response reason phrase associated with the status code.
         *
         * Because a reason phrase is not a required element in a response
         * status line, the reason phrase value MAY be null. Implementations MAY
         * choose to return the default RFC 7231 recommended reason phrase (or those
         * listed in the IANA HTTP Status Code Registry) for the response's
         * status code.
         *
         * @link http://tools.ietf.org/html/rfc7231#section-6
         * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
         * @return string Reason phrase; must return an empty string if none present.
         */
        public function getReasonPhrase(): string;
    }
}
namespace WordPress\AiClientDependencies\Nyholm\Psr7 {
    /**
     * @author Michael Dowling and contributors to guzzlehttp/psr7
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     * @author Martijn van der Ven <martijn@vanderven.se>
     *
     * @final This class should never be extended. See https://github.com/Nyholm/psr7/blob/master/doc/final.md
     */
    class Response implements \WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface
    {
        use \WordPress\AiClientDependencies\Nyholm\Psr7\MessageTrait;
        /**
         * @param int $status Status code
         * @param array $headers Response headers
         * @param string|resource|\WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface|null $body Response body
         * @param string $version Protocol version
         * @param string|null $reason Reason phrase (when empty a default will be used based on the status code)
         */
        public function __construct(int $status = 200, array $headers = [], $body = null, string $version = '1.1', ?string $reason = null)
        {
        }
        public function getStatusCode(): int
        {
        }
        public function getReasonPhrase(): string
        {
        }
        /**
         * @return static
         */
        public function withStatus($code, $reasonPhrase = ''): \WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface
        {
        }
    }
}
namespace WordPress\AiClientDependencies\Psr\Http\Message {
    /**
     * Representation of an incoming, server-side HTTP request.
     *
     * Per the HTTP specification, this interface includes properties for
     * each of the following:
     *
     * - Protocol version
     * - HTTP method
     * - URI
     * - Headers
     * - Message body
     *
     * Additionally, it encapsulates all data as it has arrived to the
     * application from the CGI and/or PHP environment, including:
     *
     * - The values represented in $_SERVER.
     * - Any cookies provided (generally via $_COOKIE)
     * - Query string arguments (generally via $_GET, or as parsed via parse_str())
     * - Upload files, if any (as represented by $_FILES)
     * - Deserialized body parameters (generally from $_POST)
     *
     * $_SERVER values MUST be treated as immutable, as they represent application
     * state at the time of request; as such, no methods are provided to allow
     * modification of those values. The other values provide such methods, as they
     * can be restored from $_SERVER or the request body, and may need treatment
     * during the application (e.g., body parameters may be deserialized based on
     * content type).
     *
     * Additionally, this interface recognizes the utility of introspecting a
     * request to derive and match additional parameters (e.g., via URI path
     * matching, decrypting cookie values, deserializing non-form-encoded body
     * content, matching authorization headers to users, etc). These parameters
     * are stored in an "attributes" property.
     *
     * Requests are considered immutable; all methods that might change state MUST
     * be implemented such that they retain the internal state of the current
     * message and return an instance that contains the changed state.
     */
    interface ServerRequestInterface extends \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface
    {
        /**
         * Retrieve server parameters.
         *
         * Retrieves data related to the incoming request environment,
         * typically derived from PHP's $_SERVER superglobal. The data IS NOT
         * REQUIRED to originate from $_SERVER.
         *
         * @return array
         */
        public function getServerParams(): array;
        /**
         * Retrieve cookies.
         *
         * Retrieves cookies sent by the client to the server.
         *
         * The data MUST be compatible with the structure of the $_COOKIE
         * superglobal.
         *
         * @return array
         */
        public function getCookieParams(): array;
        /**
         * Return an instance with the specified cookies.
         *
         * The data IS NOT REQUIRED to come from the $_COOKIE superglobal, but MUST
         * be compatible with the structure of $_COOKIE. Typically, this data will
         * be injected at instantiation.
         *
         * This method MUST NOT update the related Cookie header of the request
         * instance, nor related values in the server params.
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that has the
         * updated cookie values.
         *
         * @param array $cookies Array of key/value pairs representing cookies.
         * @return static
         */
        public function withCookieParams(array $cookies): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface;
        /**
         * Retrieve query string arguments.
         *
         * Retrieves the deserialized query string arguments, if any.
         *
         * Note: the query params might not be in sync with the URI or server
         * params. If you need to ensure you are only getting the original
         * values, you may need to parse the query string from `getUri()->getQuery()`
         * or from the `QUERY_STRING` server param.
         *
         * @return array
         */
        public function getQueryParams(): array;
        /**
         * Return an instance with the specified query string arguments.
         *
         * These values SHOULD remain immutable over the course of the incoming
         * request. They MAY be injected during instantiation, such as from PHP's
         * $_GET superglobal, or MAY be derived from some other value such as the
         * URI. In cases where the arguments are parsed from the URI, the data
         * MUST be compatible with what PHP's parse_str() would return for
         * purposes of how duplicate query parameters are handled, and how nested
         * sets are handled.
         *
         * Setting query string arguments MUST NOT change the URI stored by the
         * request, nor the values in the server params.
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that has the
         * updated query string arguments.
         *
         * @param array $query Array of query string arguments, typically from
         *     $_GET.
         * @return static
         */
        public function withQueryParams(array $query): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface;
        /**
         * Retrieve normalized file upload data.
         *
         * This method returns upload metadata in a normalized tree, with each leaf
         * an instance of Psr\Http\Message\UploadedFileInterface.
         *
         * These values MAY be prepared from $_FILES or the message body during
         * instantiation, or MAY be injected via withUploadedFiles().
         *
         * @return array An array tree of UploadedFileInterface instances; an empty
         *     array MUST be returned if no data is present.
         */
        public function getUploadedFiles(): array;
        /**
         * Create a new instance with the specified uploaded files.
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that has the
         * updated body parameters.
         *
         * @param array $uploadedFiles An array tree of UploadedFileInterface instances.
         * @return static
         * @throws \InvalidArgumentException if an invalid structure is provided.
         */
        public function withUploadedFiles(array $uploadedFiles): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface;
        /**
         * Retrieve any parameters provided in the request body.
         *
         * If the request Content-Type is either application/x-www-form-urlencoded
         * or multipart/form-data, and the request method is POST, this method MUST
         * return the contents of $_POST.
         *
         * Otherwise, this method may return any results of deserializing
         * the request body content; as parsing returns structured content, the
         * potential types MUST be arrays or objects only. A null value indicates
         * the absence of body content.
         *
         * @return null|array|object The deserialized body parameters, if any.
         *     These will typically be an array or object.
         */
        public function getParsedBody();
        /**
         * Return an instance with the specified body parameters.
         *
         * These MAY be injected during instantiation.
         *
         * If the request Content-Type is either application/x-www-form-urlencoded
         * or multipart/form-data, and the request method is POST, use this method
         * ONLY to inject the contents of $_POST.
         *
         * The data IS NOT REQUIRED to come from $_POST, but MUST be the results of
         * deserializing the request body content. Deserialization/parsing returns
         * structured data, and, as such, this method ONLY accepts arrays or objects,
         * or a null value if nothing was available to parse.
         *
         * As an example, if content negotiation determines that the request data
         * is a JSON payload, this method could be used to create a request
         * instance with the deserialized parameters.
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that has the
         * updated body parameters.
         *
         * @param null|array|object $data The deserialized body data. This will
         *     typically be in an array or object.
         * @return static
         * @throws \InvalidArgumentException if an unsupported argument type is
         *     provided.
         */
        public function withParsedBody($data): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface;
        /**
         * Retrieve attributes derived from the request.
         *
         * The request "attributes" may be used to allow injection of any
         * parameters derived from the request: e.g., the results of path
         * match operations; the results of decrypting cookies; the results of
         * deserializing non-form-encoded message bodies; etc. Attributes
         * will be application and request specific, and CAN be mutable.
         *
         * @return array Attributes derived from the request.
         */
        public function getAttributes(): array;
        /**
         * Retrieve a single derived request attribute.
         *
         * Retrieves a single derived request attribute as described in
         * getAttributes(). If the attribute has not been previously set, returns
         * the default value as provided.
         *
         * This method obviates the need for a hasAttribute() method, as it allows
         * specifying a default value to return if the attribute is not found.
         *
         * @see getAttributes()
         * @param string $name The attribute name.
         * @param mixed $default Default value to return if the attribute does not exist.
         * @return mixed
         */
        public function getAttribute(string $name, $default = null);
        /**
         * Return an instance with the specified derived request attribute.
         *
         * This method allows setting a single derived request attribute as
         * described in getAttributes().
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that has the
         * updated attribute.
         *
         * @see getAttributes()
         * @param string $name The attribute name.
         * @param mixed $value The value of the attribute.
         * @return static
         */
        public function withAttribute(string $name, $value): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface;
        /**
         * Return an instance that removes the specified derived request attribute.
         *
         * This method allows removing a single derived request attribute as
         * described in getAttributes().
         *
         * This method MUST be implemented in such a way as to retain the
         * immutability of the message, and MUST return an instance that removes
         * the attribute.
         *
         * @see getAttributes()
         * @param string $name The attribute name.
         * @return static
         */
        public function withoutAttribute(string $name): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface;
    }
}
namespace WordPress\AiClientDependencies\Nyholm\Psr7 {
    /**
     * @author Michael Dowling and contributors to guzzlehttp/psr7
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     * @author Martijn van der Ven <martijn@vanderven.se>
     *
     * @final This class should never be extended. See https://github.com/Nyholm/psr7/blob/master/doc/final.md
     */
    class ServerRequest implements \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface
    {
        use \WordPress\AiClientDependencies\Nyholm\Psr7\MessageTrait;
        use \WordPress\AiClientDependencies\Nyholm\Psr7\RequestTrait;
        /**
         * @param string $method HTTP method
         * @param string|\WordPress\AiClientDependencies\Psr\Http\Message\UriInterface $uri URI
         * @param array $headers Request headers
         * @param string|resource|\WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface|null $body Request body
         * @param string $version Protocol version
         * @param array $serverParams Typically the $_SERVER superglobal
         */
        public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1', array $serverParams = [])
        {
        }
        public function getServerParams(): array
        {
        }
        public function getUploadedFiles(): array
        {
        }
        /**
         * @return static
         */
        public function withUploadedFiles(array $uploadedFiles): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface
        {
        }
        public function getCookieParams(): array
        {
        }
        /**
         * @return static
         */
        public function withCookieParams(array $cookies): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface
        {
        }
        public function getQueryParams(): array
        {
        }
        /**
         * @return static
         */
        public function withQueryParams(array $query): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface
        {
        }
        /**
         * @return array|object|null
         */
        public function getParsedBody()
        {
        }
        /**
         * @return static
         */
        public function withParsedBody($data): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface
        {
        }
        public function getAttributes(): array
        {
        }
        /**
         * @return mixed
         */
        public function getAttribute($attribute, $default = null)
        {
        }
        /**
         * @return static
         */
        public function withAttribute($attribute, $value): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface
        {
        }
        /**
         * @return static
         */
        public function withoutAttribute($attribute): \WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface
        {
        }
    }
}
namespace WordPress\AiClientDependencies\Psr\Http\Message {
    /**
     * Describes a data stream.
     *
     * Typically, an instance will wrap a PHP stream; this interface provides
     * a wrapper around the most common operations, including serialization of
     * the entire stream to a string.
     */
    interface StreamInterface
    {
        /**
         * Reads all data from the stream into a string, from the beginning to end.
         *
         * This method MUST attempt to seek to the beginning of the stream before
         * reading data and read the stream until the end is reached.
         *
         * Warning: This could attempt to load a large amount of data into memory.
         *
         * This method MUST NOT raise an exception in order to conform with PHP's
         * string casting operations.
         *
         * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
         * @return string
         */
        public function __toString(): string;
        /**
         * Closes the stream and any underlying resources.
         *
         * @return void
         */
        public function close(): void;
        /**
         * Separates any underlying resources from the stream.
         *
         * After the stream has been detached, the stream is in an unusable state.
         *
         * @return resource|null Underlying PHP stream, if any
         */
        public function detach();
        /**
         * Get the size of the stream if known.
         *
         * @return int|null Returns the size in bytes if known, or null if unknown.
         */
        public function getSize(): ?int;
        /**
         * Returns the current position of the file read/write pointer
         *
         * @return int Position of the file pointer
         * @throws \RuntimeException on error.
         */
        public function tell(): int;
        /**
         * Returns true if the stream is at the end of the stream.
         *
         * @return bool
         */
        public function eof(): bool;
        /**
         * Returns whether or not the stream is seekable.
         *
         * @return bool
         */
        public function isSeekable(): bool;
        /**
         * Seek to a position in the stream.
         *
         * @link http://www.php.net/manual/en/function.fseek.php
         * @param int $offset Stream offset
         * @param int $whence Specifies how the cursor position will be calculated
         *     based on the seek offset. Valid values are identical to the built-in
         *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
         *     offset bytes SEEK_CUR: Set position to current location plus offset
         *     SEEK_END: Set position to end-of-stream plus offset.
         * @throws \RuntimeException on failure.
         */
        public function seek(int $offset, int $whence = \SEEK_SET): void;
        /**
         * Seek to the beginning of the stream.
         *
         * If the stream is not seekable, this method will raise an exception;
         * otherwise, it will perform a seek(0).
         *
         * @see seek()
         * @link http://www.php.net/manual/en/function.fseek.php
         * @throws \RuntimeException on failure.
         */
        public function rewind(): void;
        /**
         * Returns whether or not the stream is writable.
         *
         * @return bool
         */
        public function isWritable(): bool;
        /**
         * Write data to the stream.
         *
         * @param string $string The string that is to be written.
         * @return int Returns the number of bytes written to the stream.
         * @throws \RuntimeException on failure.
         */
        public function write(string $string): int;
        /**
         * Returns whether or not the stream is readable.
         *
         * @return bool
         */
        public function isReadable(): bool;
        /**
         * Read data from the stream.
         *
         * @param int $length Read up to $length bytes from the object and return
         *     them. Fewer than $length bytes may be returned if underlying stream
         *     call returns fewer bytes.
         * @return string Returns the data read from the stream, or an empty string
         *     if no bytes are available.
         * @throws \RuntimeException if an error occurs.
         */
        public function read(int $length): string;
        /**
         * Returns the remaining contents in a string
         *
         * @return string
         * @throws \RuntimeException if unable to read or an error occurs while
         *     reading.
         */
        public function getContents(): string;
        /**
         * Get stream metadata as an associative array or retrieve a specific key.
         *
         * The keys returned are identical to the keys returned from PHP's
         * stream_get_meta_data() function.
         *
         * @link http://php.net/manual/en/function.stream-get-meta-data.php
         * @param string|null $key Specific metadata to retrieve.
         * @return array|mixed|null Returns an associative array if no key is
         *     provided. Returns a specific key value if a key is provided and the
         *     value is found, or null if the key is not found.
         */
        public function getMetadata(?string $key = null);
    }
}
namespace WordPress\AiClientDependencies\Nyholm\Psr7 {
    /**
     * @internal
     */
    trait StreamTrait
    {
        public function __toString(): string
        {
        }
    }
    /**
     * @author Michael Dowling and contributors to guzzlehttp/psr7
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     * @author Martijn van der Ven <martijn@vanderven.se>
     *
     * @final This class should never be extended. See https://github.com/Nyholm/psr7/blob/master/doc/final.md
     */
    class Stream implements \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface
    {
        use \WordPress\AiClientDependencies\Nyholm\Psr7\StreamTrait;
        /**
         * @param resource $body
         */
        public function __construct($body)
        {
        }
        /**
         * Creates a new PSR-7 stream.
         *
         * @param string|resource|\WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface $body
         *
         * @throws \InvalidArgumentException
         */
        public static function create($body = ''): \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface
        {
        }
        /**
         * Closes the stream when the destructed.
         */
        public function __destruct()
        {
        }
        public function close(): void
        {
        }
        public function detach()
        {
        }
        public function getSize(): ?int
        {
        }
        public function tell(): int
        {
        }
        public function eof(): bool
        {
        }
        public function isSeekable(): bool
        {
        }
        public function seek($offset, $whence = \SEEK_SET): void
        {
        }
        public function rewind(): void
        {
        }
        public function isWritable(): bool
        {
        }
        public function write($string): int
        {
        }
        public function isReadable(): bool
        {
        }
        public function read($length): string
        {
        }
        public function getContents(): string
        {
        }
        /**
         * @return mixed
         */
        public function getMetadata($key = null)
        {
        }
    }
}
namespace WordPress\AiClientDependencies\Psr\Http\Message {
    /**
     * Value object representing a file uploaded through an HTTP request.
     *
     * Instances of this interface are considered immutable; all methods that
     * might change state MUST be implemented such that they retain the internal
     * state of the current instance and return an instance that contains the
     * changed state.
     */
    interface UploadedFileInterface
    {
        /**
         * Retrieve a stream representing the uploaded file.
         *
         * This method MUST return a StreamInterface instance, representing the
         * uploaded file. The purpose of this method is to allow utilizing native PHP
         * stream functionality to manipulate the file upload, such as
         * stream_copy_to_stream() (though the result will need to be decorated in a
         * native PHP stream wrapper to work with such functions).
         *
         * If the moveTo() method has been called previously, this method MUST raise
         * an exception.
         *
         * @return StreamInterface Stream representation of the uploaded file.
         * @throws \RuntimeException in cases when no stream is available or can be
         *     created.
         */
        public function getStream(): \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface;
        /**
         * Move the uploaded file to a new location.
         *
         * Use this method as an alternative to move_uploaded_file(). This method is
         * guaranteed to work in both SAPI and non-SAPI environments.
         * Implementations must determine which environment they are in, and use the
         * appropriate method (move_uploaded_file(), rename(), or a stream
         * operation) to perform the operation.
         *
         * $targetPath may be an absolute path, or a relative path. If it is a
         * relative path, resolution should be the same as used by PHP's rename()
         * function.
         *
         * The original file or stream MUST be removed on completion.
         *
         * If this method is called more than once, any subsequent calls MUST raise
         * an exception.
         *
         * When used in an SAPI environment where $_FILES is populated, when writing
         * files via moveTo(), is_uploaded_file() and move_uploaded_file() SHOULD be
         * used to ensure permissions and upload status are verified correctly.
         *
         * If you wish to move to a stream, use getStream(), as SAPI operations
         * cannot guarantee writing to stream destinations.
         *
         * @see http://php.net/is_uploaded_file
         * @see http://php.net/move_uploaded_file
         * @param string $targetPath Path to which to move the uploaded file.
         * @throws \InvalidArgumentException if the $targetPath specified is invalid.
         * @throws \RuntimeException on any error during the move operation, or on
         *     the second or subsequent call to the method.
         */
        public function moveTo(string $targetPath): void;
        /**
         * Retrieve the file size.
         *
         * Implementations SHOULD return the value stored in the "size" key of
         * the file in the $_FILES array if available, as PHP calculates this based
         * on the actual size transmitted.
         *
         * @return int|null The file size in bytes or null if unknown.
         */
        public function getSize(): ?int;
        /**
         * Retrieve the error associated with the uploaded file.
         *
         * The return value MUST be one of PHP's UPLOAD_ERR_XXX constants.
         *
         * If the file was uploaded successfully, this method MUST return
         * UPLOAD_ERR_OK.
         *
         * Implementations SHOULD return the value stored in the "error" key of
         * the file in the $_FILES array.
         *
         * @see http://php.net/manual/en/features.file-upload.errors.php
         * @return int One of PHP's UPLOAD_ERR_XXX constants.
         */
        public function getError(): int;
        /**
         * Retrieve the filename sent by the client.
         *
         * Do not trust the value returned by this method. A client could send
         * a malicious filename with the intention to corrupt or hack your
         * application.
         *
         * Implementations SHOULD return the value stored in the "name" key of
         * the file in the $_FILES array.
         *
         * @return string|null The filename sent by the client or null if none
         *     was provided.
         */
        public function getClientFilename(): ?string;
        /**
         * Retrieve the media type sent by the client.
         *
         * Do not trust the value returned by this method. A client could send
         * a malicious media type with the intention to corrupt or hack your
         * application.
         *
         * Implementations SHOULD return the value stored in the "type" key of
         * the file in the $_FILES array.
         *
         * @return string|null The media type sent by the client or null if none
         *     was provided.
         */
        public function getClientMediaType(): ?string;
    }
}
namespace WordPress\AiClientDependencies\Nyholm\Psr7 {
    /**
     * @author Michael Dowling and contributors to guzzlehttp/psr7
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     * @author Martijn van der Ven <martijn@vanderven.se>
     *
     * @final This class should never be extended. See https://github.com/Nyholm/psr7/blob/master/doc/final.md
     */
    class UploadedFile implements \WordPress\AiClientDependencies\Psr\Http\Message\UploadedFileInterface
    {
        /**
         * @param \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface|string|resource $streamOrFile
         * @param int $size
         * @param int $errorStatus
         * @param string|null $clientFilename
         * @param string|null $clientMediaType
         */
        public function __construct($streamOrFile, $size, $errorStatus, $clientFilename = null, $clientMediaType = null)
        {
        }
        public function getStream(): \WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface
        {
        }
        public function moveTo($targetPath): void
        {
        }
        public function getSize(): int
        {
        }
        public function getError(): int
        {
        }
        public function getClientFilename(): ?string
        {
        }
        public function getClientMediaType(): ?string
        {
        }
    }
}
namespace WordPress\AiClientDependencies\Psr\Http\Message {
    /**
     * Value object representing a URI.
     *
     * This interface is meant to represent URIs according to RFC 3986 and to
     * provide methods for most common operations. Additional functionality for
     * working with URIs can be provided on top of the interface or externally.
     * Its primary use is for HTTP requests, but may also be used in other
     * contexts.
     *
     * Instances of this interface are considered immutable; all methods that
     * might change state MUST be implemented such that they retain the internal
     * state of the current instance and return an instance that contains the
     * changed state.
     *
     * Typically the Host header will be also be present in the request message.
     * For server-side requests, the scheme will typically be discoverable in the
     * server parameters.
     *
     * @link http://tools.ietf.org/html/rfc3986 (the URI specification)
     */
    interface UriInterface
    {
        /**
         * Retrieve the scheme component of the URI.
         *
         * If no scheme is present, this method MUST return an empty string.
         *
         * The value returned MUST be normalized to lowercase, per RFC 3986
         * Section 3.1.
         *
         * The trailing ":" character is not part of the scheme and MUST NOT be
         * added.
         *
         * @see https://tools.ietf.org/html/rfc3986#section-3.1
         * @return string The URI scheme.
         */
        public function getScheme(): string;
        /**
         * Retrieve the authority component of the URI.
         *
         * If no authority information is present, this method MUST return an empty
         * string.
         *
         * The authority syntax of the URI is:
         *
         * <pre>
         * [user-info@]host[:port]
         * </pre>
         *
         * If the port component is not set or is the standard port for the current
         * scheme, it SHOULD NOT be included.
         *
         * @see https://tools.ietf.org/html/rfc3986#section-3.2
         * @return string The URI authority, in "[user-info@]host[:port]" format.
         */
        public function getAuthority(): string;
        /**
         * Retrieve the user information component of the URI.
         *
         * If no user information is present, this method MUST return an empty
         * string.
         *
         * If a user is present in the URI, this will return that value;
         * additionally, if the password is also present, it will be appended to the
         * user value, with a colon (":") separating the values.
         *
         * The trailing "@" character is not part of the user information and MUST
         * NOT be added.
         *
         * @return string The URI user information, in "username[:password]" format.
         */
        public function getUserInfo(): string;
        /**
         * Retrieve the host component of the URI.
         *
         * If no host is present, this method MUST return an empty string.
         *
         * The value returned MUST be normalized to lowercase, per RFC 3986
         * Section 3.2.2.
         *
         * @see http://tools.ietf.org/html/rfc3986#section-3.2.2
         * @return string The URI host.
         */
        public function getHost(): string;
        /**
         * Retrieve the port component of the URI.
         *
         * If a port is present, and it is non-standard for the current scheme,
         * this method MUST return it as an integer. If the port is the standard port
         * used with the current scheme, this method SHOULD return null.
         *
         * If no port is present, and no scheme is present, this method MUST return
         * a null value.
         *
         * If no port is present, but a scheme is present, this method MAY return
         * the standard port for that scheme, but SHOULD return null.
         *
         * @return null|int The URI port.
         */
        public function getPort(): ?int;
        /**
         * Retrieve the path component of the URI.
         *
         * The path can either be empty or absolute (starting with a slash) or
         * rootless (not starting with a slash). Implementations MUST support all
         * three syntaxes.
         *
         * Normally, the empty path "" and absolute path "/" are considered equal as
         * defined in RFC 7230 Section 2.7.3. But this method MUST NOT automatically
         * do this normalization because in contexts with a trimmed base path, e.g.
         * the front controller, this difference becomes significant. It's the task
         * of the user to handle both "" and "/".
         *
         * The value returned MUST be percent-encoded, but MUST NOT double-encode
         * any characters. To determine what characters to encode, please refer to
         * RFC 3986, Sections 2 and 3.3.
         *
         * As an example, if the value should include a slash ("/") not intended as
         * delimiter between path segments, that value MUST be passed in encoded
         * form (e.g., "%2F") to the instance.
         *
         * @see https://tools.ietf.org/html/rfc3986#section-2
         * @see https://tools.ietf.org/html/rfc3986#section-3.3
         * @return string The URI path.
         */
        public function getPath(): string;
        /**
         * Retrieve the query string of the URI.
         *
         * If no query string is present, this method MUST return an empty string.
         *
         * The leading "?" character is not part of the query and MUST NOT be
         * added.
         *
         * The value returned MUST be percent-encoded, but MUST NOT double-encode
         * any characters. To determine what characters to encode, please refer to
         * RFC 3986, Sections 2 and 3.4.
         *
         * As an example, if a value in a key/value pair of the query string should
         * include an ampersand ("&") not intended as a delimiter between values,
         * that value MUST be passed in encoded form (e.g., "%26") to the instance.
         *
         * @see https://tools.ietf.org/html/rfc3986#section-2
         * @see https://tools.ietf.org/html/rfc3986#section-3.4
         * @return string The URI query string.
         */
        public function getQuery(): string;
        /**
         * Retrieve the fragment component of the URI.
         *
         * If no fragment is present, this method MUST return an empty string.
         *
         * The leading "#" character is not part of the fragment and MUST NOT be
         * added.
         *
         * The value returned MUST be percent-encoded, but MUST NOT double-encode
         * any characters. To determine what characters to encode, please refer to
         * RFC 3986, Sections 2 and 3.5.
         *
         * @see https://tools.ietf.org/html/rfc3986#section-2
         * @see https://tools.ietf.org/html/rfc3986#section-3.5
         * @return string The URI fragment.
         */
        public function getFragment(): string;
        /**
         * Return an instance with the specified scheme.
         *
         * This method MUST retain the state of the current instance, and return
         * an instance that contains the specified scheme.
         *
         * Implementations MUST support the schemes "http" and "https" case
         * insensitively, and MAY accommodate other schemes if required.
         *
         * An empty scheme is equivalent to removing the scheme.
         *
         * @param string $scheme The scheme to use with the new instance.
         * @return static A new instance with the specified scheme.
         * @throws \InvalidArgumentException for invalid or unsupported schemes.
         */
        public function withScheme(string $scheme): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface;
        /**
         * Return an instance with the specified user information.
         *
         * This method MUST retain the state of the current instance, and return
         * an instance that contains the specified user information.
         *
         * Password is optional, but the user information MUST include the
         * user; an empty string for the user is equivalent to removing user
         * information.
         *
         * @param string $user The user name to use for authority.
         * @param null|string $password The password associated with $user.
         * @return static A new instance with the specified user information.
         */
        public function withUserInfo(string $user, ?string $password = null): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface;
        /**
         * Return an instance with the specified host.
         *
         * This method MUST retain the state of the current instance, and return
         * an instance that contains the specified host.
         *
         * An empty host value is equivalent to removing the host.
         *
         * @param string $host The hostname to use with the new instance.
         * @return static A new instance with the specified host.
         * @throws \InvalidArgumentException for invalid hostnames.
         */
        public function withHost(string $host): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface;
        /**
         * Return an instance with the specified port.
         *
         * This method MUST retain the state of the current instance, and return
         * an instance that contains the specified port.
         *
         * Implementations MUST raise an exception for ports outside the
         * established TCP and UDP port ranges.
         *
         * A null value provided for the port is equivalent to removing the port
         * information.
         *
         * @param null|int $port The port to use with the new instance; a null value
         *     removes the port information.
         * @return static A new instance with the specified port.
         * @throws \InvalidArgumentException for invalid ports.
         */
        public function withPort(?int $port): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface;
        /**
         * Return an instance with the specified path.
         *
         * This method MUST retain the state of the current instance, and return
         * an instance that contains the specified path.
         *
         * The path can either be empty or absolute (starting with a slash) or
         * rootless (not starting with a slash). Implementations MUST support all
         * three syntaxes.
         *
         * If the path is intended to be domain-relative rather than path relative then
         * it must begin with a slash ("/"). Paths not starting with a slash ("/")
         * are assumed to be relative to some base path known to the application or
         * consumer.
         *
         * Users can provide both encoded and decoded path characters.
         * Implementations ensure the correct encoding as outlined in getPath().
         *
         * @param string $path The path to use with the new instance.
         * @return static A new instance with the specified path.
         * @throws \InvalidArgumentException for invalid paths.
         */
        public function withPath(string $path): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface;
        /**
         * Return an instance with the specified query string.
         *
         * This method MUST retain the state of the current instance, and return
         * an instance that contains the specified query string.
         *
         * Users can provide both encoded and decoded query characters.
         * Implementations ensure the correct encoding as outlined in getQuery().
         *
         * An empty query string value is equivalent to removing the query string.
         *
         * @param string $query The query string to use with the new instance.
         * @return static A new instance with the specified query string.
         * @throws \InvalidArgumentException for invalid query strings.
         */
        public function withQuery(string $query): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface;
        /**
         * Return an instance with the specified URI fragment.
         *
         * This method MUST retain the state of the current instance, and return
         * an instance that contains the specified URI fragment.
         *
         * Users can provide both encoded and decoded fragment characters.
         * Implementations ensure the correct encoding as outlined in getFragment().
         *
         * An empty fragment value is equivalent to removing the fragment.
         *
         * @param string $fragment The fragment to use with the new instance.
         * @return static A new instance with the specified fragment.
         */
        public function withFragment(string $fragment): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface;
        /**
         * Return the string representation as a URI reference.
         *
         * Depending on which components of the URI are present, the resulting
         * string is either a full URI or relative reference according to RFC 3986,
         * Section 4.1. The method concatenates the various components of the URI,
         * using the appropriate delimiters:
         *
         * - If a scheme is present, it MUST be suffixed by ":".
         * - If an authority is present, it MUST be prefixed by "//".
         * - The path can be concatenated without delimiters. But there are two
         *   cases where the path has to be adjusted to make the URI reference
         *   valid as PHP does not allow to throw an exception in __toString():
         *     - If the path is rootless and an authority is present, the path MUST
         *       be prefixed by "/".
         *     - If the path is starting with more than one "/" and no authority is
         *       present, the starting slashes MUST be reduced to one.
         * - If a query is present, it MUST be prefixed by "?".
         * - If a fragment is present, it MUST be prefixed by "#".
         *
         * @see http://tools.ietf.org/html/rfc3986#section-4.1
         * @return string
         */
        public function __toString(): string;
    }
}
namespace WordPress\AiClientDependencies\Nyholm\Psr7 {
    /**
     * PSR-7 URI implementation.
     *
     * @author Michael Dowling
     * @author Tobias Schultze
     * @author Matthew Weier O'Phinney
     * @author Tobias Nyholm <tobias.nyholm@gmail.com>
     * @author Martijn van der Ven <martijn@vanderven.se>
     *
     * @final This class should never be extended. See https://github.com/Nyholm/psr7/blob/master/doc/final.md
     */
    class Uri implements \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface
    {
        public function __construct(string $uri = '')
        {
        }
        public function __toString(): string
        {
        }
        public function getScheme(): string
        {
        }
        public function getAuthority(): string
        {
        }
        public function getUserInfo(): string
        {
        }
        public function getHost(): string
        {
        }
        public function getPort(): ?int
        {
        }
        public function getPath(): string
        {
        }
        public function getQuery(): string
        {
        }
        public function getFragment(): string
        {
        }
        /**
         * @return static
         */
        public function withScheme($scheme): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface
        {
        }
        /**
         * @return static
         */
        public function withUserInfo($user, $password = null): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface
        {
        }
        /**
         * @return static
         */
        public function withHost($host): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface
        {
        }
        /**
         * @return static
         */
        public function withPort($port): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface
        {
        }
        /**
         * @return static
         */
        public function withPath($path): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface
        {
        }
        /**
         * @return static
         */
        public function withQuery($query): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface
        {
        }
        /**
         * @return static
         */
        public function withFragment($fragment): \WordPress\AiClientDependencies\Psr\Http\Message\UriInterface
        {
        }
    }
}
namespace WordPress\AiClientDependencies\Psr\Http\Client {
    /**
     * Every HTTP client related exception MUST implement this interface.
     */
    interface ClientExceptionInterface extends \Throwable
    {
    }
    /**
     * Thrown when the request cannot be completed because of network issues.
     *
     * There is no response object as this exception is thrown when no response has been received.
     *
     * Example: the target host name can not be resolved or the connection failed.
     */
    interface NetworkExceptionInterface extends \WordPress\AiClientDependencies\Psr\Http\Client\ClientExceptionInterface
    {
        /**
         * Returns the request.
         *
         * The request object MAY be a different object from the one passed to ClientInterface::sendRequest()
         *
         * @return \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface
         */
        public function getRequest(): \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface;
    }
    /**
     * Exception for when a request failed.
     *
     * Examples:
     *      - Request is invalid (e.g. method is missing)
     *      - Runtime request errors (e.g. the body stream is not seekable)
     */
    interface RequestExceptionInterface extends \WordPress\AiClientDependencies\Psr\Http\Client\ClientExceptionInterface
    {
        /**
         * Returns the request.
         *
         * The request object MAY be a different object from the one passed to ClientInterface::sendRequest()
         *
         * @return \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface
         */
        public function getRequest(): \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface;
    }
}
