<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\View;
use App\Services\Lookup;
use App\Services\PaymentService;
use App\Services\PdfService;
use App\Services\PurchaseService;
use App\Services\SaleService;

/** Sales invoices, purchases and payments: the screens with line items. */
class DocumentController extends AdminController
{
    private function lookups(): array
    {
        $farmStock = [];
        foreach (DB::all('SELECT farm_id, feed_product_id, quantity_kg FROM feed_inventory WHERE 1=1' . Auth::farmSql('farm_id')) as $r) {
            $farmStock[$r['farm_id']][$r['feed_product_id']] = (float) $r['quantity_kg'];
        }
        return [
            'farms' => Lookup::options('farms'),
            'customers' => Lookup::options('customers'),
            'suppliers' => Lookup::options('suppliers'),
            'accounts' => Lookup::options('accounts'),
            'flocks' => Lookup::options('flocks'),
            'batches' => Lookup::options('batches'),
            'feed' => Lookup::options('feed_products'),
            'units' => Lookup::options('units'),
            'medicines' => Lookup::options('medicines'),
            'products' => Lookup::options('products'),
            'stockProducts' => Lookup::options('stock_products'),
            'eggStock' => array_column(DB::all('SELECT farm_id, quantity FROM egg_inventory WHERE 1=1' . Auth::farmSql('farm_id')), 'quantity', 'farm_id'),
            'feedStock' => $farmStock,
        ];
    }

    private function scoped(string $table, int $id): array
    {
        $row = DB::one("SELECT * FROM `$table` WHERE id=?" . Auth::farmSql('farm_id'), [$id]);
        return $row ?? throw new NotFoundException();
    }

    // ------------------------------------------------------------------ Sales
    public function saleCreate(): void
    {
        $this->view('admin/sales/form', ['title' => 'New invoice', 'sale' => null, 'items' => [], 'lk' => $this->lookups()]);
    }

    public function saleEdit(string $id): void
    {
        $sale = $this->scoped('sales', (int) $id);
        if ($sale['status'] !== 'draft') {
            throw new BusinessRuleException('Only draft invoices can be edited.');
        }
        $items = DB::all('SELECT * FROM sale_items WHERE sale_id=?', [$sale['id']]);
        $this->view('admin/sales/form', ['title' => 'Edit ' . $sale['invoice_no'], 'sale' => $sale, 'items' => $items, 'lk' => $this->lookups()]);
    }

    public function saleStore(?string $id = null): void
    {
        $id = $id ? (int) $id : null;
        $lines = array_values((array) ($_POST['items'] ?? []));
        $confirm = Request::input('save_as') === 'confirm';
        $saleId = DB::transaction(function () use ($id, $lines, $confirm) {
            $saleId = SaleService::saveDraft($_POST, $lines, $id);
            if ($confirm) {
                SaleService::confirm($saleId);
                $this->takeInitialPayment('sale', $saleId);
            }
            return $saleId;
        });
        flash('success', $confirm ? 'Invoice confirmed and stock updated.' : 'Invoice saved as a draft. Stock has not changed.');
        redirect("admin/sales/$saleId");
    }

    public function saleShow(string $id): void
    {
        $sale = $this->scoped('sales', (int) $id);
        $data = $this->saleData($sale);
        $this->view('admin/sales/show', [...$data, 'title' => $sale['invoice_no'], 'accounts' => Lookup::options('accounts')]);
    }

    private function saleData(array $sale): array
    {
        return [
            'sale' => $sale,
            'items' => DB::all('SELECT * FROM sale_items WHERE sale_id=?', [$sale['id']]),
            'customer' => DB::one('SELECT * FROM customers WHERE id=?', [$sale['customer_id']]),
            'farm' => DB::one('SELECT * FROM farms WHERE id=?', [$sale['farm_id']]),
            'payments' => DB::all('SELECT p.*, a.name account_name FROM payments p JOIN accounts a ON a.id=p.account_id WHERE p.sale_id=? ORDER BY p.id', [$sale['id']]),
        ];
    }

    public function saleConfirm(string $id): void
    {
        $this->scoped('sales', (int) $id);
        SaleService::confirm((int) $id);
        flash('success', 'Invoice confirmed and stock updated.');
        redirect("admin/sales/$id");
    }

    public function salePrint(string $id): void
    {
        $sale = $this->scoped('sales', (int) $id);
        echo View::render('admin/sales/invoice', [...$this->saleData($sale), 'print' => true]);
    }

    public function salePdf(string $id): void
    {
        $sale = $this->scoped('sales', (int) $id);
        PdfService::stream(View::render('admin/sales/invoice', [...$this->saleData($sale), 'print' => false]), $sale['invoice_no'] . '.pdf');
    }

    // ------------------------------------------------------------------ Purchases
    public function purchaseCreate(): void
    {
        $this->view('admin/purchases/form', ['title' => 'New purchase', 'purchase' => null, 'items' => [], 'lk' => $this->lookups()]);
    }

    public function purchaseEdit(string $id): void
    {
        $p = $this->scoped('purchases', (int) $id);
        if ($p['status'] !== 'draft') {
            throw new BusinessRuleException('Only draft purchases can be edited.');
        }
        $items = DB::all('SELECT * FROM purchase_items WHERE purchase_id=?', [$p['id']]);
        $this->view('admin/purchases/form', ['title' => 'Edit ' . $p['purchase_no'], 'purchase' => $p, 'items' => $items, 'lk' => $this->lookups()]);
    }

    public function purchaseStore(?string $id = null): void
    {
        $id = $id ? (int) $id : null;
        $lines = array_values((array) ($_POST['items'] ?? []));
        $confirm = Request::input('save_as') === 'confirm';
        $pid = DB::transaction(function () use ($id, $lines, $confirm) {
            $pid = PurchaseService::saveDraft($_POST, $lines, $id);
            if ($confirm) {
                PurchaseService::confirm($pid);
                $this->takeInitialPayment('purchase', $pid);
            }
            return $pid;
        });
        flash('success', $confirm ? 'Purchase confirmed and stock updated.' : 'Saved as an open purchase order. Stock has not changed.');
        redirect("admin/purchases/$pid");
    }

    public function purchaseShow(string $id): void
    {
        $p = $this->scoped('purchases', (int) $id);
        $this->view('admin/purchases/show', [...$this->purchaseData($p), 'title' => $p['purchase_no'], 'accounts' => Lookup::options('accounts')]);
    }

    private function purchaseData(array $p): array
    {
        return [
            'purchase' => $p,
            'items' => DB::all('SELECT i.*, b.batch_code FROM purchase_items i LEFT JOIN batches b ON b.id=i.batch_id WHERE i.purchase_id=?', [$p['id']]),
            'supplier' => DB::one('SELECT * FROM suppliers WHERE id=?', [$p['supplier_id']]),
            'farm' => DB::one('SELECT * FROM farms WHERE id=?', [$p['farm_id']]),
            'payments' => DB::all('SELECT x.*, a.name account_name FROM payments x JOIN accounts a ON a.id=x.account_id WHERE x.purchase_id=? ORDER BY x.id', [$p['id']]),
        ];
    }

    public function purchaseConfirm(string $id): void
    {
        $this->scoped('purchases', (int) $id);
        PurchaseService::confirm((int) $id);
        flash('success', 'Purchase confirmed and stock updated.');
        redirect("admin/purchases/$id");
    }

    public function purchasePrint(string $id): void
    {
        $p = $this->scoped('purchases', (int) $id);
        echo View::render('admin/purchases/print', [...$this->purchaseData($p), 'print' => true]);
    }

    public function purchasePdf(string $id): void
    {
        $p = $this->scoped('purchases', (int) $id);
        PdfService::stream(View::render('admin/purchases/print', [...$this->purchaseData($p), 'print' => false]), $p['purchase_no'] . '.pdf');
    }

    // ------------------------------------------------------------------ Payments
    private function takeInitialPayment(string $type, int $id): void
    {
        $amount = (float) Request::input('paid_amount', 0);
        if ($amount > 0) {
            if (!Request::input('account_id')) {
                throw new BusinessRuleException('Choose the account for the amount paid.');
            }
            PaymentService::record([
                'doc_type' => $type, 'doc_id' => $id, 'amount' => $amount, 'account_id' => (int) Request::input('account_id'),
                'payment_date' => Request::input('sale_date') ?: Request::input('purchase_date') ?: date('Y-m-d'),
                'method' => $this->method(), 'reference_no' => null,
            ]);
        }
    }

    private function method(): string
    {
        $m = (string) Request::input('method', 'cash');
        return in_array($m, ['cash', 'bank_transfer', 'cheque', 'card', 'mobile_wallet'], true) ? $m : 'cash';
    }

    public function recordPayment(): void
    {
        $type = (string) Request::input('doc_type');
        $docId = Request::int('doc_id');
        $date = (string) Request::input('payment_date') ?: date('Y-m-d');
        if (!\DateTime::createFromFormat('Y-m-d', $date)) {
            throw new BusinessRuleException('Enter a valid payment date.');
        }
        PaymentService::record([
            'doc_type' => $type, 'doc_id' => $docId, 'amount' => Request::input('amount'), 'account_id' => Request::int('account_id'),
            'payment_date' => $date, 'method' => $this->method(),
            'reference_no' => mb_substr((string) Request::input('reference_no', ''), 0, 80) ?: null,
            'notes' => mb_substr((string) Request::input('notes', ''), 0, 255) ?: null,
        ]);
        flash('success', 'Payment recorded.');
        $back = match ($type) { 'sale' => "admin/sales/$docId", 'purchase' => "admin/purchases/$docId", default => 'admin/expenses' };
        redirect($back);
    }

    public function receipt(string $id): void
    {
        $x = DB::one('SELECT receipt, farm_id FROM expenses WHERE id=?', [(int) $id]);
        if (!$x || !$x['receipt']) {
            throw new NotFoundException();
        }
        if ($x['farm_id'] !== null) {
            Auth::assertFarm((int) $x['farm_id']);
        }
        $path = BASE_PATH . '/storage/uploads/' . $x['receipt'];
        if (str_contains($x['receipt'], '..') || !is_file($path)) {
            throw new NotFoundException();
        }
        header('Content-Type: ' . (new \finfo(FILEINFO_MIME_TYPE))->file($path));
        header('Content-Disposition: inline; filename="receipt-' . (int) $id . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }
}
