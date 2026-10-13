<script>
(function () {
    var filters = {
        f_presenter: document.getElementById('fPresenter'),
        f_status:    document.getElementById('fStatus'),
        f_country:   document.getElementById('fCountry'),
        f_batch:     document.getElementById('fBatch')
    };

    var table = new DataTable('#authorsTable', {
        processing: true,
        serverSide: true,
        ajax: {
            url: window.CENIM.apiUrl + 'datatable.php',
            type: 'POST',
            data: function (d) {
                d.table = 'authors';
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
            { data: 'expand', orderable: false, className: 'text-nowrap' },
            { data: 'paper_id' },
            { data: 'first_author_name' },
            { data: 'email' },
            { data: 'paper_title' },
            { data: 'affiliation' },
            { data: 'country' },
            { data: 'total_pages', className: 'text-center' },
            { data: 'is_presenter' },
            { data: 'paper_status' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false, className: 'text-end' }
        ],
        order: [[1, 'asc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        searchDelay: 400,
        language: {
            search: '',
            searchPlaceholder: 'Search paper ID, any author, email, title…',
            emptyTable: 'No authors yet. Use "Import CSV" or "Add author" to start.',
            zeroRecords: 'No author matches your search.',
            info: 'Showing _START_ to _END_ of _TOTAL_ authors',
            infoEmpty: 'No authors',
            infoFiltered: '(filtered from _MAX_ total)',
            processing: 'Loading…'
        }
    });

    Object.values(filters).forEach(function (select) {
        if (select) { select.addEventListener('change', function () { table.ajax.reload(); }); }
    });

    // Expand: tampilkan semua author paper ini (data sudah ikut di respons).
    document.querySelector('#authorsTable tbody')?.addEventListener('click', function (event) {
        var toggle = event.target.closest('.cn-expand');
        if (!toggle) { return; }
        var row = table.row(toggle.closest('tr'));
        var icon = toggle.querySelector('i');
        if (row.child.isShown()) {
            row.child.hide();
            icon.className = 'fa-solid fa-chevron-right me-1';
        } else {
            row.child(row.data().detail, 'cn-coauthors-row').show();
            icon.className = 'fa-solid fa-chevron-down me-1';
        }
    });

    document.querySelector('#authorsTable tbody')?.addEventListener('click', function (event) {
        var button = event.target.closest('.cn-delete');
        if (!button) { return; }

        Swal.fire({
            title: 'Delete this author?',
            html: '<b>' + button.dataset.name + '</b><br><span style="font-size:13px;color:#6b7893">'
                + 'This cannot be undone.</span>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#a3231f',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (!result.isConfirmed) { return; }

            var body = new FormData();
            body.append('id', button.dataset.id);
            body.append('csrf_token', window.CENIM.csrf);

            fetch(window.CENIM.adminUrl + 'author-delete.php', {
                method: 'POST',
                body: body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) { return response.json(); })
                .then(function (json) {
                    if (json.ok) {
                        cnToast(json.message || 'Author deleted.', 'success');
                        table.ajax.reload(null, false);
                    } else {
                        cnToast(json.error || 'Delete failed.', 'error');
                    }
                })
                .catch(function () { cnToast('Delete failed. Please try again.', 'error'); });
        });
    });
})();
</script>
