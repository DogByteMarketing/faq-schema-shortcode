jQuery(document).ready(function($) {

  const { __, _x, _n, _nx } = wp.i18n;

  var index = $('#faq-schema-wrapper .faq-schema-item').length;

  init();

  function init() {
    addFAQ();
    removeFAQ();
    toggleFAQ();
    liveUpdateQuestion();
  }

  function addFAQ() {
    $(document).on('click', '.add-faq', function(e){
      e.preventDefault();

      var textareaId = 'faq_schema_' + index;

      var placeholder = __("Question", 'faq-schema-shortcode');

      var newField = `
        <div class="faq-schema-item">
          <div class="faq-schema-question">
            <span class="faq-schema-question-title">New FAQ</span>
            <div class="faq-schema-actions">
              <a href="javascript:void(0);" class="trash">
                <span class="dashicons dashicons-trash"></span>
              </a>
              <span class="caret"><span class="dashicons dashicons-arrow-up-alt2"></span></span>
            </div>
          </div>
          <div class="faq-schema-answer" style="display: block;">
            <p><input type="text" class="question" name="faq_schema[${index}][question]" placeholder="${placeholder}" /></p>
            <p><textarea id="${textareaId}" name="faq_schema[${index}][answer]" rows="4"></textarea></p>
          </div>
        </div>
      `;

      $('#faq-schema-wrapper').append(newField);
      
      if (typeof wp.editor !== 'undefined') {
        wp.editor.initialize(textareaId, {
          tinymce: {
            wpautop: true,
            toolbar1: 'formatselect bold italic bullist numlist blockquote alignleft aligncenter alignright link unlink spellchecker',
          },
          quicktags: true,
          mediaButtons: true
        });
      }

      index++;
    });
  }

  function removeFAQ() {
    $(document).on('click', '#faq_schema_meta_box .trash', function(e) {
      e.preventDefault();
      e.stopPropagation();

      var confirmed = confirm(__("Are you sure you want to remove this question?", 'faq-schema-shortcode'));

      if (!confirmed) {
        return;
      }

      $(this).closest('.faq-schema-item').remove();
    });
  }

  function toggleFAQ() {
    $(document).on('click', '.faq-schema-question', function(){
      var answer = $(this).next('.faq-schema-answer');

      answer.slideToggle(200, function() {
        // update arrow after toggle finishes
        $(this).prev('.faq-schema-question').find('.caret').html(answer.is(':visible') ? '<span class="dashicons dashicons-arrow-up-alt2">' : '<span class="dashicons dashicons-arrow-down-alt2">');
      });
    });
  }

  function liveUpdateQuestion() {
    $(document).on('input', '.faq-schema-answer input[type="text"]', function() {
      var questionText = $(this).val().trim() || __("New FAQ", 'faq-schema-shortcode');

      $(this).closest('.faq-schema-answer').prev('.faq-schema-question').find('.faq-schema-question-title').text(questionText);
    });
  }

});