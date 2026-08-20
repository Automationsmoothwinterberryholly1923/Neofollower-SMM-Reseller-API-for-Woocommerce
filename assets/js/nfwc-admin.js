(function($){
  'use strict';

  function debounce(fn, wait){
    var timer;
    return function(){
      var ctx=this,args=arguments;
      clearTimeout(timer);
      timer=setTimeout(function(){ fn.apply(ctx,args); }, wait);
    };
  }

  function escapeHtml(str){
    return String(str || '').replace(/[&<>'"]/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c];
    });
  }

  function updateConditionalFields(){
    var enabled = $('#_nfwc_enabled').is(':checked');
    var type = $('#_nfwc_service_type').val() || 'default';
    var mode = $('#_nfwc_quantity_mode').val() || 'fixed';
    var $wrap = $('.nfwc-product-panel .nfwc-conditional-wrap');

    $wrap.toggleClass('nfwc-is-hidden', !enabled);
    $('.nfwc-field').removeClass('nfwc-is-hidden');

    if(type === 'package'){
      $('.nfwc-field-quantity-mode,.nfwc-field-fixed-quantity,.nfwc-field-customer-quantity').addClass('nfwc-is-hidden');
    } else if(type === 'custom_comments'){
      $('#_nfwc_quantity_mode').val('comments_lines');
      $('.nfwc-field-quantity-mode,.nfwc-field-fixed-quantity,.nfwc-field-customer-quantity').addClass('nfwc-is-hidden');
    } else {
      if(mode === 'fixed'){
        $('.nfwc-field-customer-quantity').addClass('nfwc-is-hidden');
      } else if(mode === 'customer'){
        $('.nfwc-field-fixed-quantity').addClass('nfwc-is-hidden');
      } else {
        $('.nfwc-field-fixed-quantity,.nfwc-field-customer-quantity').addClass('nfwc-is-hidden');
      }
    }
  }

  function renderServiceCard(service){
    if(!service){
      return '<p>No service selected.</p>';
    }
    return '<div class="nfwc-service-card-inner">'
      + '<strong>' + escapeHtml(service.service_id + ' — ' + service.name) + '</strong>'
      + '<ul>'
      + '<li><span>Type:</span> ' + escapeHtml(service.type || '—') + '</li>'
      + '<li><span>Category:</span> ' + escapeHtml(service.category || '—') + '</li>'
      + '<li><span>Rate:</span> ' + escapeHtml(service.rate || '—') + '</li>'
      + '<li><span>Min / Max:</span> ' + escapeHtml((service.min_qty || '—') + ' / ' + (service.max_qty || '—')) + '</li>'
      + '<li><span>Refill / Cancel:</span> ' + escapeHtml((service.refill || '—') + ' / ' + (service.cancel || '—')) + '</li>'
      + '</ul>'
      + '<button type="button" class="button nfwc-apply-service-limits" data-type="' + escapeHtml(service.guessed_type || 'default') + '" data-min="' + escapeHtml(service.min_qty || 0) + '" data-max="' + escapeHtml(service.max_qty || 0) + '">Apply type and min/max</button>'
      + '</div>';
  }

  function selectService(service){
    $('#_nfwc_service_id').val(service.service_id);
    $('#_nfwc_service_search').val(service.label || (service.service_id + ' — ' + service.name));
    $('.nfwc-selected-service-card').attr('data-empty','0').html(renderServiceCard(service));
    $('.nfwc-service-results').removeClass('is-open').empty();
  }

  var searchServices = debounce(function(){
    var term = $('#_nfwc_service_search').val();
    var $results = $('.nfwc-service-results');
    if(!term || term.length < 2){
      $results.removeClass('is-open').empty();
      return;
    }
    $results.addClass('is-open').html('<div class="nfwc-service-result"><small>' + escapeHtml(NFWCAdmin.searching || 'Searching...') + '</small></div>');
    $.get(NFWCAdmin.ajaxUrl, { action:'nfwc_search_services', nonce:NFWCAdmin.nonce, term:term })
      .done(function(resp){
        if(!resp || !resp.success || !resp.data.results || !resp.data.results.length){
          $results.html('<div class="nfwc-service-result"><small>' + escapeHtml(NFWCAdmin.noResults || 'No services found.') + '</small></div>');
          return;
        }
        var html = resp.data.results.map(function(service, idx){
          window.NFWCServiceCache = window.NFWCServiceCache || {};
          window.NFWCServiceCache['s' + idx] = service;
          return '<button type="button" class="nfwc-service-result" data-cache="s' + idx + '"><strong>' + escapeHtml(service.label) + '</strong><small>' + escapeHtml((service.category || 'No category') + ' · ' + (service.type || 'Default') + ' · Min ' + (service.min_qty || '—') + ' / Max ' + (service.max_qty || '—')) + '</small></button>';
        }).join('');
        $results.html(html);
      })
      .fail(function(){
        $results.html('<div class="nfwc-service-result"><small>Search failed.</small></div>');
      });
  }, 280);

  $(document).on('keyup focus', '#_nfwc_service_search', searchServices);
  $(document).on('click', '.nfwc-service-result[data-cache]', function(){
    var service = (window.NFWCServiceCache || {})[$(this).data('cache')];
    if(service){ selectService(service); }
  });
  $(document).on('click', '.nfwc-clear-service', function(){
    $('#_nfwc_service_id,#_nfwc_service_search').val('');
    $('.nfwc-selected-service-card').attr('data-empty','1').html('<p>No service selected.</p>');
  });
  $(document).on('click', '.nfwc-apply-service-limits', function(){
    var type = $(this).data('type') || 'default';
    var min = parseInt($(this).data('min'),10) || 1;
    var max = parseInt($(this).data('max'),10) || 0;
    $('#_nfwc_service_type').val(type);
    if(min > 0){ $('#_nfwc_min_quantity').val(min); }
    if(max > 0){ $('#_nfwc_max_quantity').val(max); }
    updateConditionalFields();
  });
  $(document).on('change', '#_nfwc_enabled,#_nfwc_service_type,#_nfwc_quantity_mode', updateConditionalFields);
  $(document).ready(updateConditionalFields);
})(jQuery);
