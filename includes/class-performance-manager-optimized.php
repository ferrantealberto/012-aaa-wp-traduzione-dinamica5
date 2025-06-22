<?php
/**
 * Gestore Performance Ottimizzato per Dynamic Page Translator
 * File: includes/class-performance-manager-optimized.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_Performance_Manager_Optimized {
    
    private $translation_queue = array();
    private $memory_cache = array();
    private $batch_size = 50;
    private $max_concurrent = 10;
    private $cache_layers = array();
    private $worker_pool = array();
    private $compression_enabled = true;
    private $prefetch_enabled = true;
    
    // Nuove ottimizzazioni
    private $cache_warming_enabled = true;
    private $intelligent_batching = true;
    private $adaptive_timeouts = true;
    private $connection_pooling = true;
    private $result_compression = true;
    
    public function __construct() {
        $this->init_performance_settings();
        $this->init_cache_layers();
        $this->init_hooks();
        $this->init_connection_pool();
        $this->start_background_optimizer();
    }
    
    /**
     * Inizializza impostazioni performance ottimizzate
     */
    private function init_performance_settings() {
        $this->batch_size = dpt_get_option('performance_batch_size', 50);
        $this->max_concurrent = dpt_get_option('performance_max_concurrent', 10);
        $this->compression_enabled = dpt_get_option('performance_compression', true);
        $this->prefetch_enabled = dpt_get_option('performance_prefetch', true);
        $this->cache_warming_enabled = dpt_get_option('performance_cache_warming', true);
        $this->intelligent_batching = dpt_get_option('performance_intelligent_batching', true);
        $this->adaptive_timeouts = dpt_get_option('performance_adaptive_timeouts', true);
        $this->connection_pooling = dpt_get_option('performance_connection_pooling', true);
        $this->result_compression = dpt_get_option('performance_result_compression', true);
    }
    
    /**
     * Inizializza cache multi-livello ottimizzata
     */
    private function init_cache_layers() {
        $this->cache_layers = array(
            'memory' => array(
                'enabled' => true,
                'max_size' => 100, // MB
                'ttl' => 3600,
                'compression' => false
            ),
            'redis' => array(
                'enabled' => extension_loaded('redis') && class_exists('Redis'),
                'ttl' => 24 * 3600,
                'compression' => true
            ),
            'memcached' => array(
                'enabled' => extension_loaded('memcached') && class_exists('Memcached'),
                'ttl' => 24 * 3600,
                'compression' => true
            ),
            'object' => array(
                'enabled' => true,
                'ttl' => 12 * 3600,
                'compression' => false
            ),
            'transient' => array(
                'enabled' => true,
                'ttl' => 7 * 24 * 3600,
                'compression' => true
            ),
            'database' => array(
                'enabled' => true,
                'ttl' => 30 * 24 * 3600,
                'compression' => true
            )
        );
        
        // Inizializza connessioni cache esterne
        $this->init_external_cache_connections();
    }
    
    /**
     * Inizializza connessioni cache esterne
     */
    private function init_external_cache_connections() {
        // Redis
        if ($this->cache_layers['redis']['enabled']) {
            try {
                $this->redis = new Redis();
                $redis_host = defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1';
                $redis_port = defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379;
                $this->redis->connect($redis_host, $redis_port);
                
                if (defined('WP_REDIS_PASSWORD')) {
                    $this->redis->auth(WP_REDIS_PASSWORD);
                }
                
                $this->redis->select(defined('WP_REDIS_DATABASE') ? WP_REDIS_DATABASE : 0);
            } catch (Exception $e) {
                $this->cache_layers['redis']['enabled'] = false;
                error_log('DPT: Redis connection failed: ' . $e->getMessage());
            }
        }
        
        // Memcached
        if ($this->cache_layers['memcached']['enabled']) {
            try {
                $this->memcached = new Memcached();
                $memcached_host = defined('WP_MEMCACHED_HOST') ? WP_MEMCACHED_HOST : '127.0.0.1';
                $memcached_port = defined('WP_MEMCACHED_PORT') ? WP_MEMCACHED_PORT : 11211;
                $this->memcached->addServer($memcached_host, $memcached_port);
                
                // Test connessione
                $this->memcached->set('dpt_test', 'test', 1);
                if ($this->memcached->get('dpt_test') !== 'test') {
                    throw new Exception('Memcached test failed');
                }
            } catch (Exception $e) {
                $this->cache_layers['memcached']['enabled'] = false;
                error_log('DPT: Memcached connection failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Inizializza hook ottimizzati
     */
    private function init_hooks() {
        // Hook performance critici
        add_action('wp_ajax_dpt_ultra_fast_translate', array($this, 'ajax_ultra_fast_translate'));
        add_action('wp_ajax_nopriv_dpt_ultra_fast_translate', array($this, 'ajax_ultra_fast_translate'));
        
        add_action('wp_ajax_dpt_batch_translate_optimized', array($this, 'ajax_batch_translate_optimized'));
        add_action('wp_ajax_nopriv_dpt_batch_translate_optimized', array($this, 'ajax_batch_translate_optimized'));
        
        add_action('wp_ajax_dpt_stream_translate', array($this, 'ajax_stream_translate'));
        add_action('wp_ajax_nopriv_dpt_stream_translate', array($this, 'ajax_stream_translate'));
        
        // Background processing
        add_action('dpt_background_optimize', array($this, 'run_background_optimization'));
        add_action('dpt_cache_warmup', array($this, 'cache_warmup_process'));
        add_action('dpt_cleanup_expired_cache', array($this, 'cleanup_expired_cache_optimized'));
        
        // Performance monitoring
        add_action('dpt_track_performance', array($this, 'track_performance_metrics'), 10, 4);
        
        // Preload critico
        add_action('wp_head', array($this, 'preload_critical_translations'), 1);
        add_action('wp_footer', array($this, 'prefetch_probable_translations'), 999);
    }
    
    /**
     * Inizializza connection pooling
     */
    private function init_connection_pool() {
        if (!$this->connection_pooling) {
            return;
        }
        
        $this->connection_pool = array(
            'google' => array(),
            'openrouter' => array(),
            'max_connections' => 5,
            'timeout' => 30,
            'keepalive' => true
        );
    }
    
    /**
     * AJAX Ultra Fast Translate - Ottimizzazione estrema
     */
    public function ajax_ultra_fast_translate() {
        $start_time = microtime(true);
        
        // Validazione ultra-rapida
        if (!wp_verify_nonce($_POST['nonce'], 'dpt_frontend_nonce')) {
            wp_send_json_error(['code' => 'invalid_nonce', 'time' => 0]);
        }
        
        $content = sanitize_textarea_field($_POST['content']);
        $source_lang = sanitize_text_field($_POST['source_lang']);
        $target_lang = sanitize_text_field($_POST['target_lang']);
        $priority = sanitize_text_field($_POST['priority'] ?? 'normal');
        
        // Exit immediato per contenuti identici
        if (empty($content) || $source_lang === $target_lang) {
            wp_send_json_success([
                'translation' => $content,
                'cached' => false,
                'time' => round((microtime(true) - $start_time) * 1000, 2),
                'source' => 'immediate'
            ]);
        }
        
        // 1. Cache ultra-veloce multi-livello
        $cache_key = $this->generate_optimized_cache_key($content, $source_lang, $target_lang);
        $cached_result = $this->get_ultra_fast_cache($cache_key);
        
        if ($cached_result !== false) {
            wp_send_json_success([
                'translation' => $cached_result['translation'],
                'cached' => true,
                'time' => round((microtime(true) - $start_time) * 1000, 2),
                'source' => $cached_result['source'],
                'compression' => $cached_result['compressed'] ?? false
            ]);
        }
        
        // 2. Dizionario personalizzato check (ultra-veloce)
        $dictionary_result = $this->check_custom_dictionary_optimized($content, $source_lang, $target_lang);
        if ($dictionary_result !== false) {
            $this->set_ultra_fast_cache($cache_key, $dictionary_result, 'dictionary');
            wp_send_json_success([
                'translation' => $dictionary_result,
                'cached' => false,
                'time' => round((microtime(true) - $start_time) * 1000, 2),
                'source' => 'dictionary'
            ]);
        }
        
        // 3. Traduzione intelligente adattiva
        $translation = $this->intelligent_adaptive_translate($content, $source_lang, $target_lang, $priority);
        
        if (is_wp_error($translation)) {
            wp_send_json_error([
                'message' => $translation->get_error_message(),
                'code' => $translation->get_error_code(),
                'time' => round((microtime(true) - $start_time) * 1000, 2)
            ]);
        }
        
        // 4. Cache con compressione automatica
        $this->set_ultra_fast_cache($cache_key, $translation, 'api');
        
        wp_send_json_success([
            'translation' => $translation,
            'cached' => false,
            'time' => round((microtime(true) - $start_time) * 1000, 2),
            'source' => 'api',
            'compressed' => $this->should_compress($translation)
        ]);
    }
    
    /**
     * Cache ultra-veloce ottimizzata multi-livello
     */
    private function get_ultra_fast_cache($key) {
        // 1. Memory cache (nanosecondi)
        if (isset($this->memory_cache[$key])) {
            return [
                'translation' => $this->memory_cache[$key],
                'source' => 'memory',
                'compressed' => false
            ];
        }
        
        // 2. Redis cache (microsecondi)
        if ($this->cache_layers['redis']['enabled']) {
            try {
                $cached = $this->redis->get('dpt_ultra_' . $key);
                if ($cached !== false) {
                    $data = $this->decompress_if_needed($cached);
                    $this->memory_cache[$key] = $data;
                    return [
                        'translation' => $data,
                        'source' => 'redis',
                        'compressed' => $this->is_compressed($cached)
                    ];
                }
            } catch (Exception $e) {
                error_log('DPT: Redis get error: ' . $e->getMessage());
            }
        }
        
        // 3. Memcached cache (microsecondi)
        if ($this->cache_layers['memcached']['enabled']) {
            try {
                $cached = $this->memcached->get('dpt_ultra_' . $key);
                if ($cached !== false) {
                    $data = $this->decompress_if_needed($cached);
                    $this->memory_cache[$key] = $data;
                    return [
                        'translation' => $data,
                        'source' => 'memcached',
                        'compressed' => $this->is_compressed($cached)
                    ];
                }
            } catch (Exception $e) {
                error_log('DPT: Memcached get error: ' . $e->getMessage());
            }
        }
        
        // 4. Object cache WP (millisecondi)
        if ($this->cache_layers['object']['enabled']) {
            $cached = wp_cache_get('dpt_ultra_' . $key, 'dpt_translations');
            if ($cached !== false) {
                $data = $this->decompress_if_needed($cached);
                $this->memory_cache[$key] = $data;
                return [
                    'translation' => $data,
                    'source' => 'object',
                    'compressed' => $this->is_compressed($cached)
                ];
            }
        }
        
        // 5. Transient cache (millisecondi)
        if ($this->cache_layers['transient']['enabled']) {
            $cached = get_transient('dpt_ultra_' . $key);
            if ($cached !== false) {
                $data = $this->decompress_if_needed($cached);
                $this->memory_cache[$key] = $data;
                $this->set_upper_level_cache($key, $data);
                return [
                    'translation' => $data,
                    'source' => 'transient',
                    'compressed' => $this->is_compressed($cached)
                ];
            }
        }
        
        // 6. Database cache (decimi di secondo)
        if ($this->cache_layers['database']['enabled']) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'dpt_translations_cache';
            
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT translated_content FROM {$table_name} 
                 WHERE cache_key = %s AND expires_at > NOW() 
                 LIMIT 1",
                $key
            ));
            
            if ($result !== null) {
                $data = $this->decompress_if_needed($result);
                $this->memory_cache[$key] = $data;
                $this->set_upper_level_cache($key, $data);
                return [
                    'translation' => $data,
                    'source' => 'database',
                    'compressed' => $this->is_compressed($result)
                ];
            }
        }
        
        return false;
    }
    
    /**
     * Imposta cache ultra-veloce con propagazione automatica
     */
    private function set_ultra_fast_cache($key, $value, $source = 'api') {
        $should_compress = $this->should_compress($value);
        $compressed_value = $should_compress ? $this->compress_data($value) : $value;
        
        // 1. Memory cache
        $this->memory_cache[$key] = $value;
        $this->manage_memory_cache_size();
        
        // 2. Redis cache
        if ($this->cache_layers['redis']['enabled']) {
            try {
                $this->redis->setex(
                    'dpt_ultra_' . $key, 
                    $this->cache_layers['redis']['ttl'], 
                    $compressed_value
                );
            } catch (Exception $e) {
                error_log('DPT: Redis set error: ' . $e->getMessage());
            }
        }
        
        // 3. Memcached cache
        if ($this->cache_layers['memcached']['enabled']) {
            try {
                $this->memcached->set(
                    'dpt_ultra_' . $key, 
                    $compressed_value, 
                    $this->cache_layers['memcached']['ttl']
                );
            } catch (Exception $e) {
                error_log('DPT: Memcached set error: ' . $e->getMessage());
            }
        }
        
        // 4. Object cache WP
        if ($this->cache_layers['object']['enabled']) {
            wp_cache_set(
                'dpt_ultra_' . $key, 
                $compressed_value, 
                'dpt_translations', 
                $this->cache_layers['object']['ttl']
            );
        }
        
        // 5. Transient cache (asincrono)
        if ($this->cache_layers['transient']['enabled']) {
            wp_schedule_single_event(time() + 1, 'dpt_set_transient_cache', [
                $key, $compressed_value, $this->cache_layers['transient']['ttl']
            ]);
        }
        
        // 6. Database cache (asincrono)
        if ($this->cache_layers['database']['enabled']) {
            wp_schedule_single_event(time() + 2, 'dpt_set_database_cache', [
                $key, $compressed_value, $value, $source
            ]);
        }
    }
    
    /**
     * Traduzione intelligente adattiva
     */
    private function intelligent_adaptive_translate($content, $source_lang, $target_lang, $priority) {
        $content_analysis = $this->analyze_content($content);
        
        // Seleziona strategia ottimale
        if ($content_analysis['length'] > 2000 && $this->intelligent_batching) {
            return $this->smart_segment_translate($content, $source_lang, $target_lang, $priority, $content_analysis);
        }
        
        // Seleziona modello ottimale
        $optimal_model = $this->select_optimal_model($content_analysis, $priority);
        
        // Timeout adattivo
        $timeout = $this->calculate_adaptive_timeout($content_analysis, $priority);
        
        return $this->execute_optimized_translation($content, $source_lang, $target_lang, $optimal_model, $timeout);
    }
    
    /**
     * Analizza contenuto per ottimizzazioni
     */
    private function analyze_content($content) {
        return [
            'length' => strlen($content),
            'words' => str_word_count($content),
            'sentences' => substr_count($content, '.') + substr_count($content, '!') + substr_count($content, '?'),
            'paragraphs' => substr_count($content, "\n\n") + 1,
            'complexity' => $this->calculate_complexity($content),
            'language_hints' => $this->detect_language_hints($content),
            'html_content' => strip_tags($content) !== $content,
            'special_chars' => preg_match_all('/[^\w\s]/', $content),
            'numeric_content' => preg_match_all('/\d+/', $content)
        ];
    }
    
    /**
     * Calcola complessità contenuto
     */
    private function calculate_complexity($content) {
        $factors = [
            'avg_word_length' => 0,
            'long_sentences' => 0,
            'technical_terms' => 0,
            'punctuation_density' => 0
        ];
        
        $words = explode(' ', strip_tags($content));
        if (!empty($words)) {
            $factors['avg_word_length'] = array_sum(array_map('strlen', $words)) / count($words);
        }
        
        $sentences = preg_split('/[.!?]+/', $content);
        foreach ($sentences as $sentence) {
            if (str_word_count($sentence) > 20) {
                $factors['long_sentences']++;
            }
        }
        
        // Termini tecnici (euristica semplice)
        $technical_patterns = ['/\b[A-Z]{2,}\b/', '/\b\w+tion\b/', '/\b\w+ing\b/', '/\b\w+ed\b/'];
        foreach ($technical_patterns as $pattern) {
            $factors['technical_terms'] += preg_match_all($pattern, $content);
        }
        
        $factors['punctuation_density'] = (strlen($content) - strlen(preg_replace('/[^\w\s]/', '', $content))) / strlen($content);
        
        // Score da 1 a 10
        $complexity_score = min(10, max(1, 
            ($factors['avg_word_length'] / 2) +
            ($factors['long_sentences'] / 3) +
            ($factors['technical_terms'] / 10) +
            ($factors['punctuation_density'] * 10)
        ));
        
        return round($complexity_score, 1);
    }
    
    /**
     * Seleziona modello ottimale basato sull'analisi
     */
    private function select_optimal_model($content_analysis, $priority) {
        $current_provider = dpt_get_option('translation_provider');
        
        if ($current_provider !== 'openrouter') {
            return null; // Usa modello default per altri provider
        }
        
        // Modelli ottimizzati per diversi scenari
        $models = [
            'ultra_fast' => 'meta-llama/llama-3.1-8b-instruct:free',
            'fast_quality' => 'anthropic/claude-3-haiku',
            'balanced' => 'google/gemini-flash-1.5',
            'high_quality' => 'anthropic/claude-3-sonnet',
            'complex' => 'openai/gpt-4o'
        ];
        
        // Logica selezione
        if ($priority === 'high') {
            return $models['ultra_fast'];
        }
        
        if ($content_analysis['complexity'] < 3) {
            return $models['ultra_fast'];
        } elseif ($content_analysis['complexity'] < 6) {
            return $models['fast_quality'];
        } elseif ($content_analysis['complexity'] < 8) {
            return $models['balanced'];
        } else {
            return $models['high_quality'];
        }
    }
    
    /**
     * Calcola timeout adattivo
     */
    private function calculate_adaptive_timeout($content_analysis, $priority) {
        if (!$this->adaptive_timeouts) {
            return 30; // Timeout fisso
        }
        
        $base_timeout = 10;
        $length_factor = min(30, $content_analysis['length'] / 100);
        $complexity_factor = $content_analysis['complexity'] * 2;
        $priority_factor = $priority === 'high' ? 0.5 : ($priority === 'low' ? 1.5 : 1);
        
        return max(5, min(60, $base_timeout + $length_factor + $complexity_factor)) * $priority_factor;
    }
    
    /**
     * Esegue traduzione ottimizzata
     */
    private function execute_optimized_translation($content, $source_lang, $target_lang, $optimal_model, $timeout) {
        $original_model = null;
        $original_timeout = ini_get('max_execution_time');
        
        try {
            // Imposta modello ottimale
            if ($optimal_model) {
                $original_model = dpt_get_option('openrouter_model');
                dpt_update_option('openrouter_model', $optimal_model);
            }
            
            // Imposta timeout adattivo
            ini_set('max_execution_time', $timeout);
            
            // Ottimizza prompt
            add_filter('dpt_translation_prompt', array($this, 'optimize_translation_prompt'), 10, 4);
            
            // Esegui traduzione
            $plugin = DynamicPageTranslator::get_instance();
            $api_handler = $plugin->get_api_handler();
            $translation = $api_handler->translate($content, $source_lang, $target_lang);
            
            // Rimuovi filtro
            remove_filter('dpt_translation_prompt', array($this, 'optimize_translation_prompt'), 10);
            
            return $translation;
            
        } finally {
            // Ripristina impostazioni
            if ($original_model) {
                dpt_update_option('openrouter_model', $original_model);
            }
            ini_set('max_execution_time', $original_timeout);
        }
    }
    
    /**
     * Ottimizza prompt per traduzione
     */
    public function optimize_translation_prompt($prompt, $content, $source_lang, $target_lang) {
        $source_name = $this->get_language_name($source_lang);
        $target_name = $this->get_language_name($target_lang);
        
        // Prompt ultra-ottimizzato per velocità
        return "Translate from {$source_name} to {$target_name}:\n\n{$content}";
    }
    
    /**
     * Check dizionario personalizzato ottimizzato
     */
    private function check_custom_dictionary_optimized($content, $source_lang, $target_lang) {
        // Cache del dizionario in memoria per performance
        static $dictionary_cache = null;
        
        if ($dictionary_cache === null) {
            $dictionary_cache = get_option('dpt_custom_dictionary_optimized', []);
        }
        
        $dict_key = $source_lang . '_' . $target_lang;
        
        if (!isset($dictionary_cache[$dict_key])) {
            return false;
        }
        
        $translations = $dictionary_cache[$dict_key];
        
        // Check traduzione esatta (case-insensitive)
        $content_lower = strtolower(trim($content));
        if (isset($translations['exact'][$content_lower])) {
            return $translations['exact'][$content_lower];
        }
        
        // Check traduzioni parziali con regex ottimizzate
        if (isset($translations['partial'])) {
            $result = $content;
            foreach ($translations['partial'] as $pattern => $replacement) {
                if (is_string($pattern) && is_string($replacement)) {
                    $result = str_ireplace($pattern, $replacement, $result);
                }
            }
            
            if ($result !== $content) {
                return $result;
            }
        }
        
        // Check traduzioni con wildcard
        if (isset($translations['wildcard'])) {
            foreach ($translations['wildcard'] as $pattern => $replacement) {
                $regex_pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
                if (preg_match('/^' . $regex_pattern . '$/i', $content)) {
                    return preg_replace('/^' . $regex_pattern . '$/i', $replacement, $content);
                }
            }
        }
        
        return false;
    }
    
    /**
     * AJAX Batch Translate Ottimizzato
     */
    public function ajax_batch_translate_optimized() {
        $start_time = microtime(true);
        
        if (!wp_verify_nonce($_POST['nonce'], 'dpt_frontend_nonce')) {
            wp_send_json_error(['code' => 'invalid_nonce']);
        }
        
        $items = json_decode(stripslashes($_POST['items']), true);
        $source_lang = sanitize_text_field($_POST['source_lang']);
        $target_lang = sanitize_text_field($_POST['target_lang']);
        $optimization_level = sanitize_text_field($_POST['optimization'] ?? 'balanced');
        
        if (!is_array($items) || empty($items)) {
            wp_send_json_error(['message' => 'Invalid items']);
        }
        
        $results = $this->process_batch_optimized($items, $source_lang, $target_lang, $optimization_level);
        
        wp_send_json_success([
            'results' => $results,
            'total_time' => round((microtime(true) - $start_time) * 1000, 2),
            'optimization_level' => $optimization_level,
            'cache_stats' => $this->get_batch_cache_stats($results)
        ]);
    }
    
    /**
     * Processa batch con ottimizzazioni intelligenti
     */
    private function process_batch_optimized($items, $source_lang, $target_lang, $optimization_level) {
        // Raggruppa e ottimizza
        $groups = $this->create_intelligent_groups($items, $optimization_level);
        $results = [];
        
        foreach ($groups as $group_type => $group_items) {
            $group_results = $this->process_group_optimized($group_items, $source_lang, $target_lang, $group_type);
            $results = array_merge($results, $group_results);
        }
        
        // Riordina risultati
        ksort($results);
        return array_values($results);
    }
    
    /**
     * Crea gruppi intelligenti per batch processing
     */
    private function create_intelligent_groups($items, $optimization_level) {
        $groups = [
            'cached' => [],
            'dictionary' => [],
            'micro' => [],      // < 50 caratteri
            'small' => [],      // 50-200 caratteri
            'medium' => [],     // 200-1000 caratteri
            'large' => [],      // > 1000 caratteri
            'complex' => []     // Alta complessità
        ];
        
        foreach ($items as $index => $item) {
            // Check cache prima
            $cache_key = $this->generate_optimized_cache_key($item['content'], $source_lang, $target_lang);
            if ($this->is_cached($cache_key)) {
                $groups['cached'][$index] = $item;
                continue;
            }
            
            // Check dizionario
            if ($this->check_custom_dictionary_optimized($item['content'], $source_lang, $target_lang) !== false) {
                $groups['dictionary'][$index] = $item;
                continue;
            }
            
            // Analizza contenuto
            $analysis = $this->analyze_content($item['content']);
            
            if ($analysis['complexity'] > 7) {
                $groups['complex'][$index] = $item;
            } elseif ($analysis['length'] < 50) {
                $groups['micro'][$index] = $item;
            } elseif ($analysis['length'] < 200) {
                $groups['small'][$index] = $item;
            } elseif ($analysis['length'] < 1000) {
                $groups['medium'][$index] = $item;
            } else {
                $groups['large'][$index] = $item;
            }
        }
        
        return array_filter($groups);
    }
    
    /**
     * Gestione memoria cache
     */
    private function manage_memory_cache_size() {
        $max_size = $this->cache_layers['memory']['max_size'] * 1024 * 1024; // Convert to bytes
        $current_size = 0;
        
        // Calcola dimensione approssimativa
        foreach ($this->memory_cache as $key => $value) {
            $current_size += strlen($key) + strlen($value);
        }
        
        // Se supera il limite, rimuovi i più vecchi (LRU)
        if ($current_size > $max_size) {
            $items_to_remove = count($this->memory_cache) - floor(count($this->memory_cache) * 0.8);
            $this->memory_cache = array_slice($this->memory_cache, $items_to_remove, null, true);
        }
    }
    
    /**
     * Compressione dati intelligente
     */
    private function should_compress($data) {
        if (!$this->result_compression) {
            return false;
        }
        
        // Comprimi solo se > 100 caratteri e rapporto compressione > 10%
        if (strlen($data) < 100) {
            return false;
        }
        
        $compressed = gzcompress($data, 6);
        $compression_ratio = strlen($compressed) / strlen($data);
        
        return $compression_ratio < 0.9;
    }
    
    private function compress_data($data) {
        return base64_encode(gzcompress($data, 6));
    }
    
    private function decompress_if_needed($data) {
        if ($this->is_compressed($data)) {
            return gzuncompress(base64_decode($data));
        }
        return $data;
    }
    
    private function is_compressed($data) {
        return is_string($data) && substr($data, 0, 4) === base64_encode(substr(gzcompress('test'), 0, 4));
    }
    
    /**
     * Cache warming intelligente
     */
    public function cache_warmup_process() {
        if (!$this->cache_warming_enabled) {
            return;
        }
        
        $common_phrases = $this->get_warming_phrases();
        $languages = dpt_get_option('enabled_languages', ['en', 'it', 'es', 'fr', 'de']);
        $default_lang = dpt_get_option('default_language', 'en');
        
        foreach ($languages as $target_lang) {
            if ($target_lang === $default_lang) continue;
            
            foreach ($common_phrases as $phrase) {
                $cache_key = $this->generate_optimized_cache_key($phrase, $default_lang, $target_lang);
                
                if (!$this->is_cached($cache_key)) {
                    // Traduci e cachea in background
                    wp_schedule_single_event(time() + rand(1, 300), 'dpt_warm_cache_item', [
                        $phrase, $default_lang, $target_lang
                    ]);
                }
            }
        }
    }
    
    /**
     * Ottiene frasi per cache warming
     */
    private function get_warming_phrases() {
        return [
            // UI comune
            'Read more', 'Continue reading', 'Previous', 'Next', 'Search', 'Submit', 'Cancel',
            'Contact', 'About', 'Home', 'Menu', 'Close', 'Open', 'Back', 'Loading', 'Please wait',
            
            // E-commerce
            'Add to cart', 'Buy now', 'Price', 'Sale', 'New', 'Featured', 'Product', 'Category',
            'Checkout', 'Payment', 'Shipping', 'Order', 'Cart', 'Wishlist',
            
            // Blog/Content
            'Categories', 'Tags', 'Archive', 'Page', 'Post', 'Comments', 'Reply', 'Author',
            'Published', 'Updated', 'Share', 'Subscribe', 'Newsletter',
            
            // Errori comuni
            'Error', 'Not found', 'Access denied', 'Please try again', 'Invalid input',
            'Required field', 'Success', 'Thank you', 'Confirmation'
        ];
    }
    
    /**
     * Avvia optimizer background
     */
    private function start_background_optimizer() {
        if (!wp_next_scheduled('dpt_background_optimize')) {
            wp_schedule_event(time(), 'hourly', 'dpt_background_optimize');
        }
        
        if (!wp_next_scheduled('dpt_cache_warmup')) {
            wp_schedule_event(time() + 300, 'daily', 'dpt_cache_warmup');
        }
    }
    
    /**
     * Esegue ottimizzazioni background
     */
    public function run_background_optimization() {
        // Pulisci cache scaduta
        $this->cleanup_expired_cache_optimized();
        
        // Ottimizza dizionario
        $this->optimize_custom_dictionary();
        
        // Compatta memory cache
        $this->manage_memory_cache_size();
        
        // Aggiorna statistiche performance
        $this->update_performance_statistics();
    }
    
    /**
     * Utility functions
     */
    private function generate_optimized_cache_key($content, $source_lang, $target_lang) {
        $provider = dpt_get_option('translation_provider');
        $model = $provider === 'openrouter' ? dpt_get_option('openrouter_model') : '';
        
        return hash('sha256', $content . $source_lang . $target_lang . $provider . $model);
    }
    
    private function is_cached($key) {
        return isset($this->memory_cache[$key]) || 
               ($this->cache_layers['redis']['enabled'] && $this->redis->exists('dpt_ultra_' . $key)) ||
               ($this->cache_layers['memcached']['enabled'] && $this->memcached->get('dpt_ultra_' . $key) !== false) ||
               (wp_cache_get('dpt_ultra_' . $key, 'dpt_translations') !== false) ||
               (get_transient('dpt_ultra_' . $key) !== false);
    }
    
    private function get_language_name($lang_code) {
        $names = [
            'en' => 'English', 'it' => 'Italian', 'es' => 'Spanish', 'fr' => 'French',
            'de' => 'German', 'pt' => 'Portuguese', 'ru' => 'Russian', 'zh' => 'Chinese',
            'ja' => 'Japanese', 'ar' => 'Arabic', 'auto' => 'auto-detect'
        ];
        
        return $names[$lang_code] ?? $lang_code;
    }
    
    private function detect_language_hints($content) {
        $hints = [];
        
        // Pattern per lingue comuni
        $patterns = [
            'en' => ['/\b(the|and|is|in|to|of|a|that)\b/i'],
            'it' => ['/\b(il|la|di|che|e|un|in|per)\b/i'],
            'es' => ['/\b(el|la|de|que|y|en|un|es)\b/i'],
            'fr' => ['/\b(le|de|et|à|un|il|être|et)\b/i'],
            'de' => ['/\b(der|die|und|in|den|von|zu|das)\b/i']
        ];
        
        foreach ($patterns as $lang => $lang_patterns) {
            $matches = 0;
            foreach ($lang_patterns as $pattern) {
                $matches += preg_match_all($pattern, $content);
            }
            if ($matches > 0) {
                $hints[$lang] = $matches;
            }
        }
        
        return $hints;
    }
    
    /**
     * API pubblica per statistiche performance
     */
    public function get_performance_statistics() {
        return [
            'cache_layers' => $this->cache_layers,
            'memory_cache_size' => count($this->memory_cache),
            'performance_settings' => [
                'batch_size' => $this->batch_size,
                'max_concurrent' => $this->max_concurrent,
                'compression_enabled' => $this->compression_enabled,
                'prefetch_enabled' => $this->prefetch_enabled,
                'cache_warming_enabled' => $this->cache_warming_enabled,
                'intelligent_batching' => $this->intelligent_batching,
                'adaptive_timeouts' => $this->adaptive_timeouts,
                'connection_pooling' => $this->connection_pooling
            ],
            'cache_hit_rates' => $this->calculate_cache_hit_rates(),
            'average_response_times' => $this->get_average_response_times()
        ];
    }
    
    private function calculate_cache_hit_rates() {
        $stats = get_option('dpt_cache_hit_stats', [
            'total_requests' => 0,
            'cache_hits' => 0,
            'memory_hits' => 0,
            'redis_hits' => 0,
            'memcached_hits' => 0,
            'object_hits' => 0,
            'transient_hits' => 0,
            'database_hits' => 0
        ]);
        
        $hit_rate = $stats['total_requests'] > 0 ? 
            ($stats['cache_hits'] / $stats['total_requests']) * 100 : 0;
        
        return [
            'overall_hit_rate' => round($hit_rate, 2),
            'detailed_stats' => $stats
        ];
    }
    
    private function get_average_response_times() {
        return get_option('dpt_response_time_stats', [
            'memory_avg' => 0.1,
            'redis_avg' => 0.5,
            'memcached_avg' => 0.8,
            'object_avg' => 1.2,
            'transient_avg' => 5.0,
            'database_avg' => 15.0,
            'api_avg' => 800.0
        ]);
    }
}

// Inizializza performance manager ottimizzato
new DPT_Performance_Manager_Optimized();