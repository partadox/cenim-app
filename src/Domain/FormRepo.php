<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Field tambahan yang bisa diatur admin (form builder). Jawabannya disimpan
 * sebagai JSON di registrations.extra_json / payments.note-nya masing-masing.
 *
 * UI pengelolaannya dibuat pada Fase 7; di sini sudah lengkap bagian
 * pengambilan, render, dan validasinya sehingga form author langsung mengikuti
 * apa pun yang tersimpan di tabel form_fields.
 */
final class FormRepo
{
    public const FORM_REGISTRATION = 'reg_step1';
    public const FORM_PAYMENT      = 'pay_step2';
    public const FORM_ATTENDANCE   = 'attend_step3';

    public const TYPES = ['text', 'textarea', 'number', 'date', 'select', 'radio', 'checkbox'];

    /** @return array<int,array<string,mixed>> */
    public static function fields(int $editionId, string $formKey): array
    {
        if (!Db::tableExists('form_fields')) {
            return [];
        }

        return Db::all(
            'SELECT * FROM form_fields
             WHERE edition_id = ? AND form_key = ? AND is_visible = 1
             ORDER BY sort, id',
            [$editionId, $formKey]
        );
    }

    /** @return array<int,string> pilihan untuk select/radio */
    public static function options(array $field): array
    {
        $decoded = json_decode((string) ($field['options_json'] ?? '[]'), true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_map(static fn ($v): string => (string) $v, $decoded));
    }

    /**
     * Validasi jawaban field dinamis.
     *
     * @param  array<int,array<string,mixed>> $fields
     * @param  array<string,mixed>            $input  biasanya $_POST['extra']
     * @return array{values:array<string,mixed>, errors:array<string,string>}
     */
    public static function validate(array $fields, array $input): array
    {
        $values = [];
        $errors = [];

        foreach ($fields as $field) {
            $key   = (string) $field['field_key'];
            $label = (string) $field['label'];
            $type  = (string) $field['type'];
            $raw   = $input[$key] ?? null;

            if ($type === 'checkbox') {
                $values[$key] = in_array((string) $raw, ['1', 'on', 'true', 'yes'], true);
                if ((int) $field['is_required'] === 1 && $values[$key] === false) {
                    $errors[$key] = $label . ' must be checked.';
                }
                continue;
            }

            $value = is_array($raw) ? '' : trim((string) ($raw ?? ''));

            if ($value === '') {
                if ((int) $field['is_required'] === 1) {
                    $errors[$key] = $label . ' is required.';
                }
                $values[$key] = null;
                continue;
            }

            switch ($type) {
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[$key] = $label . ' must be a number.';
                    }
                    break;

                case 'date':
                    if (strtotime($value) === false) {
                        $errors[$key] = $label . ' must be a valid date.';
                    }
                    break;

                case 'select':
                case 'radio':
                    $options = self::options($field);
                    if ($options !== [] && !in_array($value, $options, true)) {
                        $errors[$key] = $label . ' has an invalid value.';
                    }
                    break;

                default:
                    if (mb_strlen($value) > 2000) {
                        $errors[$key] = $label . ' is too long (max 2000 characters).';
                    }
            }

            $values[$key] = $value;
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /** Nilai tersimpan (JSON) → array siap dipakai form. */
    public static function decode(?string $json): array
    {
        $decoded = json_decode((string) ($json ?? '{}'), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Label + nilai untuk ditampilkan di panel admin.
     *
     * @param  array<int,array<string,mixed>> $fields
     * @return array<int,array{label:string,value:string}>
     */
    public static function readable(array $fields, array $values): array
    {
        $rows = [];

        foreach ($fields as $field) {
            $key   = (string) $field['field_key'];
            $value = $values[$key] ?? null;

            $rows[] = [
                'label' => (string) $field['label'],
                'value' => match (true) {
                    is_bool($value)            => $value ? 'Yes' : 'No',
                    $value === null || $value === '' => '—',
                    default                    => (string) $value,
                },
            ];
        }

        return $rows;
    }
}
