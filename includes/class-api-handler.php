<?php
/**
 * Gestore API per le traduzioni
 * File: includes/class-api-handler.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_API_Handler {
    
    private $providers = array();
    private $current_provider;
    private $rate_limits = array();
    
    public function __construct() {
        $this->init_providers();
        $this->current_provider = dpt_get_option('translation_provider', 'google');
        
        // Hook per permettere registrazione provider personalizzati
        do_action('dpt_register_translation_providers', $this);
    }
    
    /**
     * Inizializza i provider di traduzione
     */
    private function init_providers() {
        $this->providers = array(
            'google' => array(
                'name' => 'Google Translate',
                'class' => 'DPT_Google_Translate_Provider',
                'enabled' => true,
                'requires_api_key' => true
            ),
            'openrouter' => array(
                'name' => 'OpenRouter AI',
                'class' => 'DPT_OpenRouter_Translate_Provider',
                'enabled' => true,
                'requires_api_key' => true
            )
        );
    }
    
    /**
     * Traduce il contenuto usando il provider selezionato
     */
    public function translate($content, $source_lang = 'auto', $target_lang = 'en') {
        // Verifica se la traduzione è necessaria
        if ($source_lang === $target_lang) {
            return $content;
        }
        
        // Controlla rate limiting
        if (!$this->check_rate_limit()) {
            return new WP_Error('rate_limit', __('Limite di traduzione raggiunto. Riprova più tardi.', 'dynamic-translator'));
        }
        
        // Ottiene il provider
        $provider = $this->get_provider($this->current_provider);
        
        if (!$provider) {
            return new WP_Error('no_provider', __('Provider di traduzione non disponibile', 'dynamic-translator'));
        }
        
        // Pulisce e prepara il contenuto
        $content = $this->prepare_content($content);
        
        // Esegue la traduzione
        $translation = $provider->translate($content, $source_lang, $target_lang);
        
        // Log della traduzione
        $this->log_translation($content, $translation, $source_lang, $target_lang);
        
        // Aggiorna rate limiting
        $this->update_rate_limit();
        
        return $translation;
    }
    
    /**
     * Ottiene istanza del provider
     */
    private function get_provider($provider_name) {
        if (!isset($this->providers[$provider_name])) {
            return false;
        }
        
        $provider_config = $this->providers[$provider_name];
        
        if (!$provider_config['enabled']) {
            return false;
        }
        
        $class_name = $provider_config['class'];
        
        if (!class_exists($class_name)) {
            return false;
        }
        
        return new $class_name();
    }
    
    /**
     * Prepara il contenuto per la traduzione
     */
    private function prepare_content($content) {
        // Rimuove tag HTML se necessario ma preserva la struttura
        $preserve_html = dpt_get_option('preserve_html', true);
        
        if (!$preserve_html) {
            $content = wp_strip_all_tags($content);
        }
        
        // Applica filtri personalizzati
        $content = apply_filters('dpt_prepare_content', $content);
        
        return trim($content);
    }
    
    /**
     * Controlla rate limiting
     */
    private function check_rate_limit() {
        $provider = $this->current_provider;
        $limit_key = 'dpt_rate_limit_' . $provider;
        
        $limits = get_option($limit_key, array(
            'count' => 0,
            'reset_time' => time() + 3600 // Reset ogni ora
        ));
        
        // Reset se è passato il tempo limite
        if (time() > $limits['reset_time']) {
            $limits = array(
                'count' => 0,
                'reset_time' => time() + 3600
            );
        }
        
        $max_requests = $this->get_provider_rate_limit($provider);
        
        return $limits['count'] < $max_requests;
    }
    
    /**
     * Aggiorna contatore rate limiting
     */
    private function update_rate_limit() {
        $provider = $this->current_provider;
        $limit_key = 'dpt_rate_limit_' . $provider;
        
        $limits = get_option($limit_key, array(
            'count' => 0,
            'reset_time' => time() + 3600
        ));
        
        $limits['count']++;
        
        update_option($limit_key, $limits);
    }
    
    /**
     * Ottiene limite rate per provider
     */
    private function get_provider_rate_limit($provider) {
        $limits = array(
            'google' => dpt_get_option('google_rate_limit', 1000),
            'openrouter' => dpt_get_option('openrouter_rate_limit', 100)
        );
        
        return isset($limits[$provider]) ? $limits[$provider] : 100;
    }
    
    /**
     * Log delle traduzioni
     */
    private function log_translation($original, $translation, $source_lang, $target_lang) {
        if (!dpt_get_option('enable_translation_log', false)) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'provider' => $this->current_provider,
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
            'original_length' => strlen($original),
            'translation_length' => strlen($translation),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip()
        );
        
        $log = get_option('dpt_translation_log', array());
        $log[] = $log_entry;
        
        // Mantiene solo le ultime 1000 traduzioni
        if (count($log) > 1000) {
            $log = array_slice($log, -1000);
        }
        
        update_option('dpt_translation_log', $log);
    }
    
    /**
     * Ottiene IP client
     */
    private function get_client_ip() {
        $ip_fields = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_fields as $field) {
            if (!empty($_SERVER[$field])) {
                $ip = $_SERVER[$field];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Ottiene statistiche traduzioni
     */
    public function get_translation_stats() {
        $log = get_option('dpt_translation_log', array());
        
        $stats = array(
            'total_translations' => count($log),
            'translations_today' => 0,
            'translations_this_month' => 0,
            'providers_usage' => array(),
            'languages_usage' => array(),
            'average_length' => 0
        );
        
        $today = date('Y-m-d');
        $this_month = date('Y-m');
        $total_length = 0;
        
        foreach ($log as $entry) {
            $entry_date = date('Y-m-d', strtotime($entry['timestamp']));
            $entry_month = date('Y-m', strtotime($entry['timestamp']));
            
            if ($entry_date === $today) {
                $stats['translations_today']++;
            }
            
            if ($entry_month === $this_month) {
                $stats['translations_this_month']++;
            }
            
            // Statistiche provider
            $provider = $entry['provider'];
            if (!isset($stats['providers_usage'][$provider])) {
                $stats['providers_usage'][$provider] = 0;
            }
            $stats['providers_usage'][$provider]++;
            
            // Statistiche lingue
            $lang_pair = $entry['source_lang'] . '_' . $entry['target_lang'];
            if (!isset($stats['languages_usage'][$lang_pair])) {
                $stats['languages_usage'][$lang_pair] = 0;
            }
            $stats['languages_usage'][$lang_pair]++;
            
            $total_length += $entry['original_length'];
        }
        
        if (count($log) > 0) {
            $stats['average_length'] = round($total_length / count($log));
        }
        
        return $stats;
    }
    
    /**
     * Registra un nuovo provider
     */
    public function register_provider($provider_name, $provider_config) {
        $this->providers[$provider_name] = $provider_config;
        
        do_action('dpt_provider_registered', $provider_name, $provider_config);
    }
    
    /**
     * Ottiene provider disponibili
     */
    public function get_available_providers() {
        return $this->providers;
    }
    
    /**
     * Cambia provider corrente
     */
    public function set_provider($provider_name) {
        if (isset($this->providers[$provider_name])) {
            $this->current_provider = $provider_name;
            dpt_update_option('translation_provider', $provider_name);
            return true;
        }
        
        return false;
    }
    
    /**
     * Ottiene provider corrente
     */
    public function get_current_provider() {
        return $this->current_provider;
    }
    
    /**
     * Testa connessione provider
     */
    public function test_provider($provider_name) {
        $provider = $this->get_provider($provider_name);
        
        if (!$provider) {
            return new WP_Error('no_provider', __('Provider non trovato', 'dynamic-translator'));
        }
        
        if (method_exists($provider, 'test_connection')) {
            return $provider->test_connection();
        }
        
        // Test di base con traduzione semplice
        $test_result = $provider->translate('Hello', 'en', 'es');
        
        if (is_wp_error($test_result)) {
            return $test_result;
        }
        
        return true;
    }
    
    /**
     * Rileva lingua del contenuto
     */
    public function detect_language($content) {
        $provider = $this->get_provider($this->current_provider);
        
        if (!$provider || !method_exists($provider, 'detect_language')) {
            // Fallback: usa API di rilevamento linguaggio semplice
            return $this->simple_language_detection($content);
        }
        
        return $provider->detect_language($content);
    }
    
    /**
     * Rilevamento lingua semplice (fallback)
     */
    private function simple_language_detection($content) {
        // Implementazione di base basata su pattern comuni
        $patterns = array(
            'en' => array('the', 'and', 'is', 'in', 'to', 'of', 'a', 'that'),
            'it' => array('il', 'la', 'di', 'che', 'e', 'un', 'in', 'per'),
            'es' => array('el', 'la', 'de', 'que', 'y', 'en', 'un', 'es'),
            'fr' => array('le', 'de', 'et', 'à', 'un', 'il', 'être', 'et'),
            'de' => array('der', 'die', 'und', 'in', 'den', 'von', 'zu', 'das')
        );
        
        $content_lower = strtolower($content);
        $scores = array();
        
        foreach ($patterns as $lang => $words) {
            $score = 0;
            foreach ($words as $word) {
                $score += substr_count($content_lower, ' ' . $word . ' ');
            }
            $scores[$lang] = $score;
        }
        
        arsort($scores);
        $detected_lang = key($scores);
        
        return $scores[$detected_lang] > 0 ? $detected_lang : 'en';
    }
}

/**
 * Interfaccia base per i provider di traduzione
 */
interface DPT_Translation_Provider_Interface {
    public function translate($content, $source_lang, $target_lang);
    public function test_connection();
    public function get_supported_languages();
}