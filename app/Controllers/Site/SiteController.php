<?php
namespace App\Controllers\Site;

use App\Core\BusinessRuleException;
use App\Core\Database as DB;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Validator;
use App\Core\View;

class SiteController
{
    private function view(string $template, array $data = []): void
    {
        echo View::render("website/$template", $data, 'layouts/site');
    }

    private function blocks(string $type): array
    {
        return DB::all('SELECT * FROM content_blocks WHERE block_type=? AND is_published=1 AND deleted_at IS NULL ORDER BY sort_order, id', [$type]);
    }

    private function productRows(?int $limit = null, ?string $categorySlug = null): array
    {
        $sql = 'SELECT p.*, c.name category_name, c.slug category_slug FROM products p JOIN product_categories c ON c.id=p.category_id WHERE p.is_published=1 AND p.deleted_at IS NULL';
        $params = [];
        if ($categorySlug) {
            $sql .= ' AND c.slug=?';
            $params[] = $categorySlug;
        }
        return DB::all($sql . ' ORDER BY p.sort_order, p.name' . ($limit ? " LIMIT $limit" : ''), $params);
    }

    private function posts(int $limit, int $offset = 0): array
    {
        return DB::all("SELECT p.*, u.name author_name FROM blog_posts p LEFT JOIN users u ON u.id=p.author_id WHERE p.status='published' AND p.deleted_at IS NULL ORDER BY p.published_at DESC LIMIT $limit OFFSET $offset");
    }

    private function galleryRows(?int $limit = null): array
    {
        return DB::all('SELECT * FROM gallery WHERE is_published=1 AND deleted_at IS NULL ORDER BY sort_order, id DESC' . ($limit ? " LIMIT $limit" : ''));
    }

    public function home(): void
    {
        $this->view('home', [
            'products' => $this->productRows(6),
            'services' => $this->blocks('service'),
            'facilities' => $this->blocks('facility'),
            'why' => $this->blocks('why'),
            'process' => $this->blocks('process'),
            'gallery' => array_values(array_filter($this->galleryRows(7), fn ($g) => $g['media_type'] === 'image')),
            'testimonials' => DB::all('SELECT * FROM testimonials WHERE is_published=1 AND deleted_at IS NULL ORDER BY sort_order, id DESC LIMIT 3'),
            'posts' => $this->posts(3),
            'navHome' => true,
        ]);
    }

    public function about(): void
    {
        $this->view('about', ['pageTitle' => 'About us', 'why' => $this->blocks('why'), 'process' => $this->blocks('process')]);
    }

    public function farms(): void
    {
        $this->view('farms', ['pageTitle' => 'Our farms', 'farms' => DB::all("SELECT id, name, location, capacity, description, image FROM farms WHERE is_public=1 AND status='active' AND deleted_at IS NULL ORDER BY name")]);
    }

    public function products(): void
    {
        $category = Request::query("category") ?: null;
        $this->view("products", [
            "pageTitle" => "Our products", "category" => $category, "products" => $this->productRows(null, $category),
            "categories" => DB::all("SELECT name, slug FROM product_categories WHERE deleted_at IS NULL ORDER BY sort_order, name"),
        ]);
    }

    public function product(string $slug): void
    {
        $p = DB::one("SELECT p.*, c.name category_name FROM products p JOIN product_categories c ON c.id=p.category_id WHERE p.slug=? AND p.is_published=1 AND p.deleted_at IS NULL", [$slug]);
        if (!$p) {
            throw new NotFoundException();
        }
        $related = DB::all("SELECT p.*, c.name category_name FROM products p JOIN product_categories c ON c.id=p.category_id WHERE p.is_published=1 AND p.deleted_at IS NULL AND p.id<>? ORDER BY p.category_id=? DESC, p.sort_order LIMIT 3", [$p["id"], $p["category_id"]]);
        $this->view("product", ["pageTitle" => $p["name"], "metaDescription" => $p["summary"], "ogImage" => $p["image"], "p" => $p, "related" => $related]);
    }

    public function gallery(): void
    {
        $this->view("gallery", ["pageTitle" => "Gallery", "items" => $this->galleryRows()]);
    }

    public function services(): void
    {
        $this->view('services', ['pageTitle' => 'Our services', 'services' => $this->blocks('service')]);
    }

    public function facilities(): void
    {
        $this->view('facilities', ['pageTitle' => 'Farm facilities', 'facilities' => $this->blocks('facility')]);
    }

    public function blog(): void
    {
        $page = max(1, Request::int('page', 1));
        $perPage = 9;
        $total = (int) DB::value("SELECT COUNT(*) FROM blog_posts WHERE status='published' AND deleted_at IS NULL");
        $this->view('blog', ['pageTitle' => 'Blog and news', 'posts' => $this->posts($perPage, ($page - 1) * $perPage), 'page' => $page, 'pages' => (int) max(1, ceil($total / $perPage))]);
    }

    public function post(string $slug): void
    {
        $post = DB::one("SELECT p.*, u.name author_name FROM blog_posts p LEFT JOIN users u ON u.id=p.author_id WHERE p.slug=? AND p.status='published' AND p.deleted_at IS NULL", [$slug]);
        if (!$post) {
            throw new NotFoundException();
        }
        $this->view('post', [
            'post' => $post, 'pageTitle' => $post['meta_title'] ?: $post['title'], 'metaDescription' => $post['meta_description'] ?: $post['excerpt'],
            'ogImage' => $post['image'], 'more' => DB::all("SELECT title, slug, image, published_at FROM blog_posts WHERE status='published' AND deleted_at IS NULL AND id<>? ORDER BY published_at DESC LIMIT 3", [$post['id']]),
        ]);
    }

    public function contact(): void
    {
        $this->view('contact', ['pageTitle' => 'Contact us']);
    }

    public function quote(): void
    {
        $this->view('quote', ['pageTitle' => 'Get a quote', 'products' => $this->productRows()]);
    }

    public function sendMessage(): void
    {
        // A hidden field that people never see: anything that fills it in is a bot, and gets a normal-looking reply.
        if (!empty($_POST['website'])) {
            flash('success', 'Thank you. We will be in touch soon.');
            redirect('contact');
        }
        $ip = Request::ip();
        $recent = (int) DB::value('SELECT COUNT(*) FROM contact_messages WHERE ip_address=? AND created_at>DATE_SUB(NOW(), INTERVAL 1 HOUR)', [$ip]);
        if ($recent >= 8) {
            throw new BusinessRuleException('You have sent several messages recently. Please call or message us on WhatsApp instead.');
        }
        $d = Validator::check($_POST, [
            'name' => 'required|max:120', 'email' => 'email|max:160', 'phone' => 'phone', 'subject' => 'max:200', 'message' => 'required|max:5000',
            'message_type' => 'in:contact,quote,inquiry', 'product_id' => 'int',
        ], ['name' => 'Your name', 'message' => 'Your message']);
        if (!$d['email'] && !$d['phone']) {
            throw new BusinessRuleException('Enter an email address or a phone number so we can reply.');
        }
        $product = $d['product_id'] ? DB::value('SELECT id FROM products WHERE id=? AND is_published=1 AND deleted_at IS NULL', [$d['product_id']]) : null;
        DB::insert('contact_messages', [
            'message_type' => $d['message_type'] ?: 'contact', 'product_id' => $product, 'name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'],
            'subject' => $d['subject'], 'message' => $d['message'], 'ip_address' => $ip,
        ]);
        flash('success', 'Thank you. Your message has reached our team and we will reply soon.');
        $back = $_SERVER['HTTP_REFERER'] ?? '';
        $same = $back && parse_url($back, PHP_URL_HOST) === parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        header('Location: ' . ($same ? $back : url('contact')));
        exit;
    }

    public function subscribe(): void
    {
        $d = Validator::check($_POST, ['email' => 'required|email|max:160'], ['email' => 'Email']);
        DB::query('INSERT INTO newsletter_subscribers (email) VALUES (?) ON DUPLICATE KEY UPDATE deleted_at=NULL', [strtolower($d['email'])]);
        flash('success', 'Thank you for subscribing.');
        $back = $_SERVER['HTTP_REFERER'] ?? '';
        $same = $back && parse_url($back, PHP_URL_HOST) === parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        header('Location: ' . ($same ? $back : url('')));
        exit;
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $urls = ['', 'about', 'farms', 'products', 'services', 'facilities', 'gallery', 'blog', 'contact', 'quote'];
        foreach (DB::all('SELECT slug FROM products WHERE is_published=1 AND deleted_at IS NULL') as $p) {
            $urls[] = 'products/' . $p['slug'];
        }
        foreach (DB::all("SELECT slug FROM blog_posts WHERE status='published' AND deleted_at IS NULL") as $p) {
            $urls[] = 'blog/' . $p['slug'];
        }
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            echo '<url><loc>' . e(absolute_url($u)) . '</loc></url>';
        }
        echo '</urlset>';
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: " . url('admin') . "\nSitemap: " . absolute_url('sitemap.xml') . "\n";
    }
}
