<?php /* Brands list view */ ?>
<div class="row g-4">
<?php if(empty($brands)): ?>
<div class="col-12 text-center py-5">
    <div style="font-size:3rem">🏢</div>
    <h3>No brands yet</h3>
    <a href="/brands/create" class="btn btn-primary mt-3"><i class="bi bi-plus-circle me-2"></i>Create Brand</a>
</div>
<?php else: ?>
<?php foreach($brands as $b): ?>
<div class="col-md-6 col-xl-4">
    <div class="card h-100">
        <div class="card-body">
            <h5><?= htmlspecialchars($b['name']) ?></h5>
            <p class="text-muted small"><?= htmlspecialchars($b['industry']??'') ?></p>
            <span class="badge bg-secondary"><?= htmlspecialchars($b['user_role']??'') ?></span>
        </div>
        <div class="card-footer d-flex gap-2">
            <a href="/brands/<?= htmlspecialchars($b['slug']) ?>" class="btn btn-sm btn-primary flex-grow-1">Open</a>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<div class="mt-4"><a href="/brands/create" class="btn btn-outline-primary"><i class="bi bi-plus-circle me-2"></i>New Brand</a></div>
