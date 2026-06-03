<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class ApiPayload
{
    /**
     * @param  mixed  $data  Response body data that used to be wrapped in metadata.
     * @return array<string, mixed>
     */
    public static function make(mixed $data, string $message, int $statusCode, ?string $path = null): array
    {
        $payload = [
            'message' => $message,
            'statusCode' => $statusCode,
        ];

        foreach (self::normalizeData($data) as $key => $value) {
            if (self::isReservedKey($key)) {
                continue;
            }

            $payload[$key] = $value;
        }

        $payload['path'] = $path ?? request()->getPathInfo();
        $payload['timestamp'] = now()->toISOString();

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeData(mixed $data): array
    {
        if ($data === null) {
            return [];
        }

        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        } elseif ($data instanceof JsonSerializable) {
            $data = $data->jsonSerialize();
        }

        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (! is_array($data)) {
            return ['data' => $data];
        }

        return array_is_list($data) ? ['data' => $data] : $data;
    }

    private static function isReservedKey(int|string $key): bool
    {
        return in_array($key, ['message', 'statusCode', 'path', 'timestamp', 'debug', 'stack'], true);
    }
}
