<?php
declare(strict_types=1);

/**
 * Validator - Rule-based data validation.
 *
 * Supported rules (pipe-separated, e.g. "required|email|max:255"):
 *   required, nullable, email, min:n, max:n, numeric, integer, string,
 *   boolean, confirmed, url, in:a,b,c, not_in:a,b,c, regex:/pattern/,
 *   alpha, alpha_num, alpha_dash, date, same:field, different:field,
 *   unique:table.column[,exceptId], exists:table.column
 */
class Validator
{
    protected array $data;
    protected array $rules;
    protected array $errors = [];
    protected array $messages;

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    /**
     * Convenience static entry point.
     *
     * @return array{valid: bool, errors: array<string, array<int,string>>}
     */
    public static function validate(array $data, array $rules, array $messages = []): array
    {
        $validator = new self($data, $rules, $messages);
        $passed = $validator->passes();

        return [
            'valid'  => $passed,
            'errors' => $validator->errors(),
        ];
    }

    public function passes(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->getValue($field);

            $isNullable = in_array('nullable', $rules, true);
            $isRequired = in_array('required', $rules, true);

            // If nullable and empty, skip remaining rules.
            if ($isNullable && $this->isEmpty($value) && !$isRequired) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }
                [$name, $params] = $this->parseRule($rule);
                $this->applyRule($field, $value, $name, $params);
            }
        }

        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Flatten errors to one message per field.
     */
    public function firstErrors(): array
    {
        $flat = [];
        foreach ($this->errors as $field => $messages) {
            $flat[$field] = $messages[0] ?? '';
        }
        return $flat;
    }

    protected function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            [$name, $paramStr] = explode(':', $rule, 2);
            // For regex, keep the whole pattern (may contain commas/colons).
            if ($name === 'regex') {
                return [$name, [$paramStr]];
            }
            return [$name, explode(',', $paramStr)];
        }
        return [$rule, []];
    }

    protected function applyRule(string $field, mixed $value, string $rule, array $params): void
    {
        switch ($rule) {
            case 'required':
                if ($this->isEmpty($value)) {
                    $this->addError($field, $rule, "The {$this->label($field)} field is required.");
                }
                break;

            case 'email':
                if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, $rule, "The {$this->label($field)} must be a valid email address.");
                }
                break;

            case 'url':
                if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, $rule, "The {$this->label($field)} must be a valid URL.");
                }
                break;

            case 'numeric':
                if (!$this->isEmpty($value) && !is_numeric($value)) {
                    $this->addError($field, $rule, "The {$this->label($field)} must be a number.");
                }
                break;

            case 'integer':
                if (!$this->isEmpty($value) && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, $rule, "The {$this->label($field)} must be an integer.");
                }
                break;

            case 'string':
                if (!$this->isEmpty($value) && !is_string($value)) {
                    $this->addError($field, $rule, "The {$this->label($field)} must be a string.");
                }
                break;

            case 'boolean':
                if (!$this->isEmpty($value) && !in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
                    $this->addError($field, $rule, "The {$this->label($field)} must be true or false.");
                }
                break;

            case 'min':
                $min = (float) ($params[0] ?? 0);
                if (!$this->isEmpty($value)) {
                    if (is_numeric($value) && !is_string($value)) {
                        if ((float) $value < $min) {
                            $this->addError($field, $rule, "The {$this->label($field)} must be at least {$params[0]}.");
                        }
                    } elseif (mb_strlen((string) $value) < $min) {
                        $this->addError($field, $rule, "The {$this->label($field)} must be at least {$params[0]} characters.");
                    }
                }
                break;

            case 'max':
                $max = (float) ($params[0] ?? 0);
                if (!$this->isEmpty($value)) {
                    if (is_numeric($value) && !is_string($value)) {
                        if ((float) $value > $max) {
                            $this->addError($field, $rule, "The {$this->label($field)} may not be greater than {$params[0]}.");
                        }
                    } elseif (mb_strlen((string) $value) > $max) {
                        $this->addError($field, $rule, "The {$this->label($field)} may not be greater than {$params[0]} characters.");
                    }
                }
                break;

            case 'confirmed':
                $confirmation = $this->getValue($field . '_confirmation');
                if ($value !== $confirmation) {
                    $this->addError($field, $rule, "The {$this->label($field)} confirmation does not match.");
                }
                break;

            case 'same':
                $other = $params[0] ?? '';
                if ($value !== $this->getValue($other)) {
                    $this->addError($field, $rule, "The {$this->label($field)} and {$this->label($other)} must match.");
                }
                break;

            case 'different':
                $other = $params[0] ?? '';
                if ($value === $this->getValue($other)) {
                    $this->addError($field, $rule, "The {$this->label($field)} and {$this->label($other)} must be different.");
                }
                break;

            case 'in':
                if (!$this->isEmpty($value) && !in_array((string) $value, $params, true)) {
                    $this->addError($field, $rule, "The selected {$this->label($field)} is invalid.");
                }
                break;

            case 'not_in':
                if (!$this->isEmpty($value) && in_array((string) $value, $params, true)) {
                    $this->addError($field, $rule, "The selected {$this->label($field)} is invalid.");
                }
                break;

            case 'alpha':
                if (!$this->isEmpty($value) && !preg_match('/^[\pL\pM]+$/u', (string) $value)) {
                    $this->addError($field, $rule, "The {$this->label($field)} may only contain letters.");
                }
                break;

            case 'alpha_num':
                if (!$this->isEmpty($value) && !preg_match('/^[\pL\pM\pN]+$/u', (string) $value)) {
                    $this->addError($field, $rule, "The {$this->label($field)} may only contain letters and numbers.");
                }
                break;

            case 'alpha_dash':
                if (!$this->isEmpty($value) && !preg_match('/^[\pL\pM\pN_-]+$/u', (string) $value)) {
                    $this->addError($field, $rule, "The {$this->label($field)} may only contain letters, numbers, dashes and underscores.");
                }
                break;

            case 'regex':
                $pattern = $params[0] ?? '';
                if (!$this->isEmpty($value) && $pattern !== '' && !preg_match($pattern, (string) $value)) {
                    $this->addError($field, $rule, "The {$this->label($field)} format is invalid.");
                }
                break;

            case 'date':
                if (!$this->isEmpty($value) && strtotime((string) $value) === false) {
                    $this->addError($field, $rule, "The {$this->label($field)} is not a valid date.");
                }
                break;

            case 'unique':
                $this->validateUnique($field, $value, $params);
                break;

            case 'exists':
                $this->validateExists($field, $value, $params);
                break;
        }
    }

    protected function validateUnique(string $field, mixed $value, array $params): void
    {
        if ($this->isEmpty($value)) {
            return;
        }
        $target = $params[0] ?? '';
        if (!str_contains($target, '.')) {
            return;
        }
        [$table, $column] = explode('.', $target, 2);
        $exceptId = $params[1] ?? null;

        try {
            $db = Database::getInstance();
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
            $bind = [$value];
            if ($exceptId !== null && $exceptId !== '') {
                $sql .= ' AND `id` != ?';
                $bind[] = $exceptId;
            }
            $count = (int) $db->fetchColumn($sql, $bind);
            if ($count > 0) {
                $this->addError($field, 'unique', "The {$this->label($field)} has already been taken.");
            }
        } catch (\Throwable $e) {
            // If DB unavailable, do not block validation on this rule.
        }
    }

    protected function validateExists(string $field, mixed $value, array $params): void
    {
        if ($this->isEmpty($value)) {
            return;
        }
        $target = $params[0] ?? '';
        if (!str_contains($target, '.')) {
            return;
        }
        [$table, $column] = explode('.', $target, 2);

        try {
            $db = Database::getInstance();
            $count = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?",
                [$value]
            );
            if ($count === 0) {
                $this->addError($field, 'exists', "The selected {$this->label($field)} is invalid.");
            }
        } catch (\Throwable $e) {
            // Ignore when DB is unavailable.
        }
    }

    protected function getValue(string $field): mixed
    {
        // Support dot notation for nested arrays.
        if (str_contains($field, '.')) {
            $segments = explode('.', $field);
            $value = $this->data;
            foreach ($segments as $segment) {
                if (is_array($value) && array_key_exists($segment, $value)) {
                    $value = $value[$segment];
                } else {
                    return null;
                }
            }
            return $value;
        }
        return $this->data[$field] ?? null;
    }

    protected function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            return count($value) === 0;
        }
        return false;
    }

    protected function label(string $field): string
    {
        return str_replace(['_', '.'], ' ', $field);
    }

    protected function addError(string $field, string $rule, string $default): void
    {
        $message = $this->messages["{$field}.{$rule}"] ?? $this->messages[$field] ?? $default;
        $this->errors[$field][] = $message;
    }
}
