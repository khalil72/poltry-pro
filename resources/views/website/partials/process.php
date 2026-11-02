<ol class="process" data-hatch>
  <?php foreach ($process as $i => $s): ?>
    <li class="process-step" style="--i:<?= (int) $i ?>">
      <span class="process-egg" aria-hidden="true"><span class="yolk"></span><span class="num"><?= $i + 1 ?></span></span>
      <h3><?= e($s['title']) ?></h3>
      <p><?= e($s['summary']) ?></p>
    </li>
  <?php endforeach; ?>
</ol>
