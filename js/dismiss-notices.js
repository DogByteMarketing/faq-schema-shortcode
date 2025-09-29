jQuery(document).ready(function($) {

  let ajax_url = faq_schema_shortcode_object.ajax_url;
  let nonce    = faq_schema_shortcode_object.nonce;

  $('body').on('click', '.faq-schema-shortcode .notice-dismiss', function() {
    $.post(ajax_url, {
      action: 'faq_schema_shortcode_dismiss_notice_nonce',
      nonce: nonce
    });
  });

});
