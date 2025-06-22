<?php
/**
 * Uninstall script per Dynamic Page Translator
 * 
 * Questo file viene eseguito quando il plugin viene disinstallato
 * attraverso l'interfaccia admin di WordPress.
 */

// Previene accesso diretto
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Verifica che l'utente abbia i permessi necessari
if (!current_user_can('activate_plugins')) {
    exit;
}

/**
 * Rimuove tutte le tracce del plugin dal database
 */
class DPT_Uninstaller {
    
    /**
     * Esegue la disinstallazione completa
     */
    public static function uninstall() {
        global $wpdb;
        
        // Chiede conferma prima di procedere (solo in admin)
        if (is_admin() && !defined('DOING_AJAX')) {
            $confirm = get_option('dpt_confirm_uninstall', false);
            if (!$confirm) {
                // Se non confermato, salva flag e rimanda
                update_option('dpt_confirm_uninstall_pending', true);
                return;
            }
        }
        
        // 1. Rimuove tabelle database
        self::drop_database_tables();
        
        // 2. Rimuove opzioni
        self::remove_options();
        
        // 3. Rimuove file caricati
        self::remove_uploaded_files();
        
        // 4. Rimuove moduli esterni
        self::remove_external_modules();
        
        // 5. Pulisce cache
        self::clear_cache();
        
        // 6. Rimuove scheduled events
        self::remove_scheduled_events();
        
        // 7. Pulisce user meta
        self::clean_user_meta();
        
        // 8. Log disinstallazione
        self::log_uninstall();
    }
    
    /**
     * Rimuove tabelle database
     */
    private static function drop_database_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'dpt_translations_cache',
            $wpdb->prefix . 'dpt_translation_logs',
            $wpdb->prefix . 'dpt_module_settings'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }
    }
    
    /**
     * Rimuove tutte le opzioni del plugin
     */
    private static function remove_options() {
        global $wpdb;
        
        // Lista opzioni principali
        $options = array(
            'dpt_enabled_languages',
            'dpt_default_language',
            'dpt_translation_provider',
            'dpt_google_api_key',
            'dpt_openrouter_api_key',
            'dpt_openrouter_model',
            'dpt_cache_duration',
            'dpt_flag_position',
            'dpt_flag_style',
            'dpt_auto_detect_language',
            'dpt_translate_dynamic_content',
            'dpt_preserve_html',
            'dpt_enable_cache',
            'dpt_enable_translation_log',
            'dpt_google_rate_limit',
            'dpt_openrouter_rate_limit',
            'dpt_flag_custom_positions',
            'dpt_flag_size',
            'dpt_flag_border_style',
            'dpt_flag_shadow',
            'dpt_flag_animations',
            'dpt_flag_hide_mobile',
            'dpt_flag_show_labels',
            'dpt_flag_auto_hide',
            'dpt_custom_flags',
            'dpt_active_modules',
            'dpt_cache_stats',
            'dpt_translation_log',
            'dpt_google_translate_stats',
            'dpt_openrouter_stats',
            'dpt_version',
            'dpt_first_install',
            'dpt_db_version'
        );
        
        // Rimuove opzioni una per una
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Rimuove tutte le opzioni che iniziano con 'dpt_'
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE 'dpt_%'"
        );
        
        // Rimuove rate limiting per provider
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE 'dpt_rate_limit_%'"
        );
        
        // Rimuove transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_dpt_%' 
             OR option_name LIKE '_transient_timeout_dpt_%'"
        );
    }
    
    /**
     * Rimuove file caricati
     */
    private static function remove_uploaded_files() {
        $upload_dir = wp_upload_dir();
        
        // Directory bandiere personalizzate
        $flags_dir = $upload_dir['basedir'] . '/dpt-flags/';
        if (is_dir($flags_dir)) {
            self::delete_directory($flags_dir);
        }
        
        // Directory cache esportazioni
        $exports_dir = $upload_dir['basedir'] . '/dpt-exports/';
        if (is_dir($exports_dir)) {
            self::delete_directory($exports_dir);
        }
        
        // Directory backup
        $backup_dir = $upload_dir['basedir'] . '/dpt-backups/';
        if (is_dir($backup_dir)) {
            self::delete_directory($backup_dir);
        }
    }
    
    /**
     * Rimuove moduli esterni
     */
    private static function remove_external_modules() {
        $modules_dir = WP_CONTENT_DIR . '/dpt-modules/';
        if (is_dir($modules_dir)) {
            self::delete_directory($modules_dir);
        }
    }
    
    /**
     * Pulisce cache
     */
    private static function clear_cache() {
        // Pulisce cache oggetti
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Pulisce cache transients
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_dpt_%' 
             OR option_name LIKE '_transient_timeout_dpt_%'"
        );
        
        // Pulisce cache di terze parti se esistenti
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        if (function_exists('wp_rocket_clean_domain')) {
            wp_rocket_clean_domain();
        }
        
        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        }
    }
    
    /**
     * Rimuove eventi programmati
     */
    private static function remove_scheduled_events() {
        $scheduled_events = array(
            'dpt_cleanup_cache',
            'dpt_update_stats',
            'dpt_backup_cache',
            'dpt_optimize_database',
            'dpt_check_api_limits'
        );
        
        foreach ($scheduled_events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
            // Rimuove tutti gli eventi di questo tipo
            wp_clear_scheduled_hook($event);
        }
    }
    
    /**
     * Pulisce user meta
     */
    private static function clean_user_meta() {
        global $wpdb;
        
        // Rimuove meta utenti correlati al plugin
        $wpdb->query(
            "DELETE FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'dpt_%'"
        );
        
        // Rimuove preferenze admin
        $wpdb->query(
            "DELETE FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE '%dpt_admin_%'"
        );
    }
    
    /**
     * Elimina directory ricorsivamente
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Log della disinstallazione
     */
    private static function log_uninstall() {
        // Log solo se esplicitamente richiesto
        if (defined('DPT_LOG_UNINSTALL') && DPT_LOG_UNINSTALL) {
            $log_data = array(
                'timestamp' => current_time('mysql'),
                'site_url' => get_site_url(),
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'plugin_version' => '1.0.0'
            );
            
            // Invia log anonimo per statistiche (opzionale)
            if (defined('DPT_SEND_UNINSTALL_DATA') && DPT_SEND_UNINSTALL_DATA) {
                wp_remote_post('https://stats.tuosito.com/dpt-uninstall', array(
                    'body' => json_encode($log_data),
                    'headers' => array('Content-Type' => 'application/json'),
                    'timeout' => 5
                ));
            }
        }
    }
    
    /**
     * Verifica se mantenere dati
     */
    private static function should_keep_data() {
        // Controlla se l'opzione di mantenere dati è attiva
        return get_option('dpt_keep_data_on_uninstall', false);
    }
    
    /**
     * Backup dati prima della rimozione
     */
    private static function backup_data_before_removal() {
        global $wpdb;
        
        if (!self::should_keep_data()) {
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/dpt-uninstall-backup/';
        
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        // Backup cache traduzioni
        $cache_data = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}dpt_translations_cache",
            ARRAY_A
        );
        
        if (!empty($cache_data)) {
            file_put_contents(
                $backup_dir . 'translations_cache_' . date('Y-m-d_H-i-s') . '.json',
                json_encode($cache_data, JSON_PRETTY_PRINT)
            );
        }
        
        // Backup opzioni
        $options_data = array();
        $options = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'dpt_%'",
            ARRAY_A
        );
        
        foreach ($options as $option) {
            $options_data[$option['option_name']] = maybe_unserialize($option['option_value']);
        }
        
        if (!empty($options_data)) {
            file_put_contents(
                $backup_dir . 'options_' . date('Y-m-d_H-i-s') . '.json',
                json_encode($options_data, JSON_PRETTY_PRINT)
            );
        }
        
        // Crea file readme per il backup
        $readme_content = "Dynamic Page Translator - Backup Disinstallazione\n";
        $readme_content .= "Data: " . date('Y-m-d H:i:s') . "\n";
        $readme_content .= "Sito: " . get_site_url() . "\n\n";
        $readme_content .= "Questo backup contiene i dati del plugin prima della disinstallazione.\n";
        $readme_content .= "Per ripristinare i dati, reinstalla il plugin e usa la funzione di import.\n";
        
        file_put_contents($backup_dir . 'README.txt', $readme_content);
    }
}

// Esegue la disinstallazione
try {
    // Backup dati se richiesto
    if (get_option('dpt_backup_before_uninstall', true)) {
        DPT_Uninstaller::backup_data_before_removal();
    }
    
    // Solo se non richiesto di mantenere i dati
    if (!get_option('dpt_keep_data_on_uninstall', false)) {
        DPT_Uninstaller::uninstall();
    }
    
} catch (Exception $e) {
    // Log errore senza fermare la disinstallazione
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('DPT Uninstall Error: ' . $e->getMessage());
    }
    
    // Continua comunque con la disinstallazione di base
    DPT_Uninstaller::remove_options();
}

// Pulisce il file uninstall stesso (WordPress si occupa di questo automaticamente)
// Ma rimuoviamo eventuali hook residui
remove_all_actions('dpt_cleanup_cache');
remove_all_actions('dpt_update_stats');
remove_all_filters('dpt_translation_content');
remove_all_filters('dpt_flag_url');