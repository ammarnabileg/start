<?php $user = auth_user(); ?>
<?php if ($user): ?>
    </main>
    <footer class="px-6 py-4 text-center text-xs text-gray-400 border-t bg-white">
      <?= e(CRM_APP_NAME) ?> · <?= date('Y') ?>
    </footer>
  </div>
</div>
<?php else: ?>
</div>
<?php endif; ?>
<script src="<?= url('assets/app.js') ?>"></script>
</body>
</html>
