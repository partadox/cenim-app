<?php
/**
 * Form builder: field tambahan pada form registrasi (dan pembayaran) author.
 * Jawabannya tersimpan di registrations.extra_json dan tampil di halaman review.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Db;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\FormRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$formKey   = in_array(input('form'), [FormRepo::FORM_PAYMENT, FormRepo::FORM_ATTENDANCE], true)
    ? (string) input('form')
    : FormRepo::FORM_REGISTRATION;

if (is_post()) {
    Csrf::verify();

    $action = (string) input('action', '');
    $id     = input_int('id');

    $data = [
        'label'        => mb_substr(trim((string) input('label', '')), 0, 190),
        'type'         => in_array(input('type', ''), FormRepo::TYPES, true) ? (string) input('type') : 'text',
        'placeholder'  => mb_substr(trim((string) input('placeholder', '')), 0, 190) ?: null,
        'help_text'    => mb_substr(trim((string) input('help_text', '')), 0, 500) ?: null,
        'is_required'  => input_bool('is_required') ? 1 : 0,
        'sort'         => input_int('sort'),
        'is_visible'   => $action === 'create' ? 1 : (input_bool('is_visible') ? 1 : 0),
        'options_json' => null,
        'updated_at'   => now(),
    ];

    $options = array_values(array_filter(array_map(
        'trim',
        explode("\n", (string) input('options', ''))
    ), static fn (string $v): bool => $v !== ''));

    if ($options !== []) {
        $data['options_json'] = json_encode($options, JSON_UNESCAPED_UNICODE);
    }

    if ($action === 'create') {
        $fieldKey = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', (string) input('field_key', '')) ?? '');
        $fieldKey = trim($fieldKey, '_');

        if ($fieldKey === '' || $data['label'] === '') {
            Session::flash('error', 'Field key and label are required.');
            Response::redirect(admin_url('forms.php?form=' . $formKey));
        }

        $exists = Db::value(
            'SELECT 1 FROM form_fields WHERE edition_id = ? AND form_key = ? AND field_key = ?',
            [$editionId, $formKey, $fieldKey]
        );

        if ($exists !== null) {
            Session::flash('error', 'That field key already exists on this form.');
            Response::redirect(admin_url('forms.php?form=' . $formKey));
        }

        $data['edition_id'] = $editionId;
        $data['form_key']   = $formKey;
        $data['field_key']  = $fieldKey;
        $data['created_at'] = now();
        unset($data['updated_at']);

        $newId = Db::insert('form_fields', $data);
        Audit::log('form.field_created', ['target_type' => 'form_field', 'target_id' => $newId, 'meta' => ['form' => $formKey, 'field' => $fieldKey]]);
        Session::flash('success', 'Field "' . $fieldKey . '" added. It appears on the author form immediately.');
    } elseif ($action === 'update') {
        Db::update('form_fields', $data, 'id = :target_id AND edition_id = :edition_id', [
            'target_id'  => $id,
            'edition_id' => $editionId,
        ]);
        Session::flash('success', 'Field updated.');
    } elseif ($action === 'delete') {
        $field = Db::one('SELECT * FROM form_fields WHERE id = ? AND edition_id = ?', [$id, $editionId]);

        if ($field !== null) {
            Db::delete('form_fields', 'id = ?', [$id]);
            Audit::log('form.field_deleted', ['target_type' => 'form_field', 'target_id' => $id, 'meta' => ['field' => $field['field_key']]]);
            Session::flash('success', 'Field deleted. Answers already given stay in the registration record.');
        }
    }

    Response::redirect(admin_url('forms.php?form=' . $formKey));
}

View::render('admin/forms', [
    'title'    => 'Form builder',
    'subtitle' => match ($formKey) {
        FormRepo::FORM_PAYMENT    => 'Extra questions on the payment form',
        FormRepo::FORM_ATTENDANCE => 'Extra questions on the video presentation form',
        default                   => 'Extra questions on the author registration form',
    },
    'active'   => 'forms',
    'edition'  => $edition,
    'formKey'  => $formKey,
    'fields'   => Db::all(
        'SELECT * FROM form_fields WHERE edition_id = ? AND form_key = ? ORDER BY sort, id',
        [$editionId, $formKey]
    ),
], 'admin');
