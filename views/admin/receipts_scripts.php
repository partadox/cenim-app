<script>
(function () {
    new DataTable('#receiptsTable', {
        processing: true,
        serverSide: true,
        ajax: {
            url: window.CENIM.apiUrl + 'datatable.php',
            type: 'POST',
            data: function (d) {
                d.table = 'receipts';
                d.csrf_token = window.CENIM.csrf;
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
            { data: 'invoice_number' },
            { data: 'paper_id' },
            { data: 'first_author_name' },
            { data: 'issued_at' },
            { data: 'amount', className: 'text-end' },
            { data: 'actions', orderable: false, searchable: false, className: 'text-end' }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        searchDelay: 400,
        language: {
            search: '',
            searchPlaceholder: 'Search receipt, invoice, paper ID, name…',
            emptyTable: 'No receipt issued yet.',
            zeroRecords: 'No receipt matches your search.',
            info: 'Showing _START_ to _END_ of _TOTAL_ receipts',
            infoEmpty: 'No receipts',
            infoFiltered: '(filtered from _MAX_ total)',
            processing: 'Loading…'
        }
    });
})();
</script>
