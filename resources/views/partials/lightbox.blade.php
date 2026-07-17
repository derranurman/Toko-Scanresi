-- Reusable image lightbox. Any element with data-lightbox-src opens here. --
<div id="imgLightbox" class="img-lightbox" role="dialog" aria-modal="true" aria-hidden="true">
    <span id="imgLightboxClose" class="img-lightbox__close" role="button" aria-label="Tutup">&times;</span>
    <img id="imgLightboxImg" src="" alt="">
</div>
<style>
    .img-lightbox{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.8);padding:1rem;cursor:zoom-out;}
    .img-lightbox.is-open{display:flex;}
    .img-lightbox img{max-height:90vh;max-width:90vw;object-fit:contain;border-radius:.5rem;box-shadow:0 25px 50px -12px rgba(0,0,0,.6);background:#fff;cursor:default;}
    .img-lightbox__close{position:absolute;top:1rem;right:1.25rem;color:#fff;font-size:2.25rem;line-height:1;cursor:pointer;font-weight:300;user-select:none;}
    [data-lightbox-src]{cursor:zoom-in;}
</style>
<script>
    (function () {
        if (window.__imgLightboxInit) return;
        window.__imgLightboxInit = true;
        document.addEventListener('DOMContentLoaded', function () {
            var overlay = document.getElementById('imgLightbox');
            var img = document.getElementById('imgLightboxImg');
            var closeBtn = document.getElementById('imgLightboxClose');
            if (!overlay || !img) return;
            function open(src, alt) {
                if (!src) return;
                img.src = src;
                img.alt = alt || '';
                overlay.classList.add('is-open');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }
            function close() {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
                img.src = '';
                document.body.style.overflow = '';
            }
            document.addEventListener('click', function (e) {
                var trigger = e.target.closest('[data-lightbox-src]');
                if (trigger) {
                    e.preventDefault();
                    open(trigger.getAttribute('data-lightbox-src'), trigger.getAttribute('data-lightbox-alt'));
                    return;
                }
                if (e.target === overlay || e.target === closeBtn) {
                    close();
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') close();
            });
        });
    })();
</script>
