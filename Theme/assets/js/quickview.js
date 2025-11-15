/**
 * Quickview modal (AJAX) — loads gf_quickview and initializes variation/addon totals.
 * Improved error handling: if server returns "Produktansicht nicht verfügbar", show fallback link.
 */
(function($){
  'use strict';
  var modalSel = '#gfModal';
  var contentSel = '#gfModalContent';
  var backdropSel = '#gfModalBackdrop';

  function openModal(){ $(backdropSel).show().addClass('open'); $(modalSel).show().addClass('open').attr('aria-hidden','false'); $('body').css('overflow','hidden'); }
  function closeModal(){ $(modalSel).removeClass('open').hide().attr('aria-hidden','true'); $(backdropSel).removeClass('open').hide(); $('body').css('overflow',''); $(contentSel).html(''); }

  function formatPrice(n){ n = (Math.round(n*100)/100).toFixed(2); return n.replace('.', ',') + '€'; }

  function ensureStickyCTA($content){
    if ($content.find('.gf-sticky-cta').length) return;
    var bar = $('<div class="gf-sticky-cta" role="region" aria-label="Warenkorb Aktion"></div>');
    bar.html('<div class="gf-total">Gesamt: <span class="gf-total-val">0,00€</span></div><button type="button" class="gf-btn">In den Warenkorb</button>');
    $content.find('.gf-modal-inner').append(bar);
    bar.on('click', '.gf-btn', function(){ $content.find('form.cart').trigger('submit'); });
  }

  function readBasePrice($content){
    var $vp = $content.find('.woocommerce-variation-price .price, .gf-modal-price .price');
    if ($vp.length) {
      var txt = $vp.first().text() || '';
      var num = parseFloat(txt.replace(/[^\d,.-]/g,'').replace(/\./g,'').replace(',','.')) || 0;
      return num;
    }
    var raw = parseFloat($content.find('.gf-modal-price').data('base-price') || 0) || 0;
    return raw;
  }

  function sumAddons($content){
    var sum = 0;
    $content.find('.gf-addons .gf-addon-check').each(function(){
      var $c = $(this);
      if ($c.is(':checked')){
        var price = parseFloat($c.data('price') || 0) || 0;
        var qty = 1;
        var $row = $c.closest('.gf-addon-row');
        if ($row.length) {
          var $q = $row.find('.gf-addon-qty');
          if ($q.length) qty = Math.max(1, parseInt($q.val() || 1, 10));
        }
        sum += price * qty;
      }
    });
    return sum;
  }

  function refreshTotals($content){
    var base = parseFloat($content.data('current-base') || 0) || 0;
    var extras = sumAddons($content);
    var qty = Math.max(1, parseInt($content.find('input.qty').val() || 1, 10));
    var total = (base + extras) * qty;
    $content.find('.gf-total-val').text(formatPrice(total));
  }

  function initVariationEvents($content){
    $(document).off('found_variation.quickview').on('found_variation.quickview', '.variations_form', function(ev, variation){
      if (!variation) return;
      var price = parseFloat(variation.display_price || variation.display_regular_price) || 0;
      $content.data('current-base', price);
      refreshTotals($content);
    });
    $(document).off('reset_data.quickview').on('reset_data.quickview', '.variations_form', function(){
      $content.data('current-base', readBasePrice($content));
      refreshTotals($content);
    });
  }

  function bindFormAjax($content){
    var $form = $content.find('form.cart');
    if (!$form.length) return;
    $form.off('submit.quickview').on('submit.quickview', function(e){
      e.preventDefault();
      var $varId = $form.find('input[name="variation_id"]');
      var $sel = $form.find('select[name^="attribute_"]');
      if ($sel.length && !$sel.val()) {
        var first = $sel.find('option[value]:not([value=""])').first();
        if (first.length) { $sel.val(first.val()).trigger('change'); }
      }
      // attempt to set variation_id if missing using data-product_variations
      if ($varId.length && !$varId.val()) {
        try {
          var vfjson = $form.closest('.variations_form').attr('data-product_variations');
          var vars = vfjson ? JSON.parse(vfjson) : null;
          if (vars) {
            var attrs = {};
            $form.find('select[name^="attribute_"]').each(function(){ attrs[$(this).attr('name')] = $(this).val(); });
            for (var i=0;i<vars.length;i++){
              var v = vars[i];
              var ok = true;
              for (var key in v.attributes){
                if (v.attributes.hasOwnProperty(key) && v.attributes[key] && attrs['attribute_' + key.split('attribute_').pop()] !== v.attributes[key]) { ok = false; break; }
              }
              if (ok && v.variation_id) { $varId.val(v.variation_id); break; }
            }
          }
        } catch(e){}
      }

      var fd = new FormData($form[0]);
      if (!fd.has('product_id') && fd.get('add-to-cart')) fd.append('product_id', fd.get('add-to-cart'));
      var ajaxUrl = (window.wc_add_to_cart_params && wc_add_to_cart_params.wc_ajax_url) ? wc_add_to_cart_params.wc_ajax_url.replace('%%endpoint%%','add_to_cart') : ( (typeof ThemeQuickview !== 'undefined' && ThemeQuickview.wc_ajax_url) ? ThemeQuickview.wc_ajax_url + 'add_to_cart' : '/?wc-ajax=add_to_cart' );

      fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(res){ return res.json(); })
        .then(function(res){
          if (!res) { alert('Unerwartete Antwort'); return; }
          if (res.error) {
            if (res.product_url) { alert('Bitte wählen Sie zuerst alle notwendigen Optionen.'); return; }
            alert('Artikel konnte nicht hinzugefügt werden.');
            return;
          }
          if (res.fragments) {
            Object.keys(res.fragments).forEach(function(sel){
              try {
                var html = res.fragments[sel];
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newEl = doc.body.firstElementChild;
                var oldEl = document.querySelector(sel);
                if (oldEl && newEl) oldEl.replaceWith(newEl);
              } catch(e){}
            });
          }
          closeModal();
        })
        .catch(function(){ alert('Fehler beim Hinzufügen in den Warenkorb.'); });
    });
  }

  // open quickview on product click (delegated)
  $(document).on('click', '.gf-product-link', function(e){
    if (e.metaKey || e.ctrlKey || e.which === 2) return;
    e.preventDefault();
    var $link = $(this);
    var pid = parseInt($link.closest('.product, .shop-card').data('pid') || $link.closest('.product, .shop-card').attr('id')?.replace('post-','') || 0, 10);
    if (!pid) return;
    openModal();
    $(contentSel).html('<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Bitte warten…</p></div>');

    // fetch via gf_quickview
    var baseUrl = (typeof ThemeQuickview !== 'undefined' && ThemeQuickview.ajax_quickview_endpoint) ? ThemeQuickview.ajax_quickview_endpoint : (window.location.origin + '/?wc-ajax=gf_quickview');
    var url = baseUrl + '&product_id=' + pid;
    fetch(url, { credentials: 'same-origin' }).then(function(r){ return r.text(); }).then(function(html){
      // if server returned a simple "Produktansicht nicht verfügbar." message, provide fallback
      if (!html || html.indexOf('Produktansicht nicht verfügbar') !== -1 || html.trim().length < 10) {
        $(contentSel).html('<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Produktansicht nicht verfügbar. <a href="' + ($link.attr('href') || '#') + '">Produktseite öffnen</a></p></div>');
        return;
      }
      $(contentSel).html(html);
      var $content = $(contentSel);
      ensureStickyCTA($content);
      // set initial base price
      $content.data('current-base', readBasePrice($content));
      if ($.fn.wc_variation_form) $content.find('.variations_form').each(function(){ $(this).wc_variation_form(); });
      initVariationEvents($content);
      // recalc on addon/qty changes
      $content.on('change input', '.gf-addons input, input.qty, .gf-addon-qty', function(){ refreshTotals($content); });
      refreshTotals($content);
      bindFormAjax($content);
    }).catch(function(){
      $(contentSel).html('<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Fehler beim Laden. <a href="' + ($link.attr('href') || '#') + '">Produkt öffnen</a></p></div>');
    });
  });

  // close handlers
  $(document).on('click', '.gf-modal-close, .gf-modal-backdrop', function(e){ e.preventDefault(); closeModal(); });
  $(document).on('keydown', function(e){ if (e.key === 'Escape' && $(modalSel).hasClass('open')) closeModal(); });

})(jQuery);