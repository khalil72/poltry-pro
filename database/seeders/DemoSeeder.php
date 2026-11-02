<?php
use App\Core\Auth;
use App\Core\Database as DB;
use App\Services\BatchService;
use App\Services\ExpenseService;
use App\Services\FinanceService;
use App\Services\OperationsService as Ops;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\VaccinationService;

/**
 * Five months of sample activity, posted through the real services so every stock and money figure agrees.
 * Dates are relative to today. Random numbers use a fixed seed so runs are comparable.
 */
class DemoSeeder
{
    public const DEMO_PASSWORD = 'Demo@12345';
    private array $ids = [];

    public function run(int $adminId): void
    {
        mt_srand(20261101);
        Auth::actAs($adminId);
        DB::transaction(function () {
            $this->master();
            $this->blog();
            $this->timeline();
            $this->extras();
        });
    }

    private function master(): void
    {
        $farmA = DB::insert('farms', ['name' => 'Riverside Layer Farm', 'code' => 'RLF', 'location' => 'Sheikhupura Road, Lahore', 'manager_name' => 'Bilal Ahmad', 'contact_phone' => '+92 300 0000001', 'contact_email' => 'riverside@poultrypro.example', 'capacity' => 12000, 'description' => 'Our layer farm, with two houses producing fresh table eggs every day.', 'image' => 'images/site/layer-house.jpg']);
        $farmB = DB::insert('farms', ['name' => 'Hillview Broiler Farm', 'code' => 'HBF', 'location' => 'Kasur Road, Lahore', 'manager_name' => 'Usman Tariq', 'contact_phone' => '+92 300 0000002', 'contact_email' => 'hillview@poultrypro.example', 'capacity' => 24000, 'description' => 'Three climate-managed broiler sheds, with new flocks placed throughout the year.', 'image' => 'images/site/hero-shed.jpg']);
        $this->ids['farmA'] = $farmA;
        $this->ids['farmB'] = $farmB;
        $sheds = [[$farmA, 'Layer House 1', 'L1', 'layer', 6000], [$farmA, 'Layer House 2', 'L2', 'layer', 6000], [$farmB, 'Broiler Shed 1', 'B1', 'broiler', 8000], [$farmB, 'Broiler Shed 2', 'B2', 'broiler', 8000], [$farmB, 'Broiler Shed 3', 'B3', 'broiler', 8000]];
        foreach ($sheds as [$f, $n, $no, $t, $cap]) {
            $this->ids[$no] = DB::insert('sheds', ['farm_id' => $f, 'name' => $n, 'shed_number' => $no, 'shed_type' => $t, 'max_capacity' => $cap]);
        }
        foreach ([
            ['farm_manager', 'Bilal Ahmad', 'manager@poultrypro.test', [$farmA]], ['accountant', 'Sara Khan', 'accountant@poultrypro.test', []],
            ['farm_staff', 'Imran Ali', 'staff@poultrypro.test', [$farmA]], ['viewer', 'Owner View', 'viewer@poultrypro.test', []],
        ] as [$role, $name, $email, $farms]) {
            $uid = DB::insert('users', ['role_id' => DB::value('SELECT id FROM roles WHERE slug=?', [$role]), 'name' => $name, 'email' => $email, 'password_hash' => password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT)]);
            foreach ($farms as $f) {
                DB::insert('user_farms', ['user_id' => $uid, 'farm_id' => $f]);
            }
        }
        foreach ([
            ['chicks', 'Punjab Hatcheries', '+92 300 0000011', 'Day-old chicks and pullets'], ['feed', 'GreenMill Feeds', '+92 300 0000012', 'Poultry feed'],
            ['meds', 'VetCare Supplies', '+92 300 0000013', 'Vaccines and medicines'], ['equip', 'FarmTech Equipment', '+92 300 0000014', 'Drinkers, feeders and fans'],
        ] as [$k, $n, $ph, $sp]) {
            $this->ids["sup_$k"] = DB::insert('suppliers', ['name' => $n, 'contact_number' => $ph, 'email' => strtolower(str_replace(' ', '', $n)) . '@example.com', 'address' => 'Lahore', 'supplied_products' => $sp]);
        }
        $this->ids['customers'] = [];
        foreach ([['Ahmed Raza', 'Raza Poultry Traders', 'Lahore'], ['Nadia Malik', 'Fresh Mart Stores', 'Lahore'], ['Hassan Iqbal', 'City Bakers', 'Kasur'], ['Fatima Noor', 'Noor Restaurants', 'Lahore'], ['Kamran Shah', 'Shah Live Birds', 'Sheikhupura'], ['Zainab Akhtar', null, 'Lahore']] as $i => [$n, $b, $a]) {
            $this->ids['customers'][] = DB::insert('customers', ['name' => $n, 'business_name' => $b, 'phone' => '+92 300 00001' . str_pad((string) (20 + $i), 2, '0', STR_PAD_LEFT), 'email' => strtolower(str_replace(' ', '.', $n)) . '@example.com', 'address' => $a]);
        }
        $this->ids['cash'] = DB::insert('accounts', ['name' => 'Farm Cash', 'account_type' => 'cash', 'opening_balance' => 1500000]);
        $this->ids['bank'] = DB::insert('accounts', ['name' => 'Business Bank Account', 'account_type' => 'bank', 'account_number' => '0000-1234567', 'opening_balance' => 12000000]);

        $cat = fn ($n) => DB::insert('feed_categories', ['name' => $n]);
        $kg = (int) DB::value("SELECT id FROM units WHERE symbol='kg'");
        $bag = (int) DB::value("SELECT id FROM units WHERE symbol='bag50'");
        $this->ids['kg'] = $kg;
        $this->ids['bag'] = $bag;
        foreach ([['starter', 'Broiler Starter', 'Broiler', 'BS-01', 2000, 72], ['finisher', 'Broiler Finisher', 'Broiler', 'BF-01', 2000, 68], ['layer', 'Layer Mash', 'Layer', 'LM-01', 3000, 62]] as [$k, $n, $c, $sku, $low, $price]) {
            $this->ids["feedcat_$c"] ??= $cat($c . ' feed');
            $this->ids["feed_$k"] = DB::insert('feed_products', ['category_id' => $this->ids["feedcat_$c"], 'supplier_id' => $this->ids['sup_feed'], 'default_unit_id' => $bag, 'name' => $n, 'sku' => $sku, 'low_stock_kg' => $low, 'sale_price_kg' => $price]);
        }
        $vac = DB::insert('medicine_categories', ['name' => 'Vaccines']);
        $med = DB::insert('medicine_categories', ['name' => 'Antibiotics and tonics']);
        foreach ([['nd', 'Newcastle Disease Vaccine', $vac], ['ibd', 'Gumboro (IBD) Vaccine', $vac], ['tonic', 'Vitamin and Electrolyte Tonic', $med], ['abx', 'Respiratory Treatment', $med]] as [$k, $n, $c]) {
            $this->ids["med_$k"] = DB::insert('medicines', ['category_id' => $c, 'supplier_id' => $this->ids['sup_meds'], 'name' => $n, 'unit' => 'dose', 'low_stock_qty' => 2000]);
        }
        DB::insert('vaccinations', ['name' => 'Newcastle first dose', 'disease' => 'Newcastle disease', 'medicine_id' => $this->ids['med_nd'], 'batch_type' => 'broiler', 'age_days' => 7, 'route' => 'Drinking water']);
        DB::insert('vaccinations', ['name' => 'Gumboro', 'disease' => 'Infectious bursal disease', 'medicine_id' => $this->ids['med_ibd'], 'batch_type' => 'broiler', 'age_days' => 14, 'route' => 'Drinking water']);
        DB::insert('vaccinations', ['name' => 'Newcastle booster', 'disease' => 'Newcastle disease', 'medicine_id' => $this->ids['med_nd'], 'batch_type' => 'broiler', 'age_days' => 21, 'route' => 'Drinking water']);
        DB::insert('vaccinations', ['name' => 'Layer Newcastle booster', 'disease' => 'Newcastle disease', 'medicine_id' => $this->ids['med_nd'], 'batch_type' => 'layer', 'age_days' => 140, 'repeat_days' => 60, 'route' => 'Drinking water']);
        $this->ids['employees'] = [];
        foreach ([['EMP-001', $farmA, 'Bilal Ahmad', 'Farm Manager', 85000], ['EMP-002', $farmA, 'Imran Ali', 'Shed Supervisor', 45000], ['EMP-003', $farmA, 'Tahir Mehmood', 'Egg Collector', 32000], ['EMP-004', $farmB, 'Usman Tariq', 'Farm Manager', 85000], ['EMP-005', $farmB, 'Ali Haider', 'Shed Supervisor', 45000], ['EMP-006', $farmB, 'Waqas Javed', 'Feed Operator', 32000]] as [$c, $f, $n, $d, $sal]) {
            $this->ids['employees'][] = ['id' => DB::insert('employees', ['employee_code' => $c, 'farm_id' => $f, 'name' => $n, 'designation' => $d, 'phone' => '+92 300 00002' . substr($c, -2), 'salary' => $sal, 'joining_date' => date('Y-m-d', strtotime('-2 years'))]), 'salary' => $sal];
        }
        foreach ([
            ['Ahmed Raza', 'Raza Poultry Traders', 'The birds arrive even in weight and in good condition. We have not had to chase a delivery in the two years we have bought from them.', 5],
            ['Nadia Malik', 'Fresh Mart Stores', 'Our customers notice how fresh the eggs are. Weekly deliveries come on the day they promise.', 5],
            ['Kamran Shah', 'Shah Live Birds', 'Clear pricing, honest weights, and they tell you early if a batch will be late.', 4],
        ] as [$n, $t, $f, $r]) {
            DB::insert('testimonials', ['customer_name' => $n, 'customer_title' => $t, 'feedback' => $f, 'rating' => $r]);
        }
    }

    private function blog(): void
    {
        $posts = [
            ['Preparing a shed before new chicks arrive', 'farming_tips', 'A clean, warm, dry shed is the cheapest health programme you can buy. Here is what we do before every placement.', 'images/site/chicks-brooder.jpg',
                '<p>The first week sets the tone for the whole batch. Most of what goes wrong later can be traced back to a shed that was not ready.</p><h2>Clean first, then disinfect</h2><p>Remove all old litter, wash down every surface and let the shed dry. Disinfectant does little on a dirty surface, so cleaning comes first.</p><h2>Warm the floor, not just the air</h2><p>Start the brooders well before the chicks arrive so that the litter has warmed through. Chicks lose heat through their feet.</p><ul><li>Check drinker lines and flush them</li><li>Spread fresh, dry litter evenly</li><li>Set out extra feeders for the first days</li></ul><p>Then watch the chicks: if they huddle they are cold, and if they crowd the walls they are too warm.</p>'],
            ['Reading daily mortality without panic', 'bird_health', 'Some losses are normal. The trend matters more than any single day, and that is why we write it down.', 'images/site/hen-closeup.jpg',
                '<p>Every flock loses some birds. What matters is whether today looks different from last week, which you can only tell if you record it each day.</p><h2>Record shed by shed</h2><p>A rise in one shed points to something local, such as a blocked drinker line or poor air. A rise across every shed points to something wider, such as feed or disease.</p><blockquote>A number written down today is the warning you get tomorrow.</blockquote><p>If losses climb for two or three days running, call your veterinarian rather than waiting.</p>'],
            ['Why vaccination timing matters', 'vaccination', 'A vaccine given late, or given badly, may not protect the flock. Follow your veterinarian\'s schedule and keep a record.', 'images/site/chicks-box.jpg',
                '<p>A vaccination programme only works when each dose is given at the right age and handled correctly. Your veterinarian will set a schedule for local disease risks, and it should be written down before the chicks arrive.</p><h2>When giving vaccine in water</h2><ul><li>Keep vaccine cold until the moment it is used</li><li>Use clean water with no disinfectant in the line</li><li>Make sure every bird can reach a drinker</li></ul><p>Record the date, the batch and the lot number. If a problem shows up later, those records are the first thing your veterinarian will ask for.</p>'],
            ['Cutting feed waste without cutting growth', 'feed_nutrition', 'Feed is the largest cost on most poultry farms. Small daily losses add up quickly.', 'images/site/layer-house.jpg',
                '<p>Feed is usually the biggest single cost in raising birds, so waste shows up straight away in your margin.</p><h2>Where feed goes missing</h2><ul><li>Feeders set too low, so birds scratch feed onto the litter</li><li>Feeders filled too full</li><li>Damp or stale feed that birds refuse</li><li>Rodents and wild birds</li></ul><p>Raise feeder height as the birds grow, keep bags dry and off the floor, and weigh what goes out each day. You cannot manage waste you do not measure.</p>'],
            ['What buyers look for in table eggs', 'industry', 'Shops and bakeries care about the same few things: freshness, clean shells and steady supply.', 'images/site/eggs-tray.jpg',
                '<p>Whether you sell to a corner shop or a bakery, buyers tend to ask for the same things.</p><h2>Freshness and clean shells</h2><p>Collect often, store somewhere cool, and sell oldest first. Remove cracked and dirty eggs before packing, because a single bad tray costs more than it saves.</p><h2>Reliable supply</h2><p>A buyer who can count on you each week is worth more than one who pays a little extra once. Keep daily records of what each house produces so you know what you can promise.</p>'],
        ];
        foreach ($posts as $i => [$t, $c, $x, $img, $body]) {
            DB::insert('blog_posts', ['title' => $t, 'slug' => slugify($t), 'category' => $c, 'excerpt' => $x, 'body' => $body, 'image' => $img, 'author_id' => Auth::id(), 'status' => 'published', 'published_at' => date('Y-m-d H:i:s', strtotime('-' . (5 + $i * 16) . ' days'))]);
        }
    }

    private function day(int $ago): string
    {
        return date('Y-m-d', strtotime(sprintf('%+d days', -$ago)));
    }

    private function buy(string $supplier, int $farm, string $date, array $lines, float $paidShare = 1.0): int
    {
        $id = PurchaseService::saveDraft(['supplier_id' => $this->ids["sup_$supplier"], 'farm_id' => $farm, 'purchase_date' => $date, 'supplier_invoice_no' => 'SI-' . mt_rand(10000, 99999)], $lines);
        PurchaseService::confirm($id);
        $total = (float) DB::value('SELECT total_amount FROM purchases WHERE id=?', [$id]);
        if ($paidShare > 0) {
            PaymentService::record(['doc_type' => 'purchase', 'doc_id' => $id, 'amount' => round($total * $paidShare, 2), 'account_id' => $this->ids['bank'], 'payment_date' => $date, 'method' => 'bank_transfer']);
        }
        return $id;
    }

    private function sell(int $customer, int $farm, string $date, array $lines, float $paidShare, string $method = 'cash', float $discount = 0): int
    {
        $id = SaleService::saveDraft(['customer_id' => $customer, 'farm_id' => $farm, 'sale_date' => $date, 'discount' => $discount, 'tax_rate' => 0], $lines);
        SaleService::confirm($id);
        $total = (float) DB::value('SELECT grand_total FROM sales WHERE id=?', [$id]);
        if ($paidShare > 0) {
            PaymentService::record(['doc_type' => 'sale', 'doc_id' => $id, 'amount' => round($total * $paidShare, 2), 'account_id' => $method === 'cash' ? $this->ids['cash'] : $this->ids['bank'], 'payment_date' => $date, 'method' => $method]);
        }
        return $id;
    }

    private function feedStock(int $farm, int $product): float
    {
        return (float) DB::value('SELECT COALESCE(quantity_kg,0) FROM feed_inventory WHERE farm_id=? AND feed_product_id=?', [$farm, $product]);
    }

    private function topUpFeed(int $farm, int $product, float $dailyKg, string $date, float $pricePerBag): void
    {
        if ($this->feedStock($farm, $product) < $dailyKg * 2 + 200) {
            $bags = (int) ceil($dailyKg * 12 / 50) + 10;
            $this->buy('feed', $farm, $date, [['item_type' => 'feed', 'ref_id' => $product, 'unit_id' => $this->ids['bag'], 'quantity' => $bags, 'unit_price' => $pricePerBag + mt_rand(-40, 40)]], 0.85);
        }
    }

    private function timeline(): void
    {
        $A = $this->ids['farmA'];
        $B = $this->ids['farmB'];
        // ago => [name, type, shed, farm, qty, price, age at purchase, breed]
        $plan = [
            150 => ['Layer flock Spring', 'layer', 'L1', $A, 5000, 1150, 126, 'Lohmann Brown'],
            140 => ['Broiler flock 1', 'broiler', 'B1', $B, 6000, 65, 0, 'Ross 308'],
            90 => ['Layer flock Summer', 'layer', 'L2', $A, 4200, 1180, 126, 'Lohmann Brown'],
            85 => ['Broiler flock 2', 'broiler', 'B2', $B, 7000, 70, 0, 'Ross 308'],
            28 => ['Broiler flock 3', 'broiler', 'B3', $B, 7500, 72, 0, 'Cobb 500'],
            12 => ['Broiler flock 4', 'broiler', 'B1', $B, 5000, 72, 0, 'Cobb 500'],
        ];
        $active = [];
        foreach ([$A, $B] as $farm) {
            $this->buy('meds', $farm, $this->day(151), [
                ['item_type' => 'medicine', 'ref_id' => $this->ids['med_nd'], 'quantity' => 60000, 'unit_price' => 2.2, 'lot_no' => 'ND-' . $farm . 'A', 'expiry_date' => $this->day(-300)],
                ['item_type' => 'medicine', 'ref_id' => $this->ids['med_ibd'], 'quantity' => 30000, 'unit_price' => 3.1, 'lot_no' => 'IB-' . $farm . 'A', 'expiry_date' => $this->day(-300)],
                ['item_type' => 'medicine', 'ref_id' => $this->ids['med_tonic'], 'quantity' => 8000, 'unit_price' => 1.4, 'lot_no' => 'TN-' . $farm . 'A', 'expiry_date' => $this->day(-20)],
            ]);
        }
        $this->buy('equip', $B, $this->day(148), [['item_type' => 'equipment', 'description' => 'Tunnel ventilation fans, set of 6', 'quantity' => 1, 'unit_price' => 480000]], 0.5);

        for ($ago = 150; $ago >= 0; $ago--) {
            $date = $this->day($ago);
            if (isset($plan[$ago])) {
                [$n, $t, $shedKey, $farm, $qty, $price, $age, $breed] = $plan[$ago];
                $id = BatchService::create(['name' => $n, 'batch_type' => $t, 'shed_id' => $this->ids[$shedKey], 'breed' => $breed, 'purchase_date' => $date, 'age_at_purchase_days' => $age, 'initial_quantity' => $qty, 'supplier_id' => $this->ids['sup_chicks'], 'purchase_price' => $price]);
                $active[$id] = ['id' => $id, 'type' => $t, 'shed' => $this->ids[$shedKey], 'farm' => $farm, 'start' => $ago, 'age0' => $age];
                PaymentService::record(['doc_type' => 'purchase', 'doc_id' => (int) DB::value('SELECT purchase_id FROM purchase_items WHERE batch_id=? ORDER BY id LIMIT 1', [$id]), 'amount' => round($qty * $price * 0.8, 2), 'account_id' => $this->ids['bank'], 'payment_date' => $date, 'method' => 'bank_transfer']);
            }
            $this->transferDay($ago, $active, $date);
            foreach ($active as $id => $b) {
                $this->dailyFlock($b, $date, $ago);
            }
            $this->sellBroilers($active, $ago, $date);
            $this->vaccinate($date);
            $this->sellEggs($ago, $date);
            $this->monthly($date);
        }
    }

    private function transferDay(int $ago, array $active, string $date): void
    {
        if ($ago === 15) {
            foreach ($active as $b) {
                if ($b['shed'] === $this->ids['B3']) {
                    Ops::transferBirds(['flock' => "{$b['id']}:{$b['shed']}", 'to_shed_id' => $this->ids['B2'], 'quantity' => 800, 'transfer_date' => $date, 'notes' => 'Reducing stocking density']);
                }
            }
        }
    }

    private function dailyFlock(array $b, string $date, int $ago): void
    {
        $age = $b['start'] - $ago;
        foreach (DB::all('SELECT shed_id, quantity FROM bird_inventory WHERE batch_id=? AND quantity>0', [$b['id']]) as $flock) {
            $birds = (int) $flock['quantity'];
            $key = "{$b['id']}:{$flock['shed_id']}";
            if ($b['type'] === 'layer') {
                $kgDay = round($birds * 0.115, 1);
                $product = $this->ids['feed_layer'];
                $price = 3050;
            } else {
                $perBird = min(0.2, 0.025 + $age * 0.0042);
                $kgDay = round($birds * $perBird, 1);
                $product = $age < 21 ? $this->ids['feed_starter'] : $this->ids['feed_finisher'];
                $price = $age < 21 ? 3500 : 3300;
            }
            $this->topUpFeed($b['farm'], $product, $kgDay, $date, $price);
            Ops::recordFeed(['flock' => $key, 'record_date' => $date, 'feed_product_id' => $product, 'quantity' => $kgDay, 'unit_id' => $this->ids['kg'], 'wastage_kg' => round($kgDay * (mt_rand(0, 15) / 1000), 1)]);

            $rate = $b['type'] === 'layer' ? 0.00012 : ($age < 7 ? 0.0009 : 0.00035);
            $dead = (int) round($birds * $rate * (mt_rand(40, 170) / 100));
            if ($dead > 0) {
                $reason = DB::value('SELECT id FROM mortality_reasons ORDER BY RAND() LIMIT 1');
                Ops::recordMortality(['flock' => $key, 'record_date' => $date, 'quantity' => $dead, 'reason_id' => $reason]);
            }
            if ($b['type'] === 'layer') {
                $laying = min(0.93, 0.55 + ($age + $b['age0'] - 126) * 0.015);
                $total = (int) round($birds * $laying * (mt_rand(97, 103) / 100));
                $damaged = (int) round($total * 0.006);
                $broken = (int) round($total * 0.004);
                Ops::recordEggs(['flock' => $key, 'record_date' => $date, 'good_eggs' => $total - $damaged - $broken, 'damaged_eggs' => $damaged, 'broken_eggs' => $broken]);
            }
        }
    }

    private function sellBroilers(array &$active, int $ago, string $date): void
    {
        foreach ($active as $id => $b) {
            if ($b['type'] !== 'broiler') {
                continue;
            }
            $age = $b['start'] - $ago;
            if ($age < 38) {
                continue;
            }
            foreach (DB::all('SELECT shed_id, quantity FROM bird_inventory WHERE batch_id=? AND quantity>0', [$id]) as $flock) {
                // The last day clears the shed; the days before take a share.
                $qty = $age >= 42 ? (int) $flock['quantity'] : (int) floor($flock['quantity'] * 0.3);
                if ($qty <= 0) {
                    continue;
                }
                $cust = $this->ids['customers'][[0, 4, 3, 0, 4][($age + $id) % 5]];
                $share = [1, 1, 0.7, 1, 0.5][($age + $id) % 5];
                $this->sell($cust, $b['farm'], $date, [['item_type' => 'bird', 'ref_id' => "$id:{$flock['shed_id']}", 'quantity' => $qty, 'unit_price' => 465 + mt_rand(0, 35)]], $share, $share === 1 ? 'bank_transfer' : 'cash', $qty > 1500 ? 5000 : 0);
            }
            if ($age >= 42) {
                DB::update('batches', ['status' => 'closed'], $id);
                unset($active[$id]);
            }
        }
    }

    private function vaccinate(string $date): void
    {
        foreach (DB::all("SELECT r.id, r.batch_id FROM vaccination_records r JOIN batches b ON b.id=r.batch_id WHERE r.status='scheduled' AND r.scheduled_date<=? AND b.status='active'", [$date]) as $r) {
            $birds = (int) DB::value('SELECT COALESCE(SUM(quantity),0) FROM bird_inventory WHERE batch_id=?', [$r['batch_id']]);
            VaccinationService::markGiven((int) $r['id'], ['given_date' => $date, 'doses' => $birds, 'veterinarian' => 'Dr. Asif Mahmood']);
        }
    }

    private function sellEggs(int $ago, string $date): void
    {
        if ($ago % 3 !== 0) {
            return;
        }
        $stock = (int) DB::value('SELECT COALESCE(quantity,0) FROM egg_inventory WHERE farm_id=?', [$this->ids['farmA']]);
        if ($stock < 3000) {
            return;
        }
        $qty = (int) floor($stock * 0.9 / 30) * 30;
        $cust = $this->ids['customers'][[1, 2, 3, 5][($ago / 3) % 4]];
        $share = [1, 1, 0.6, 1][($ago / 3) % 4];
        $this->sell($cust, $this->ids['farmA'], $date, [['item_type' => 'egg', 'ref_id' => '', 'quantity' => $qty, 'unit_price' => 25 + mt_rand(0, 2)]], $share, 'bank_transfer');
    }

    private function monthly(string $date): void
    {
        $dom = (int) date('j', strtotime($date));
        $cat = fn ($n) => (int) DB::value('SELECT id FROM expense_categories WHERE name=?', [$n]);
        if ($dom === 5) {
            foreach ([[$this->ids['farmA'], 185000, 28000], [$this->ids['farmB'], 340000, 42000]] as [$farm, $power, $water]) {
                ExpenseService::create(['expense_date' => $date, 'category_id' => $cat('Electricity'), 'farm_id' => $farm, 'description' => 'Electricity bill', 'amount' => $power + mt_rand(-15000, 15000), 'paid_amount' => $power - 20000, 'account_id' => $this->ids['bank'], 'method' => 'bank_transfer']);
                ExpenseService::create(['expense_date' => $date, 'category_id' => $cat('Water'), 'farm_id' => $farm, 'description' => 'Water and pump maintenance', 'amount' => $water, 'paid_amount' => $water, 'account_id' => $this->ids['cash']]);
            }
        }
        if ($dom === 12) {
            ExpenseService::create(['expense_date' => $date, 'category_id' => $cat('Transportation'), 'farm_id' => $this->ids['farmB'], 'description' => 'Bird delivery fuel and vehicle hire', 'amount' => 95000 + mt_rand(0, 20000), 'paid_amount' => 95000, 'account_id' => $this->ids['cash']]);
            ExpenseService::create(['expense_date' => $date, 'category_id' => $cat('Veterinary'), 'farm_id' => $this->ids['farmA'], 'description' => 'Monthly veterinarian visit', 'amount' => 25000, 'paid_amount' => 25000, 'account_id' => $this->ids['cash']]);
        }
        if ($dom === 20) {
            ExpenseService::create(['expense_date' => $date, 'category_id' => $cat('Maintenance'), 'farm_id' => $this->ids['farmB'], 'description' => 'Shed repairs and cleaning', 'amount' => 60000 + mt_rand(0, 30000), 'paid_amount' => 60000, 'account_id' => $this->ids['cash']]);
        }
        if ($dom === 1) {
            $period = date('Y-m', strtotime("$date -1 month"));
            foreach ($this->ids['employees'] as $e) {
                ExpenseService::paySalary(['employee_id' => $e['id'], 'period' => $period, 'basic_amount' => $e['salary'], 'allowances' => 3000, 'deductions' => 0, 'pay_date' => $date, 'account_id' => $this->ids['bank'], 'method' => 'bank_transfer']);
            }
        }
    }

    private function extras(): void
    {
        foreach ($this->ids['employees'] as $i => $e) {
            for ($ago = 14; $ago >= 0; $ago--) {
                $d = $this->day($ago);
                if (date('w', strtotime($d)) === '0') {
                    continue;
                }
                DB::insert('attendance', ['employee_id' => $e['id'], 'attendance_date' => $d, 'status' => ($ago + $i) % 11 === 0 ? 'absent' : (($ago + $i) % 13 === 0 ? 'half_day' : 'present'), 'created_by' => Auth::id()]);
            }
        }
        // A treatment, an expiring lot and a low-feed product, so the alerts have something real to show.
        $flock = DB::one("SELECT i.batch_id, i.shed_id, b.purchase_date FROM bird_inventory i JOIN batches b ON b.id=i.batch_id WHERE i.quantity>0 AND b.batch_type='layer' ORDER BY b.id LIMIT 1");
        Ops::recordMedicine(['flock' => "{$flock['batch_id']}:{$flock['shed_id']}", 'record_date' => $this->day(9), 'medicine_id' => $this->ids['med_tonic'], 'quantity' => 1500, 'veterinarian' => 'Dr. Asif Mahmood', 'diagnosis' => 'Heat stress support']);
        $this->buy('meds', $this->ids['farmB'], $this->day(4), [['item_type' => 'medicine', 'ref_id' => $this->ids['med_abx'], 'quantity' => 1200, 'unit_price' => 6.5, 'lot_no' => 'RT-221', 'expiry_date' => $this->day(-18)]]);

        // One open purchase order, one draft invoice, and one cancelled invoice to show a full reversal.
        PurchaseService::saveDraft(['supplier_id' => $this->ids['sup_feed'], 'farm_id' => $this->ids['farmA'], 'purchase_date' => $this->day(1), 'notes' => 'Next month feed order'], [['item_type' => 'feed', 'ref_id' => $this->ids['feed_layer'], 'unit_id' => $this->ids['bag'], 'quantity' => 200, 'unit_price' => 3050]]);
        SaleService::saveDraft(['customer_id' => $this->ids['customers'][5], 'farm_id' => $this->ids['farmA'], 'sale_date' => date('Y-m-d'), 'discount' => 0, 'tax_rate' => 0], [['item_type' => 'egg', 'ref_id' => '', 'quantity' => 300, 'unit_price' => 26]]);
        $cancelled = $this->sell($this->ids['customers'][2], $this->ids['farmA'], $this->day(2), [['item_type' => 'egg', 'ref_id' => '', 'quantity' => 600, 'unit_price' => 26]], 1, 'cash');
        SaleService::cancel($cancelled, 'Customer changed the order');
        $manure = (int) DB::value("SELECT id FROM products WHERE slug='poultry-manure'");
        $this->sell($this->ids['customers'][5], $this->ids['farmB'], $this->day(3), [['item_type' => 'product', 'ref_id' => $manure, 'quantity' => 2, 'unit_price' => 18000]], 1, 'cash');
        FinanceService::transferBetweenAccounts(['from_account_id' => $this->ids['cash'], 'to_account_id' => $this->ids['bank'], 'transfer_date' => $this->day(6), 'amount' => 500000, 'notes' => 'Cash deposited at the bank']);
        DB::insert('contact_messages', ['message_type' => 'quote', 'product_id' => DB::value("SELECT id FROM products WHERE slug='fresh-eggs'"), 'name' => 'Sana Bakery', 'phone' => '+92 300 0000099', 'subject' => 'Weekly egg supply', 'message' => 'We need about 60 trays a week. Please send your best price.']);
    }
}
