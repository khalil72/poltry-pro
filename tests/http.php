<?php
// End-to-end checks over real HTTP against the running site and the demo database.
// It writes some records, so run it on the demo data only. Usage: php tests/http.php [base-url]
if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}
$base = rtrim($argv[1] ?? 'http://localhost/umar/PoultryPro', '/');
$pass = 0;
$fail = [];
function ok(bool $c, string $n): void
{
    global $pass, $fail;
    if ($c) {
        $pass++;
    } else {
        $fail[] = $n;
        echo "  FAIL: $n\n";
    }
}

class Client
{
    public array $cookies = [];
    public int $code = 0;
    public string $body = '';
    public array $headers = [];

    public function __construct(public string $base)
    {
    }

    public function req(string $path, ?array $post = null, array $extra = []): string
    {
        $url = str_starts_with($path, 'http') ? $path : $this->base . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        $this->headers = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 90,
            CURLOPT_HEADERFUNCTION => function ($c, $h) {
                $this->headers[] = trim($h);
                if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $h, $m)) {
                    $this->cookies[$m[1]] = $m[2];
                }
                return strlen($h);
            },
        ]);
        $hdr = $extra['headers'] ?? [];
        if ($this->cookies) {
            $hdr[] = 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        }
        if (isset($extra['referer'])) {
            $hdr[] = 'Referer: ' . $extra['referer'];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $extra['multipart'] ?? false ? $post : http_build_query($post));
        }
        $this->body = (string) curl_exec($ch);
        $this->code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $this->body;
    }

    public function location(): ?string
    {
        foreach ($this->headers as $h) {
            if (stripos($h, 'Location:') === 0) {
                return trim(substr($h, 9));
            }
        }
        return null;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $h) {
            if (stripos($h, "$name:") === 0) {
                return trim(substr($h, strlen($name) + 1));
            }
        }
        return null;
    }

    public function token(): string
    {
        return preg_match('/name="_token" value="([a-f0-9]+)"/', $this->body, $m) ? $m[1] : '';
    }

    public function login(string $email, string $pw): bool
    {
        $this->req('admin/login');
        $t = $this->token();
        $this->req('admin/login', ['_token' => $t, 'email' => $email, 'password' => $pw]);
        return $this->code === 302 && str_contains((string) $this->location(), 'dashboard');
    }

    /** Posts a form, picking up a fresh token from the page it lives on first. */
    public function submit(string $formPage, string $action, array $data, array $extra = []): string
    {
        $this->req($formPage);
        return $this->req($action, ['_token' => $this->token(), '_once' => bin2hex(random_bytes(8)), ...$data], ['referer' => $this->base . '/' . $formPage, ...$extra]);
    }
}

function hasError(string $body): bool
{
    return (bool) preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Uncaught|SQLSTATE|Undefined (variable|array key|index))/', $body);
}

// ---------------------------------------------------------------------- Public site
echo "Public website\n";
$c = new Client($base);
foreach (['', 'about', 'farms', 'products', 'products/fresh-eggs', 'services', 'facilities', 'gallery', 'blog', 'contact', 'quote', 'sitemap.xml', 'robots.txt'] as $p) {
    $c->req($p);
    ok($c->code === 200 && !hasError($c->body), "public /$p loads ($c->code)");
}
$c->req('');
ok(str_contains($c->body, 'Healthy Poultry, Quality Products, Sustainable Farming.'), 'landing page is the homepage, with the specified headline');
ok(str_contains($c->body, 'data-count="25000"'), 'homepage statistics come from settings');
$c->req('sitemap.xml');
ok(str_contains($c->body, '/products/fresh-eggs') && !str_contains($c->body, '/admin'), 'sitemap lists products and no admin pages');
$c->req('robots.txt');
ok(str_contains($c->body, 'Disallow:') && str_contains($c->body, 'Sitemap:'), 'robots.txt present');
$c->req('blog');
preg_match('#/blog/([a-z0-9-]+)"#', $c->body, $m);
$c->req('blog/' . $m[1]);
ok($c->code === 200 && !hasError($c->body), 'a blog article opens');
$c->req('this-does-not-exist');
ok($c->code === 404, 'unknown page returns 404');
$c->req('products/nope');
ok($c->code === 404, 'unknown product returns 404');

echo "Files that must not be reachable\n";
foreach (['.env', '.env.example', 'config/database.php', 'config/app.php', 'database/schema.sql', 'database/seed.php', 'composer.json', 'composer.lock', 'storage/logs/php-error.log', 'app/bootstrap.php', 'vendor/autoload.php', 'tests/run.php', 'routes/admin.php', 'resources/views/layouts/admin.php', 'public/../.env'] as $p) {
    $c->req($p);
    ok($c->code === 404 || $c->code === 403, "/$p is blocked (got $c->code)");
    ok(!str_contains($c->body, 'DB_PASSWORD') && !str_contains($c->body, 'password_hash'), "/$p leaks nothing");
}

echo "Forms and spam protection\n";
$c = new Client($base);
$c->req('contact');
$t = $c->token();
$c->req('contact', ['name' => 'X', 'phone' => '12345', 'message' => 'no token']);
ok($c->code === 302 && str_contains((string) $c->location(), ''), 'contact form without a token is not accepted');
$c->req('contact', ['_token' => $t, '_once' => 'a1', 'name' => '', 'phone' => '12345', 'message' => 'x'], ['referer' => "$base/contact"]);
ok($c->code === 302, 'missing name sends you back');
$c->req('contact');
ok(str_contains($c->body, 'Your name is required'), 'the validation message is shown');
$c->req('contact', ['_token' => $t, '_once' => 'a2', 'name' => 'Test Buyer', 'email' => 'bad', 'message' => 'x'], ['referer' => "$base/contact"]);
$c->req('contact');
ok(str_contains($c->body, 'valid email'), 'invalid email refused');
$marker = 'HTTPTEST' . bin2hex(random_bytes(4));
$c->req('contact', ['_token' => $t, '_once' => 'a3', 'name' => "<script>alert('$marker')</script>", 'phone' => '0300 1234567', 'message' => "Need eggs $marker"], ['referer' => "$base/contact"]);
ok($c->code === 302, 'valid message accepted');
$c->req('contact', ['_token' => $t, '_once' => 'a3', 'name' => 'Replay', 'phone' => '0300 1234567', 'message' => "Replay $marker"], ['referer' => "$base/contact"]);
$c->req('contact');
ok(str_contains($c->body, 'already submitted'), 'replaying the same form is refused');
$c->req('contact', ['_token' => $t, '_once' => 'a4', 'website' => 'http://spam.test', 'name' => 'Bot', 'phone' => '0300 1234567', 'message' => "Bot $marker"], ['referer' => "$base/contact"]);
$c->req('newsletter', ['_token' => $t, '_once' => 'a5', 'email' => "n$marker@example.com"], ['referer' => "$base/"]);
ok($c->code === 302, 'newsletter signup accepted');

// ---------------------------------------------------------------------- Admin access
echo "Admin access without signing in\n";
$anon = new Client($base);
foreach (['admin', 'admin/dashboard', 'admin/sales', 'admin/users', 'admin/reports/profit-loss', 'admin/sales/1/pdf', 'admin/receipts/1'] as $p) {
    $anon->req($p);
    ok($anon->code === 302 && str_contains((string) $anon->location(), 'admin/login'), "/$p sends a visitor to sign-in");
}
$anon->req('admin/dashboard/data', null, ['headers' => ['Accept: application/json']]);
ok($anon->code === 403, 'dashboard data is refused without a session');
$anon->req('admin/farms', ['_token' => 'x', 'name' => 'Hack']);
ok(in_array($anon->code, [302, 403], true), 'posting without a session does nothing');

echo "Sign-in protection\n";
$c = new Client($base);
ok(!$c->login('admin@poultrypro.test', 'wrong-password'), 'wrong password refused');
$c->req('admin/login');
ok(str_contains($c->body, 'not correct'), 'a clear message is shown');
$lock = 'lockout' . bin2hex(random_bytes(3)) . '@poultrypro.test';
for ($i = 0; $i < 5; $i++) {
    $c->login($lock, 'nope');
}
$c->login($lock, 'nope');
$c->req('admin/login');
ok(str_contains($c->body, 'Too many failed'), 'repeated failures lock the account for a while');
$cookieHdr = '';
$c = new Client($base);
$c->req('admin/login');
foreach ($c->headers as $h) {
    if (stripos($h, 'Set-Cookie:') === 0) {
        $cookieHdr = $h;
    }
}
ok(stripos($cookieHdr, 'httponly') !== false && stripos($cookieHdr, 'samesite=lax') !== false, 'session cookie is HttpOnly and SameSite');
ok($c->header('X-Frame-Options') === 'SAMEORIGIN' && $c->header('X-Content-Type-Options') === 'nosniff', 'security headers set');

// ---------------------------------------------------------------------- Crawl as each role
echo "Crawling every page as each role\n";
$roles = [
    'admin' => ['admin@poultrypro.test', 'Admin@12345'], 'manager' => ['manager@poultrypro.test', 'Demo@12345'], 'accountant' => ['accountant@poultrypro.test', 'Demo@12345'],
    'staff' => ['staff@poultrypro.test', 'Demo@12345'], 'viewer' => ['viewer@poultrypro.test', 'Demo@12345'],
];
$clients = [];
foreach ($roles as $role => [$em, $pw]) {
    $cl = new Client($base);
    ok($cl->login($em, $pw), "$role can sign in");
    $clients[$role] = $cl;
    $seen = [];
    $queue = ['admin/dashboard'];
    $basePath = parse_url($base, PHP_URL_PATH) ?: '';
    $pages = 0;
    while ($queue && $pages < 400) {
        $p = array_shift($queue);
        if (isset($seen[$p])) {
            continue;
        }
        $seen[$p] = true;
        $pages++;
        $cl->req($p);
        if ($cl->code !== 200) {
            if (!($cl->code === 302 || $cl->code === 403)) {
                ok(false, "$role: /$p returned $cl->code");
            }
            continue;
        }
        ok(!hasError($cl->body), "$role: /$p has no PHP error");
        if (hasError($cl->body)) {
            preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Uncaught|SQLSTATE|Undefined[^<]{0,150})[^<]{0,200}/', $cl->body, $m);
            echo "    $m[0]\n";
        }
        if (preg_match_all('#href="' . preg_quote($basePath, '#') . '/(admin/[^"\#]*)"#', $cl->body, $mm)) {
            foreach ($mm[1] as $link) {
                $link = html_entity_decode($link);
                if (str_contains($link, '?') || isset($seen[$link]) || str_ends_with($link, '/pdf') || str_ends_with($link, '/print')) {
                    continue;
                }
                $queue[] = $link;
            }
        }
    }
    echo "  $role: $pages pages\n";
}

echo "Permissions enforced on the server\n";
$expect403 = [
    'staff' => ['admin/sales', 'admin/purchases', 'admin/expenses', 'admin/users', 'admin/finance', 'admin/reports/profit-loss', 'admin/reports/sales', 'admin/farms', 'admin/website', 'admin/settings', 'admin/activity-logs', 'admin/customers', 'admin/sales/create', 'admin/batches/create'],
    'manager' => ['admin/sales', 'admin/purchases', 'admin/users', 'admin/finance', 'admin/reports/profit-loss', 'admin/website', 'admin/accounts', 'admin/farms/create', 'admin/roles'],
    'accountant' => ['admin/users', 'admin/mortality', 'admin/website', 'admin/settings', 'admin/roles', 'admin/batches/create', 'admin/feed-consumption/create'],
    'viewer' => ['admin/users', 'admin/settings', 'admin/website', 'admin/farms/create', 'admin/sales/create', 'admin/purchases/create', 'admin/mortality/create', 'admin/expenses/create', 'admin/activity-logs'],
];
foreach ($expect403 as $role => $paths) {
    foreach ($paths as $p) {
        $clients[$role]->req($p);
        ok($clients[$role]->code === 403, "$role is refused /$p (got {$clients[$role]->code})");
    }
}
$v = $clients['viewer'];
$v->req('admin/farms');
$tok = $v->token();
if (!$tok) {
    $v->req('admin/profile');
    $tok = $v->token();
}
foreach ([['admin/farms', ['name' => 'Hack', 'code' => 'HX', 'status' => 'active']], ['admin/farms/1/delete', []], ['admin/sales/1/cancel', []], ['admin/payments/record', ['doc_type' => 'sale', 'doc_id' => 1, 'amount' => 1]]] as [$p, $d]) {
    $v->req($p, ['_token' => $tok, '_once' => bin2hex(random_bytes(6)), ...$d]);
    ok($v->code === 403, "viewer cannot POST /$p (got $v->code)");
}
foreach (['farms/create', 'sales/create', 'users'] as $p) {
    $v->req('admin/farms');
    ok(!str_contains($v->body, 'admin/' . $p . '"') || $p === 'users', "viewer sees no link to $p");
}
$v->req('admin/farms');
ok(!str_contains($v->body, 'bi-pencil') && !str_contains($v->body, 'bi-trash'), 'viewer sees no edit or delete buttons');

$s = $clients['staff'];
$s->req('admin/dashboard/data', null, ['headers' => ['Accept: application/json']]);
$j = json_decode($s->body, true);
ok(isset($j['stats']['birds']) && !isset($j['stats']['net_profit']) && !isset($j['charts']['revenue']), 'staff dashboard data holds no financial figures');
$s->req('admin/dashboard/data?farm_id=2', null, ['headers' => ['Accept: application/json']]);
ok($s->code === 403, 'staff cannot load another farm on the dashboard');
$s->req('admin/mortality');
ok(!str_contains($s->body, 'Hillview'), 'staff list shows only their farm');
$s->req('admin/mortality?farm_id=2');
ok(!str_contains($s->body, 'Hillview'), 'filtering to another farm still shows nothing');

$m = $clients['manager'];
$pdo = new PDO('mysql:host=127.0.0.1;dbname=poultrypro;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$q = fn ($sql) => $pdo->query($sql)->fetchColumn();
$otherShed = $q("SELECT id FROM sheds WHERE shed_number='B1'");
$otherBatch = $q("SELECT b.id FROM batches b JOIN farms f ON f.id=b.farm_id WHERE f.code='HBF' LIMIT 1");
$otherFarm = $q("SELECT id FROM farms WHERE code='HBF'");
$ownFarm = $q("SELECT id FROM farms WHERE code='RLF'");
foreach (["admin/sheds/$otherShed", "admin/sheds/$otherShed/edit", "admin/batches/$otherBatch", "admin/batches/$otherBatch/edit", "admin/farms/$otherFarm"] as $p) {
    $m->req($p);
    ok($m->code === 404, "manager cannot open another farm's record /$p (got $m->code)");
}
$before = $q("SELECT name FROM sheds WHERE id=$otherShed");
$m->submit("admin/sheds/create", "admin/sheds/$otherShed", ['name' => 'Hacked', 'farm_id' => $otherFarm, 'shed_number' => 'B1', 'shed_type' => 'broiler', 'max_capacity' => 9000, 'status' => 'active']);
ok($q("SELECT name FROM sheds WHERE id=$otherShed") === $before, 'manager cannot edit another farm shed by posting directly');
$cnt = $q("SELECT COUNT(*) FROM sheds");
$m->submit('admin/sheds/create', 'admin/sheds', ['name' => 'Hacked new', 'farm_id' => $otherFarm, 'shed_number' => 'H9', 'shed_type' => 'broiler', 'max_capacity' => 100, 'status' => 'active']);
ok($q('SELECT COUNT(*) FROM sheds') === $cnt, 'manager cannot add a shed to another farm by tampering with the form');
$flockOther = $pdo->query("SELECT CONCAT(i.batch_id,':',i.shed_id) FROM bird_inventory i JOIN farms f ON f.id=i.farm_id WHERE f.code='HBF' AND i.quantity>0 LIMIT 1")->fetchColumn();
if ($flockOther) {
    $dead = $q("SELECT COUNT(*) FROM mortality_records");
    $m->submit('admin/mortality/create', 'admin/mortality', ['flock' => $flockOther, 'record_date' => date('Y-m-d'), 'quantity' => 1]);
    ok($q('SELECT COUNT(*) FROM mortality_records') === $dead, 'manager cannot post mortality for another farm flock');
}

// ---------------------------------------------------------------------- CRUD through the forms
echo "Working through the admin forms\n";
$a = $clients['admin'];
$a->req('admin/dashboard/data', null, ['headers' => ['Accept: application/json']]);
$j = json_decode($a->body, true);
ok(isset($j['stats']['net_profit'], $j['charts']['revenue'], $j['charts']['eggs']['good'], $j['charts']['batches']['labels']), 'dashboard data has every series');
ok($j['stats']['birds'] == $q('SELECT SUM(quantity) FROM bird_inventory'), 'dashboard birds equal the inventory');
$a->req('admin/dashboard/data?farm_id=' . $ownFarm, null, ['headers' => ['Accept: application/json']]);
$j2 = json_decode($a->body, true);
ok($j2['stats']['farms'] === 1, 'farm filter narrows the dashboard');
$a->req('admin/dashboard/data?from=2020-01-01&to=2020-02-01', null, ['headers' => ['Accept: application/json']]);
$j3 = json_decode($a->body, true);
ok(abs((float) $j3['stats']['sales']) < 0.01, 'date filter narrows the dashboard');

$code = 'T' . random_int(1000, 9999);
$a->submit('admin/farms/create', 'admin/farms', ['name' => "Test Farm $code", 'code' => $code, 'capacity' => 500, 'status' => 'active', 'is_public' => '1']);
$farmId = $q("SELECT id FROM farms WHERE code='$code'");
ok((bool) $farmId, 'farm created through the form');
$a->submit('admin/farms/create', 'admin/farms', ['name' => 'Dup', 'code' => $code, 'capacity' => 1, 'status' => 'active']);
ok($q("SELECT COUNT(*) FROM farms WHERE code='$code'") == 1, 'duplicate farm code refused');
$a->submit("admin/farms/$farmId/edit", "admin/farms/$farmId", ['name' => "Edited $code", 'code' => $code, 'capacity' => 600, 'status' => 'active']);
ok($q("SELECT name FROM farms WHERE id=$farmId") === "Edited $code", 'farm edited');
ok($q("SELECT is_public FROM farms WHERE id=$farmId") == 0, 'unticked switch saved as off');
$a->submit('admin/sheds/create', 'admin/sheds', ['name' => 'Test Shed', 'farm_id' => $farmId, 'shed_number' => 'T1', 'shed_type' => 'broiler', 'max_capacity' => 100, 'status' => 'active']);
$shedId = $q("SELECT id FROM sheds WHERE farm_id=$farmId");
ok((bool) $shedId, 'shed created');
$a->submit("admin/farms", "admin/farms/$farmId/delete", []);
ok($q("SELECT deleted_at FROM farms WHERE id=$farmId") === null, 'farm with sheds cannot be deleted');
$a->submit('admin/batches/create', 'admin/batches', ['name' => 'Over', 'batch_type' => 'broiler', 'shed_id' => $shedId, 'purchase_date' => date('Y-m-d'), 'initial_quantity' => 101]);
ok($q("SELECT COUNT(*) FROM batches WHERE farm_id=$farmId") == 0, 'batch over capacity refused through the form');
$a->submit('admin/batches/create', 'admin/batches', ['name' => 'Fit', 'batch_type' => 'broiler', 'shed_id' => $shedId, 'purchase_date' => date('Y-m-d'), 'initial_quantity' => 80]);
$batchId = $q("SELECT id FROM batches WHERE farm_id=$farmId");
ok($batchId && $q("SELECT current_bird_count FROM sheds WHERE id=$shedId") == 80, 'batch created and shed updated');
$a->req("admin/batches/$batchId");
ok($a->code === 200 && str_contains($a->body, 'Batch profit and loss'), 'batch detail page shows profit and loss');
$a->submit('admin/mortality/create', 'admin/mortality', ['flock' => "$batchId:$shedId", 'record_date' => date('Y-m-d'), 'quantity' => 5]);
ok($q("SELECT current_bird_count FROM sheds WHERE id=$shedId") == 75, 'mortality form works');
$mid = $q("SELECT id FROM mortality_records WHERE batch_id=$batchId");
$a->submit('admin/mortality', "admin/mortality/$mid/cancel", ['reason' => 'test']);
ok($q("SELECT current_bird_count FROM sheds WHERE id=$shedId") == 80, 'mortality cancel works');

// Same-form replay through admin
$a->req('admin/customers/create');
$tok = $a->token();
$once = bin2hex(random_bytes(8));
$email = "$code@example.com";
$cust = ['_token' => $tok, '_once' => $once, 'name' => "Buyer $code", 'email' => $email, 'status' => 'active'];
$a->req('admin/customers', $cust, ['referer' => "$base/admin/customers/create"]);
$a->req('admin/customers', $cust, ['referer' => "$base/admin/customers/create"]);
ok($q("SELECT COUNT(*) FROM customers WHERE email='$email'") == 1, 'double submit of a form creates one record');
$custId = $q("SELECT id FROM customers WHERE email='$email'");

// A sale through the real form
$feedProduct = $q("SELECT id FROM feed_products WHERE name='Layer Mash'");
$cashAcc = $q("SELECT id FROM accounts WHERE account_type='cash' LIMIT 1");
$stockBefore = $q("SELECT quantity FROM egg_inventory WHERE farm_id=$ownFarm");
$a->submit('admin/sales/create', 'admin/sales/save', ['customer_id' => $custId, 'farm_id' => $ownFarm, 'sale_date' => date('Y-m-d'), 'discount' => 0, 'tax_rate' => 0, 'save_as' => 'draft', 'items' => [['item_type' => 'egg', 'ref_id' => '', 'quantity' => 30, 'unit_price' => 26]]]);
$saleId = $q("SELECT id FROM sales WHERE customer_id=$custId");
ok($saleId && $q("SELECT status FROM sales WHERE id=$saleId") === 'draft', 'invoice saved as draft');
ok($q("SELECT quantity FROM egg_inventory WHERE farm_id=$ownFarm") == $stockBefore, 'draft did not change stock');
$a->req("admin/sales/$saleId/edit");
ok($a->code === 200 && str_contains($a->body, 'docData'), 'draft opens for editing');
$a->submit("admin/sales/$saleId", "admin/sales/$saleId/confirm", []);
ok($q("SELECT status FROM sales WHERE id=$saleId") === 'confirmed' && $q("SELECT quantity FROM egg_inventory WHERE farm_id=$ownFarm") == $stockBefore - 30, 'confirm posts stock once');
$a->submit("admin/sales/$saleId", "admin/sales/$saleId/confirm", []);
ok($q("SELECT quantity FROM egg_inventory WHERE farm_id=$ownFarm") == $stockBefore - 30, 'second confirm over HTTP changes nothing');
$a->submit("admin/sales/$saleId", 'admin/payments/record', ['doc_type' => 'sale', 'doc_id' => $saleId, 'amount' => 780, 'account_id' => $cashAcc, 'payment_date' => date('Y-m-d'), 'method' => 'cash']);
ok($q("SELECT payment_status FROM sales WHERE id=$saleId") === 'paid', 'payment through the modal form');
$a->req("admin/sales/$saleId/pdf");
ok($a->code === 200 && str_starts_with($a->body, '%PDF'), 'invoice PDF downloads');
$a->req("admin/sales/$saleId/print");
ok($a->code === 200 && str_contains($a->body, 'INVOICE') && !hasError($a->body), 'invoice print view');
$a->submit("admin/sales/$saleId", "admin/sales/$saleId/cancel", ['reason' => 'http test']);
ok($q("SELECT status FROM sales WHERE id=$saleId") === 'cancelled' && $q("SELECT quantity FROM egg_inventory WHERE farm_id=$ownFarm") == $stockBefore, 'cancel restores stock');
$a->req("admin/sales/$saleId/print");
ok(str_contains($a->body, 'CANCELLED'), 'cancelled invoice is stamped');

// Purchase through the form
$fb = $q("SELECT COALESCE(quantity_kg,0) FROM feed_inventory WHERE farm_id=$ownFarm AND feed_product_id=$feedProduct");
$bagId = $q("SELECT id FROM units WHERE symbol='bag50'");
$supId = $q('SELECT id FROM suppliers LIMIT 1');
$a->submit('admin/purchases/create', 'admin/purchases/save', ['supplier_id' => $supId, 'farm_id' => $ownFarm, 'purchase_date' => date('Y-m-d'), 'save_as' => 'confirm', 'paid_amount' => 1000, 'account_id' => $cashAcc, 'method' => 'cash', 'items' => [['item_type' => 'feed', 'ref_id' => $feedProduct, 'unit_id' => $bagId, 'quantity' => 2, 'unit_price' => 3000]]]);
ok(abs($q("SELECT quantity_kg FROM feed_inventory WHERE farm_id=$ownFarm AND feed_product_id=$feedProduct") - $fb - 100) < 0.01, 'purchase form adds 2 bags as 100 kg');
$pid = $q('SELECT id FROM purchases ORDER BY id DESC LIMIT 1');
ok($q("SELECT payment_status FROM purchases WHERE id=$pid") === 'partial', 'purchase form records the part payment');
$a->req("admin/purchases/$pid/pdf");
ok(str_starts_with($a->body, '%PDF'), 'purchase PDF downloads');

// Uploads
echo "Uploads\n";
$tmpDir = sys_get_temp_dir();
$php = "$tmpDir/shell.jpg";
file_put_contents($php, '<?php echo "pwned"; ?>');
$a->req('admin/gallery/create');
$tok = $a->token();
$a->req('admin/gallery', ['_token' => $tok, '_once' => bin2hex(random_bytes(6)), 'title' => 'Disguised', 'category' => 'farm', 'media_type' => 'image', 'image' => new CURLFile($php, 'image/jpeg', 'shell.jpg')], ['multipart' => true, 'referer' => "$base/admin/gallery/create"]);
ok($q("SELECT COUNT(*) FROM gallery WHERE title='Disguised'") == 0, 'a script disguised as a JPG is refused');
$php2 = "$tmpDir/hack.php";
file_put_contents($php2, '<?php echo "pwned"; ?>');
$a->req('admin/gallery', ['_token' => $tok, '_once' => bin2hex(random_bytes(6)), 'title' => 'Disguised2', 'category' => 'farm', 'media_type' => 'image', 'image' => new CURLFile($php2, 'application/x-php', 'hack.php')], ['multipart' => true, 'referer' => "$base/admin/gallery/create"]);
ok($q("SELECT COUNT(*) FROM gallery WHERE title='Disguised2'") == 0, 'a PHP file is refused');
$img = imagecreatetruecolor(2400, 1200);
imagefill($img, 0, 0, imagecolorallocate($img, 30, 120, 80));
$good = "$tmpDir/ok.jpg";
imagejpeg($img, $good);
$a->req('admin/gallery', ['_token' => $tok, '_once' => bin2hex(random_bytes(6)), 'title' => 'Real photo', 'category' => 'farm', 'media_type' => 'image', 'is_published' => '1', 'image' => new CURLFile($good, 'image/jpeg', 'evil.php.jpg')], ['multipart' => true, 'referer' => "$base/admin/gallery/create"]);
$stored = $q("SELECT image FROM gallery WHERE title='Real photo' ORDER BY id DESC LIMIT 1");
ok((bool) $stored && preg_match('#^uploads/gallery/[a-f0-9]{24}\.jpg$#', $stored) === 1, 'a real image is stored under a random name');
$size = getimagesize(dirname(__DIR__) . '/public/assets/' . $stored);
ok($size[0] === 1800, 'large images are resized');
$a->req('assets/' . $stored);
ok($a->code === 200 && str_starts_with((string) $a->header('Content-Type'), 'image/'), 'uploaded image is served');
file_put_contents(dirname(__DIR__) . '/public/assets/uploads/probe.php', '<?php echo "EXECUTED";');
$a->req('assets/uploads/probe.php');
ok(!str_contains($a->body, 'EXECUTED'), 'a PHP file in the uploads folder never runs');
unlink(dirname(__DIR__) . '/public/assets/uploads/probe.php');
$gid = $q("SELECT id FROM gallery WHERE title='Real photo' ORDER BY id DESC LIMIT 1");
$a->submit('admin/gallery', "admin/gallery/$gid/delete", []);
$a->submit('admin/gallery/create', 'admin/gallery', ['title' => 'Bad video', 'category' => 'farm', 'media_type' => 'video', 'video_url' => 'https://evil.test/x']);
ok($q("SELECT COUNT(*) FROM gallery WHERE title='Bad video'") == 0, 'a non-YouTube video link is refused');
$a->submit('admin/gallery/create', 'admin/gallery', ['title' => 'Good video', 'category' => 'farm', 'media_type' => 'video', 'video_url' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa', 'is_published' => '1']);
$vid = $q("SELECT id FROM gallery WHERE title='Good video'");
ok((bool) $vid, 'a YouTube link is accepted');
$pubG = new Client($base);
$pubG->req('gallery');
ok(str_contains($pubG->body, 'youtube-nocookie.com/embed/aaaaaaaaaaa'), 'the video appears in the public gallery');
$a->submit('admin/gallery', "admin/gallery/$vid/delete", []);
$pubG->req('gallery');
ok(!str_contains($pubG->body, 'embed/aaaaaaaaaaa'), 'a deleted item leaves the public gallery');

// Receipt privacy
$a->req('admin/expenses/create');
$tok = $a->token();
$cat = $q("SELECT id FROM expense_categories WHERE name='Water'");
$a->req('admin/expenses', ['_token' => $tok, '_once' => bin2hex(random_bytes(6)), 'expense_date' => date('Y-m-d'), 'category_id' => $cat, 'description' => "Receipt $code", 'farm_id' => $otherFarm, 'amount' => 10, 'paid_amount' => 0, 'receipt' => new CURLFile($good, 'image/jpeg', 'r.jpg')], ['multipart' => true, 'referer' => "$base/admin/expenses/create"]);
$xid = $q("SELECT id FROM expenses WHERE description='Receipt $code'");
ok((bool) $xid, 'expense with receipt saved');
$a->req("admin/receipts/$xid");
ok($a->code === 200 && str_starts_with((string) $a->header('Content-Type'), 'image/'), 'receipt opens for a permitted user');
$file = $q("SELECT receipt FROM expenses WHERE id=$xid");
$anon->req("storage/uploads/$file");
ok($anon->code !== 200, 'receipt file cannot be fetched directly');
$anon->req("admin/receipts/$xid");
ok($anon->code === 302, 'receipt route needs sign-in');
$clients['accountant']->req("admin/receipts/$xid");
ok($clients['accountant']->code === 200, 'accountant can open a receipt');
$a->submit('admin/expenses', "admin/expenses/$xid/cancel", ['reason' => 'test']);

// CMS: change content, see it on the site
echo "Website CMS\n";
$a->req('admin/website');
preg_match_all('/<(?:input|textarea)[^>]*name="([a-z_]+)"[^>]*>/', $a->body, $names);
$all = [];
foreach ($pdo->query('SELECT setting_key, setting_value FROM website_settings')->fetchAll() as $r) {
    $all[$r['setting_key']] = $r['setting_value'];
}
$post = [];
foreach (array_unique($names[1]) as $n) {
    if ($n[0] !== '_' && isset($all[$n])) {
        if (str_starts_with($n, 'show_')) {
            if ($all[$n] === '1') {
                $post[$n] = '1';
            }
        } else {
            $post[$n] = $all[$n];
        }
    }
}
$original = $all['hero_title'];
$post['hero_title'] = "Test headline $code";
unset($post['show_blog']);
$a->submit('admin/website', 'admin/website', $post);
$pub = new Client($base);
$pub->req('');
ok(str_contains($pub->body, "Test headline $code"), 'hero text edited in the CMS appears on the site');
ok(!str_contains($pub->body, 'From the farm journal'), 'a hidden section disappears from the homepage');
$post['hero_title'] = $original;
$post['show_blog'] = '1';
$a->submit('admin/website', 'admin/website', $post);
$pub->req('');
ok(str_contains($pub->body, $original) && str_contains($pub->body, 'From the farm journal'), 'content restored');
$a->submit('admin/website', 'admin/website', [...$post, 'social_facebook' => 'javascript:alert(1)']);
ok($q("SELECT setting_value FROM website_settings WHERE setting_key='social_facebook'") !== 'javascript:alert(1)', 'script links refused in social settings');
$a->submit('admin/blog/create', 'admin/blog', ['title' => "Post $code", 'category' => 'industry', 'status' => 'published', 'excerpt' => 'x', 'body' => '<p>Body text</p><script>alert(1)</script><img src=x onerror=alert(2)>']);
$slug = $q("SELECT slug FROM blog_posts WHERE title='Post $code'");
$pub->req("blog/$slug");
ok($pub->code === 200 && str_contains($pub->body, 'Body text') && !str_contains($pub->body, 'alert(1)') && !str_contains($pub->body, 'onerror'), 'blog post publishes with scripts removed');
$msgId = $q("SELECT id FROM contact_messages WHERE message LIKE '%$marker%' ORDER BY id LIMIT 1");
ok($q("SELECT COUNT(*) FROM contact_messages WHERE message LIKE 'Bot%$marker%'") == 0, 'a bot filling the hidden field stored nothing');
$a->req("admin/messages/$msgId");
ok(!str_contains($a->body, "<script>alert('$marker')") && str_contains($a->body, '&lt;script&gt;'), 'a script in a message is shown as text, never run');
ok($q("SELECT is_read FROM contact_messages WHERE id=$msgId") == 1, 'opening a message marks it read');
$a->submit('admin/messages', "admin/messages/$msgId/delete", []);

// Users
echo "Users and roles\n";
$mail = strtolower($code) . '@poultrypro.test';
$role = $q("SELECT id FROM roles WHERE slug='farm_staff'");
$a->submit('admin/users/create', 'admin/users', ['name' => 'New', 'email' => $mail, 'role_id' => $role, 'status' => 'active', 'password' => 'short']);
ok($q("SELECT COUNT(*) FROM users WHERE email='$mail'") == 0, 'short password refused');
$a->submit('admin/users/create', 'admin/users', ['name' => 'New', 'email' => $mail, 'role_id' => $role, 'status' => 'active', 'password' => 'GoodPass123', 'farm_ids' => [$ownFarm]]);
$uid = $q("SELECT id FROM users WHERE email='$mail'");
ok((bool) $uid && str_starts_with($q("SELECT password_hash FROM users WHERE id=$uid"), '$2y$'), 'user created with a hashed password');
ok($q("SELECT farm_id FROM user_farms WHERE user_id=$uid") == $ownFarm, 'farm assignment saved');
$a->req("admin/users/$uid/edit");
ok(!str_contains($a->body, '$2y$'), 'the password hash is never sent to the browser');
$a->submit('admin/users', "admin/users/1/delete", []);
ok($q('SELECT deleted_at FROM users WHERE id=1') === null, 'you cannot delete yourself');
$nu = new Client($base);
ok($nu->login($mail, 'GoodPass123'), 'new user can sign in');
$a->submit("admin/users/$uid/edit", "admin/users/$uid", ['name' => 'New', 'email' => $mail, 'role_id' => $role, 'status' => 'inactive']);
$nu->req('admin/dashboard');
ok($nu->code === 302, 'deactivating a user ends their access straight away');
$a->submit('admin/users', "admin/users/$uid/delete", []);
ok($q("SELECT deleted_at FROM users WHERE id=$uid") !== null, 'user deleted softly');
$a->req('admin/roles');
ok(str_contains($a->body, 'Farm Manager'), 'roles page lists roles');

// Reports
echo "Reports and exports\n";
$keys = ['farm-performance', 'shed-performance', 'bird-inventory', 'batch-performance', 'mortality', 'feed-consumption', 'egg-production', 'medicine-usage', 'employees', 'purchases', 'sales', 'expenses', 'customer-ledger', 'supplier-ledger', 'profit-loss'];
ok(count($keys) === 15, '15 reports');
$from = date('Y-m-d', strtotime('-200 days'));
foreach ($keys as $k) {
    $a->req("admin/reports/$k?from=$from");
    ok($a->code === 200 && !hasError($a->body), "report $k loads");
    ok(!str_contains($a->body, 'Nothing to report'), "report $k has data");
    $a->req("admin/reports/$k?from=$from&export=xlsx");
    ok($a->code === 200 && str_starts_with($a->body, 'PK') && !hasError($a->body), "report $k exports to Excel ($a->code)");
    $a->req("admin/reports/$k?from=$from&export=pdf");
    ok($a->code === 200 && str_starts_with($a->body, '%PDF'), "report $k exports to PDF ($a->code)");
    $a->req("admin/reports/$k?from=$from&export=print");
    ok($a->code === 200 && !hasError($a->body), "report $k prints");
}
$a->req('admin/reports/mortality?export=print&farm_id=' . $ownFarm);
ok(!str_contains($a->body, 'Hillview'), 'report farm filter works');
$a->req("admin/reports/mortality?q=" . urlencode("' OR 1=1 -- "));
ok($a->code === 200 && !hasError($a->body), 'SQL in the search box is harmless');
$a->req("admin/farms?q=" . urlencode("' UNION SELECT password_hash FROM users -- ") . '&sort=' . urlencode('id;DROP TABLE users') . '&dir=' . urlencode('asc;DROP'));
ok($a->code === 200 && !hasError($a->body) && !str_contains($a->body, '$2y$'), 'SQL in list parameters is harmless');
$a->req('admin/reports/nope');
ok($a->code === 404, 'unknown report returns 404');
$cust1 = $q('SELECT customer_id FROM sales WHERE status=\'confirmed\' LIMIT 1');
$a->req("admin/reports/customer-ledger?customer_id=$cust1");
ok(str_contains($a->body, 'closing balance') && str_contains($a->body, 'Invoice'), 'single customer ledger shows a running account');
$clients['manager']->req('admin/reports/mortality');
ok(!str_contains($clients['manager']->body, 'Hillview'), 'manager reports show only their farm');
$clients['manager']->req('admin/reports/mortality?farm_id=' . $otherFarm);
ok($clients['manager']->code === 403, 'manager cannot filter a report to another farm');
$clients['manager']->req('admin/reports/batch-performance');
ok(!str_contains($clients['manager']->body, 'Profit / loss'), 'manager batch report has no money columns');
$clients['manager']->req('admin/reports/farm-performance?export=xlsx');
ok($clients['manager']->code === 200, 'manager can export an operational report');
$clients['manager']->req('admin/reports/sales?export=xlsx');
ok($clients['manager']->code === 403, 'manager cannot export a financial report');

// Tidy up the throwaway records this run made.
$pdo->exec("UPDATE blog_posts SET deleted_at=NOW() WHERE title='Post $code'");
$pdo->exec("DELETE FROM newsletter_subscribers WHERE email LIKE 'n$marker%'");
$pdo->exec("DELETE FROM login_attempts");
foreach ([$php, $php2, $good] as $f) {
    @unlink($f);
}

echo "\n$pass passed, " . count($fail) . " failed\n";
foreach (array_slice($fail, 0, 60) as $f) {
    echo " - $f\n";
}
exit($fail ? 1 : 0);
