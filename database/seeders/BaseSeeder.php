<?php
use App\Core\Database as DB;

/** Data every installation needs: roles, permissions, settings, website copy, categories and the first admin. */
class BaseSeeder
{
    public const ADMIN_EMAIL = 'admin@poultrypro.test';
    public const ADMIN_PASSWORD = 'Admin@12345';

    public static function run(): int
    {
        self::permissions();
        self::settings();
        self::reference();
        self::website();
        $roleId = (int) DB::value("SELECT id FROM roles WHERE slug='super_admin'");
        return DB::insert('users', [
            'role_id' => $roleId, 'name' => 'System Administrator', 'email' => self::ADMIN_EMAIL,
            'password_hash' => password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT), 'must_change_password' => 1,
        ]);
    }

    private static function permissions(): void
    {
        $modules = [
            'dashboard' => ['Dashboard', ['view']], 'farms' => ['Farms', ['view', 'manage']], 'sheds' => ['Sheds', ['view', 'manage']], 'batches' => ['Batches', ['view', 'manage']],
            'birds' => ['Bird inventory', ['view', 'manage']], 'feed' => ['Feed stock and products', ['view', 'manage']], 'feed_consumption' => ['Feed consumption', ['view', 'manage']],
            'eggs' => ['Egg production', ['view', 'manage']], 'mortality' => ['Mortality', ['view', 'manage']], 'medicines' => ['Medicines and treatments', ['view', 'manage']], 'vaccinations' => ['Vaccinations', ['view', 'manage']],
            'purchases' => ['Purchases', ['view', 'manage']], 'sales' => ['Sales', ['view', 'manage']], 'expenses' => ['Expenses', ['view', 'manage']], 'customers' => ['Customers', ['view', 'manage']], 'suppliers' => ['Suppliers', ['view', 'manage']],
            'employees' => ['Employees and attendance', ['view', 'manage']], 'salaries' => ['Salaries', ['view', 'manage']], 'payments' => ['Payments', ['view', 'manage']], 'accounts' => ['Cash and bank accounts', ['view', 'manage']],
            'finance' => ['Financial figures', ['view']], 'reports' => ['Operational reports', ['view']], 'reports_financial' => ['Financial reports', ['view']],
            'cms' => ['Website CMS', ['view', 'manage']], 'users' => ['Users', ['view', 'manage']], 'roles' => ['Roles and permissions', ['view', 'manage']], 'settings' => ['System settings', ['view', 'manage']], 'activity_logs' => ['Activity log', ['view']],
        ];
        foreach ($modules as $slug => [$label, $actions]) {
            foreach ($actions as $a) {
                DB::insert('permissions', ['slug' => "$slug.$a", 'module' => $slug, 'label' => ($a === 'view' ? 'View ' : 'Manage ') . strtolower($label)]);
            }
        }
        $both = fn (array $m) => array_merge(...array_map(fn ($x) => ["$x.view", "$x.manage"], $m));
        $roles = [
            ['super_admin', 'Super Admin', 1, 'Full access to every farm and module.', []],
            ['farm_manager', 'Farm Manager', 0, 'Runs the assigned farms: sheds, batches, stock, production and mortality.', [
                'dashboard.view', 'farms.view', 'employees.view', 'reports.view', ...$both(['sheds', 'batches', 'birds', 'feed', 'feed_consumption', 'eggs', 'mortality', 'medicines', 'vaccinations']),
            ]],
            ['accountant', 'Accountant', 1, 'Purchases, sales, expenses, payments and financial reports.', [
                'dashboard.view', 'finance.view', 'reports_financial.view', 'reports.view', 'farms.view', 'batches.view', 'birds.view', 'feed.view', 'eggs.view', 'medicines.view', 'employees.view',
                ...$both(['purchases', 'sales', 'expenses', 'payments', 'accounts', 'salaries', 'customers', 'suppliers']),
            ]],
            ['farm_staff', 'Farm Staff', 0, 'Daily entries at the assigned farms: feed, eggs and mortality.', [
                'dashboard.view', 'eggs.view', 'eggs.manage', 'feed_consumption.view', 'feed_consumption.manage', 'mortality.view', 'mortality.manage',
            ]],
            ['viewer', 'Viewer', 1, 'Read-only access.', [
                'dashboard.view', 'farms.view', 'sheds.view', 'batches.view', 'birds.view', 'feed.view', 'feed_consumption.view', 'eggs.view', 'mortality.view', 'medicines.view', 'vaccinations.view',
                'purchases.view', 'sales.view', 'expenses.view', 'customers.view', 'suppliers.view', 'employees.view', 'salaries.view', 'payments.view', 'accounts.view', 'finance.view', 'reports.view', 'reports_financial.view',
            ]],
        ];
        foreach ($roles as [$slug, $name, $all, $desc, $perms]) {
            $id = DB::insert('roles', ['slug' => $slug, 'name' => $name, 'all_farms' => $all, 'description' => $desc]);
            foreach ($perms as $p) {
                DB::query('INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE slug=?', [$id, $p]);
            }
        }
    }

    private static function settings(): void
    {
        $s = [
            'company_name' => 'PoultryPro Farms', 'company_tagline' => 'Healthy birds, fresh eggs and dependable supply.', 'logo' => '', 'favicon' => '',
            'hero_title' => 'Healthy Poultry, Quality Products, Sustainable Farming.',
            'hero_text' => 'We are committed to providing healthy poultry, quality eggs, and reliable poultry products through modern farming practices and professional farm management.',
            'hero_image' => 'images/site/hero-shed.jpg', 'hero_cta_primary' => 'Explore Our Products', 'hero_cta_secondary' => 'Contact Our Team',
            'about_title' => 'A family farm run with modern discipline',
            'about_intro' => 'PoultryPro Farms raises broilers and layers in clean, climate-managed sheds. Every flock is recorded from the day it arrives until the day it leaves, so we always know what our birds ate, how they are doing and what we can promise our customers.',
            'about_history' => "We began with a single broiler shed and a handful of local customers. As demand for dependable supply grew, we added layer houses, our own feed planning and a small veterinary team.\n\nToday we supply live birds, day-old chicks, eggs and feed to shops, restaurants and other farms across the region.",
            'about_mission' => 'To supply healthy birds and fresh eggs that families and businesses can rely on, every week of the year.',
            'about_vision' => 'To be the farm that other farms measure themselves against for bird welfare, hygiene and honest dealing.',
            'about_standards' => 'Controlled ventilation and temperature, clean water, measured feeding, and strict entry rules for every shed.',
            'about_quality' => 'A vaccination programme for every batch, daily health checks, and full records of feed, medicine and mortality for each flock.',
            'about_image' => 'images/site/hen-closeup.jpg',
            'stat_birds' => '25000', 'stat_farms' => '2', 'stat_customers' => '350', 'stat_years' => '12',
            'contact_email' => 'info@poultrypro.example', 'contact_phone' => '+92 300 0000000', 'contact_whatsapp' => '923000000000', 'contact_address' => 'Farm Road, Lahore, Pakistan',
            'contact_map_embed' => 'https://www.google.com/maps?q=Lahore,Pakistan&output=embed',
            'social_facebook' => 'https://www.facebook.com/', 'social_instagram' => 'https://www.instagram.com/', 'social_youtube' => 'https://www.youtube.com/', 'social_linkedin' => '',
            'seo_title' => 'PoultryPro Farms | Healthy poultry, fresh eggs and day-old chicks', 'seo_description' => 'Broiler chickens, layer hens, day-old chicks, fresh eggs and poultry feed from a modern, professionally managed farm.', 'seo_keywords' => 'poultry farm, broiler chickens, layer hens, day-old chicks, fresh eggs, poultry feed',
            'footer_about' => 'Broilers, layers, chicks, eggs and feed from professionally managed farms. Raised with care and recorded at every step.', 'footer_copyright' => '© {year} PoultryPro Farms. All rights reserved.',
            'show_about' => '1', 'show_products' => '1', 'show_services' => '1', 'show_facilities' => '1', 'show_why' => '1', 'show_process' => '1', 'show_stats' => '1', 'show_gallery' => '1', 'show_testimonials' => '1', 'show_blog' => '1', 'show_contact' => '1',
            'currency' => 'PKR', 'default_tax_rate' => '0', 'mortality_alert_pct' => '5', 'vaccination_reminder_days' => '7', 'expiry_alert_days' => '30',
        ];
        foreach ($s as $k => $v) {
            DB::insert('website_settings', ['setting_key' => $k, 'setting_value' => $v]);
        }
    }

    private static function reference(): void
    {
        foreach ([['Kilogram', 'kg', 1], ['Bag (50 kg)', 'bag50', 50], ['Bag (25 kg)', 'bag25', 25], ['Tonne', 't', 1000]] as [$n, $s, $f]) {
            DB::insert('units', ['name' => $n, 'symbol' => $s, 'kg_factor' => $f]);
        }
        foreach (['Disease', 'Heat stress', 'Injury', 'Predator', 'Culled', 'Unknown'] as $r) {
            DB::insert('mortality_reasons', ['name' => $r]);
        }
        foreach ([
            ['Feed', 'operational', 'Feed costs not held in stock, such as delivery. Stocked feed belongs in Purchases.'], ['Medicine', 'operational', 'Medicine costs not held in stock. Stocked medicine belongs in Purchases.'],
            ['Electricity', 'operational', null], ['Water', 'operational', null], ['Employee Salaries', 'operational', 'Filled automatically when a salary is paid.'], ['Transportation', 'operational', null],
            ['Maintenance', 'operational', null], ['Equipment', 'capital', 'Long-lasting equipment. Recorded as capital, not in profit.'], ['Veterinary', 'operational', 'Vet visits and services.'], ['Other Expenses', 'operational', null],
        ] as [$n, $t, $d]) {
            DB::insert('expense_categories', ['name' => $n, 'expense_type' => $t, 'description' => $d]);
        }
    }

    private static function website(): void
    {
        $cats = [];
        foreach (['Live Birds', 'Chicks', 'Eggs', 'Feed', 'Other Products'] as $i => $c) {
            $cats[$c] = DB::insert('product_categories', ['name' => $c, 'slug' => slugify($c), 'sort_order' => $i]);
        }
        $products = [
            ['Broiler Chickens', 'Live Birds', 'Market-ready broilers, weighed and delivered to order.', "Our broilers reach market weight in about six weeks on a measured starter and finisher programme. Each batch has a full record of feed, vaccinations and health checks.\n\nAvailable in full or part loads for shops, restaurants and wholesalers.", 'images/site/broiler-flock.jpg', 480, 'bird', 'in_stock'],
            ['Layer Chickens', 'Live Birds', 'Point-of-lay pullets, vaccinated and ready for your layer house.', "Well-grown pullets raised on a complete vaccination programme and sold close to point of lay.\n\nWe can advise on housing and feeding for the first weeks after delivery.", 'images/site/hens-field.jpg', 1450, 'bird', 'limited'],
            ['Day-Old Chicks', 'Chicks', 'Healthy, uniform chicks from selected breeders.', "Broiler and layer chicks selected for vigour and uniformity, delivered in ventilated boxes.\n\nBook ahead: chick placements are planned around your shed availability.", 'images/site/chicks-box.jpg', 85, 'chick', 'on_order'],
            ['Fresh Eggs', 'Eggs', 'Graded table eggs collected every day from our layer houses.', "Eggs are collected daily, checked, and only sound eggs are packed. Available by the tray for shops, bakeries and caterers.\n\nRegular weekly deliveries can be arranged.", 'images/site/eggs-tray.jpg', 780, 'tray of 30', 'in_stock'],
            ['Poultry Feed', 'Feed', 'Starter, grower, finisher and layer feed in 50 kg bags.', "The same feed programme we use on our own farms, available in 50 kg bags.\n\nAsk us which feed fits the age and type of your birds.", 'images/site/layer-house.jpg', 3100, '50 kg bag', 'in_stock'],
            ['Poultry Manure', 'Other Products', 'Well-rotted manure for fields, orchards and nurseries.', 'Composted litter from our sheds, sold by the trolley load to growers nearby. Collection from the farm or delivery by arrangement.', 'images/site/hens-fence.jpg', null, null, 'limited'],
        ];
        foreach ($products as $i => [$n, $c, $sum, $desc, $img, $price, $unit, $av]) {
            DB::insert('products', ['category_id' => $cats[$c], 'name' => $n, 'slug' => slugify($n), 'summary' => $sum, 'description' => $desc, 'image' => $img, 'price' => $price, 'price_unit' => $unit, 'availability' => $av, 'sort_order' => $i]);
        }
        $blocks = [
            'service' => [
                ['Poultry farming', 'Broilers and layers raised to contract or for the open market.', 'bi-house-heart'], ['Chick supply', 'Uniform day-old chicks, booked around your placement dates.', 'bi-egg-fried'],
                ['Egg production', 'Daily collected, graded eggs by the tray, with regular delivery.', 'bi-egg'], ['Poultry feed supply', 'Starter, grower, finisher and layer feed in 50 kg bags.', 'bi-basket'],
                ['Farm consultancy', 'Practical advice on sheds, feeding and flock health from people who farm every day.', 'bi-chat-dots'], ['Poultry farm management', 'Day-to-day management and record keeping for owners who want it run properly.', 'bi-clipboard-check'],
            ],
            'facility' => [
                ['Modern poultry sheds', 'Well-spaced, easy-to-clean houses built for bird comfort.', 'bi-house-door', 'images/site/hero-shed.jpg'], ['Automatic feeding systems', 'Measured feed lines give every bird fair access and limit waste.', 'bi-gear-wide-connected', 'images/site/layer-house.jpg'],
                ['Clean water systems', 'Nipple drinkers on filtered lines, checked every day.', 'bi-droplet', 'images/site/broiler-flock.jpg'], ['Ventilation systems', 'Fans and inlets keep air fresh and litter dry.', 'bi-fan', 'images/site/hen-cage.jpg'],
                ['Temperature control', 'Brooding heat for chicks and cooling for the hot months.', 'bi-thermometer-half', 'images/site/chicks-brooder.jpg'], ['Biosecurity facilities', 'Controlled entry, footbaths and disinfection for people and vehicles.', 'bi-shield-check', 'images/site/hens-fence.jpg'],
                ['Veterinary care', 'A planned vaccination programme and regular visits from a veterinarian.', 'bi-heart-pulse', 'images/site/hen-closeup.jpg'],
            ],
            'why' => [
                ['Healthy poultry', 'Every batch follows a vaccination programme and daily health checks.'], ['Quality feed and nutrition', 'Feed matched to the age and type of the birds.'], ['Hygienic farming', 'Clean sheds, clean water and strict entry rules.'],
                ['Experienced management', 'A team that has raised flocks through every season.'], ['Reliable supply', 'Planned placements mean we can tell you what is available and when.'], ['Customer satisfaction', 'Straight answers, fair weights and deliveries that turn up.'],
            ],
            'process' => [
                ['Quality chick selection', 'Chicks from selected breeders, checked on arrival.'], ['Proper feeding', 'Measured rations for each stage of growth.'], ['Health monitoring', 'Daily checks and mortality records.'],
                ['Vaccination and care', 'A scheduled programme for every batch.'], ['Quality production', 'Birds and eggs checked before sale.'], ['Product delivery', 'On time, to your door or dock.'],
            ],
        ];
        foreach ($blocks as $type => $rows) {
            foreach ($rows as $i => $r) {
                DB::insert('content_blocks', ['block_type' => $type, 'title' => $r[0], 'summary' => $r[1], 'icon' => $r[2] ?? null, 'image' => $r[3] ?? null, 'sort_order' => $i]);
            }
        }
        $gallery = [
            ['Inside a broiler shed', 'sheds', 'images/site/hero-shed.jpg'], ['Layer house with feed lines', 'sheds', 'images/site/layer-house.jpg'], ['Hens out on grass', 'chickens', 'images/site/hens-field.jpg'], ['A flock in the shed', 'chickens', 'images/site/broiler-flock.jpg'],
            ['Eggs ready for packing', 'eggs', 'images/site/eggs-tray.jpg'], ['Chicks under the brooder', 'activities', 'images/site/chicks-brooder.jpg'], ['Chicks arriving', 'activities', 'images/site/chicks-box.jpg'], ['Hens at the fence', 'farm', 'images/site/hens-fence.jpg'],
            ['Fresh eggs in a nest', 'eggs', 'images/site/eggs-nest.jpg'], ['A young chick', 'chickens', 'images/site/chick-grass.jpg'], ['Mixed eggs in a tray', 'eggs', 'images/site/eggs-mixed.jpg'], ['Out in the yard', 'farm', 'images/site/hen-yard.jpg'],
        ];
        foreach ($gallery as $i => [$t, $c, $img]) {
            DB::insert('gallery', ['title' => $t, 'category' => $c, 'image' => $img, 'sort_order' => $i]);
        }
    }
}
