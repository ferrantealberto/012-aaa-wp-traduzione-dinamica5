<?php
/**
 * Modulo Flag Display
 * File: modules/flag-display/flag-display.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_Flag_Display_Module {
    
    private $flag_positions = array();
    private $flag_styles = array();
    
    public function __construct() {
        $this->init_properties();
        $this->init_hooks();
        
        // Registra il modulo DOPO che il plugin principale è inizializzato
        add_action('dpt_modules_loaded', array($this, 'register_module'), 5);
    }
    
    /**
     * Inizializza le proprietà
     */
    private function init_properties() {
        $this->flag_positions = array(
            'top-left' => __('In alto a sinistra', 'dynamic-translator'),
            'top-right' => __('In alto a destra', 'dynamic-translator'),
            'top-center' => __('In alto al centro', 'dynamic-translator'),
            'bottom-left' => __('In basso a sinistra', 'dynamic-translator'),
            'bottom-right' => __('In basso a destra', 'dynamic-translator'),
            'bottom-center' => __('In basso al centro', 'dynamic-translator'),
            'header' => __('Nell\'header', 'dynamic-translator'),
            'footer' => __('Nel footer', 'dynamic-translator'),
            'menu' => __('Nel menu', 'dynamic-translator'),
            'sidebar' => __('Nella sidebar', 'dynamic-translator'),
            'floating' => __('Fluttuante', 'dynamic-translator'),
            'custom' => __('Personalizzato', 'dynamic-translator')
        );
        
        $this->flag_styles = array(
            'dropdown' => __('Menu a tendina', 'dynamic-translator'),
            'inline' => __('Bandiere in linea', 'dynamic-translator'),
            'popup' => __('Popup modale', 'dynamic-translator'),
            'sidebar-slide' => __('Sidebar scorrevole', 'dynamic-translator'),
            'circle-menu' => __('Menu circolare', 'dynamic-translator'),
            'minimal' => __('Minimale', 'dynamic-translator')
        );
    }
    
    /**
     * Inizializza gli hook
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_flag_assets'));
        add_action('wp_footer', array($this, 'render_flag_switcher'), 5);
        add_filter('body_class', array($this, 'add_body_classes'));
        
        // Hook per posizioni specifiche
        add_action('wp_head', array($this, 'add_header_flags'));
        add_action('wp_footer', array($this, 'add_footer_flags'));
        add_filter('wp_nav_menu_items', array($this, 'add_menu_flags'), 10, 2);
        add_action('dynamic_sidebar_before', array($this, 'add_sidebar_flags'));
        
        // Hook admin per gestione bandiere
        add_action('admin_menu', array($this, 'add_flag_admin_menu'));
        add_action('admin_post_dpt_upload_flag', array($this, 'handle_flag_upload'));
        
        // AJAX per preview
        add_action('wp_ajax_dpt_flag_preview', array($this, 'ajax_flag_preview'));
    }
    
    /**
     * Registra il modulo nel sistema principale
     */
    public function register_module() {
        // Ora possiamo chiamare get_instance() in sicurezza
        $plugin = DynamicPageTranslator::get_instance();
        $plugin->register_module('flag_display', $this);
    }
    
    /**
     * Enqueue assets per bandiere
     */
    public function enqueue_flag_assets() {
        wp_enqueue_style(
            'dpt-flags',
            DPT_PLUGIN_URL . 'assets/css/flags.css',
            array(),
            DPT_VERSION
        );
        
        wp_enqueue_script(
            'dpt-flags',
            DPT_PLUGIN_URL . 'assets/js/flags.js',
            array('jquery'),
            DPT_VERSION,
            true
        );
        
        // Localizza script bandiere
        wp_localize_script('dpt-flags', 'dptFlags', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dpt_flags_nonce'),
            'position' => dpt_get_option('flag_position', 'top-right'),
            'style' => dpt_get_option('flag_style', 'dropdown'),
            'customPositions' => dpt_get_option('flag_custom_positions', array()),
            'animations' => dpt_get_option('flag_animations', true),
            'hideOnMobile' => dpt_get_option('flag_hide_mobile', false),
            'showLabels' => dpt_get_option('flag_show_labels', true),
            'autoHide' => dpt_get_option('flag_auto_hide', false),
            'strings' => array(
                'selectLanguage' => __('Seleziona lingua', 'dynamic-translator'),
                'currentLanguage' => __('Lingua corrente', 'dynamic-translator'),
                'changeLanguage' => __('Cambia lingua', 'dynamic-translator'),
                'close' => __('Chiudi', 'dynamic-translator')
            )
        ));
    }
    
    /**
     * Renderizza il flag switcher principale
     */
    public function render_flag_switcher() {
        $position = dpt_get_option('flag_position', 'top-right');
        $style = dpt_get_option('flag_style', 'dropdown');
        
        // Non renderizzare se posizione è gestita altrove
        if (in_array($position, array('header', 'footer', 'menu', 'sidebar', 'custom'))) {
            return;
        }
        
        echo '<div id="dpt-flag-switcher" class="dpt-flag-switcher dpt-position-' . esc_attr($position) . ' dpt-style-' . esc_attr($style) . '">';
        $this->render_flag_switcher_content($style);
        echo '</div>';
    }
    
    /**
     * Renderizza contenuto switcher
     */
    private function render_flag_switcher_content($style) {
        $current_lang = $this->get_current_language();
        $languages = $this->get_available_languages();
        $show_labels = dpt_get_option('flag_show_labels', true);
        
        switch ($style) {
            case 'dropdown':
                $this->render_dropdown_style($current_lang, $languages, $show_labels);
                break;
            case 'inline':
                $this->render_inline_style($current_lang, $languages, $show_labels);
                break;
            case 'popup':
                $this->render_popup_style($current_lang, $languages, $show_labels);
                break;
            case 'sidebar-slide':
                $this->render_sidebar_slide_style($current_lang, $languages, $show_labels);
                break;
            case 'circle-menu':
                $this->render_circle_menu_style($current_lang, $languages, $show_labels);
                break;
            case 'minimal':
                $this->render_minimal_style($current_lang, $languages, $show_labels);
                break;
            default:
                $this->render_dropdown_style($current_lang, $languages, $show_labels);
        }
    }
    
    /**
     * Renderizza stile dropdown
     */
    private function render_dropdown_style($current_lang, $languages, $show_labels) {
        $current_flag = $this->get_flag_image($current_lang);
        ?>
        <div class="dpt-dropdown-container">
            <button class="dpt-dropdown-trigger" type="button" aria-expanded="false" aria-haspopup="true">
                <?php echo $current_flag; ?>
                <?php if ($show_labels): ?>
                    <span class="dpt-lang-label"><?php echo esc_html($languages[$current_lang] ?? $current_lang); ?></span>
                <?php endif; ?>
                <svg class="dpt-dropdown-arrow" width="12" height="8" viewBox="0 0 12 8">
                    <path d="M6 8L0 2h12z" fill="currentColor"/>
                </svg>
            </button>
            <ul class="dpt-dropdown-menu" role="menu">
                <?php foreach ($languages as $code => $name): ?>
                    <?php if ($code !== $current_lang): ?>
                        <li role="none">
                            <button class="dpt-lang-option" role="menuitem" data-lang="<?php echo esc_attr($code); ?>">
                                <?php echo $this->get_flag_image($code); ?>
                                <?php if ($show_labels): ?>
                                    <span class="dpt-lang-label"><?php echo esc_html($name); ?></span>
                                <?php endif; ?>
                            </button>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Renderizza stile inline
     */
    private function render_inline_style($current_lang, $languages, $show_labels) {
        ?>
        <div class="dpt-inline-container">
            <?php foreach ($languages as $code => $name): ?>
                <button class="dpt-lang-option <?php echo $code === $current_lang ? 'active' : ''; ?>" 
                        data-lang="<?php echo esc_attr($code); ?>" 
                        title="<?php echo esc_attr($name); ?>"
                        <?php echo $code === $current_lang ? 'aria-current="page"' : ''; ?>>
                    <?php echo $this->get_flag_image($code); ?>
                    <?php if ($show_labels): ?>
                        <span class="dpt-lang-label"><?php echo esc_html($name); ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Renderizza stile popup
     */
    private function render_popup_style($current_lang, $languages, $show_labels) {
        $current_flag = $this->get_flag_image($current_lang);
        ?>
        <button class="dpt-popup-trigger" type="button">
            <?php echo $current_flag; ?>
            <?php if ($show_labels): ?>
                <span class="dpt-lang-label"><?php echo esc_html($languages[$current_lang] ?? $current_lang); ?></span>
            <?php endif; ?>
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
                            <button class="dpt-lang-card <?php echo $code === $current_lang ? 'active' : ''; ?>" 
                                    data-lang="<?php echo esc_attr($code); ?>"
                                    <?php echo $code === $current_lang ? 'aria-current="page"' : ''; ?>>
                                <?php echo $this->get_flag_image($code); ?>
                                <span class="dpt-lang-name"><?php echo esc_html($name); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    // Altri metodi di rendering...
    private function render_sidebar_slide_style($current_lang, $languages, $show_labels) {
        // Implementazione sidebar slide
        echo '<div class="dpt-sidebar-slide">Sidebar Slide Implementation</div>';
    }
    
    private function render_circle_menu_style($current_lang, $languages, $show_labels) {
        // Implementazione circle menu
        echo '<div class="dpt-circle-menu">Circle Menu Implementation</div>';
    }
    
    private function render_minimal_style($current_lang, $languages, $show_labels) {
        // Implementazione minimal
        echo '<div class="dpt-minimal">Minimal Implementation</div>';
    }
    
    /**
     * Ottiene immagine bandiera
     */
    private function get_flag_image($lang_code) {
        $flag_url = $this->get_flag_url($lang_code);
        $language_name = $this->get_language_name($lang_code);
        
        return '<img src="' . esc_url($flag_url) . '" alt="' . esc_attr($language_name) . '" class="dpt-flag" loading="lazy">';
    }
    
    /**
     * Ottiene URL bandiera
     */
    private function get_flag_url($lang_code) {
        // Controlla bandiere personalizzate prima
        $custom_flags = get_option('dpt_custom_flags', array());
        if (isset($custom_flags[$lang_code])) {
            return $custom_flags[$lang_code];
        }
        
        // Usa bandiere di default
        $flag_formats = array('svg', 'png', 'jpg', 'webp');
        
        foreach ($flag_formats as $format) {
            $flag_path = DPT_PLUGIN_PATH . 'assets/flags/' . $lang_code . '.' . $format;
            if (file_exists($flag_path)) {
                return DPT_PLUGIN_URL . 'assets/flags/' . $lang_code . '.' . $format;
            }
        }
        
        // Fallback a bandiera generica
        return DPT_PLUGIN_URL . 'assets/flags/default.svg';
    }
    
    /**
     * Aggiunge classi al body
     */
    public function add_body_classes($classes) {
        $classes[] = 'dpt-flag-position-' . dpt_get_option('flag_position', 'top-right');
        $classes[] = 'dpt-flag-style-' . dpt_get_option('flag_style', 'dropdown');
        
        if (dpt_get_option('flag_hide_mobile', false)) {
            $classes[] = 'dpt-hide-flags-mobile';
        }
        
        if (dpt_get_option('flag_animations', true)) {
            $classes[] = 'dpt-flag-animations';
        }
        
        return $classes;
    }
    
    /**
     * Aggiunge bandiere nell'header
     */
    public function add_header_flags() {
        if (dpt_get_option('flag_position', 'top-right') === 'header') {
            echo '<div class="dpt-header-flags">';
            $this->render_flag_switcher_content(dpt_get_option('flag_style', 'dropdown'));
            echo '</div>';
        }
    }
    
    /**
     * Aggiunge bandiere nel footer
     */
    public function add_footer_flags() {
        if (dpt_get_option('flag_position', 'top-right') === 'footer') {
            echo '<div class="dpt-footer-flags">';
            $this->render_flag_switcher_content(dpt_get_option('flag_style', 'dropdown'));
            echo '</div>';
        }
    }
    
    /**
     * Aggiunge bandiere nel menu
     */
    public function add_menu_flags($items, $args) {
        if (dpt_get_option('flag_position', 'top-right') !== 'menu') {
            return $items;
        }
        
        // Aggiungi solo al menu principale
        if (isset($args->theme_location) && $args->theme_location === 'primary') {
            ob_start();
            $this->render_flag_switcher_content(dpt_get_option('flag_style', 'dropdown'));
            $flag_html = ob_get_clean();
            
            $items .= '<li class="menu-item dpt-menu-flags">' . $flag_html . '</li>';
        }
        
        return $items;
    }
    
    /**
     * Aggiunge bandiere nella sidebar
     */
    public function add_sidebar_flags($sidebar_id) {
        if (dpt_get_option('flag_position', 'top-right') === 'sidebar' && $sidebar_id === 'primary') {
            echo '<div class="widget dpt-sidebar-flags">';
            echo '<h3 class="widget-title">' . __('Lingua', 'dynamic-translator') . '</h3>';
            $this->render_flag_switcher_content(dpt_get_option('flag_style', 'dropdown'));
            echo '</div>';
        }
    }
    
    /**
     * Aggiunge menu admin per gestione bandiere
     */
    public function add_flag_admin_menu() {
        add_submenu_page(
            'dynamic-translator',
            __('Gestione Bandiere', 'dynamic-translator'),
            __('Bandiere', 'dynamic-translator'),
            'manage_options',
            'dynamic-translator-flags',
            array($this, 'render_flags_admin_page')
        );
    }
    
    /**
     * Renderizza pagina admin bandiere
     */
    public function render_flags_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Gestione Bandiere', 'dynamic-translator'); ?></h1>
            <p><?php _e('Configura l\'aspetto e il comportamento del selettore di lingua.', 'dynamic-translator'); ?></p>
            
            <div class="dpt-flags-admin">
                <div class="dpt-flags-preview">
                    <h2><?php _e('Anteprima', 'dynamic-translator'); ?></h2>
                    <div id="dpt-flag-preview-container">
                        <!-- Anteprima generata via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Gestisce upload bandiere personalizzate
     */
    public function handle_flag_upload() {
        // Implementazione upload bandiere
        wp_redirect(admin_url('admin.php?page=dynamic-translator-flags'));
        exit;
    }
    
    /**
     * AJAX anteprima bandiere
     */
    public function ajax_flag_preview() {
        check_ajax_referer('dpt_flags_nonce', 'nonce');
        
        $position = sanitize_text_field($_POST['position']);
        $style = sanitize_text_field($_POST['style']);
        
        ob_start();
        echo '<div class="dpt-flag-switcher dpt-position-' . esc_attr($position) . ' dpt-style-' . esc_attr($style) . '">';
        $this->render_flag_switcher_content($style);
        echo '</div>';
        $preview_html = ob_get_clean();
        
        wp_send_json_success(array('html' => $preview_html));
    }
    
    /**
     * Metodi helper
     */
    private function get_current_language() {
        return isset($_COOKIE['dpt_current_lang']) ? sanitize_text_field($_COOKIE['dpt_current_lang']) : dpt_get_option('default_language', 'en');
    }
    
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
        
        $enabled = dpt_get_option('enabled_languages', array('en', 'es', 'fr', 'de'));
        $available = array();
        
        foreach ($enabled as $code) {
            if (isset($all_languages[$code])) {
                $available[$code] = $all_languages[$code];
            }
        }
        
        return $available;
    }
    
    private function get_language_name($lang_code) {
        $languages = $this->get_available_languages();
        return isset($languages[$lang_code]) ? $languages[$lang_code] : $lang_code;
    }
}

// Inizializza il modulo
new DPT_Flag_Display_Module();