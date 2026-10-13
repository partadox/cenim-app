<script>
(function () {
    var division = document.getElementById('fDivision');

    var table = new DataTable('#committeeTable', {
        processing: true,
        serverSide: true,
        ajax: {
            url: window.CENIM.apiUrl + 'datatable.php',
            type: 'POST',
            data: function (d) {
                d.table = 'committee';
                d.csrf_token = window.CENIM.csrf;
                d.f_division = division ? division.value : '';
            },
            error: function (xhr) {
                var message = 'Failed to load data.';
                try { message = JSON.parse(xhr.responseText).error || message; } catch (e) {}
                cnToast(message, 'error');
                if (xhr.status === 401) { window.location.href = window.CENIM.adminUrl + 'login.php'; }
            }
        },
        columns: [
            { data: 'sort', className: 'text-center' },
            { data: 'name' },
            { data: 'role' },
            { data: 'division' },
            { data: 'email' },
            { data: 'show_on_landing', className: 'text-center' },
            { data: 'certificate' },
            { data: 'actions', orderable: false, searchable: false, className: 'text-end' }
        ],
        order: [[0, 'asc']],
        pageLength: 50,
        lengthMenu: [25, 50, 100],
        searchDelay: 400,
        language: {
            search: '',
            searchPlaceholder: 'Search name, role, division…',
            emptyTable: 'No committee member yet.',
            zeroRecords: 'No member matches your search.',
            info: 'Showing _START_ to _END_ of _TOTAL_ members',
            infoEmpty: 'No members',
            infoFiltered: '(filtered from _MAX_ total)',
            processing: 'Loading…'
        }
    });

    division?.addEventListener('change', function () { table.ajax.reload(); });

    document.querySelector('#committeeTable tbody')?.addEventListener('click', function (event) {
        var button = event.target.closest('.cn-delete');
        if (!button) { return; }

        Swal.fire({
            title: 'Delete this member?',
            html: '<b>' + button.dataset.name + '</b>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#a3231f'
        }).then(function (result) {
            if (!result.isConfirmed) { return; }

            var body = new FormData();
            body.append('id', button.dataset.id);
            body.append('csrf_token', window.CENIM.csrf);

            fetch(window.CENIM.adminUrl + 'committee-delete.php', {
                method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    cnToast(json.ok ? (json.message || 'Deleted.') : (json.error || 'Delete failed.'),
                            json.ok ? 'success' : 'error');
                    if (json.ok) { table.ajax.reload(null, false); }
                })
                .catch(function () { cnToast('Delete failed.', 'error'); });
        });
    });
})();
</script>
