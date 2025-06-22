<?php
/**
 * Strumenti di Debug e Troubleshooting per Dynamic Page Translator - VERSIONE CORRETTA
 * File: includes/class-debug-helper.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_Debug_Helper {
    
    private static $instance = null;
    private $log_entries = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook per aggiungere pagina debug nell'admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_debug_page'));
            add_action('wp_ajax_dpt_run_diagnostic', array($this, 'ajax_run_diagnostic'));
            add_action('wp_ajax_dpt_test_openrouter_detailed', array($this, 'ajax_test_openrouter_detailed'));
        }
    }
    
    /**
     * Aggiunge pagina debug nell'admin
     */
    public function add_debug_page() {
        add_submenu_page(
            'dynamic-translator',
            __('Debug & Troubleshooting', 'dynamic-translator'),
            __('Debug', 'dynamic-translator'),
            'manage_options',
            'dynamic-translator-debug',
            array($this, 'render_debug_page')
        );
    }
    
    /**
     * Renderizza pagina debug
     */
    public function render_debug_page() {
        // CORREZIONE: Genera nonce corretto per la pagina
        $debug_nonce = wp_create_nonce('dpt_debug_nonce');
        ?>
        <div class="wrap">
            <h1><?php _e('Debug & Troubleshooting', 'dynamic-translator'); ?></h1>
            
            <div class="dpt-debug-sections">
                
                <!-- Diagnostica Sistema -->
                <div class="dpt-debug-section">
                    <h2><?php _e('Diagnostica Sistema', 'dynamic-translator'); ?></h2>
                    <button type="button" id="run-full-diagnostic" class="button button-primary">
                        <?php _e('Esegui Diagnostica Completa', 'dynamic-translator'); ?>
                    </button>
                    <div id="diagnostic-results" class="dpt-diagnostic-results"></div>
                </div>
                
                <!-- Test OpenRouter Dettagliato -->
                <div class="dpt-debug-section">
                    <h2><?php _e('Test OpenRouter Dettagliato', 'dynamic-translator'); ?></h2>
                    <p><?php _e('Test approfondito della connessione OpenRouter con informazioni dettagliate.', 'dynamic-translator'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e('API Key', 'dynamic-translator'); ?></th>
                            <td>
                                <input type="password" id="debug-openrouter-key" class="regular-text" 
                                       value="<?php echo esc_attr(dpt_get_option('openrouter_api_key', '')); ?>"
                                       placeholder="<?php _e('Inserisci API key per test', 'dynamic-translator'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Modello', 'dynamic-translator'); ?></th>
                            <td>
                                <select id="debug-openrouter-model">
                                    <option value="meta-llama/llama-3.1-8b-instruct:free">Llama 3.1 8B (Free)</option>
                                    <option value="meta-llama/llama-3.1-70b-instruct">Llama 3.1 70B</option>
                                    <option value="anthropic/claude-3-haiku">Claude 3 Haiku</option>
                                    <option value="openai/gpt-4o-mini">GPT-4o Mini</option>
                                    <option value="google/gemini-flash-1.5">Gemini Flash 1.5</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Testo Test', 'dynamic-translator'); ?></th>
                            <td>
                                <input type="text" id="debug-test-text" class="regular-text" 
                                       value="Hello, how are you?" 
                                       placeholder="<?php _e('Testo da tradurre per test', 'dynamic-translator'); ?>">
                            </td>
                        </tr>
                    </table>
                    
                    <button type="button" id="test-openrouter-detailed" class="button">
                        <?php _e('Test Dettagliato OpenRouter', 'dynamic-translator'); ?>
                    </button>
                    <div id="openrouter-test-results" class="dpt-test-results"></div>
                </div>
                
                <!-- Informazioni Sistema -->
                <div class="dpt-debug-section">
                    <h2><?php _e('Informazioni Sistema', 'dynamic-translator'); ?></h2>
                    <div class="dpt-system-info">
                        <?php $this->render_system_info(); ?>
                    </div>
                </div>
                
                <!-- Log Traduzioni -->
                <div class="dpt-debug-section">
                    <h2><?php _e('Log Traduzioni Recenti', 'dynamic-translator'); ?></h2>
                    <div class="dpt-translation-log">
                        <?php $this->render_translation_log(); ?>
                    </div>
                </div>
                
                <!-- Test Connettività -->
                <div class="dpt-debug-section">
                    <h2><?php _e('Test Connettività', 'dynamic-translator'); ?></h2>
                    <div class="dpt-connectivity-tests">
                        <?php $this->render_connectivity_tests(); ?>
                    </div>
                </div>
                
            </div>
        </div>
        
        <style>
        .dpt-debug-sections {
            display: grid;
            gap: 20px;
        }
        .dpt-debug-section {
            background: white;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .dpt-diagnostic-results,
        .dpt-test-results {
            margin-top: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            border: 1px solid #ddd;
        }
        .dpt-system-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .dpt-system-info th,
        .dpt-system-info td {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .dpt-system-info th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .dpt-status-ok { color: #28a745; }
        .dpt-status-warning { color: #ffc107; }
        .dpt-status-error { color: #dc3545; }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            
            // CORREZIONE: Usa il nonce corretto generato in PHP
            const debugNonce = '<?php echo $debug_nonce; ?>';
            
            // Diagnostica completa
            $('#run-full-diagnostic').on('click', function() {
                const $button = $(this);
                const $results = $('#diagnostic-results');
                
                $button.prop('disabled', true).text('Esecuzione diagnostica...');
                $results.html('Esecuzione test di sistema...\n');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dpt_run_diagnostic',
                        nonce: debugNonce
                    },
                    timeout: 60000,
                    success: function(response) {
                        if (response.success) {
                            $results.html(response.data.report);
                        } else {
                            $results.html('ERRORE: ' + (response.data || 'Errore sconosciuto'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $results.html('ERRORE CONNESSIONE: ' + status + ' - ' + error);
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Esegui Diagnostica Completa');
                    }
                });
            });
            
            // Test OpenRouter dettagliato
            $('#test-openrouter-detailed').on('click', function() {
                const $button = $(this);
                const $results = $('#openrouter-test-results');
                
                const apiKey = $('#debug-openrouter-key').val();
                const model = $('#debug-openrouter-model').val();
                const testText = $('#debug-test-text').val();
                
                if (!apiKey) {
                    $results.html('ERRORE: Inserisci API key');
                    return;
                }
                
                $button.prop('disabled', true).text('Test in corso...');
                $results.html('Avvio test OpenRouter...\n');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dpt_test_openrouter_detailed',
                        api_key: apiKey,
                        model: model,
                        test_text: testText,
                        nonce: debugNonce
                    },
                    timeout: 60000,
                    success: function(response) {
                        if (response.success) {
                            $results.html(response.data.report);
                        } else {
                            $results.html('ERRORE: ' + (response.data || 'Errore sconosciuto'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $results.html('ERRORE CONNESSIONE: ' + status + ' - ' + error);
                        console.log('Dettagli errore:', xhr.responseText);
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Test Dettagliato OpenRouter');
                    }
                });
            });
            
        });
        </script>
        <?php
    }
    
    /**
     * Render informazioni sistema
     */
    private function render_system_info() {
        $current_provider = dpt_get_option('translation_provider', 'google');
        $enabled_languages = dpt_get_option('enabled_languages', array('en', 'es', 'fr', 'de'));
        
        ?>
        <table class="dpt-system-info">
            <tr>
                <th><?php _e('Plugin Version', 'dynamic-translator'); ?></th>
                <td><?php echo esc_html(DPT_VERSION); ?></td>
            </tr>
            <tr>
                <th><?php _e('WordPress Version', 'dynamic-translator'); ?></th>
                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
            </tr>
            <tr>
                <th><?php _e('PHP Version', 'dynamic-translator'); ?></th>
                <td><?php echo esc_html(PHP_VERSION); ?></td>
            </tr>
            <tr>
                <th><?php _e('Current Provider', 'dynamic-translator'); ?></th>
                <td><?php echo esc_html(ucfirst($current_provider)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Cache Enabled', 'dynamic-translator'); ?></th>
                <td>
                    <span class="<?php echo dpt_get_option('enable_cache', true) ? 'dpt-status-ok' : 'dpt-status-warning'; ?>">
                        <?php echo dpt_get_option('enable_cache', true) ? '✓ Abilitata' : '⚠ Disabilitata'; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th><?php _e('cURL Available', 'dynamic-translator'); ?></th>
                <td>
                    <span class="<?php echo function_exists('curl_init') ? 'dpt-status-ok' : 'dpt-status-error'; ?>">
                        <?php echo function_exists('curl_init') ? '✓ Disponibile' : '✗ Non disponibile'; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th><?php _e('cURL Version', 'dynamic-translator'); ?></th>
                <td>
                    <?php 
                    if (function_exists('curl_version')) {
                        $curl_info = curl_version();
                        echo esc_html($curl_info['version']);
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Memory Limit', 'dynamic-translator'); ?></th>
                <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
            </tr>
            <tr>
                <th><?php _e('Max Execution Time', 'dynamic-translator'); ?></th>
                <td><?php echo esc_html(ini_get('max_execution_time')); ?>s</td>
            </tr>
            <tr>
                <th><?php _e('Enabled Languages', 'dynamic-translator'); ?></th>
                <td><?php echo esc_html(implode(', ', $enabled_languages)); ?></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render log traduzioni
     */
    private function render_translation_log() {
        $log = get_option('dpt_translation_log', array());
        
        if (empty($log)) {
            echo '<p>' . __('Nessun log disponibile. Abilita il logging nelle impostazioni avanzate.', 'dynamic-translator') . '</p>';
            return;
        }
        
        // Mostra solo le ultime 10 voci
        $recent_log = array_slice($log, -10);
        
        ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Timestamp', 'dynamic-translator'); ?></th>
                    <th><?php _e('Provider', 'dynamic-translator'); ?></th>
                    <th><?php _e('Lingue', 'dynamic-translator'); ?></th>
                    <th><?php _e('Caratteri', 'dynamic-translator'); ?></th>
                    <th><?php _e('Status', 'dynamic-translator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($recent_log) as $entry): ?>
                <tr>
                    <td><?php echo esc_html($entry['timestamp']); ?></td>
                    <td><?php echo esc_html($entry['provider']); ?></td>
                    <td><?php echo esc_html($entry['source_lang'] . ' → ' . $entry['target_lang']); ?></td>
                    <td><?php echo esc_html($entry['original_length']); ?></td>
                    <td>
                        <span class="dpt-status-ok">✓</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render test connettività
     */
    private function render_connectivity_tests() {
        $tests = array(
            'Google Translate' => 'https://translation.googleapis.com',
            'OpenRouter AI' => 'https://openrouter.ai',
            'DNS Resolution' => 'https://8.8.8.8',
            'HTTPS Support' => 'https://httpbin.org/get'
        );
        
        echo '<table class="wp-list-table widefat">';
        echo '<thead><tr><th>' . __('Servizio', 'dynamic-translator') . '</th><th>' . __('Status', 'dynamic-translator') . '</th><th>' . __('Tempo', 'dynamic-translator') . '</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($tests as $name => $url) {
            $start_time = microtime(true);
            $response = wp_remote_get($url, array('timeout' => 10));
            $end_time = microtime(true);
            $duration = round(($end_time - $start_time) * 1000, 2);
            
            $status_class = 'dpt-status-error';
            $status_text = '✗ Errore';
            
            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                if ($code >= 200 && $code < 400) {
                    $status_class = 'dpt-status-ok';
                    $status_text = '✓ OK';
                } elseif ($code >= 400 && $code < 500) {
                    $status_class = 'dpt-status-warning';
                    $status_text = '⚠ ' . $code;
                }
            }
            
            echo '<tr>';
            echo '<td>' . esc_html($name) . '</td>';
            echo '<td><span class="' . $status_class . '">' . $status_text . '</span></td>';
            echo '<td>' . $duration . 'ms</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    /**
     * AJAX diagnostica completa - CORREZIONE NONCE
     */
    public function ajax_run_diagnostic() {
        // CORREZIONE: Verifica nonce corretto
        if (!wp_verify_nonce($_POST['nonce'], 'dpt_debug_nonce')) {
            wp_send_json_error('Nonce non valido');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
            return;
        }
        
        $report = $this->run_full_diagnostic();
        
        wp_send_json_success(array('report' => $report));
    }
    
    /**
     * AJAX test OpenRouter dettagliato - CORREZIONE NONCE
     */
    public function ajax_test_openrouter_detailed() {
        // CORREZIONE: Verifica nonce corretto
        if (!wp_verify_nonce($_POST['nonce'], 'dpt_debug_nonce')) {
            wp_send_json_error('Nonce non valido');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
            return;
        }
        
        $api_key = sanitize_text_field($_POST['api_key']);
        $model = sanitize_text_field($_POST['model']);
        $test_text = sanitize_text_field($_POST['test_text']);
        
        if (empty($api_key)) {
            wp_send_json_error('API key richiesta');
            return;
        }
        
        $report = $this->test_openrouter_detailed($api_key, $model, $test_text);
        
        wp_send_json_success(array('report' => $report));
    }
    
    /**
     * Esegue diagnostica completa
     */
    private function run_full_diagnostic() {
        $report = "=== DIAGNOSTICA SISTEMA DYNAMIC PAGE TRANSLATOR ===\n";
        $report .= "Data: " . current_time('Y-m-d H:i:s') . "\n\n";
        
        // 1. Informazioni base
        $report .= "1. INFORMAZIONI BASE\n";
        $report .= "Plugin Version: " . DPT_VERSION . "\n";
        $report .= "WordPress: " . get_bloginfo('version') . "\n";
        $report .= "PHP: " . PHP_VERSION . "\n";
        $report .= "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Sconosciuto') . "\n\n";
        
        // 2. Configurazione
        $report .= "2. CONFIGURAZIONE\n";
        $report .= "Provider: " . dpt_get_option('translation_provider', 'non impostato') . "\n";
        $report .= "Lingue abilitate: " . implode(', ', dpt_get_option('enabled_languages', array())) . "\n";
        $report .= "Cache abilitata: " . (dpt_get_option('enable_cache', true) ? 'Sì' : 'No') . "\n";
        $report .= "HTML preservato: " . (dpt_get_option('preserve_html', true) ? 'Sì' : 'No') . "\n\n";
        
        // 3. Test estensioni PHP
        $report .= "3. ESTENSIONI PHP\n";
        $extensions = array('curl', 'json', 'mbstring', 'openssl');
        foreach ($extensions as $ext) {
            $status = extension_loaded($ext) ? '✓' : '✗';
            $report .= "{$ext}: {$status}\n";
        }
        $report .= "\n";
        
        // 4. Test connettività
        $report .= "4. TEST CONNETTIVITÀ\n";
        $urls = array(
            'Google Translate' => 'https://translation.googleapis.com',
            'OpenRouter' => 'https://openrouter.ai/api/v1/models'
        );
        
        foreach ($urls as $name => $url) {
            $start = microtime(true);
            $response = wp_remote_get($url, array('timeout' => 10));
            $duration = round((microtime(true) - $start) * 1000, 2);
            
            if (is_wp_error($response)) {
                $report .= "{$name}: ✗ ERRORE - " . $response->get_error_message() . "\n";
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $status = ($code >= 200 && $code < 400) ? '✓' : '⚠';
                $report .= "{$name}: {$status} HTTP {$code} ({$duration}ms)\n";
            }
        }
        $report .= "\n";
        
        // 5. Test API Keys
        $report .= "5. TEST API KEYS\n";
        $google_key = dpt_get_option('google_api_key', '');
        $openrouter_key = dpt_get_option('openrouter_api_key', '');
        
        $report .= "Google API Key: " . (empty($google_key) ? '✗ Non configurata' : '✓ Configurata (' . strlen($google_key) . ' caratteri)') . "\n";
        $report .= "OpenRouter API Key: " . (empty($openrouter_key) ? '✗ Non configurata' : '✓ Configurata (' . strlen($openrouter_key) . ' caratteri)') . "\n\n";
        
        // 6. Raccomandazioni
        $report .= "6. RACCOMANDAZIONI\n";
        $issues = array();
        
        if (!extension_loaded('curl')) {
            $issues[] = "- Installa estensione PHP cURL";
        }
        
        if (empty($google_key) && empty($openrouter_key)) {
            $issues[] = "- Configura almeno una API key";
        }
        
        if (!dpt_get_option('enable_cache', true)) {
            $issues[] = "- Abilita la cache per ridurre i costi";
        }
        
        if (empty($issues)) {
            $report .= "✓ Tutto sembra configurato correttamente!\n";
        } else {
            $report .= implode("\n", $issues) . "\n";
        }
        
        return $report;
    }
    
    /**
     * Test OpenRouter dettagliato
     */
    private function test_openrouter_detailed($api_key, $model, $test_text) {
        $report = "=== TEST OPENROUTER DETTAGLIATO ===\n";
        $report .= "Data: " . current_time('Y-m-d H:i:s') . "\n";
        $report .= "Modello: {$model}\n";
        $report .= "Testo test: {$test_text}\n\n";
        
        // 1. Validazione API key
        $report .= "1. VALIDAZIONE API KEY\n";
        if (empty($api_key)) {
            $report .= "✗ ERRORE: API key vuota\n\n";
            return $report;
        }
        
        if (strlen($api_key) < 20) {
            $report .= "⚠ WARNING: API key sembra troppo breve\n";
        } else {
            $report .= "✓ Lunghezza API key OK\n";
        }
        
        $report .= "Formato API key: " . substr($api_key, 0, 8) . "..." . substr($api_key, -4) . "\n\n";
        
        // 2. Test connettività base
        $report .= "2. TEST CONNETTIVITÀ\n";
        $start_time = microtime(true);
        $response = wp_remote_get('https://openrouter.ai/api/v1/models', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'HTTP-Referer' => get_site_url(),
                'X-Title' => get_bloginfo('name')
            )
        ));
        $duration = round((microtime(true) - $start_time) * 1000, 2);
        
        if (is_wp_error($response)) {
            $report .= "✗ ERRORE connessione: " . $response->get_error_message() . "\n\n";
            return $report;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $report .= "Response Code: {$response_code} ({$duration}ms)\n";
        
        if ($response_code === 401) {
            $report .= "✗ ERRORE: API key non valida\n\n";
            return $report;
        } elseif ($response_code === 403) {
            $report .= "✗ ERRORE: Accesso negato\n\n";
            return $report;
        } elseif ($response_code !== 200) {
            $report .= "⚠ WARNING: Response code inatteso\n";
        } else {
            $report .= "✓ Connessione OK\n";
        }
        $report .= "\n";
        
        // 3. Test traduzione
        $report .= "3. TEST TRADUZIONE\n";
        $request_data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => "Translate to Italian: {$test_text}"
                )
            ),
            'max_tokens' => 100,
            'temperature' => 0.1
        );
        
        $start_time = microtime(true);
        $translation_response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => get_site_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => wp_json_encode($request_data)
        ));
        $translation_duration = round((microtime(true) - $start_time) * 1000, 2);
        
        if (is_wp_error($translation_response)) {
            $report .= "✗ ERRORE traduzione: " . $translation_response->get_error_message() . "\n\n";
            return $report;
        }
        
        $translation_code = wp_remote_retrieve_response_code($translation_response);
        $translation_body = wp_remote_retrieve_body($translation_response);
        
        $report .= "Response Code: {$translation_code} ({$translation_duration}ms)\n";
        
        if ($translation_code !== 200) {
            $report .= "✗ ERRORE: HTTP {$translation_code}\n";
            $report .= "Response: " . substr($translation_body, 0, 500) . "\n\n";
            return $report;
        }
        
        $translation_data = json_decode($translation_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $report .= "✗ ERRORE: JSON non valido\n";
            $report .= "Response: " . substr($translation_body, 0, 500) . "\n\n";
            return $report;
        }
        
        if (isset($translation_data['error'])) {
            $report .= "✗ ERRORE API: " . $translation_data['error']['message'] . "\n\n";
            return $report;
        }
        
        if (!isset($translation_data['choices'][0]['message']['content'])) {
            $report .= "✗ ERRORE: Risposta incompleta\n";
            $report .= "Response: " . substr($translation_body, 0, 500) . "\n\n";
            return $report;
        }
        
        $translation = trim($translation_data['choices'][0]['message']['content']);
        $report .= "✓ Traduzione riuscita!\n";
        $report .= "Testo originale: {$test_text}\n";
        $report .= "Traduzione: {$translation}\n\n";
        
        // 4. Informazioni aggiuntive
        $report .= "4. INFORMAZIONI AGGIUNTIVE\n";
        if (isset($translation_data['usage'])) {
            $usage = $translation_data['usage'];
            $report .= "Token prompt: " . ($usage['prompt_tokens'] ?? 'N/A') . "\n";
            $report .= "Token completion: " . ($usage['completion_tokens'] ?? 'N/A') . "\n";
            $report .= "Token totali: " . ($usage['total_tokens'] ?? 'N/A') . "\n";
        }
        
        if (isset($translation_data['model'])) {
            $report .= "Modello utilizzato: " . $translation_data['model'] . "\n";
        }
        
        $report .= "\n✓ TEST COMPLETATO CON SUCCESSO!\n";
        
        return $report;
    }
}

// Inizializza debug helper
DPT_Debug_Helper::get_instance();