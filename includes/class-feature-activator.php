<?php
/**
 * Attivatore Funzionalità per Dynamic Page Translator
 * File: includes/class-feature-activator.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_Feature_Activator {
    
    public function __construct() {
        $this->init_hooks();
        $this->activate_features();
    }
    
    /**
     * Inizializza hook
     */
    private function init_hooks() {
        add_action('admin_notices', array($this, 'show_activation_notice'));
        add_action('admin_init', array($this, 'redirect_to_settings'));
    }
    
    /**
     * Attiva tutte le funzionalità
     */
    private function activate_features() {
        // Attiva traduzione live
        $this->activate_live_translation();
        
        // Attiva modelli OpenRouter estesi
        $this->activate_extended_models();
        
        // Attiva modulo WooCommerce
        $this->activate_woocommerce_module();
        
        // Attiva dizionario personalizzato
        $this->activate_custom_dictionary();
        
        // Salva flag di attivazione
        update_option('dpt_features_activated', true);
        update_option('dpt_features_activation_time', time());
    }
    
    /**
     * Attiva traduzione live
     */
    private function activate_live_translation() {
        // Imposta opzioni performance per traduzione live
        $performance_options = array(
            'performance_batch_size' => 20,
            'performance_max_concurrent' => 5,
            'performance_cache_preload' => true,
            'performance_live_translation' => true,
            'performance_streaming_enabled' => true,
            'performance_parallel_processing' => true
        );
        
        foreach ($performance_options as $option => $value) {
            dpt_update_option($option, $value);
        }
    }
    
    /**
     * Attiva modelli OpenRouter estesi
     */
    private function activate_extended_models() {
        // Assicura che il provider sia impostato su OpenRouter
        dpt_update_option('translation_provider', 'openrouter');
        
        // Imposta modello predefinito veloce
        dpt_update_option('openrouter_model', 'anthropic/claude-3-haiku');
        
        // Abilita filtri modelli
        dpt_update_option('enable_model_filters', true);
    }
    
    /**
     * Attiva modulo WooCommerce
     */
    private function activate_woocommerce_module() {
        // Ottieni opzioni attuali
        $options = get_option('dpt_woocommerce_translator_options', array());
        
        // Imposta opzioni WooCommerce
        $woocommerce_options = array(
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
            'live_translation' => true,
            'excluded_products' => array(),
            'excluded_categories' => array()
        );
        
        // Unisci con opzioni esistenti
        $options = array_merge($options, $woocommerce_options);
        
        // Salva opzioni
        update_option('dpt_woocommerce_translator_options', $options);
    }
    
    /**
     * Attiva dizionario personalizzato
     */
    private function activate_custom_dictionary() {
        // Ottieni opzioni attuali
        $options = get_option('dpt_custom_dictionary_options', array());
        
        // Imposta opzioni dizionario
        $dictionary_options = array(
            'enabled' => true,
            'excluded_words' => isset($options['excluded_words']) ? $options['excluded_words'] : array(),
            'custom_translations' => isset($options['custom_translations']) ? $options['custom_translations'] : array()
        );
        
        // Salva opzioni
        update_option('dpt_custom_dictionary_options', $dictionary_options);
    }
    
    /**
     * Mostra notifica di attivazione
     */
    public function show_activation_notice() {
        // Verifica se è la prima volta dopo l'attivazione
        $activation_time = get_option('dpt_features_activation_time', 0);
        
        if ($activation_time > 0 && (time() - $activation_time) < 86400) { // 24 ore
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php _e('Dynamic Page Translator: Nuove funzionalità attivate!', 'dynamic-translator'); ?></strong></p>
                <p><?php _e('Sono state attivate le seguenti nuove funzionalità:', 'dynamic-translator'); ?></p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><?php _e('Traduzione live e ottimizzazioni performance', 'dynamic-translator'); ?></li>
                    <li><?php _e('Modelli OpenRouter estesi con filtri avanzati', 'dynamic-translator'); ?></li>
                    <li><?php _e('Modulo WooCommerce migliorato', 'dynamic-translator'); ?></li>
                    <li><?php _e('Dizionario personalizzato per escludere parole dalla traduzione', 'dynamic-translator'); ?></li>
                </ul>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=dynamic-translator'); ?>" class="button button-primary">
                        <?php _e('Vai alle impostazioni', 'dynamic-translator'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Redirect alle impostazioni dopo attivazione
     */
    public function redirect_to_settings() {
        // Verifica se è necessario il redirect
        if (get_option('dpt_features_redirect', false)) {
            // Rimuovi flag redirect
            delete_option('dpt_features_redirect');
            
            // Redirect
            wp_redirect(admin_url('admin.php?page=dynamic-translator&activated=1'));
            exit;
        }
    }
}

// Inizializza attivatore funzionalità
new DPT_Feature_Activator();
