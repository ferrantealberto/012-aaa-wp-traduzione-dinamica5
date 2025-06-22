<?php
/**
 * Gestore Modelli OpenRouter Espanso per Dynamic Page Translator
 * File: includes/class-openrouter-models-manager.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_OpenRouter_Models_Manager {
    
    private $models_list = array();
    private $cached_models = array();
    
    public function __construct() {
        $this->init_models_list();
        $this->init_hooks();
    }
    
    /**
     * Inizializza hook
     */
    private function init_hooks() {
        add_action('wp_ajax_dpt_search_openrouter_models', array($this, 'ajax_search_models'));
        add_action('wp_ajax_dpt_get_model_details', array($this, 'ajax_get_model_details'));
        add_action('wp_ajax_dpt_test_model_translation', array($this, 'ajax_test_model_translation'));
        add_action('wp_ajax_dpt_refresh_models_list', array($this, 'ajax_refresh_models_list'));
        
        // Enqueue assets per admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_models_assets'));
    }
    
    /**
     * Inizializza lista completa modelli
     */
    private function init_models_list() {
        $this->models_list = array(
            // === MODELLI GRATUITI ===
            'meta-llama/llama-3.1-8b-instruct:free' => array(
                'name' => 'Llama 3.1 8B Instruct (Free)',
                'provider' => 'Meta',
                'cost' => 0,
                'category' => 'free',
                'speed' => 'fast',
                'quality' => 'good',
                'context_length' => 131072,
                'description' => 'Modello gratuito veloce, buona qualità per traduzioni base',
                'best_for' => 'Traduzioni veloci, contenuti brevi, test',
                'languages_strong' => 'EN, ES, FR, DE, IT, PT',
                'translation_quality' => 7,
                'speed_rating' => 9,
                'free' => true,
                'translation_optimized' => true
            ),
            
            'google/gemma-2-9b-it:free' => array(
                'name' => 'Gemma 2 9B IT (Free)',
                'provider' => 'Google',
                'cost' => 0,
                'category' => 'free',
                'speed' => 'fast',
                'quality' => 'good',
                'context_length' => 8192,
                'description' => 'Modello Google gratuito ottimizzato per instruction following',
                'best_for' => 'Traduzioni precise, linguaggio formale',
                'languages_strong' => 'EN, ES, FR, DE, IT, JA, KO',
                'translation_quality' => 7.5,
                'speed_rating' => 8,
                'free' => true,
                'translation_optimized' => true
            ),
            
            'microsoft/wizardlm-2-8x22b:free' => array(
                'name' => 'WizardLM 2 8x22B (Free)',
                'provider' => 'Microsoft',
                'cost' => 0,
                'category' => 'free',
                'speed' => 'medium',
                'quality' => 'excellent',
                'context_length' => 65536,
                'description' => 'Modello gratuito di alta qualità, ottimo per traduzioni complesse',
                'best_for' => 'Traduzioni tecniche, contenuti lunghi',
                'languages_strong' => 'EN, ZH, ES, FR, DE, JA, KO, RU',
                'translation_quality' => 8.5,
                'speed_rating' => 6,
                'free' => true,
                'translation_optimized' => true
            ),
            
            'mistralai/mistral-7b-instruct:free' => array(
                'name' => 'Mistral 7B Instruct (Free)',
                'provider' => 'Mistral AI',
                'cost' => 0,
                'category' => 'free',
                'speed' => 'fast',
                'quality' => 'good',
                'context_length' => 32768,
                'description' => 'Modello francese gratuito, eccellente per lingue europee',
                'best_for' => 'Lingue europee, contenuti creativi',
                'languages_strong' => 'FR, EN, ES, DE, IT, PT',
                'translation_quality' => 7.5,
                'speed_rating' => 8,
                'free' => true,
                'translation_optimized' => true
            ),
            
            // === MODELLI ECONOMICI (Low-Cost) ===
            'anthropic/claude-3-haiku' => array(
                'name' => 'Claude 3 Haiku',
                'provider' => 'Anthropic',
                'cost' => 0.80,
                'category' => 'low-cost',
                'speed' => 'fast',
                'quality' => 'excellent',
                'context_length' => 200000,
                'description' => 'Veloce e preciso, ideale per traduzioni live',
                'best_for' => 'Traduzioni real-time, alta precisione',
                'languages_strong' => 'EN, ES, FR, DE, IT, PT, JA, ZH',
                'translation_quality' => 9,
                'speed_rating' => 9,
                'free' => false,
                'translation_optimized' => true
            ),
            
            'google/gemini-flash-1.5' => array(
                'name' => 'Gemini Flash 1.5',
                'provider' => 'Google',
                'cost' => 0.40,
                'category' => 'low-cost',
                'speed' => 'very-fast',
                'quality' => 'excellent',
                'context_length' => 1000000,
                'description' => 'Velocissimo e economico, contesto lungo',
                'best_for' => 'Documenti lunghi, traduzioni veloci',
                'languages_strong' => 'EN, ZH, JA, KO, HI, ES, FR, DE, PT, IT',
                'translation_quality' => 8.5,
                'speed_rating' => 10,
                'free' => false,
                'translation_optimized' => true
            ),
            
            // === MODELLI PREMIUM ===
            'anthropic/claude-3-sonnet' => array(
                'name' => 'Claude 3 Sonnet',
                'provider' => 'Anthropic',
                'cost' => 15.00,
                'category' => 'premium',
                'speed' => 'medium',
                'quality' => 'exceptional',
                'context_length' => 200000,
                'description' => 'Qualità eccezionale per traduzioni critiche',
                'best_for' => 'Traduzioni legali, mediche, tecniche',
                'languages_strong' => 'Tutte le lingue con alta precisione',
                'translation_quality' => 10,
                'speed_rating' => 6,
                'free' => false,
                'translation_optimized' => true
            ),
            
            'openai/gpt-4o' => array(
                'name' => 'GPT-4o',
                'provider' => 'OpenAI',
                'cost' => 30.00,
                'category' => 'premium',
                'speed' => 'medium',
                'quality' => 'exceptional',
                'context_length' => 128000,
                'description' => 'GPT-4 ottimizzato, eccellente per tutte le lingue',
                'best_for' => 'Traduzioni professionali, contenuti complessi',
                'languages_strong' => 'Tutte le lingue principali',
                'translation_quality' => 10,
                'speed_rating' => 6,
                'free' => false,
                'translation_optimized' => true
            )
        );
    }
    
    /**
     * Enqueue assets per gestione modelli
     */
    public function enqueue_models_assets($hook) {
        if (strpos($hook, 'dynamic-translator') === false) {
            return;
        }
        
        wp_enqueue_script(
            'dpt-models-manager',
            DPT_PLUGIN_URL . 'assets/js/models-manager.js',
            array('jquery', 'jquery-ui-autocomplete', 'jquery-ui-slider'),
            DPT_VERSION,
            true
        );
        
        wp_enqueue_style(
            'dpt-models-manager',
            DPT_PLUGIN_URL . 'assets/css/models-manager.css',
            array(),
            DPT_VERSION
        );
        
        wp_localize_script('dpt-models-manager', 'dptModels', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dpt_models_nonce'),
            'strings' => array(
                'searching' => __('Cercando modelli...', 'dynamic-translator'),
                'noResults' => __('Nessun modello trovato', 'dynamic-translator'),
                'testModel' => __('Test Modello', 'dynamic-translator'),
                'testing' => __('Test in corso...', 'dynamic-translator'),
                'testSuccess' => __('Test riuscito!', 'dynamic-translator'),
                'testFailed' => __('Test fallito:', 'dynamic-translator')
            ),
            'providers' => $this->get_available_providers(),
            'categories' => $this->get_available_categories(),
            'languages' => $this->get_supported_languages()
        ));
    }
    
    /**
     * AJAX: Cerca modelli
     */
    public function ajax_search_models() {
        check_ajax_referer('dpt_models_nonce', 'nonce');
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? 'all');
        $provider = sanitize_text_field($_POST['provider'] ?? 'all');
        $min_quality = intval($_POST['min_quality'] ?? 0);
        $max_cost = floatval($_POST['max_cost'] ?? 999);
        $speed = sanitize_text_field($_POST['speed'] ?? 'all');
        $free_only = isset($_POST['free_only']) && $_POST['free_only'] === 'true';
        
        $filtered_models = $this->filter_models($search, $category, $provider, $min_quality, $max_cost, $speed, $free_only);
        
        // Ottieni statistiche sui modelli filtrati
        $stats = $this->get_models_stats($filtered_models);
        
        wp_send_json_success(array(
            'models' => $filtered_models,
            'total' => count($filtered_models),
            'stats' => $stats
        ));
    }
    
    /**
     * Filtra modelli in base ai criteri
     */
    private function filter_models($search, $category, $provider, $min_quality, $max_cost, $speed, $free_only) {
        $filtered = array();
        
        foreach ($this->models_list as $model_id => $model) {
            // Filtro free only
            if ($free_only && !$model['free']) {
                continue;
            }
            
            // Filtro categoria
            if ($category !== 'all' && $model['category'] !== $category) {
                continue;
            }
            
            // Filtro provider
            if ($provider !== 'all' && strtolower($model['provider']) !== strtolower($provider)) {
                continue;
            }
            
            // Filtro qualità minima
            if ($model['translation_quality'] < $min_quality) {
                continue;
            }
            
            // Filtro costo massimo
            if ($model['cost'] > $max_cost) {
                continue;
            }
            
            // Filtro velocità
            if ($speed !== 'all' && $model['speed'] !== $speed) {
                continue;
            }
            
            // Filtro ricerca testuale
            if (!empty($search)) {
                $searchable = strtolower($model['name'] . ' ' . $model['description'] . ' ' . $model['best_for'] . ' ' . $model['provider']);
                if (strpos($searchable, strtolower($search)) === false) {
                    continue;
                }
            }
            
            $model['id'] = $model_id;
            $filtered[$model_id] = $model;
        }
        
        return $filtered;
    }
    
    /**
     * AJAX: Ottieni dettagli modello
     */
    public function ajax_get_model_details() {
        check_ajax_referer('dpt_models_nonce', 'nonce');
        
        $model_id = sanitize_text_field($_POST['model_id']);
        
        if (!isset($this->models_list[$model_id])) {
            wp_send_json_error('Modello non trovato');
        }
        
        $model = $this->models_list[$model_id];
        $model['id'] = $model_id;
        
        wp_send_json_success($model);
    }
    
    /**
     * AJAX: Test traduzione modello
     */
    public function ajax_test_model_translation() {
        check_ajax_referer('dpt_models_nonce', 'nonce');
        
        $model_id = sanitize_text_field($_POST['model_id']);
        $test_text = sanitize_text_field($_POST['test_text'] ?? 'Hello world');
        $target_lang = sanitize_text_field($_POST['target_lang'] ?? 'it');
        
        if (!isset($this->models_list[$model_id])) {
            wp_send_json_error('Modello non trovato');
        }
        
        // Backup modello corrente
        $current_model = dpt_get_option('openrouter_model');
        
        // Cambia temporaneamente modello
        dpt_update_option('openrouter_model', $model_id);
        
        $start_time = microtime(true);
        
        // Esegui test traduzione
        $plugin = DynamicPageTranslator::get_instance();
        $api_handler = $plugin->get_api_handler();
        $translation = $api_handler->translate($test_text, 'en', $target_lang);
        
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2);
        
        // Ripristina modello originale
        dpt_update_option('openrouter_model', $current_model);
        
        if (is_wp_error($translation)) {
            wp_send_json_error(array(
                'message' => $translation->get_error_message(),
                'duration' => $duration
            ));
        }
        
        wp_send_json_success(array(
            'original' => $test_text,
            'translation' => $translation,
            'duration' => $duration,
            'model' => $this->models_list[$model_id]['name']
        ));
    }
    
    /**
     * AJAX: Aggiorna lista modelli da API
     */
    public function ajax_refresh_models_list() {
        check_ajax_referer('dpt_models_nonce', 'nonce');
        
        $api_key = dpt_get_option('openrouter_api_key');
        
        if (empty($api_key)) {
            wp_send_json_error('API key non configurata');
        }
        
        $response = wp_remote_get('https://openrouter.ai/api/v1/models', array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['data'])) {
            wp_send_json_error('Formato risposta API non valido');
        }
        
        // Aggiorna cache modelli
        update_option('dpt_openrouter_models_cache', $data['data']);
        update_option('dpt_openrouter_models_cache_time', time());
        
        wp_send_json_success(array(
            'models' => $data['data'],
            'count' => count($data['data']),
            'updated' => date('Y-m-d H:i:s')
        ));
    }
    
    /**
     * Ottieni statistiche sui modelli filtrati
     */
    private function get_models_stats($models) {
        $stats = array(
            'free_count' => 0,
            'paid_count' => 0,
            'avg_quality' => 0,
            'avg_speed' => 0,
            'avg_cost' => 0,
            'fastest_model' => '',
            'highest_quality_model' => '',
            'translation_optimized_count' => 0
        );
        
        if (empty($models)) {
            return $stats;
        }
        
        $total_quality = 0;
        $total_speed = 0;
        $total_cost = 0;
        $max_speed = 0;
        $max_quality = 0;
        
        foreach ($models as $model_id => $model) {
            // Conteggi
            if ($model['free']) {
                $stats['free_count']++;
            } else {
                $stats['paid_count']++;
                $total_cost += $model['cost'];
            }
            
            if (isset($model['translation_optimized']) && $model['translation_optimized']) {
                $stats['translation_optimized_count']++;
            }
            
            // Medie
            $total_quality += $model['translation_quality'];
            $total_speed += $model['speed_rating'];
            
            // Modello più veloce
            if ($model['speed_rating'] > $max_speed) {
                $max_speed = $model['speed_rating'];
                $stats['fastest_model'] = $model['name'];
            }
            
            // Modello con qualità più alta
            if ($model['translation_quality'] > $max_quality) {
                $max_quality = $model['translation_quality'];
                $stats['highest_quality_model'] = $model['name'];
            }
        }
        
        $count = count($models);
        $stats['avg_quality'] = round($total_quality / $count, 1);
        $stats['avg_speed'] = round($total_speed / $count, 1);
        
        if ($stats['paid_count'] > 0) {
            $stats['avg_cost'] = round($total_cost / $stats['paid_count'], 2);
        }
        
        return $stats;
    }
    
    /**
     * Ottiene provider disponibili
     */
    private function get_available_providers() {
        $providers = array();
        
        foreach ($this->models_list as $model) {
            $provider = $model['provider'];
            if (!in_array($provider, $providers)) {
                $providers[] = $provider;
            }
        }
        
        sort($providers);
        return $providers;
    }
    
    /**
     * Ottiene categorie disponibili
     */
    private function get_available_categories() {
        $categories = array();
        
        foreach ($this->models_list as $model) {
            $category = $model['category'];
            if (!in_array($category, $categories)) {
                $categories[] = $category;
            }
        }
        
        sort($categories);
        return $categories;
    }
    
    /**
     * Ottiene lingue supportate
     */
    private function get_supported_languages() {
        return array(
            'EN' => 'English',
            'IT' => 'Italiano',
            'ES' => 'Español',
            'FR' => 'Français',
            'DE' => 'Deutsch',
            'PT' => 'Português',
            'RU' => 'Русский',
            'ZH' => '中文',
            'JA' => '日本語',
            'KO' => '한국어',
            'AR' => 'العربية',
            'HI' => 'हिन्दी'
        );
    }
    
    /**
     * Ottieni modelli gratuiti
     */
    public function get_free_models() {
        $filtered = array();
        foreach ($this->models_list as $model_id => $model) {
            if ($model['free']) {
                $model['id'] = $model_id;
                $filtered[$model_id] = $model;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Ottieni modelli ottimizzati per traduzione
     */
    public function get_translation_optimized_models() {
        $filtered = array();
        foreach ($this->models_list as $model_id => $model) {
            if (isset($model['translation_optimized']) && $model['translation_optimized']) {
                $model['id'] = $model_id;
                $filtered[$model_id] = $model;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Ottieni modelli per velocità
     */
    public function get_models_by_speed($speed = 'fast') {
        $filtered = array();
        foreach ($this->models_list as $model_id => $model) {
            if ($model['speed'] === $speed) {
                $model['id'] = $model_id;
                $filtered[$model_id] = $model;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Ottieni modelli raccomandati per caso d'uso
     */
    public function get_recommended_models($use_case = 'general') {
        $recommended = array();
        
        switch ($use_case) {
            case 'live_translation':
                $recommended = array(
                    'google/gemini-flash-1.5',
                    'anthropic/claude-3-haiku',
                    'meta-llama/llama-3.1-8b-instruct:free'
                );
                break;
                
            case 'high_quality':
                $recommended = array(
                    'anthropic/claude-3-sonnet',
                    'openai/gpt-4o'
                );
                break;
                
            case 'free':
                $recommended = array(
                    'microsoft/wizardlm-2-8x22b:free',
                    'meta-llama/llama-3.1-8b-instruct:free',
                    'mistralai/mistral-7b-instruct:free'
                );
                break;
                
            default: // general
                $recommended = array(
                    'anthropic/claude-3-haiku',
                    'google/gemini-flash-1.5',
                    'meta-llama/llama-3.1-8b-instruct:free'
                );
        }
        
        $result = array();
        foreach ($recommended as $model_id) {
            if (isset($this->models_list[$model_id])) {
                $model = $this->models_list[$model_id];
                $model['id'] = $model_id;
                $result[$model_id] = $model;
            }
        }
        
        return $result;
    }
}

// Inizializza manager modelli
new DPT_OpenRouter_Models_Manager();
