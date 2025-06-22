/**
 * Custom Dictionary Admin JavaScript
 */
(function($) {
    'use strict';

    // DOM Ready
    $(function() {
        const $form = $('#dpt-dictionary-options-form');
        const $message = $('#dpt-dictionary-message');
        
        // Salva impostazioni
        $form.on('submit', function(e) {
            e.preventDefault();
            
            const data = {
                action: 'dpt_save_dictionary_options',
                nonce: dptDictionary.nonce,
                enabled: $('input[name="enabled"]').is(':checked') ? '1' : '0'
            };
            
            $.post(dptDictionary.ajaxUrl, data, function(response) {
                if (response.success) {
                    showMessage('success', response.data);
                } else {
                    showMessage('error', response.data || dptDictionary.strings.saveError);
                }
            }).fail(function() {
                showMessage('error', dptDictionary.strings.saveError);
            });
        });
        
        // Aggiungi parola esclusa
        $('#dpt-add-excluded-word').on('click', function() {
            const $input = $('#dpt-new-excluded-word');
            const word = $input.val().trim();
            
            if (!word) {
                return;
            }
            
            const data = {
                action: 'dpt_add_excluded_word',
                nonce: dptDictionary.nonce,
                word: word
            };
            
            $.post(dptDictionary.ajaxUrl, data, function(response) {
                if (response.success) {
                    $input.val('');
                    
                    // Aggiorna lista
                    const $tbody = $('.dpt-excluded-words-list tbody');
                    $tbody.find('.dpt-no-items').remove();
                    
                    const $row = $('<tr></tr>');
                    $row.append('<td>' + escapeHtml(response.data.word) + '</td>');
                    $row.append('<td class="dpt-actions-column"><button type="button" class="button button-small dpt-remove-excluded-word" data-word="' + escapeHtml(response.data.word) + '">' + dptDictionary.strings.removeText + '</button></td>');
                    
                    $tbody.append($row);
                    showMessage('success', response.data.message);
                } else {
                    showMessage('error', response.data || dptDictionary.strings.addError);
                }
            }).fail(function() {
                showMessage('error', dptDictionary.strings.addError);
            });
        });
        
        // Rimuovi parola esclusa
        $(document).on('click', '.dpt-remove-excluded-word', function() {
            if (!confirm(dptDictionary.strings.confirmDelete)) {
                return;
            }
            
            const $button = $(this);
            const word = $button.data('word');
            
            const data = {
                action: 'dpt_remove_excluded_word',
                nonce: dptDictionary.nonce,
                word: word
            };
            
            $.post(dptDictionary.ajaxUrl, data, function(response) {
                if (response.success) {
                    // Rimuovi riga
                    $button.closest('tr').remove();
                    
                    // Se non ci sono più righe, aggiungi messaggio
                    const $tbody = $('.dpt-excluded-words-list tbody');
                    if ($tbody.find('tr').length === 0) {
                        $tbody.append('<tr class="dpt-no-items"><td colspan="2">' + dptDictionary.strings.noExcludedWords + '</td></tr>');
                    }
                    
                    showMessage('success', response.data.message);
                } else {
                    showMessage('error', response.data || dptDictionary.strings.removeError);
                }
            }).fail(function() {
                showMessage('error', dptDictionary.strings.removeError);
            });
        });
        
        // Aggiungi traduzione personalizzata
        $('#dpt-add-custom-translation').on('click', function() {
            const original = $('#dpt-original-text').val().trim();
            const language = $('#dpt-translation-language').val();
            const translation = $('#dpt-custom-translation').val().trim();
            
            if (!original || !language || !translation) {
                return;
            }
            
            const data = {
                action: 'dpt_add_custom_translation',
                nonce: dptDictionary.nonce,
                original: original,
                language: language,
                translation: translation
            };
            
            $.post(dptDictionary.ajaxUrl, data, function(response) {
                if (response.success) {
                    // Reset form
                    $('#dpt-original-text').val('');
                    $('#dpt-custom-translation').val('');
                    
                    // Aggiorna lista
                    const $tbody = $('.dpt-custom-translations-list tbody');
                    $tbody.find('.dpt-no-items').remove();
                    
                    const $row = $('<tr></tr>');
                    $row.append('<td>' + escapeHtml(response.data.original) + '</td>');
                    $row.append('<td>' + escapeHtml(response.data.language_name) + '</td>');
                    $row.append('<td>' + escapeHtml(response.data.translation) + '</td>');
                    $row.append('<td class="dpt-actions-column"><button type="button" class="button button-small dpt-remove-custom-translation" data-key="' + escapeHtml(response.data.key) + '">' + dptDictionary.strings.removeText + '</button></td>');
                    
                    $tbody.append($row);
                    showMessage('success', response.data.message);
                } else {
                    showMessage('error', response.data || dptDictionary.strings.addError);
                }
            }).fail(function() {
                showMessage('error', dptDictionary.strings.addError);
            });
        });
        
        // Rimuovi traduzione personalizzata
        $(document).on('click', '.dpt-remove-custom-translation', function() {
            if (!confirm(dptDictionary.strings.confirmDelete)) {
                return;
            }
            
            const $button = $(this);
            const key = $button.data('key');
            
            const data = {
                action: 'dpt_remove_custom_translation',
                nonce: dptDictionary.nonce,
                key: key
            };
            
            $.post(dptDictionary.ajaxUrl, data, function(response) {
                if (response.success) {
                    // Rimuovi riga
                    $button.closest('tr').remove();
                    
                    // Se non ci sono più righe, aggiungi messaggio
                    const $tbody = $('.dpt-custom-translations-list tbody');
                    if ($tbody.find('tr').length === 0) {
                        $tbody.append('<tr class="dpt-no-items"><td colspan="4">' + dptDictionary.strings.noCustomTranslations + '</td></tr>');
                    }
                    
                    showMessage('success', response.data.message);
                } else {
                    showMessage('error', response.data || dptDictionary.strings.removeError);
                }
            }).fail(function() {
                showMessage('error', dptDictionary.strings.removeError);
            });
        });
        
        // Ripristina default
        $('#dpt-dictionary-reset').on('click', function() {
            if (confirm(dptDictionary.strings.confirmReset)) {
                $('input[name="enabled"]').prop('checked', true);
                
                // Svuota liste
                $('.dpt-excluded-words-list tbody').html('<tr class="dpt-no-items"><td colspan="2">' + dptDictionary.strings.noExcludedWords + '</td></tr>');
                $('.dpt-custom-translations-list tbody').html('<tr class="dpt-no-items"><td colspan="4">' + dptDictionary.strings.noCustomTranslations + '</td></tr>');
                
                // Salva impostazioni
                $form.submit();
            }
        });
        
        // Utility: Mostra messaggio
        function showMessage(type, text) {
            $message.removeClass('notice-success notice-error').addClass('notice-' + type).html('<p>' + text + '</p>').show();
            
            setTimeout(function() {
                $message.fadeOut();
            }, 3000);
        }
        
        // Utility: Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    });
})(jQuery);
