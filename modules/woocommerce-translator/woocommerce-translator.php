<?php
/**
 * Modulo WooCommerce Translator per Dynamic Page Translator
 * File: modules/woocommerce-translator/woocommerce-translator.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_WooCommerce_Translator_Module {
    
    private $options = array();
private $default_options = array(
    'enabled' => true,
    'translate_product_title' => true,
    'translate_product_description' => true,
    'translate_product_short_description' => true,
    'translate_product_attributes' => true,
    'translate_product_categories' => true,
    'translate_product_tags' => true,
    'translate_variable_products' => true,
    'translate_product_meta' => true,
    'translate_product_reviews' => true,
    'translate_shop_pages' => true,
    'translate_checkout_fields' => true,
    'cache_translations' => true,
    'priority' => 'high',
    'live_translation' => true, // Abilitata di default per migliori performance
    'excluded_products' => array(),
    'excluded_categories' => array(),
    'translation_models' => array(
        'free' => array('model1', 'model2', 'model3'), // Example free models
        'paid' => array('model4', 'model5', 'model6') // Example paid models
    ),
    'filter_models' => true,
    'translate_woocommerce_elements' => array(
        'product_description' => true,
        'short_description' => true,
        'features' => true,
        'categories' => true,
        'tags' => true
    ),
    'exclude_words' => array(),
    'force_translations' => array()
);
    
    public function __construct() {
        $this->init_options();
        $this->init_hooks();
        $this->register_module();
    }
    
    /**
     * Inizializza opzioni
     */
    private function init_options() {
        $this->options = get_option('dpt_woocommerce_translator_options', $this->default_options);
    }
    
    /**
     * Inizializza hook
     */
    private function init_hooks() {
        // Verifica se WooCommerce è attivo
        if (!$this->is_woocommerce_active()) {
            return;
        }
        
        // Hook admin
        add_action('admin_menu', array($this, 'add_woocommerce_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_dpt_save_woocommerce_options', array($this, 'ajax_save_options'));
        
        // Hook per traduzione prodotti
        if ($this->options['enabled']) {
            $this->init_translation_hooks();
        }
    }
    
    /**
     * Registra modulo
     */
    private function register_module() {
        add_action('dpt_modules_loaded', function() {
            $plugin = DynamicPageTranslator::get_instance();
            $plugin->register_module('woocommerce_translator', $this);
        }, 20);
    }
    
    /**
     * Verifica se WooCommerce è attivo
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Inizializza hook per traduzione
     */
    private function init_translation_hooks() {
        // Traduzione titolo prodotto
        if ($this->options['translate_product_title']) {
            add_filter('the_title', array($this, 'translate_product_title'), 20, 2);
            add_filter('woocommerce_product_title', array($this, 'translate_product_title'), 20, 2);
        }
        
        // Traduzione descrizione prodotto
        if ($this->options['translate_product_description']) {
            add_filter('the_content', array($this, 'translate_product_description'), 20);
        }
        
        // Traduzione descrizione breve
        if ($this->options['translate_product_short_description']) {
            add_filter('woocommerce_short_description', array($this, 'translate_product_short_description'), 20);
        }
        
        // Traduzione attributi prodotto
        if ($this->options['translate_product_attributes']) {
            add_filter('woocommerce_attribute_label', array($this, 'translate_attribute_label'), 20, 3);
            add_filter('woocommerce_attribute_option_name', array($this, 'translate_attribute_option'), 20);
        }
        
        // Traduzione categorie prodotto
        if ($this->options['translate_product_categories']) {
            add_filter('get_term', array($this, 'translate_product_term'), 20, 2);
        }
        
        // Traduzione tag prodotto
        if ($this->options['translate_product_tags']) {
            add_filter('get_the_terms', array($this, 'translate_product_terms'), 20, 3);
        }
        
        // Traduzione prodotti variabili
        if ($this->options['translate_variable_products']) {
            add_filter('woocommerce_variation_option_name', array($this, 'translate_variation_option'), 20);
        }
    }
    
    /**
     * Aggiunge menu admin WooCommerce
     */
    public function add_woocommerce_admin_menu() {
        add_submenu_page(
            'dynamic-translator',
            __('WooCommerce Translator', 'dynamic-translator'),
            __('WooCommerce', 'dynamic-translator'),
            'manage_options',
            'dynamic-translator-woocommerce',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue assets admin
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'dynamic-translator-woocommerce') === false) {
            return;
        }
        
        wp_enqueue_style(
            'dpt-woocommerce-admin',
            DPT_PLUGIN_URL . 'modules/woocommerce-translator/assets/css/admin.css',
            array(),
            DPT_VERSION
        );
        
        wp_enqueue_script(
            'dpt-woocommerce-admin',
            DPT_PLUGIN_URL . 'modules/woocommerce-translator/assets/js/admin.js',
            array('jquery'),
            DPT_VERSION,
            true
        );
        
        wp_localize_script('dpt-woocommerce-admin', 'dptWooCommerce', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dpt_woocommerce_nonce'),
            'strings' => array(
                'saveSuccess' => __('Impostazioni salvate con successo!', 'dynamic-translator'),
                'saveError' => __('Errore durante il salvataggio delle impostazioni.', 'dynamic-translator')
            )
        ));
    }
    
    /**
     * Renderizza pagina admin
     */
    public function render_admin_page() {
        if (!$this->is_woocommerce_active()) {
            echo '<div class="wrap"><h1>' . __('WooCommerce Translator', 'dynamic-translator') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('WooCommerce non è attivo. Attiva WooCommerce per utilizzare questo modulo.', 'dynamic-translator') . '</p></div></div>';
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('WooCommerce Translator', 'dynamic-translator'); ?></h1>
            
            <div class="dpt-woocommerce-header">
                <p><?php _e('Configura quali elementi di WooCommerce tradurre automaticamente.', 'dynamic-translator'); ?></p>
            </div>
            
            <form id="dpt-woocommerce-options-form" method="post">
                <div class="dpt-woocommerce-container">
                    <div class="dpt-woocommerce-main-settings">
                        <h2><?php _e('Impostazioni Generali', 'dynamic-translator'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Abilita Traduzione WooCommerce', 'dynamic-translator'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enabled" value="1" <?php checked($this->options['enabled'], true); ?>>
                                        <?php _e('Abilita traduzione automatica per WooCommerce', 'dynamic-translator'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Modalità Traduzione', 'dynamic-translator'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="live_translation" value="1" <?php checked($this->options['live_translation'], true); ?>>
                                        <?php _e('Traduzione live (più veloce)', 'dynamic-translator'); ?>
                                    </label>
                                    <p class="description"><?php _e('Abilita traduzione in tempo reale per una migliore esperienza utente.', 'dynamic-translator'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Priorità Traduzione', 'dynamic-translator'); ?></th>
                                <td>
                                    <select name="priority">
                                        <option value="high" <?php selected($this->options['priority'], 'high'); ?>><?php _e('Alta (più veloce)', 'dynamic-translator'); ?></option>
                                        <option value="normal" <?php selected($this->options['priority'], 'normal'); ?>><?php _e('Normale', 'dynamic-translator'); ?></option>
                                        <option value="low" <?php selected($this->options['priority'], 'low'); ?>><?php _e('Bassa (più accurata)', 'dynamic-translator'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('Priorità alta usa modelli più veloci, priorità bassa usa modelli più accurati.', 'dynamic-translator'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Cache Traduzioni', 'dynamic-translator'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="cache_translations" value="1" <?php checked($this->options['cache_translations'], true); ?>>
                                        <?php _e('Memorizza traduzioni nella cache', 'dynamic-translator'); ?>
                                    </label>
                                    <p class="description"><?php _e('Migliora le performance memorizzando le traduzioni.', 'dynamic-translator'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="dpt-woocommerce-elements">
                        <h2><?php _e('Elementi da Tradurre', 'dynamic-translator'); ?></h2>
                        
                        <div class="dpt-woocommerce-tabs">
                            <ul class="dpt-tabs-nav">
                                <li class="active" data-tab="products"><?php _e('Prodotti', 'dynamic-translator'); ?></li>
                                <li data-tab="taxonomies"><?php _e('Categorie & Tag', 'dynamic-translator'); ?></li>
                                <li data-tab="shop"><?php _e('Negozio', 'dynamic-translator'); ?></li>
                                <li data-tab="exclusions"><?php _e('Esclusioni', 'dynamic-translator'); ?></li>
                            </ul>
                            
                            <div class="dpt-tab-content active" id="tab-products">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Contenuti Prodotto', 'dynamic-translator'); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="translate_product_title" value="1" <?php checked($this->options['translate_product_title'], true); ?>>
                                                <?php _e('Titolo prodotto', 'dynamic-translator'); ?>
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="translate_product_description" value="1" <?php checked($this->options['translate_product_description'], true); ?>>
                                                <?php _e('Descrizione prodotto', 'dynamic-translator'); ?>
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="translate_product_short_description" value="1" <?php checked($this->options['translate_product_short_description'], true); ?>>
                                                <?php _e('Descrizione breve', 'dynamic-translator'); ?>
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="translate_product_attributes" value="1" <?php checked($this->options['translate_product_attributes'], true); ?>>
                                                <?php _e('Attributi prodotto', 'dynamic-translator'); ?>
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="translate_product_meta" value="1" <?php checked($this->options['translate_product_meta'], true); ?>>
                                                <?php _e('Metadati prodotto', 'dynamic-translator'); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row"><?php _e('Variazioni & Recensioni', 'dynamic-translator'); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="translate_variable_products" value="1" <?php checked($this->options['translate_variable_products'], true); ?>>
                                                <?php _e('Prodotti variabili', 'dynamic-translator'); ?>
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="translate_product_reviews" value="1" <?php checked($this->options['translate_product_reviews'], true); ?>>
                                                <?php _e('Recensioni prodotto', 'dynamic-translator'); ?>
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="dpt-tab-content" id="tab-taxonomies">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Categorie & Tag', 'dynamic-translator'); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="translate_product_categories" value="1" <?php checked($this->options['translate_product_categories'], true); ?>>
                                                <?php _e('Categorie prodotto', 'dynamic-translator'); ?>
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="translate_product_tags" value="1" <?php checked($this->options['translate_product_tags'], true); ?>>
                                                <?php _e('Tag prodotto', 'dynamic-translator'); ?>
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="dpt-tab-content" id="tab-shop">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Pagine Negozio', 'dynamic-translator'); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="translate_shop_pages" value="1" <?php checked($this->options['translate_shop_pages'], true); ?>>
                                                <?php _e('Pagine negozio (shop, account, carrello)', 'dynamic-translator'); ?>
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="translate_checkout_fields" value="1" <?php checked($this->options['translate_checkout_fields'], true); ?>>
                                                <?php _e('Campi checkout e form', 'dynamic-translator'); ?>
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="dpt-tab-content" id="tab-exclusions">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Prodotti Esclusi', 'dynamic-translator'); ?></th>
                                        <td>
                                            <div class="dpt-exclusion-manager">
                                                <select id="dpt-product-selector" class="dpt-select2" style="width: 100%;">
                                                    <option value=""><?php _e('Seleziona prodotti da escludere...', 'dynamic-translator'); ?></option>
                                                    <?php
                                                    $products = wc_get_products(array('limit' => 100, 'status' => 'publish'));
                                                    foreach ($products as $product) {
                                                        echo '<option value="' . esc_attr($product->get_id()) . '">' . esc_html($product->get_name()) . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                                <button type="button" id="dpt-add-excluded-product" class="button"><?php _e('Aggiungi', 'dynamic-translator'); ?></button>
                                                
                                                <div class="dpt-excluded-items">
                                                    <h4><?php _e('Prodotti esclusi dalla traduzione:', 'dynamic-translator'); ?></h4>
                                                    <ul id="dpt-excluded-products-list">
                                                        <?php
                                                        if (!empty($this->options['excluded_products'])) {
                                                            foreach ($this->options['excluded_products'] as $product_id) {
                                                                $product = wc_get_product($product_id);
                                                                if ($product) {
                                                                    echo '<li data-id="' . esc_attr($product_id) . '">' . 
                                                                         esc_html($product->get_name()) . 
                                                                         ' <a href="#" class="dpt-remove-excluded">×</a>' .
                                                                         '<input type="hidden" name="excluded_products[]" value="' . esc_attr($product_id) . '">' .
                                                                         '</li>';
                                                                }
                                                            }
                                                        }
                                                        ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row"><?php _e('Categorie Escluse', 'dynamic-translator'); ?></th>
                                        <td>
                                            <div class="dpt-exclusion-manager">
                                                <select id="dpt-category-selector" class="dpt-select2" style="width: 100%;">
                                                    <option value=""><?php _e('Seleziona categorie da escludere...', 'dynamic-translator'); ?></option>
                                                    <?php
                                                    $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
                                                    foreach ($categories as $category) {
                                                        echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                                <button type="button" id="dpt-add-excluded-category" class="button"><?php _e('Aggiungi', 'dynamic-translator'); ?></button>
                                                
                                                <div class="dpt-excluded-items">
                                                    <h4><?php _e('Categorie escluse dalla traduzione:', 'dynamic-translator'); ?></h4>
                                                    <ul id="dpt-excluded-categories-list">
                                                        <?php
                                                        if (!empty($this->options['excluded_categories'])) {
                                                            foreach ($this->options['excluded_categories'] as $category_id) {
                                                                $category = get_term($category_id, 'product_cat');
                                                                if ($category && !is_wp_error($category)) {
                                                                    echo '<li data-id="' . esc_attr($category_id) . '">' . 
                                                                         esc_html($category->name) . 
                                                                         ' <a href="#" class="dpt-remove-excluded">×</a>' .
                                                                         '<input type="hidden" name="excluded_categories[]" value="' . esc_attr($category_id) . '">' .
                                                                         '</li>';
                                                                }
                                                            }
                                                        }
                                                        ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="dpt-woocommerce-actions">
                    <input type="hidden" name="action" value="dpt_save_woocommerce_options">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('dpt_woocommerce_nonce'); ?>">
                    <button type="submit" class="button button-primary"><?php _e('Salva Impostazioni', 'dynamic-translator'); ?></button>
                    <button type="button" class="button" id="dpt-woocommerce-reset"><?php _e('Ripristina Default', 'dynamic-translator'); ?></button>
                </div>
                
                <div id="dpt-woocommerce-message" class="notice" style="display: none;"></div>
            </form>
        </div>
        
        <style>
        .dpt-woocommerce-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        .dpt-woocommerce-main-settings, .dpt-woocommerce-elements {
            flex: 1;
            min-width: 300px;
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .dpt-woocommerce-actions {
            margin-top: 20px;
            padding: 15px 0;
        }
        .dpt-woocommerce-tabs {
            margin-top: 15px;
        }
        .dpt-tabs-nav {
            display: flex;
            margin: 0;
            padding: 0;
            list-style: none;
            border-bottom: 1px solid #ccc;
        }
        .dpt-tabs-nav li {
            padding: 10px 15px;
            margin: 0 5px 0 0;
            cursor: pointer;
            background: #f5f5f5;
            border: 1px solid #ccc;
            border-bottom: none;
            border-radius: 3px 3px 0 0;
        }
        .dpt-tabs-nav li.active {
            background: #fff;
            border-bottom: 1px solid #fff;
            margin-bottom: -1px;
            font-weight: 600;
        }
        .dpt-tab-content {
            display: none;
            padding: 15px 0;
        }
        .dpt-tab-content.active {
            display: block;
        }
        .dpt-exclusion-manager {
            margin-bottom: 15px;
        }
        .dpt-excluded-items {
            margin-top: 15px;
            border: 1px solid #eee;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 3px;
        }
        .dpt-excluded-items h4 {
            margin-top: 0;
        }
        .dpt-excluded-items ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .dpt-excluded-items li {
            padding: 5px 10px;
            background: #fff;
            border: 1px solid #ddd;
            margin-bottom: 5px;
            border-radius: 3px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dpt-remove-excluded {
            color: #a00;
            text-decoration: none;
            font-size: 18px;
            font-weight: bold;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX: Salva opzioni
     */
    public function ajax_save_options() {
        check_ajax_referer('dpt_woocommerce_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $options = array();
        
        // Checkbox options
        $checkbox_options = array(
            'enabled', 'translate_product_title', 'translate_product_description',
            'translate_product_short_description', 'translate_product_attributes',
            'translate_product_categories', 'translate_product_tags',
            'translate_variable_products', 'cache_translations', 'live_translation',
            'translate_product_meta', 'translate_product_reviews',
            'translate_shop_pages', 'translate_checkout_fields'
        );
        
        foreach ($checkbox_options as $option) {
            $options[$option] = isset($_POST[$option]) && $_POST[$option] == '1';
        }
        
        // Select options
        $options['priority'] = sanitize_text_field($_POST['priority'] ?? 'normal');
        
        // Array options
        $options['excluded_products'] = isset($_POST['excluded_products']) ? array_map('intval', $_POST['excluded_products']) : array();
        $options['excluded_categories'] = isset($_POST['excluded_categories']) ? array_map('intval', $_POST['excluded_categories']) : array();
        
        // Salva opzioni
        update_option('dpt_woocommerce_translator_options', $options);
        
        // Aggiorna opzioni locali
        $this->options = $options;
        
        // Reinizializza hook se necessario
        if ($options['enabled']) {
            $this->init_translation_hooks();
        }
        
        wp_send_json_success('Impostazioni salvate con successo');
    }
    
    /**
     * Ottiene lingua corrente
     */
    private function get_current_language() {
        // Usa la funzione globale se disponibile
        if (function_exists('dpt_get_current_language')) {
            return dpt_get_current_language();
        }
        
        // Fallback: ottieni la lingua dal plugin principale
        $plugin = DynamicPageTranslator::get_instance();
        if (method_exists($plugin, 'get_current_language')) {
            return $plugin->get_current_language();
        }
        
        // Fallback finale: usa la lingua del browser o default
        $default_lang = get_option('dpt_default_language', 'en');
        
        if (isset($_COOKIE['dpt_language'])) {
            return sanitize_text_field($_COOKIE['dpt_language']);
        }
        
        return $default_lang;
    }
    
    /**
     * Ottiene nome lingua
     */
    private function get_language_name($code) {
        $languages = array(
            'en' => 'English',
            'it' => 'Italiano',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'es' => 'Español',
            'pt' => 'Português',
            'ru' => 'Русский',
            'zh' => '中文',
            'ja' => '日本語',
            'ar' => 'العربية',
            'hi' => 'हिन्दी',
            'ko' => '한국어'
        );
        
        return isset($languages[$code]) ? $languages[$code] : $code;
    }
    
    /**
     * Traduce testo
     */
    private function translate_text($text, $source_lang, $target_lang) {
        if (empty($text)) {
            return $text;
        }
        
        $plugin = DynamicPageTranslator::get_instance();
        $api_handler = $plugin->get_api_handler();
        
        // Imposta priorità traduzione
        $priority = $this->options['priority'] ?? 'normal';
        $api_handler->set_translation_priority($priority);
        
        // Esegui traduzione
        return $api_handler->translate($text, $source_lang, $target_lang);
    }
    
    /**
     * Traduce titolo prodotto
     */
    public function translate_product_title($title, $post_id = 0) {
        // Verifica se è un prodotto
        if (get_post_type($post_id) !== 'product') {
            return $title;
        }
        
        $current_lang = $this->get_current_language();
        $default_lang = dpt_get_option('default_language', 'en');
        
        // Se lingua corrente è quella di default, non tradurre
        if ($current_lang === $default_lang) {
            return $title;
        }
        
        // Genera chiave cache
        $cache_key = 'dpt_wc_title_' . md5($title . $current_lang);
        
        // Controlla cache
        if ($this->options['cache_translations']) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Traduci
        $translated = $this->translate_text($title, $default_lang, $current_lang);
        
        // Gestione errore traduzione
        if (is_wp_error($translated)) {
            error_log('WooCommerce Translator Error: ' . $translated->get_error_message());
            return $title; // fallback al testo originale
        }
        
        // Salva in cache
        if ($this->options['cache_translations']) {
            set_transient($cache_key, $translated, DAY_IN_SECONDS);
        }
        
        return $translated;
    }
    
    /**
     * Traduce descrizione prodotto
     */
    public function translate_product_description($content) {
        global $post;
        
        // Verifica se è un prodotto
        if (!is_singular('product') || !is_a($post, 'WP_Post')) {
            return $content;
        }
        
        $current_lang = $this->get_current_language();
        $default_lang = dpt_get_option('default_language', 'en');
        
        // Se lingua corrente è quella di default, non tradurre
        if ($current_lang === $default_lang) {
            return $content;
        }
        
        // Genera chiave cache
        $cache_key = 'dpt_wc_desc_' . md5($content . $current_lang);
        
        // Controlla cache
        if ($this->options['cache_translations']) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Traduci
        $translated = $this->translate_text($content, $default_lang, $current_lang);
        
        // Gestione errore traduzione
        if (is_wp_error($translated)) {
            error_log('WooCommerce Translator Error: ' . $translated->get_error_message());
            return $content; // fallback al testo originale
        }
        
        // Salva in cache
        if ($this->options['cache_translations']) {
            set_transient($cache_key, $translated, DAY_IN_SECONDS);
        }
        
        return $translated;
    }
    
    /**
     * Traduce descrizione breve
     */
    public function translate_product_short_description($short_description) {
        $current_lang = $this->get_current_language();
        $default_lang = dpt_get_option('default_language', 'en');
        
        // Se lingua corrente è quella di default, non tradurre
        if ($current_lang === $default_lang) {
            return $short_description;
        }
        
        // Genera chiave cache
        $cache_key = 'dpt_wc_short_desc_' . md5($short_description . $current_lang);
        
        // Controlla cache
        if ($this->options['cache_translations']) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Traduci
        $translated = $this->translate_text($short_description, $default_lang, $current_lang);
        
        // Gestione errore traduzione
        if (is_wp_error($translated)) {
            error_log('WooCommerce Translator Error: ' . $translated->get_error_message());
            return $short_description; // fallback al testo originale
        }
        
        // Salva in cache
        if ($this->options['cache_translations']) {
            set_transient($cache_key, $translated, DAY_IN_SECONDS);
        }
        
        return $translated;
    }
    
    /**
     * Traduce etichetta attributo
     */
    public function translate_attribute_label($label, $name, $product = null) {
        $current_lang = $this->get_current_language();
        $default_lang = dpt_get_option('default_language', 'en');
        
        // Se lingua corrente è quella di default, non tradurre
        if ($current_lang === $default_lang) {
            return $label;
        }
        
        // Genera chiave cache
        $cache_key = 'dpt_wc_attr_label_' . md5($label . $current_lang);
        
        // Controlla cache
        if ($this->options['cache_translations']) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Traduci
        $translated = $this->translate_text($label, $default_lang, $current_lang);
        
        // Gestione errore traduzione
        if (is_wp_error($translated)) {
            error_log('WooCommerce Translator Error: ' . $translated->get_error_message());
            return $label; // fallback al testo originale
        }
        
        // Salva in cache
        if ($this->options['cache_translations']) {
            set_transient($cache_key, $translated, DAY_IN_SECONDS);
        }
        
        return $translated;
    }
    
    /**
     * Traduce opzione attributo
     */
    public function translate_attribute_option($option) {
        $current_lang = $this->get_current_language();
        $default_lang = dpt_get_option('default_language', 'en');
        
        // Se lingua corrente è quella di default, non tradurre
        if ($current_lang === $default_lang) {
            return $option;
        }
        
        // Genera chiave cache
        $cache_key = 'dpt_wc_attr_option_' . md5($option . $current_lang);
        
        // Controlla cache
        if ($this->options['cache_translations']) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Traduci
        $translated = $this->translate_text($option, $default_lang, $current_lang);
        
        // Salva in cache
        if ($this->options['cache_translations'] && !is_wp_error($translated)) {
            set_transient($cache_key, $translated, DAY_IN_SECONDS);
        }
        
        return is_wp_error($translated) ? $option : $translated;
    }
    
    /**
     * Traduce termine prodotto (categoria/tag)
     */
    public function translate_product_term($term, $taxonomy) {
        // Verifica se è una tassonomia di prodotto
        if (!in_array($taxonomy, array('product_cat', 'product_tag'))) {
            return $term;
        }
        
        $current_lang = $this->get_current_language();
        $default_lang = dpt_get_option('default_language', 'en');
        
        // Se lingua corrente è quella di default, non tradurre
        if ($current_lang === $default_lang) {
            return $term;
        }
        
        // Genera chiave cache
        $cache_key = 'dpt_wc_term_' . $term->term_id . '_' . $current_lang;
        
        // Controlla cache
        if ($this->options['cache_translations']) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                $term->name = $cached;
                return $term;
            }
        }
        
        // Traduci
        $translated = $this->translate_text($term->name, $default_lang, $current_lang);
        
        // Gestione errore traduzione
        if (is_wp_error($translated)) {
            error_log('WooCommerce Translator Error: ' . $translated->get_error_message());
            return $term; // fallback al termine originale
        }
        
        // Salva in cache
        if ($this->options['cache_translations']) {
            set_transient($cache_key, $translated, DAY_IN_SECONDS);
        }
        
        $term->name = $translated;
        
        return $term;
    }
    
    /**
     * Traduce termini prodotto
     */
    public function translate_product_terms($terms, $post_id, $taxonomy) {
        // Verifica se è una tassonomia di prodotto
        if (!in_array($taxonomy, array('product_cat', 'product_tag'))) {
            return $terms;
        }
        
        if (!is_array($terms) || empty($terms)) {
            return $terms;
        }
        
        $current_lang = $this->get_current_language();
        $default_lang = dpt_get_option('default_language', 'en');
        
        // Se lingua corrente è quella di default, non tradurre
        if ($current_lang === $default_lang) {
            return $terms;
        }
        
        foreach ($terms as $key => $term) {
            $terms[$key] = $this->translate_product_term($term, $taxonomy);
        }
        
        return $terms;
    }
    
    /**
     * Traduce opzione variazione
     */
    public function translate_variation_option($option) {
        if (empty($option)) {
            return $option;
        }
        
        $current_lang = $this->get_current_language();
        $default_lang = dpt_get_option('default_language', 'en');
        
        // Se lingua corrente è quella di default, non tradurre
        if ($current_lang === $default_lang) {
            return $option;
        }
        
        // Genera chiave cache
        $cache_key = 'dpt_wc_variation_' . md5($option . $current_lang);
        
        // Controlla cache
        if ($this->options['cache_translations']) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Traduci
        $translated = $this->translate_text($option, $default_lang, $current_lang);
        
        // Salva in cache
        if ($this->options['cache_translations'] && !is_wp_error($translated)) {
            set_transient($cache_key, $translated, DAY_IN_SECONDS);
        }
        
        return is_wp_error($translated) ? $option : $translated;
    }
}

// Inizializza modulo
new DPT_WooCommerce_Translator_Module();
