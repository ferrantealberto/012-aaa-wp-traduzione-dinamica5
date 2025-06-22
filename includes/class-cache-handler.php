<?php
/**
 * Gestore della cache per le traduzioni
 * File: includes/class-cache-handler.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_Cache_Handler {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dpt_translations_cache';
        
        // Hook per pulizia automatica cache scaduta
        add_action('dpt_cleanup_cache', array($this, 'cleanup_expired_cache'));
        
        // Programma pulizia cache se non già programmata
        if (!wp_next_scheduled('dpt_cleanup_cache')) {
            wp_schedule_event(time(), 'daily', 'dpt_cleanup_cache');
        }
    }
    
    /**
     * Ottiene una traduzione dalla cache
     */
    public function get_translation($cache_key) {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT translated_content FROM {$this->table_name} 
             WHERE cache_key = %s AND expires_at > NOW()",
            $cache_key
        ));
        
        if ($result) {
            // Aggiorna statistiche uso cache
            $this->update_cache_stats('hit');
            return $result->translated_content;
        }
        
        $this->update_cache_stats('miss');
        return false;
    }
    
    /**
     * Salva una traduzione nella cache
     */
    public function save_translation($cache_key, $translation, $original_content = '', $source_lang = '', $target_lang = '') {
        global $wpdb;
        
        $cache_duration = dpt_get_option('cache_duration', 30);
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$cache_duration} days"));
        
        $result = $wpdb->replace(
            $this->table_name,
            array(
                'cache_key' => $cache_key,
                'original_content' => $original_content,
                'translated_content' => $translation,
                'source_lang' => $source_lang,
                'target_lang' => $target_lang,
                'expires_at' => $expires_at
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Genera chiave cache unica
     */
    public function generate_cache_key($content, $source_lang, $target_lang, $additional_params = array()) {
        $data = array(
            'content' => $content,
            'source' => $source_lang,
            'target' => $target_lang,
            'params' => $additional_params
        );
        
        return md5(serialize($data));
    }
    
    /**
     * Pulisce cache scaduta
     */
    public function cleanup_expired_cache() {
        global $wpdb;
        
        $deleted = $wpdb->query(
            "DELETE FROM {$this->table_name} WHERE expires_at < NOW()"
        );
        
        do_action('dpt_cache_cleaned', $deleted);
        
        return $deleted;
    }
    
    /**
     * Pulisce tutta la cache per una lingua specifica
     */
    public function clear_language_cache($language_code) {
        global $wpdb;
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE source_lang = %s OR target_lang = %s",
            $language_code,
            $language_code
        ));
        
        return $deleted;
    }
    
    /**
     * Pulisce tutta la cache
     */
    public function clear_all_cache() {
        global $wpdb;
        
        $deleted = $wpdb->query("DELETE FROM {$this->table_name}");
        
        do_action('dpt_all_cache_cleared', $deleted);
        
        return $deleted;
    }
    
    /**
     * Ottiene statistiche cache
     */
    public function get_cache_stats() {
        global $wpdb;
        
        $total_entries = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $expired_entries = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE expires_at < NOW()");
        
        $stats = get_option('dpt_cache_stats', array(
            'hits' => 0,
            'misses' => 0,
            'hit_rate' => 0
        ));
        
        $stats['total_entries'] = $total_entries;
        $stats['expired_entries'] = $expired_entries;
        $stats['active_entries'] = $total_entries - $expired_entries;
        
        // Calcola dimensione cache
        $cache_size = $wpdb->get_var(
            "SELECT SUM(LENGTH(original_content) + LENGTH(translated_content)) FROM {$this->table_name}"
        );
        $stats['cache_size_mb'] = round($cache_size / 1024 / 1024, 2);
        
        return $stats;
    }
    
    /**
     * Aggiorna statistiche uso cache
     */
    private function update_cache_stats($type) {
        $stats = get_option('dpt_cache_stats', array(
            'hits' => 0,
            'misses' => 0,
            'hit_rate' => 0
        ));
        
        $stats[$type === 'hit' ? 'hits' : 'misses']++;
        
        $total = $stats['hits'] + $stats['misses'];
        $stats['hit_rate'] = $total > 0 ? round(($stats['hits'] / $total) * 100, 2) : 0;
        
        update_option('dpt_cache_stats', $stats);
    }
    
    /**
     * Ottiene traduzioni per lingua
     */
    public function get_translations_by_language($language_code, $limit = 100) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE (source_lang = %s OR target_lang = %s) 
             AND expires_at > NOW() 
             ORDER BY created_at DESC 
             LIMIT %d",
            $language_code,
            $language_code,
            $limit
        ));
    }
    
    /**
     * Esporta cache in formato JSON
     */
    public function export_cache() {
        global $wpdb;
        
        $translations = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE expires_at > NOW()"
        );
        
        $export_data = array(
            'export_date' => current_time('mysql'),
            'total_translations' => count($translations),
            'translations' => $translations
        );
        
        return json_encode($export_data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Importa cache da JSON
     */
    public function import_cache($json_data) {
        global $wpdb;
        
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['translations'])) {
            return false;
        }
        
        $imported = 0;
        foreach ($data['translations'] as $translation) {
            $result = $wpdb->replace(
                $this->table_name,
                array(
                    'cache_key' => $translation['cache_key'],
                    'original_content' => $translation['original_content'],
                    'translated_content' => $translation['translated_content'],
                    'source_lang' => $translation['source_lang'],
                    'target_lang' => $translation['target_lang'],
                    'expires_at' => $translation['expires_at']
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result) {
                $imported++;
            }
        }
        
        return $imported;
    }
    
    /**
     * Ottimizza tabella cache
     */
    public function optimize_cache_table() {
        global $wpdb;
        
        // Prima pulisce cache scaduta
        $this->cleanup_expired_cache();
        
        // Poi ottimizza la tabella
        $wpdb->query("OPTIMIZE TABLE {$this->table_name}");
        
        do_action('dpt_cache_optimized');
        
        return true;
    }
    
    /**
     * Controlla se la cache è abilitata
     */
    public function is_cache_enabled() {
        return dpt_get_option('enable_cache', true);
    }
    
    /**
     * Abilita/disabilita cache
     */
    public function toggle_cache($enable = true) {
        return dpt_update_option('enable_cache', $enable);
    }
}