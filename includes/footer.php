<?php
/**
 * 707 GYM - HTML Footer Include
 * Closes main/app-wrapper and initializes application scripts.
 */
?>
</main><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- Application scripts -->
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
