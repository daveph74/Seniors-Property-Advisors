<?php

namespace App\Content;

/**
 * Plain text as it will actually be stored.
 *
 * Every write path used to validate first and strip tags afterwards, which let a value pass
 * `required` and then be emptied on the way to the database. "<hr>" as a testimonial name passed
 * validation, sanitised to nothing, and hit a NOT NULL constraint as a 500. Sanitising first means
 * the rules see what will really be saved.
 */
class Text
{
    public static function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return trim(strip_tags($value)) ?: null;
    }

    /** The same, over a set of keys in a request payload. */
    public static function cleanAll(array $input, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $input[$key] = self::clean($input[$key]);
            }
        }

        return $input;
    }
}
