<link rel="stylesheet" href="<?= e(Cenim\Support\CertificateHtml::GOOGLE_FONTS) ?>">
<script>
(function () {
    var canvas = document.getElementById('cnCanvas');
    if (!canvas) { return; }

    var fields  = JSON.parse(document.getElementById('cnFieldsData').textContent || '[]');
    var sample  = JSON.parse(document.getElementById('cnSampleData').textContent || '{}');
    // Harus sama dengan Cenim\Support\CertificateHtml::FONTS.
    var fonts   = <?= json_encode(Cenim\Support\CertificateHtml::FONTS) ?>;
    var pageW   = parseFloat(canvas.dataset.pageW);
    var pageH   = parseFloat(canvas.dataset.pageH);
    var selected = null;

    var editor = {
        wrap:   document.getElementById('cnFieldEditor'),
        empty:  document.getElementById('cnNoSelection'),
        label:  document.getElementById('cnSelectedLabel'),
        text:   document.getElementById('fText'),
        size:   document.getElementById('fSize'),
        width:  document.getElementById('fWidth'),
        align:  document.getElementById('fAlign'),
        color:  document.getElementById('fColor'),
        bold:   document.getElementById('fBold'),
        hidden: document.getElementById('fHidden'),
        font:   document.getElementById('fFont'),
        pos:    document.getElementById('fPos')
    };

    // ── Isi contoh per jenis elemen ─────────────────────────────────────────
    function previewText(field) {
        switch (field.kind) {
            case 'name':      return sample.name || 'Recipient Name';
            case 'affiliation': return sample.affiliation || 'Institut Teknologi Sepuluh Nopember';
            case 'number':    return (field.value ? field.value + ' ' : '') + (sample.number || '0000/XX/2026-001');
            case 'subject':   return sample.subject || 'Paper title';
            case 'date':      return sample.date || '';
            case 'code':      return 'Verification code: ' + (sample.code || '');
            case 'qr':        return 'QR';
            case 'signature': return 'signature';
            default:          return field.value || '(' + field.label + ')';
        }
    }

    function pxPerMm() { return canvas.clientWidth / pageW; }

    /** Ukuran font pratinjau: pt → mm → px. */
    function fontPx(field) { return field.size * 0.3528 * pxPerMm(); }

    function render() {
        canvas.querySelectorAll('.cn-chip').forEach(function (el) { el.remove(); });

        fields.forEach(function (field, index) {
            var chip = document.createElement('div');
            chip.className = 'cn-chip';
            chip.dataset.index = String(index);

            var isBox = field.kind === 'qr' || field.kind === 'signature';

            chip.style.position = 'absolute';
            chip.style.left = field.x + '%';
            chip.style.top = field.y + '%';
            chip.style.cursor = 'move';
            chip.style.userSelect = 'none';
            chip.style.opacity = field.hidden ? '0.35' : '1';
            chip.style.outline = selected === index ? '2px solid #febd06' : '1px dashed rgba(0,57,129,.35)';
            chip.style.padding = '1px 2px';

            if (isBox) {
                var boxPx = field.size * pxPerMm();
                chip.style.width = boxPx + 'px';
                chip.style.height = boxPx * (field.kind === 'signature' ? 0.4 : 1) + 'px';
                chip.style.transform = 'translateX(-50%)';
                chip.style.display = 'grid';
                chip.style.placeItems = 'center';
                chip.style.background = field.kind === 'qr' ? '#111' : 'rgba(0,57,129,.08)';
                chip.style.color = field.kind === 'qr' ? '#fff' : '#003981';
                chip.style.fontSize = '10px';
                chip.textContent = previewText(field);
            } else {
                var widthPx = field.width / 100 * canvas.clientWidth;
                chip.style.width = widthPx + 'px';
                chip.style.transform = field.align === 'C'
                    ? 'translateX(-50%)'
                    : (field.align === 'R' ? 'translateX(-100%)' : 'none');
                chip.style.textAlign = field.align === 'C' ? 'center' : (field.align === 'R' ? 'right' : 'left');
                chip.style.fontSize = fontPx(field) + 'px';
                chip.style.fontWeight = field.bold ? '700' : '400';
                chip.style.fontFamily = fonts[field.font || 'sans'] || fonts.sans;
                chip.style.color = field.color;
                chip.style.lineHeight = '1.15';
                chip.style.whiteSpace = 'pre-wrap';
                chip.textContent = previewText(field);
            }

            canvas.appendChild(chip);
        });

        renderList();
    }

    function renderList() {
        var body = document.getElementById('cnFieldList');
        body.innerHTML = '';

        fields.forEach(function (field, index) {
            var tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            if (selected === index) { tr.style.background = 'rgba(254,189,6,.14)'; }

            tr.innerHTML = '<td>' + field.label + '</td>'
                + '<td class="text-muted">' + field.kind + '</td>'
                + '<td class="text-end text-muted">' + field.x + ' / ' + field.y + '</td>'
                + '<td class="text-end">' + (field.hidden ? '<span class="cn-badge cn-badge-draft">hidden</span>' : '') + '</td>';

            tr.addEventListener('click', function () { select(index); });
            body.appendChild(tr);
        });
    }

    function select(index) {
        selected = index;
        var field = fields[index];

        editor.wrap.style.display = '';
        editor.empty.style.display = 'none';
        editor.label.textContent = field.label + ' (' + field.kind + ')';

        editor.text.value = field.value || '';
        editor.text.disabled = field.kind !== 'text' && field.kind !== 'number';
        editor.size.value = field.size;
        editor.width.value = field.width;
        editor.align.value = field.align;
        editor.color.value = field.color;
        editor.bold.checked = !!field.bold;
        editor.hidden.checked = !!field.hidden;
        editor.font.value = field.font || 'sans';
        editor.pos.textContent = field.x + ' / ' + field.y;

        render();
    }

    // ── Sunting properti ────────────────────────────────────────────────────
    function bind(el, handler, event) {
        el.addEventListener(event || 'input', function () {
            if (selected === null) { return; }
            handler(fields[selected], el);
            render();
        });
    }

    bind(editor.text,   function (f, el) { f.value = el.value; });
    bind(editor.size,   function (f, el) { f.size = Math.max(5, Math.min(60, parseInt(el.value || '12', 10))); });
    bind(editor.width,  function (f, el) { f.width = Math.max(5, Math.min(100, parseFloat(el.value || '60'))); });
    bind(editor.align,  function (f, el) { f.align = el.value; }, 'change');
    bind(editor.color,  function (f, el) { f.color = el.value; });
    bind(editor.bold,   function (f, el) { f.bold = el.checked; }, 'change');
    bind(editor.hidden, function (f, el) { f.hidden = el.checked; }, 'change');
    bind(editor.font,   function (f, el) { f.font = el.value; }, 'change');

    // ── Geser dengan mouse ──────────────────────────────────────────────────
    var dragging = null;

    canvas.addEventListener('mousedown', function (event) {
        var chip = event.target.closest('.cn-chip');
        if (!chip) { return; }

        event.preventDefault();
        var index = parseInt(chip.dataset.index, 10);
        select(index);

        var rect = canvas.getBoundingClientRect();
        dragging = { index: index, rect: rect };
    });

    document.addEventListener('mousemove', function (event) {
        if (!dragging) { return; }

        var field = fields[dragging.index];
        field.x = Math.round(Math.max(0, Math.min(100, (event.clientX - dragging.rect.left) / dragging.rect.width * 100)) * 10) / 10;
        field.y = Math.round(Math.max(0, Math.min(100, (event.clientY - dragging.rect.top) / dragging.rect.height * 100)) * 10) / 10;

        editor.pos.textContent = field.x + ' / ' + field.y;
        render();
    });

    document.addEventListener('mouseup', function () { dragging = null; });

    // ── Geser halus dengan tombol panah ─────────────────────────────────────
    document.addEventListener('keydown', function (event) {
        if (selected === null) { return; }
        if (['INPUT', 'SELECT', 'TEXTAREA'].indexOf(document.activeElement.tagName) >= 0) { return; }

        var step = event.shiftKey ? 1 : 0.2;
        var field = fields[selected];
        var moved = true;

        switch (event.key) {
            case 'ArrowLeft':  field.x = Math.max(0, field.x - step); break;
            case 'ArrowRight': field.x = Math.min(100, field.x + step); break;
            case 'ArrowUp':    field.y = Math.max(0, field.y - step); break;
            case 'ArrowDown':  field.y = Math.min(100, field.y + step); break;
            default: moved = false;
        }

        if (moved) {
            event.preventDefault();
            field.x = Math.round(field.x * 10) / 10;
            field.y = Math.round(field.y * 10) / 10;
            editor.pos.textContent = field.x + ' / ' + field.y;
            render();
        }
    });

    // ── Simpan ──────────────────────────────────────────────────────────────
    document.getElementById('cnSaveLayout').addEventListener('click', function () {
        var button = this;
        button.disabled = true;

        var body = new FormData();
        body.append('csrf_token', window.CENIM.csrf);
        body.append('action', 'layout');
        body.append('fields_json', JSON.stringify(fields));

        fetch(window.location.href, {
            method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                cnToast(json.ok ? (json.message || 'Layout saved.') : (json.error || 'Save failed.'),
                        json.ok ? 'success' : 'error');
            })
            .catch(function () { cnToast('Save failed. Please try again.', 'error'); })
            .finally(function () { button.disabled = false; });
    });

    window.addEventListener('resize', render);
    render();
})();
</script>
