<?php
/**
 * Interfaccia amministrativa del plugin - VERSIONE CORRETTA NONCE
 * File: includes/class-admin-interface.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_Admin_Interface {
    
    private $plugin_name = 'dynamic-translator';
    private $version;
    
    public function __construct() {
        $this->version = DPT_VERSION;
        $this->init_hooks();
    }
    
    /**
     * Inizializza gli hook admin
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_dpt_test_provider', array($this, 'ajax_test_provider'));
        add_action('wp_ajax_dpt_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_dpt_export_cache', array($this, 'ajax_export_cache'));
        add_action('wp_ajax_dpt_import_cache', array($this, 'ajax_import_cache'));
        add_action('wp_ajax_dpt_flag_preview', array($this, 'ajax_flag_preview'));
        add_action('wp_ajax_dpt_test_openrouter_detailed', array($this, 'ajax_test_openrouter_detailed'));
        add_action('wp_ajax_dpt_run_diagnostic', array($this, 'ajax_run_diagnostic'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // NUOVO: Hook per includere nonce globale
        add_action('admin_footer', array($this, 'add_admin_nonce'));
    }
    
    /**
     * NUOVO: Aggiunge nonce globale per tutte le operazioni admin
     */
    public function add_admin_nonce() {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'dynamic-translator') !== false) {
            ?>
            <script type="text/javascript">
                window.dptAdminNonce = '<?php echo wp_create_nonce('dpt_admin_nonce'); ?>';
                window.dptDebugNonce = '<?php echo wp_create_nonce('dpt_debug_nonce'); ?>';
            </script>
            <?php
        }
    }
    
    /**
     * Aggiunge menu amministrativo
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Dynamic Translator', 'dynamic-translator'),
            __('Translator', 'dynamic-translator'),
            'manage_options',
            'dynamic-translator',
            array($this, 'admin_page'),
            'dashicons-translation',
            30
        );
        
        add_submenu_page(
            'dynamic-translator',
            __('Impostazioni', 'dynamic-translator'),
            __('Impostazioni', 'dynamic-translator'),
            'manage_options',
            'dynamic-translator',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'dynamic-translator',
            __('Cache Management', 'dynamic-translator'),
            __('Cache', 'dynamic-translator'),
            'manage_options',
            'dynamic-translator-cache',
            array($this, 'cache_page')
        );
        
        add_submenu_page(
            'dynamic-translator',
            __('Statistiche', 'dynamic-translator'),
            __('Statistiche', 'dynamic-translator'),
            'manage_options',
            'dynamic-translator-stats',
            array($this, 'stats_page')
        );
        
        add_submenu_page(
            'dynamic-translator',
            __('Moduli', 'dynamic-translator'),
            __('Moduli', 'dynamic-translator'),
            'manage_options',
            'dynamic-translator-modules',
            array($this, 'modules_page')
        );
        
        add_submenu_page(
            'dynamic-translator',
            __('Debug & Troubleshooting', 'dynamic-translator'),
            __('Debug', 'dynamic-translator'),
            'manage_options',
            'dynamic-translator-debug',
            array($this, 'debug_page')
        );
    }
    
    /**
     * Registra le impostazioni
     */
    public function register_settings() {
        register_setting('dpt_settings', 'dpt_enabled_languages');
        register_setting('dpt_settings', 'dpt_default_language');
        register_setting('dpt_settings', 'dpt_translation_provider');
        register_setting('dpt_settings', 'dpt_google_api_key');
        register_setting('dpt_settings', 'dpt_openrouter_api_key');
        register_setting('dpt_settings', 'dpt_openrouter_model');
        register_setting('dpt_settings', 'dpt_cache_duration');
        register_setting('dpt_settings', 'dpt_flag_position');
        register_setting('dpt_settings', 'dpt_flag_style');
        register_setting('dpt_settings', 'dpt_auto_detect_language');
        register_setting('dpt_settings', 'dpt_translate_dynamic_content');
        register_setting('dpt_settings', 'dpt_preserve_html');
        register_setting('dpt_settings', 'dpt_enable_cache');
        register_setting('dpt_settings', 'dpt_enable_translation_log');
        register_setting('dpt_settings', 'dpt_google_rate_limit');
        register_setting('dpt_settings', 'dpt_openrouter_rate_limit');
        register_setting('dpt_settings', 'dpt_flag_custom_positions');
    }
    
    /**
     * Pagina principale admin
     */
    public function admin_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $this->render_admin_page();
    }
    
    /**
     * Render pagina amministrativa
     */
    private function render_admin_page() {
        $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap dpt-admin-page">
            <div class="dpt-admin-header">
                <h1>
                    <?php _e('Dynamic Page Translator', 'dynamic-translator'); ?>
                    <span class="dpt-version">v<?php echo DPT_VERSION; ?></span>
                </h1>
            </div>
            
            <nav class="nav-tab-wrapper dpt-tabs">
                <a href="?page=dynamic-translator&tab=general" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Generale', 'dynamic-translator'); ?>
                </a>
                <a href="?page=dynamic-translator&tab=providers" class="nav-tab <?php echo $current_tab === 'providers' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Provider API', 'dynamic-translator'); ?>
                </a>
                <a href="?page=dynamic-translator&tab=display" class="nav-tab <?php echo $current_tab === 'display' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Visualizzazione', 'dynamic-translator'); ?>
                </a>
                <a href="?page=dynamic-translator&tab=advanced" class="nav-tab <?php echo $current_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Avanzate', 'dynamic-translator'); ?>
                </a>
            </nav>
            
            <form method="post" action="">
                <?php wp_nonce_field('dpt_settings', 'dpt_nonce'); ?>
                
                <div class="dpt-tab-content" id="dpt-tab-<?php echo $current_tab; ?>">
                    <?php
                    switch ($current_tab) {
                        case 'general':
                            $this->render_general_settings();
                            break;
                        case 'providers':
                            $this->render_providers_settings();
                            break;
                        case 'display':
                            $this->render_display_settings();
                            break;
                        case 'advanced':
                            $this->render_advanced_settings();
                            break;
                    }
                    ?>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render impostazioni generali
     */
    private function render_general_settings() {
        $enabled_languages = dpt_get_option('enabled_languages', array('en', 'es', 'fr', 'de'));
        $default_language = dpt_get_option('default_language', 'en');
        $auto_detect = dpt_get_option('auto_detect_language', true);
        
        $available_languages = array(
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
        ?>
        <div class="dpt-form-section">
            <h3><?php _e('Configurazione Lingue', 'dynamic-translator'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Lingue Abilitate', 'dynamic-translator'); ?></th>
                    <td>
                        <div class="dpt-language-grid">
                            <?php foreach ($available_languages as $code => $name): ?>
                                <div class="dpt-language-item">
                                    <input type="checkbox" name="dpt_enabled_languages[]" value="<?php echo $code; ?>" id="lang_<?php echo $code; ?>"
                                           <?php checked(in_array($code, $enabled_languages)); ?>>
                                    <img src="<?php echo DPT_PLUGIN_URL . 'assets/flags/' . $code . '.svg'; ?>" 
                                         alt="<?php echo $name; ?>" class="dpt-flag" onerror="this.style.display='none';">
                                    <label for="lang_<?php echo $code; ?>"><?php echo $name; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="description"><?php _e('Seleziona le lingue disponibili per la traduzione.', 'dynamic-translator'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Lingua Predefinita', 'dynamic-translator'); ?></th>
                    <td>
                        <select name="dpt_default_language">
                            <?php foreach ($available_languages as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php selected($default_language, $code); ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Lingua predefinita del sito.', 'dynamic-translator'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Rilevamento Automatico', 'dynamic-translator'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="dpt_auto_detect_language" value="1" <?php checked($auto_detect); ?>>
                            <?php _e('Rileva automaticamente la lingua del contenuto', 'dynamic-translator'); ?>
                        </label>
                        <p class="description"><?php _e('Se abilitato, tenta di rilevare automaticamente la lingua del contenuto.', 'dynamic-translator'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render impostazioni provider - CORREZIONE NONCE
     */
    private function render_providers_settings() {
        $current_provider = dpt_get_option('translation_provider', 'google');
        $google_api_key = dpt_get_option('google_api_key', '');
        $openrouter_api_key = dpt_get_option('openrouter_api_key', '');
        $openrouter_model = dpt_get_option('openrouter_model', 'meta-llama/llama-3.1-8b-instruct:free');
        ?>
        <div class="dpt-form-section">
            <h3><?php _e('Configurazione Provider', 'dynamic-translator'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Provider di Traduzione', 'dynamic-translator'); ?></th>
                    <td>
                        <label>
                            <input type="radio" name="dpt_translation_provider" value="google" <?php checked($current_provider, 'google'); ?>>
                            <strong>Google Translate</strong>
                        </label><br>
                        <label>
                            <input type="radio" name="dpt_translation_provider" value="openrouter" <?php checked($current_provider, 'openrouter'); ?>>
                            <strong>OpenRouter AI</strong>
                        </label>
                        <p class="description"><?php _e('Seleziona il provider per le traduzioni.', 'dynamic-translator'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="dpt-form-section">
            <h3><?php _e('Google Translate API', 'dynamic-translator'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Google API Key', 'dynamic-translator'); ?></th>
                    <td>
                        <div class="dpt-api-key-field">
                            <input type="password" name="dpt_google_api_key" value="<?php echo esc_attr($google_api_key); ?>" class="regular-text" id="google-api-key">
                            <button type="button" class="dpt-api-key-toggle" onclick="toggleApiKey('google-api-key')">👁️</button>
                            <button type="button" id="test-google-api" class="button dpt-test-button" data-provider="google"><?php _e('Test Connessione', 'dynamic-translator'); ?></button>
                        </div>
                        <p class="description">
                            <?php _e('Ottieni la tua API key da', 'dynamic-translator'); ?> 
                            <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="dpt-form-section">
            <h3><?php _e('OpenRouter AI', 'dynamic-translator'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('OpenRouter API Key', 'dynamic-translator'); ?></th>
                    <td>
                        <div class="dpt-api-key-field">
                            <input type="password" name="dpt_openrouter_api_key" value="<?php echo esc_attr($openrouter_api_key); ?>" class="regular-text" id="openrouter-api-key">
                            <button type="button" class="dpt-api-key-toggle" onclick="toggleApiKey('openrouter-api-key')">👁️</button>
                            <button type="button" id="test-openrouter-api" class="button dpt-test-button" data-provider="openrouter"><?php _e('Test Connessione', 'dynamic-translator'); ?></button>
                        </div>
                        <p class="description">
                            <?php _e('Ottieni la tua API key da', 'dynamic-translator'); ?> 
                            <a href="https://openrouter.ai/" target="_blank">OpenRouter</a>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Modello OpenRouter', 'dynamic-translator'); ?></th>
                    <td>
                        <select name="dpt_openrouter_model" id="openrouter-model">
                            <option value="meta-llama/llama-3.1-8b-instruct:free" <?php selected($openrouter_model, 'meta-llama/llama-3.1-8b-instruct:free'); ?>>
                                Llama 3.1 8B (Free) - Gratuito
                            </option>
                            <option value="meta-llama/llama-3.1-70b-instruct" <?php selected($openrouter_model, 'meta-llama/llama-3.1-70b-instruct'); ?>>
                                Llama 3.1 70B - $0.59/1M token
                            </option>
                            <option value="anthropic/claude-3-haiku" <?php selected($openrouter_model, 'anthropic/claude-3-haiku'); ?>>
                                Claude 3 Haiku - $0.80/1M token
                            </option>
                            <option value="openai/gpt-4o-mini" <?php selected($openrouter_model, 'openai/gpt-4o-mini'); ?>>
                                GPT-4o Mini - $0.60/1M token
                            </option>
                            <option value="google/gemini-flash-1.5" <?php selected($openrouter_model, 'google/gemini-flash-1.5'); ?>>
                                Gemini Flash 1.5 - $0.40/1M token
                            </option>
                        </select>
                        <p class="description"><?php _e('Seleziona il modello AI per le traduzioni. I modelli gratuiti hanno limitazioni.', 'dynamic-translator'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <script>
        function toggleApiKey(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = '🙈';
            } else {
                input.type = 'password';
                button.textContent = '👁️';
            }
        }
        </script>
        <?php
    }
    
    /**
     * Render impostazioni visualizzazione
     */
    private function render_display_settings() {
        $flag_position = dpt_get_option('flag_position', 'top-right');
        $flag_style = dpt_get_option('flag_style', 'dropdown');
        $custom_positions = dpt_get_option('flag_custom_positions', array());
        ?>
        <div class="dpt-form-section">
            <h3><?php _e('Posizione e Stile Bandiere', 'dynamic-translator'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Posizione Bandiere', 'dynamic-translator'); ?></th>
                    <td>
                        <select name="dpt_flag_position" onchange="toggleCustomPositions(this.value)">
                            <option value="top-left" <?php selected($flag_position, 'top-left'); ?>><?php _e('In alto a sinistra', 'dynamic-translator'); ?></option>
                            <option value="top-right" <?php selected($flag_position, 'top-right'); ?>><?php _e('In alto a destra', 'dynamic-translator'); ?></option>
                            <option value="top-center" <?php selected($flag_position, 'top-center'); ?>><?php _e('In alto al centro', 'dynamic-translator'); ?></option>
                            <option value="bottom-left" <?php selected($flag_position, 'bottom-left'); ?>><?php _e('In basso a sinistra', 'dynamic-translator'); ?></option>
                            <option value="bottom-right" <?php selected($flag_position, 'bottom-right'); ?>><?php _e('In basso a destra', 'dynamic-translator'); ?></option>
                            <option value="bottom-center" <?php selected($flag_position, 'bottom-center'); ?>><?php _e('In basso al centro', 'dynamic-translator'); ?></option>
                            <option value="floating" <?php selected($flag_position, 'floating'); ?>><?php _e('Fluttuante', 'dynamic-translator'); ?></option>
                            <option value="header" <?php selected($flag_position, 'header'); ?>><?php _e('Nell\'header', 'dynamic-translator'); ?></option>
                            <option value="footer" <?php selected($flag_position, 'footer'); ?>><?php _e('Nel footer', 'dynamic-translator'); ?></option>
                            <option value="menu" <?php selected($flag_position, 'menu'); ?>><?php _e('Nel menu', 'dynamic-translator'); ?></option>
                            <option value="custom" <?php selected($flag_position, 'custom'); ?>><?php _e('Posizione personalizzata', 'dynamic-translator'); ?></option>
                        </select>
                        <p class="description"><?php _e('Scegli dove mostrare il selettore di lingua.', 'dynamic-translator'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Stile Bandiere', 'dynamic-translator'); ?></th>
                    <td>
                        <select name="dpt_flag_style" onchange="updateFlagPreview()">
                            <option value="dropdown" <?php selected($flag_style, 'dropdown'); ?>><?php _e('Menu a tendina', 'dynamic-translator'); ?></option>
                            <option value="inline" <?php selected($flag_style, 'inline'); ?>><?php _e('Bandiere in linea', 'dynamic-translator'); ?></option>
                            <option value="popup" <?php selected($flag_style, 'popup'); ?>><?php _e('Popup modale', 'dynamic-translator'); ?></option>
                            <option value="sidebar-slide" <?php selected($flag_style, 'sidebar-slide'); ?>><?php _e('Sidebar scorrevole', 'dynamic-translator'); ?></option>
                            <option value="circle-menu" <?php selected($flag_style, 'circle-menu'); ?>><?php _e('Menu circolare', 'dynamic-translator'); ?></option>
                            <option value="minimal" <?php selected($flag_style, 'minimal'); ?>><?php _e('Minimale', 'dynamic-translator'); ?></option>
                        </select>
                        <p class="description"><?php _e('Stile di visualizzazione delle bandiere.', 'dynamic-translator'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="dpt-form-section">
            <h3><?php _e('Anteprima', 'dynamic-translator'); ?></h3>
            <div class="dpt-flag-preview" id="dpt-flag-preview-container">
                <p><?php _e('Caricamento anteprima...', 'dynamic-translator'); ?></p>
            </div>
        </div>

        <script>
        function toggleCustomPositions(value) {
            const customRow = document.getElementById('custom-positions-row');
            if (customRow) {
                customRow.style.display = value === 'custom' ? 'table-row' : 'none';
            }
        }

        function updateFlagPreview() {
            // Implementato in admin.js
            if (typeof window.DPTAdmin !== 'undefined' && window.DPTAdmin.updateFlagPreview) {
                window.DPTAdmin.updateFlagPreview();
            }
        }
        </script>
        <?php
    }
    
    /**
     * Render impostazioni avanzate
     */
    private function render_advanced_settings() {
        $cache_duration = dpt_get_option('cache_duration', 30);
        $preserve_html = dpt_get_option('preserve_html', true);
        $enable_cache = dpt_get_option('enable_cache', true);
        $enable_log = dpt_get_option('enable_translation_log', false);
        $google_rate_limit = dpt_get_option('google_rate_limit', 1000);
        $openrouter_rate_limit = dpt_get_option('openrouter_rate_limit', 100);
        ?>
        <div class="dpt-form-section">
            <h3><?php _e('Cache e Performance', 'dynamic-translator'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Abilita Cache', 'dynamic-translator'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="dpt_enable_cache" value="1" <?php checked($enable_cache); ?>>
                            <?php _e('Usa cache per le traduzioni', 'dynamic-translator'); ?>
                        </label>
                        <p class="description"><?php _e('Disabilita per tradurre sempre in tempo reale (sconsigliato).', 'dynamic-translator'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Durata Cache', 'dynamic-translator'); ?></th>
                    <td>
                        <input type="number" name="dpt_cache_duration" value="<?php echo $cache_duration; ?>" min="1" max="365" class="small-text">
                        <?php _e('giorni', 'dynamic-translator'); ?>
                        <p class="description"><?php _e('Durata di conservazione delle traduzioni in cache.', 'dynamic-translator'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="dpt-form-section">
            <h3><?php _e('Opzioni Traduzione', 'dynamic-translator'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Preserva HTML', 'dynamic-translator'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="dpt_preserve_html" value="1" <?php checked($preserve_html); ?>>
                            <?php _e('Mantieni tag HTML nelle traduzioni', 'dynamic-translator'); ?>
                        </label>
                        <p class="description"><?php _e('Raccomandato per preservare la formattazione.', 'dynamic-translator'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Log Traduzioni', 'dynamic-translator'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="dpt_enable_translation_log" value="1" <?php checked($enable_log); ?>>
                            <?php _e('Registra tutte le traduzioni', 'dynamic-translator'); ?>
                        </label>
                        <p class="description"><?php _e('Utile per statistiche e debug.', 'dynamic-translator'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Salva le impostazioni
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['dpt_nonce'], 'dpt_settings')) {
            wp_die(__('Errore di sicurezza', 'dynamic-translator'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permessi insufficienti', 'dynamic-translator'));
        }
        
        $settings = array(
            'enabled_languages',
            'default_language', 
            'translation_provider',
            'google_api_key',
            'openrouter_api_key',
            'openrouter_model',
            'cache_duration',
            'flag_position',
            'flag_style',
            'auto_detect_language',
            'preserve_html',
            'enable_cache',
            'enable_translation_log',
            'google_rate_limit',
            'openrouter_rate_limit',
            'flag_custom_positions'
        );
        
        foreach ($settings as $setting) {
            if (isset($_POST['dpt_' . $setting])) {
                $value = $_POST['dpt_' . $setting];
                
                if (is_array($value)) {
                    $value = array_map('sanitize_text_field', $value);
                } else {
                    $value = sanitize_text_field($value);
                }
                
                dpt_update_option($setting, $value);
            } else {
                // Per le checkbox non inviate, imposta false
                if (in_array($setting, array('auto_detect_language', 'preserve_html', 'enable_cache', 'enable_translation_log'))) {
                    dpt_update_option($setting, false);
                }
            }
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Impostazioni salvate!', 'dynamic-translator') . '</p></div>';
        });
    }
    
    /**
     * Pagina gestione cache
     */
    public function cache_page() {
        $plugin = DynamicPageTranslator::get_instance();
        $cache_handler = $plugin->get_cache_handler();
        $stats = $cache_handler->get_cache_stats();
        ?>
        <div class="wrap dpt-admin-page">
            <div class="dpt-admin-header">
                <h1><?php _e('Gestione Cache', 'dynamic-translator'); ?></h1>
            </div>
            
            <div class="dpt-stats-cards">
                <div class="dpt-stats-card">
                    <span class="dpt-stat-number"><?php echo number_format($stats['active_entries'] ?? 0); ?></span>
                    <span class="dpt-stat-label"><?php _e('Traduzioni Attive', 'dynamic-translator'); ?></span>
                </div>
                <div class="dpt-stats-card">
                    <span class="dpt-stat-number"><?php echo number_format($stats['expired_entries'] ?? 0); ?></span>
                    <span class="dpt-stat-label"><?php _e('Traduzioni Scadute', 'dynamic-translator'); ?></span>
                </div>
                <div class="dpt-stats-card">
                    <span class="dpt-stat-number"><?php echo number_format($stats['hit_rate'] ?? 0, 1); ?>%</span>
                    <span class="dpt-stat-label"><?php _e('Hit Rate', 'dynamic-translator'); ?></span>
                </div>
                <div class="dpt-stats-card">
                    <span class="dpt-stat-number"><?php echo number_format($stats['cache_size_mb'] ?? 0, 2); ?> MB</span>
                    <span class="dpt-stat-label"><?php _e('Dimensione Cache', 'dynamic-translator'); ?></span>
                </div>
            </div>
            
            <div class="dpt-cache-actions">
                <button type="button" id="clear-expired-cache" class="button"><?php _e('Pulisci Cache Scaduta', 'dynamic-translator'); ?></button>
                <button type="button" id="clear-all-cache" class="button button-secondary"><?php _e('Pulisci Tutta la Cache', 'dynamic-translator'); ?></button>
                <button type="button" id="optimize-cache" class="button"><?php _e('Ottimizza Cache', 'dynamic-translator'); ?></button>
                <button type="button" id="export-cache" class="button"><?php _e('Esporta Cache', 'dynamic-translator'); ?></button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Pagina statistiche
     */
    public function stats_page() {
        $plugin = DynamicPageTranslator::get_instance();
        $api_handler = $plugin->get_api_handler();
        $stats = $api_handler->get_translation_stats();
        ?>
        <div class="wrap dpt-admin-page">
            <div class="dpt-admin-header">
                <h1><?php _e('Statistiche Traduzioni', 'dynamic-translator'); ?></h1>
            </div>
            
            <div class="dpt-stats-cards">
                <div class="dpt-stats-card">
                    <span class="dpt-stat-number" data-stat="total_translations"><?php echo number_format($stats['total_translations']); ?></span>
                    <span class="dpt-stat-label"><?php _e('Traduzioni Totali', 'dynamic-translator'); ?></span>
                </div>
                <div class="dpt-stats-card">
                    <span class="dpt-stat-number" data-stat="translations_today"><?php echo number_format($stats['translations_today']); ?></span>
                    <span class="dpt-stat-label"><?php _e('Oggi', 'dynamic-translator'); ?></span>
                </div>
                <div class="dpt-stats-card">
                    <span class="dpt-stat-number" data-stat="translations_this_month"><?php echo number_format($stats['translations_this_month']); ?></span>
                    <span class="dpt-stat-label"><?php _e('Questo Mese', 'dynamic-translator'); ?></span>
                </div>
                <div class="dpt-stats-card">
                    <span class="dpt-stat-number" data-stat="average_length"><?php echo number_format($stats['average_length']); ?></span>
                    <span class="dpt-stat-label"><?php _e('Lunghezza Media', 'dynamic-translator'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Pagina moduli
     */
    public function modules_page() {
        ?>
        <div class="wrap dpt-admin-page">
            <div class="dpt-admin-header">
                <h1><?php _e('Gestione Moduli', 'dynamic-translator'); ?></h1>
                <p><?php _e('Gestisci i moduli del plugin per estendere le funzionalità.', 'dynamic-translator'); ?></p>
            </div>
            
            <div class="dpt-modules-grid" id="modules-manager">
                <p><?php _e('Caricamento moduli...', 'dynamic-translator'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Pagina debug
     */
    public function debug_page() {
        // DELEGA alla classe debug helper
        if (class_exists('DPT_Debug_Helper')) {
            $debug_helper = DPT_Debug_Helper::get_instance();
            $debug_helper->render_debug_page();
        } else {
            echo '<div class="wrap"><h1>Debug non disponibile</h1><p>Classe debug helper non trovata.</p></div>';
        }
    }
    
    /**
     * AJAX test provider migliorato - CORREZIONE NONCE
     */
    public function ajax_test_provider() {
        // CORREZIONE: Usa nonce amministrativo corretto
        if (!check_ajax_referer('dpt_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Errore di sicurezza', 'dynamic-translator'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permessi insufficienti', 'dynamic-translator'));
        }
        
        $provider = sanitize_text_field($_POST['provider']);
        
        error_log("DPT: Testing provider: {$provider}");
        
        try {
            $api_key = '';
            if ($provider === 'google') {
                $api_key = sanitize_text_field($_POST['google_api_key'] ?? dpt_get_option('google_api_key', ''));
            } elseif ($provider === 'openrouter') {
                $api_key = sanitize_text_field($_POST['openrouter_api_key'] ?? dpt_get_option('openrouter_api_key', ''));
            }
            
            if (empty($api_key)) {
                wp_send_json_error(__('API key non fornita', 'dynamic-translator'));
            }
            
            if ($provider === 'openrouter') {
                $result = $this->test_openrouter_connection($api_key);
            } elseif ($provider === 'google') {
                $result = $this->test_google_connection($api_key);
            } else {
                wp_send_json_error(__('Provider non supportato', 'dynamic-translator'));
            }
            
            if (is_wp_error($result)) {
                error_log("DPT: Test failed for {$provider}: " . $result->get_error_message());
                wp_send_json_error($result->get_error_message());
            } else {
                error_log("DPT: Test successful for {$provider}");
                wp_send_json_success(__('Connessione riuscita!', 'dynamic-translator'));
            }
            
        } catch (Exception $e) {
            error_log("DPT: Exception during test: " . $e->getMessage());
            wp_send_json_error(__('Errore durante il test: ', 'dynamic-translator') . $e->getMessage());
        }
    }
    
    /**
     * Test specifico per OpenRouter
     */
    private function test_openrouter_connection($api_key) {
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API key OpenRouter non fornita', 'dynamic-translator'));
        }
        
        $response = wp_remote_get('https://openrouter.ai/api/v1/models', array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'HTTP-Referer' => get_site_url(),
                'X-Title' => get_bloginfo('name'),
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_site_url()
            ),
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_error', 
                sprintf(__('Errore di connessione: %s', 'dynamic-translator'), 
                $response->get_error_message())
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        error_log("DPT OpenRouter Test - Response Code: {$response_code}");
        
        switch ($response_code) {
            case 200:
                break;
            case 401:
                return new WP_Error('invalid_api_key', __('API key non valida', 'dynamic-translator'));
            case 403:
                return new WP_Error('access_denied', __('Accesso negato - verifica permessi API key', 'dynamic-translator'));
            case 429:
                return new WP_Error('rate_limit', __('Limite rate raggiunto - riprova più tardi', 'dynamic-translator'));
            case 500:
            case 502:
            case 503:
                return new WP_Error('server_error', __('Errore server OpenRouter - riprova più tardi', 'dynamic-translator'));
            default:
                return new WP_Error('unexpected_error', 
                    sprintf(__('Errore inaspettato (HTTP %d)', 'dynamic-translator'), $response_code)
                );
        }
        
        return true;
    }
    
    /**
     * Test specifico per Google
     */
    private function test_google_connection($api_key) {
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API key Google non fornita', 'dynamic-translator'));
        }
        
        $test_url = 'https://translation.googleapis.com/language/translate/v2';
        
        $params = array(
            'key' => $api_key,
            'q' => 'Hello',
            'target' => 'es',
            'format' => 'text'
        );
        
        $response = wp_remote_post($test_url, array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => http_build_query($params),
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_error', 
                sprintf(__('Errore di connessione Google: %s', 'dynamic-translator'), 
                $response->get_error_message())
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 400) {
            return new WP_Error('invalid_api_key', __('API key Google non valida', 'dynamic-translator'));
        }
        
        if ($response_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : "HTTP {$response_code}";
                
            return new WP_Error('google_api_error', 
                sprintf(__('Errore API Google: %s', 'dynamic-translator'), $error_message)
            );
        }
        
        return true;
    }
    
    /**
     * AJAX clear cache - CORREZIONE NONCE
     */
    public function ajax_clear_cache() {
        if (!check_ajax_referer('dpt_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Errore di sicurezza', 'dynamic-translator'));
        }
        
        $plugin = DynamicPageTranslator::get_instance();
        $cache_handler = $plugin->get_cache_handler();
        
        $type = sanitize_text_field($_POST['type']);
        
        switch ($type) {
            case 'expired':
                $deleted = $cache_handler->cleanup_expired_cache();
                break;
            case 'all':
                $deleted = $cache_handler->clear_all_cache();
                break;
            default:
                wp_send_json_error(__('Tipo non valido', 'dynamic-translator'));
        }
        
        wp_send_json_success(sprintf(__('%d traduzioni eliminate', 'dynamic-translator'), $deleted));
    }
    
    /**
     * AJAX export cache - CORREZIONE NONCE
     */
    public function ajax_export_cache() {
        if (!check_ajax_referer('dpt_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Errore di sicurezza', 'dynamic-translator'));
        }
        
        $plugin = DynamicPageTranslator::get_instance();
        $cache_handler = $plugin->get_cache_handler();
        
        $export_data = $cache_handler->export_cache();
        
        wp_send_json_success(array('data' => $export_data));
    }
    
    /**
     * AJAX import cache - CORREZIONE NONCE
     */
    public function ajax_import_cache() {
        if (!check_ajax_referer('dpt_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Errore di sicurezza', 'dynamic-translator'));
        }
        
        if (!isset($_FILES['cache_file'])) {
            wp_send_json_error(__('Nessun file caricato', 'dynamic-translator'));
        }
        
        $file_content = file_get_contents($_FILES['cache_file']['tmp_name']);
        
        $plugin = DynamicPageTranslator::get_instance();
        $cache_handler = $plugin->get_cache_handler();
        
        $imported = $cache_handler->import_cache($file_content);
        
        if ($imported === false) {
            wp_send_json_error(__('Errore durante l\'importazione', 'dynamic-translator'));
        }
        
        wp_send_json_success(sprintf(__('%d traduzioni importate', 'dynamic-translator'), $imported));
    }
    
    /**
     * AJAX flag preview - CORREZIONE NONCE
     */
    public function ajax_flag_preview() {
        if (!check_ajax_referer('dpt_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Errore di sicurezza', 'dynamic-translator'));
        }
        
        $position = sanitize_text_field($_POST['position']);
        $style = sanitize_text_field($_POST['style']);
        
        ob_start();
        echo '<div class="dpt-flag-switcher dpt-position-' . esc_attr($position) . ' dpt-style-' . esc_attr($style) . '">';
        echo '<p>Anteprima non ancora implementata per ' . esc_html($style) . ' in posizione ' . esc_html($position) . '</p>';
        echo '</div>';
        $preview_html = ob_get_clean();
        
        wp_send_json_success(array('html' => $preview_html));
    }
    
    /**
     * AJAX test OpenRouter dettagliato - DELEGA ALLA CLASSE DEBUG
     */
    public function ajax_test_openrouter_detailed() {
        if (class_exists('DPT_Debug_Helper')) {
            $debug_helper = DPT_Debug_Helper::get_instance();
            $debug_helper->ajax_test_openrouter_detailed();
        } else {
            wp_send_json_error('Debug helper non disponibile');
        }
    }
    
    /**
     * AJAX diagnostica completa - DELEGA ALLA CLASSE DEBUG
     */
    public function ajax_run_diagnostic() {
        if (class_exists('DPT_Debug_Helper')) {
            $debug_helper = DPT_Debug_Helper::get_instance();
            $debug_helper->ajax_run_diagnostic();
        } else {
            wp_send_json_error('Debug helper non disponibile');
        }
    }
    
    /**
     * Notice amministrative
     */
    public function admin_notices() {
        $provider = dpt_get_option('translation_provider', 'google');
        $api_key = dpt_get_option($provider . '_api_key', '');
        
        if (empty($api_key)) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>' . sprintf(__('Configura la tua API key per %s nelle impostazioni del plugin.', 'dynamic-translator'), ucfirst($provider)) . '</p>';
            echo '</div>';
        }
    }
}