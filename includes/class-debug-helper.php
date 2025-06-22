<?php
/**
 * Debug Helper Class
 * Helps track and display module loading status
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_Debug_Helper {
    private static $instance = null;
    private $log = array();
    private $is_debug = false;
    
    public function is_debug() {
        return $this->is_debug;
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->is_debug = defined('WP_DEBUG') && WP_DEBUG;
        add_action('admin_notices', array($this, 'display_module_status'));
    }

    public function log($message, $type = 'info') {
        if (!$this->is_debug) {
            return;
        }

        $this->log[] = array(
            'time' => microtime(true),
            'message' => $message,
            'type' => $type
        );

        error_log("Dynamic Translator Debug: $message");
    }

    public function display_module_status() {
        if (!$this->is_debug || !is_admin()) {
            return;
        }

        $module_manager = null;
        $main_plugin = DynamicPageTranslator::get_instance();
        
        if (method_exists($main_plugin, 'get_module_manager')) {
            $module_manager = $main_plugin->get_module_manager();
        }

        echo '<div class="notice notice-info is-dismissible">';
        echo '<h3>Dynamic Translator Module Status</h3>';
        
        if ($module_manager) {
            $active_modules = $module_manager->get_active_modules();
            $all_modules = $module_manager->get_modules();
            
            echo '<p><strong>Active Modules:</strong> ' . count($active_modules) . '</p>';
            echo '<p><strong>Total Modules:</strong> ' . count($all_modules) . '</p>';
            
            echo '<h4>Module Details:</h4>';
            echo '<ul>';
            foreach ($all_modules as $id => $module) {
                $status = isset($active_modules[$id]) ? '✅ Active' : '❌ Inactive';
                echo '<li>';
                echo "<strong>$id:</strong> $status<br>";
                echo "Type: " . ($module['type'] ?? 'N/A') . "<br>";
                echo "Version: " . ($module['version'] ?? 'N/A');
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>❌ Module Manager not properly initialized</p>';
        }

        if (!empty($this->log)) {
            echo '<h4>Debug Log:</h4>';
            echo '<ul>';
            foreach ($this->log as $entry) {
                $time = date('H:i:s', (int)$entry['time']);
                $type = strtoupper($entry['type']);
                echo "<li>[$time][$type] {$entry['message']}</li>";
            }
            echo '</ul>';
        }

        echo '</div>';
    }

    public function get_log() {
        return $this->log;
    }

    public function clear_log() {
        $this->log = array();
    }
}
