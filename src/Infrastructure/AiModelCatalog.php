<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use Throwable;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Lists the AI providers connected under Settings → Connectors and their text
 * models, marking which ones can use tools (agents need that to call abilities).
 *
 * Listing models can mean a request to each provider, so results are cached.
 */
final class AiModelCatalog
{
    private const CACHE_KEY = 'agent_studio_model_catalog';
    private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    /**
     * @return list<array{id: string, name: string, models: list<array{id: string, name: string, tools: bool}>}>
     */
    public function providers(): array
    {
        if (! class_exists(AiClient::class)) {
            return [];
        }

        try {
            $registry = AiClient::defaultRegistry();
            $ids = array_values(array_filter(
                $registry->getRegisteredProviderIds(),
                static fn (string $id): bool => $registry->isProviderConfigured($id)
            ));
        } catch (Throwable) {
            return [];
        }

        if ($ids === []) {
            return [];
        }

        sort($ids);
        $key = self::CACHE_KEY . '_' . md5(implode(',', $ids));
        $cached = get_transient($key);
        if (is_array($cached)) {
            /** @var list<array{id: string, name: string, models: list<array{id: string, name: string, tools: bool}>}> $cached */
            return $cached;
        }

        $providers = [];
        foreach ($ids as $id) {
            try {
                $class = $registry->getProviderClassName($id);
                $models = [];
                foreach ($class::modelMetadataDirectory()->listModelMetadata() as $model) {
                    if (self::generatesText($model)) {
                        $models[] = [
                            'id' => $model->getId(),
                            'name' => $model->getName(),
                            'tools' => self::usesTools($model),
                        ];
                    }
                }
            } catch (Throwable) {
                continue;
            }

            // Tool-capable models first, then by name.
            usort(
                $models,
                static fn (array $a, array $b): int => [! $a['tools'], $a['name']] <=> [! $b['tools'], $b['name']]
            );
            $providers[] = ['id' => $id, 'name' => $class::metadata()->getName(), 'models' => $models];
        }

        set_transient($key, $providers, self::CACHE_TTL);

        return $providers;
    }

    private static function generatesText(ModelMetadata $model): bool
    {
        foreach ($model->getSupportedCapabilities() as $capability) {
            if ($capability->equals(CapabilityEnum::textGeneration())) {
                return true;
            }
        }

        return false;
    }

    private static function usesTools(ModelMetadata $model): bool
    {
        foreach ($model->getSupportedOptions() as $option) {
            if ($option->getName()->equals(OptionEnum::functionDeclarations())) {
                return true;
            }
        }

        return false;
    }
}
