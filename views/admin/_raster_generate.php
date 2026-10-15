<?php
/**
 * Server tanpa ekstensi gd: PNG logo/kop dirender di browser dari SVG yang
 * sama, lalu dikirim bersama form sebagai png[nama berkas] (data URL).
 *
 * Form yang memakai: <form data-raster-svg="url SVG" data-raster-targets='[{name,width,height,vbW,vbH,bg}]'>
 */
?>
<script>
document.querySelectorAll('form[data-raster-svg]').forEach(function (form) {
    form.addEventListener('submit', async function (ev) {
        if (form.dataset.rasterDone === '1') {
            return;
        }
        ev.preventDefault();

        var button = form.querySelector('[type=submit]');
        var label  = button ? button.innerHTML : '';
        if (button) {
            button.disabled  = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Rendering images…';
        }

        try {
            var res = await fetch(form.dataset.rasterSvg, {credentials: 'same-origin', cache: 'no-store'});
            if (!res.ok) {
                throw new Error('SVG could not be loaded (HTTP ' + res.status + ').');
            }
            var url = URL.createObjectURL(new Blob([await res.text()], {type: 'image/svg+xml'}));
            var img = new Image();
            img.src = url;
            await img.decode();
            // Font yang ditanam di SVG kadang baru siap sesaat setelah decode.
            await new Promise(function (resolve) { setTimeout(resolve, 400); });

            JSON.parse(form.dataset.rasterTargets).forEach(function (t) {
                var canvas = document.createElement('canvas');
                canvas.width  = t.width;
                canvas.height = t.height;
                var ctx = canvas.getContext('2d');
                if (t.bg) {
                    ctx.fillStyle = t.bg;
                    ctx.fillRect(0, 0, t.width, t.height);
                }
                // Tinggi penuh; lebar mengikuti viewBox (sisa kanan terpotong, seperti PNG gd).
                ctx.drawImage(img, 0, 0, t.height * t.vbW / t.vbH, t.height);

                var input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'png[' + t.name + ']';
                input.value = canvas.toDataURL('image/png');
                form.appendChild(input);
            });
            URL.revokeObjectURL(url);

            form.dataset.rasterDone = '1';
            form.submit();
        } catch (err) {
            if (button) {
                button.disabled  = false;
                button.innerHTML = label;
            }
            alert('The images could not be rendered in this browser: ' + err.message + '\nTry the latest Chrome, Edge, or Firefox.');
        }
    });
});
</script>
