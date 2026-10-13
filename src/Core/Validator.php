<?php

declare(strict_types=1);

namespace Cenim\Core;

/**
 * Validasi sederhana berbasis string rule.
 *
 *   $v = Validator::make($_POST, [
 *       'email'       => 'required|email|maxlen:190',
 *       'paper_id'    => 'required|maxlen:40',
 *       'extra_pages' => 'int|between:0,20',
 *       'category'    => 'required|in:student,regular',
 *   ], ['email' => 'Email first author']);
 *
 *   if ($v->fails()) { ... $v->errors() ... }
 *   $clean = $v->validated();
 *
 * Rule tersedia: required, email, url, int, numeric, bool, date, digits,
 * min, max, between, minlen, maxlen, len, in, regex, same, nullable.
 */
final class Validator
{
    /** @var array<string,string[]> */
    private array $errors = [];
    /** @var array<string,mixed> */
    private array $validated = [];

    /**
     * @param array<string,mixed>  $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     */
    private function __construct(
        private array $data,
        private array $rules,
        private array $labels = []
    ) {
        $this->run();
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Pesan error pertama untuk satu field. */
    public function error(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /** Semua pesan error dalam satu list. */
    public function messages(): array
    {
        return array_merge(...array_values($this->errors ?: [[]]));
    }

    /** @return array<string,mixed> hanya field yang punya rule */
    public function validated(): array
    {
        return $this->validated;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            $isEmpty = $value === null || $value === '' || $value === [];

            // Field kosong: hanya dicek terhadap rule "required", rule lain dilewati.
            if ($isEmpty) {
                if (in_array('required', $rules, true)) {
                    $this->add($field, 'is required');
                } else {
                    $this->validated[$field] = null;
                }
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'required' || $rule === 'nullable' || $rule === '') {
                    continue;
                }
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $this->check($field, (string) $name, $arg, $value);
            }

            $this->validated[$field] = $value;
        }
    }

    private function check(string $field, string $rule, ?string $arg, mixed $value): void
    {
        $str = is_scalar($value) ? (string) $value : '';

        switch ($rule) {
            case 'email':
                if (filter_var($str, FILTER_VALIDATE_EMAIL) === false) {
                    $this->add($field, 'must be a valid email address');
                }
                break;

            case 'url':
                if (filter_var($str, FILTER_VALIDATE_URL) === false) {
                    $this->add($field, 'must be a valid URL');
                }
                break;

            case 'int':
                if (preg_match('/^-?\d+$/', $str) !== 1) {
                    $this->add($field, 'must be a whole number');
                }
                break;

            case 'numeric':
                if (!is_numeric($str)) {
                    $this->add($field, 'must be a number');
                }
                break;

            case 'bool':
                if (!in_array($str, ['0', '1', 'true', 'false', 'on', 'yes', 'no'], true)) {
                    $this->add($field, 'must be true or false');
                }
                break;

            case 'digits':
                if (preg_match('/^\d+$/', $str) !== 1) {
                    $this->add($field, 'must contain digits only');
                }
                break;

            case 'date':
                if (strtotime($str) === false) {
                    $this->add($field, 'must be a valid date');
                }
                break;

            case 'min':
                if ((float) $str < (float) $arg) {
                    $this->add($field, 'must be at least ' . $arg);
                }
                break;

            case 'max':
                if ((float) $str > (float) $arg) {
                    $this->add($field, 'must not be greater than ' . $arg);
                }
                break;

            case 'between':
                [$lo, $hi] = array_pad(explode(',', (string) $arg, 2), 2, '0');
                if ((float) $str < (float) $lo || (float) $str > (float) $hi) {
                    $this->add($field, "must be between {$lo} and {$hi}");
                }
                break;

            case 'minlen':
                if (mb_strlen($str) < (int) $arg) {
                    $this->add($field, 'must be at least ' . $arg . ' characters');
                }
                break;

            case 'maxlen':
                if (mb_strlen($str) > (int) $arg) {
                    $this->add($field, 'must not exceed ' . $arg . ' characters');
                }
                break;

            case 'len':
                if (mb_strlen($str) !== (int) $arg) {
                    $this->add($field, 'must be exactly ' . $arg . ' characters');
                }
                break;

            case 'in':
                if (!in_array($str, explode(',', (string) $arg), true)) {
                    $this->add($field, 'has an invalid value');
                }
                break;

            case 'regex':
                if ($arg === null || @preg_match($arg, $str) !== 1) {
                    $this->add($field, 'has an invalid format');
                }
                break;

            case 'same':
                if (($this->data[$arg] ?? null) !== $value) {
                    $this->add($field, 'must match ' . $this->label((string) $arg));
                }
                break;

            default:
                Logger::warn('Validator: rule tidak dikenal', ['rule' => $rule, 'field' => $field]);
        }
    }

    private function add(string $field, string $message): void
    {
        $this->errors[$field][] = $this->label($field) . ' ' . $message . '.';
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
}
