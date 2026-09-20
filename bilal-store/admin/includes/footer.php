    </div><!-- /.app-content -->

    <footer class="app-footer">
      <small>
        &copy; <?= date('Y') ?> <?= e(setting('site_name', APP_NAME)) ?> ·
        Built with PHP &amp; Bootstrap.
      </small>
    </footer>
  </main><!-- /.app-main -->
</div><!-- /.app -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= e(url('assets/js/script.js')) ?>"></script>
</body>
</html>
