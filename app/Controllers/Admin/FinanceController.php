<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database as DB;
use App\Core\Request;
use App\Services\FinanceService;

class FinanceController extends AdminController
{
    public function overview(): void
    {
        $valid = fn ($d) => $d && ($x = \DateTime::createFromFormat('Y-m-d', $d)) && $x->format('Y-m-d') === $d;
        $from = $valid(Request::query('from')) ? Request::query('from') : date('Y-01-01');
        $to = $valid(Request::query('to')) ? Request::query('to') : date('Y-m-d');
        $farm = Request::int('farm_id') ?: null;
        if ($farm) {
            Auth::assertFarm($farm);
        }
        $accounts = DB::all("SELECT id, name, account_type, status FROM accounts WHERE deleted_at IS NULL ORDER BY account_type, name");
        foreach ($accounts as &$a) {
            $a['balance'] = FinanceService::accountBalance((int) $a['id']);
        }
        unset($a);
        $this->view('admin/finance/overview', [
            'title' => 'Financial overview', 'from' => $from, 'to' => $to, 'farm' => $farm, 'accounts' => $accounts,
            'pl' => FinanceService::profitAndLoss($from, $to, $farm),
            'receivables' => FinanceService::receivables(), 'payables' => FinanceService::payables(), 'unpaid' => FinanceService::unpaidExpenses(),
            'recent' => DB::all('SELECT f.*, a.name account_name FROM financial_transactions f LEFT JOIN accounts a ON a.id=f.account_id WHERE f.txn_date BETWEEN ? AND ?' . Auth::farmSql('f.farm_id') . ($farm ? ' AND f.farm_id=' . $farm : '') . ' ORDER BY f.txn_date DESC, f.id DESC LIMIT 25', [$from, $to]),
        ]);
    }
}
