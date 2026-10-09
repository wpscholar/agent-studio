<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use stdClass;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
use AgentStudio\Contracts\Model\ModelException;
use AgentStudio\Contracts\Model\ModelRequest;
use AgentStudio\Contracts\Model\ModelResponse;
use AgentStudio\Contracts\Model\ToolSpec;
use AgentStudio\Contracts\ModelClient;
use AgentStudio\Domain\Conversation\Message as ConversationMessage;
use AgentStudio\Domain\Conversation\ToolCall;

/**
 * Talks to a model through the WordPress 7.0 AI Client, so agents use whichever
 * provider and credentials the site owner set up under Settings > Connectors.
 * Agent Studio never stores an API key.
 */
final class CoreAiModelClient implements ModelClient
{
    /**
     * JSON Schema keywords whose value is an object. When empty they must be
     * encoded as {} and not [], which PHP arrays cannot express after a round
     * trip through storage, and providers reject [].
     */
    private const OBJECT_SCHEMA_KEYS = ['properties', 'patternProperties', 'definitions', '$defs', 'dependentSchemas'];

    public function generate(ModelRequest $request): ModelResponse
    {
        if (! function_exists('wp_ai_client_prompt')) {
            throw new ModelException('The WordPress AI Client is not available. Agent Studio needs WordPress 7.0 or later.');
        }

        if (function_exists('wp_supports_ai') && ! wp_supports_ai()) {
            throw new ModelException('AI features are turned off for this site.');
        }

        $builder = wp_ai_client_prompt();

        if ($request->provider !== '' && $request->model !== '') {
            // The agent was set to one exact model.
            $builder->using_model_preference([$request->provider, $request->model]);
        } else {
            /**
             * Filters which AI provider agents set to "Automatic" use, for example "anthropic" or "openai".
             *
             * An empty string lets the AI Client choose any connected provider with a suitable model.
             *
             * @param string $provider Provider ID. Default empty.
             */
            $provider = apply_filters('agent_studio_ai_provider', '');
            if (is_string($provider) && $provider !== '') {
                $builder->using_provider($provider);
            }

            if ($request->model !== '') {
                $builder->using_model_preference($request->model);
            }
        }

        $builder->using_system_instruction($request->systemPrompt);
        $builder->using_max_tokens($request->maxTokens);

        $declarations = array_map(fn (ToolSpec $tool): FunctionDeclaration => $this->declare($tool), $request->tools);
        if ($declarations !== []) {
            $builder->using_function_declarations(...$declarations);
        }

        $history = $this->history($request->messages);
        if ($history !== []) {
            $builder->with_history(...$history);
        }

        if (! $builder->is_supported_for_text_generation()) {
            throw new ModelException('No AI provider is ready. Add one under Settings > Connectors.');
        }

        $result = $builder->generate_text_result();

        if (is_wp_error($result)) {
            throw new ModelException($result->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; stored on the run and escaped on display.
        }

        if (! $result instanceof GenerativeAiResult) {
            throw new ModelException('The WordPress AI Client returned an unexpected result.');
        }

        return $this->parse($result);
    }

    private function declare(ToolSpec $tool): FunctionDeclaration
    {
        return new FunctionDeclaration(
            $tool->name,
            $tool->description,
            $tool->inputSchema !== [] ? $this->normalizeSchema($tool->inputSchema) : null
        );
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function normalizeSchema(array $schema): array
    {
        foreach ($schema as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if ($value === [] && in_array($key, self::OBJECT_SCHEMA_KEYS, true)) {
                $schema[$key] = new stdClass();
                continue;
            }

            $schema[$key] = $this->normalizeSchema($value);
        }

        return $schema;
    }

    /**
     * @param list<ConversationMessage> $messages
     *
     * @return list<Message>
     */
    private function history(array $messages): array
    {
        $history = [];

        foreach ($messages as $message) {
            $mapped = $this->mapMessage($message);
            if ($mapped !== null) {
                $history[] = $mapped;
            }
        }

        return $history;
    }

    private function mapMessage(ConversationMessage $message): ?Message
    {
        switch ($message->role) {
            case ConversationMessage::ROLE_USER:
                return $message->content === ''
                    ? null
                    : new Message(MessageRoleEnum::user(), [new MessagePart($message->content)]);

            case ConversationMessage::ROLE_ASSISTANT:
                $parts = [];
                if ($message->content !== '') {
                    $parts[] = new MessagePart($message->content);
                }

                foreach ($message->toolCalls as $call) {
                    // An empty argument map must reach the provider as {} and not [].
                    $args = $call->input === [] ? new stdClass() : $call->input;
                    $parts[] = new MessagePart(new FunctionCall($call->id, $call->name, $args));
                }

                return $parts === [] ? null : new Message(MessageRoleEnum::model(), $parts);

            case ConversationMessage::ROLE_TOOL:
                return new Message(
                    MessageRoleEnum::user(),
                    [new MessagePart(new FunctionResponse($message->toolCallId, $message->toolName, $message->content))]
                );
        }

        return null;
    }

    private function parse(GenerativeAiResult $result): ModelResponse
    {
        $text = '';
        $toolCalls = [];

        foreach ($result->toMessage()->getParts() as $part) {
            $partText = $part->getText();
            if ($partText !== null) {
                $text .= $partText;
            }

            $call = $part->getFunctionCall();
            if ($call !== null) {
                $args = $call->getArgs();
                $toolCalls[] = new ToolCall(
                    (string) ($call->getId() ?? $call->getName()),
                    (string) $call->getName(),
                    is_array($args) ? $args : []
                );
            }
        }

        $usage = $result->getTokenUsage();

        return new ModelResponse($text, $toolCalls, $usage->getPromptTokens(), $usage->getCompletionTokens());
    }
}
