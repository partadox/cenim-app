<script>
(function () {
    var filters = {
        f_type:  document.getElementById('fType'),
        f_state: document.getElementById('fState')
    };

    var table = new DataTable('#certificatesTable', {
        processing: true,
        serverSide: true,
        ajax: {
            url: window.CENIM.apiUrl + 'datatable.php',
            type: 'POST',
            data: function (d) {
                d.table = 'certificates';
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
            { data: 'code' },
            { data: 'type' },
            { data: 'recipient_name' },
            { data: 'subject_title' },
            { data: 'issued_at' },
            { data: 'download_count', className: 'text-center' },
            { data: 'state' },
            { data: 'actions', orderable: false, searchable: false, className: 'text-end' }
        ],
        order: [[4, 'desc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        searchDelay: 400,
        language: {
            search: '',
            searchPlaceholder: 'Search code, recipient, subject…',
            emptyTable: 'No certificate issued yet — use “Issue all” above.',
            zeroRecords: 'No certificate matches your search.',
            info: 'Showing _START_ to _END_ of _TOTAL_ certificates',
            infoEmpty: 'No certificates',
            infoFiltered: '(filtered from _MAX_ total)',
            processing: 'Loading…'
        }
    });

    Object.values(filters).forEach(function (select) {
        if (select) { select.addEventListener('change', function () { table.ajax.reload(); }); }
    });

    document.querySelector('#certificatesTable tbody')?.addEventListener('click', function (event) {
        var button = event.target.closest('.cn-revoke');
        if (!button) { return; }
        document.getElementById('revokeId').value = button.dataset.id;
        document.getElementById('revokeCode').textContent = button.dataset.code;
        document.getElementById('revokeReason').value = '';
        new bootstrap.Modal(document.getElementById('revokeModal')).show();
    });

    // ── Pencarian penerima penghargaan ──────────────────────────────────────
    var input = document.getElementById('award_author');
    var hidden = document.getElementById('award_author_id');
    var box = document.getElementById('awardSuggestions');
    var timer = null;

    function clearBox() { box.innerHTML = ''; }

    input?.addEventListener('input', function () {
        hidden.value = '';
        var term = this.value.trim();
        clearTimeout(timer);

        if (term.length < 2) { clearBox(); return; }

        timer = setTimeout(function () {
            var body = new FormData();
            body.append('csrf_token', window.CENIM.csrf);
            body.append('q', term);

            fetch(window.CENIM.apiUrl + 'author-search.php', {
                method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    clearBox();
                    (json.results || []).forEach(function (row) {
                        var item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action py-1';
                        item.style.fontSize = '12.5px';
                        item.textContent = row.paper_id + ' — ' + row.name;
                        item.addEventListener('click', function () {
                            input.value = row.paper_id + ' — ' + row.name;
                            hidden.value = row.id;
                            clearBox();
                        });
                        box.appendChild(item);
                    });
                })
                .catch(function () { clearBox(); });
        }, 250);
    });

    document.addEventListener('click', function (event) {
        if (!box.contains(event.target) && event.target !== input) { clearBox(); }
    });
})();
</script>
