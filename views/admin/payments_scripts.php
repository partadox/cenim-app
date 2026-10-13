<script>
(function () {
    var filters = {
        f_status:   document.getElementById('fStatus'),
        f_currency: document.getElementById('fCurrency')
    };

    var table = new DataTable('#paymentsTable', {
        processing: true,
        serverSide: true,
        ajax: {
            url: window.CENIM.apiUrl + 'datatable.php',
            type: 'POST',
            data: function (d) {
                d.table = 'payments';
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
            { data: 'invoice_number' },
            { data: 'paper_id' },
            { data: 'first_author_name' },
            { data: 'paid_at' },
            { data: 'amount_paid', className: 'text-end' },
            { data: 'difference', className: 'text-end' },
            { data: 'proof', className: 'text-center', orderable: false, searchable: false },
            { data: 'status' },
            { data: 'actions', orderable: false, searchable: false, className: 'text-end' }
        ],
        order: [[3, 'desc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        searchDelay: 400,
        language: {
            search: '',
            searchPlaceholder: 'Search invoice, paper ID, sender…',
            emptyTable: 'No payment submitted yet.',
            zeroRecords: 'No payment matches your search.',
            info: 'Showing _START_ to _END_ of _TOTAL_ payments',
            infoEmpty: 'No payments',
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
})();
</script>
