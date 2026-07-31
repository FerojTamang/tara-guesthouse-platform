<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\CmsException;

/** Validates one exact Administrator bulk-settings payload. */
final readonly class CmsSettingsUpdateRequest
{
    /** @param array<string, string> $values */
    private function __construct(private array $values)
    {
    }

    public static function fromInput(mixed $settings): self
    {
        if (!is_array($settings) || $settings === [] || array_is_list($settings) || count($settings) > 100) {
            throw CmsException::invalidInput('The settings payload is invalid.');
        }

        $values = [];

        foreach ($settings as $key => $value) {
            if (
                !is_string($key)
                || preg_match('/^[a-z][a-z0-9]*(?:\.[a-z0-9]+)*$/D', $key) !== 1
                || mb_strlen($key, 'UTF-8') > 150
                || !is_string($value)
                || !mb_check_encoding($value, 'UTF-8')
                || str_contains($value, "\0")
            ) {
                throw CmsException::invalidInput('The settings payload is invalid.');
            }

            $values[$key] = $value;
        }

        ksort($values, SORT_STRING);

        return new self($values);
    }

    /** @return array<string, string> */
    public function values(): array
    {
        return $this->values;
    }
}
