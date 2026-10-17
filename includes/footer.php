    </div><!-- /.container-fluid -->
</main><!-- /.main-container -->

<footer class="bg-white border-top py-3 mt-auto text-muted small text-center no-print">
    <div class="container-fluid px-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <div>
            &copy; <?= date('Y'); ?> <strong><?= e(APP_NAME); ?></strong> &bull; Trainee Practical Assessment System
        </div>
        <div>
            <span>Version <?= e(APP_VERSION); ?></span> &bull; <span>PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION; ?> & PDO MySQL</span>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS (Includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>



</body>
</html>
