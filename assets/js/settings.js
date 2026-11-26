jQuery(document).ready(function($) {
  var $root = $('.faq-schema-settings');
  var toastTimer = null;
  var saving = false;
  var savedMessage = '';

  if (!$root.length) {
    return;
  }

  initAppearance();
  initTabs();
  initCodeExample();
  initPluginSlider();
  initSupport();
  initSave();

  function initAppearance() {
    var $panel = $('#faq-schema-panel-appearance');
    var $preview = $('#faq-schema-preview');
    var $stage = $panel.find('.faq-schema-appearance__preview');

    // Iris measures the input while it is visible. Keep the panel off-screen so the first tab stays General.
    $panel.addClass('is-measuring').removeAttr('hidden');
    $panel.find('.faq-schema-color').wpColorPicker({
      change: function() {
        window.setTimeout(applyPreview, 0);
      },
      clear: function() {
        window.setTimeout(applyPreview, 0);
      }
    });
    $panel.removeClass('is-measuring').attr('hidden', 'hidden');

    function labelValue(id) {
      return $.trim($('#' + id).val());
    }

    function labeledText(label, text) {
      if (!label) {
        return text;
      }

      return label + ' ' + text;
    }

    function colorValue(id, fallback) {
      var value = $.trim($('#' + id).val());

      return value || fallback;
    }

    function applyPreview() {
      var accordion = $('#accordion').is(':checked');
      var questionLabel = labelValue('question_label');
      var answerLabel = labelValue('answer_label');

      $preview.toggleClass('is-accordion', accordion);
      $stage.css({
        '--faq-preview-text': colorValue('accordion_text_color', '#1d2327'),
        '--faq-preview-bg': colorValue('accordion_background_color', '#f7f7f7'),
        '--faq-preview-hover': colorValue('accordion_background_hover_color', '#e0e0e0')
      });

      $preview.find('.faq-schema-preview__item').each(function() {
        var $item = $(this);
        var question = $item.find('.faq-schema-preview__question').attr('data-question');
        var answer = $item.find('.faq-schema-preview__answer').attr('data-answer');

        $item.find('.faq-schema-preview__q').text(labeledText(questionLabel, question));
        $item.find('.faq-schema-preview__a').text(labeledText(answerLabel, answer));
      });

      $stage.find('.faq-schema-preview__hover-text').text(labeledText(questionLabel, $stage.find('.faq-schema-preview__hover').attr('data-question')));
    }

    function resetPreviewItems() {
      $preview.find('.faq-schema-preview__item').each(function(index) {
        var open = index === 0;

        $(this).toggleClass('is-open', open);
        $(this).find('.faq-schema-preview__question').attr('aria-expanded', open ? 'true' : 'false');
        $(this).find('.faq-schema-preview__icon').text(open ? '\u2212' : '+');
      });
    }

    $root.on('input change', '#question_label, #answer_label, #accordion_text_color, #accordion_background_color, #accordion_background_hover_color', applyPreview);
    $root.on('change', '#accordion', function() {
      if (this.checked) {
        resetPreviewItems();
      }

      applyPreview();
    });
    $root.on('click', '.faq-schema-preview__question', function() {
      var $item = $(this).closest('.faq-schema-preview__item');
      var open = !$item.hasClass('is-open');

      if (!$('#accordion').is(':checked')) {
        return;
      }

      $item.toggleClass('is-open', open);
      $(this).attr('aria-expanded', open ? 'true' : 'false');
      $(this).find('.faq-schema-preview__icon').text(open ? '\u2212' : '+');
    });
    $root.on('click', '.faq-schema-preview__reset', resetPreviewItems);

    applyPreview();
  }

  function initTabs() {
    function tabHash($tab) {
      return $tab.attr('id').replace('faq-schema-tab-', '');
    }

    function showTab($tab, updateHash) {
      var target = $tab.data('tab');
      var hash = tabHash($tab);

      $root.find('.faq-schema-nav__tab').removeClass('is-active').attr('aria-selected', 'false');
      $tab.addClass('is-active').attr('aria-selected', 'true');

      $root.find('.faq-schema-panel').removeClass('is-active').attr('hidden', 'hidden');
      $root.find(target).addClass('is-active').removeAttr('hidden');

      $root.find('.faq-schema-settings__title').text($.trim($tab.find('.faq-schema-nav__label').text()));
      $root.find('.faq-schema-settings__summary').text($tab.attr('data-description'));

      if (!updateHash || !window.history || !window.history.replaceState) {
        return;
      }

      window.history.replaceState(null, '', window.location.pathname + window.location.search + '#' + hash);
    }

    function tabFromHash() {
      var hash = window.location.hash.replace('#', '');

      if (!/^[a-z0-9-]+$/i.test(hash)) {
        return $();
      }

      return $root.find('#faq-schema-tab-' + hash);
    }

    $root.on('click', '.faq-schema-nav__tab', function(e) {
      e.preventDefault();
      showTab($(this), true);
    });

    $(window).on('hashchange.faqSchemaTabs', function() {
      var $tab = tabFromHash();

      if ($tab.length) {
        showTab($tab, false);
      }
    });

    var $initial = tabFromHash();

    if ($initial.length) {
      showTab($initial, false);
    }
  }

  function initCodeExample() {
    $root.on('click', '.faq-schema-code-switch__button', function() {
      var example = $(this).data('example');

      if ($(this).attr('aria-pressed') === 'true') {
        return;
      }

      switchCodeExample(example);
    });

    $root.on('click', '.faq-schema-general__copy', function() {
      var button = this;
      var text = $.trim($root.find('.faq-schema-code:not([hidden])').text());
      var $label = $(button).find('.faq-schema-general__copy-label');
      var original = $label.text();

      function copied() {
        $label.text(button.getAttribute('data-copied'));
        window.setTimeout(function() {
          $label.text(original);
        }, 2000);
      }

      function fallbackCopy() {
        var area = document.createElement('textarea');
        area.value = text;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        document.body.removeChild(area);
        copied();
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(copied).catch(fallbackCopy);
        return;
      }

      fallbackCopy();
    });

    $root.on('change', '#shortcode_alias', function() {
      var example = this.checked ? 'alias' : 'dbm';
      var $open = $root.find('.faq-schema-code-switch__button[aria-pressed="true"]');

      if (!$open.length || $open.data('example') === example) {
        return;
      }

      switchCodeExample(example);
    });
  }

  function switchCodeExample(example) {
    var $button = $root.find('.faq-schema-code-switch__button[data-example="' + example + '"]');
    var $code = $root.find('.faq-schema-code[data-example="' + example + '"]');

    $root.find('.faq-schema-code-switch__button').removeClass('is-active').attr('aria-pressed', 'false');
    $root.find('.faq-schema-code').attr('hidden', 'hidden');

    $button.addClass('is-active').attr('aria-pressed', 'true');
    $code.removeAttr('hidden');
  }

  function initSupport() {
    var prompt = $root.find('.faq-schema-review-prompt').get(0);
    var promptTrigger = null;

    // 1 to 3 stars ask for help. 4 and 5 stay as review links.
    $root.on('click', '.faq-schema-support__star', function(e) {
      var rating = parseInt(this.getAttribute('data-rating'), 10);

      if (!prompt || rating > 3) {
        return;
      }

      e.preventDefault();
      promptTrigger = this;

      if (typeof prompt.showModal === 'function' && !prompt.open) {
        prompt.showModal();
      }
    });

    if (prompt) {
      $(prompt).on('click', function(e) {
        if (e.target === prompt) {
          prompt.close();
        }
      });

      $(prompt).on('click', '.faq-schema-review-prompt__close, .faq-schema-review-prompt__reach', function() {
        window.setTimeout(function() {
          if (prompt.open) {
            prompt.close();
          }
        }, 0);
      });

      $(prompt).on('close', function() {
        if (promptTrigger) {
          promptTrigger.focus();
          promptTrigger = null;
        }
      });
    }

    $root.on('click', '.faq-schema-support__copy', function() {
      var button = this;
      var text = button.getAttribute('data-copy');
      var $label = $(button).find('.faq-schema-support__copy-label');
      var original = $label.text();

      function copied() {
        $label.text(button.getAttribute('data-copied'));
        window.setTimeout(function() {
          $label.text(original);
        }, 2000);
      }

      function fallbackCopy() {
        var area = document.createElement('textarea');
        area.value = text;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        document.body.removeChild(area);
        copied();
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(copied).catch(fallbackCopy);
        return;
      }

      fallbackCopy();
    });
  }

  function initPluginSlider() {
    var $slider = $root.find('.faq-schema-plugins');
    var $track = $slider.find('.faq-schema-plugins__track');
    var $viewport = $slider.find('.faq-schema-plugins__viewport');
    var $dots = $slider.find('.faq-schema-plugins__dot');
    var count = $dots.length;
    var position = 1;
    var timer = null;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!$slider.length || count < 2) {
      return;
    }

    var $slides = $track.children('.faq-schema-plugins__slide');

    $track.prepend($slides.last().clone().attr('aria-hidden', 'true'));
    $track.append($slides.first().clone().attr('aria-hidden', 'true'));

    function showSlide(next, animate) {
      if (reduceMotion) {
        if (next < 1) {
          next = count;
        } else if (next > count) {
          next = 1;
        }

        animate = false;
      }

      position = next;

      if (animate === false) {
        $track.css('transition', 'none');
      }

      $track.css('transform', 'translateX(' + (position * -$viewport.innerWidth()) + 'px)');

      if (animate === false) {
        $track[0].offsetHeight;
        $track.css('transition', '');
      }

      var dot = position - 1;

      if (position === 0) {
        dot = count - 1;
      } else if (position > count) {
        dot = 0;
      }

      $dots.removeClass('is-active').attr('aria-selected', 'false');
      $dots.eq(dot).addClass('is-active').attr('aria-selected', 'true');
    }

    $track.on('transitionend', function(e) {
      if (e.target !== $track[0] || e.originalEvent.propertyName !== 'transform') {
        return;
      }

      if (position === 0) {
        showSlide(count, false);
      } else if (position === count + 1) {
        showSlide(1, false);
      }
    });

    function queue() {
      if (reduceMotion) {
        return;
      }

      clearInterval(timer);
      timer = setInterval(function() {
        showSlide(position + 1);
      }, 6000);
    }

    showSlide(1, false);

    $(window).on('resize.faqSchemaPlugins', function() {
      showSlide(position, false);
    });

    $slider.on('click', '.faq-schema-plugins__dot', function() {
      showSlide($(this).index() + 1);
      queue();
    });

    $slider.on('mouseenter', function() {
      clearInterval(timer);
    });

    $slider.on('mouseleave', queue);

    var dragging = false;
    var startX = 0;
    var deltaX = 0;
    var width = 0;
    var pointerId = null;
    var suppressClick = false;

    $viewport.on('pointerdown', function(e) {
      if (e.pointerType === 'mouse' && e.button !== 0) {
        return;
      }

      if ($(e.target).closest('a, button').length) {
        return;
      }

      dragging = true;
      startX = e.clientX;
      deltaX = 0;
      width = $viewport.outerWidth();
      pointerId = e.pointerId;
      clearInterval(timer);
      $viewport.addClass('is-dragging');
      $track.css('transition', 'none');

      if ($viewport[0].setPointerCapture) {
        try {
          $viewport[0].setPointerCapture(e.pointerId);
        } catch (err) {
          // Some pointer events cannot be captured. Dragging still follows the move events.
        }
      }
    });

    $viewport.on('pointermove', function(e) {
      var offset = 0;

      if (!dragging || e.pointerId !== pointerId) {
        return;
      }

      deltaX = e.clientX - startX;
      offset = deltaX;

      $track.css('transform', 'translateX(' + ((position * -$viewport.innerWidth()) + offset) + 'px)');
    });

    $viewport.on('pointerup pointercancel', function(e) {
      var threshold = 0;

      if (!dragging || e.pointerId !== pointerId) {
        return;
      }

      dragging = false;
      pointerId = null;
      $viewport.removeClass('is-dragging');
      $track.css('transition', '');
      $track[0].offsetHeight;
      threshold = Math.max(40, width * 0.15);

      if (deltaX <= -threshold) {
        showSlide(position + 1);
      } else if (deltaX >= threshold) {
        showSlide(position - 1);
      } else {
        showSlide(position);
      }

      if (Math.abs(deltaX) > 8) {
        suppressClick = true;
      }

      if (e.pointerType !== 'mouse') {
        queue();
      }
    });

    $viewport.on('click', 'a, button', function(e) {
      if (!suppressClick) {
        return;
      }

      e.preventDefault();
      e.stopPropagation();
      suppressClick = false;
    });

    $viewport.on('dragstart', function(e) {
      e.preventDefault();
    });

    queue();
  }

  function initSave() {
    $root.on('submit', 'form', function(e) {
      e.preventDefault();
      saveSettings();
    });
  }

  function saveSettings() {
    if (saving) {
      return;
    }

    saving = true;
    savedMessage = '';
    setSaving(true);

    var data = $root.find('form').serializeArray().filter(function(field) {
      return field.name !== 'action';
    });

    data.push({
      name: 'action',
      value: 'faq_schema_shortcode_save_settings'
    });

    $.post(ajaxurl, $.param(data))
      .done(function(response) {
        if (response && response.success && response.data) {
          savedMessage = response.data.message;
        }
      })
      .always(function() {
        saving = false;
        setSaving(false);

        if (savedMessage) {
          showToast(savedMessage);
        }
      });
  }

  function setSaving(isSaving) {
    $root.toggleClass('is-saving', isSaving).attr('aria-busy', isSaving ? 'true' : 'false');
    $root.find('.faq-schema-save').prop('disabled', isSaving);

    if (isSaving) {
      $root.find('.faq-schema-toast').removeClass('is-visible');
      clearTimeout(toastTimer);
    }
  }

  function showToast(message) {
    var $toast = $root.find('.faq-schema-toast');

    $toast.text(message).addClass('is-visible');

    clearTimeout(toastTimer);
    toastTimer = setTimeout(function() {
      $toast.removeClass('is-visible');
    }, 2500);
  }
});
