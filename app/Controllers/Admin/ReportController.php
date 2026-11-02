<?php
namespace App\Controllers\Admin;

use App\Core\NotFoundException;
use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Services\ExportService;
use App\Services\PdfService;
use App\Services\ReportService;

class ReportController extends AdminController
{
    public function index(): void
    {
        $defs = array_filter(ReportService::definitions(), fn ($d) => can($d['perm']));
        if (!$defs) {
            Auth::authorize('reports.view');
        }
        $this->view('admin/reports/index', ['title' => 'Reports', 'defs' => $defs]);
    }

    public function show(string $key): void
    {
        $def = ReportService::definitions()[$key] ?? throw new NotFoundException();
        Auth::authorize($def['perm']);
        $f = ReportService::filters();
        $export = Request::query('export');
        $subtitle = $this->subtitle($f);
        $filename = $key . '-' . date('Ymd');

        if ($export === 'xlsx') {
            ExportService::excel($def['title'], $subtitle, ReportService::run($def, $f, null), "$filename.xlsx");
        }
        if ($export === 'pdf' || $export === 'print') {
            $result = ReportService::run($def, $f, null);
            $html = View::render('admin/reports/print', ['def' => $def, 'result' => $result, 'subtitle' => $subtitle, 'print' => $export === 'print']);
            if ($export === 'print') {
                echo $html;
                return;
            }
            PdfService::stream($html, "$filename.pdf", count($result['columns']) > 6 ? 'landscape' : 'portrait');
        }

        $page = max(1, Request::int('page', 1));
        $result = ReportService::run($def, $f, $page);
        $table = new class($result['count'], $page) {
            public int $perPage = 25;
            public function __construct(public int $total, public int $page) {}
            public function pages(): int { return (int) max(1, ceil($this->total / $this->perPage)); }
        };
        $this->view('admin/reports/show', ['title' => $def['title'], 'key' => $key, 'def' => $def, 'result' => $result, 'f' => $f, 'table' => $table, 'subtitle' => $subtitle]);
    }

    private function subtitle(array $f): string
    {
        $parts = [];
        if ($f['from'] || $f['to']) {
            $parts[] = ($f['from'] ? fdate($f['from']) : 'Start') . ' to ' . ($f['to'] ? fdate($f['to']) : 'today');
        }
        if ($f['farm']) {
            $parts[] = 'Farm: ' . \App\Core\Database::value('SELECT name FROM farms WHERE id=?', [$f['farm']]);
        }
        if ($f['batch']) {
            $parts[] = 'Batch: ' . \App\Core\Database::value('SELECT batch_code FROM batches WHERE id=?', [$f['batch']]);
        }
        if ($f['customer']) {
            $parts[] = 'Customer: ' . \App\Core\Database::value('SELECT name FROM customers WHERE id=?', [$f['customer']]);
        }
        if ($f['supplier']) {
            $parts[] = 'Supplier: ' . \App\Core\Database::value('SELECT name FROM suppliers WHERE id=?', [$f['supplier']]);
        }
        return ($parts ? implode(' · ', $parts) : 'All dates') . ' · Generated ' . date('d M Y H:i');
    }
}
