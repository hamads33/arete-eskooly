<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            foreach (is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules) as $ruleDefinition) {
                [$rule, $argument] = array_pad(explode(':', (string) $ruleDefinition, 2), 2, null);
                if (!$this->passes($rule, $value, $argument)) {
                    $this->errors[$field][] = $this->message($field, $rule, $argument);
                }
            }
        }
        return $this->errors === [];
    }

    public function errors(): array { return $this->errors; }

    private function passes(string $rule, mixed $value, ?string $argument): bool
    {
        if ($rule !== 'required' && ($value === null || $value === '')) return true;
        return match ($rule) {
            'required' => $value !== null && $value !== '',
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'string' => is_string($value),
            'numeric' => is_numeric($value),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'min' => is_numeric($value) ? (float) $value >= (float) $argument : mb_strlen((string) $value) >= (int) $argument,
            'max' => is_numeric($value) ? (float) $value <= (float) $argument : mb_strlen((string) $value) <= (int) $argument,
            'in' => in_array((string) $value, explode(',', (string) $argument), true),
            'date' => strtotime((string) $value) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'file' => is_array($value) && ($value['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK,
            'image' => is_array($value) && ($value['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && @getimagesize((string) $value['tmp_name']) !== false,
            default => throw new \InvalidArgumentException("Unknown validation rule: {$rule}"),
        };
    }

    private function message(string $field, string $rule, ?string $argument): string
    {
        $label = ucfirst(str_replace('_', ' ', $field));
        return match ($rule) {
            'required' => "{$label} is required.",
            'min', 'max' => "{$label} failed the {$rule}:{$argument} rule.",
            default => "{$label} must be a valid {$rule} value.",
        };
    }
}
