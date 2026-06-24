<?php
namespace App\Core;

/**
 * Lightweight input validator.
 *
 * Supported rules (pipe-separated string or array):
 *   required, email, min:n, max:n, numeric, in:a,b,c,
 *   regex:/pattern/, unique:table.column
 */
class Validator
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * @return array{0:bool,1:array<string,string[]>}
     */
    public function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleset) {
            $ruleList = is_array($ruleset) ? $ruleset : explode('|', $ruleset);
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                $name = $rule;
                $param = null;
                if (strpos($rule, ':') !== false) {
                    [$name, $param] = explode(':', $rule, 2);
                }
                $error = $this->applyRule($field, $value, $name, $param, $data);
                if ($error !== null) {
                    $errors[$field][] = $error;
                }
            }
        }
        return [empty($errors), $errors];
    }

    private function applyRule(string $field, $value, string $rule, ?string $param, array $data): ?string
    {
        $label = ucfirst(str_replace('_', ' ', $field));
        $empty = ($value === null || $value === '' || (is_array($value) && count($value) === 0));

        switch ($rule) {
            case 'required':
                return $empty ? "$label is required." : null;

            case 'email':
                if ($empty) return null;
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "$label must be a valid email address.";

            case 'numeric':
                if ($empty) return null;
                return is_numeric($value) ? null : "$label must be numeric.";

            case 'min':
                if ($empty) return null;
                $len = is_numeric($value) ? (float) $value : mb_strlen((string) $value);
                $cmp = is_numeric($value) ? (float) $value : $len;
                return $cmp >= (float) $param ? null : "$label must be at least $param.";

            case 'max':
                if ($empty) return null;
                $cmp = is_numeric($value) ? (float) $value : mb_strlen((string) $value);
                return $cmp <= (float) $param ? null : "$label must not exceed $param.";

            case 'in':
                if ($empty) return null;
                $allowed = explode(',', (string) $param);
                return in_array((string) $value, $allowed, true) ? null : "$label is invalid.";

            case 'regex':
                if ($empty) return null;
                return @preg_match($param, (string) $value) ? null : "$label format is invalid.";

            case 'unique':
                if ($empty) return null;
                [$table, $column] = array_pad(explode('.', (string) $param), 2, null);
                if ($table && $column) {
                    $row = $this->db->fetch(
                        "SELECT 1 FROM `$table` WHERE `$column` = :v LIMIT 1",
                        [':v' => $value]
                    );
                    return $row ? "$label is already taken." : null;
                }
                return null;

            case 'confirmed':
                $other = $data[$field . '_confirmation'] ?? null;
                return $value === $other ? null : "$label confirmation does not match.";

            default:
                return null;
        }
    }
}
