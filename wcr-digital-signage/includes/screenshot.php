<?php
/**
 * Self-Screenshot: wenn ?wcr_screenshot=1 in der URL steht,
 * macht html-to-image die Seite zu einem JPG und startet direkt den Download.
 * html-to-image hat besseren CSS-Support als html2canvas (backdrop-filter etc.)
 */
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {
    if (empty($_GET['wcr_screenshot'])) return;
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js"></script>
<style>
    #wpadminbar, #wpadminbar * { display: none !important; }
    html { margin-top: 0 !important; }
    ::-webkit-scrollbar { display: none; }
</style>
<script>
(function () {
    window.addEventListener('load', function () {
        var adminBar = document.getElementById('wpadminbar');
        if (adminBar) adminBar.remove();
        document.documentElement.style.marginTop = '0';
        document.body.style.marginTop = '0';
        window.scrollTo(0, 0);

        setTimeout(function () {
            var node = document.body;

            htmlToImage.toJpeg(node, {
                quality        : 0.93,
                width          : 1080,
                height         : 1920,
                canvasWidth    : 1080,
                canvasHeight   : 1920,
                pixelRatio     : 1,
                skipAutoScale  : true,
                cacheBust      : true,
                style: {
                    margin   : '0',
                    padding  : '0',
                    overflow : 'hidden',
                },
            })
            .then(function (dataUrl) {
                var date = new Date().toISOString().slice(0, 10);
                var a    = document.createElement('a');
                a.href     = dataUrl;
                a.download = 'oeffnungszeiten_' + date + '.jpg';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);

                setTimeout(function () {
                    if (window.opener) {
                        window.opener.postMessage({ wcr_screenshot_done: true }, '*');
                        setTimeout(function () { window.close(); }, 800);
                    }
                }, 500);
            })
            .catch(function (err) {
                // Fallback: 2. Versuch mit toBlob
                console.warn('toJpeg fehlgeschlagen, versuche toPng:', err);
                htmlToImage.toBlob(node, {
                    width      : 1080,
                    height     : 1920,
                    pixelRatio : 1,
                    cacheBust  : true,
                }).then(function (blob) {
                    var url  = URL.createObjectURL(blob);
                    var date = new Date().toISOString().slice(0, 10);
                    var a    = document.createElement('a');
                    a.href     = url;
                    a.download = 'oeffnungszeiten_' + date + '.png';
                    document.body.appendChild(a);
                    a.click();
                    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
                }).catch(function (e) {
                    alert('✗ Fehler: ' + e);
                });
            });

        }, 3000); // etwas mehr Zeit für Fonts + Blur-Render
    });
}());
</script>
<?php
}, 99);
