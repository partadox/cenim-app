<script>
(function () {
    var filters = {
        f_status:   document.getElementById('fStatus'),
        f_type:     document.getElementById('fType'),
        f_currency: document.getElementById('fCurrency')
    };

    var table = new DataTable('#registrationsTable', {
        processing: true,
        serverSide: true,
        ajax: {
            url: window.CENIM.apiUrl + 'datatable.php',
            type: 'POST',
            data: function (d) {
                d.table = 'registrations';
                d.csrf_token = window.CENIM.csrf;
                Object.keys(filters).forEach(function (key) {
                    d[key] = filters[key] ? filters[key].value : '';
                });
            },
            error: function (xhr) {
                var message = 'Failed to load data.';
                try { message = JSON.parse(xhr.responseText).error || message; } catch (e) {}
                cnToast(message, 'error');
                if (xhr.status === 401) { window.location.href = window.CENIM.adminUrl + 'login.php'; }
            }
        },
        columns: [
            { data: 'paper_id' },
            { data: 'first_author_name' },
            { data: 'category' },
            { data: 'participant_type' },
            { data: 'docs', orderable: false, searchable: false, className: 'text-center' },
            { data: 'total', className: 'text-end' },
            { data: 'status' },
            { data: 'submitted_at' },
            { data: 'admin_note', orderable: false },
            { data: 'actions', orderable: false, searchable: false, className: 'text-end' }
        ],
        order: [[7, 'desc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        searchDelay: 400,
        language: {
            search: '',
            searchPlaceholder: 'Search paper ID, name, email, note…',
            emptyTable: 'No registration yet.',
            zeroRecords: 'No registration matches your search.',
            info: 'Showing _START_ to _END_ of _TOTAL_ registrations',
            infoEmpty: 'No registrations',
            infoFiltered: '(filtered from _MAX_ total)',
            processing: 'Loading…'
        }
    });

    Object.values(filters).forEach(function (select) {
        if (select) { select.addEventListener('change', function () { table.ajax.reload(); }); }
    });

    // Keterangan internal admin (tidak terlihat author).
    document.querySelector('#registrationsTable tbody')?.addEventListener('click', function (event) {
        var button = event.target.closest('.cn-note');
        if (!button) { return; }

        Swal.fire({
            title: 'Note for ' + button.dataset.paper,
            input: 'textarea',
            inputValue: button.dataset.note || '',
            inputPlaceholder: 'e.g. Paid through the committee, host paper, waiting for the student card',
            inputAttributes: { maxlength: 1000, rows: 4 },
            html: '<span style="font-size:12.5px;color:#6b7893">Internal note, not shown to the author. Leave empty to remove it.</span>',
            showCancelButton: true,
            confirmButtonText: 'Save',
            confirmButtonColor: '#003981',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (!result.isConfirmed) { return; }
            var body = new FormData();
            body.append('id', button.dataset.id);
            body.append('note', result.value || '');
            body.append('csrf_token', window.CENIM.csrf);

            fetch(window.CENIM.adminUrl + 'registration-note.php', {
                method: 'POST',
                body: body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) { return response.json(); })
                .then(function (json) {
                    cnToast(json.ok ? json.message : (json.error || 'Failed to save the note.'), json.ok ? 'success' : 'error');
                    if (json.ok) { table.ajax.reload(null, false); }
                })
                .catch(function () { cnToast('Failed to save the note. Please try again.', 'error'); });
        });
    });

    // Kartu ringkasan berfungsi sebagai filter cepat.
    document.querySelectorAll('[data-status-filter]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            filters.f_status.value = this.dataset.statusFilter;
            table.ajax.reload();
        });
    });
})();
</script>
