<?php

declare(strict_types=1);

namespace AgentStudio\Application\Mcp;

/**
 * Reads a server-sent events body (text/event-stream) into the JSON messages it carries.
 */
final class SseParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function messages(string $body): array
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $messages = [];

        foreach (preg_split('/\n\n+/', $body) ?: [] as $event) {
            $data = [];
            foreach (explode("\n", $event) as $line) {
                if ($line === '' || $line[0] === ':') {
                    continue;
                }
                if (str_starts_with($line, 'data:')) {
                    $value = substr($line, 5);
                    $data[] = str_starts_with($value, ' ') ? substr($value, 1) : $value;
                }
            }

            if ($data === []) {
                continue;
            }

            $decoded = json_decode(implode("\n", $data), true);
            if (is_array($decoded) && ! array_is_list($decoded)) {
                $messages[] = $decoded;
            }
        }

        return $messages;
    }
}
