jQuery(document).ready(function($) {
  $('.faq-question').on('click', function() {
    var $this      = $(this);
    var $answer    = $this.next('.faq-answer');
    var $icon      = $this.find('.faq-toggle-icon');
    var isExpanded = $this.attr('aria-expanded') === 'true';

    // Toggle answer visibility
    $answer.slideToggle(300);

    // Update ARIA attribute
    $this.attr('aria-expanded', !isExpanded);

    // Update icon
    $icon.text(isExpanded ? '+' : '−');
  });
});