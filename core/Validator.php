<?php
declare(strict_types=1);

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    private function __construct(array $data, array $rules)
    {
        $this->data  = $data;
        $this->rules = $rules;
        $this->validate();
    }

    public static function make(array $data, array $rules): static
    {
        return new static($data, $rules);
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleStr) {
            $rules = explode('|', $ruleStr);
            $value = $this->data[$field] ?? null;
            foreach ($rules as $rule) {
                [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($field, $value, $ruleName, $param);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule, ?string $param): void
    {
        switch ($rule) {
            case 'required':
                if ($value === null || $value === '') $this->addError($field, "The {$field} field is required.");
                break;
            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) $this->addError($field, "The {$field} must be a valid email.");
                break;
            case 'min':
                if ($value !== null && strlen((string)$value) < (int)$param) $this->addError($field, "The {$field} must be at least {$param} characters.");
                break;
            case 'max':
                if ($value !== null && strlen((string)$value) > (int)$param) $this->addError($field, "The {$field} may not exceed {$param} characters.");
                break;
            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) $this->addError($field, "The {$field} must be numeric.");
                break;
            case 'integer':
                if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) $this->addError($field, "The {$field} must be an integer.");
                break;
            case 'confirmed':
                $confirm = $this->data[$field . '_confirmation'] ?? null;
                if ($value !== $confirm) $this->addError($field, "The {$field} confirmation does not match.");
                break;
            case 'in':
                $options = explode(',', $param ?? '');
                if ($value !== null && $value !== '' && !in_array($value, $options)) $this->addError($field, "The {$field} must be one of: {$param}.");
                break;
            case 'url':
                if ($value && !filter_var($value, FILTER_VALIDATE_URL)) $this->addError($field, "The {$field} must be a valid URL.");
                break;
            case 'unique':
                [$table, $col, $exceptId] = array_pad(explode(',', $param ?? ''), 3, null);
                if ($value && $table && $col) {
                    $db  = Database::getInstance();
                    $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` = ?";
                    $params = [$value];
                    if ($exceptId) { $sql .= " AND id != ?"; $params[] = $exceptId; }
                    if ((int)$db->fetchColumn($sql, $params) > 0) $this->addError($field, "The {$field} has already been taken.");
                }
                break;
            case 'nullable':
                break;
            case 'sometimes':
                break;
            case 'date':
                if ($value && !strtotime($value)) $this->addError($field, "The {$field} must be a valid date.");
                break;
            case 'boolean':
                if ($value !== null && !in_array($value, [true,false,'1','0',1,0,'true','false'], true)) $this->addError($field, "The {$field} must be boolean.");
                break;
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function passes(): bool  { return empty($this->errors); }
    public function fails(): bool   { return !empty($this->errors); }
    public function errors(): array { return $this->errors; }

    public function firstError(string $field = ''): ?string
    {
        if ($field) return $this->errors[$field][0] ?? null;
        foreach ($this->errors as $errs) { return $errs[0] ?? null; }
        return null;
    }
}
