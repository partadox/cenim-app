<?php
/** @var string $tab */
?>
<?php if ($tab === 'audit'): ?>
<script>
(function () {
    var table = new DataTable('#auditTable', {
        processing: true,
        serverSide: true,
        ajax: {
            url: window.CENIM.apiUrl + 'datatable.php',
            type: 'POST',
            data: function (d) {
                d.table = 'audit_logs';
                d.csrf_token = window.CENIM.csrf;
                d.f_action = document.getElementById('fAction').value;
                d.f_actor = document.getElementById('fActor').value;
            },
            error: function (xhr) {
                var message = 'Failed to load data.';
                try { message = JSON.parse(xhr.responseText).error || message; } catch (e) {}
                cnToast(message, 'error');
                if (xhr.status === 401) { window.location.href = window.CENIM.adminUrl + 'login.php'; }
            }
        },
        columns: [
            { data: 'created_at' },
            { data: 'actor' },
            { data: 'action' },
            { data: 'target' },
            { data: 'ip' },
            { data: 'meta', orderable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [25, 50, 100, 200],
        searchDelay: 400,
        language: {
            search: '',
            searchPlaceholder: 'Search action, actor, IP…',
            emptyTable: 'No audit entry yet.',
            zeroRecords: 'No entry matches your search.',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'No entries',
            infoFiltered: '(filtered from _MAX_ total)',
            processing: 'Loading…'
        }
    });

    ['fAction', 'fActor'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', function () { table.ajax.reload(); });
    });
})();
</script>
<?php elseif ($tab === 'mail'): ?>
<script>
(function () {
    var table = new DataTable('#mailTable', {
        processing: true,
        serverSide: true,
        ajax: {
            url: window.CENIM.apiUrl + 'datatable.php',
            type: 'POST',
            data: function (d) {
                d.table = 'mail_log';
                d.csrf_token = window.CENIM.csrf;
                d.f_status = document.getElementById('fStatus').value;
                d.f_template = document.getElementById('fTemplate').value;
            },
            error: function (xhr) {
                var message = 'Failed to load data.';
                try { message = JSON.parse(xhr.responseText).error || message; } catch (e) {}
                cnToast(message, 'error');
                if (xhr.status === 401) { window.location.href = window.CENIM.adminUrl + 'login.php'; }
            }
        },
        columns: [
            { data: 'created_at' },
            { data: 'to_email' },
            { data: 'subject' },
            { data: 'template' },
            { data: 'status' },
            { data: 'actions', orderable: false, searchable: false, className: 'text-end' }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [25, 50, 100],
        searchDelay: 400,
        language: {
            search: '',
            searchPlaceholder: 'Search recipient, subject, template…',
            emptyTable: 'No email sent yet.',
            zeroRecords: 'No email matches your search.',
            info: 'Showing _START_ to _END_ of _TOTAL_ emails',
            infoEmpty: 'No emails',
            infoFiltered: '(filtered from _MAX_ total)',
            processing: 'Loading…'
        }
    });

    ['fStatus', 'fTemplate'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', function () { table.ajax.reload(); });
    });
})();
</script>
<?php endif; ?>
