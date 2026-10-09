<?php

/**
 * Lightweight declarations for the WordPress 7.0 AI Client facade.
 *
 * Describes `wp_ai_client_prompt()` and the subset of the `WP_AI_Client_Prompt_Builder`
 * that this plugin uses, so PHPStan can verify the model adapter. These exist only for
 * static analysis (loaded via the PHPStan bootstrap). WordPress 7.0 provides the real
 * implementations at runtime, and every call site guards with function_exists().
 */

declare(strict_types=1);

if (! class_exists('WP_AI_Client_Prompt_Builder')) {
    // phpcs:ignore
    class WP_AI_Client_Prompt_Builder
    {
        public function using_system_instruction(string $instruction): self
        {
            return $this;
        }

        public function using_max_tokens(int $maxTokens): self
        {
            return $this;
        }

        /**
         * @param string|array{0: string, 1: string} ...$models Model IDs, or [provider ID, model ID] pairs.
         */
        public function using_model_preference(string|array ...$models): self
        {
            return $this;
        }

        public function using_provider(string $providerId): self
        {
            return $this;
        }

        public function using_function_declarations(object ...$declarations): self
        {
            return $this;
        }

        public function with_history(object ...$messages): self
        {
            return $this;
        }

        public function is_supported_for_text_generation(): bool
        {
            return false;
        }

        /**
         * @return mixed A GenerativeAiResult on success or WP_Error on failure.
         */
        public function generate_text_result()
        {
            return null;
        }
    }
}

if (! function_exists('wp_ai_client_prompt')) {
    /**
     * @param string|null $prompt
     */
    function wp_ai_client_prompt($prompt = null): WP_AI_Client_Prompt_Builder
    {
        return new WP_AI_Client_Prompt_Builder();
    }
}

if (! function_exists('wp_supports_ai')) {
    function wp_supports_ai(): bool
    {
        return true;
    }
}
