<?php
namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Database as DB;
use App\Core\Uploader;
use App\Core\ValidationException;

/** Key/value settings: website content for the CMS and system options for Super Admin. */
class SettingsController extends AdminController
{
    public const WEBSITE = [
        'Brand' => [
            'company_name' => ['Company name', 'text'], 'company_tagline' => ['Tagline', 'text'], 'logo' => ['Logo', 'image'], 'favicon' => ['Browser icon', 'image'],
        ],
        'Hero' => [
            'hero_title' => ['Headline', 'text'], 'hero_text' => ['Supporting text', 'textarea'], 'hero_image' => ['Background image', 'image'],
            'hero_cta_primary' => ['First button', 'text'], 'hero_cta_secondary' => ['Second button', 'text'],
        ],
        'About' => [
            'about_title' => ['Heading', 'text'], 'about_intro' => ['Introduction', 'textarea'], 'about_history' => ['Company history', 'textarea'], 'about_mission' => ['Mission', 'textarea'],
            'about_vision' => ['Vision', 'textarea'], 'about_standards' => ['Farming standards', 'textarea'], 'about_quality' => ['Quality assurance', 'textarea'], 'about_image' => ['Image', 'image'],
        ],
        'Statistics' => [
            'stat_birds' => ['Total birds', 'number'], 'stat_farms' => ['Total farms', 'number'], 'stat_customers' => ['Total customers', 'number'], 'stat_years' => ['Years of experience', 'number'],
        ],
        'Contact' => [
            'contact_email' => ['Business email', 'text'], 'contact_phone' => ['Phone', 'text'], 'contact_whatsapp' => ['WhatsApp number', 'text', 'Digits with country code, such as 923001234567.'],
            'contact_address' => ['Farm address', 'textarea'], 'contact_map_embed' => ['Google Maps link', 'text', 'The src address from Google Maps > Share > Embed a map.'],
        ],
        'Social' => [
            'social_facebook' => ['Facebook', 'text'], 'social_instagram' => ['Instagram', 'text'], 'social_youtube' => ['YouTube', 'text'], 'social_linkedin' => ['LinkedIn', 'text'],
        ],
        'SEO' => [
            'seo_title' => ['Site title', 'text'], 'seo_description' => ['Site description', 'textarea'], 'seo_keywords' => ['Keywords', 'text'],
        ],
        'Footer' => [
            'footer_about' => ['Company introduction', 'textarea'], 'footer_copyright' => ['Copyright line', 'text'],
        ],
        'Homepage sections' => [
            'show_about' => ['About', 'toggle'], 'show_products' => ['Products', 'toggle'], 'show_services' => ['Services', 'toggle'], 'show_facilities' => ['Facilities', 'toggle'],
            'show_why' => ['Why choose us', 'toggle'], 'show_process' => ['Farming process', 'toggle'], 'show_stats' => ['Statistics', 'toggle'], 'show_gallery' => ['Gallery', 'toggle'],
            'show_testimonials' => ['Testimonials', 'toggle'], 'show_blog' => ['Blog', 'toggle'], 'show_contact' => ['Contact', 'toggle'],
        ],
    ];

    public const SYSTEM = [
        'Finance' => [
            'currency' => ['Currency code', 'text'], 'default_tax_rate' => ['Default sales tax (%)', 'number'],
        ],
        'Alerts' => [
            'mortality_alert_pct' => ['Mortality alert: batch above (%)', 'number'], 'vaccination_reminder_days' => ['Vaccination reminder: days ahead', 'number'], 'expiry_alert_days' => ['Medicine expiry alert: days ahead', 'number'],
        ],
    ];

    public function website(): void
    {
        $this->view('admin/settings/edit', ['title' => 'Website content', 'groups' => self::WEBSITE, 'action' => 'admin/website', 'preview' => true]);
    }

    public function saveWebsite(): void
    {
        $this->save(self::WEBSITE);
        flash('success', 'Website content saved.');
        redirect('admin/website');
    }

    public function system(): void
    {
        $this->view('admin/settings/edit', ['title' => 'System settings', 'groups' => self::SYSTEM, 'action' => 'admin/settings', 'preview' => false]);
    }

    public function saveSystem(): void
    {
        $this->save(self::SYSTEM);
        flash('success', 'Settings saved.');
        redirect('admin/settings');
    }

    private function save(array $groups): void
    {
        $errors = [];
        foreach ($groups as $fields) {
            foreach ($fields as $key => $def) {
                $type = $def[1];
                $value = null;
                if ($type === 'image') {
                    if (Uploader::hasFile($key)) {
                        try {
                            Uploader::deletePublic(setting($key) ?: null);
                            $value = Uploader::image($key, 'site');
                        } catch (ValidationException $e) {
                            $errors += $e->errors;
                            continue;
                        }
                    } elseif (isset($_POST["_remove_$key"])) {
                        Uploader::deletePublic(setting($key) ?: null);
                        $value = '';
                    } else {
                        continue;
                    }
                } elseif ($type === 'toggle') {
                    $value = isset($_POST[$key]) ? '1' : '0';
                } else {
                    $value = trim((string) ($_POST[$key] ?? ''));
                    if ($type === 'number' && $value !== '' && !is_numeric($value)) {
                        $errors[$key] = "{$def[0]} must be a number.";
                        continue;
                    }
                    if ($value !== '' && (str_starts_with($key, 'social_') || $key === 'contact_map_embed') && !preg_match('#^https://#i', $value)) {
                        $errors[$key] = "{$def[0]} must be a link starting with https://.";
                        continue;
                    }
                }
                DB::query('INSERT INTO website_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', [$key, $value]);
            }
        }
        settings_all(true);
        Activity::log('update', 'settings');
        if ($errors) {
            throw new ValidationException($errors);
        }
    }
}
