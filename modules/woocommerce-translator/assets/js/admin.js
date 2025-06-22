/**
 * WooCommerce Translator Admin JavaScript
 */
(function($) {
    'use strict';

    // DOM Ready
    $(function() {
        const $form = $('#dpt-woocommerce-options-form');
        const $message = $('#dpt-woocommerce-message');
        
        // Tab navigation
        $('.dpt-tabs-nav li').on('click', function() {
            const tabId = $(this).data('tab');
            
            // Attiva tab
            $('.dpt-tabs-nav li').removeClass('active');
            $(this).addClass('active');
            
            // Mostra contenuto tab
            $('.dpt-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');
        });
        
        // Salva impostazioni
        $form.on('submit', function(e) {
            e.preventDefault();
            
            const formData = $(this).serialize();
            
            $.post(dptWooCommerce.ajaxUrl, formData, function(response) {
                if (response.success) {
                    showMessage('success', response.data);
                } else {
                    showMessage('error', response.data || dptWooCommerce.strings.saveError);
                }
            }).fail(function() {
                showMessage('error', dptWooCommerce.strings.saveError);
            });
        });
        
        // Ripristina default
        $('#dpt-woocommerce-reset').on('click', function() {
            if (confirm('Sei sicuro di voler ripristinare le impostazioni predefinite?')) {
                // Ripristina checkbox
                $form.find('input[type="checkbox"]').prop('checked', true);
                
                // Ripristina select
                $form.find('select[name="priority"]').val('high');
                
                // Svuota liste esclusioni
                $('#dpt-excluded-products-list, #dpt-excluded-categories-list').empty();
                
                // Salva impostazioni
                $form.submit();
            }
        });
        
        // Inizializza Select2 se disponibile
        if ($.fn.select2) {
            $('.dpt-select2').select2({
                width: '100%',
                placeholder: 'Seleziona...',
                allowClear: true
            });
        }
        
        // Aggiungi prodotto escluso
        $('#dpt-add-excluded-product').on('click', function() {
            const $select = $('#dpt-product-selector');
            const productId = $select.val();
            const productName = $select.find('option:selected').text();
            
            if (!productId) {
                return;
            }
            
            // Verifica se già presente
            if ($('#dpt-excluded-products-list li[data-id="' + productId + '"]').length > 0) {
                return;
            }
            
            // Aggiungi alla lista
            $('#dpt-excluded-products-list').append(
                '<li data-id="' + productId + '">' + 
                productName + 
                ' <a href="#" class="dpt-remove-excluded">×</a>' +
                '<input type="hidden" name="excluded_products[]" value="' + productId + '">' +
                '</li>'
            );
            
            // Reset select
            $select.val('').trigger('change');
        });
        
        // Aggiungi categoria esclusa
        $('#dpt-add-excluded-category').on('click', function() {
            const $select = $('#dpt-category-selector');
            const categoryId = $select.val();
            const categoryName = $select.find('option:selected').text();
            
            if (!categoryId) {
                return;
            }
            
            // Verifica se già presente
            if ($('#dpt-excluded-categories-list li[data-id="' + categoryId + '"]').length > 0) {
                return;
            }
            
            // Aggiungi alla lista
            $('#dpt-excluded-categories-list').append(
                '<li data-id="' + categoryId + '">' + 
                categoryName + 
                ' <a href="#" class="dpt-remove-excluded">×</a>' +
                '<input type="hidden" name="excluded_categories[]" value="' + categoryId + '">' +
                '</li>'
            );
            
            // Reset select
            $select.val('').trigger('change');
        });
        
        // Rimuovi elemento escluso
        $(document).on('click', '.dpt-remove-excluded', function(e) {
            e.preventDefault();
            $(this).closest('li').remove();
        });
        
        // Utility: Mostra messaggio
        function showMessage(type, text) {
            $message.removeClass('notice-success notice-error').addClass('notice-' + type).html('<p>' + text + '</p>').show();
            
            setTimeout(function() {
                $message.fadeOut();
            }, 3000);
        }
    });
})(jQuery);
