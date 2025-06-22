/**
 * JavaScript Frontend per Dynamic Page Translator - VERSIONE CORRETTA CAMBIO LINGUA
 * File: assets/js/frontend.js
 */

(function($) {
    'use strict';
    
    // Variabili globali
    let currentLanguage = dptFrontend.currentLang;
    let translationQueue = [];
    let isTranslating = false;
    let translationTimeout;
    let isLanguageChanging = false; // NUOVO: Flag per evitare doppi cambi
    
    /**
     * Inizializzazione del plugin
     */
    function init() {
        setupLanguageSwitcher();
        setupDynamicTranslation();
        setupCustomPositions();
        setupKeyboardNavigation();
        setupAccessibility();
        
        // Event listeners
        $(document).on('click', '.dpt-lang-option', handleLanguageChange);
        $(document).on('click', '.dpt-lang-link', handleLanguageChange);
        $(document).on('click', '.dpt-lang-card', handleLanguageChange);
        
        // Inizializza bandiere se presenti
        if (typeof dptFlags !== 'undefined') {
            initFlags();
        }
        
        // Processa traduzioni dinamiche se presenti
        if (typeof dptDynamicTranslations !== 'undefined') {
            processDynamicTranslations();
        }
        
        console.log('DPT: Frontend inizializzato, lingua corrente:', currentLanguage);
    }
    
    /**
     * CORREZIONE PRINCIPALE: Gestisce il cambio di lingua migliorato
     */
    function handleLanguageChange(e) {
        e.preventDefault();
        
        // NUOVO: Evita doppi click durante il cambio
        if (isLanguageChanging) {
            console.log('DPT: Cambio lingua già in corso, ignoro click');
            return;
        }
        
        const $clickedElement = $(this);
        const newLanguage = $clickedElement.data('lang');
        
        // Validazione migliorata
        if (!newLanguage) {
            console.error('DPT: Lingua non specificata nell\'elemento cliccato');
            return;
        }
        
        if (newLanguage === currentLanguage) {
            console.log('DPT: Lingua già selezionata:', newLanguage);
            closeAllPopups();
            return;
        }
        
        console.log('DPT: Iniziando cambio lingua da', currentLanguage, 'a', newLanguage);
        
        // Imposta flag cambio lingua
        isLanguageChanging = true;
        
        // CORREZIONE: Aggiorna immediatamente il selettore visivo
        updateLanguageSwitcherImmediate(newLanguage);
        
        // Mostra loading
        showLoadingState();
        showTranslationIndicator('Cambio lingua in corso...');
        
        // Chiudi immediatamente tutti i popup
        closeAllPopups();
        
        // Timeout di sicurezza
        if (translationTimeout) {
            clearTimeout(translationTimeout);
        }
        
        translationTimeout = setTimeout(function() {
            console.log('DPT: Timeout cambio lingua raggiunto');
            isLanguageChanging = false;
            hideLoadingState();
            hideTranslationIndicator();
            showError('Timeout: il cambio lingua sta impiegando troppo tempo');
        }, 30000);
        
        // Invia richiesta AJAX
        $.ajax({
            url: dptFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dpt_change_language',
                language: newLanguage,
                nonce: dptFrontend.nonce
            },
            timeout: 25000,
            success: function(response) {
                console.log('DPT: Risposta cambio lingua:', response);
                
                if (response.success) {
                    // CORREZIONE: Aggiorna lingua corrente globale
                    const oldLanguage = currentLanguage;
                    currentLanguage = newLanguage;
                    
                    // CORREZIONE: Aggiorna cookie immediatamente lato client
                    document.cookie = `dpt_current_lang=${newLanguage}; path=/; max-age=${30 * 24 * 60 * 60}; SameSite=Lax`;
                    
                    // Aggiorna meta tag
                    $('meta[name="dpt-current-language"]').attr('content', newLanguage);
                    
                    // CORREZIONE: Aggiorna definitivamente il selettore
                    updateLanguageSwitcherFinal(newLanguage);
                    
                    // Event personalizzato
                    $(document).trigger('dpt:languageChanged', {
                        newLanguage: newLanguage,
                        oldLanguage: oldLanguage
                    });
                    
                    // CORREZIONE: Gestione traduzione migliorata
                    if (shouldTranslatePage(newLanguage, oldLanguage)) {
                        console.log('DPT: Avvio traduzione pagina per lingua:', newLanguage);
                        showTranslationIndicator('Traduzione in corso...');
                        
                        setTimeout(function() {
                            translatePageContent(newLanguage);
                        }, 200);
                    } else {
                        console.log('DPT: Traduzione non necessaria');
                        console.log('  - newLanguage:', newLanguage);
                        console.log('  - defaultLang:', dptFrontend.defaultLang);
                        console.log('  - autoTranslate:', dptFrontend.autoTranslate);
                        
                        // CORREZIONE: Se la lingua non è quella di default, forza la traduzione
                        if (newLanguage !== dptFrontend.defaultLang && dptFrontend.autoTranslate !== false) {
                            console.log('DPT: Forzando traduzione per lingua non-default');
                            showTranslationIndicator('Traduzione forzata in corso...');
                            setTimeout(function() {
                                translatePageContent(newLanguage);
                            }, 200);
                        } else {
                            hideLoadingState();
                            hideTranslationIndicator();
                            showSuccess('Lingua cambiata in ' + getLanguageName(newLanguage));
                        }
                    }
                    
                    // Ricarica pagina se richiesto
                    if (response.data && response.data.reload) {
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }
                    
                } else {
                    console.error('DPT: Errore cambio lingua:', response.data);
                    // CORREZIONE: Ripristina lingua precedente in caso di errore
                    updateLanguageSwitcherImmediate(currentLanguage);
                    hideLoadingState();
                    hideTranslationIndicator();
                    showError(response.data.message || dptFrontend.strings.translationError);
                }
            },
            error: function(xhr, status, error) {
                console.error('DPT: Errore AJAX cambio lingua:', {xhr, status, error});
                
                // CORREZIONE: Ripristina stato precedente
                updateLanguageSwitcherImmediate(currentLanguage);
                hideLoadingState();
                hideTranslationIndicator();
                
                let errorMsg = dptFrontend.strings.translationError;
                if (status === 'timeout') {
                    errorMsg = 'Timeout: controlla la connessione internet';
                } else if (xhr.status === 0) {
                    errorMsg = 'Impossibile contattare il server';
                }
                
                showError(errorMsg);
            },
            complete: function() {
                // CORREZIONE: Reset flag sempre
                isLanguageChanging = false;
                
                // Pulisci timeout
                if (translationTimeout) {
                    clearTimeout(translationTimeout);
                    translationTimeout = null;
                }
            }
        });
    }
    
    /**
     * CORREZIONE: Determina se la pagina deve essere tradotta
     */
    function shouldTranslatePage(newLanguage, oldLanguage) {
        console.log('DPT: Controllo se tradurre pagina:');
        console.log('  - newLanguage:', newLanguage);
        console.log('  - oldLanguage:', oldLanguage);
        console.log('  - defaultLang:', dptFrontend.defaultLang);
        console.log('  - autoTranslate:', dptFrontend.autoTranslate);
        
        // Non tradurre se auto-translate è disabilitato
        if (!dptFrontend.autoTranslate) {
            console.log('  → Auto-translate disabilitato');
            return false;
        }
        
        // Non tradurre se è la lingua di default
        if (newLanguage === dptFrontend.defaultLang) {
            console.log('  → È lingua di default, non traduco');
            return false;
        }
        
        // CORREZIONE: Traduci sempre quando la nuova lingua non è quella di default
        console.log('  → Traduzione necessaria!');
        return true;
    }
    
    /**
     * NUOVO: Aggiorna switcher immediatamente per feedback visivo
     */
    function updateLanguageSwitcherImmediate(newLanguage) {
        console.log('DPT: Aggiornamento immediato switcher per lingua:', newLanguage);
        
        // Rimuovi stato attivo da tutti gli elementi
        $('.dpt-lang-option, .dpt-lang-card, .dpt-lang-link').removeClass('active').removeAttr('aria-current');
        
        // Aggiungi stato attivo al nuovo elemento
        $(`.dpt-lang-option[data-lang="${newLanguage}"], .dpt-lang-card[data-lang="${newLanguage}"], .dpt-lang-link[data-lang="${newLanguage}"]`)
            .addClass('active')
            .attr('aria-current', 'page');
        
        // Aggiorna trigger dropdown se presente
        const $trigger = $('.dpt-dropdown-trigger, .dpt-popup-trigger');
        if ($trigger.length) {
            updateTriggerDisplay($trigger, newLanguage);
        }
        
        // Aggiorna minimal se presente
        const $minimal = $('.dpt-minimal-current');
        if ($minimal.length) {
            $minimal.text(newLanguage.toUpperCase());
        }
    }
    
    /**
     * NUOVO: Aggiorna display del trigger con bandiera e nome
     */
    function updateTriggerDisplay($trigger, newLanguage) {
        // Trova la bandiera per la nuova lingua
        const $newOption = $(`.dpt-lang-option[data-lang="${newLanguage}"]`).first();
        
        if ($newOption.length) {
            const $newFlag = $newOption.find('.dpt-flag').clone();
            const newLabel = $newOption.find('.dpt-lang-label').text() || getLanguageName(newLanguage);
            
            // Aggiorna bandiera nel trigger
            const $currentFlag = $trigger.find('.dpt-flag');
            if ($currentFlag.length && $newFlag.length) {
                $currentFlag.replaceWith($newFlag);
            }
            
            // Aggiorna label nel trigger
            const $currentLabel = $trigger.find('.dpt-lang-label, .dpt-lang-name');
            if ($currentLabel.length && newLabel) {
                $currentLabel.text(newLabel);
            }
        }
    }
    
    /**
     * NUOVO: Aggiornamento finale del switcher dopo successo
     */
    function updateLanguageSwitcherFinal(newLanguage) {
        // Conferma l'aggiornamento visivo
        updateLanguageSwitcherImmediate(newLanguage);
        
        // Aggiorna eventuali elementi nascosti
        $('input[name="current_language"]').val(newLanguage);
        
        console.log('DPT: Switcher aggiornato definitivamente per lingua:', newLanguage);
    }
    
    /**
     * NUOVO: Ottiene nome lingua leggibile
     */
    function getLanguageName(langCode) {
        const names = {
            'en': 'English',
            'it': 'Italiano', 
            'es': 'Español',
            'fr': 'Français',
            'de': 'Deutsch',
            'pt': 'Português',
            'ru': 'Русский',
            'zh': '中文',
            'ja': '日本語',
            'ar': 'العربية'
        };
        
        return names[langCode] || langCode;
    }
    
    /**
     * CORREZIONE: Indicatore di traduzione migliorato
     */
    function showTranslationIndicator(message) {
        // Rimuovi indicatori precedenti
        $('.dpt-translation-indicator').remove();
        
        const $indicator = $(`
            <div class="dpt-translation-indicator">
                ${message}
            </div>
        `);
        
        $('body').append($indicator);
        
        // Auto-hide dopo 10 secondi
        setTimeout(() => {
            hideTranslationIndicator();
        }, 10000);
    }
    
    /**
     * NUOVO: Nasconde indicatore traduzione
     */
    function hideTranslationIndicator() {
        $('.dpt-translation-indicator').fadeOut(300, function() {
            $(this).remove();
        });
    }
    
    /**
     * NUOVO: Mostra notifica di successo
     */
    function showSuccess(message) {
        $('.dpt-success-notification, .dpt-error-notification').remove();
        
        const $success = $(`<div class="dpt-success-notification">${message}</div>`);
        $('body').append($success);
        
        setTimeout(() => {
            $success.fadeOut(() => $success.remove());
        }, 3000);
    }
    
    /**
     * CORREZIONE: Traduce contenuto pagina con selettori migliorati
     */
    function translatePageContent(targetLang) {
        console.log('DPT: Avvio traduzione contenuto pagina per lingua:', targetLang);
        
        // CORREZIONE: Selettori più ampi per catturare tutto il contenuto
        const textElements = $('h1, h2, h3, h4, h5, h6, p, li, td, th, span, div, a')
            .not('.dpt-language-switcher, .dpt-flag-switcher, .dpt-popup-overlay, .dpt-sidebar-panel, .dpt-translation-indicator, .dpt-success-notification, .dpt-error-notification, .dpt-no-translate')
            .filter(function() {
                const $this = $(this);
                const text = $this.text().trim();
                
                // CORREZIONE: Filtri più permissivi
                return text.length > 3 && 
                       text.length < 500 && 
                       $this.children().length === 0 && // Solo elementi senza figli
                       !/^\d+$/.test(text) && // Non solo numeri
                       !/^[^\w\s]*$/.test(text) && // Non solo simboli
                       !$this.is('script, style, noscript') && // Non script/style
                       !$this.hasClass('dpt-no-translate'); // Non elementi marcati
            })
            .slice(0, 50); // Aumentato limite a 50 elementi
        
        let elementsToTranslate = 0;
        let elementsTranslated = 0;
        
        console.log('DPT: Elementi candidati trovati:', textElements.length);
        
        textElements.each(function() {
            const $element = $(this);
            const text = $element.text().trim();
            
            console.log('DPT: Elemento da tradurre:', text.substring(0, 50) + '...');
            
            elementsToTranslate++;
            
            // Marca elemento come in traduzione
            $element.addClass('dpt-translating');
            
            // Aggiunge alla coda
            queueTranslation(null, {
                element: this,
                content: text,
                source_lang: dptFrontend.defaultLang,
                target_lang: targetLang,
                callback: function() {
                    elementsTranslated++;
                    $element.removeClass('dpt-translating');
                    
                    console.log('DPT: Elemento tradotto', elementsTranslated, 'di', elementsToTranslate);
                    
                    // Se tutti gli elementi sono stati processati
                    if (elementsTranslated >= elementsToTranslate) {
                        hideLoadingState();
                        hideTranslationIndicator();
                        showSuccess('Traduzione completata (' + elementsTranslated + ' elementi)');
                        console.log('DPT: Traduzione pagina completata');
                    }
                }
            });
        });
        
        console.log('DPT: Elementi da tradurre:', elementsToTranslate);
        
        if (elementsToTranslate === 0) {
            console.log('DPT: Nessun elemento da tradurre trovato');
            hideLoadingState();
            hideTranslationIndicator();
            
            // CORREZIONE: Messaggio più informativo
            showError('Nessun contenuto testuale trovato da tradurre su questa pagina');
            return;
        }
        
        // Aggiorna indicatore con progresso
        showTranslationIndicator(`Traduzione di ${elementsToTranslate} elementi...`);
        
        // Avvia processamento coda
        processTranslationQueue();
    }
    
    /**
     * CORREZIONE: Aggiunge elemento alla coda con callback
     */
    function queueTranslation($element, translationData) {
        if (translationData) {
            translationQueue.push(translationData);
        } else if ($element) {
            const textNodes = getTextNodes($element);
            textNodes.forEach(function(node) {
                const text = node.textContent.trim();
                if (text.length > 3) {
                    translationQueue.push({
                        element: node,
                        content: text,
                        source_lang: dptFrontend.defaultLang,
                        target_lang: currentLanguage
                    });
                }
            });
        }
    }
    
    /**
     * CORREZIONE: Processa coda con gestione callback
     */
    function processTranslationQueue() {
        if (isTranslating || translationQueue.length === 0) {
            return;
        }
        
        isTranslating = true;
        console.log('DPT: Processo coda traduzioni, elementi:', translationQueue.length);
        
        const batchSize = 2; // Ridotto per stabilità
        const batch = translationQueue.splice(0, batchSize);
        
        let completedBatch = 0;
        const totalBatch = batch.length;
        
        function checkBatchComplete() {
            completedBatch++;
            if (completedBatch >= totalBatch) {
                isTranslating = false;
                setTimeout(() => processTranslationQueue(), 300);
            }
        }
        
        batch.forEach(function(item) {
            translateElement(item, checkBatchComplete);
        });
    }
    
    /**
     * CORREZIONE: Traduce elemento con callback
     */
    function translateElement(item, callback) {
        $.ajax({
            url: dptFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dpt_translate_element',
                content: item.content,
                source_lang: item.source_lang,
                target_lang: item.target_lang,
                cache_key: item.cache_key || generateCacheKey(item.content, item.source_lang, item.target_lang),
                nonce: dptFrontend.nonce
            },
            timeout: 15000,
            success: function(response) {
                if (response.success && response.data.translation) {
                    if (item.element) {
                        // Aggiorna nodo di testo
                        if (item.element.nodeType === 3) {
                            item.element.textContent = response.data.translation;
                        } else {
                            $(item.element).text(response.data.translation);
                        }
                    }
                }
            },
            error: function(xhr, status, error) {
                console.warn('DPT: Errore traduzione elemento:', error);
            },
            complete: function() {
                // Esegui callback se presente
                if (item.callback) {
                    item.callback();
                }
                if (callback) {
                    callback();
                }
            }
        });
    }
    
    /**
     * Setup language switcher base
     */
    function setupLanguageSwitcher() {
        const $switcher = $('#dpt-language-switcher');
        if (!$switcher.length) return;
        
        // Setup dropdown
        setupDropdown($switcher);
        setupPopup($switcher);
        setupSidebarSlide($switcher);
        setupCircleMenu($switcher);
        setupMinimal($switcher);
        
        // Mostra switcher se era nascosto
        $switcher.show();
    }
    
    /**
     * Setup dropdown functionality
     */
    function setupDropdown($container) {
        const $dropdown = $container.find('.dpt-dropdown-container');
        if (!$dropdown.length) return;
        
        const $trigger = $dropdown.find('.dpt-dropdown-trigger');
        const $menu = $dropdown.find('.dpt-dropdown-menu');
        
        $trigger.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isOpen = $menu.is(':visible');
            
            // Chiudi tutti gli altri dropdown
            $('.dpt-dropdown-menu').hide();
            $('.dpt-dropdown-trigger').attr('aria-expanded', 'false');
            
            if (!isOpen) {
                $menu.show();
                $trigger.attr('aria-expanded', 'true');
                $menu.find('.dpt-lang-option').first().focus();
            }
        });
        
        // Chiudi dropdown cliccando fuori
        $(document).on('click', function(e) {
            if (!$dropdown.is(e.target) && $dropdown.has(e.target).length === 0) {
                $menu.hide();
                $trigger.attr('aria-expanded', 'false');
            }
        });
        
        // Navigazione tastiera
        $menu.on('keydown', '.dpt-lang-option', function(e) {
            const $options = $menu.find('.dpt-lang-option');
            const currentIndex = $options.index(this);
            
            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    const nextIndex = (currentIndex + 1) % $options.length;
                    $options.eq(nextIndex).focus();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    const prevIndex = currentIndex === 0 ? $options.length - 1 : currentIndex - 1;
                    $options.eq(prevIndex).focus();
                    break;
                case 'Escape':
                    $menu.hide();
                    $trigger.attr('aria-expanded', 'false').focus();
                    break;
            }
        });
    }
    
    /**
     * Setup popup functionality
     */
    function setupPopup($container) {
        const $trigger = $container.find('.dpt-popup-trigger');
        const $overlay = $container.find('.dpt-popup-overlay');
        const $close = $container.find('.dpt-popup-close');
        
        if (!$trigger.length || !$overlay.length) return;
        
        $trigger.on('click', function(e) {
            e.preventDefault();
            $overlay.fadeIn(200);
            $('body').addClass('dpt-popup-open');
            $overlay.find('.dpt-lang-card').first().focus();
        });
        
        $close.on('click', closePopup);
        $overlay.on('click', function(e) {
            if (e.target === this) {
                closePopup();
            }
        });
        
        function closePopup() {
            $overlay.fadeOut(200);
            $('body').removeClass('dpt-popup-open');
            $trigger.focus();
        }
        
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $overlay.is(':visible')) {
                closePopup();
            }
        });
    }
    
    // Altri setup functions...
    function setupSidebarSlide($container) {
        // Implementazione semplificata
    }
    
    function setupCircleMenu($container) {
        // Implementazione semplificata  
    }
    
    function setupMinimal($container) {
        const $trigger = $container.find('.dpt-minimal-current');
        const $options = $container.find('.dpt-minimal-options');
        
        if (!$trigger.length || !$options.length) return;
        
        $trigger.on('click', function(e) {
            e.preventDefault();
            $options.toggle();
        });
        
        $(document).on('click', function(e) {
            if (!$container.is(e.target) && $container.has(e.target).length === 0) {
                $options.hide();
            }
        });
    }
    
    /**
     * NUOVA FUNZIONE: Chiude tutti i popup
     */
    function closeAllPopups() {
        $('.dpt-popup-overlay').fadeOut(200);
        $('body').removeClass('dpt-popup-open');
        
        $('.dpt-sidebar-overlay').fadeOut(200);
        $('.dpt-sidebar-panel').css('transform', 'translateX(-100%)');
        $('body').removeClass('dpt-sidebar-open');
        
        $('.dpt-dropdown-menu').hide();
        $('.dpt-dropdown-trigger').attr('aria-expanded', 'false');
        
        $('.dpt-minimal-options').hide();
        
        console.log('DPT: Tutti i popup chiusi');
    }
    
    /**
     * Setup funzioni base
     */
    function setupDynamicTranslation() {
        // Implementazione base per compatibilità
    }
    
    function setupCustomPositions() {
        if (dptFrontend.flagPosition !== 'custom' || !dptFrontend.customPositions) {
            return;
        }
        // Implementazione semplificata
    }
    
    function setupKeyboardNavigation() {
        $(document).on('keydown', function(e) {
            if (e.altKey && e.key === 'l') {
                e.preventDefault();
                const $trigger = $('.dpt-dropdown-trigger, .dpt-popup-trigger, .dpt-sidebar-trigger').first();
                if ($trigger.length) {
                    $trigger.click();
                }
            }
        });
    }
    
    function setupAccessibility() {
        $('.dpt-language-switcher').attr('role', 'navigation').attr('aria-label', dptFrontend.strings.selectLanguage);
        
        $('.dpt-lang-option').each(function() {
            const $option = $(this);
            const lang = $option.data('lang');
            $option.attr('aria-label', `${dptFrontend.strings.changeLanguage}: ${lang}`);
        });
    }
    
    function processDynamicTranslations() {
        // Implementazione semplificata
    }
    
    function initFlags() {
        if (typeof dptFlags !== 'undefined') {
            $(document).trigger('dpt:initFlags', dptFlags);
        }
    }
    
    /**
     * Utility functions
     */
    function getTextNodes($element) {
        const textNodes = [];
        $element.contents().each(function() {
            if (this.nodeType === 3) {
                textNodes.push(this);
            } else if (this.nodeType === 1) {
                textNodes.push(...getTextNodes($(this)));
            }
        });
        return textNodes;
    }
    
    function generateCacheKey(content, sourceLang, targetLang) {
        const data = content + sourceLang + targetLang;
        let hash = 0;
        for (let i = 0; i < data.length; i++) {
            const char = data.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return Math.abs(hash).toString(36);
    }
    
    function showLoadingState() {
        $('.dpt-language-switcher').addClass('loading');
        $('body').css('cursor', 'wait');
        console.log('DPT: Loading state mostrato');
    }
    
    function hideLoadingState() {
        $('.dpt-language-switcher').removeClass('loading');
        $('body').css('cursor', '');
        console.log('DPT: Loading state nascosto');
    }
    
    function showError(message) {
        console.error('DPT: Errore:', message);
        
        $('.dpt-error-notification, .dpt-success-notification').remove();
        
        const $error = $(`<div class="dpt-error-notification">${message}</div>`);
        $('body').append($error);
        
        setTimeout(() => {
            $error.fadeOut(() => $error.remove());
        }, 5000);
    }
    
    // Inizializza quando il documento è pronto
    $(document).ready(init);
    
    // Espone API pubblica
    window.DynamicTranslator = {
        changeLanguage: function(language) {
            $(`.dpt-lang-option[data-lang="${language}"]`).trigger('click');
        },
        getCurrentLanguage: function() {
            return currentLanguage;
        },
        closeAllPopups: closeAllPopups,
        hideLoadingState: hideLoadingState,
        updateLanguageSwitcher: updateLanguageSwitcherFinal,
        // NUOVO: Funzioni di debug
        forceTranslation: function(targetLang) {
            console.log('DPT: Forzando traduzione manuale per lingua:', targetLang);
            showTranslationIndicator('Traduzione forzata...');
            translatePageContent(targetLang || 'it');
        },
        debugConfig: function() {
            console.log('DPT: Configurazione debug:');
            console.log('- currentLanguage:', currentLanguage);
            console.log('- dptFrontend:', dptFrontend);
            console.log('- Elementi switcher:', $('.dpt-language-switcher').length);
            console.log('- Opzioni lingua:', $('.dpt-lang-option').length);
        },
        testTranslateElement: function(text, targetLang) {
            console.log('DPT: Test traduzione elemento:', text);
            $.ajax({
                url: dptFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dpt_translate_element',
                    content: text || 'Hello world',
                    source_lang: dptFrontend.defaultLang,
                    target_lang: targetLang || 'it',
                    nonce: dptFrontend.nonce
                },
                success: function(response) {
                    console.log('✅ Test traduzione successo:', response);
                },
                error: function(xhr, status, error) {
                    console.log('❌ Test traduzione errore:', {xhr, status, error});
                }
            });
        }
    };
    
})(jQuery);