<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validator với thông báo tiếng Việt.
 *
 *   $v->validate($data, [
 *       'email' => 'required|email|max:190|unique:users,email',
 *       'qty'   => 'required|integer|min:1',
 *   ]);  // => [dữ liệu sạch, lỗi]
 *
 * Rule hỗ trợ: required, nullable, string, email, numeric, integer, min, max, in, confirmed,
 * phone (VN), slug, url, unique:table,column[,ignoreId], exists:table,column
 */
final class Validator
{
    private const MESSAGES = [
        'required' => ':field là bắt buộc.',
        'string' => ':field phải là chuỗi.',
        'email' => ':field không đúng định dạng email.',
        'numeric' => ':field phải là số.',
        'integer' => ':field phải là số nguyên.',
        'min.string' => ':field phải có ít nhất :param ký tự.',
        'max.string' => ':field không được vượt quá :param ký tự.',
        'min.numeric' => ':field phải lớn hơn hoặc bằng :param.',
        'max.numeric' => ':field phải nhỏ hơn hoặc bằng :param.',
        'in' => ':field không hợp lệ.',
        'confirmed' => ':field xác nhận không khớp.',
        'phone' => ':field không phải số điện thoại hợp lệ.',
        'slug' => ':field chỉ gồm chữ thường, số và dấu gạch ngang.',
        'url' => ':field không phải URL hợp lệ.',
        'unique' => ':field đã tồn tại.',
        'exists' => ':field không tồn tại.',
        'encoding' => ':field chứa ký tự không hợp lệ (yêu cầu UTF-8).',
    ];

    public function __construct(private ?Database $db = null)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|string[]> $rules
     * @param array<string, string> $labels tên hiển thị của trường
     * @return array{0: array<string, mixed>, 1: array<string, string[]>}
     */
    public function validate(array $data, array $rules, array $labels = []): array
    {
        $clean = [];
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $value = $data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $isEmpty = $value === null || $value === '' || $value === [];
            $isNumeric = in_array('numeric', $fieldRules, true) || in_array('integer', $fieldRules, true);

            if ($isEmpty) {
                if (in_array('required', $fieldRules, true)) {
                    $errors[$field][] = $this->message('required', $label);
                } elseif (array_key_exists($field, $data) || in_array('nullable', $fieldRules, true)) {
                    $clean[$field] = null;
                }
                continue;
            }

            if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                $errors[$field][] = $this->message('encoding', $label);
                continue;
            }

            foreach ($fieldRules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $error = $this->check($name, $param, $value, $data, $field, $isNumeric);
                if ($error !== null) {
                    $errors[$field][] = $this->message($error, $label, (string) $param);
                    break; // mỗi trường chỉ báo lỗi đầu tiên
                }
            }

            if (!isset($errors[$field])) {
                $clean[$field] = match (true) {
                    in_array('integer', $fieldRules, true) => (int) $value,
                    in_array('numeric', $fieldRules, true) => $value + 0,
                    default => $value,
                };
            }
        }

        return [$clean, $errors];
    }

    /** @return string|null key thông báo lỗi, null nếu hợp lệ */
    private function check(string $rule, ?string $param, mixed $value, array $data, string $field, bool $isNumeric): ?string
    {
        switch ($rule) {
            case 'required':
            case 'nullable':
                return null;
            case 'string':
                return is_string($value) ? null : 'string';
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? null : 'email';
            case 'numeric':
                return is_numeric($value) ? null : 'numeric';
            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false ? null : 'integer';
            case 'min':
            case 'max':
                $size = $isNumeric && is_numeric($value) ? $value + 0 : mb_strlen((string) $value);
                $ok = $rule === 'min' ? $size >= (float) $param : $size <= (float) $param;
                return $ok ? null : $rule . ($isNumeric ? '.numeric' : '.string');
            case 'in':
                return in_array((string) $value, explode(',', (string) $param), true) ? null : 'in';
            case 'confirmed':
                return ($data[$field . '_confirmation'] ?? null) === $value ? null : 'confirmed';
            case 'phone':
                return preg_match('/^(0|\+84)(3|5|7|8|9)\d{8}$/', preg_replace('/[\s.\-]/', '', (string) $value) ?? '') ? null : 'phone';
            case 'slug':
                return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value) ? null : 'slug';
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false ? null : 'url';
            case 'unique':
                [$table, $column, $ignoreId] = array_pad(explode(',', (string) $param), 3, null);
                $query = $this->db()->table((string) $table)->where($column ?: $field, $value);
                if ($ignoreId !== null && $ignoreId !== '') {
                    $query->where('id', '!=', (int) $ignoreId);
                }
                return $query->exists() ? 'unique' : null;
            case 'exists':
                [$table, $column] = array_pad(explode(',', (string) $param), 2, null);
                return $this->db()->table((string) $table)->where($column ?: 'id', $value)->exists() ? null : 'exists';
            default:
                throw new \InvalidArgumentException("Rule validate không tồn tại: {$rule}");
        }
    }

    private function db(): Database
    {
        return $this->db ?? throw new \LogicException('Validator cần Database cho rule unique/exists.');
    }

    private function message(string $key, string $label, string $param = ''): string
    {
        return str_replace([':field', ':param'], [$label, $param], self::MESSAGES[$key] ?? ':field không hợp lệ.');
    }
}
