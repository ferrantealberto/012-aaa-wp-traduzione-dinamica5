<?php
/**
 * Modulo SEO Optimizer per Dynamic Page Translator
 * File: modules/seo-optimizer/seo-optimizer.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_SEO_Optimizer_Module {
    
    private $current_language;
    private $default_language;
    private $enabled_languages;
    private $seo_configs;
    
    public function __construct() {
        $this->init_properties();
        $this->init_hooks();
        $this->register_module();
    }
    
    /**
     * Inizializza le proprietà
     */
    private function init_properties() {
        $this->default_language = dpt_get_option('default_language', 'en');
        $this->enabled_languages = dpt_get_option('enabled_languages', array('en', 'es', 'fr', 'de'));
        $this->current_language = $this->get_current_language();
        
        $this->seo_configs = array(
            'hreflang_enabled' => dpt_get_option('seo_hreflang_enabled', true),
            'canonical_enabled' => dpt_get_option('seo_canonical_enabled', true),
            'meta_translate' => dpt_get_option('seo_meta_translate', true),
            'schema_enabled' => dpt_get_option('seo_schema_enabled', true),
            'sitemap_enabled' => dpt_get_option('seo_sitemap_enabled', true),
            'url_structure' => dpt_get_option('seo_url_structure', 'parameter'), // parameter, subdomain, subdirectory
            'robots_enabled' => dpt_get_option('seo_robots_enabled', true)
        );
    }
    
    /**
     * Inizializza gli hook
     */
    private function init_hooks() {
        // SEO meta tags
        add_action('wp_head', array($this, 'add_hreflang_tags'), 1);
        add_action('wp_head', array($this, 'add_canonical_tags'), 2);
        add_action('wp_head', array($this, 'add_language_meta'), 3);
        add_action('wp_head', array($this, 'add_schema_markup'), 90);
        
        // Meta description e title
        add_filter('document_title_parts', array($this, 'modify_document_title'), 10, 1);
        add_filter('get_the_excerpt', array($this, 'translate_excerpt'), 10, 1);
        
        // Open Graph e Twitter Cards
        add_action('wp_head', array($this, 'add_og_meta'), 5);
        add_action('wp_head', array($this, 'add_twitter_meta'), 6);
        
        // Sitemap XML multilingue
        add_action('init', array($this, 'init_multilingual_sitemap'));
        add_filter('wp_sitemaps_posts_entry', array($this, 'modify_sitemap_entry'), 10, 3);
        
        // Robots.txt
        add_filter('robots_txt', array($this, 'modify_robots_txt'), 10, 2);
        
        // URL structure
        add_filter('home_url', array($this, 'modify_home_url'), 10, 4);
        add_filter('post_link', array($this, 'modify_post_link'), 10, 3);
        add_filter('page_link', array($this, 'modify_page_link'), 10, 2);
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_seo_admin_menu'));
        add_action('add_meta_boxes', array($this, 'add_seo_meta_boxes'));
        add_action('save_post', array($this, 'save_seo_meta_data'));
        
        // JSON-LD Schema
        add_action('wp_footer', array($this, 'add_jsonld_schema'));
        
        // Breadcrumbs multilingue
        add_filter('wpseo_breadcrumb_links', array($this, 'modify_breadcrumbs')); // Yoast
        add_filter('rank_math/frontend/breadcrumb/items', array($this, 'modify_rankmath_breadcrumbs')); // RankMath
    }
    
    /**
     * Registra il modulo
     */
    private function register_module() {
        $plugin = DynamicPageTranslator::get_instance();
        $plugin->register_module('seo_optimizer', $this);
    }
    
    /**
     * Aggiunge tag hreflang
     */
    public function add_hreflang_tags() {
        if (!$this->seo_configs['hreflang_enabled']) {
            return;
        }
        
        global $wp;
        $current_url = home_url($wp->request);
        
        // Tag per lingua predefinita
        echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($this->get_url_for_language($current_url, $this->default_language)) . '">' . "\n";
        
        // Tag per ogni lingua abilitata
        foreach ($this->enabled_languages as $lang_code) {
            $lang_url = $this->get_url_for_language($current_url, $lang_code);
            $hreflang = $this->get_hreflang_code($lang_code);
            echo '<link rel="alternate" hreflang="' . esc_attr($hreflang) . '" href="' . esc_url($lang_url) . '">' . "\n";
        }
    }
    
    /**
     * Aggiunge tag canonical
     */
    public function add_canonical_tags() {
        if (!$this->seo_configs['canonical_enabled']) {
            return;
        }
        
        global $wp;
        $current_url = home_url($wp->request);
        
        // Se siamo nella lingua predefinita, canonical punta alla URL senza parametri
        if ($this->current_language === $this->default_language) {
            $canonical_url = $this->get_clean_url($current_url);
        } else {
            $canonical_url = $this->get_url_for_language($current_url, $this->current_language);
        }
        
        echo '<link rel="canonical" href="' . esc_url($canonical_url) . '">' . "\n";
    }
    
    /**
     * Aggiunge meta tag per lingua
     */
    public function add_language_meta() {
        echo '<meta http-equiv="content-language" content="' . esc_attr($this->current_language) . '">' . "\n";
        echo '<meta name="language" content="' . esc_attr($this->get_language_name($this->current_language)) . '">' . "\n";
    }
    
    /**
     * Aggiunge markup Schema.org
     */
    public function add_schema_markup() {
        if (!$this->seo_configs['schema_enabled']) {
            return;
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'inLanguage' => $this->current_language,
            'url' => get_permalink(),
            'name' => get_the_title(),
            'description' => get_the_excerpt()
        );
        
        // Aggiunge traduzioni disponibili
        $translations = array();
        foreach ($this->enabled_languages as $lang_code) {
            $translations[] = array(
                '@type' => 'WebPage',
                'inLanguage' => $lang_code,
                'url' => $this->get_url_for_language(get_permalink(), $lang_code)
            );
        }
        
        if (!empty($translations)) {
            $schema['workTranslation'] = $translations;
        }
        
        echo '<script type="application/ld+json">' . json_encode($schema) . '</script>' . "\n";
    }
    
    /**
     * Modifica il titolo del documento
     */
    public function modify_document_title($title_parts) {
        if (!$this->seo_configs['meta_translate'] || $this->current_language === $this->default_language) {
            return $title_parts;
        }
        
        // Traduce title se necessario
        if (isset($title_parts['title'])) {
            $translated_title = $this->get_translated_meta($title_parts['title'], 'title');
            if ($translated_title) {
                $title_parts['title'] = $translated_title;
            }
        }
        
        // Traduce tagline se necessario
        if (isset($title_parts['tagline'])) {
            $translated_tagline = $this->get_translated_meta($title_parts['tagline'], 'tagline');
            if ($translated_tagline) {
                $title_parts['tagline'] = $translated_tagline;
            }
        }
        
        return $title_parts;
    }
    
    /**
     * Traduce excerpt
     */
    public function translate_excerpt($excerpt) {
        if (!$this->seo_configs['meta_translate'] || $this->current_language === $this->default_language) {
            return $excerpt;
        }
        
        $translated_excerpt = $this->get_translated_meta($excerpt, 'excerpt');
        return $translated_excerpt ?: $excerpt;
    }
    
    /**
     * Aggiunge meta tag Open Graph
     */
    public function add_og_meta() {
        if (is_singular()) {
            echo '<meta property="og:locale" content="' . esc_attr($this->get_og_locale($this->current_language)) . '">' . "\n";
            
            // Aggiunge alternative locales
            foreach ($this->enabled_languages as $lang_code) {
                if ($lang_code !== $this->current_language) {
                    echo '<meta property="og:locale:alternate" content="' . esc_attr($this->get_og_locale($lang_code)) . '">' . "\n";
                }
            }
        }
    }
    
    /**
     * Aggiunge meta tag Twitter
     */
    public function add_twitter_meta() {
        if (is_singular()) {
            // Twitter non ha supporto nativo per multiple lingue, ma aggiungiamo info lingua
            echo '<meta name="twitter:data1" content="' . esc_attr($this->get_language_name($this->current_language)) . '">' . "\n";
            echo '<meta name="twitter:label1" content="Language">' . "\n";
        }
    }
    
    /**
     * Inizializza sitemap multilingue
     */
    public function init_multilingual_sitemap() {
        if (!$this->seo_configs['sitemap_enabled']) {
            return;
        }
        
        // Hook per modificare sitemap XML
        add_action('wp_loaded', array($this, 'handle_sitemap_request'));
    }
    
    /**
     * Gestisce richieste sitemap
     */
    public function handle_sitemap_request() {
        if (!isset($_GET['sitemap-lang'])) {
            return;
        }
        
        $lang = sanitize_text_field($_GET['sitemap-lang']);
        if (!in_array($lang, $this->enabled_languages)) {
            return;
        }
        
        // Genera sitemap per lingua specifica
        $this->generate_language_sitemap($lang);
    }
    
    /**
     * Genera sitemap per lingua
     */
    private function generate_language_sitemap($language) {
        header('Content-Type: application/xml; charset=UTF-8');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        
        // Homepage
        $this->add_sitemap_url(home_url('/'), $language);
        
        // Posts e Pages
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'numberposts' => -1
        ));
        
        foreach ($posts as $post) {
            $url = get_permalink($post);
            $this->add_sitemap_url($url, $language, $post);
        }
        
        echo '</urlset>';
        exit;
    }
    
    /**
     * Aggiunge URL al sitemap
     */
    private function add_sitemap_url($url, $language, $post = null) {
        $lang_url = $this->get_url_for_language($url, $language);
        
        echo '<url>' . "\n";
        echo '<loc>' . esc_url($lang_url) . '</loc>' . "\n";
        
        if ($post) {
            echo '<lastmod>' . mysql2date('Y-m-d\TH:i:s+00:00', $post->post_modified_gmt) . '</lastmod>' . "\n";
        }
        
        // Aggiunge link alle traduzioni
        foreach ($this->enabled_languages as $lang_code) {
            $alt_url = $this->get_url_for_language($url, $lang_code);
            $hreflang = $this->get_hreflang_code($lang_code);
            echo '<xhtml:link rel="alternate" hreflang="' . esc_attr($hreflang) . '" href="' . esc_url($alt_url) . '"/>' . "\n";
        }
        
        echo '</url>' . "\n";
    }
    
    /**
     * Modifica robots.txt
     */
    public function modify_robots_txt($output, $public) {
        if (!$this->seo_configs['robots_enabled']) {
            return $output;
        }
        
        // Aggiunge sitemap per ogni lingua
        foreach ($this->enabled_languages as $lang_code) {
            $sitemap_url = home_url("/?sitemap-lang={$lang_code}");
            $output .= "Sitemap: {$sitemap_url}\n";
        }
        
        return $output;
    }
    
    /**
     * Modifica URL home
     */
    public function modify_home_url($url, $path, $orig_scheme, $blog_id) {
        if (!is_admin() && $this->current_language !== $this->default_language) {
            return $this->get_url_for_language($url, $this->current_language);
        }
        
        return $url;
    }
    
    /**
     * Modifica link post
     */
    public function modify_post_link($permalink, $post, $leavename) {
        if (!is_admin() && $this->current_language !== $this->default_language) {
            return $this->get_url_for_language($permalink, $this->current_language);
        }
        
        return $permalink;
    }
    
    /**
     * Modifica link pagina
     */
    public function modify_page_link($link, $post_id) {
        if (!is_admin() && $this->current_language !== $this->default_language) {
            return $this->get_url_for_language($link, $this->current_language);
        }
        
        return $link;
    }
    
    /**
     * Aggiunge menu admin SEO
     */
    public function add_seo_admin_menu() {
        add_submenu_page(
            'dynamic-translator',
            __('SEO Multilingue', 'dynamic-translator'),
            __('SEO', 'dynamic-translator'),
            'manage_options',
            'dynamic-translator-seo',
            array($this, 'render_seo_admin_page')
        );
    }
    
    /**
     * Renderizza pagina admin SEO
     */
    public function render_seo_admin_page() {
        if (isset($_POST['submit'])) {
            $this->save_seo_settings();
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('SEO Multilingue', 'dynamic-translator'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('dpt_seo_settings', 'dpt_seo_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Tag Hreflang', 'dynamic-translator'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="seo_hreflang_enabled" value="1" <?php checked($this->seo_configs['hreflang_enabled']); ?>>
                                <?php _e('Abilita tag hreflang automatici', 'dynamic-translator'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Tag Canonical', 'dynamic-translator'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="seo_canonical_enabled" value="1" <?php checked($this->seo_configs['canonical_enabled']); ?>>
                                <?php _e('Abilita tag canonical per traduzioni', 'dynamic-translator'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Traduzione Meta', 'dynamic-translator'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="seo_meta_translate" value="1" <?php checked($this->seo_configs['meta_translate']); ?>>
                                <?php _e('Traduci automaticamente title e description', 'dynamic-translator'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Schema Markup', 'dynamic-translator'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="seo_schema_enabled" value="1" <?php checked($this->seo_configs['schema_enabled']); ?>>
                                <?php _e('Abilita markup Schema.org multilingue', 'dynamic-translator'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Sitemap XML', 'dynamic-translator'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="seo_sitemap_enabled" value="1" <?php checked($this->seo_configs['sitemap_enabled']); ?>>
                                <?php _e('Genera sitemap per ogni lingua', 'dynamic-translator'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Struttura URL', 'dynamic-translator'); ?></th>
                        <td>
                            <select name="seo_url_structure">
                                <option value="parameter" <?php selected($this->seo_configs['url_structure'], 'parameter'); ?>><?php _e('Parametro (?lang=en)', 'dynamic-translator'); ?></option>
                                <option value="subdirectory" <?php selected($this->seo_configs['url_structure'], 'subdirectory'); ?>><?php _e('Sottodirectory (/en/)', 'dynamic-translator'); ?></option>
                                <option value="subdomain" <?php selected($this->seo_configs['url_structure'], 'subdomain'); ?>><?php _e('Sottodominio (en.sito.com)', 'dynamic-translator'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="dpt-seo-tools">
                <h2><?php _e('Strumenti SEO', 'dynamic-translator'); ?></h2>
                
                <div class="dpt-seo-actions">
                    <button type="button" id="generate-sitemaps" class="button"><?php _e('Genera Sitemap', 'dynamic-translator'); ?></button>
                    <button type="button" id="validate-hreflang" class="button"><?php _e('Valida Hreflang', 'dynamic-translator'); ?></button>
                    <button type="button" id="check-meta-translations" class="button"><?php _e('Controlla Meta Traduzioni', 'dynamic-translator'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Aggiunge meta box SEO
     */
    public function add_seo_meta_boxes() {
        add_meta_box(
            'dpt-seo-meta',
            __('SEO Multilingue', 'dynamic-translator'),
            array($this, 'render_seo_meta_box'),
            array('post', 'page'),
            'normal',
            'high'
        );
    }
    
    /**
     * Renderizza meta box SEO
     */
    public function render_seo_meta_box($post) {
        wp_nonce_field('dpt_seo_meta', 'dpt_seo_meta_nonce');
        
        $meta_titles = get_post_meta($post->ID, '_dpt_meta_titles', true) ?: array();
        $meta_descriptions = get_post_meta($post->ID, '_dpt_meta_descriptions', true) ?: array();
        
        ?>
        <table class="form-table">
            <?php foreach ($this->enabled_languages as $lang_code): ?>
                <tr>
                    <th scope="row"><?php echo esc_html($this->get_language_name($lang_code)); ?></th>
                    <td>
                        <p>
                            <label><?php _e('Titolo SEO', 'dynamic-translator'); ?></label>
                            <input type="text" name="dpt_meta_title[<?php echo $lang_code; ?>]" 
                                   value="<?php echo esc_attr($meta_titles[$lang_code] ?? ''); ?>" 
                                   style="width: 100%;" placeholder="<?php _e('Lascia vuoto per traduzione automatica', 'dynamic-translator'); ?>">
                        </p>
                        <p>
                            <label><?php _e('Descrizione SEO', 'dynamic-translator'); ?></label>
                            <textarea name="dpt_meta_description[<?php echo $lang_code; ?>]" 
                                      rows="3" style="width: 100%;" 
                                      placeholder="<?php _e('Lascia vuoto per traduzione automatica', 'dynamic-translator'); ?>"><?php echo esc_textarea($meta_descriptions[$lang_code] ?? ''); ?></textarea>
                        </p>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }
    
    /**
     * Salva dati meta SEO
     */
    public function save_seo_meta_data($post_id) {
        if (!isset($_POST['dpt_seo_meta_nonce']) || !wp_verify_nonce($_POST['dpt_seo_meta_nonce'], 'dpt_seo_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['dpt_meta_title'])) {
            update_post_meta($post_id, '_dpt_meta_titles', array_map('sanitize_text_field', $_POST['dpt_meta_title']));
        }
        
        if (isset($_POST['dpt_meta_description'])) {
            update_post_meta($post_id, '_dpt_meta_descriptions', array_map('sanitize_textarea_field', $_POST['dpt_meta_description']));
        }
    }
    
    /**
     * Aggiunge JSON-LD schema nel footer
     */
    public function add_jsonld_schema() {
        if (!$this->seo_configs['schema_enabled'] || !is_singular()) {
            return;
        }
        
        global $post;
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => $this->get_schema_type($post),
            'headline' => get_the_title(),
            'description' => get_the_excerpt(),
            'url' => get_permalink(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'inLanguage' => $this->current_language,
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author)
            )
        );
        
        // Aggiunge traduzioni disponibili
        $translations = array();
        foreach ($this->enabled_languages as $lang_code) {
            if ($lang_code !== $this->current_language) {
                $translations[] = array(
                    '@type' => $this->get_schema_type($post),
                    'url' => $this->get_url_for_language(get_permalink(), $lang_code),
                    'inLanguage' => $lang_code
                );
            }
        }
        
        if (!empty($translations)) {
            $schema['workTranslation'] = $translations;
        }
        
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
    
    /**
     * Utility functions
     */
    private function get_current_language() {
        return isset($_COOKIE['dpt_current_lang']) ? sanitize_text_field($_COOKIE['dpt_current_lang']) : $this->default_language;
    }
    
    private function get_url_for_language($url, $language) {
        switch ($this->seo_configs['url_structure']) {
            case 'subdirectory':
                $parsed_url = parse_url($url);
                $path = '/' . $language . ($parsed_url['path'] ?? '/');
                return $parsed_url['scheme'] . '://' . $parsed_url['host'] . $path . ($parsed_url['query'] ? '?' . $parsed_url['query'] : '');
                
            case 'subdomain':
                $parsed_url = parse_url($url);
                $host = $language . '.' . preg_replace('/^[^.]+\./', '', $parsed_url['host']);
                return $parsed_url['scheme'] . '://' . $host . ($parsed_url['path'] ?? '/') . ($parsed_url['query'] ? '?' . $parsed_url['query'] : '');
                
            default: // parameter
                return add_query_arg('lang', $language, $url);
        }
    }
    
    private function get_clean_url($url) {
        return remove_query_arg('lang', $url);
    }
    
    private function get_hreflang_code($lang_code) {
        $hreflang_map = array(
            'en' => 'en',
            'it' => 'it',
            'es' => 'es',
            'fr' => 'fr',
            'de' => 'de',
            'pt' => 'pt',
            'ru' => 'ru',
            'zh' => 'zh-CN',
            'ja' => 'ja',
            'ar' => 'ar'
        );
        
        return $hreflang_map[$lang_code] ?? $lang_code;
    }
    
    private function get_og_locale($lang_code) {
        $locale_map = array(
            'en' => 'en_US',
            'it' => 'it_IT',
            'es' => 'es_ES',
            'fr' => 'fr_FR',
            'de' => 'de_DE',
            'pt' => 'pt_PT',
            'ru' => 'ru_RU',
            'zh' => 'zh_CN',
            'ja' => 'ja_JP',
            'ar' => 'ar_AR'
        );
        
        return $locale_map[$lang_code] ?? $lang_code . '_' . strtoupper($lang_code);
    }
    
    private function get_language_name($lang_code) {
        $names = array(
            'en' => 'English',
            'it' => 'Italiano',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'pt' => 'Português',
            'ru' => 'Русский',
            'zh' => '中文',
            'ja' => '日本語',
            'ar' => 'العربية'
        );
        
        return $names[$lang_code] ?? $lang_code;
    }
    
    private function get_schema_type($post) {
        if ($post->post_type === 'page') {
            return 'WebPage';
        } elseif ($post->post_type === 'post') {
            return 'BlogPosting';
        }
        
        return 'Article';
    }
    
    private function get_translated_meta($content, $type) {
        // Implementazione semplificata - usa cache/API per tradurre meta
        $plugin = DynamicPageTranslator::get_instance();
        $cache_handler = $plugin->get_cache_handler();
        
        $cache_key = $cache_handler->generate_cache_key(
            $content,
            $this->default_language,
            $this->current_language,
            array('type' => 'meta_' . $type)
        );
        
        return $cache_handler->get_translation($cache_key);
    }
    
    private function save_seo_settings() {
        if (!wp_verify_nonce($_POST['dpt_seo_nonce'], 'dpt_seo_settings')) {
            return;
        }
        
        $settings = array(
            'seo_hreflang_enabled',
            'seo_canonical_enabled',
            'seo_meta_translate',
            'seo_schema_enabled',
            'seo_sitemap_enabled',
            'seo_url_structure',
            'seo_robots_enabled'
        );
        
        foreach ($settings as $setting) {
            if (isset($_POST[$setting])) {
                dpt_update_option($setting, sanitize_text_field($_POST[$setting]));
            } else {
                dpt_update_option($setting, false);
            }
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Impostazioni SEO salvate!', 'dynamic-translator') . '</p></div>';
        });
    }
}

// Inizializza il modulo
new DPT_SEO_Optimizer_Module();