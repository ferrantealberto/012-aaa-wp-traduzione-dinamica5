/**
 * JavaScript Admin per Dynamic Page Translator - VERSIONE CORRETTA NONCE
 * File: assets/js/admin.js
 */

(function($) {
    'use strict';
    
    let adminConfig = {};
    let charts = {};
    
    /**
     * Inizializzazione admin
     */
    function init() {
        setupTabs();
        setupTestButtons();
        setupCacheManagement();
        setupModuleManagement();
        setupFlagPreview();
        setupCustomPositions();
        setupFileUpload();
        setupApiKeyToggles();
        setupFormValidation();
        setupCharts();
        setupAutoSave();
        setupDebugTools();
        
        // Event listeners globali
        $(document).on('click', '.dpt-test-button', handleTestClick);
        $(document).on('click', '.dpt-clear-cache', handleClearCache);
        $(document).on('change', 'input[name="dpt_flag_position"]', updateFlagPreview);
        $(document).on('change', 'select[name="dpt_flag_style"]', updateFlagPreview);
        
        // Setup per file input cache
        $('#import-cache-file').on('change', function() {
            const $importBtn = $('#import-cache');
            if (this.files.length > 0) {
                $importBtn.prop('disabled', false);
            } else {
                $importBtn.prop('disabled', true);
            }
        });
        
        // Inizializza tooltip
        setupTooltips();
        
        // Auto-refresh stats
        setupStatsAutoRefresh();
        
        // Aggiungi CSS personalizzato
        addCustomCSS();
    }
    
    /**
     * NUOVO: Ottiene nonce amministrativo corretto
     */
    function getAdminNonce() {
        return window.dptAdminNonce || $('#dpt_nonce').val() || '';
    }
    
    /**
     * NUOVO: Ottiene nonce debug corretto
     */
    function getDebugNonce() {
        return window.dptDebugNonce || $('#dpt_debug_nonce').val() || '';
    }
    
    /**
     * Setup tabs navigation
     */
    function setupTabs() {
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            const $tab = $(this);
            const targetTab = $tab.attr('href').split('tab=')[1];
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show/hide tab content
            $('.dpt-tab-content').hide();
            $('#dpt-tab-' + targetTab).show();
            
            // Update URL without reload
            if (history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.set('tab', targetTab);
                history.replaceState(null, '', url);
            }
            
            // Trigger tab change event
            $(document).trigger('dpt:tabChanged', targetTab);
        });
        
        // Load initial tab from URL
        const urlParams = new URLSearchParams(window.location.search);
        const currentTab = urlParams.get('tab') || 'general';
        $(`.nav-tab[href*="tab=${currentTab}"]`).click();
    }
    
    /**
     * Setup test buttons per API - VERSIONE CORRETTA NONCE
     */
    function setupTestButtons() {
        $(document).on('click', '#test-google-api, [data-provider="google"]', function() {
            testProvider('google', $(this));
        });
        
        $(document).on('click', '#test-openrouter-api, [data-provider="openrouter"]', function() {
            testProvider('openrouter', $(this));
        });
    }
    
    /**
     * Testa provider API - VERSIONE CORRETTA NONCE
     */
    function testProvider(provider, $button) {
        // Ottieni API key dal campo corrispondente
        let apiKey = '';
        if (provider === 'google') {
            apiKey = $('input[name="dpt_google_api_key"]').val();
        } else if (provider === 'openrouter') {
            apiKey = $('input[name="dpt_openrouter_api_key"]').val();
        }
        
        if (!apiKey.trim()) {
            showTestResult($button, 'error', 'Inserisci prima la API key');
            return;
        }
        
        // Validazione API key
        if (provider === 'openrouter' && apiKey.length < 20) {
            showTestResult($button, 'error', 'API key OpenRouter sembra troppo breve');
            return;
        }
        
        if (provider === 'google' && !apiKey.startsWith('AIza')) {
            showTestResult($button, 'warning', 'Le API key Google di solito iniziano con "AIza"');
        }
        
        $button.addClass('testing').prop('disabled', true);
        clearTestResult($button);
        
        // Mostra progress
        showTestProgress($button, 'Connessione in corso...');
        
        const requestData = {
            action: 'dpt_test_provider',
            provider: provider,
            nonce: getAdminNonce() // CORREZIONE: Usa nonce amministrativo
        };
        
        // Aggiungi API key al request per test diretto
        if (provider === 'google') {
            requestData.google_api_key = apiKey;
        } else if (provider === 'openrouter') {
            requestData.openrouter_api_key = apiKey;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: requestData,
            timeout: 45000, // Timeout aumentato a 45 secondi
            success: function(response) {
                console.log('Test response:', response);
                
                if (response.success) {
                    showTestResult($button, 'success', response.data);
                    
                    // Se OpenRouter, mostra info aggiuntive
                    if (provider === 'openrouter') {
                        showAdditionalInfo($button, 'API key valida e modello funzionante');
                    }
                } else {
                    showTestResult($button, 'error', response.data || 'Errore sconosciuto');
                    
                    // Suggerimenti specifici per errore
                    if (provider === 'openrouter') {
                        showTroubleshootingTips($button, response.data);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Test error:', {xhr, status, error});
                
                let errorMsg = 'Errore di connessione';
                
                if (status === 'timeout') {
                    errorMsg = 'Timeout - la richiesta ha impiegato troppo tempo. Controlla la connessione internet.';
                } else if (xhr.status === 0) {
                    errorMsg = 'Impossibile contattare il server. Controlla firewall e proxy.';
                } else if (xhr.status >= 500) {
                    errorMsg = 'Errore server interno. Riprova più tardi.';
                } else if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMsg = xhr.responseJSON.data;
                } else {
                    errorMsg = `Errore HTTP ${xhr.status}: ${error}`;
                }
                
                showTestResult($button, 'error', errorMsg);
                
                // Log dettagliato per debug
                console.log('Detailed error info:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    provider: provider
                });
            },
            complete: function() {
                $button.removeClass('testing').prop('disabled', false);
                hideTestProgress($button);
            }
        });
    }
    
    /**
     * Setup gestione cache - CORREZIONE NONCE
     */
    function setupCacheManagement() {
        $('#clear-expired-cache').on('click', function() {
            clearCache('expired', $(this));
        });
        
        $('#clear-all-cache').on('click', function() {
            if (confirm('Sei sicuro di voler eliminare tutta la cache delle traduzioni?')) {
                clearCache('all', $(this));
            }
        });
        
        $('#optimize-cache').on('click', function() {
            optimizeCache($(this));
        });
        
        $('#export-cache').on('click', function() {
            exportCache($(this));
        });
        
        $('#import-cache').on('click', function() {
            importCache();
        });
    }
    
    /**
     * Pulisce cache - CORREZIONE NONCE
     */
    function clearCache(type, $button) {
        const originalText = $button.text();
        $button.prop('disabled', true).text('Eliminazione...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dpt_clear_cache',
                type: type,
                nonce: getAdminNonce() // CORREZIONE: Usa nonce amministrativo
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data);
                    refreshCacheStats();
                } else {
                    showNotice('error', response.data);
                }
            },
            error: function() {
                showNotice('error', 'Errore durante l\'eliminazione della cache');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    /**
     * Esporta cache - CORREZIONE NONCE
     */
    function exportCache($button) {
        const originalText = $button.text();
        $button.prop('disabled', true).text('Esportazione...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dpt_export_cache',
                nonce: getAdminNonce() // CORREZIONE: Usa nonce amministrativo
            },
            success: function(response) {
                if (response.success) {
                    downloadJson(response.data.data, 'dpt-cache-export.json');
                    showNotice('success', 'Cache esportata con successo');
                } else {
                    showNotice('error', 'Errore durante l\'esportazione');
                }
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    /**
     * Setup strumenti debug - CORREZIONE NONCE
     */
    function setupDebugTools() {
        // Diagnostica completa
        $('#run-full-diagnostic').on('click', function() {
            const $button = $(this);
            const $results = $('#diagnostic-results');
            
            const originalText = $button.text();
            $button.prop('disabled', true).text('Esecuzione diagnostica...');
            $results.html('Esecuzione test di sistema...\n');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dpt_run_diagnostic',
                    nonce: getDebugNonce() // CORREZIONE: Usa nonce debug
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
                    console.error('Diagnostic error:', xhr.responseText);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Test OpenRouter dettagliato
        $('#test-openrouter-detailed').on('click', function() {
            runAdvancedOpenRouterTest();
        });
    }
    
    /**
     * Test diagnostico avanzato per OpenRouter - CORREZIONE NONCE
     */
    function runAdvancedOpenRouterTest() {
        const apiKey = $('#debug-openrouter-key').val() || $('input[name="dpt_openrouter_api_key"]').val();
        const model = $('#debug-openrouter-model').val() || $('select[name="dpt_openrouter_model"]').val();
        const testText = $('#debug-test-text').val() || 'Hello world';
        
        if (!apiKey) {
            alert('Inserisci prima l\'API key OpenRouter');
            return;
        }
        
        const $button = $('#test-openrouter-detailed');
        const $results = $('#openrouter-test-results');
        const originalText = $button.text();
        
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
                nonce: getDebugNonce() // CORREZIONE: Usa nonce debug
            },
            timeout: 60000,
            success: function(response) {
                if (response.success) {
                    $results.html('<pre>' + response.data.report + '</pre>');
                } else {
                    $results.html('<div style="color: red;">ERRORE: ' + (response.data || 'Errore sconosciuto') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $results.html('<div style="color: red;">ERRORE RICHIESTA: ' + status + ' - ' + error + '</div>');
                console.error('OpenRouter test error:', xhr.responseText);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    /**
     * Aggiorna anteprima bandiere - CORREZIONE NONCE
     */
    function updateFlagPreview() {
        const position = $('select[name="dpt_flag_position"]').val();
        const style = $('select[name="dpt_flag_style"]').val();
        
        const $preview = $('#dpt-flag-preview-container');
        $preview.addClass('loading').html('<p>Caricamento anteprima...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dpt_flag_preview',
                position: position,
                style: style,
                nonce: getAdminNonce() // CORREZIONE: Usa nonce amministrativo
            },
            success: function(response) {
                if (response.success) {
                    $preview.html(response.data.html);
                } else {
                    $preview.html('<p>Errore nel caricamento anteprima</p>');
                }
            },
            error: function() {
                $preview.html('<p>Errore nel caricamento anteprima</p>');
            },
            complete: function() {
                $preview.removeClass('loading');
            }
        });
    }
    
    /**
     * Setup gestione moduli
     */
    function setupModuleManagement() {
        $('.dpt-module-action').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const action = $button.data('action');
            const module = $button.data('module');
            
            if (action === 'delete' && !confirm('Sei sicuro di voler eliminare questo modulo?')) {
                return;
            }
            
            const originalText = $button.text();
            $button.prop('disabled', true).text('Elaborazione...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dpt_module_action',
                    module_action: action,
                    module: module,
                    nonce: getAdminNonce() // CORREZIONE: Usa nonce amministrativo
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotice('error', response.data);
                    }
                },
                error: function() {
                    showNotice('error', 'Errore durante l\'operazione');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    }
    
    /**
     * Setup anteprima bandiere
     */
    function setupFlagPreview() {
        updateFlagPreview(); // Initial load
    }
    
    /**
     * Setup posizioni personalizzate
     */
    function setupCustomPositions() {
        let positionCounter = 0;
        
        $('#add-custom-position').on('click', function() {
            addCustomPosition();
        });
        
        $(document).on('click', '.remove-position', function() {
            $(this).closest('.dpt-position-item').remove();
        });
        
        // Toggle custom positions visibility
        $('select[name="dpt_flag_position"]').on('change', function() {
            const $customRow = $('#custom-positions-row');
            if ($(this).val() === 'custom') {
                $customRow.show();
            } else {
                $customRow.hide();
            }
        });
        
        // Sortable positions
        if ($.fn.sortable) {
            $('#custom-positions-manager').sortable({
                handle: '.dpt-position-item',
                placeholder: 'dpt-position-placeholder',
                tolerance: 'pointer'
            });
        }
    }
    
    /**
     * Aggiunge posizione personalizzata
     */
    function addCustomPosition() {
        const $container = $('#custom-positions-manager');
        const $newItem = $(`
            <div class="dpt-position-item">
                <input type="text" name="dpt_flag_custom_positions[selector][]" placeholder="Selettore CSS" class="regular-text">
                <select name="dpt_flag_custom_positions[method][]">
                    <option value="append">Append</option>
                    <option value="prepend">Prepend</option>
                    <option value="after">After</option>
                    <option value="before">Before</option>
                </select>
                <button type="button" class="button remove-position">Rimuovi</button>
            </div>
        `);
        
        $container.append($newItem);
    }
    
    /**
     * Setup upload file
     */
    function setupFileUpload() {
        $('.dpt-upload-area').on('click', function() {
            $(this).find('input[type="file"]').click();
        });
        
        // Drag & drop
        $('.dpt-upload-area').on('dragover', function(e) {
            e.preventDefault();
            $(this).addClass('dragover');
        });
        
        $('.dpt-upload-area').on('dragleave', function(e) {
            e.preventDefault();
            $(this).removeClass('dragover');
        });
        
        $('.dpt-upload-area').on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('dragover');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                $(this).find('input[type="file"]')[0].files = files;
                $(this).find('input[type="file"]').trigger('change');
            }
        });
    }
    
    /**
     * Setup toggle API keys
     */
    function setupApiKeyToggles() {
        $('.dpt-api-key-toggle').on('click', function() {
            const $input = $(this).siblings('input[type="password"], input[type="text"]');
            const currentType = $input.attr('type');
            
            if (currentType === 'password') {
                $input.attr('type', 'text');
                $(this).html('🙈');
            } else {
                $input.attr('type', 'password');
                $(this).html('👁️');
            }
        });
    }
    
    /**
     * Setup validazione form
     */
    function setupFormValidation() {
        $('form').on('submit', function(e) {
            const $form = $(this);
            let hasErrors = false;
            
            // Valida API keys
            $form.find('input[name*="_api_key"]').each(function() {
                const $input = $(this);
                const value = $input.val().trim();
                
                if (value && value.length < 10) {
                    showFieldError($input, 'API key troppo breve');
                    hasErrors = true;
                } else {
                    clearFieldError($input);
                }
            });
            
            // Valida numeri
            $form.find('input[type="number"]').each(function() {
                const $input = $(this);
                const value = parseInt($input.val());
                const min = parseInt($input.attr('min'));
                const max = parseInt($input.attr('max'));
                
                if (min && value < min) {
                    showFieldError($input, `Valore minimo: ${min}`);
                    hasErrors = true;
                } else if (max && value > max) {
                    showFieldError($input, `Valore massimo: ${max}`);
                    hasErrors = true;
                } else {
                    clearFieldError($input);
                }
            });
            
            if (hasErrors) {
                e.preventDefault();
                showNotice('error', 'Correggi gli errori nel form prima di salvare');
            }
        });
    }
    
    /**
     * Setup charts
     */
    function setupCharts() {
        if (typeof Chart === 'undefined') {
            return;
        }
        
        // Chart provider usage
        const $providersChart = $('#providers-chart');
        if ($providersChart.length) {
            loadProvidersChart($providersChart[0]);
        }
        
        // Chart lingue
        const $languagesChart = $('#languages-chart');
        if ($languagesChart.length) {
            loadLanguagesChart($languagesChart[0]);
        }
    }
    
    /**
     * Carica chart provider
     */
    function loadProvidersChart(canvas) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dpt_get_providers_stats',
                nonce: getAdminNonce()
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    charts.providers = new Chart(canvas, {
                        type: 'doughnut',
                        data: {
                            labels: Object.keys(data),
                            datasets: [{
                                data: Object.values(data),
                                backgroundColor: [
                                    '#007cba',
                                    '#28a745',
                                    '#ffc107',
                                    '#dc3545'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
            }
        });
    }
    
    /**
     * Carica chart lingue
     */
    function loadLanguagesChart(canvas) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dpt_get_languages_stats',
                nonce: getAdminNonce()
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    charts.languages = new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: Object.keys(data),
                            datasets: [{
                                label: 'Traduzioni',
                                data: Object.values(data),
                                backgroundColor: '#007cba'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            }
        });
    }
    
    /**
     * Setup auto-save
     */
    function setupAutoSave() {
        let saveTimer;
        
        $('input, select, textarea').on('input change', function() {
            const $field = $(this);
            
            // Skip per alcuni campi
            if ($field.hasClass('no-autosave') || $field.attr('type') === 'file') {
                return;
            }
            
            clearTimeout(saveTimer);
            saveTimer = setTimeout(() => {
                autoSaveField($field);
            }, 2000);
        });
    }
    
    /**
     * Auto-save singolo campo
     */
    function autoSaveField($field) {
        const fieldName = $field.attr('name');
        const fieldValue = $field.val();
        
        if (!fieldName || !fieldValue) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dpt_autosave_field',
                field_name: fieldName,
                field_value: fieldValue,
                nonce: getAdminNonce()
            },
            success: function(response) {
                if (response.success) {
                    showAutoSaveIndicator($field, 'saved');
                } else {
                    showAutoSaveIndicator($field, 'error');
                }
            },
            error: function() {
                showAutoSaveIndicator($field, 'error');
            }
        });
    }
    
    /**
     * Setup tooltips
     */
    function setupTooltips() {
        $('[data-tooltip]').hover(
            function() {
                const tooltip = $(this).data('tooltip');
                showTooltip($(this), tooltip);
            },
            function() {
                hideTooltip();
            }
        );
    }
    
    /**
     * Setup auto-refresh stats
     */
    function setupStatsAutoRefresh() {
        if (window.location.href.includes('stats')) {
            setInterval(() => {
                refreshStats();
            }, 30000); // Ogni 30 secondi
        }
    }
    
    /**
     * Refresh statistiche
     */
    function refreshStats() {
        $('.dpt-stat-number').each(function() {
            const $stat = $(this);
            const statType = $stat.data('stat');
            
            if (statType) {
                updateStat($stat, statType);
            }
        });
    }
    
    /**
     * Aggiorna singola statistica
     */
    function updateStat($element, statType) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dpt_get_stat',
                stat_type: statType,
                nonce: getAdminNonce()
            },
            success: function(response) {
                if (response.success) {
                    animateNumber($element, response.data);
                }
            }
        });
    }
    
    /**
     * Gestisce click test generico
     */
    function handleTestClick(e) {
        e.preventDefault();
        const $button = $(this);
        const provider = $button.data('provider');
        
        if (provider) {
            testProvider(provider, $button);
        }
    }
    
    /**
     * Gestisce clear cache generico
     */
    function handleClearCache(e) {
        e.preventDefault();
        const $button = $(this);
        const cacheType = $button.data('cache-type');
        
        if (cacheType) {
            clearCache(cacheType, $button);
        }
    }
    
    /**
     * Refresh statistiche cache
     */
    function refreshCacheStats() {
        // Ricarica la pagina per aggiornare le statistiche
        // In futuro si potrebbe implementare un refresh AJAX
        setTimeout(() => {
            if (window.location.href.includes('cache')) {
                location.reload();
            }
        }, 1000);
    }
    
    /**
     * Mostra progress durante test
     */
    function showTestProgress($button, message) {
        clearTestResult($button);
        const $progress = $(`<div class="dpt-test-progress">${message}</div>`);
        $button.after($progress);
    }
    
    /**
     * Nasconde progress
     */
    function hideTestProgress($button) {
        $button.siblings('.dpt-test-progress').remove();
    }
    
    /**
     * Mostra informazioni aggiuntive
     */
    function showAdditionalInfo($button, info) {
        const $info = $(`<div class="dpt-test-info">${info}</div>`);
        $button.siblings('.dpt-test-result').after($info);
        
        setTimeout(() => {
            $info.fadeOut(() => $info.remove());
        }, 10000);
    }
    
    /**
     * Mostra suggerimenti troubleshooting
     */
    function showTroubleshootingTips($button, errorMessage) {
        let tips = '';
        
        if (errorMessage.includes('API key')) {
            tips = 'Verifica che l\'API key sia corretta e abbia i permessi necessari.';
        } else if (errorMessage.includes('connessione')) {
            tips = 'Controlla la connessione internet e i firewall.';
        } else if (errorMessage.includes('timeout')) {
            tips = 'Il server potrebbe essere sovraccarico. Riprova tra qualche minuto.';
        } else if (errorMessage.includes('rate limit')) {
            tips = 'Hai raggiunto il limite di richieste. Attendi prima di riprovare.';
        } else {
            tips = 'Prova a usare la diagnostica avanzata nel menu Debug.';
        }
        
        if (tips) {
            const $tips = $(`<div class="dpt-test-tips"><strong>Suggerimento:</strong> ${tips}</div>`);
            $button.siblings('.dpt-test-result').after($tips);
            
            setTimeout(() => {
                $tips.fadeOut(() => $tips.remove());
            }, 15000);
        }
    }
    
    /**
     * Mostra risultato test
     */
    function showTestResult($button, type, message) {
        clearTestResult($button);
        
        // Icone per i diversi tipi di risultato
        let icon = '';
        if (type === 'success') {
            icon = '✅ ';
        } else if (type === 'error') {
            icon = '❌ ';
        } else if (type === 'warning') {
            icon = '⚠️ ';
        }
        
        const $result = $(`<div class="dpt-test-result ${type}">${icon}${message}</div>`);
        $button.after($result);
        
        // Auto-hide dopo tempo variabile basato sul tipo
        const hideDelay = type === 'error' ? 10000 : 5000;
        setTimeout(() => {
            $result.fadeOut(() => $result.remove());
        }, hideDelay);
    }
    
    /**
     * Pulisce risultato test
     */
    function clearTestResult($button) {
        $button.siblings('.dpt-test-result, .dpt-test-info, .dpt-test-tips, .dpt-test-progress').remove();
    }
    
    /**
     * Aggiunge CSS personalizzato
     */
    function addCustomCSS() {
        const css = `
            <style>
                .dpt-test-progress {
                    color: #0073aa;
                    font-style: italic;
                    margin-top: 5px;
                    padding: 4px 8px;
                    background: #f0f8ff;
                    border-left: 3px solid #0073aa;
                    border-radius: 2px;
                }
                
                .dpt-test-info {
                    color: #0073aa;
                    font-size: 12px;
                    margin-top: 5px;
                    padding: 4px 8px;
                    background: #f0f8ff;
                    border-radius: 2px;
                }
                
                .dpt-test-tips {
                    color: #856404;
                    font-size: 12px;
                    margin-top: 5px;
                    padding: 6px 10px;
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 3px;
                }
                
                .dpt-test-result.warning {
                    background: #fff3cd;
                    color: #856404;
                    border: 1px solid #ffeaa7;
                }
                
                .dpt-test-result.success {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                    padding: 8px 12px;
                    border-radius: 4px;
                    margin-top: 10px;
                    font-size: 13px;
                }
                
                .dpt-test-result.error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                    padding: 8px 12px;
                    border-radius: 4px;
                    margin-top: 10px;
                    font-size: 13px;
                }
            </style>
        `;
        
        $('head').append(css);
    }
    
    /**
     * Utility functions
     */
    function showNotice(type, message) {
        const $notice = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
        $('.wrap').first().prepend($notice);
        
        setTimeout(() => {
            $notice.fadeOut(() => $notice.remove());
        }, 5000);
    }
    
    function showFieldError($field, message) {
        clearFieldError($field);
        
        const $error = $(`<div class="dpt-field-error">${message}</div>`);
        $field.addClass('error').after($error);
    }
    
    function clearFieldError($field) {
        $field.removeClass('error').siblings('.dpt-field-error').remove();
    }
    
    function showAutoSaveIndicator($field, status) {
        const $indicator = $field.siblings('.dpt-autosave-indicator');
        
        if ($indicator.length === 0) {
            const $newIndicator = $('<span class="dpt-autosave-indicator"></span>');
            $field.after($newIndicator);
        }
        
        const $finalIndicator = $field.siblings('.dpt-autosave-indicator');
        
        $finalIndicator.removeClass('saved error')
                      .addClass(status)
                      .text(status === 'saved' ? '✓' : '✗');
        
        setTimeout(() => {
            $finalIndicator.fadeOut();
        }, 2000);
    }
    
    function downloadJson(data, filename) {
        const blob = new Blob([data], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    
    function animateNumber($element, newValue) {
        const currentValue = parseInt($element.text().replace(/,/g, '')) || 0;
        const diff = newValue - currentValue;
        const steps = 30;
        const increment = diff / steps;
        let step = 0;
        
        const timer = setInterval(() => {
            step++;
            const value = Math.round(currentValue + (increment * step));
            $element.text(value.toLocaleString());
            
            if (step >= steps) {
                clearInterval(timer);
                $element.text(newValue.toLocaleString());
            }
        }, 50);
    }
    
    function showTooltip($element, text) {
        const $tooltip = $('<div class="dpt-admin-tooltip">' + text + '</div>');
        $('body').append($tooltip);
        
        const offset = $element.offset();
        $tooltip.css({
            top: offset.top - $tooltip.outerHeight() - 5,
            left: offset.left + ($element.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
        });
        
        setTimeout(() => {
            $tooltip.remove();
        }, 3000);
    }
    
    function hideTooltip() {
        $('.dpt-admin-tooltip').remove();
    }
    
    // Funzioni globali per uso da HTML inline
    window.toggleApiKey = function(inputId) {
        const input = document.getElementById(inputId);
        const button = input.nextElementSibling;
        if (input.type === 'password') {
            input.type = 'text';
            button.textContent = '🙈';
        } else {
            input.type = 'password';
            button.textContent = '👁️';
        }
    };
    
    window.addCustomPosition = function() {
        addCustomPosition();
    };
    
    window.removePosition = function(button) {
        button.closest('.dpt-position-item').remove();
    };
    
    window.toggleCustomPositions = function(value) {
        const customRow = document.getElementById('custom-positions-row');
        if (customRow) {
            customRow.style.display = value === 'custom' ? 'table-row' : 'none';
        }
    };
    
    window.runAdvancedOpenRouterTest = function() {
        runAdvancedOpenRouterTest();
    };
    
    // Inizializza quando il documento è pronto
    $(document).ready(init);
    
    // Espone API per uso esterno
    window.DPTAdmin = {
        showNotice: showNotice,
        refreshStats: refreshStats,
        updateFlagPreview: updateFlagPreview,
        testProvider: testProvider,
        clearCache: clearCache
    };
    
})(jQuery);