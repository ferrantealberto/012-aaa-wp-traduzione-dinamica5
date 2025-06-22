/**
 * JavaScript Bandiere per Dynamic Page Translator
 * File: assets/js/flags.js
 */

(function($) {
    'use strict';
    
    let flagsConfig = {};
    let currentStyle = 'dropdown';
    let currentPosition = 'top-right';
    
    /**
     * Inizializzazione modulo bandiere
     */
    function init() {
        if (typeof dptFlags !== 'undefined') {
            flagsConfig = dptFlags;
            currentStyle = flagsConfig.style || 'dropdown';
            currentPosition = flagsConfig.position || 'top-right';
            
            setupFlags();
            setupResponsiveBehavior();
            setupCustomPositions();
            setupAnimations();
        }
        
        // Event listener per inizializzazione da frontend principale
        $(document).on('dpt:initFlags', function(e, config) {
            flagsConfig = config;
            setupFlags();
        });
    }
    
    /**
     * Setup principale delle bandiere
     */
    function setupFlags() {
        const $switcher = $('.dpt-flag-switcher');
        if (!$switcher.length) return;
        
        // Setup specifico per stile
        switch(currentStyle) {
            case 'dropdown':
                setupDropdownFlags($switcher);
                break;
            case 'inline':
                setupInlineFlags($switcher);
                break;
            case 'popup':
                setupPopupFlags($switcher);
                break;
            case 'sidebar-slide':
                setupSidebarSlideFlags($switcher);
                break;
            case 'circle-menu':
                setupCircleMenuFlags($switcher);
                break;
            case 'minimal':
                setupMinimalFlags($switcher);
                break;
        }
        
        // Setup comune
        setupCommonFeatures($switcher);
        
        // Applica posizione
        applyPosition($switcher);
        
        // Applica configurazioni personalizzate
        applyCustomStyling($switcher);
    }
    
    /**
     * Setup dropdown flags
     */
    function setupDropdownFlags($container) {
        const $trigger = $container.find('.dpt-dropdown-trigger');
        const $menu = $container.find('.dpt-dropdown-menu');
        
        if (!$trigger.length || !$menu.length) return;
        
        // Click trigger
        $trigger.off('click.dptFlags').on('click.dptFlags', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleDropdown($trigger, $menu);
        });
        
        // Chiudi dropdown cliccando fuori
        $(document).off('click.dptDropdown').on('click.dptDropdown', function(e) {
            if (!$container.is(e.target) && $container.has(e.target).length === 0) {
                closeDropdown($trigger, $menu);
            }
        });
        
        // Navigazione tastiera
        setupDropdownKeyboard($trigger, $menu);
        
        // Auto-hide su mobile
        if (flagsConfig.autoHide && window.innerWidth < 768) {
            setupAutoHide($container);
        }
    }
    
    /**
     * Setup inline flags
     */
    function setupInlineFlags($container) {
        const $options = $container.find('.dpt-lang-option');
        
        $options.off('click.dptFlags').on('click.dptFlags', function(e) {
            e.preventDefault();
            const $this = $(this);
            
            // Rimuovi stato attivo da altri
            $options.removeClass('active').removeAttr('aria-current');
            
            // Aggiungi stato attivo al corrente
            $this.addClass('active').attr('aria-current', 'page');
            
            // Trigger evento cambio lingua
            triggerLanguageChange($this.data('lang'));
        });
        
        // Effetti hover
        if (flagsConfig.animations) {
            setupHoverEffects($options);
        }
    }
    
    /**
     * Setup popup flags
     */
    function setupPopupFlags($container) {
        const $trigger = $container.find('.dpt-popup-trigger');
        const $overlay = $container.find('.dpt-popup-overlay');
        const $close = $container.find('.dpt-popup-close');
        const $cards = $container.find('.dpt-lang-card');
        
        if (!$trigger.length || !$overlay.length) return;
        
        // Apri popup
        $trigger.off('click.dptFlags').on('click.dptFlags', function(e) {
            e.preventDefault();
            openPopup($overlay);
        });
        
        // Chiudi popup
        $close.off('click.dptFlags').on('click.dptFlags', function(e) {
            e.preventDefault();
            closePopup($overlay);
        });
        
        // Chiudi cliccando sull'overlay
        $overlay.off('click.dptFlags').on('click.dptFlags', function(e) {
            if (e.target === this) {
                closePopup($overlay);
            }
        });
        
        // Click su card lingua
        $cards.off('click.dptFlags').on('click.dptFlags', function(e) {
            e.preventDefault();
            const $this = $(this);
            
            // Aggiorna stato attivo
            $cards.removeClass('active').removeAttr('aria-current');
            $this.addClass('active').attr('aria-current', 'page');
            
            // Chiudi popup e cambia lingua
            closePopup($overlay);
            triggerLanguageChange($this.data('lang'));
        });
        
        // ESC per chiudere
        $(document).off('keydown.dptPopup').on('keydown.dptPopup', function(e) {
            if (e.key === 'Escape' && $overlay.is(':visible')) {
                closePopup($overlay);
            }
        });
        
        // Navigazione tastiera nel popup
        setupPopupKeyboard($cards);
    }
    
    /**
     * Setup sidebar slide flags
     */
    function setupSidebarSlideFlags($container) {
        const $trigger = $container.find('.dpt-sidebar-trigger');
        const $overlay = $container.find('.dpt-sidebar-overlay');
        const $panel = $container.find('.dpt-sidebar-panel');
        const $close = $container.find('.dpt-sidebar-close');
        const $options = $container.find('.dpt-sidebar-option');
        
        if (!$trigger.length || !$panel.length) return;
        
        // Apri sidebar
        $trigger.off('click.dptFlags').on('click.dptFlags', function(e) {
            e.preventDefault();
            openSidebar($overlay, $panel);
        });
        
        // Chiudi sidebar
        $close.off('click.dptFlags').on('click.dptFlags', function(e) {
            e.preventDefault();
            closeSidebar($overlay, $panel);
        });
        
        $overlay.off('click.dptFlags').on('click.dptFlags', function(e) {
            closeSidebar($overlay, $panel);
        });
        
        // Click opzioni
        $options.off('click.dptFlags').on('click.dptFlags', function(e) {
            e.preventDefault();
            const $this = $(this);
            
            if (!$this.hasClass('active')) {
                $options.removeClass('active').removeAttr('aria-current');
                $this.addClass('active').attr('aria-current', 'page');
                
                closeSidebar($overlay, $panel);
                triggerLanguageChange($this.data('lang'));
            }
        });
        
        // ESC per chiudere
        $(document).off('keydown.dptSidebar').on('keydown.dptSidebar', function(e) {
            if (e.key === 'Escape' && $overlay.is(':visible')) {
                closeSidebar($overlay, $panel);
            }
        });
    }
    
    /**
     * Setup circle menu flags
     */
    function setupCircleMenuFlags($container) {
        const $trigger = $container.find('.dpt-circle-trigger');
        const $menu = $container.find('.dpt-circle-menu');
        const $options = $container.find('.dpt-circle-option');
        
        if (!$trigger.length || !$menu.length) return;
        
        let isOpen = false;
        
        // Toggle menu
        $trigger.off('click.dptFlags').on('click.dptFlags', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (isOpen) {
                closeCircleMenu($trigger, $menu, $options);
                isOpen = false;
            } else {
                openCircleMenu($trigger, $menu, $options);
                isOpen = true;
            }
        });
        
        // Click opzioni
        $options.off('click.dptFlags').on('click.dptFlags', function(e) {
            e.preventDefault();
            
            closeCircleMenu($trigger, $menu, $options);
            isOpen = false;
            
            triggerLanguageChange($(this).data('lang'));
        });
        
        // Chiudi cliccando fuori
        $(document).off('click.dptCircle').on('click.dptCircle', function(e) {
            if (isOpen && !$container.is(e.target) && $container.has(e.target).length === 0) {
                closeCircleMenu($trigger, $menu, $options);
                isOpen = false;
            }
        });
    }
    
    /**
     * Setup minimal flags
     */
    function setupMinimalFlags($container) {
        const $trigger = $container.find('.dpt-minimal-current');
        const $options = $container.find('.dpt-minimal-options');
        const $optionButtons = $container.find('.dpt-minimal-option');
        
        if (!$trigger.length || !$options.length) return;
        
        // Toggle options
        $trigger.off('click.dptFlags').on('click.dptFlags', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $options.toggle();
        });
        
        // Click opzioni
        $optionButtons.off('click.dptFlags').on('click.dptFlags', function(e) {
            e.preventDefault();
            
            const newLang = $(this).data('lang');
            $trigger.text(newLang.toUpperCase());
            $options.hide();
            
            triggerLanguageChange(newLang);
        });
        
        // Chiudi cliccando fuori
        $(document).off('click.dptMinimal').on('click.dptMinimal', function(e) {
            if (!$container.is(e.target) && $container.has(e.target).length === 0) {
                $options.hide();
            }
        });
    }
    
    /**
     * Setup features comuni
     */
    function setupCommonFeatures($container) {
        // Tooltip
        if (flagsConfig.showTooltips) {
            setupTooltips($container);
        }
        
        // Loading states
        setupLoadingStates($container);
        
        // Analytics tracking
        if (flagsConfig.analytics) {
            setupAnalytics($container);
        }
        
        // Lazy loading bandiere
        setupLazyLoading($container);
    }
    
    /**
     * Applica posizione
     */
    function applyPosition($container) {
        if (currentPosition === 'custom') {
            // Le posizioni personalizzate sono gestite dal frontend principale
            return;
        }
        
        // Rimuovi tutte le classi di posizione esistenti
        $container.removeClass(function(index, className) {
            return (className.match(/(^|\s)dpt-position-\S+/g) || []).join(' ');
        });
        
        // Aggiungi nuova classe posizione
        $container.addClass('dpt-position-' + currentPosition);
        
        // Posizioni speciali
        if (currentPosition === 'floating') {
            makeFloating($container);
        }
    }
    
    /**
     * Rende il switcher fluttuante
     */
    function makeFloating($container) {
        $container.draggable({
            containment: 'window',
            handle: $container,
            stop: function(event, ui) {
                // Salva posizione in localStorage
                if (typeof Storage !== 'undefined') {
                    localStorage.setItem('dpt_floating_position', JSON.stringify({
                        top: ui.position.top,
                        left: ui.position.left
                    }));
                }
            }
        });
        
        // Ripristina posizione salvata
        if (typeof Storage !== 'undefined') {
            const savedPosition = localStorage.getItem('dpt_floating_position');
            if (savedPosition) {
                const position = JSON.parse(savedPosition);
                $container.css({
                    top: position.top + 'px',
                    left: position.left + 'px',
                    right: 'auto',
                    bottom: 'auto'
                });
            }
        }
    }
    
    /**
     * Applica styling personalizzato
     */
    function applyCustomStyling($container) {
        // Dimensione bandiere
        if (flagsConfig.flagSize) {
            $container.find('.dpt-flag').css('width', flagsConfig.flagSize + 'px');
        }
        
        // Stile bordi
        if (flagsConfig.flagBorderStyle) {
            $container.addClass('dpt-flag-border-' + flagsConfig.flagBorderStyle);
        }
        
        // Ombreggiatura
        if (flagsConfig.flagShadow === false) {
            $container.addClass('dpt-flag-no-shadow');
        } else if (flagsConfig.flagShadow) {
            $container.addClass('dpt-flag-shadow');
        }
        
        // Nascondi labels se richiesto
        if (!flagsConfig.showLabels) {
            $container.addClass('dpt-hide-labels');
        }
        
        // Tema personalizzato
        if (flagsConfig.theme) {
            $container.addClass('dpt-theme-' + flagsConfig.theme);
        }
    }
    
    /**
     * Setup comportamento responsivo
     */
    function setupResponsiveBehavior() {
        let resizeTimer;
        
        $(window).off('resize.dptFlags').on('resize.dptFlags', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                handleResize();
            }, 250);
        });
        
        // Check iniziale
        handleResize();
    }
    
    /**
     * Gestisce il resize
     */
    function handleResize() {
        const isMobile = window.innerWidth < 768;
        const $container = $('.dpt-flag-switcher');
        
        if (flagsConfig.hideOnMobile && isMobile) {
            $container.hide();
        } else {
            $container.show();
        }
        
        // Adatta stile per mobile
        if (isMobile && currentStyle === 'inline') {
            adaptInlineForMobile($container);
        }
    }
    
    /**
     * Adatta inline per mobile
     */
    function adaptInlineForMobile($container) {
        const $options = $container.find('.dpt-lang-option');
        const containerWidth = $container.width();
        let totalWidth = 0;
        
        $options.each(function() {
            totalWidth += $(this).outerWidth(true);
        });
        
        if (totalWidth > containerWidth) {
            // Converte temporaneamente a dropdown
            convertToDropdown($container);
        }
    }
    
    /**
     * Setup posizioni personalizzate
     */
    function setupCustomPositions() {
        if (currentPosition !== 'custom' || !flagsConfig.customPositions) {
            return;
        }
        
        const $switcher = $('.dpt-flag-switcher');
        
        flagsConfig.customPositions.forEach(function(position, index) {
            const $target = $(position.selector);
            if ($target.length) {
                const $clone = $switcher.clone()
                    .attr('id', 'dpt-flag-switcher-' + index)
                    .show();
                
                // Applica metodo di inserimento
                switch(position.method) {
                    case 'append':
                        $target.append($clone);
                        break;
                    case 'prepend':
                        $target.prepend($clone);
                        break;
                    case 'after':
                        $target.after($clone);
                        break;
                    case 'before':
                        $target.before($clone);
                        break;
                }
                
                // Re-inizializza eventi per il clone
                setupFlags($clone);
            }
        });
        
        // Nasconde switcher originale
        $switcher.hide();
    }
    
    /**
     * Setup animazioni
     */
    function setupAnimations() {
        if (!flagsConfig.animations) {
            $('body').addClass('dpt-no-animations');
            return;
        }
        
        // Animazioni di entrata
        $('.dpt-flag-switcher').addClass('dpt-animate-in');
        
        // Animazioni hover
        $('.dpt-lang-option').hover(
            function() {
                $(this).addClass('dpt-hover');
            },
            function() {
                $(this).removeClass('dpt-hover');
            }
        );
    }
    
    /**
     * Utility functions per gestione UI
     */
    function toggleDropdown($trigger, $menu) {
        const isOpen = $menu.is(':visible');
        
        if (isOpen) {
            closeDropdown($trigger, $menu);
        } else {
            openDropdown($trigger, $menu);
        }
    }
    
    function openDropdown($trigger, $menu) {
        // Chiudi altri dropdown
        $('.dpt-dropdown-menu').not($menu).hide();
        $('.dpt-dropdown-trigger').not($trigger).attr('aria-expanded', 'false');
        
        $menu.show();
        $trigger.attr('aria-expanded', 'true');
        
        // Focus primo elemento
        setTimeout(() => {
            $menu.find('.dpt-lang-option').first().focus();
        }, 100);
        
        // Analytics
        trackEvent('dropdown_opened');
    }
    
    function closeDropdown($trigger, $menu) {
        $menu.hide();
        $trigger.attr('aria-expanded', 'false');
    }
    
    function openPopup($overlay) {
        $overlay.fadeIn(200);
        $('body').addClass('dpt-popup-open');
        
        // Focus primo elemento
        setTimeout(() => {
            $overlay.find('.dpt-lang-card').first().focus();
        }, 250);
        
        trackEvent('popup_opened');
    }
    
    function closePopup($overlay) {
        $overlay.fadeOut(200);
        $('body').removeClass('dpt-popup-open');
    }
    
    function openSidebar($overlay, $panel) {
        $overlay.fadeIn(200);
        $panel.css('transform', 'translateX(0)');
        $('body').addClass('dpt-sidebar-open');
        
        setTimeout(() => {
            $panel.find('.dpt-sidebar-option').first().focus();
        }, 300);
        
        trackEvent('sidebar_opened');
    }
    
    function closeSidebar($overlay, $panel) {
        $overlay.fadeOut(200);
        $panel.css('transform', 'translateX(-100%)');
        $('body').removeClass('dpt-sidebar-open');
    }
    
    function openCircleMenu($trigger, $menu, $options) {
        $menu.show();
        $trigger.addClass('active');
        
        // Anima opzioni con delay
        $options.each(function(index) {
            const $option = $(this);
            setTimeout(() => {
                $option.addClass('visible');
            }, index * 50);
        });
        
        trackEvent('circle_menu_opened');
    }
    
    function closeCircleMenu($trigger, $menu, $options) {
        $options.removeClass('visible');
        $trigger.removeClass('active');
        
        setTimeout(() => {
            $menu.hide();
        }, 300);
    }
    
    /**
     * Setup navigazione tastiera
     */
    function setupDropdownKeyboard($trigger, $menu) {
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
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    $(this).click();
                    break;
                case 'Escape':
                    closeDropdown($trigger, $menu);
                    $trigger.focus();
                    break;
            }
        });
    }
    
    function setupPopupKeyboard($cards) {
        $cards.on('keydown', function(e) {
            const currentIndex = $cards.index(this);
            let nextIndex;
            
            switch(e.key) {
                case 'ArrowRight':
                case 'ArrowDown':
                    e.preventDefault();
                    nextIndex = (currentIndex + 1) % $cards.length;
                    $cards.eq(nextIndex).focus();
                    break;
                case 'ArrowLeft':
                case 'ArrowUp':
                    e.preventDefault();
                    nextIndex = currentIndex === 0 ? $cards.length - 1 : currentIndex - 1;
                    $cards.eq(nextIndex).focus();
                    break;
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    $(this).click();
                    break;
            }
        });
    }
    
    /**
     * Setup tooltips
     */
    function setupTooltips($container) {
        $container.find('[title]').each(function() {
            const $element = $(this);
            const title = $element.attr('title');
            
            $element.removeAttr('title');
            
            $element.hover(
                function() {
                    showTooltip($element, title);
                },
                function() {
                    hideTooltip();
                }
            );
        });
    }
    
    function showTooltip($element, text) {
        const $tooltip = $('<div class="dpt-tooltip">' + text + '</div>');
        $('body').append($tooltip);
        
        const offset = $element.offset();
        $tooltip.css({
            top: offset.top - $tooltip.outerHeight() - 5,
            left: offset.left + ($element.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
        });
    }
    
    function hideTooltip() {
        $('.dpt-tooltip').remove();
    }
    
    /**
     * Setup loading states
     */
    function setupLoadingStates($container) {
        $container.on('dpt:loading', function() {
            $(this).addClass('dpt-loading');
        });
        
        $container.on('dpt:loaded', function() {
            $(this).removeClass('dpt-loading');
        });
    }
    
    /**
     * Setup analytics
     */
    function setupAnalytics($container) {
        if (!flagsConfig.analytics) return;
        
        $container.find('.dpt-lang-option').on('click', function() {
            const language = $(this).data('lang');
            trackEvent('language_changed', {
                from: flagsConfig.currentLang,
                to: language,
                method: currentStyle
            });
        });
    }
    
    /**
     * Setup lazy loading
     */
    function setupLazyLoading($container) {
        const $flags = $container.find('.dpt-flag[data-src]');
        
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const $flag = $(entry.target);
                        $flag.attr('src', $flag.data('src')).removeAttr('data-src');
                        observer.unobserve(entry.target);
                    }
                });
            });
            
            $flags.each(function() {
                observer.observe(this);
            });
        } else {
            // Fallback per browser vecchi
            $flags.each(function() {
                const $flag = $(this);
                $flag.attr('src', $flag.data('src')).removeAttr('data-src');
            });
        }
    }
    
    /**
     * Utility functions
     */
    function triggerLanguageChange(language) {
        $(document).trigger('dpt:flagLanguageChange', language);
        
        // Se non gestito dal frontend principale, usa evento click standard
        setTimeout(() => {
            $(`.dpt-lang-option[data-lang="${language}"]`).first().trigger('click');
        }, 50);
    }
    
    function trackEvent(eventName, data = {}) {
        if (flagsConfig.analytics && typeof gtag !== 'undefined') {
            gtag('event', eventName, {
                event_category: 'DPT_Flags',
                ...data
            });
        }
        
        // Event personalizzato
        $(document).trigger('dpt:analyticsEvent', {
            event: eventName,
            data: data
        });
    }
    
    function convertToDropdown($container) {
        // Conversione temporanea inline -> dropdown per mobile
        // Implementazione semplificata
        $container.addClass('dpt-mobile-dropdown');
    }
    
    /**
     * API pubblica per bandiere
     */
    window.DPTFlags = {
        changeStyle: function(newStyle) {
            currentStyle = newStyle;
            setupFlags();
        },
        changePosition: function(newPosition) {
            currentPosition = newPosition;
            applyPosition($('.dpt-flag-switcher'));
        },
        refresh: function() {
            setupFlags();
        },
        getCurrentStyle: function() {
            return currentStyle;
        },
        getCurrentPosition: function() {
            return currentPosition;
        }
    };
    
    // Inizializza quando il documento è pronto
    $(document).ready(init);
    
})(jQuery);