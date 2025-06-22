<?php
/**
 * Gestore display frontend - VERSIONE CORRETTA POPUP
 * File: includes/class-frontend-display.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_Frontend_Display {
    
    private $current_language;
    private $default_language;
    private $enabled_languages;
    
    public function __construct() {
        $this->init_properties();
        $this->init_hooks();
    }
    
    /**
     * Inizializza le proprietà
     */
    private function init_properties() {
        $this->default_language = dpt_get_option('default_language', 'en');
        $this->enabled_languages = dpt_get_option('enabled_languages', array('en', 'es', 'fr', 'de'));
        $this->current_language = $this->get_current_language();
    }
    
    /**
     * Inizializza gli hook
     */
    private function init_hooks() {
        add_action('wp_head', array($this, 'add_language_meta'));
        add_action('wp_footer', array($this, 'add_language_switcher'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Hook per traduzione automatica del contenuto
        add_filter('the_content', array($this, 'translate_content'), 999);
        add_filter('the_title', array($this, 'translate_title'), 999);
        add_filter('widget_text', array($this, 'translate_widget_text'), 999);
        
        // CORREZIONE PRINCIPALE: Hook AJAX per cambio lingua
        add_action('wp_ajax_dpt_change_language', array($this, 'ajax_change_language'));
        add_action('wp_ajax_nopriv_dpt_change_language', array($this, 'ajax_change_language'));
        
        // Hook per traduzione dinamica elementi
        add_action('wp_ajax_dpt_translate_element', array($this, 'ajax_translate_element'));
        add_action('wp_ajax_nopriv_dpt_translate_element', array($this, 'ajax_translate_element'));
    }
    
    /**
     * Aggiunge meta tag per lingua corrente
     */
    public function add_language_meta() {
        echo '<meta name="dpt-current-language" content="' . esc_attr($this->current_language) . '">' . "\n";
        echo '<meta name="dpt-default-language" content="' . esc_attr($this->default_language) . '">' . "\n";
        
        // Aggiungi link alternativi per SEO
        foreach ($this->enabled_languages as $lang_code) {
            if ($lang_code !== $this->current_language) {
                $url = add_query_arg('lang', $lang_code, get_permalink());
                echo '<link rel="alternate" hreflang="' . esc_attr($lang_code) . '" href="' . esc_url($url) . '">' . "\n";
            }
        }
    }
    
    /**
     * Enqueue assets frontend
     */
    public function enqueue_frontend_assets() {
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
        
        // CORREZIONE: Localizza script con dati corretti
        wp_localize_script('dpt-frontend', 'dptFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dpt_frontend_nonce'),
            'currentLang' => $this->current_language,
            'defaultLang' => $this->default_language,
            'enabledLangs' => $this->enabled_languages,
            'autoTranslate' => dpt_get_option('translate_dynamic_content', true),
            'preserveHtml' => dpt_get_option('preserve_html', true),
            'flagPosition' => dpt_get_option('flag_position', 'top-right'),
            'flagStyle' => dpt_get_option('flag_style', 'dropdown'),
            'customPositions' => dpt_get_option('flag_custom_positions', array()),
            'strings' => array(
                'translating' => __('Traduzione in corso...', 'dynamic-translator'),
                'translationError' => __('Errore durante la traduzione', 'dynamic-translator'),
                'selectLanguage' => __('Seleziona lingua', 'dynamic-translator'),
                'changeLanguage' => __('Cambia lingua', 'dynamic-translator'),
                'translationComplete' => __('Traduzione completata', 'dynamic-translator'),
                'timeout' => __('Timeout: la traduzione sta impiegando troppo tempo', 'dynamic-translator'),
                'connectionError' => __('Errore di connessione', 'dynamic-translator')
            )
        ));
    }
    
    /**
     * Traduce il contenuto principale
     */
    public function translate_content($content) {
        if (!$this->should_translate()) {
            return $content;
        }
        
        if ($this->current_language === $this->default_language) {
            return $content;
        }
        
        return $this->get_translated_content($content, 'content');
    }
    
    /**
     * Traduce i titoli
     */
    public function translate_title($title) {
        if (!$this->should_translate()) {
            return $title;
        }
        
        if ($this->current_language === $this->default_language) {
            return $title;
        }
        
        return $this->get_translated_content($title, 'title');
    }
    
    /**
     * Traduce il testo dei widget
     */
    public function translate_widget_text($text) {
        if (!$this->should_translate()) {
            return $text;
        }
        
        if ($this->current_language === $this->default_language) {
            return $text;
        }
        
        return $this->get_translated_content($text, 'widget');
    }
    
    /**
     * Ottiene contenuto tradotto
     */
    private function get_translated_content($content, $type = 'content') {
        if (empty(trim($content))) {
            return $content;
        }
        
        // Controlla cache prima
        $plugin = DynamicPageTranslator::get_instance();
        $cache_handler = $plugin->get_cache_handler();
        
        $cache_key = $cache_handler->generate_cache_key(
            $content,
            $this->default_language,
            $this->current_language,
            array('type' => $type)
        );
        
        $cached_translation = $cache_handler->get_translation($cache_key);
        
        if ($cached_translation !== false) {
            return $cached_translation;
        }
        
        // Se non in cache, segna per traduzione dinamica
        $this->mark_for_dynamic_translation($content, $cache_key, $type);
        
        return $content; // Restituisce originale, verrà tradotto via JS
    }
    
    /**
     * Segna contenuto per traduzione dinamica
     */
    private function mark_for_dynamic_translation($content, $cache_key, $type) {
        static $dynamic_translations = array();
        
        $dynamic_translations[] = array(
            'content' => $content,
            'cache_key' => $cache_key,
            'type' => $type,
            'source_lang' => $this->default_language,
            'target_lang' => $this->current_language
        );
        
        // Aggiungi script per traduzione dinamica
        add_action('wp_footer', function() use ($dynamic_translations) {
            if (!empty($dynamic_translations)) {
                echo '<script type="text/javascript">';
                echo 'var dptDynamicTranslations = ' . json_encode($dynamic_translations) . ';';
                echo '</script>';
            }
        }, 20);
    }
    
    /**
     * Controlla se deve tradurre
     */
    private function should_translate() {
        // Non tradurre in admin
        if (is_admin()) {
            return false;
        }
        
        // Non tradurre durante preview
        if (is_preview()) {
            return false;
        }
        
        // Controlla se traduzione dinamica è abilitata
        if (!dpt_get_option('translate_dynamic_content', true)) {
            return false;
        }
        
        // Filtro per permettere override
        return apply_filters('dpt_should_translate', true);
    }
    
    /**
     * Aggiunge il language switcher
     */
    public function add_language_switcher() {
        $position = dpt_get_option('flag_position', 'top-right');
        $style = dpt_get_option('flag_style', 'dropdown');
        
        if ($position === 'custom') {
            // Per posizioni personalizzate, il JavaScript gestirà il posizionamento
            echo '<div id="dpt-language-switcher" class="dpt-language-switcher dpt-style-' . esc_attr($style) . '" style="display:none;">';
        } else {
            echo '<div id="dpt-language-switcher" class="dpt-language-switcher dpt-position-' . esc_attr($position) . ' dpt-style-' . esc_attr($style) . '">';
        }
        
        $this->render_language_switcher($style);
        
        echo '</div>';
    }
    
    /**
     * Renderizza il language switcher
     */
    private function render_language_switcher($style) {
        $languages = $this->get_available_languages();
        $current_flag = $this->get_flag_url($this->current_language);
        
        switch ($style) {
            case 'dropdown':
                $this->render_dropdown_switcher($languages, $current_flag);
                break;
            case 'inline':
                $this->render_inline_switcher($languages);
                break;
            case 'popup':
                $this->render_popup_switcher($languages, $current_flag);
                break;
            default:
                $this->render_dropdown_switcher($languages, $current_flag);
        }
    }
    
    /**
     * Renderizza switcher dropdown
     */
    private function render_dropdown_switcher($languages, $current_flag) {
        ?>
        <div class="dpt-dropdown">
            <button class="dpt-dropdown-toggle" type="button">
                <img src="<?php echo esc_url($current_flag); ?>" alt="<?php echo esc_attr($languages[$this->current_language]); ?>" class="dpt-flag">
                <span class="dpt-lang-name"><?php echo esc_html($languages[$this->current_language]); ?></span>
                <span class="dpt-dropdown-arrow">▼</span>
            </button>
            <ul class="dpt-dropdown-menu">
                <?php foreach ($languages as $code => $name): ?>
                    <?php if ($code !== $this->current_language): ?>
                        <li>
                            <a href="#" class="dpt-lang-option" data-lang="<?php echo esc_attr($code); ?>">
                                <img src="<?php echo esc_url($this->get_flag_url($code)); ?>" alt="<?php echo esc_attr($name); ?>" class="dpt-flag">
                                <span class="dpt-lang-name"><?php echo esc_html($name); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Renderizza switcher inline
     */
    private function render_inline_switcher($languages) {
        ?>
        <div class="dpt-inline-flags">
            <?php foreach ($languages as $code => $name): ?>
                <a href="#" class="dpt-lang-option <?php echo $code === $this->current_language ? 'active' : ''; ?>" 
                   data-lang="<?php echo esc_attr($code); ?>" title="<?php echo esc_attr($name); ?>">
                    <img src="<?php echo esc_url($this->get_flag_url($code)); ?>" alt="<?php echo esc_attr($name); ?>" class="dpt-flag">
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Renderizza switcher popup - CORREZIONE POPUP
     */
    private function render_popup_switcher($languages, $current_flag) {
        ?>
        <button class="dpt-popup-trigger" type="button" aria-label="<?php esc_attr_e('Seleziona lingua', 'dynamic-translator'); ?>">
            <img src="<?php echo esc_url($current_flag); ?>" alt="<?php echo esc_attr($languages[$this->current_language]); ?>" class="dpt-flag">
            <span class="dpt-lang-name"><?php echo esc_html($languages[$this->current_language]); ?></span>
        </button>
        
        <div class="dpt-popup-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="dpt-popup-title">
            <div class="dpt-popup-content">
                <div class="dpt-popup-header">
                    <h3 id="dpt-popup-title"><?php _e('Seleziona Lingua', 'dynamic-translator'); ?></h3>
                    <button class="dpt-popup-close" type="button" aria-label="<?php esc_attr_e('Chiudi', 'dynamic-translator'); ?>">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="dpt-popup-body">
                    <div class="dpt-lang-grid">
                        <?php foreach ($languages as $code => $name): ?>
                            <button class="dpt-lang-card dpt-lang-option <?php echo $code === $this->current_language ? 'active' : ''; ?>" 
                                    type="button"
                                    data-lang="<?php echo esc_attr($code); ?>"
                                    <?php echo $code === $this->current_language ? 'aria-current="page"' : ''; ?>
                                    aria-label="<?php echo esc_attr($name); ?>">
                                <img src="<?php echo esc_url($this->get_flag_url($code)); ?>" alt="<?php echo esc_attr($name); ?>" class="dpt-flag">
                                <span class="dpt-lang-name"><?php echo esc_html($name); ?></span>
                                <?php if ($code === $this->current_language): ?>
                                    <span class="dpt-check-mark" aria-hidden="true">✓</span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Ottiene le lingue disponibili con nomi
     */
    private function get_available_languages() {
        $all_languages = array(
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
        
        $available = array();
        foreach ($this->enabled_languages as $code) {
            if (isset($all_languages[$code])) {
                $available[$code] = $all_languages[$code];
            }
        }
        
        return $available;
    }
    
    /**
     * Ottiene URL bandiera per lingua
     */
    private function get_flag_url($lang_code) {
        $flag_url = DPT_PLUGIN_URL . 'assets/flags/' . $lang_code . '.svg';
        
        // Controlla se file esiste, altrimenti usa bandiera generica
        $flag_path = DPT_PLUGIN_PATH . 'assets/flags/' . $lang_code . '.svg';
        if (!file_exists($flag_path)) {
            $flag_url = DPT_PLUGIN_URL . 'assets/flags/default.svg';
        }
        
        return apply_filters('dpt_flag_url', $flag_url, $lang_code);
    }
    
    /**
     * CORREZIONE PRINCIPALE: AJAX cambio lingua con debugging migliorato
     */
    public function ajax_change_language() {
        // Log iniziale
        error_log('DPT: ajax_change_language called');
        
        // CORREZIONE: Verifica nonce corretto
        if (!wp_verify_nonce($_POST['nonce'], 'dpt_frontend_nonce')) {
            error_log('DPT: Nonce verification failed for change language');
            wp_send_json_error(array(
                'message' => __('Errore di sicurezza', 'dynamic-translator'),
                'code' => 'invalid_nonce',
                'debug' => 'Nonce non valido'
            ));
        }
        
        $new_language = sanitize_text_field($_POST['language']);
        
        // CORREZIONE: Validazione lingua migliorata
        if (empty($new_language)) {
            error_log('DPT: Empty language provided');
            wp_send_json_error(array(
                'message' => __('Lingua non specificata', 'dynamic-translator'),
                'code' => 'missing_language'
            ));
        }
        
        if (!in_array($new_language, $this->enabled_languages)) {
            error_log("DPT: Unsupported language: {$new_language}");
            wp_send_json_error(array(
                'message' => __('Lingua non supportata', 'dynamic-translator'),
                'code' => 'unsupported_language',
                'language' => $new_language,
                'enabled_languages' => $this->enabled_languages
            ));
        }
        
        try {
            // CORREZIONE: Gestione cookie migliorata con controllo errori
            $cookie_domain = parse_url(home_url(), PHP_URL_HOST);
            $cookie_set = setcookie('dpt_current_lang', $new_language, array(
                'expires' => time() + (30 * DAY_IN_SECONDS),
                'path' => '/',
                'domain' => $cookie_domain,
                'secure' => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax'
            ));
            
            if (!$cookie_set) {
                error_log('DPT: Failed to set language cookie');
            }
            
            // Aggiorna lingua corrente nella sessione
            $this->current_language = $new_language;
            
            // Log dell'operazione
            error_log("DPT: Language successfully changed to {$new_language} for user " . get_current_user_id());
            
            // CORREZIONE: Risposta di successo SEMPRE (anche se cookie fallisce)
            wp_send_json_success(array(
                'message' => sprintf(__('Lingua cambiata in %s', 'dynamic-translator'), $new_language),
                'language' => $new_language,
                'previous_language' => $this->current_language,
                'reload' => dpt_get_option('reload_on_change', false),
                'auto_translate' => dpt_get_option('translate_dynamic_content', true),
                'default_lang' => $this->default_language,
                'cookie_set' => $cookie_set,
                'timestamp' => current_time('mysql'),
                'debug' => array(
                    'enabled_languages' => $this->enabled_languages,
                    'current_user' => get_current_user_id(),
                    'site_url' => home_url()
                )
            ));
            
        } catch (Exception $e) {
            error_log('DPT: Exception in ajax_change_language: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Errore interno durante il cambio lingua', 'dynamic-translator'),
                'code' => 'internal_error',
                'debug' => $e->getMessage()
            ));
        }
    }
    
    /**
     * CORREZIONE: AJAX traduzione elemento con timeout e retry
     */
    public function ajax_translate_element() {
        // CORREZIONE: Verifica nonce e validazione input
        if (!wp_verify_nonce($_POST['nonce'], 'dpt_frontend_nonce')) {
            error_log('DPT: Nonce verification failed for translate element');
            wp_send_json_error(array(
                'message' => __('Errore di sicurezza', 'dynamic-translator'),
                'code' => 'invalid_nonce'
            ));
        }
        
        $content = sanitize_textarea_field($_POST['content']);
        $source_lang = sanitize_text_field($_POST['source_lang']);
        $target_lang = sanitize_text_field($_POST['target_lang']);
        $cache_key = sanitize_text_field($_POST['cache_key']);
        
        // Validazione input
        if (empty($content)) {
            wp_send_json_success(array('translation' => $content));
        }
        
        if (empty($source_lang) || empty($target_lang)) {
            wp_send_json_error(array(
                'message' => __('Lingue non specificate', 'dynamic-translator'),
                'code' => 'missing_languages'
            ));
        }
        
        // Se è la stessa lingua, restituisci originale
        if ($source_lang === $target_lang) {
            wp_send_json_success(array('translation' => $content));
        }
        
        // NUOVO: Controllo lunghezza contenuto
        if (strlen($content) > 5000) {
            wp_send_json_error(array(
                'message' => __('Contenuto troppo lungo per la traduzione', 'dynamic-translator'),
                'code' => 'content_too_long'
            ));
        }
        
        try {
            $plugin = DynamicPageTranslator::get_instance();
            
            // Controlla cache
            $cache_handler = $plugin->get_cache_handler();
            $cached_translation = $cache_handler->get_translation($cache_key);
            
            if ($cached_translation !== false) {
                wp_send_json_success(array(
                    'translation' => $cached_translation,
                    'from_cache' => true,
                    'cache_key' => $cache_key
                ));
            }
            
            // CORREZIONE: Traduce con timeout e gestione errori
            $api_handler = $plugin->get_api_handler();
            
            // Imposta timeout per evitare richieste bloccanti
            ini_set('max_execution_time', 30);
            
            $translation = $api_handler->translate($content, $source_lang, $target_lang);
            
            if (is_wp_error($translation)) {
                error_log('DPT: Translation error: ' . $translation->get_error_message());
                wp_send_json_error(array(
                    'message' => $translation->get_error_message(),
                    'code' => $translation->get_error_code(),
                    'original_content' => $content
                ));
            }
            
            if (empty($translation) || $translation === $content) {
                error_log('DPT: Empty or unchanged translation received');
                wp_send_json_error(array(
                    'message' => __('Traduzione non riuscita', 'dynamic-translator'),
                    'code' => 'translation_failed',
                    'original_content' => $content
                ));
            }
            
            // Salva in cache
            $cache_saved = $cache_handler->save_translation($cache_key, $translation, $content, $source_lang, $target_lang);
            
            wp_send_json_success(array(
                'translation' => $translation,
                'from_cache' => false,
                'cache_key' => $cache_key,
                'cache_saved' => $cache_saved,
                'source_lang' => $source_lang,
                'target_lang' => $target_lang,
                'timestamp' => current_time('mysql'),
                'debug' => array(
                    'content_length' => strlen($content),
                    'translation_length' => strlen($translation)
                )
            ));
            
        } catch (Exception $e) {
            error_log('DPT: Exception in ajax_translate_element: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Errore interno durante la traduzione', 'dynamic-translator'),
                'code' => 'internal_error',
                'original_content' => $content,
                'debug' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Ottiene lingua corrente
     */
    private function get_current_language() {
        // Priorità: GET parameter, cookie, browser language, default
        
        // 1. Parametro GET
        if (isset($_GET['lang']) && in_array($_GET['lang'], $this->enabled_languages)) {
            return sanitize_text_field($_GET['lang']);
        }
        
        // 2. Cookie
        if (isset($_COOKIE['dpt_current_lang']) && in_array($_COOKIE['dpt_current_lang'], $this->enabled_languages)) {
            return sanitize_text_field($_COOKIE['dpt_current_lang']);
        }
        
        // 3. Auto-detect browser language
        if (dpt_get_option('auto_detect_language', true)) {
            $browser_lang = $this->detect_browser_language();
            if ($browser_lang && in_array($browser_lang, $this->enabled_languages)) {
                return $browser_lang;
            }
        }
        
        // 4. Default
        return $this->default_language;
    }
    
    /**
     * Rileva lingua del browser
     */
    private function detect_browser_language() {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return false;
        }
        
        $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $languages = explode(',', $accept_language);
        
        foreach ($languages as $language) {
            $lang_code = substr(trim($language), 0, 2);
            if (in_array($lang_code, $this->enabled_languages)) {
                return $lang_code;
            }
        }
        
        return false;
    }
    
    /**
     * Ottiene informazioni pagina per SEO multilingue
     */
    public function get_page_language_info() {
        return array(
            'current_language' => $this->current_language,
            'default_language' => $this->default_language,
            'enabled_languages' => $this->enabled_languages,
            'page_url' => get_permalink(),
            'alternative_urls' => $this->get_alternative_urls()
        );
    }
    
    /**
     * Ottiene URL alternativi per altre lingue
     */
    private function get_alternative_urls() {
        $alternatives = array();
        
        foreach ($this->enabled_languages as $lang) {
            if ($lang !== $this->current_language) {
                $alternatives[$lang] = add_query_arg('lang', $lang, get_permalink());
            }
        }
        
        return $alternatives;
    }
    
    /**
     * Applica posizioni personalizzate per bandiere
     */
    public function apply_custom_flag_positions() {
        $custom_positions = dpt_get_option('flag_custom_positions', array());
        
        if (!empty($custom_positions) && is_array($custom_positions)) {
            add_action('wp_footer', function() use ($custom_positions) {
                echo '<script type="text/javascript">';
                echo 'var dptCustomPositions = ' . json_encode($custom_positions) . ';';
                echo '</script>';
            }, 15);
        }
    }
}