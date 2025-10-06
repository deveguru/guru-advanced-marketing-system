<?php
/**
 * Plugin Name:       Guru Advanced Marketing System (Golden Record Edition)
 * Plugin URI:        https://github.com/deveguru
 * Description:       A professional shortcode-based marketing system with a customizable and persistent marketing bar.
 * Version:           11.3.0
 * Author:            Alireza Fatemi (Refactored by Professional Programmer)
 * Author URI:        https://alirezafatemi.ir
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gams-pro-final
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>سیستم بازاریابی پیشرفته گورو:</strong> برای فعال‌سازی این افزونه، ووکامرس باید نصب و فعال باشد.</p></div>';
    });
    return;
}

final class Guru_Advanced_Marketing_System_Final {

    const VERSION = '11.3.0';
    const TEXT_DOMAIN = 'gams-pro-final';
    const UTM_SOURCE_VAL   = 'gams';
    const UTM_CAMPAIGN_VAR = 'utm_campaign';
    const UTM_CONTENT_VAR  = 'utm_content';
    const SESSION_KEY      = 'gams_referral_data';
    const BAR_STYLE_OPTION_KEY = 'gams_bar_style_options';
    const MARKETER_ROLE = 'marketer';
    const META_MARKETER_ID = '_gams_marketer_id';
    const META_LINK_ID = '_gams_link_id';
    const META_COMMISSION_TOTAL = '_gams_commission_total';
    const META_COMMISSION_STATUS = '_gams_commission_status';
    const META_COMMISSION_PROCESSED = '_gams_commission_processed';

    private static $_instance = null;
    private $db_links_table;
    private $db_visits_table;
    private $db_payouts_table;
    private static $shortcode_assets_enqueued = false;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        $this->define_db_tables();
        $this->hooks();
    }
    
    private function define_db_tables() {
        global $wpdb;
        $this->db_links_table = $wpdb->prefix . 'gams_links';
        $this->db_visits_table = $wpdb->prefix . 'gams_visits';
        $this->db_payouts_table = $wpdb->prefix . 'gams_payouts';
    }

    private function hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('template_redirect', [$this, 'handle_referral_visit_and_set_session']);
        add_action('woocommerce_checkout_create_order', [$this, 'save_referral_data_to_order_meta'], 10, 2);
        add_action('woocommerce_order_status_processing', [$this, 'process_order_commission'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this, 'process_order_commission'], 10, 1);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'load_admin_scripts']);
        add_shortcode('marketer_dashboard', [$this, 'render_marketer_dashboard']);
        add_shortcode('marketing_dynamic_link', [$this, 'render_dynamic_link_shortcode']);
    }

    public function activate() {
        $this->setup_roles();
        $this->create_db_tables();
        add_option('gams_global_commission_rate', '10');
        $default_styles = [
            'bar_bg' => '#2d3436', 'bar_height' => '65', 'text_color' => '#ffffff',
            'btn_bg_start' => '#00CED1', 'btn_bg_end' => '#40E0D0', 'btn_text_color' => '#ffffff',
            'border_radius' => '12',
        ];
        add_option(self::BAR_STYLE_OPTION_KEY, $default_styles);
        flush_rewrite_rules();
    }
    
    public function render_dynamic_link_shortcode($atts) {
        if (!self::$shortcode_assets_enqueued) {
            add_action('wp_footer', [$this, 'enqueue_shortcode_frontend_assets']);
            self::$shortcode_assets_enqueued = true;
        }

        global $post;
        if (!is_a($post, 'WP_Post')) return '';

        $post_id = $post->ID;
        $share_url = $this->get_dynamic_share_link();
        $user = wp_get_current_user();
        $is_marketer = $user->ID && in_array(self::MARKETER_ROLE, $user->roles);
        $button_text = $is_marketer ? esc_html__('دریافت لینک بازاریابی', self::TEXT_DOMAIN) : esc_html__('اشتراک‌گذاری', self::TEXT_DOMAIN);
        $post_title = get_the_title($post_id);
        $thumbnail_url = get_the_post_thumbnail_url($post_id, 'thumbnail') ?: (function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src() : '');
        $defaults = [
            'bar_bg' => '#2d3436', 'bar_height' => '65', 'text_color' => '#ffffff',
            'btn_bg_start' => '#00CED1', 'btn_bg_end' => '#40E0D0', 'btn_text_color' => '#ffffff',
            'border_radius' => '12',
        ];
        $styles = get_option(self::BAR_STYLE_OPTION_KEY, $defaults);
        
        ob_start();
        $this->render_dynamic_link_styles($styles);
        ?>
        <div class="gams-shortcode-bar">
            <div class="bar-info">
                <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php echo esc_attr($post_title); ?>">
                <h5><?php echo esc_html($post_title); ?></h5>
            </div>
            <button class="bar-button gams-share-trigger" data-link="<?php echo esc_url($share_url); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.499 2.499 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5z"/></svg>
                <span class="gams-button-text"><?php echo $button_text; ?></span>
                <span class="gams-tooltip"><?php esc_html_e('کپی شد!', self::TEXT_DOMAIN); ?></span>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_referral_visit_and_set_session() {
        if (is_admin() || !isset($_GET['utm_source']) || $_GET['utm_source'] !== self::UTM_SOURCE_VAL || !isset($_GET[self::UTM_CAMPAIGN_VAR])) return;
        
        global $wpdb;
        $marketer_id = absint($_GET[self::UTM_CAMPAIGN_VAR]);
        $custom_slug = isset($_GET[self::UTM_CONTENT_VAR]) ? sanitize_text_field($_GET[self::UTM_CONTENT_VAR]) : null;
        $marketer = get_user_by('id', $marketer_id);
        
        if (!$marketer || !in_array(self::MARKETER_ROLE, $marketer->roles)) return;
        
        $link_id = 0;
        $redirect_url = home_url('/');

        if ($custom_slug) {
            if (strpos($custom_slug, 'product-') === 0) {
                $product_id = absint(str_replace('product-', '', $custom_slug));
                if ($product_id > 0 && get_post_type($product_id) === 'product' && get_post_status($product_id) === 'publish') {
                    $redirect_url = get_permalink($product_id);
                }
            } else {
                $link = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->db_links_table} WHERE custom_slug = %s AND marketer_id = %d", $custom_slug, $marketer_id));
                if ($link) {
                    if (($link->expire_at && strtotime($link->expire_at) < time()) || ($link->max_uses > 0 && $link->use_count >= $link->max_uses)) return;
                    
                    $link_id = $link->link_id;
                    $wpdb->update($this->db_links_table, ['use_count' => $link->use_count + 1], ['link_id' => $link_id]);
                    $wpdb->insert($this->db_visits_table, ['link_id' => $link_id, 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                    
                    if ($link->product_id && ($product_permalink = get_permalink($link->product_id))) {
                        $redirect_url = $product_permalink;
                    }
                }
            }
        }
        
        if (function_exists('WC') && WC()->session) {
            WC()->session->set(self::SESSION_KEY, ['mid' => $marketer_id, 'lid' => $link_id]);
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    public function save_referral_data_to_order_meta($order, $data) {
        if (function_exists('WC') && WC()->session) {
            $referral_data = WC()->session->get(self::SESSION_KEY);
            if (!empty($referral_data) && isset($referral_data['mid'])) {
                $order->update_meta_data(self::META_MARKETER_ID, absint($referral_data['mid']));
                $order->update_meta_data(self::META_LINK_ID, absint($referral_data['lid']));
            }
        }
    }

    public function process_order_commission($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta(self::META_COMMISSION_PROCESSED)) return;
        
        $marketer_id = $order->get_meta(self::META_MARKETER_ID);
        if (empty($marketer_id)) return;

        $marketer = get_user_by('id', $marketer_id);
        if (!$marketer || !in_array(self::MARKETER_ROLE, $marketer->roles)) return;

        $total_commission = 0;
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_variation_id() ?: $item->get_product_id();
            $rate = $this->get_commission_rate_for_product($product_id);
            $total_commission += $item->get_total() * ($rate / 100);
        }

        if ($total_commission > 0) {
            $order->update_meta_data(self::META_COMMISSION_TOTAL, $total_commission);
            $order->update_meta_data(self::META_COMMISSION_STATUS, 'unpaid');
            $order->update_meta_data(self::META_COMMISSION_PROCESSED, true);
            $order->save();
            delete_transient('gams_stats_' . $marketer_id);
            if (function_exists('WC') && WC()->session) {
                WC()->session->__unset(self::SESSION_KEY);
            }
        }
    }
    
    public function render_marketer_dashboard() {
        if (!is_user_logged_in() || (!current_user_can(self::MARKETER_ROLE) && !current_user_can('manage_options'))) {
            return '<div class="gams-pro-wrapper"><p>' . esc_html__('این داشبورد فقط برای همکاران بازاریاب و مدیران قابل مشاهده است.', self::TEXT_DOMAIN) . '</p></div>';
        }

        // Use a UMD version of Chart.js to avoid module loading errors
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.umd.min.js', [], '3.9.1', true);
        
        $user_id = get_current_user_id();
        $this->handle_dashboard_form_submissions($user_id);
        
        ob_start();
        ?>
        <div class="gams-pro-wrapper">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
            <?php $this->display_flash_message(); ?>
            <div class="gams-tabs">
                <a href="#overview" class="gams-tab-link active" data-tab="overview"><i class="bi bi-grid-1x2-fill"></i> <?php esc_html_e('نمای کلی', self::TEXT_DOMAIN); ?></a>
                <a href="#links" class="gams-tab-link" data-tab="links"><i class="bi bi-link-45deg"></i> <?php esc_html_e('مدیریت لینک‌ها', self::TEXT_DOMAIN); ?></a>
                <a href="#analytics" class="gams-tab-link" data-tab="analytics"><i class="bi bi-graph-up"></i> <?php esc_html_e('آمار و رهگیری', self::TEXT_DOMAIN); ?></a>
                <a href="#payouts" class="gams-tab-link" data-tab="payouts"><i class="bi bi-wallet2"></i> <?php esc_html_e('درآمد و واریز', self::TEXT_DOMAIN); ?></a>
            </div>
            <div id="overview" class="gams-tab-content active"><?php echo $this->render_tab_overview($user_id); ?></div>
            <div id="links" class="gams-tab-content"><?php echo $this->render_tab_links($user_id); ?></div>
            <div id="analytics" class="gams-tab-content"><?php echo $this->render_tab_analytics($user_id); ?></div>
            <div id="payouts" class="gams-tab-content"><?php echo $this->render_tab_payouts($user_id); ?></div>
            <div id="gams-qr-modal" class="gams-modal"><div class="gams-modal-overlay"></div><div class="gams-modal-content"><button class="gams-modal-close">&times;</button><img id="gams-qr-image" src="" alt="QR Code"></div></div>
        </div>
        <?php
        echo $this->get_dashboard_styles();
        echo $this->get_dashboard_scripts($this->get_marketer_stats($user_id)['chart_data']);
        return ob_get_clean();
    }
    
    public function add_admin_menu() {
        add_menu_page(__('بازاریابی', self::TEXT_DOMAIN), __('بازاریابی', self::TEXT_DOMAIN), 'manage_options', 'gams-payouts', [$this, 'render_admin_payouts_page'], 'dashicons-groups', 25);
        add_submenu_page('gams-payouts', __('درخواست‌های واریز', self::TEXT_DOMAIN), __('درخواست‌های واریز', self::TEXT_DOMAIN), 'manage_options', 'gams-payouts', [$this, 'render_admin_payouts_page']);
        add_submenu_page('gams-payouts', __('مدیریت بازاریابان', self::TEXT_DOMAIN), __('مدیریت بازاریابان', self::TEXT_DOMAIN), 'manage_options', 'gams-marketers', [$this, 'render_admin_marketers_page']);
        add_submenu_page('gams-payouts', __('تنظیمات کمیسیون', self::TEXT_DOMAIN), __('تنظیمات کمیسیون', self::TEXT_DOMAIN), 'manage_options', 'gams-settings', [$this, 'render_admin_settings_page']);
        add_submenu_page('gams-payouts', __('تنظیمات ظاهری', self::TEXT_DOMAIN), __('تنظیمات ظاهری', self::TEXT_DOMAIN), 'manage_options', 'gams-appearance', [$this, 'render_admin_appearance_page']);
    }

    public function load_admin_scripts($hook) {
        if (strpos($hook, 'gams-') !== false) {
            wp_enqueue_script('wc-enhanced-select');
            wp_enqueue_style('woocommerce_admin_styles');
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
        }
    }

    public function render_admin_appearance_page() {
        if (isset($_POST['gams_save_appearance']) && check_admin_referer('gams_appearance_nonce')) {
            $styles = [
                'bar_bg'         => sanitize_hex_color($_POST['bar_bg']), 'bar_height'     => absint($_POST['bar_height']),
                'text_color'     => sanitize_hex_color($_POST['text_color']), 'btn_bg_start'   => sanitize_hex_color($_POST['btn_bg_start']),
                'btn_bg_end'     => sanitize_hex_color($_POST['btn_bg_end']), 'btn_text_color' => sanitize_hex_color($_POST['btn_text_color']),
                'border_radius'  => absint($_POST['border_radius']),
            ];
            update_option(self::BAR_STYLE_OPTION_KEY, $styles);
            echo '<div class="updated"><p>' . esc_html__('تنظیمات ظاهری با موفقیت ذخیره شد.', self::TEXT_DOMAIN) . '</p></div>';
        }
        $styles = get_option(self::BAR_STYLE_OPTION_KEY);
        ?>
        <div class="wrap"><h1><?php esc_html_e('تنظیمات ظاهری نوار بازاریابی', self::TEXT_DOMAIN); ?></h1><p><?php esc_html_e('در این بخش می‌توانید ظاهر نواری که با شورت‌کد [marketing_dynamic_link] نمایش داده می‌شود را سفارشی‌سازی کنید.', self::TEXT_DOMAIN); ?></p><form method="post" action=""><?php wp_nonce_field('gams_appearance_nonce'); ?> <table class="form-table">
            <tr valign="top"><th scope="row"><label for="bar_bg"><?php esc_html_e('رنگ پس‌زمینه نوار', self::TEXT_DOMAIN); ?></label></th><td><input class="gams-color-picker" type="text" id="bar_bg" name="bar_bg" value="<?php echo esc_attr($styles['bar_bg']); ?>" /></td></tr>
            <tr valign="top"><th scope="row"><label for="text_color"><?php esc_html_e('رنگ متن عنوان', self::TEXT_DOMAIN); ?></label></th><td><input class="gams-color-picker" type="text" id="text_color" name="text_color" value="<?php echo esc_attr($styles['text_color']); ?>" /></td></tr>
            <tr valign="top"><th scope="row"><label for="bar_height"><?php esc_html_e('ارتفاع نوار (پیکسل)', self::TEXT_DOMAIN); ?></label></th><td><input type="number" id="bar_height" name="bar_height" value="<?php echo esc_attr($styles['bar_height']); ?>" min="40" max="150" /></td></tr>
            <tr valign="top"><th scope="row"><label for="border_radius"><?php esc_html_e('گردی گوشه‌ها (پیکسل)', self::TEXT_DOMAIN); ?></label></th><td><input type="number" id="border_radius" name="border_radius" value="<?php echo esc_attr($styles['border_radius']); ?>" min="0" max="50" /></td></tr>
            <tr valign="top"><th scope="row"><h3><?php esc_html_e('تنظیمات دکمه', self::TEXT_DOMAIN); ?></h3></th><td></td></tr>
            <tr valign="top"><th scope="row"><label for="btn_bg_start"><?php esc_html_e('رنگ شروع گرادینت دکمه', self::TEXT_DOMAIN); ?></label></th><td><input class="gams-color-picker" type="text" id="btn_bg_start" name="btn_bg_start" value="<?php echo esc_attr($styles['btn_bg_start']); ?>" /></td></tr>
            <tr valign="top"><th scope="row"><label for="btn_bg_end"><?php esc_html_e('رنگ پایان گرادینت دکمه', self::TEXT_DOMAIN); ?></label></th><td><input class="gams-color-picker" type="text" id="btn_bg_end" name="btn_bg_end" value="<?php echo esc_attr($styles['btn_bg_end']); ?>" /></td></tr>
            <tr valign="top"><th scope="row"><label for="btn_text_color"><?php esc_html_e('رنگ متن دکمه', self::TEXT_DOMAIN); ?></label></th><td><input class="gams-color-picker" type="text" id="btn_text_color" name="btn_text_color" value="<?php echo esc_attr($styles['btn_text_color']); ?>" /></td></tr>
        </table> <?php submit_button(__('ذخیره تنظیمات', self::TEXT_DOMAIN), 'primary', 'gams_save_appearance'); ?> </form>
        <script>jQuery(document).ready(function($){$('.gams-color-picker').wpColorPicker();});</script></div>
        <?php
    }

    public function render_admin_settings_page() { if (isset($_POST['gams_save_settings']) && check_admin_referer('gams_settings_nonce')) { update_option('gams_global_commission_rate', sanitize_text_field($_POST['gams_global_commission_rate'])); $cat_rates = []; if (isset($_POST['category_rates']) && is_array($_POST['category_rates'])) { foreach ($_POST['category_rates'] as $cat_id => $rate) { if ($rate !== '' && is_numeric($rate)) { $cat_rates[absint($cat_id)] = floatval($rate); } } } update_option('gams_category_commission_rates', $cat_rates); $tag_rates = []; if (isset($_POST['tag_rates']) && is_array($_POST['tag_rates'])) { foreach ($_POST['tag_rates'] as $tag_id => $rate) { if ($rate !== '' && is_numeric($rate)) { $tag_rates[absint($tag_id)] = floatval($rate); } } } update_option('gams_tag_commission_rates', $tag_rates); $prod_rates = get_option('gams_product_commission_rates', []); if (isset($_POST['new_product_rate_id']) && !empty($_POST['new_product_rate_id']) && isset($_POST['new_product_rate_value']) && $_POST['new_product_rate_value'] !== '' && is_numeric($_POST['new_product_rate_value'])) { $prod_rates[absint($_POST['new_product_rate_id'])] = floatval($_POST['new_product_rate_value']); } if (isset($_POST['remove_product_rate'])) { unset($prod_rates[absint($_POST['remove_product_rate'])]); } update_option('gams_product_commission_rates', $prod_rates); echo '<div class="updated"><p>' . esc_html__('تنظیمات با موفقیت ذخیره شد.', self::TEXT_DOMAIN) . '</p></div>'; } $global_rate = get_option('gams_global_commission_rate', 10); $category_rates = get_option('gams_category_commission_rates', []); $tag_rates = get_option('gams_tag_commission_rates', []); $product_rates = get_option('gams_product_commission_rates', []); ?> <div class="wrap"><h1><?php esc_html_e('تنظیمات کمیسیون بازاریابی', self::TEXT_DOMAIN); ?></h1><form method="post" action=""><?php wp_nonce_field('gams_settings_nonce'); ?> <div class="card"><h2 class="title"><?php esc_html_e('کمیسیون عمومی', self::TEXT_DOMAIN); ?></h2><p><?php esc_html_e('این درصد برای تمام محصولاتی که تنظیمات خاصی ندارند اعمال می‌شود.', self::TEXT_DOMAIN); ?></p><table class="form-table"><tr><th scope="row"><label for="gams_global_commission_rate"><?php esc_html_e('درصد کمیسیون عمومی (%)', self::TEXT_DOMAIN); ?></label></th><td><input type="number" step="0.1" min="0" id="gams_global_commission_rate" name="gams_global_commission_rate" value="<?php echo esc_attr($global_rate); ?>" /></td></tr></table></div> <div class="card"><h2 class="title"><?php esc_html_e('کمیسیون بر اساس دسته‌بندی', self::TEXT_DOMAIN); ?></h2><table class="form-table"><?php $categories = get_terms('product_cat', ['hide_empty' => false]); foreach ($categories as $category): ?><tr><th scope="row"><label for="cat-rate-<?php echo esc_attr($category->term_id); ?>"><?php echo esc_html($category->name); ?></label></th><td><input type="number" step="0.1" min="0" id="cat-rate-<?php echo esc_attr($category->term_id); ?>" name="category_rates[<?php echo esc_attr($category->term_id); ?>]" value="<?php echo esc_attr($category_rates[$category->term_id] ?? ''); ?>" placeholder="<?php esc_attr_e('پیش‌فرض', self::TEXT_DOMAIN); ?>" /> %</td></tr><?php endforeach; ?></table></div> <div class="card"><h2 class="title"><?php esc_html_e('کمیسیون بر اساس برچسب محصول', self::TEXT_DOMAIN); ?></h2><table class="form-table"><?php $tags = get_terms('product_tag', ['hide_empty' => false]); foreach ($tags as $tag): ?><tr><th scope="row"><label for="tag-rate-<?php echo esc_attr($tag->term_id); ?>"><?php echo esc_html($tag->name); ?></label></th><td><input type="number" step="0.1" min="0" id="tag-rate-<?php echo esc_attr($tag->term_id); ?>" name="tag_rates[<?php echo esc_attr($tag->term_id); ?>]" value="<?php echo esc_attr($tag_rates[$tag->term_id] ?? ''); ?>" placeholder="<?php esc_attr_e('پیش‌فرض', self::TEXT_DOMAIN); ?>" /> %</td></tr><?php endforeach; ?></table></div> <div class="card"><h2 class="title"><?php esc_html_e('کمیسیون برای محصولات خاص', self::TEXT_DOMAIN); ?></h2><p><?php esc_html_e('این تنظیم بالاترین اولویت را دارد.', self::TEXT_DOMAIN); ?></p><table class="form-table"><tr><th scope="row"><?php esc_html_e('افزودن محصول جدید', self::TEXT_DOMAIN); ?></th><td> <select class="wc-product-search" style="width: 300px;" name="new_product_rate_id" data-placeholder="<?php esc_attr_e('یک محصول را جستجو کنید...', self::TEXT_DOMAIN); ?>" data-action="woocommerce_json_search_products_and_variations"></select> <input type="number" step="0.1" min="0" name="new_product_rate_value" placeholder="<?php esc_attr_e('درصد کمیسیون', self::TEXT_DOMAIN); ?>" /> </td></tr></table> <?php if (!empty($product_rates)): ?><h3><?php esc_html_e('کمیسیون‌های تعریف شده:', self::TEXT_DOMAIN); ?></h3><table class="wp-list-table widefat striped"><thead><tr><th><?php esc_html_e('محصول', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('درصد کمیسیون', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('عملیات', self::TEXT_DOMAIN); ?></th></tr></thead><tbody> <?php foreach ($product_rates as $product_id => $rate): $product = wc_get_product($product_id); if (!$product) continue; ?> <tr><td><?php echo esc_html($product->get_name()); ?></td><td><?php echo esc_html($rate); ?>%</td><td><button type="submit" name="remove_product_rate" value="<?php echo esc_attr($product_id); ?>" class="button button-link-delete"><?php esc_html_e('حذف', self::TEXT_DOMAIN); ?></button></td></tr> <?php endforeach; ?></tbody></table><?php endif; ?> </div> <?php submit_button(__('ذخیره تنظیمات', self::TEXT_DOMAIN), 'primary', 'gams_save_settings'); ?> </form></div><?php }
    
    public function render_admin_payouts_page() { global $wpdb; if (isset($_POST['gams_mark_as_paid']) && check_admin_referer('gams_payout_nonce')) { $payout_id = absint($_POST['payout_id']); $payout = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->db_payouts_table} WHERE payout_id = %d", $payout_id)); if ($payout) { $wpdb->update($this->db_payouts_table, ['status' => 'paid', 'paid_at' => current_time('mysql')], ['payout_id' => $payout_id]); $wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value = 'paid' WHERE meta_key = %s AND meta_value = 'pending_payout' AND post_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %d)", self::META_COMMISSION_STATUS, self::META_MARKETER_ID, $payout->marketer_id)); delete_transient('gams_stats_' . $payout->marketer_id); echo '<div class="updated"><p>' . esc_html__('درخواست با موفقیت به عنوان پرداخت شده علامت‌گذاری شد.', self::TEXT_DOMAIN) . '</p></div>'; } } $payouts = $wpdb->get_results("SELECT * FROM {$this->db_payouts_table} ORDER BY request_at DESC"); ?> <div class="wrap"><h1><?php esc_html_e('مدیریت درخواست‌های واریز وجه', self::TEXT_DOMAIN); ?></h1><table class="wp-list-table widefat fixed striped"><thead><tr><th><?php esc_html_e('بازاریاب', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('مبلغ', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('تاریخ درخواست', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('وضعیت', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('اطلاعات حساب بانکی', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('عملیات', self::TEXT_DOMAIN); ?></th></tr></thead><tbody> <?php if(empty($payouts)): ?><tr><td colspan="6"><?php esc_html_e('هیچ درخواست واریزی ثبت نشده است.', self::TEXT_DOMAIN); ?></td></tr><?php else: foreach ($payouts as $payout): $user = get_userdata($payout->marketer_id); $bank_details = json_decode($payout->bank_details, true); ?> <tr><td><?php echo esc_html($user->display_name); ?></td><td><?php echo wc_price($payout->amount); ?></td><td><?php echo date_i18n('Y/m/d H:i', strtotime($payout->request_at)); ?></td><td><?php echo $payout->status === 'paid' ? '<span style="color:green;">' . esc_html__('پرداخت شده', self::TEXT_DOMAIN) . '</span>' : '<span style="color:orange;">' . esc_html__('در انتظار', self::TEXT_DOMAIN) . '</span>'; ?></td> <td><?php if(is_array($bank_details)): ?><strong><?php esc_html_e('صاحب حساب:', self::TEXT_DOMAIN); ?></strong> <?php echo esc_html($bank_details['holder_name']); ?><br><strong><?php esc_html_e('نام بانک:', self::TEXT_DOMAIN); ?></strong> <?php echo esc_html($bank_details['bank_name']); ?><br><strong><?php esc_html_e('شماره کارت:', self::TEXT_DOMAIN); ?></strong> <?php echo esc_html($bank_details['card_number']); ?><br><strong><?php esc_html_e('شماره شبا:', self::TEXT_DOMAIN); ?></strong> IR<?php echo esc_html($bank_details['sheba']); ?><?php endif; ?></td> <td><?php if($payout->status === 'pending'): ?><form method="post" action=""><?php wp_nonce_field('gams_payout_nonce'); ?><input type="hidden" name="payout_id" value="<?php echo esc_attr($payout->payout_id); ?>"><button type="submit" name="gams_mark_as_paid" class="button button-primary"><?php esc_html_e('علامت‌گذاری به عنوان پرداخت شده', self::TEXT_DOMAIN); ?></button></form><?php else: echo esc_html__('پرداخت در:', self::TEXT_DOMAIN) . ' ' . date_i18n('Y/m/d', strtotime($payout->paid_at)); endif; ?></td></tr> <?php endforeach; endif; ?></tbody></table></div><?php }

    public function render_admin_marketers_page() { if (isset($_POST['gams_change_role']) && check_admin_referer('gams_role_nonce')) { $user_id = absint($_POST['user_id']); $new_role = sanitize_text_field($_POST['new_role']); if (get_userdata($user_id) && !empty($new_role)) { $user = new WP_User($user_id); $user->set_role($new_role); echo '<div class="updated"><p>' . esc_html__('نقش کاربر با موفقیت تغییر کرد.', self::TEXT_DOMAIN) . '</p></div>'; } else { echo '<div class="error"><p>' . esc_html__('خطا در تغییر نقش کاربر.', self::TEXT_DOMAIN) . '</p></div>'; } } ?> <div class="wrap"><h1><?php esc_html_e('مدیریت بازاریابان', self::TEXT_DOMAIN); ?></h1> <div class="card" style="margin-bottom: 20px;"><h2 class="title"><?php esc_html_e('تغییر نقش کاربر', self::TEXT_DOMAIN); ?></h2><form method="post" action=""><?php wp_nonce_field('gams_role_nonce'); ?> <table class="form-table"><tr><th scope="row"><label for="user_id"><?php esc_html_e('انتخاب کاربر', self::TEXT_DOMAIN); ?></label></th><td><select name="user_id" id="user_id" required style="width: 25em;"><?php foreach (get_users() as $user): ?><option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->user_login); ?> (<?php esc_html_e('نقش فعلی:', self::TEXT_DOMAIN); ?> <?php echo esc_html(implode(', ', $user->roles)); ?>)</option><?php endforeach; ?></select></td></tr> <tr><th scope="row"><label for="new_role"><?php esc_html_e('نقش جدید', self::TEXT_DOMAIN); ?></label></th><td><select name="new_role" id="new_role" required style="width: 25em;"><?php wp_dropdown_roles(get_option('default_role')); ?><option value="<?php echo self::MARKETER_ROLE; ?>"><?php esc_html_e('همکار بازاریاب', self::TEXT_DOMAIN); ?></option></select></td></tr></table> <?php submit_button(__('تغییر نقش', self::TEXT_DOMAIN), 'primary', 'gams_change_role'); ?></form></div> <h2 class="title"><?php esc_html_e('لیست بازاریابان فعال', self::TEXT_DOMAIN); ?></h2><table class="wp-list-table widefat fixed striped"><thead><tr><th><?php esc_html_e('نام', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('ایمیل', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('تعداد فروش', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('درآمد کل', self::TEXT_DOMAIN); ?></th></tr></thead><tbody> <?php foreach (get_users(['role' => self::MARKETER_ROLE]) as $marketer) { $stats = $this->get_marketer_stats($marketer->ID); echo '<tr><td>' . esc_html($marketer->display_name) . '</td><td>' . esc_html($marketer->user_email) . '</td><td>' . esc_html($stats['sales_count']) . '</td><td>' . wc_price($stats['total_commission']) . '</td></tr>'; } ?> </tbody></table></div><?php }
    
    private function get_dynamic_share_link() {
        global $post;
        $post_id = is_a($post, 'WP_Post') ? $post->ID : 0;
        if (!$post_id) return home_url('/');
        
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array(self::MARKETER_ROLE, $user->roles)) {
                $params = ['utm_source' => self::UTM_SOURCE_VAL, self::UTM_CAMPAIGN_VAR => $user->ID];
                if (get_post_type($post_id) === 'product') {
                    $params[self::UTM_CONTENT_VAR] = 'product-' . $post_id;
                }
                return add_query_arg($params, home_url('/'));
            }
        }
        return get_permalink($post_id);
    }
    
    public function enqueue_shortcode_frontend_assets() {
        ?>
        <script id="gams-frontend-script">
            document.body.addEventListener('click', function(e) {
                const trigger = e.target.closest('.gams-share-trigger');
                if (trigger) {
                    e.preventDefault();
                    const urlToCopy = trigger.dataset.link;
                    if (!urlToCopy) return;
                    navigator.clipboard.writeText(urlToCopy).then(() => {
                        const tooltip = trigger.querySelector('.gams-tooltip');
                        if (tooltip) {
                            tooltip.classList.add('gams-tooltip--visible');
                            setTimeout(() => {
                                tooltip.classList.remove('gams-tooltip--visible');
                            }, 2500);
                        }
                    }).catch(err => {
                        console.error('GAMS: Clipboard copy failed.', err);
                    });
                }
            });
        </script>
        <?php
    }

    private function render_dynamic_link_styles($styles) {
        ?>
        <style>
            .gams-shortcode-bar { direction: rtl; display: flex; align-items: center; justify-content: space-between; width: 100%; background-color: <?php echo esc_attr($styles['bar_bg']); ?>; color: <?php echo esc_attr($styles['text_color']); ?>; height: <?php echo esc_attr($styles['bar_height']); ?>px; padding: 0 20px; border-radius: <?php echo esc_attr($styles['border_radius']); ?>px; box-sizing: border-box; box-shadow: 0 8px 30px rgba(0,0,0,0.12); margin: 2em 0; }
            .gams-shortcode-bar .bar-info { display: flex; align-items: center; gap: 15px; overflow: hidden; }
            .gams-shortcode-bar .bar-info img { width: calc(<?php echo esc_attr($styles['bar_height']); ?>px - 20px); height: calc(<?php echo esc_attr($styles['bar_height']); ?>px - 20px); border-radius: <?php echo esc_attr($styles['border_radius']-4 > 0 ? $styles['border_radius']-4 : 8); ?>px; object-fit: cover; flex-shrink: 0; }
            .gams-shortcode-bar .bar-info h5 { margin: 0; font-size: 1rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: inherit; }
            .gams-shortcode-bar .bar-button { background: linear-gradient(135deg, <?php echo esc_attr($styles['btn_bg_start']); ?> 0%, <?php echo esc_attr($styles['btn_bg_end']); ?> 100%); color: <?php echo esc_attr($styles['btn_text_color']); ?>; border: none; padding: 10px 20px; border-radius: 50px; font-size: 14px; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94); flex-shrink: 0; position: relative; cursor: pointer; }
            .gams-shortcode-bar .bar-button:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2); }
            .gams-shortcode-bar .gams-tooltip { visibility: hidden; opacity: 0; background-color: #2c3e50; color: #fff; text-align: center; border-radius: 6px; padding: 5px 10px; position: absolute; z-index: 10; bottom: 125%; left: 50%; transform: translateX(-50%); transition: opacity 0.3s, visibility 0.3s; font-size: 12px; white-space: nowrap; }
            .gams-shortcode-bar .gams-tooltip.gams-tooltip--visible { visibility: visible; opacity: 1; }
            .gams-shortcode-bar .bar-button svg { width: 16px; height: 16px; }
            @media (max-width: 600px) { 
                .gams-shortcode-bar { padding: 10px 15px; } 
                .gams-shortcode-bar .bar-info { gap: 10px; }
                .gams-shortcode-bar .bar-info h5 { font-size: 0.9rem; }
                .gams-shortcode-bar .bar-button { padding: 10px; gap: 0; }
                .gams-shortcode-bar .bar-button .gams-button-text { display: none; }
            }
        </style>
        <?php
    }
    
    private function get_commission_rate_for_product($product_id) {
        $product_rates = get_option('gams_product_commission_rates', []);
        if (isset($product_rates[$product_id]) && is_numeric($product_rates[$product_id])) return floatval($product_rates[$product_id]);
        $tag_rates = get_option('gams_tag_commission_rates', []);
        $tag_ids = wc_get_product_term_ids($product_id, 'product_tag');
        $highest_tag_rate = -1;
        foreach ($tag_ids as $tag_id) { if (isset($tag_rates[$tag_id]) && is_numeric($tag_rates[$tag_id]) && floatval($tag_rates[$tag_id]) > $highest_tag_rate) { $highest_tag_rate = floatval($tag_rates[$tag_id]); } }
        if ($highest_tag_rate > -1) return $highest_tag_rate;
        $category_rates = get_option('gams_category_commission_rates', []);
        $term_ids = wc_get_product_term_ids($product_id, 'product_cat');
        $highest_cat_rate = -1;
        foreach ($term_ids as $term_id) { if (isset($category_rates[$term_id]) && is_numeric($category_rates[$term_id]) && floatval($category_rates[$term_id]) > $highest_cat_rate) { $highest_cat_rate = floatval($category_rates[$term_id]); } }
        if ($highest_cat_rate > -1) return $highest_cat_rate;
        return floatval(get_option('gams_global_commission_rate', 10));
    }
    
    private function handle_dashboard_form_submissions($user_id) { if ('POST' !== $_SERVER['REQUEST_METHOD'] || !isset($_POST['gams_action_nonce']) || !wp_verify_nonce($_POST['gams_action_nonce'], 'gams_dashboard_action')) return; global $wpdb; $action = sanitize_key($_POST['gams_action'] ?? ''); $feedback = []; switch ($action) { case 'generate_link': $slug = sanitize_title($_POST['custom_slug'] ?? ''); if (empty($slug)) $slug = 'ref-' . uniqid(); if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->db_links_table} WHERE custom_slug = %s", $slug))) { $feedback = ['message' => __('این نامک سفارشی قبلاً استفاده شده است.', self::TEXT_DOMAIN), 'type' => 'error']; } else { $expire_days = absint($_POST['expire_in_days'] ?? 0); $expire_at = ($expire_days > 0) ? date('Y-m-d H:i:s', strtotime("+" . $expire_days . " days")) : null; $wpdb->insert($this->db_links_table, ['marketer_id' => $user_id, 'product_id' => absint($_POST['product_id'] ?? 0), 'custom_slug' => $slug, 'expire_at' => $expire_at, 'max_uses' => absint($_POST['max_uses'] ?? 0)]); $feedback = ['message' => __('لینک بازاریابی با موفقیت ساخته شد.', self::TEXT_DOMAIN), 'type' => 'success']; } break; case 'update_link': $link_id = absint($_POST['link_id'] ?? 0); $link = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->db_links_table} WHERE link_id = %d AND marketer_id = %d", $link_id, $user_id)); if (!$link) { $feedback = ['message' => __('خطا: لینک یافت نشد یا شما اجازه ویرایش آن را ندارید.', self::TEXT_DOMAIN), 'type' => 'error']; } else { $slug = sanitize_title($_POST['custom_slug'] ?? ''); if ($slug !== $link->custom_slug && $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->db_links_table} WHERE custom_slug = %s", $slug))) { $feedback = ['message' => __('این نامک سفارشی قبلاً استفاده شده است.', self::TEXT_DOMAIN), 'type' => 'error']; } else { $expire_days = absint($_POST['expire_in_days'] ?? 0); $expire_at = ($expire_days > 0) ? date('Y-m-d H:i:s', strtotime("+" . $expire_days . " days")) : null; $wpdb->update($this->db_links_table, ['product_id' => absint($_POST['product_id'] ?? 0), 'custom_slug' => $slug, 'expire_at' => $expire_at, 'max_uses' => absint($_POST['max_uses'] ?? 0)], ['link_id' => $link_id]); $feedback = ['message' => __('لینک با موفقیت بروزرسانی شد.', self::TEXT_DOMAIN), 'type' => 'success']; } } break; case 'delete_link': $link_id = absint($_POST['link_id'] ?? 0); if ($wpdb->get_var($wpdb->prepare("SELECT marketer_id FROM {$this->db_links_table} WHERE link_id = %d", $link_id)) == $user_id) { $wpdb->delete($this->db_links_table, ['link_id' => $link_id]); $feedback = ['message' => __('لینک با موفقیت حذف شد.', self::TEXT_DOMAIN), 'type' => 'success']; } else { $feedback = ['message' => __('خطا: شما اجازه حذف این لینک را ندارید.', self::TEXT_DOMAIN), 'type' => 'error']; } break; case 'save_bank': $details = ['holder_name' => sanitize_text_field($_POST['holder_name'] ?? ''), 'bank_name' => sanitize_text_field($_POST['bank_name'] ?? ''), 'card_number' => sanitize_text_field($_POST['card_number'] ?? ''), 'sheba' => sanitize_text_field($_POST['sheba'] ?? '')]; update_user_meta($user_id, 'gams_bank_details', $details); $feedback = ['message' => __('اطلاعات بانکی شما با موفقیت ذخیره شد.', self::TEXT_DOMAIN), 'type' => 'success']; break; case 'request_payout': $stats = $this->get_marketer_stats($user_id); $unpaid_amount = $stats['unpaid_commission']; $bank_details = get_user_meta($user_id, 'gams_bank_details', true); if ($unpaid_amount <= 0) { $feedback = ['message' => __('موجودی شما برای درخواست واریز کافی نیست.', self::TEXT_DOMAIN), 'type' => 'error']; } elseif (empty($bank_details) || empty($bank_details['card_number'])) { $feedback = ['message' => __('لطفاً ابتدا اطلاعات حساب بانکی خود را تکمیل کنید.', self::TEXT_DOMAIN), 'type' => 'error']; } else { $wpdb->insert($this->db_payouts_table, ['marketer_id' => $user_id, 'amount' => $unpaid_amount, 'bank_details' => json_encode($bank_details, JSON_UNESCAPED_UNICODE)]); $wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value = 'pending_payout' WHERE meta_key = %s AND meta_value = 'unpaid' AND post_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %d)", self::META_COMMISSION_STATUS, self::META_MARKETER_ID, $user_id)); delete_transient('gams_stats_' . $user_id); $feedback = ['message' => __('درخواست واریز شما با موفقیت ثبت شد و در انتظار تأیید مدیر است.', self::TEXT_DOMAIN), 'type' => 'success']; } break; } if (!empty($feedback)) { set_transient('gams_flash_message_' . $user_id, $feedback, 30); wp_redirect(esc_url_raw($_SERVER['REQUEST_URI'])); exit; } }
    
    private function render_tab_overview($user_id) { $stats = $this->get_marketer_stats($user_id); $referral_link = add_query_arg([ 'utm_source' => self::UTM_SOURCE_VAL, self::UTM_CAMPAIGN_VAR => $user_id, ], home_url('/')); ob_start(); ?> <h2 class="gams-h2"><?php esc_html_e('نمای کلی بازاریابی', self::TEXT_DOMAIN); ?></h2> <div class="gams-grid"><div class="gams-grid-item"><div class="gams-icon"><i class="bi bi-cash-coin"></i></div><div><h3 class="gams-h3"><?php esc_html_e('درآمد کل', self::TEXT_DOMAIN); ?></h3><p class="gams-p"><?php echo wc_price($stats['total_commission']); ?></p></div></div><div class="gams-grid-item"><div class="gams-icon"><i class="bi bi-cart-check-fill"></i></div><div><h3 class="gams-h3"><?php esc_html_e('تعداد فروش موفق', self::TEXT_DOMAIN); ?></h3><p class="gams-p"><?php echo esc_html($stats['sales_count']); ?></p></div></div><div class="gams-grid-item"><div class="gams-icon"><i class="bi bi-cursor-fill"></i></div><div><h3 class="gams-h3"><?php esc_html_e('تعداد کلیک', self::TEXT_DOMAIN); ?></h3><p class="gams-p"><?php echo esc_html($stats['total_clicks']); ?></p></div></div></div> <div class="gams-card"><h3 class="gams-card-title"><i class="bi bi-link-45deg"></i> <?php esc_html_e('لینک بازاریابی عمومی شما', self::TEXT_DOMAIN); ?></h3><div class="gams-input-group"><input type="text" value="<?php echo esc_url($referral_link); ?>" readonly id="gams-general-link" class="gams-input"><button class="gams-button" onclick="gamsCopy('gams-general-link')"><i class="bi bi-clipboard"></i> <?php esc_html_e('کپی', self::TEXT_DOMAIN); ?></button><button class="gams-button gams-qr-button" onclick="showGamsQrCode('<?php echo esc_js($referral_link); ?>')"><i class="bi bi-qr-code"></i> <?php esc_html_e('کد QR', self::TEXT_DOMAIN); ?></button></div></div> <div class="gams-card"><h3 class="gams-card-title"><i class="bi bi-bar-chart-line-fill"></i> <?php esc_html_e('نمودار درآمد ماهانه', self::TEXT_DOMAIN); ?></h3><div class="gams-chart-container"><canvas id="gamsMonthlyCommissionChart"></canvas></div></div> <?php return ob_get_clean(); }
    
    private function render_tab_links($user_id) {
        global $wpdb;

        // Handle product search
        $searched_products = [];
        $search_term = '';
        if (isset($_POST['gams_product_search_term'])) {
            $search_term = sanitize_text_field($_POST['gams_product_search_term']);
            if (!empty($search_term)) {
                $searched_products = wc_get_products(['s' => $search_term, 'limit' => 50]);
            }
        }

        $links = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->db_links_table} WHERE marketer_id = %d ORDER BY created_at DESC", $user_id));
        ob_start();
        ?>
        <h2 class="gams-h2"><?php esc_html_e('مدیریت لینک‌ها', self::TEXT_DOMAIN); ?></h2>
        <div class="gams-card">
            <h3 class="gams-card-title"><i class="bi bi-plus-circle-fill"></i> <?php esc_html_e('ساخت لینک جدید', self::TEXT_DOMAIN); ?></h3>

            <!-- Product Search Form -->
            <form method="POST" action="#links" style="margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid var(--gams-border-color);">
                <label class="gams-label" for="gams_product_search_term_input"><?php esc_html_e('۱. ابتدا محصول مورد نظر را جستجو کنید (اختیاری)', self::TEXT_DOMAIN); ?></label>
                <div class="gams-input-group">
                    <input type="text" id="gams_product_search_term_input" name="gams_product_search_term" value="<?php echo esc_attr($search_term); ?>" class="gams-input" placeholder="<?php esc_attr_e('نام محصول را وارد کنید...', self::TEXT_DOMAIN); ?>">
                    <button type="submit" class="gams-button"><i class="bi bi-search"></i> <?php esc_html_e('جستجو', self::TEXT_DOMAIN); ?></button>
                </div>
            </form>

            <!-- Link Generation Form -->
            <form method="POST" action="#links">
                <input type="hidden" name="gams_action" value="generate_link">
                <?php wp_nonce_field('gams_dashboard_action', 'gams_action_nonce'); ?>
                <div class="gams-form-grid">
                    <div>
                        <label class="gams-label" for="gams_product_id_select"><?php esc_html_e('۲. محصول را از لیست زیر انتخاب کنید', self::TEXT_DOMAIN); ?></label>
                        <select id="gams_product_id_select" name="product_id" class="gams-input">
                            <option value="0"><?php esc_html_e('لینک کلی سایت (بدون انتخاب محصول)', self::TEXT_DOMAIN); ?></option>
                            <?php if (!empty($searched_products)): ?>
                                <?php foreach ($searched_products as $product): ?>
                                    <option value="<?php echo esc_attr($product->get_id()); ?>"><?php echo esc_html($product->get_name()); ?></option>
                                <?php endforeach; ?>
                            <?php elseif (!empty($search_term)): ?>
                                <option value="" disabled><?php esc_html_e('محصولی با این مشخصات یافت نشد.', self::TEXT_DOMAIN); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="gams-label"><?php esc_html_e('۳. نامک سفارشی (اختیاری)', self::TEXT_DOMAIN); ?></label>
                        <input type="text" name="custom_slug" placeholder="<?php esc_attr_e('مثال: off-yalda', self::TEXT_DOMAIN); ?>" class="gams-input">
                    </div>
                    <div>
                        <label class="gams-label"><?php esc_html_e('انقضا پس از (روز)', self::TEXT_DOMAIN); ?></label>
                        <input type="number" name="expire_in_days" min="0" value="0" placeholder="<?php esc_attr_e('0 برای نامحدود', self::TEXT_DOMAIN); ?>" class="gams-input">
                    </div>
                    <div>
                        <label class="gams-label"><?php esc_html_e('حداکثر استفاده', self::TEXT_DOMAIN); ?></label>
                        <input type="number" name="max_uses" min="0" value="0" placeholder="<?php esc_attr_e('0 برای نامحدود', self::TEXT_DOMAIN); ?>" class="gams-input">
                    </div>
                </div>
                <button type="submit" class="gams-button"><?php esc_html_e('ساخت لینک', self::TEXT_DOMAIN); ?></button>
            </form>
        </div>

        <div class="gams-card">
            <h3 class="gams-card-title"><i class="bi bi-list-task"></i> <?php esc_html_e('لینک‌های ساخته شده', self::TEXT_DOMAIN); ?></h3>
            <div class="gams-table-wrapper">
                <table class="gams-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('لینک', self::TEXT_DOMAIN); ?></th>
                            <th><?php esc_html_e('محصول هدف', self::TEXT_DOMAIN); ?></th>
                            <th><?php esc_html_e('کلیک', self::TEXT_DOMAIN); ?></th>
                            <th><?php esc_html_e('انقضا', self::TEXT_DOMAIN); ?></th>
                            <th><?php esc_html_e('عملیات', self::TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($links)): ?>
                            <tr><td colspan="5"><?php esc_html_e('هنوز هیچ لینکی نساخته‌اید.', self::TEXT_DOMAIN); ?></td></tr>
                        <?php else: foreach ($links as $link): $link_url = add_query_arg([ 'utm_source' => self::UTM_SOURCE_VAL, self::UTM_CAMPAIGN_VAR => $link->marketer_id, self::UTM_CONTENT_VAR => $link->custom_slug ], home_url('/')); ?>
                            <tr id="link-row-<?php echo esc_attr($link->link_id); ?>">
                                <td><input type="text" readonly value="<?php echo esc_url($link_url); ?>" id="link-<?php echo esc_attr($link->link_id); ?>" class="gams-input-readonly"></td>
                                <td><?php echo $link->product_id ? esc_html(get_the_title($link->product_id)) : esc_html__('عمومی', self::TEXT_DOMAIN); ?></td>
                                <td><?php echo esc_html($link->use_count); ?></td>
                                <td><?php echo $link->expire_at ? esc_html(date_i18n('Y/m/d', strtotime($link->expire_at))) : esc_html__('ندارد', self::TEXT_DOMAIN); ?></td>
                                <td>
                                    <button class="gams-button-small" onclick="gamsCopy('link-<?php echo esc_attr($link->link_id); ?>')"><i class="bi bi-clipboard"></i></button>
                                    <button class="gams-button-small" onclick="showGamsQrCode('<?php echo esc_js($link_url); ?>')"><i class="bi bi-qr-code"></i></button>
                                    <button class="gams-button-small" onclick="toggleEditForm('<?php echo esc_attr($link->link_id); ?>')"><i class="bi bi-pencil-square"></i></button>
                                    <form method="POST" action="#links" style="display:inline;" onsubmit="return confirm('<?php echo esc_js(__('آیا از حذف این لینک مطمئن هستید؟', self::TEXT_DOMAIN)); ?>');">
                                        <input type="hidden" name="gams_action" value="delete_link">
                                        <input type="hidden" name="link_id" value="<?php echo esc_attr($link->link_id); ?>">
                                        <?php wp_nonce_field('gams_dashboard_action', 'gams_action_nonce'); ?>
                                        <button type="submit" class="gams-button-small gams-button-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="edit-form-row-<?php echo esc_attr($link->link_id); ?>" style="display:none;">
                                <td colspan="5">
                                    <p style="font-weight:bold;"><?php esc_html_e('توجه: برای تغییر محصول، لطفاً یک لینک جدید بسازید.', self::TEXT_DOMAIN); ?></p>
                                    <form method="POST" action="#links" class="gams-edit-form">
                                        <input type="hidden" name="gams_action" value="update_link">
                                        <input type="hidden" name="link_id" value="<?php echo esc_attr($link->link_id); ?>">
                                        <input type="hidden" name="product_id" value="<?php echo esc_attr($link->product_id); ?>">
                                        <?php wp_nonce_field('gams_dashboard_action', 'gams_action_nonce'); ?>
                                        <div class="gams-form-grid">
                                            <div><label><?php esc_html_e('نامک سفارشی', self::TEXT_DOMAIN); ?></label><input type="text" name="custom_slug" value="<?php echo esc_attr($link->custom_slug); ?>" class="gams-input"></div>
                                            <div><label><?php esc_html_e('انقضا (روز از امروز)', self::TEXT_DOMAIN); ?></label><input type="number" name="expire_in_days" value="<?php echo $link->expire_at && strtotime($link->expire_at) > time() ? floor((strtotime($link->expire_at) - time())/DAY_IN_SECONDS) : 0; ?>" min="0" class="gams-input"></div>
                                            <div><label><?php esc_html_e('حداکثر استفاده', self::TEXT_DOMAIN); ?></label><input type="number" name="max_uses" value="<?php echo esc_attr($link->max_uses); ?>" min="0" class="gams-input"></div>
                                        </div>
                                        <button type="submit" class="gams-button"><?php esc_html_e('ذخیره تغییرات', self::TEXT_DOMAIN); ?></button>
                                        <button type="button" class="gams-button gams-button-secondary" onclick="toggleEditForm('<?php echo esc_attr($link->link_id); ?>')"><?php esc_html_e('انصراف', self::TEXT_DOMAIN); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_tab_analytics($user_id) { $stats = $this->get_marketer_stats($user_id); ob_start(); ?> <h2 class="gams-h2"><?php esc_html_e('آمار و رهگیری', self::TEXT_DOMAIN); ?></h2> <div class="gams-card"><h3 class="gams-card-title"><i class="bi bi-table"></i> <?php esc_html_e('۱۰ فروش آخر شما', self::TEXT_DOMAIN); ?></h3><div class="gams-table-wrapper"><table class="gams-table"><thead><tr><th><?php esc_html_e('شماره سفارش', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('تاریخ', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('مبلغ سفارش', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('کمیسیون شما', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('وضعیت سفارش', self::TEXT_DOMAIN); ?></th></tr></thead><tbody><?php if(empty($stats['recent_sales'])): ?><tr><td colspan="5"><?php esc_html_e('هنوز فروشی برای شما ثبت نشده است.', self::TEXT_DOMAIN); ?></td></tr><?php else: foreach($stats['recent_sales'] as $sale): ?><tr><td>#<?php echo esc_html($sale['order_id']); ?></td><td><?php echo date_i18n('Y/m/d', strtotime($sale['date'])); ?></td><td><?php echo wc_price($sale['total']); ?></td><td><?php echo wc_price($sale['commission']); ?></td><td><?php echo esc_html(wc_get_order_status_name($sale['order_status'])); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div> <div class="gams-card"><h3 class="gams-card-title"><i class="bi bi-link"></i> <?php esc_html_e('گزارش فروش بر اساس لینک', self::TEXT_DOMAIN); ?></h3><div class="gams-table-wrapper"><table class="gams-table"><thead><tr><th><?php esc_html_e('لینک بازاریابی', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('تعداد فروش', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('مجموع کمیسیون', self::TEXT_DOMAIN); ?></th></tr></thead><tbody><?php if(empty($stats['sales_by_link'])): ?><tr><td colspan="3"><?php esc_html_e('اطلاعاتی برای نمایش وجود ندارد.', self::TEXT_DOMAIN); ?></td></tr><?php else: foreach($stats['sales_by_link'] as $link_sale): ?><tr><td><?php echo esc_html($link_sale['slug']); ?></td><td><?php echo esc_html($link_sale['count']); ?></td><td><?php echo wc_price($link_sale['commission']); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div> <div class="gams-card"><h3 class="gams-card-title"><i class="bi bi-basket-fill"></i> <?php esc_html_e('محصولات پرفروش شما', self::TEXT_DOMAIN); ?></h3><div class="gams-table-wrapper"><table class="gams-table"><thead><tr><th><?php esc_html_e('محصول', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('تعداد فروش', self::TEXT_DOMAIN); ?></th></tr></thead><tbody><?php if(empty($stats['top_products'])): ?><tr><td colspan="2"><?php esc_html_e('اطلاعاتی برای نمایش وجود ندارد.', self::TEXT_DOMAIN); ?></td></tr><?php else: foreach($stats['top_products'] as $product): ?><tr><td><?php echo esc_html($product['name']); ?></td><td><?php echo esc_html($product['count']); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div> <?php return ob_get_clean(); }
    
    private function render_tab_payouts($user_id) { global $wpdb; $stats = $this->get_marketer_stats($user_id); $bank_details = get_user_meta($user_id, 'gams_bank_details', true) ?: []; $payouts = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->db_payouts_table} WHERE marketer_id = %d ORDER BY request_at DESC", $user_id)); ob_start(); ?> <h2 class="gams-h2"><?php esc_html_e('درآمد و درخواست واریز', self::TEXT_DOMAIN); ?></h2> <div class="gams-grid"><div class="gams-grid-item"><div class="gams-icon"><i class="bi bi-wallet-fill"></i></div><div><h3 class="gams-h3"><?php esc_html_e('موجودی قابل برداشت', self::TEXT_DOMAIN); ?></h3><p class="gams-p"><?php echo wc_price($stats['unpaid_commission']); ?></p></div></div><div class="gams-grid-item"><div class="gams-icon"><i class="bi bi-check2-circle"></i></div><div><h3 class="gams-h3"><?php esc_html_e('جمع مبالغ پرداخت شده', self::TEXT_DOMAIN); ?></h3><p class="gams-p"><?php echo wc_price($stats['paid_commission']); ?></p></div></div></div> <div class="gams-card"><h3 class="gams-card-title"><i class="bi bi-credit-card-2-front-fill"></i> <?php esc_html_e('اطلاعات حساب بانکی', self::TEXT_DOMAIN); ?></h3><form method="POST" action=""><input type="hidden" name="gams_action" value="save_bank"><?php wp_nonce_field('gams_dashboard_action', 'gams_action_nonce'); ?><div class="gams-form-row"><label class="gams-label"><?php esc_html_e('نام صاحب حساب', self::TEXT_DOMAIN); ?></label><input type="text" name="holder_name" value="<?php echo esc_attr($bank_details['holder_name'] ?? ''); ?>" class="gams-input" required></div><div class="gams-form-row"><label class="gams-label"><?php esc_html_e('نام بانک', self::TEXT_DOMAIN); ?></label><input type="text" name="bank_name" value="<?php echo esc_attr($bank_details['bank_name'] ?? ''); ?>" class="gams-input" required></div><div class="gams-form-row"><label class="gams-label"><?php esc_html_e('شماره کارت', self::TEXT_DOMAIN); ?></label><input type="text" name="card_number" value="<?php echo esc_attr($bank_details['card_number'] ?? ''); ?>" class="gams-input" required></div><div class="gams-form-row"><label class="gams-label"><?php esc_html_e('شماره شبا (بدون IR)', self::TEXT_DOMAIN); ?></label><input type="text" name="sheba" value="<?php echo esc_attr($bank_details['sheba'] ?? ''); ?>" class="gams-input" required></div><button type="submit" class="gams-button"><?php esc_html_e('ذخیره اطلاعات', self::TEXT_DOMAIN); ?></button></form></div> <div class="gams-card"><h3 class="gams-card-title"><i class="bi bi-box-arrow-in-down"></i> <?php esc_html_e('درخواست واریز وجه', self::TEXT_DOMAIN); ?></h3><form method="POST" action=""><input type="hidden" name="gams_action" value="request_payout"><?php wp_nonce_field('gams_dashboard_action', 'gams_action_nonce'); ?><p style="font-size:1rem;color:#555;margin-bottom:15px;"><?php printf(esc_html__('مبلغ قابل برداشت شما %s است.', self::TEXT_DOMAIN), wc_price($stats['unpaid_commission'])); ?></p><button type="submit" class="gams-button" <?php if($stats['unpaid_commission'] <= 0) echo 'disabled'; ?>><?php esc_html_e('ثبت درخواست واریز', self::TEXT_DOMAIN); ?></button></form></div> <div class="gams-card"><h3 class="gams-card-title"><i class="bi bi-clock-history"></i> <?php esc_html_e('تاریخچه درخواست‌های واریز', self::TEXT_DOMAIN); ?></h3><div class="gams-table-wrapper"><table class="gams-table"><thead><tr><th><?php esc_html_e('مبلغ', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('تاریخ درخواست', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('وضعیت', self::TEXT_DOMAIN); ?></th><th><?php esc_html_e('تاریخ واریز', self::TEXT_DOMAIN); ?></th></tr></thead><tbody><?php if(empty($payouts)): ?><tr><td colspan="4"><?php esc_html_e('تاکنون درخواستی ثبت نکرده‌اید.', self::TEXT_DOMAIN); ?></td></tr><?php else: foreach ($payouts as $payout): ?><tr><td><?php echo wc_price($payout->amount); ?></td><td><?php echo date_i18n('Y/m/d', strtotime($payout->request_at)); ?></td><td><?php echo $payout->status === 'paid' ? '<span style="color:green">' . esc_html__('پرداخت شده', self::TEXT_DOMAIN) . '</span>' : '<span style="color:orange">' . esc_html__('در انتظار تایید', self::TEXT_DOMAIN) . '</span>'; ?></td><td><?php echo $payout->paid_at ? date_i18n('Y/m/d', strtotime($payout->paid_at)) : '-'; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div> <?php return ob_get_clean(); }
    
    private function display_flash_message() { $feedback = get_transient('gams_flash_message_' . get_current_user_id()); if ($feedback) { echo '<div class="gams-feedback gams-' . esc_attr($feedback['type']) . '">' . esc_html($feedback['message']) . '</div>'; delete_transient('gams_flash_message_' . get_current_user_id()); } }
    
    public function create_db_tables() { global $wpdb; require_once(ABSPATH . 'wp-admin/includes/upgrade.php'); $charset_collate = $wpdb->get_charset_collate(); $sql_links = "CREATE TABLE {$this->db_links_table} ( link_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, marketer_id BIGINT(20) UNSIGNED NOT NULL, product_id BIGINT(20) UNSIGNED DEFAULT 0, custom_slug VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, expire_at DATETIME DEFAULT NULL, max_uses INT(11) DEFAULT 0, use_count INT(11) DEFAULT 0, PRIMARY KEY (link_id), UNIQUE KEY (custom_slug), KEY (marketer_id) ) $charset_collate;"; $sql_visits = "CREATE TABLE {$this->db_visits_table} ( visit_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, link_id BIGINT(20) UNSIGNED NOT NULL, visit_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ip_address VARCHAR(100) NOT NULL, PRIMARY KEY (visit_id), KEY (link_id) ) $charset_collate;"; $sql_payouts = "CREATE TABLE {$this->db_payouts_table} ( payout_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, marketer_id BIGINT(20) UNSIGNED NOT NULL, amount DECIMAL(10, 2) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', request_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, paid_at DATETIME DEFAULT NULL, bank_details TEXT, PRIMARY KEY (payout_id), KEY (marketer_id) ) $charset_collate;"; dbDelta($sql_links); dbDelta($sql_visits); dbDelta($sql_payouts); }
    
    public function setup_roles() { if (!get_role(self::MARKETER_ROLE)) { add_role(self::MARKETER_ROLE, __('همکار بازاریاب', self::TEXT_DOMAIN), ['read' => true]); } }
    
    private function get_marketer_stats($user_id) { $transient_key = 'gams_stats_' . $user_id; $stats = get_transient($transient_key); if (false === $stats) { global $wpdb; $query = $wpdb->prepare("SELECT p.ID, p.post_date, p.post_status, pm1.meta_value as commission, pm2.meta_value as total, pm3.meta_value as status, pm4.meta_value as link_id FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = %s WHERE p.post_type = 'shop_order' AND pm.meta_key = %s AND pm.meta_value = %d AND pm1.meta_key = %s AND pm2.meta_key = '_order_total' AND pm3.meta_key = %s ORDER BY p.post_date DESC", self::META_LINK_ID, self::META_MARKETER_ID, $user_id, self::META_COMMISSION_TOTAL, self::META_COMMISSION_STATUS); $orders = $wpdb->get_results($query); $stats = ['sales_count' => 0, 'total_commission' => 0, 'unpaid_commission' => 0, 'paid_commission' => 0, 'recent_sales' => [], 'chart_data' => ['labels' => [], 'data' => []], 'top_products' => [], 'sales_by_link' => [], 'total_clicks' => 0]; $monthly_commission = []; $product_sales = []; $link_sales = []; if (!empty($orders)) { $stats['sales_count'] = count($orders); foreach ($orders as $order) { $stats['total_commission'] += $order->commission; if ($order->status == 'unpaid') $stats['unpaid_commission'] += $order->commission; elseif ($order->status == 'paid') $stats['paid_commission'] += $order->commission; if(count($stats['recent_sales']) < 10) $stats['recent_sales'][] = ['order_id' => $order->ID, 'date' => $order->post_date, 'total' => $order->total, 'commission' => $order->commission, 'order_status' => $order->post_status]; $month = date('Y-m', strtotime($order->post_date)); if(!isset($monthly_commission[$month])) $monthly_commission[$month] = 0; $monthly_commission[$month] += $order->commission; $link_id_key = $order->link_id ?: 'general'; if(!isset($link_sales[$link_id_key])) $link_sales[$link_id_key] = ['count' => 0, 'commission' => 0]; $link_sales[$link_id_key]['count']++; $link_sales[$link_id_key]['commission'] += $order->commission; $wc_order = wc_get_order($order->ID); if ($wc_order) { foreach ($wc_order->get_items() as $item) { $product_id = $item->get_product_id(); if(!isset($product_sales[$product_id])) $product_sales[$product_id] = ['name' => $item->get_name(), 'count' => 0]; $product_sales[$product_id]['count'] += $item->get_quantity(); } } } ksort($monthly_commission); $last_12_months = array_slice($monthly_commission, -12, 12, true); foreach ($last_12_months as $month => $commission) { $stats['chart_data']['labels'][] = date_i18n('F Y', strtotime($month . '-01')); $stats['chart_data']['data'][] = $commission; } arsort($product_sales); $stats['top_products'] = array_slice($product_sales, 0, 5, true); foreach($link_sales as $link_id => $data) { $slug = ($link_id === 'general' || $link_id == 0) ? __('لینک عمومی', self::TEXT_DOMAIN) : $wpdb->get_var($wpdb->prepare("SELECT custom_slug FROM {$this->db_links_table} WHERE link_id = %d", $link_id)); $stats['sales_by_link'][] = ['slug' => $slug ?: __('لینک پویا/حذف شده', self::TEXT_DOMAIN), 'count' => $data['count'], 'commission' => $data['commission']]; } } $stats['total_clicks'] = (int) $wpdb->get_var($wpdb->prepare("SELECT SUM(use_count) FROM {$this->db_links_table} WHERE marketer_id = %d", $user_id)); set_transient($transient_key, $stats, HOUR_IN_SECONDS); } return $stats; }
    
    private function get_dashboard_styles() { ob_start(); ?> <style> :root { --gams-primary-color: #00CED1; --gams-secondary-color: #40E0D0; --gams-danger-color: #dc3545; --gams-text-dark: #333; --gams-text-light: #777; --gams-bg-light: #f9f9f9; --gams-bg-white: #fff; --gams-border-color: #ddd; --gams-radius: 8px; } .gams-pro-wrapper { direction: rtl; font-family: inherit; background-color: #EEEEEE; padding: 25px; border-radius: 15px; box-shadow: 0 8px 24px rgba(0,0,0,.08); } .gams-tabs { display: flex; border-bottom: 2px solid var(--gams-border-color); margin-bottom: 20px; flex-wrap: wrap; } .gams-tab-link { background: none; border: none; padding: 12px 18px; cursor: pointer; font-size: 1rem; color: var(--gams-text-light); text-decoration: none; border-bottom: 3px solid transparent; transition: all .3s; } .gams-tab-link:hover { color: var(--gams-text-dark); } .gams-tab-link.active { color: var(--gams-primary-color); border-bottom-color: var(--gams-primary-color); font-weight: 600; } .gams-tab-link i { margin-left: 8px; } .gams-tab-content { display: none; animation: gamsFadeIn .5s; } .gams-tab-content.active { display: block; } @keyframes gamsFadeIn { from { opacity: 0; } to { opacity: 1; } } .gams-card { background-color: var(--gams-bg-white); padding: 25px; border-radius: var(--gams-radius); box-shadow: 0 4px 16px rgba(0,0,0,.05); margin-bottom: 20px; } .gams-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; } .gams-grid-item { background-color: var(--gams-bg-white); padding: 20px; border-radius: var(--gams-radius); box-shadow: 0 4px 16px rgba(0,0,0,.05); display: flex; align-items: center; } .gams-icon { font-size: 2.5rem; color: var(--gams-primary-color); margin-left: 20px; } .gams-h2 { font-size: 1.8rem; color: var(--gams-text-dark); margin: 0 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid var(--gams-primary-color); } .gams-card-title { font-size: 1.5rem; color: var(--gams-text-dark); margin: 0 0 20px 0; } .gams-h3 { margin: 0 0 8px; font-size: 1rem; color: var(--gams-text-light); } .gams-p { margin: 0; font-size: 1.5rem; font-weight: 600; color: var(--gams-text-dark); } .gams-input, .gams-input-readonly { width: 100%; padding: 10px; border: 1px solid var(--gams-border-color); border-radius: var(--gams-radius); box-sizing: border-box; font-family: inherit; transition: border-color .3s; } .gams-input:focus { border-color: var(--gams-primary-color); outline: none; } .gams-input-readonly { background: var(--gams-bg-light); direction: ltr; text-align: left; } .gams-input-group { display: flex; } .gams-input-group .gams-input { border-top-right-radius: 0; border-bottom-right-radius: 0; flex-grow: 1; } .gams-input-group .gams-button { border-radius: 0; margin-right: -1px; border-top-left-radius: var(--gams-radius); border-bottom-left-radius: var(--gams-radius); } .gams-button { background: linear-gradient(135deg, var(--gams-primary-color) 0%, var(--gams-secondary-color) 100%); color: #fff; padding: 10px 20px; border: none; border-radius: var(--gams-radius); font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: box-shadow .3s; } .gams-button:hover { box-shadow: 0 4px 15px rgba(0, 206, 209, 0.4); } .gams-button:disabled { background: #ccc; cursor: not-allowed; box-shadow: none; } .gams-button-secondary { background: #6c757d; } .gams-button-danger { background: var(--gams-danger-color); } .gams-button-small { padding: 5px 10px; font-size: 0.9rem; margin: 0 2px; } .gams-label { display: block; margin-bottom: 5px; font-weight: 600; } .gams-form-row { margin-bottom: 15px; } .gams-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; } .gams-table-wrapper { overflow-x: auto; } .gams-table { width: 100%; border-collapse: collapse; } .gams-table th, .gams-table td { padding: 12px; text-align: right; border-bottom: 1px solid #f0f0f0; vertical-align: middle; } .gams-table th { background: var(--gams-bg-light); font-weight: 600; } .gams-edit-form { background-color: var(--gams-bg-light); padding: 15px; border-radius: var(--gams-radius); } .gams-chart-container { position: relative; height: 300px; width: 100%; } .gams-feedback { margin-bottom: 15px; padding: 12px; border-radius: var(--gams-radius); font-weight: 600; border: 1px solid; } .gams-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; } .gams-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; } .gams-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); } .gams-modal.active { display: flex; align-items: center; justify-content: center; } .gams-modal-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; cursor: pointer; } .gams-modal-content { position: relative; background: #fff; padding: 20px; border-radius: var(--gams-radius); width: 90%; max-width: 340px; text-align: center; z-index: 1001; } .gams-modal-content img { max-width: 100%; height: auto; } .gams-modal-close { position: absolute; top: -10px; right: -10px; background: #fff; border-radius: 50%; border: 1px solid #ccc; width: 30px; height: 30px; font-size: 20px; cursor: pointer; } </style> <?php return ob_get_clean(); }
    
    private function get_dashboard_scripts($chart_data) { $chart_data_json = json_encode($chart_data); $currency_symbol = get_woocommerce_currency_symbol(); ob_start(); ?> <script> document.addEventListener('DOMContentLoaded', function() { const tabLinks = document.querySelectorAll('.gams-tab-link'); const tabContents = document.querySelectorAll('.gams-tab-content'); function setActiveTabFromHash() { const hash = window.location.hash.substring(1); const tabToActivate = hash || 'overview'; tabLinks.forEach(l => l.classList.remove('active')); tabContents.forEach(c => c.classList.remove('active')); const activeLink = document.querySelector(`.gams-tab-link[data-tab="${tabToActivate}"]`); const activeContent = document.getElementById(tabToActivate); if (activeLink) activeLink.classList.add('active'); if (activeContent) activeContent.classList.add('active'); } tabLinks.forEach(link => { link.addEventListener('click', e => { e.preventDefault(); const tabId = link.getAttribute('data-tab'); window.location.hash = tabId; setActiveTabFromHash(); }); }); setActiveTabFromHash(); const chartEl = document.getElementById('gamsMonthlyCommissionChart'); if (chartEl && typeof Chart !== 'undefined') { const ctx = chartEl.getContext('2d'); const chartData = <?php echo $chart_data_json; ?>; new Chart(ctx, { type: 'line', data: { labels: chartData.labels, datasets: [{ label: '<?php echo esc_js(__('درآمد', self::TEXT_DOMAIN)); ?>', data: chartData.data, fill: true, backgroundColor: 'rgba(0, 206, 209, 0.2)', borderColor: '#00CED1', tension: 0.3 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return new Intl.NumberFormat('fa-IR').format(value) + ' <?php echo esc_js($currency_symbol); ?>'; } } } } } }); } const modal = document.getElementById('gams-qr-modal'); if (modal) { const closeBtn = modal.querySelector('.gams-modal-close'); const overlay = modal.querySelector('.gams-modal-overlay'); function hideModal() { modal.classList.remove('active'); } if(closeBtn) closeBtn.addEventListener('click', hideModal); if(overlay) overlay.addEventListener('click', hideModal); } }); function gamsCopy(elementId) { const input = document.getElementById(elementId); input.select(); input.setSelectionRange(0, 99999); try { document.execCommand('copy'); } catch (err) { console.error('Copy failed', err); } } function showGamsQrCode(url) { const modal = document.getElementById('gams-qr-modal'); const img = document.getElementById('gams-qr-image'); if(modal && img) { img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(url); modal.classList.add('active'); } } function toggleEditForm(linkId) { const linkRow = document.getElementById('link-row-' + linkId); const editFormRow = document.getElementById('edit-form-row-' + linkId); if (linkRow && editFormRow) { linkRow.style.display = linkRow.style.display === 'none' ? '' : 'none'; editFormRow.style.display = editFormRow.style.display === 'none' ? '' : 'none'; } } </script> <?php return ob_get_clean(); }
}

Guru_Advanced_Marketing_System_Final::instance();
