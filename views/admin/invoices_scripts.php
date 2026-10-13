<script>
(function () {
    var filters = {
        f_status:   document.getElementById('fStatus'),
        f_currency: document.getElementById('fCurrency')
    };

    var table = new DataTable('#invoicesTable', {
        processing: true,
        serverSide: true,
        ajax: {
            url: window.CENIM.apiUrl + 'datatable.php',
            type: 'POST',
            data: function (d) {
                d.table = 'invoices';
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
            { data: 'number' },
            { data: 'paper_id' },
            { data: 'first_author_name' },
            { data: 'issued_at' },
            { data: 'due_date' },
            { data: 'total', className: 'text-end' },
            { data: 'status' },
            { data: 'actions', orderable: false, searchable: false, className: 'text-end' }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        searchDelay: 400,
        language: {
            search: '',
            searchPlaceholder: 'Search invoice number, paper ID, name…',
            emptyTable: 'No invoice issued yet.',
            zeroRecords: 'No invoice matches your search.',
            info: 'Showing _START_ to _END_ of _TOTAL_ invoices',
            infoEmpty: 'No invoices',
            infoFiltered: '(filtered from _MAX_ total)',
            processing: 'Loading…'
        }
    });

    Object.values(filters).forEach(function (select) {
        if (select) { select.addEventListener('change', function () { table.ajax.reload(); }); }
    });

    document.querySelectorAll('[data-status-filter]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            filters.f_status.value = this.dataset.statusFilter;
            table.ajax.reload();
        });
    });

    // Modal void diisi dari tombol yang diklik.
    document.querySelector('#invoicesTable tbody')?.addEventListener('click', function (event) {
        var button = event.target.closest('.cn-void');
        if (!button) { return; }

        document.getElementById('voidId').value = button.dataset.id;
        document.getElementById('voidNumber').textContent = button.dataset.number;
        document.getElementById('voidReason').value = '';
        new bootstrap.Modal(document.getElementById('voidModal')).show();
    });
})();
</script>
