<?php
/**
 * Gestore moduli del plugin
 * File: includes/class-module-manager.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_Module_Manager {
    
    private $modules = array();
    private $active_modules = array();
    private $modules_path;
    private $is_loading = false; // Flag per evitare ricorsione
    
    public function __construct() {
        $this->modules_path = DPT_PLUGIN_PATH . 'modules/';
        $this->init_hooks();
        $this->load_modules();
    }
    
    /**
     * Inizializza gli hook
     */
    private function init_hooks() {
        // Usa priorità più alta per attivarsi DOPO che i moduli sono caricati
        add_action('dpt_modules_loaded', array($this, 'activate_default_modules'), 20);
        add_action('admin_init', array($this, 'handle_module_actions'));
        
        // Hook per permettere registrazione moduli esterni
        do_action('dpt_register_modules', $this);
    }
    
    /**
     * Carica tutti i moduli disponibili
     */
    private function load_modules() {
        if ($this->is_loading) {
            return; // Evita ricorsione
        }
        
        $this->is_loading = true;
        
        // Carica moduli interni
        $this->discover_internal_modules();
        
        // Carica moduli esterni
        $this->discover_external_modules();
        
        // Carica moduli attivi
        $this->load_active_modules();
        
        $this->is_loading = false;
    }
    
    /**
     * Scopre moduli interni
     */
    private function discover_internal_modules() {
        $internal_modules = array(
            'google-translate' => array(
                'name' => __('Google Translate', 'dynamic-translator'),
                'description' => __('Provider di traduzione Google Translate API', 'dynamic-translator'),
                'version' => '1.0.0',
                'author' => 'Dynamic Translator',
                'type' => 'translation_provider',
                'file' => 'google-translate/google-translate.php',
                'class' => 'DPT_Google_Translate_Provider',
                'requires' => array(),
                'internal' => true,
                'icon' => 'google.svg'
            ),
            'openrouter-translate' => array(
                'name' => __('OpenRouter AI', 'dynamic-translator'),
                'description' => __('Provider di traduzione usando modelli AI tramite OpenRouter', 'dynamic-translator'),
                'version' => '1.0.0',
                'author' => 'Dynamic Translator',
                'type' => 'translation_provider',
                'file' => 'openrouter-translate/openrouter-translate.php',
                'class' => 'DPT_OpenRouter_Translate_Provider',
                'requires' => array(),
                'internal' => true,
                'icon' => 'openrouter.svg'
            ),
            'flag-display' => array(
                'name' => __('Flag Display', 'dynamic-translator'),
                'description' => __('Gestisce la visualizzazione delle bandiere per la selezione lingua', 'dynamic-translator'),
                'version' => '1.0.0',
                'author' => 'Dynamic Translator',
                'type' => 'display',
                'file' => 'flag-display/flag-display.php',
                'class' => 'DPT_Flag_Display_Module',
                'requires' => array(),
                'internal' => true,
                'icon' => 'flags.svg'
            ),
            'seo-optimizer' => array(
                'name' => __('SEO Optimizer', 'dynamic-translator'),
                'description' => __('Ottimizza il SEO per contenuti multilingue', 'dynamic-translator'),
                'version' => '1.0.0',
                'author' => 'Dynamic Translator',
                'type' => 'seo',
                'file' => 'seo-optimizer/seo-optimizer.php',
                'class' => 'DPT_SEO_Optimizer_Module',
                'requires' => array(),
                'internal' => true,
                'icon' => 'seo.svg'
            )
        );
        
        foreach ($internal_modules as $module_id => $module_info) {
            $module_file = $this->modules_path . $module_info['file'];
            if (file_exists($module_file)) {
                $this->modules[$module_id] = $module_info;
            }
        }
    }
    
    /**
     * Scopre moduli esterni
     */
    private function discover_external_modules() {
        $external_modules_path = WP_CONTENT_DIR . '/dpt-modules/';
        
        if (!is_dir($external_modules_path)) {
            return;
        }
        
        $directories = glob($external_modules_path . '*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $module_id = basename($dir);
            $module_file = $dir . '/' . $module_id . '.php';
            $info_file = $dir . '/module.json';
            
            if (file_exists($module_file) && file_exists($info_file)) {
                $module_info = json_decode(file_get_contents($info_file), true);
                
                if ($module_info && $this->validate_module_info($module_info)) {
                    $module_info['file'] = $module_file;
                    $module_info['internal'] = false;
                    $module_info['external_path'] = $dir;
                    
                    $this->modules[$module_id] = $module_info;
                }
            }
        }
    }
    
    /**
     * Valida informazioni modulo
     */
    private function validate_module_info($info) {
        $required_fields = array('name', 'description', 'version', 'type', 'class');
        
        foreach ($required_fields as $field) {
            if (!isset($info[$field]) || empty($info[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Carica moduli attivi - SENZA attivazione automatica
     */
    private function load_active_modules() {
        $active_modules = get_option('dpt_active_modules', array());
        
        foreach ($active_modules as $module_id) {
            if (isset($this->modules[$module_id])) {
                // NON caricare automaticamente qui per evitare ricorsione
                // Il caricamento avverrà tramite activate_default_modules
            }
        }
    }
    
    /**
     * Carica un singolo modulo
     */
    private function load_module($module_id) {
        if (!isset($this->modules[$module_id])) {
            return false;
        }
        
        $module = $this->modules[$module_id];
        
        // Controlla dipendenze
        if (!$this->check_module_dependencies($module)) {
            return false;
        }
        
        // Carica file modulo
        if ($module['internal']) {
            $module_file = $this->modules_path . $module['file'];
        } else {
            $module_file = $module['file'];
        }
        
        if (!file_exists($module_file)) {
            return false;
        }
        
        // Il file è già stato incluso nella fase di load_dependencies
        // Quindi non lo ricarichiamo per evitare errori "class already exists"
        
        // Se la classe esiste, la registriamo come attiva
        if (class_exists($module['class'])) {
            // Non istanziare qui - i moduli si auto-istanziano
            $this->active_modules[$module_id] = true;
            
            // Trigger hook per modulo caricato
            do_action('dpt_module_loaded', $module_id, null);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Controlla dipendenze modulo
     */
    private function check_module_dependencies($module) {
        if (empty($module['requires'])) {
            return true;
        }
        
        foreach ($module['requires'] as $dependency) {
            if (is_string($dependency)) {
                // Dipendenza da plugin WordPress
                if (!is_plugin_active($dependency)) {
                    return false;
                }
            } elseif (is_array($dependency)) {
                // Dipendenza da funzione/classe
                if (isset($dependency['function']) && !function_exists($dependency['function'])) {
                    return false;
                }
                if (isset($dependency['class']) && !class_exists($dependency['class'])) {
                    return false;
                }
                if (isset($dependency['module']) && !$this->is_module_active($dependency['module'])) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Attiva moduli di default - CHIAMATO DOPO l'inizializzazione
     */
    public function activate_default_modules() {
        // Evita di attivarsi durante il caricamento iniziale
        if ($this->is_loading) {
            return;
        }
        
        $default_modules = array('flag-display');
        $current_provider = dpt_get_option('translation_provider', 'google');
        
        // Attiva provider corrente
        if ($current_provider === 'google') {
            $default_modules[] = 'google-translate';
        } elseif ($current_provider === 'openrouter') {
            $default_modules[] = 'openrouter-translate';
        }
        
        foreach ($default_modules as $module_id) {
            if (!$this->is_module_active($module_id)) {
                $this->activate_module($module_id);
            }
        }
    }
    
    /**
     * Attiva un modulo
     */
    public function activate_module($module_id) {
        if (!isset($this->modules[$module_id])) {
            return new WP_Error('module_not_found', __('Modulo non trovato', 'dynamic-translator'));
        }
        
        if ($this->is_module_active($module_id)) {
            return new WP_Error('module_already_active', __('Modulo già attivo', 'dynamic-translator'));
        }
        
        // Controlla dipendenze
        if (!$this->check_module_dependencies($this->modules[$module_id])) {
            return new WP_Error('missing_dependencies', __('Dipendenze mancanti', 'dynamic-translator'));
        }
        
        // Carica modulo
        if ($this->load_module($module_id)) {
            $active_modules = get_option('dpt_active_modules', array());
            $active_modules[] = $module_id;
            update_option('dpt_active_modules', array_unique($active_modules));
            
            // Hook attivazione modulo
            do_action('dpt_module_activated', $module_id);
            
            return true;
        }
        
        return new WP_Error('activation_failed', __('Attivazione fallita', 'dynamic-translator'));
    }
    
    /**
     * Disattiva un modulo
     */
    public function deactivate_module($module_id) {
        if (!$this->is_module_active($module_id)) {
            return new WP_Error('module_not_active', __('Modulo non attivo', 'dynamic-translator'));
        }
        
        // Hook disattivazione modulo
        do_action('dpt_module_deactivating', $module_id);
        
        $active_modules = get_option('dpt_active_modules', array());
        $active_modules = array_diff($active_modules, array($module_id));
        update_option('dpt_active_modules', $active_modules);
        
        // Rimuovi da moduli attivi
        unset($this->active_modules[$module_id]);
        
        // Hook disattivazione completata
        do_action('dpt_module_deactivated', $module_id);
        
        return true;
    }
    
    /**
     * Controlla se modulo è attivo
     */
    public function is_module_active($module_id) {
        return isset($this->active_modules[$module_id]);
    }
    
    /**
     * Ottiene tutti i moduli
     */
    public function get_modules() {
        return $this->modules;
    }
    
    /**
     * Ottiene moduli attivi
     */
    public function get_active_modules() {
        return $this->active_modules;
    }
    
    /**
     * Ottiene moduli per tipo
     */
    public function get_modules_by_type($type) {
        $filtered = array();
        
        foreach ($this->modules as $module_id => $module) {
            if ($module['type'] === $type) {
                $filtered[$module_id] = $module;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Registra modulo esterno
     */
    public function register_module($module_id, $module_info) {
        if (isset($this->modules[$module_id])) {
            return new WP_Error('module_exists', __('Modulo già registrato', 'dynamic-translator'));
        }
        
        if (!$this->validate_module_info($module_info)) {
            return new WP_Error('invalid_module', __('Informazioni modulo non valide', 'dynamic-translator'));
        }
        
        $module_info['internal'] = false;
        $this->modules[$module_id] = $module_info;
        
        do_action('dpt_module_registered', $module_id, $module_info);
        
        return true;
    }
    
    /**
     * Gestisce azioni moduli dall'admin
     */
    public function handle_module_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'dynamic-translator-modules') {
            return;
        }
        
        if (!isset($_GET['action']) || !isset($_GET['module'])) {
            return;
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'dpt_module_action')) {
            wp_die(__('Errore di sicurezza', 'dynamic-translator'));
        }
        
        $action = sanitize_text_field($_GET['action']);
        $module_id = sanitize_text_field($_GET['module']);
        
        switch ($action) {
            case 'activate':
                $result = $this->activate_module($module_id);
                break;
            case 'deactivate':
                $result = $this->deactivate_module($module_id);
                break;
            case 'delete':
                $result = $this->delete_module($module_id);
                break;
            default:
                return;
        }
        
        if (is_wp_error($result)) {
            $message = $result->get_error_message();
            $class = 'error';
        } else {
            $message = __('Azione completata con successo', 'dynamic-translator');
            $class = 'updated';
        }
        
        add_action('admin_notices', function() use ($message, $class) {
            echo '<div class="notice notice-' . $class . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        });
    }
    
    /**
     * Elimina modulo (solo esterni)
     */
    public function delete_module($module_id) {
        if (!isset($this->modules[$module_id])) {
            return new WP_Error('module_not_found', __('Modulo non trovato', 'dynamic-translator'));
        }
        
        $module = $this->modules[$module_id];
        
        if ($module['internal']) {
            return new WP_Error('cannot_delete_internal', __('Non è possibile eliminare moduli interni', 'dynamic-translator'));
        }
        
        // Disattiva se attivo
        if ($this->is_module_active($module_id)) {
            $this->deactivate_module($module_id);
        }
        
        // Elimina file
        if (isset($module['external_path']) && is_dir($module['external_path'])) {
            $this->delete_directory($module['external_path']);
        }
        
        // Rimuovi da lista moduli
        unset($this->modules[$module_id]);
        
        do_action('dpt_module_deleted', $module_id);
        
        return true;
    }
    
    /**
     * Elimina directory ricorsivamente
     */
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Ottiene statistiche moduli
     */
    public function get_modules_stats() {
        return array(
            'total_modules' => count($this->modules),
            'active_modules' => count($this->active_modules),
            'internal_modules' => count(array_filter($this->modules, function($m) { return $m['internal']; })),
            'external_modules' => count(array_filter($this->modules, function($m) { return !$m['internal']; })),
            'modules_by_type' => $this->get_modules_count_by_type()
        );
    }
    
    /**
     * Ottiene conteggio moduli per tipo
     */
    private function get_modules_count_by_type() {
        $counts = array();
        
        foreach ($this->modules as $module) {
            $type = $module['type'];
            if (!isset($counts[$type])) {
                $counts[$type] = 0;
            }
            $counts[$type]++;
        }
        
        return $counts;
    }
}