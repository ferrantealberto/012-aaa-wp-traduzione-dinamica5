<?php
/**
 * Modulo Custom Dictionary per Dynamic Page Translator
 * File: modules/custom-dictionary/custom-dictionary.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_Custom_Dictionary_Module {
    
    private $options = array();
    private $default_options = array(
        'enabled' => true,
        'excluded_words' => array(),
        'custom_translations' => array()
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
        $this->options = get_option('dpt_custom_dictionary_options', $this->default_options);
    }
    
    /**
     * Inizializza hook
     */
    private function init_hooks() {
        // Hook admin
        add_action('admin_menu', array($this, 'add_custom_dictionary_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_dpt_save_dictionary_options', array($this, 'ajax_save_options'));
        add_action('wp_ajax_dpt_add_excluded_word', array($this, 'ajax_add_excluded_word'));
        add_action('wp_ajax_dpt_remove_excluded_word', array($this, 'ajax_remove_excluded_word'));
        add_action('wp_ajax_dpt_add_custom_translation', array($this, 'ajax_add_custom_translation'));
        add_action('wp_ajax_dpt_remove_custom_translation', array($this, 'ajax_remove_custom_translation'));
        
        // Hook per traduzione
        if ($this->options['enabled']) {
            add_filter('dpt_pre_translate_text', array($this, 'process_text_with_dictionary'), 10, 3);
            add_filter('dpt_post_translate_text', array($this, 'apply_custom_translations'), 10, 3);
        }
    }
    
    /**
     * Registra modulo
     */
    private function register_module() {
        add_action('dpt_modules_loaded', function() {
            $plugin = DynamicPageTranslator::get_instance();
            $plugin->register_module('custom_dictionary', $this);
        }, 20);
    }
    
    /**
     * Aggiunge menu admin Custom Dictionary
     */
    public function add_custom_dictionary_admin_menu() {
        add_submenu_page(
            'dynamic-translator',
            __('Custom Dictionary', 'dynamic-translator'),
            __('Custom Dictionary', 'dynamic-translator'),
            'manage_options',
            'dynamic-translator-dictionary',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue assets admin
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'dynamic-translator-dictionary') === false) {
            return;
        }
        
        wp_enqueue_style(
            'dpt-dictionary-admin',
            DPT_PLUGIN_URL . 'modules/custom-dictionary/assets/css/admin.css',
            array(),
            DPT_VERSION
        );
        
        wp_enqueue_script(
            'dpt-dictionary-admin',
            DPT_PLUGIN_URL . 'modules/custom-dictionary/assets/js/admin.js',
            array('jquery'),
            DPT_VERSION,
            true
        );
        
        wp_localize_script('dpt-dictionary-admin', 'dptDictionary', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dpt_dictionary_nonce'),
            'strings' => array(
                'saveSuccess' => __('Impostazioni salvate con successo!', 'dynamic-translator'),
                'saveError' => __('Errore durante il salvataggio delle impostazioni.', 'dynamic-translator'),
                'addSuccess' => __('Parola aggiunta con successo!', 'dynamic-translator'),
                'addError' => __('Errore durante l\'aggiunta della parola.', 'dynamic-translator'),
                'removeSuccess' => __('Parola rimossa con successo!', 'dynamic-translator'),
                'removeError' => __('Errore durante la rimozione della parola.', 'dynamic-translator'),
                'confirmDelete' => __('Sei sicuro di voler rimuovere questa parola?', 'dynamic-translator')
            )
        ));
    }
    
    /**
     * Renderizza pagina admin
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Custom Dictionary', 'dynamic-translator'); ?></h1>
            
            <div class="dpt-dictionary-header">
                <p><?php _e('Configura parole da non tradurre o traduzioni personalizzate.', 'dynamic-translator'); ?></p>
            </div>
            
            <form id="dpt-dictionary-options-form" method="post">
                <div class="dpt-dictionary-container">
                    <div class="dpt-dictionary-main-settings">
                        <h2><?php _e('Impostazioni Generali', 'dynamic-translator'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Abilita Custom Dictionary', 'dynamic-translator'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enabled" value="1" <?php checked($this->options['enabled'], true); ?>>
                                        <?php _e('Abilita dizionario personalizzato', 'dynamic-translator'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="dpt-dictionary-actions">
                            <input type="hidden" name="action" value="dpt_save_dictionary_options">
                            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('dpt_dictionary_nonce'); ?>">
                            <button type="submit" class="button button-primary"><?php _e('Salva Impostazioni', 'dynamic-translator'); ?></button>
                        </div>
                        
                        <div id="dpt-dictionary-message" class="notice" style="display: none;"></div>
                    </div>
                    
                    <div class="dpt-dictionary-excluded-words">
                        <h2><?php _e('Parole da Non Tradurre', 'dynamic-translator'); ?></h2>
                        <p><?php _e('Aggiungi parole o frasi che non devono essere tradotte in nessuna lingua.', 'dynamic-translator'); ?></p>
                        
                        <div class="dpt-add-excluded-word">
                            <input type="text" id="dpt-new-excluded-word" placeholder="<?php _e('Inserisci parola o frase...', 'dynamic-translator'); ?>">
                            <button type="button" id="dpt-add-excluded-word" class="button"><?php _e('Aggiungi', 'dynamic-translator'); ?></button>
                        </div>
                        
                        <div class="dpt-excluded-words-list">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Parola/Frase', 'dynamic-translator'); ?></th>
                                        <th class="dpt-actions-column"><?php _e('Azioni', 'dynamic-translator'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($this->options['excluded_words'])): ?>
                                        <tr class="dpt-no-items">
                                            <td colspan="2"><?php _e('Nessuna parola esclusa.', 'dynamic-translator'); ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($this->options['excluded_words'] as $word): ?>
                                            <tr>
                                                <td><?php echo esc_html($word); ?></td>
                                                <td class="dpt-actions-column">
                                                    <button type="button" class="button button-small dpt-remove-excluded-word" data-word="<?php echo esc_attr($word); ?>">
                                                        <?php _e('Rimuovi', 'dynamic-translator'); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="dpt-dictionary-custom-translations">
                        <h2><?php _e('Traduzioni Personalizzate', 'dynamic-translator'); ?></h2>
                        <p><?php _e('Aggiungi traduzioni personalizzate per parole o frasi specifiche.', 'dynamic-translator'); ?></p>
                        
                        <div class="dpt-add-custom-translation">
                            <div class="dpt-translation-row">
                                <div class="dpt-translation-field">
                                    <label><?php _e('Parola/Frase Originale', 'dynamic-translator'); ?></label>
                                    <input type="text" id="dpt-original-text" placeholder="<?php _e('Testo originale...', 'dynamic-translator'); ?>">
                                </div>
                                
                                <div class="dpt-translation-field">
                                    <label><?php _e('Lingua', 'dynamic-translator'); ?></label>
                                    <select id="dpt-translation-language">
                                        <?php
                                        $languages = dpt_get_option('enabled_languages', array());
                                        foreach ($languages as $lang_code) {
                                            echo '<option value="' . esc_attr($lang_code) . '">' . esc_html($this->get_language_name($lang_code)) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="dpt-translation-field">
                                    <label><?php _e('Traduzione', 'dynamic-translator'); ?></label>
                                    <input type="text" id="dpt-custom-translation" placeholder="<?php _e('Traduzione personalizzata...', 'dynamic-translator'); ?>">
                                </div>
                                
                                <div class="dpt-translation-actions">
                                    <button type="button" id="dpt-add-custom-translation" class="button"><?php _e('Aggiungi', 'dynamic-translator'); ?></button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="dpt-custom-translations-list">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Testo Originale', 'dynamic-translator'); ?></th>
                                        <th><?php _e('Lingua', 'dynamic-translator'); ?></th>
                                        <th><?php _e('Traduzione', 'dynamic-translator'); ?></th>
                                        <th class="dpt-actions-column"><?php _e('Azioni', 'dynamic-translator'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($this->options['custom_translations'])): ?>
                                        <tr class="dpt-no-items">
                                            <td colspan="4"><?php _e('Nessuna traduzione personalizzata.', 'dynamic-translator'); ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($this->options['custom_translations'] as $key => $translation): ?>
                                            <tr>
                                                <td><?php echo esc_html($translation['original']); ?></td>
                                                <td><?php echo esc_html($this->get_language_name($translation['language'])); ?></td>
                                                <td><?php echo esc_html($translation['translation']); ?></td>
                                                <td class="dpt-actions-column">
                                                    <button type="button" class="button button-small dpt-remove-custom-translation" data-key="<?php echo esc_attr($key); ?>">
                                                        <?php _e('Rimuovi', 'dynamic-translator'); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <style>
        .dpt-dictionary-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
        }
        .dpt-dictionary-main-settings,
        .dpt-dictionary-excluded-words,
        .dpt-dictionary-custom-translations {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .dpt-dictionary-actions {
            margin-top: 20px;
            padding: 15px 0;
        }
        .dpt-add-excluded-word {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .dpt-add-excluded-word input {
            flex: 1;
        }
        .dpt-actions-column {
            width: 100px;
            text-align: center;
        }
        .dpt-translation-row {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: flex-end;
        }
        .dpt-translation-field {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .dpt-translation-field label {
            margin-bottom: 5px;
            font-weight: 500;
        }
        .dpt-translation-actions {
            display: flex;
            align-items: flex-end;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX: Salva opzioni
     */
    public function ajax_save_options() {
        check_ajax_referer('dpt_dictionary_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $options = $this->options;
        $options['enabled'] = isset($_POST['enabled']) && $_POST['enabled'] == '1';
        
        // Salva opzioni
        update_option('dpt_custom_dictionary_options', $options);
        
        // Aggiorna opzioni locali
        $this->options = $options;
        
        wp_send_json_success('Impostazioni salvate con successo');
    }
    
    /**
     * AJAX: Aggiungi parola esclusa
     */
    public function ajax_add_excluded_word() {
        check_ajax_referer('dpt_dictionary_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $word = sanitize_text_field($_POST['word'] ?? '');
        
        if (empty($word)) {
            wp_send_json_error('Parola non valida');
        }
        
        $options = $this->options;
        
        // Verifica se la parola esiste già
        if (in_array($word, $options['excluded_words'])) {
            wp_send_json_error('Questa parola è già nella lista');
        }
        
        // Aggiungi parola
        $options['excluded_words'][] = $word;
        
        // Salva opzioni
        update_option('dpt_custom_dictionary_options', $options);
        
        // Aggiorna opzioni locali
        $this->options = $options;
        
        wp_send_json_success(array(
            'message' => 'Parola aggiunta con successo',
            'word' => $word
        ));
    }
    
    /**
     * AJAX: Rimuovi parola esclusa
     */
    public function ajax_remove_excluded_word() {
        check_ajax_referer('dpt_dictionary_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $word = sanitize_text_field($_POST['word'] ?? '');
        
        if (empty($word)) {
            wp_send_json_error('Parola non valida');
        }
        
        $options = $this->options;
        
        // Trova e rimuovi la parola
        $key = array_search($word, $options['excluded_words']);
        
        if ($key === false) {
            wp_send_json_error('Parola non trovata');
        }
        
        unset($options['excluded_words'][$key]);
        $options['excluded_words'] = array_values($options['excluded_words']); // Reindex array
        
        // Salva opzioni
        update_option('dpt_custom_dictionary_options', $options);
        
        // Aggiorna opzioni locali
        $this->options = $options;
        
        wp_send_json_success(array(
            'message' => 'Parola rimossa con successo',
            'word' => $word
        ));
    }
    
    /**
     * AJAX: Aggiungi traduzione personalizzata
     */
    public function ajax_add_custom_translation() {
        check_ajax_referer('dpt_dictionary_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $original = sanitize_text_field($_POST['original'] ?? '');
        $language = sanitize_text_field($_POST['language'] ?? '');
        $translation = sanitize_text_field($_POST['translation'] ?? '');
        
        if (empty($original) || empty($language) || empty($translation)) {
            wp_send_json_error('Dati non validi');
        }
        
        $options = $this->options;
        
        // Crea chiave unica
        $key = md5($original . '_' . $language);
        
        // Aggiungi traduzione
        $options['custom_translations'][$key] = array(
            'original' => $original,
            'language' => $language,
            'translation' => $translation
        );
        
        // Salva opzioni
        update_option('dpt_custom_dictionary_options', $options);
        
        // Aggiorna opzioni locali
        $this->options = $options;
        
        wp_send_json_success(array(
            'message' => 'Traduzione aggiunta con successo',
            'key' => $key,
            'original' => $original,
            'language' => $language,
            'language_name' => $this->get_language_name($language),
            'translation' => $translation
        ));
    }
    
    /**
     * AJAX: Rimuovi traduzione personalizzata
     */
    public function ajax_remove_custom_translation() {
        check_ajax_referer('dpt_dictionary_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $key = sanitize_text_field($_POST['key'] ?? '');
        
        if (empty($key)) {
            wp_send_json_error('Chiave non valida');
        }
        
        $options = $this->options;
        
        // Verifica se la traduzione esiste
        if (!isset($options['custom_translations'][$key])) {
            wp_send_json_error('Traduzione non trovata');
        }
        
        // Rimuovi traduzione
        unset($options['custom_translations'][$key]);
        
        // Salva opzioni
        update_option('dpt_custom_dictionary_options', $options);
        
        // Aggiorna opzioni locali
        $this->options = $options;
        
        wp_send_json_success(array(
            'message' => 'Traduzione rimossa con successo',
            'key' => $key
        ));
    }
    
    /**
     * Processa testo con dizionario prima della traduzione
     */
    public function process_text_with_dictionary($text, $source_lang, $target_lang) {
        if (empty($text) || empty($this->options['excluded_words'])) {
            return $text;
        }
        
        // Sostituisci parole escluse con placeholder
        $placeholders = array();
        
        foreach ($this->options['excluded_words'] as $index => $word) {
            $placeholder = "[[DPT_EXCLUDED_" . $index . "]]";
            $text = str_replace($word, $placeholder, $text);
            $placeholders[$placeholder] = $word;
        }
        
        // Salva placeholders in transient
        set_transient('dpt_excluded_words_' . md5($text), $placeholders, HOUR_IN_SECONDS);
        
        return $text;
    }
    
    /**
     * Applica traduzioni personalizzate dopo la traduzione
     */
    public function apply_custom_translations($translated_text, $source_lang, $target_lang) {
        if (empty($translated_text)) {
            return $translated_text;
        }
        
        // Gestione errore traduzione
        if (is_wp_error($translated_text)) {
            error_log('Custom Dictionary Translation Error: ' . $translated_text->get_error_message());
            return $translated_text->get_error_message(); // fallback con messaggio errore
        }
        
        // Ripristina parole escluse
        $placeholders = get_transient('dpt_excluded_words_' . md5($translated_text));
        
        if ($placeholders) {
            foreach ($placeholders as $placeholder => $original) {
                $translated_text = str_replace($placeholder, $original, $translated_text);
            }
            
            delete_transient('dpt_excluded_words_' . md5($translated_text));
        }
        
        // Applica traduzioni personalizzate
        if (!empty($this->options['custom_translations'])) {
            foreach ($this->options['custom_translations'] as $translation) {
                if ($translation['language'] === $target_lang) {
                    $translated_text = str_replace($translation['original'], $translation['translation'], $translated_text);
                }
            }
        }
        
        return $translated_text;
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
}

// Inizializza modulo
new DPT_Custom_Dictionary_Module();
