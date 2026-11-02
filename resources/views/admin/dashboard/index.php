<?php
use App\Services\Lookup;

$money = can('finance.view');
?>
<div class="page-head">
  <div><h1 class="page-title">Dashboard</h1><div class="small text-body-secondary">Live figures from your farm records.</div></div>
</div>
<form class="card mb-3" id="dashFilters"><div class="filter-bar border-0">
  <label class="filter-date"><span>From</span><input class="form-control form-control-sm" type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="filter-date"><span>To</span><input class="form-control form-control-sm" type="date" name="to" value="<?= e($to) ?>"></label>
  <select class="form-select form-select-sm filter-input" name="farm_id" aria-label="Farm"><option value="">All farms</option><?php foreach (Lookup::options('farms') as $o): ?><option value="<?= e($o['value']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select>
  <button class="btn btn-sm btn-primary">Apply</button>
  <span class="small text-body-secondary ms-auto" id="dashStatus" role="status"></span>
</div></form>
<div class="stat-grid mb-4" id="statGrid" data-url="<?= e(url('admin/dashboard/data')) ?>" data-money="<?= $money ? '1' : '0' ?>"></div>
<div class="row g-3">
  <?php if ($money): ?>
    <div class="col-lg-6"><div class="card chart-card"><div class="card-header">Monthly revenue</div><div class="card-body"><canvas id="cRevenue" role="img" aria-label="Bar chart of revenue per month"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card chart-card"><div class="card-header">Monthly expenses</div><div class="card-body"><canvas id="cExpenses" role="img" aria-label="Stacked bar chart of operating expenses and direct costs per month"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card chart-card"><div class="card-header">Sales and purchases</div><div class="card-body"><canvas id="cSalesPurchases" role="img" aria-label="Bar chart comparing sales and purchases per month"></canvas></div></div></div>
  <?php endif; ?>
  <div class="col-lg-6"><div class="card chart-card"><div class="card-header">Egg production <span class="chart-sub">per day</span></div><div class="card-body"><canvas id="cEggs" role="img" aria-label="Line chart of eggs collected per day"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card chart-card"><div class="card-header">Bird mortality <span class="chart-sub">birds per month</span></div><div class="card-body"><canvas id="cMortality" role="img" aria-label="Bar chart of dead birds per month"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card chart-card"><div class="card-header">Feed consumption <span class="chart-sub">kg per month</span></div><div class="card-body"><canvas id="cFeed" role="img" aria-label="Bar chart of feed used per month"></canvas></div></div></div>
  <div class="col-12"><div class="card chart-card"><div class="card-header">Batch performance <span class="chart-sub">birds placed and birds now, active batches</span></div><div class="card-body"><canvas id="cBatches" role="img" aria-label="Bar chart comparing initial and current birds for each active batch"></canvas></div></div></div>
</div>
<?php if (can('reports.view') || can('reports_financial.view')): ?><p class="small text-body-secondary mt-3">Want these figures as a table? See <a href="<?= e(url('admin/reports')) ?>">Reports</a>.</p><?php endif; ?>
