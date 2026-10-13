<?php
/**
 * Cek ukuran berkas di browser sebelum dikirim. Batasnya sama dengan yang
 * dipakai server (upload_max_bytes()); input dengan data-max-bytes memakai
 * batas fiturnya sendiri (mis. logo). Total satu kiriman juga dicek terhadap
 * post_max_size, karena PHP membuang seluruh form bila melebihinya.
 */
?>
<script>
(function () {
    var MAX  = <?= (int) upload_max_bytes() ?>;
    var POST = <?= (int) min(ini_bytes('post_max_size'), PHP_INT_MAX) ?>;
    function label(n) { return n >= 1048576 ? (Math.round(n / 104857.6) / 10) + ' MB' : Math.ceil(n / 1024) + ' KB'; }
    function warn(msg) {
        if (window.Swal) { Swal.fire({ icon: 'warning', title: 'File too large', text: msg }); } else { alert(msg); }
    }
    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input || input.type !== 'file' || !input.files) { return; }
        var max = parseInt(input.dataset.maxBytes || MAX, 10);
        var total = 0, big = [];
        Array.prototype.forEach.call(input.files, function (f) { total += f.size; if (f.size > max) { big.push(f.name + ' (' + label(f.size) + ')'); } });
        if (big.length) {
            warn(big.join(', ') + ' — the maximum is ' + label(max) + ' per file. Please compress it or choose a smaller file.');
            input.value = '';
        } else if (total > POST * 0.95) {
            warn('Together these files are ' + label(total) + ', more than the server accepts in one upload (' + label(POST) + '). Please send fewer files at a time.');
            input.value = '';
        }
    });
})();
</script>
