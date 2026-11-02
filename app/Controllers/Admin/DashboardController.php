<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Services\DashboardService;

class DashboardController extends AdminController
{
    private function range(): array
    {
        $valid = fn ($d) => $d && ($x = \DateTime::createFromFormat('Y-m-d', $d)) && $x->format('Y-m-d') === $d;
        $from = Request::query('from');
        $to = Request::query('to');
        $to = $valid($to) ? $to : date('Y-m-d');
        $from = $valid($from) ? $from : date('Y-m-01', strtotime('-5 months'));
        return $from > $to ? [$to, $from] : [$from, $to];
    }

    private function farm(): ?int
    {
        $f = Request::int('farm_id');
        if (!$f) {
            return null;
        }
        Auth::assertFarm($f);
        return $f;
    }

    public function index(): void
    {
        [$from, $to] = $this->range();
        $this->view('admin/dashboard/index', ['title' => 'Dashboard', 'from' => $from, 'to' => $to, 'scripts' => ['vendor/chart/chart.umd.js', 'js/dashboard.js']]);
    }

    public function data(): void
    {
        [$from, $to] = $this->range();
        $farm = $this->farm();
        json_out(['ok' => true, 'stats' => DashboardService::stats($farm, $from, $to), 'charts' => DashboardService::charts($farm, $from, $to), 'currency' => currency()]);
    }
}
