<?php
/**
 * Modulo OpenRouter AI Translate - VERSIONE COMPLETA E CORRETTA
 * File: modules/openrouter-translate/openrouter-translate.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_OpenRouter_Translate_Provider implements DPT_Translation_Provider_Interface {
    
    private $api_key;
    private $api_url = 'https://openrouter.ai/api/v1/chat/completions';
    private $model;
    private $supported_languages;
    
    public function __construct() {
        $this->api_key = dpt_get_option('openrouter_api_key', '');
        $this->model = dpt_get_option('openrouter_model', 'meta-llama/llama-3.1-8b-instruct:free');
        $this->init_supported_languages();
        
        // CORREZIONE: Registra il modulo immediatamente se il plugin è già inizializzato
        if (did_action('dpt_modules_loaded')) {
            $this->register_module();
        } else {
            add_action('dpt_modules_loaded', array($this, 'register_module'), 5);
        }
    }
    
    /**
     * Inizializza le lingue supportate
     */
    private function init_supported_languages() {
        $this->supported_languages = array(
            'en' => 'English',
            'it' => 'Italian',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'ar' => 'Arabic',
            'nl' => 'Dutch',
            'sv' => 'Swedish',
            'da' => 'Danish',
            'no' => 'Norwegian',
            'fi' => 'Finnish',
            'pl' => 'Polish',
            'cs' => 'Czech',
            'hu' => 'Hungarian',
            'ro' => 'Romanian',
            'bg' => 'Bulgarian',
            'hr' => 'Croatian',
            'sk' => 'Slovak',
            'sl' => 'Slovenian',
            'et' => 'Estonian',
            'lv' => 'Latvian',
            'lt' => 'Lithuanian',
            'el' => 'Greek',
            'tr' => 'Turkish',
            'he' => 'Hebrew',
            'hi' => 'Hindi',
            'th' => 'Thai',
            'vi' => 'Vietnamese',
            'id' => 'Indonesian',
            'ms' => 'Malay',
            'tl' => 'Filipino',
            'uk' => 'Ukrainian',
            'ca' => 'Catalan',
            'eu' => 'Basque',
            'gl' => 'Galician'
        );
    }
    
    /**
     * Registra il modulo nel sistema
     */
    public function register_module() {
        try {
            $plugin = DynamicPageTranslator::get_instance();
            if ($plugin) {
                $plugin->register_module('openrouter_translate', $this);
            }
        } catch (Exception $e) {
            error_log('DPT OpenRouter Module Registration Error: ' . $e->getMessage());
        }
    }
    
    /**
     * IMPLEMENTAZIONE RICHIESTA: Ottiene le lingue supportate
     */
    public function get_supported_languages() {
        return $this->supported_languages;
    }
    
    /**
     * CORREZIONE: Test connessione migliorato
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key OpenRouter non configurata', 'dynamic-translator'));
        }
        
        // Test 1: Verifica validità API key con endpoint models
        $models_test = $this->test_api_key_validity();
        if (is_wp_error($models_test)) {
            return $models_test;
        }
        
        // Test 2: Test traduzione semplice
        $translation_test = $this->test_simple_translation();
        if (is_wp_error($translation_test)) {
            return $translation_test;
        }
        
        return true;
    }
    
    /**
     * NUOVO: Test validità API key
     */
    private function test_api_key_validity() {
        $response = wp_remote_get('https://openrouter.ai/api/v1/models', array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'HTTP-Referer' => get_site_url(),
                'X-Title' => get_bloginfo('name')
            )
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_failed', 
                sprintf(__('Errore connessione OpenRouter: %s', 'dynamic-translator'), 
                $response->get_error_message())
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 401) {
            return new WP_Error('invalid_api_key', __('API key OpenRouter non valida', 'dynamic-translator'));
        }
        
        if ($response_code === 403) {
            return new WP_Error('access_denied', __('Accesso negato - verifica i permessi API key', 'dynamic-translator'));
        }
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', 
                sprintf(__('Errore API OpenRouter (HTTP %d)', 'dynamic-translator'), $response_code)
            );
        }
        
        return true;
    }
    
    /**
     * NUOVO: Test traduzione semplice
     */
    private function test_simple_translation() {
        $request_data = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a translator. Respond only with the translation.'
                ),
                array(
                    'role' => 'user',
                    'content' => 'Translate "Hello" to Spanish. Respond only with the translation.'
                )
            ),
            'temperature' => 0,
            'max_tokens' => 10,
            'stream' => false
        );
        
        $response = wp_remote_post($this->api_url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => get_site_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode($request_data)
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('translation_test_failed', 
                sprintf(__('Test traduzione fallito: %s', 'dynamic-translator'), 
                $response->get_error_message())
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : "HTTP {$response_code}";
                
            return new WP_Error('api_request_failed', 
                sprintf(__('Richiesta API fallita: %s', 'dynamic-translator'), $error_message)
            );
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Risposta JSON non valida da OpenRouter', 'dynamic-translator'));
        }
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('incomplete_response', __('Risposta incompleta da OpenRouter', 'dynamic-translator'));
        }
        
        $translation = trim($data['choices'][0]['message']['content']);
        
        if (empty($translation) || strtolower($translation) === 'hello') {
            return new WP_Error('no_translation', __('OpenRouter non ha tradotto il testo', 'dynamic-translator'));
        }
        
        return true;
    }
    
    /**
     * Traduce il contenuto usando AI
     */
    public function translate($content, $source_lang = 'auto', $target_lang = 'en') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key OpenRouter non configurata', 'dynamic-translator'));
        }
        
        if (empty($content)) {
            return $content;
        }
        
        // Aggiorna API key se cambiata nelle impostazioni
        $this->api_key = dpt_get_option('openrouter_api_key', '');
        $this->model = dpt_get_option('openrouter_model', 'meta-llama/llama-3.1-8b-instruct:free');
        
        // Determina i nomi delle lingue
        $source_language_name = $this->get_language_name($source_lang);
        $target_language_name = $this->get_language_name($target_lang);
        
        // Crea il prompt per la traduzione
        $prompt = $this->create_translation_prompt($content, $source_language_name, $target_language_name);
        
        // Prepara i dati per la richiesta
        $request_data = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a professional translator. Translate accurately while preserving meaning, tone, and formatting. If HTML tags are present, preserve them exactly.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.1,
            'max_tokens' => $this->calculate_max_tokens($content),
            'stream' => false
        );
        
        // Esegue la richiesta con retry
        $response = $this->make_api_request($request_data);
        
        if (is_wp_error($response)) {
            $this->update_stats(false, strlen($content));
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            $this->update_stats(false, strlen($content));
            return new WP_Error('openrouter_api_error', $data['error']['message']);
        }
        
        if (!isset($data['choices'][0]['message']['content'])) {
            $this->update_stats(false, strlen($content));
            return new WP_Error('no_translation', __('Nessuna traduzione ricevuta', 'dynamic-translator'));
        }
        
        $translation = trim($data['choices'][0]['message']['content']);
        $translation = $this->post_process_translation($translation, $content);
        
        $this->update_stats(true, strlen($content));
        
        return $translation;
    }
    
    /**
     * Crea prompt per traduzione
     */
    private function create_translation_prompt($content, $source_language, $target_language) {
        $preserve_html = dpt_get_option('preserve_html', true);
        
        $prompt = "Translate the following text from {$source_language} to {$target_language}.\n\n";
        
        if ($preserve_html) {
            $prompt .= "IMPORTANT: Preserve all HTML tags exactly as they are. Only translate the text content, not the HTML structure or attributes.\n\n";
        }
        
        $prompt .= "Rules:\n";
        $prompt .= "- Maintain the original meaning and tone\n";
        $prompt .= "- Keep the same formatting and structure\n";
        $prompt .= "- Do not add explanations or comments\n";
        $prompt .= "- Return only the translated text\n\n";
        
        $prompt .= "Text to translate:\n{$content}";
        
        return $prompt;
    }
    
    /**
     * Calcola max tokens per la risposta
     */
    private function calculate_max_tokens($content) {
        $content_length = strlen($content);
        $estimated_tokens = ceil($content_length / 4); // Stima approssimativa
        
        // Aggiungi margine del 50% per sicurezza
        $max_tokens = ceil($estimated_tokens * 1.5);
        
        // Limiti ragionevoli
        $max_tokens = max(50, min($max_tokens, 4000));
        
        return $max_tokens;
    }
    
    /**
     * Effettua richiesta API con retry
     */
    private function make_api_request($request_data, $retry_count = 0) {
        $max_retries = 2;
        
        $response = wp_remote_post($this->api_url, array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => get_site_url(),
                'X-Title' => get_bloginfo('name') . ' - Dynamic Translator'
            ),
            'body' => json_encode($request_data)
        ));
        
        // Se errore di connessione e abbiamo retry disponibili
        if (is_wp_error($response) && $retry_count < $max_retries) {
            sleep(1); // Attende 1 secondo prima del retry
            return $this->make_api_request($request_data, $retry_count + 1);
        }
        
        return $response;
    }
    
    /**
     * Post-processa la traduzione
     */
    private function post_process_translation($translation, $original) {
        // Rimuove eventuali markup aggiuntivi aggiunti dall'AI
        $translation = trim($translation);
        
        // Se la traduzione inizia e finisce con virgolette, rimuovile
        if (preg_match('/^["\'](.+)["\']$/', $translation, $matches)) {
            $translation = $matches[1];
        }
        
        // Se l'originale era maiuscolo e la traduzione no, correggi
        if (ctype_upper($original) && !ctype_upper($translation)) {
            $translation = strtoupper($translation);
        }
        
        // Se l'originale iniziava con maiuscola, assicurati che anche la traduzione lo faccia
        if (ctype_upper(substr($original, 0, 1)) && ctype_lower(substr($translation, 0, 1))) {
            $translation = ucfirst($translation);
        }
        
        return $translation;
    }
    
    /**
     * Ottiene nome lingua
     */
    private function get_language_name($lang_code) {
        if ($lang_code === 'auto') {
            return 'the source language';
        }
        
        return isset($this->supported_languages[$lang_code]) 
            ? $this->supported_languages[$lang_code] 
            : $lang_code;
    }
    
    /**
     * Rileva lingua del contenuto
     */
    public function detect_language($content) {
        if (empty($this->api_key) || empty($content)) {
            return 'en'; // Default fallback
        }
        
        $request_data = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a language detector. Respond only with the ISO 639-1 language code (2 letters) of the given text.'
                ),
                array(
                    'role' => 'user',
                    'content' => "Detect the language of this text and respond only with the 2-letter ISO code:\n\n" . substr($content, 0, 500)
                )
            ),
            'temperature' => 0,
            'max_tokens' => 5
        );
        
        $response = wp_remote_post($this->api_url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => get_site_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode($request_data)
        ));
        
        if (is_wp_error($response)) {
            return 'en'; // Fallback
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            $detected = trim(strtolower($data['choices'][0]['message']['content']));
            
            // Valida che sia un codice lingua valido
            if (strlen($detected) === 2 && isset($this->supported_languages[$detected])) {
                return $detected;
            }
        }
        
        return 'en'; // Fallback
    }
    
    /**
     * Ottiene statistiche del modulo
     */
    public function get_module_stats() {
        $stats = get_option('dpt_openrouter_stats', array(
            'total_requests' => 0,
            'total_characters' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'total_tokens_used' => 0,
            'estimated_cost' => 0,
            'last_request' => null,
            'current_model' => $this->model
        ));
        
        return $stats;
    }
    
    /**
     * Aggiorna statistiche
     */
    public function update_stats($request_successful, $character_count = 0, $tokens_used = 0) {
        $stats = $this->get_module_stats();
        
        $stats['total_requests']++;
        $stats['total_characters'] += $character_count;
        $stats['last_request'] = current_time('mysql');
        $stats['current_model'] = $this->model;
        
        if ($request_successful) {
            $stats['successful_requests']++;
            $stats['total_tokens_used'] += $tokens_used;
            
            // Stima costo approssimativo (varia per modello)
            $cost_per_token = $this->get_model_cost_per_token();
            $stats['estimated_cost'] += ($tokens_used * $cost_per_token);
        } else {
            $stats['failed_requests']++;
        }
        
        update_option('dpt_openrouter_stats', $stats);
    }
    
    /**
     * Ottiene costo per token del modello corrente
     */
    private function get_model_cost_per_token() {
        $costs = array(
            'meta-llama/llama-3.1-8b-instruct:free' => 0, // Gratuito
            'meta-llama/llama-3.1-70b-instruct' => 0.00000059, // $0.59/1M token
            'anthropic/claude-3-haiku' => 0.0000008, // $0.80/1M token
            'openai/gpt-4o-mini' => 0.0000006, // $0.60/1M token
            'google/gemini-flash-1.5' => 0.0000004 // $0.40/1M token
        );
        
        return isset($costs[$this->model]) ? $costs[$this->model] : 0.0000005; // Default
    }
    
    /**
     * Reset statistiche
     */
    public function reset_stats() {
        delete_option('dpt_openrouter_stats');
    }
    
    /**
     * Ottiene informazioni utilizzo
     */
    public function get_usage_info() {
        $stats = $this->get_module_stats();
        
        return array(
            'provider' => 'OpenRouter AI',
            'model' => $this->model,
            'total_requests' => $stats['total_requests'],
            'successful_requests' => $stats['successful_requests'],
            'failed_requests' => $stats['failed_requests'],
            'total_characters' => $stats['total_characters'],
            'total_tokens' => $stats['total_tokens_used'],
            'estimated_cost' => number_format($stats['estimated_cost'], 4),
            'last_request' => $stats['last_request']
        );
    }
}

// CORREZIONE: Inizializza sempre il provider
new DPT_OpenRouter_Translate_Provider();