<?php /* SociAI OS - create view for brands */ ?>
<div class="p-2">
    <h2><?= htmlspecialchars($pageTitle ?? ucfirst('create')) ?></h2>
    <!-- brands/create view — implement full UI here -->
    <p class="text-muted">This view is ready to be implemented. Data is available from the controller.</p>
    <?php if(APP_DEBUG && !empty($__data)): ?>
    <pre style="font-size:.7rem;color:#8b8b9e;background:rgba(255,255,255,.03);padding:1rem;border-radius:8px;overflow:auto;"><?= htmlspecialchars(print_r($__data??[], true)) ?></pre>
    <?php endif; ?>
</div>
