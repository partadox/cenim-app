<?php
/**
 * Editor template sertifikat: gambar latar, tanda tangan, dan posisi elemen
 * (drag-and-drop, koordinat disimpan dalam persen).
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\CertificateRepo;
use Cenim\Domain\CertificateTemplateRepo;
use Cenim\Domain\FileRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$year      = (int) ($edition['year'] ?? date('Y'));

$type = (string) input('type', CertificateRepo::TYPE_AUTHOR);

if (!in_array($type, CertificateRepo::types(), true)) {
    Response::abort(404, 'Unknown certificate type.');
}

$template = CertificateTemplateRepo::ensure($editionId, $type);

if (is_post()) {
    Csrf::verify();

    $action = (string) input('action', 'settings');

    if ($action === 'layout') {
        $fields = json_decode((string) input('fields_json', '[]'), true);

        if (!is_array($fields) || $fields === []) {
            Response::json(['ok' => false, 'error' => 'Layout data is invalid.'], 422);
        }

        CertificateTemplateRepo::saveFields((int) $template['id'], $fields);

        Response::json(['ok' => true, 'message' => 'Layout saved.']);
    }

    if ($action === 'number_base') {
        $base = trim((string) input('number_base', ''));
        CertificateTemplateRepo::setNumberBase((int) $template['id'], $base);

        $assigned = CertificateRepo::assignMissingNumbers($editionId, CertificateTemplateRepo::find((int) $template['id']) ?? []);
        Audit::log('certificate.number_base', ['meta' => ['type' => $type, 'base' => $base, 'assigned' => $assigned]]);

        Session::flash('success', $base === ''
            ? 'Number base cleared. New certificates will have no number.'
            : 'Number base saved.' . ($assigned > 0 ? ' ' . $assigned . ' existing certificate(s) numbered.' : ''));
        Response::redirect(input('back') === 'template'
            ? admin_url('certificate-template.php?type=' . $type)
            : admin_url('certificates.php'));
    }

    if ($action === 'reset_layout') {
        CertificateTemplateRepo::saveFields((int) $template['id'], CertificateTemplateRepo::defaultFields($type));
        Session::flash('success', 'Layout reset to the default.');
        Response::redirect(admin_url('certificate-template.php?type=' . $type));
    }

    if ($action === 'settings') {
        CertificateTemplateRepo::save((int) $template['id'], [
            'name'        => (string) input('name', ''),
            'page_size'   => (string) input('page_size', 'A4'),
            'orientation' => (string) input('orientation', 'L'),
            'is_active'   => input_bool('is_active'),
        ]);

        foreach (['background' => 'setBackground', 'signature' => 'setSignature'] as $field => $method) {
            $file = $_FILES[$field] ?? null;

            if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            try {
                $fileId = FileRepo::replace(
                    $file,
                    'certificate_' . $field,
                    'certificate_template',
                    (int) $template['id'],
                    $editionId,
                    $year
                );
                CertificateTemplateRepo::$method((int) $template['id'], $fileId);
            } catch (InvalidArgumentException $e) {
                Session::flash('error', ucfirst($field) . ': ' . $e->getMessage());
            }
        }

        if (input('remove_background') !== null) {
            CertificateTemplateRepo::setBackground((int) $template['id'], null);
        }
        if (input('remove_signature') !== null) {
            CertificateTemplateRepo::setSignature((int) $template['id'], null);
        }

        Session::flash('success', 'Template saved.');
        Response::redirect(admin_url('certificate-template.php?type=' . $type));
    }

    Session::flash('error', 'Unknown action.');
    Response::redirect(admin_url('certificate-template.php?type=' . $type));
}

$template = CertificateTemplateRepo::ensure($editionId, $type);

View::render('admin/certificate_template', [
    'title'      => CertificateRepo::label($type) . ' certificate template',
    'subtitle'   => 'Drag the elements to position them — coordinates are saved as page percentages',
    'active'     => 'certificates',
    'edition'    => $edition,
    'type'       => $type,
    'types'      => CertificateRepo::types(),
    'template'   => $template,
    'fields'     => CertificateTemplateRepo::fields($template),
    'background' => $template['background_file_id'] !== null ? FileRepo::find((int) $template['background_file_id']) : null,
    'signature'  => $template['signature_file_id'] !== null ? FileRepo::find((int) $template['signature_file_id']) : null,
    'sample'     => CertificateRepo::byType($editionId, $type)[0] ?? null,
    'scripts'    => View::partial('admin/certificate_template_scripts'),
], 'admin');
