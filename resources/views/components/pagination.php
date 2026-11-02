<?php if ($table->pages() > 1):
    $p = $table->page; $last = $table->pages();
    $from = max(1, $p - 2); $to = min($last, $p + 2); ?>
<nav class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-top" aria-label="Pages">
  <small class="text-body-secondary">Page <?= $p ?> of <?= $last ?></small>
  <ul class="pagination pagination-sm mb-0">
    <li class="page-item <?= $p <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(list_url(['page' => $p - 1])) ?>">Previous</a></li>
    <?php if ($from > 1): ?><li class="page-item"><a class="page-link" href="<?= e(list_url(['page' => 1])) ?>">1</a></li><?php if ($from > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; endif; ?>
    <?php for ($i = $from; $i <= $to; $i++): ?><li class="page-item <?= $i === $p ? 'active' : '' ?>"><a class="page-link" href="<?= e(list_url(['page' => $i])) ?>"><?= $i ?></a></li><?php endfor; ?>
    <?php if ($to < $last): ?><?php if ($to < $last - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?><li class="page-item"><a class="page-link" href="<?= e(list_url(['page' => $last])) ?>"><?= $last ?></a></li><?php endif; ?>
    <li class="page-item <?= $p >= $last ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(list_url(['page' => $p + 1])) ?>">Next</a></li>
  </ul>
</nav>
<?php endif; ?>
