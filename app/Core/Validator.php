<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * Lightweight validation layer.
 *
 * Rules syntax: [field => 'required|email|min:8|max:255', ...]
 * Supported rules: required, email, min:N, max:N, numeric, integer, decimal,
 * date, in:a,b,c, nullable, unique:table,column.
 */
final class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $ruleList = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                $this->check($field, $value, $rule, $data);
                if (isset($this->errors[$field])) {
                    break;
                }
            }
        }

        return empty($this->errors);
    }

    private function check(string $field, mixed $value, string $rule, array $data): void
    {
        if ($rule === 'nullable') {
            return;
        }

        if (str_starts_with($rule, 'required')) {
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                $this->errors[$field] = $this->message($field, 'required');
            }
            return;
        }

        // If value empty & nullable not set, skip remaining checks (empty treated valid
        // except when rule is 'required' handled above).
        if ($value === null || $value === '') {
            return;
        }

        if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $this->message($field, 'email');
        } elseif ($rule === 'numeric' && !is_numeric($value)) {
            $this->errors[$field] = $this->message($field, 'numeric');
        } elseif ($rule === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->errors[$field] = $this->message($field, 'integer');
        } elseif ($rule === 'decimal') {
            $v = (string) $value;
            // Optional sign; accepts both 1.00, 1,00 and negative amounts.
            if (!preg_match('/^-?\d+(\.\d{1,4})?$/', $v) && !preg_match('/^-?\d+([.,]\d{1,4})?$/', $v)) {
                $this->errors[$field] = $this->message($field, 'decimal');
            }
        } elseif ($rule === 'date') {
            if (strtotime((string) $value) === false) {
                $this->errors[$field] = $this->message($field, 'date');
            }
        } elseif (str_starts_with($rule, 'min:')) {
            $min = (int) substr($rule, 4);
            if (is_string($value) && mb_strlen($value) < $min) {
                $this->errors[$field] = $this->message($field, 'min', ['min' => $min]);
            }
        } elseif (str_starts_with($rule, 'max:')) {
            $max = (int) substr($rule, 4);
            if (is_string($value) && mb_strlen($value) > $max) {
                $this->errors[$field] = $this->message($field, 'max', ['max' => $max]);
            }
        } elseif (str_starts_with($rule, 'in:')) {
            $allowed = explode(',', substr($rule, 3));
            if (!in_array($value, $allowed, true)) {
                $this->errors[$field] = $this->message($field, 'in');
            }
        } elseif (str_starts_with($rule, 'unique:')) {
            $parts = explode(',', substr($rule, 7)); // table[,column][,ignoreId]
            $table = $parts[0];
            $column = $parts[1] ?? $field;
            $ignoreId = $parts[2] ?? null;
            $sql = 'SELECT COUNT(*) AS c FROM ' . DB::quoteIdentifier($table)
                 . ' WHERE ' . DB::quoteIdentifier($column) . ' = :v';
            $params = ['v' => $value];
            if ($ignoreId) {
                $sql .= ' AND id <> :ignore';
                $params['ignore'] = $ignoreId;
            }
            if ((int) DB::scalar($sql, $params) > 0) {
                $this->errors[$field] = $this->message($field, 'unique');
            }
        }
    }

    private function message(string $field, string $rule, array $replace = []): string
    {
        $messages = [
            'required' => __('validation.required'),
            'email'    => __('validation.email'),
            'numeric'  => __('validation.numeric'),
            'integer'  => __('validation.integer'),
            'decimal'  => __('validation.decimal'),
            'date'     => __('validation.date'),
            'min'      => __('validation.min', $replace),
            'max'      => __('validation.max', $replace),
            'in'       => __('validation.in'),
            'unique'   => __('validation.unique'),
        ];
        $label = Translator::instance()->get('fields.' . $field, $field);
        return str_replace(':field', $label, $messages[$rule] ?? 'Invalid value');
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /** Validate and throw a ValidationException (422) on failure. */
    public function validateOrFail(array $data, array $rules): void
    {
        if (!$this->validate($data, $rules)) {
            throw new ValidationException($this->errors());
        }
    }
}
