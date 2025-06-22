<?php
/**
 * Modulo Google Translate
 * File: modules/google-translate/google-translate.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_Google_Translate_Provider implements DPT_Translation_Provider_Interface {
    
    private $api_key;
    private $api_url = 'https://translation.googleapis.com/language/translate/v2';
    private $detect_url = 'https://translation.googleapis.com/language/translate/v2/detect';
    private $languages_url = 'https://translation.googleapis.com/language/translate/v2/languages';
    
    public function __construct() {
        $this->api_key = dpt_get_option('google_api_key', '');
        
        // Registra il modulo
        add_action('init', array($this, 'register_module'));
    }
    
    /**
     * Registra il modulo nel sistema
     */
    public function register_module() {
        $plugin = DynamicPageTranslator::get_instance();
        $plugin->register_module('google_translate', $this);
    }
    
    /**
     * Traduce il contenuto
     */
    public function translate($content, $source_lang = 'auto', $target_lang = 'en') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key Google non configurata', 'dynamic-translator'));
        }
        
        if (empty($content)) {
            return $content;
        }
        
        // Prepara i parametri
        $params = array(
            'key' => $this->api_key,
            'q' => $content,
            'target' => $target_lang,
            'format' => dpt_get_option('preserve_html', true) ? 'html' : 'text'
        );
        
        // Aggiunge lingua sorgente se specificata
        if ($source_lang !== 'auto') {
            $params['source'] = $source_lang;
        }
        
        // Esegue la richiesta
        $response = wp_remote_post($this->api_url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => http_build_query($params)
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('google_api_error', $data['error']['message']);
        }
        
        if (!isset($data['data']['translations'][0]['translatedText'])) {
            return new WP_Error('no_translation', __('Nessuna traduzione ricevuta', 'dynamic-translator'));
        }
        
        $translation = $data['data']['translations'][0]['translatedText'];
        
        // Decodifica entità HTML se necessario
        if (dpt_get_option('preserve_html', true)) {
            $translation = html_entity_decode($translation, ENT_QUOTES, 'UTF-8');
        }
        
        return $translation;
    }
    
    /**
     * Rileva la lingua del contenuto
     */
    public function detect_language($content) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key Google non configurata', 'dynamic-translator'));
        }
        
        $params = array(
            'key' => $this->api_key,
            'q' => $content
        );
        
        $response = wp_remote_post($this->detect_url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => http_build_query($params)
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('google_api_error', $data['error']['message']);
        }
        
        if (isset($data['data']['detections'][0][0]['language'])) {
            return $data['data']['detections'][0][0]['language'];
        }
        
        return 'en'; // Fallback
    }
    
    /**
     * Ottiene le lingue supportate
     */
    public function get_supported_languages() {
        static $cached_languages = null;
        
        if ($cached_languages !== null) {
            return $cached_languages;
        }
        
        if (empty($this->api_key)) {
            return array();
        }
        
        $params = array(
            'key' => $this->api_key,
            'target' => 'en' // Ottiene nomi in inglese
        );
        
        $response = wp_remote_get($this->languages_url . '?' . http_build_query($params), array(
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error']) || !isset($data['data']['languages'])) {
            return array();
        }
        
        $languages = array();
        foreach ($data['data']['languages'] as $language) {
            $languages[$language['language']] = $language['name'];
        }
        
        $cached_languages = $languages;
        return $languages;
    }
    
    /**
     * Testa la connessione API
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key non configurata', 'dynamic-translator'));
        }
        
        // Test con una traduzione semplice
        $test_result = $this->translate('Hello', 'en', 'es');
        
        if (is_wp_error($test_result)) {
            return $test_result;
        }
        
        if (empty($test_result)) {
            return new WP_Error('empty_response', __('Risposta vuota dall\'API', 'dynamic-translator'));
        }
        
        return true;
    }
    
    /**
     * Ottiene informazioni sull'utilizzo API
     */
    public function get_usage_info() {
        // Google non fornisce informazioni di utilizzo tramite API
        // Restituisce info statiche
        return array(
            'provider' => 'Google Translate',
            'daily_limit' => 'Varia per piano',
            'monthly_limit' => 'Varia per piano',
            'current_usage' => 'Non disponibile',
            'cost_per_char' => '$20 per 1M caratteri'
        );
    }
    
    /**
     * Traduzione batch per più contenuti
     */
    public function translate_batch($contents, $source_lang = 'auto', $target_lang = 'en') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key Google non configurata', 'dynamic-translator'));
        }
        
        if (empty($contents) || !is_array($contents)) {
            return array();
        }
        
        // Google permette fino a 128 testi per richiesta
        $batch_size = 100;
        $results = array();
        
        for ($i = 0; $i < count($contents); $i += $batch_size) {
            $batch = array_slice($contents, $i, $batch_size);
            
            $params = array(
                'key' => $this->api_key,
                'target' => $target_lang,
                'format' => dpt_get_option('preserve_html', true) ? 'html' : 'text'
            );
            
            // Aggiunge lingua sorgente se specificata
            if ($source_lang !== 'auto') {
                $params['source'] = $source_lang;
            }
            
            // Aggiunge tutti i testi al batch
            foreach ($batch as $text) {
                $params['q'][] = $text;
            }
            
            $response = wp_remote_post($this->api_url, array(
                'timeout' => 60,
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ),
                'body' => http_build_query($params)
            ));
            
            if (is_wp_error($response)) {
                // In caso di errore, aggiungi errori per tutti i testi del batch
                for ($j = 0; $j < count($batch); $j++) {
                    $results[] = $response;
                }
                continue;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['error'])) {
                $error = new WP_Error('google_api_error', $data['error']['message']);
                for ($j = 0; $j < count($batch); $j++) {
                    $results[] = $error;
                }
                continue;
            }
            
            if (isset($data['data']['translations'])) {
                foreach ($data['data']['translations'] as $translation) {
                    $translated_text = $translation['translatedText'];
                    
                    // Decodifica entità HTML se necessario
                    if (dpt_get_option('preserve_html', true)) {
                        $translated_text = html_entity_decode($translated_text, ENT_QUOTES, 'UTF-8');
                    }
                    
                    $results[] = $translated_text;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Ottiene le statistiche di utilizzo del modulo
     */
    public function get_module_stats() {
        $stats = get_option('dpt_google_translate_stats', array(
            'total_requests' => 0,
            'total_characters' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'last_request' => null
        ));
        
        return $stats;
    }
    
    /**
     * Aggiorna le statistiche del modulo
     */
    public function update_stats($request_successful, $character_count = 0) {
        $stats = $this->get_module_stats();
        
        $stats['total_requests']++;
        $stats['total_characters'] += $character_count;
        $stats['last_request'] = current_time('mysql');
        
        if ($request_successful) {
            $stats['successful_requests']++;
        } else {
            $stats['failed_requests']++;
        }
        
        update_option('dpt_google_translate_stats', $stats);
    }
    
    /**
     * Pulisce le statistiche
     */
    public function reset_stats() {
        delete_option('dpt_google_translate_stats');
    }
}

// Inizializza il provider se Google è selezionato
if (dpt_get_option('translation_provider', 'google') === 'google') {
    new DPT_Google_Translate_Provider();
}