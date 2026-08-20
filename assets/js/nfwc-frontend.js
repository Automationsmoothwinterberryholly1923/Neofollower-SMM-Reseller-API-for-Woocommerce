(function($){
  'use strict';

  function countLines(value){
    if(!value){ return 0; }
    return value.split(/\r\n|\r|\n/).filter(function(line){ return $.trim(line).length > 0; }).length;
  }

  function formatNumber(amount){
    var decimals = parseInt(NFWCFrontend.decimals, 10);
    if(isNaN(decimals)){ decimals = 2; }
    var decimalSeparator = NFWCFrontend.decimalSeparator || '.';
    var thousandSeparator = NFWCFrontend.thousandSeparator || ',';
    var fixed = Math.max(0, amount).toFixed(decimals);
    var parts = fixed.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);
    return parts.join(decimalSeparator);
  }

  function formatPrice(amount){
    var format = NFWCFrontend.priceFormat || '%1$s%2$s';
    var symbol = NFWCFrontend.currencySymbol || '$';
    return format.replace('%1$s', symbol).replace('%2$s', formatNumber(amount));
  }

  function getMultiplier($box){
    var mode = $box.data('nfwc-price-mode') || 'fixed';
    if(mode === 'quantity'){
      return Math.max(1, parseInt($('#nfwc_quantity').val(), 10) || 1);
    }
    if(mode === 'comments'){
      return Math.max(1, countLines($('#nfwc_comments').val()));
    }
    if(mode === 'drip'){
      var runs = Math.max(2, parseInt($('#nfwc_runs').val(), 10) || 2);
      var qty = $('#nfwc_quantity').length ? Math.max(1, parseInt($('#nfwc_quantity').val(), 10) || 1) : 1;
      return Math.max(1, qty * runs);
    }
    return 1;
  }

  function updateCounter(){
    var $textarea = $('[data-nfwc-comment-counter]');
    if(!$textarea.length){ return; }
    var count = countLines($textarea.val());
    var label = count === 1 ? (NFWCFrontend.commentSingular || 'comment') : (NFWCFrontend.commentPlural || 'comments');
    $('.nfwc-comment-count').text(count + ' ' + label);
  }

  function updatePriceCalculator(){
    var $box = $('.nfwc-product-fields');
    var $amount = $('.nfwc-price-calculator__amount');
    if(!$box.length || !$amount.length){ return; }
    var unitPrice = parseFloat($box.data('nfwc-unit-price')) || 0;
    var total = unitPrice * getMultiplier($box);
    $amount.html(formatPrice(total));
  }

  function updateAll(){
    updateCounter();
    updatePriceCalculator();
  }

  $(document).on('input change', '#nfwc_quantity,#nfwc_comments,#nfwc_runs,#nfwc_interval', updateAll);
  $(document).ready(updateAll);
})(jQuery);
