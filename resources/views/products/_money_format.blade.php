{{--
    Formatter ribuan untuk input harga (class .money-input).
    - Saat mengetik: angka diformat dengan titik ribuan (10000 → 10.000).
    - Sebelum submit: titik dihapus supaya server menerima angka murni (10000).
    - Tanpa JS (graceful degradation): nilai mentah tetap valid untuk dikirim.
--}}
<script>
(function () {
    function formatThousands(value) {
        var digits = String(value).replace(/\D/g, '').replace(/^0+(?=\d)/, '');
        if (digits === '') return '';
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var inputs = document.querySelectorAll('.money-input');

        inputs.forEach(function (el) {
            // Format nilai awal saat halaman dimuat.
            el.value = formatThousands(el.value);

            el.addEventListener('input', function () {
                var fromEnd = el.value.length - el.selectionStart;
                el.value = formatThousands(el.value);
                var pos = Math.max(0, el.value.length - fromEnd);
                try { el.setSelectionRange(pos, pos); } catch (e) {}
            });

            var form = el.closest('form');
            if (form && !form.dataset.moneyBound) {
                form.dataset.moneyBound = '1';
                form.addEventListener('submit', function () {
                    form.querySelectorAll('.money-input').forEach(function (m) {
                        m.value = m.value.replace(/\./g, '') || '0';
                    });
                });
            }
        });
    });
})();
</script>
