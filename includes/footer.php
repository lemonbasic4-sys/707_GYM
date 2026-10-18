<?php
/**
 * 707 GYM - HTML Footer Include
 * Closes main/app-wrapper, loads Chart.js, initializes Lucide icons, loads app.js.
 */
?>
</main><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<!-- Application scripts -->
<script src="<?= BASE_URL ?>/assets/js/charts.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>

<!-- Initialize Lucide icons AFTER DOM is ready -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>

</body>
</html>
