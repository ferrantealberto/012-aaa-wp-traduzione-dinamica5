<?php
/**
 * Plugin Name: Dynamic Page Translator
 * Description: Plugin modulare per traduzione dinamica delle pagine WordPress con cache locale
 * Version: 1.0.0
 * Author: Il Tuo Nome
 * Text Domain: dynamic-translator
 */

// Previene accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definisce le costanti del plugin
define('DPT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DPT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DPT_VERSION', '1.0.0');

/**
 * Classe principale del plugin
 */
class DynamicPageTranslator {
    
    private static $instance = null;
    private $modules = array();
    private $cache_handler;
    private $api_handler;
    private $is_initialized = false; // FLAG per evitare ricorsione
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Evita ricorsione durante l'inizializzazione
        if ($this->is_initialized) {
            return;
        }
        
        $this->init_hooks();
        $this->load_dependencies();
        
        // Marca come inizializzato PRIMA di init_modules
        $this->is_initialized = true;
        
        // Ora possiamo inizializzare i moduli senza rischio di ricorsione
        add_action('plugins_loaded', array($this, 'init_modules'), 15);
    }
    
    /**
     * Inizializza gli hook di WordPress
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Carica le dipendenze del plugin
     */
    private function load_dependencies() {
        require_once DPT_PLUGIN_PATH . 'includes/class-cache-handler.php';
        require_once DPT_PLUGIN_PATH . 'includes/class-api-handler.php';
        require_once DPT_PLUGIN_PATH . 'includes/class-admin-interface.php';
        require_once DPT_PLUGIN_PATH . 'includes/class-frontend-display.php';
        require_once DPT_PLUGIN_PATH . 'includes/class-module-manager.php';
    }
    
    /**
     * Inizializza i moduli - CHIAMATO DOPO plugins_loaded
     */
    public function init_modules() {
        // Inizializza gli handler principali
        $this->cache_handler = new DPT_Cache_Handler();
        $this->api_handler = new DPT_API_Handler();
        
        // Inizializza il gestore moduli
        $module_manager = new DPT_Module_Manager();
        $this->modules = $module_manager->get_active_modules();
        
        // Carica moduli base SENZA auto-registrazione
        $this->load_base_modules();
        
        // Hook per permettere l'aggiunta di moduli personalizzati
        do_action('dpt_modules_loaded', $this->modules);
    }
    
    /**
     * Carica moduli base senza auto-registrazione
     */
    private function load_base_modules() {
        $base_modules = array(
            'google-translate/google-translate.php',
            'openrouter-translate/openrouter-translate.php',
            'flag-display/flag-display.php'
        );
        
        foreach ($base_modules as $module_file) {
            $module_path = DPT_PLUGIN_PATH . 'modules/' . $module_file;
            if (file_exists($module_path)) {
                require_once $module_path;
            }
        }
    }
    
    /**
     * Inizializzazione del plugin
     */
    public function init() {
        // Carica textdomain per traduzioni
        load_plugin_textdomain('dynamic-translator', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Inizializza admin interface
        if (is_admin()) {
            new DPT_Admin_Interface();
        }
        
        // Inizializza frontend display
        new DPT_Frontend_Display();
        
        // Gestisce richieste AJAX
        add_action('wp_ajax_dpt_translate_content', array($this, 'ajax_translate_content'));
        add_action('wp_ajax_nopriv_dpt_translate_content', array($this, 'ajax_translate_content'));
    }
    
    /**
     * Gestisce la traduzione via AJAX
     */
    public function ajax_translate_content() {
        check_ajax_referer('dpt_nonce', 'nonce');
        
        $content = sanitize_textarea_field($_POST['content']);
        $target_lang = sanitize_text_field($_POST['target_lang']);
        $source_lang = sanitize_text_field($_POST['source_lang']);
        
        // Controlla cache prima
        $cache_key = md5($content . $target_lang . $source_lang);
        $cached_translation = $this->cache_handler->get_translation($cache_key);
        
        if ($cached_translation) {
            wp_send_json_success(array('translation' => $cached_translation));
        }
        
        // Esegue traduzione
        $translation = $this->api_handler->translate($content, $source_lang, $target_lang);
        
        if ($translation) {
            // Salva in cache
            $this->cache_handler->save_translation($cache_key, $translation);
            wp_send_json_success(array('translation' => $translation));
        }
        
        wp_send_json_error(array('message' => __('Errore durante la traduzione', 'dynamic-translator')));
    }
    
    /**
     * Enqueue script frontend
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script(
            'dpt-frontend',
            DPT_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            DPT_VERSION,
            true
        );
        
        wp_enqueue_style(
            'dpt-frontend',
            DPT_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            DPT_VERSION
        );
        
        wp_localize_script('dpt-frontend', 'dpt_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dpt_nonce'),
            'current_lang' => $this->get_current_language()
        ));
    }
    
    /**
     * Enqueue script admin
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'dynamic-translator') === false) {
            return;
        }
        
        wp_enqueue_script(
            'dpt-admin',
            DPT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            DPT_VERSION,
            true
        );
        
        wp_enqueue_style(
            'dpt-admin',
            DPT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            DPT_VERSION
        );
    }
    
    /**
     * Attivazione plugin
     */
    public function activate() {
        $this->create_database_tables();
        $this->set_default_options();
        flush_rewrite_rules();
    }
    
    /**
     * Disattivazione plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Crea tabelle database
     */
    private function create_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dpt_translations_cache';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            cache_key varchar(32) NOT NULL,
            original_content longtext NOT NULL,
            translated_content longtext NOT NULL,
            source_lang varchar(10) NOT NULL,
            target_lang varchar(10) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY cache_key (cache_key),
            KEY lang_pair (source_lang, target_lang)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Imposta opzioni di default
     */
    private function set_default_options() {
        $default_options = array(
            'enabled_languages' => array('en', 'es', 'fr', 'de'),
            'default_language' => 'en',
            'translation_provider' => 'google',
            'google_api_key' => '',
            'openrouter_api_key' => '',
            'openrouter_model' => 'meta-llama/llama-3.1-8b-instruct:free',
            'cache_duration' => 30, // giorni
            'flag_position' => 'top-right',
            'flag_style' => 'dropdown',
            'auto_detect_language' => true,
            'translate_dynamic_content' => true
        );
        
        foreach ($default_options as $key => $value) {
            add_option('dpt_' . $key, $value);
        }
    }
    
    /**
     * Ottiene la lingua corrente
     */
    public function get_current_language() {
        if (isset($_COOKIE['dpt_current_lang'])) {
            return sanitize_text_field($_COOKIE['dpt_current_lang']);
        }
        return get_option('dpt_default_language', 'en');
    }
    
    /**
     * Ottiene istanza cache handler
     */
    public function get_cache_handler() {
        return $this->cache_handler;
    }
    
    /**
     * Ottiene istanza API handler
     */
    public function get_api_handler() {
        return $this->api_handler;
    }
    
    /**
     * Registra un nuovo modulo - METODO SICURO
     */
    public function register_module($module_name, $module_class) {
        if ($this->is_initialized) {
            $this->modules[$module_name] = $module_class;
            do_action('dpt_module_registered', $module_name, $module_class);
        }
    }
    
    /**
     * Ottiene moduli attivi
     */
    public function get_modules() {
        return $this->modules;
    }
}

// Inizializza il plugin SOLO dopo che tutti i plugin sono caricati
function dpt_init() {
    return DynamicPageTranslator::get_instance();
}

// Hook per inizializzare dopo che tutti i plugin sono caricati
add_action('plugins_loaded', 'dpt_init', 10);

// Funzioni helper globali
function dpt_get_option($option_name, $default = false) {
    return get_option('dpt_' . $option_name, $default);
}

function dpt_update_option($option_name, $value) {
    return update_option('dpt_' . $option_name, $value);
}

function dpt_translate($content, $target_lang, $source_lang = 'auto') {
    $plugin = DynamicPageTranslator::get_instance();
    return $plugin->get_api_handler()->translate($content, $source_lang, $target_lang);
}