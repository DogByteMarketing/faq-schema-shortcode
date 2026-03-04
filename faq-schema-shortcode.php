<?php

/**
 * Plugin Name: FAQ Schema Shortcode
 * Plugin URI: https://www.dogbytemarketing.com/contact/
 * Description: Quickly add FAQ sections compatible with structured data to your site using simple shortcodes, improving your SEO.
 * Author: Dog Byte Marketing
 * Version: 1.2.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author URI: https://www.dogbytemarketing.com
 * Text Domain: faq-schema-shortcode
 * License: GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace DogByteMarketing;

register_activation_hook(__FILE__, array(__NAMESPACE__ . '\FAQ_Schema_Shortcode', 'activation'));

class FAQ_Schema_Shortcode
{
  
  /**
	 * Full path and filename of plugin.
	 *
	 * @var string $version Full path and filename of plugin.
	 */
  private $plugin;
  
  /**
	 * The version of this plugin.
	 *
	 * @var   string $version The current version of this plugin.
	 */
	private $version;

  private $settings = array();
  private $faq_items = array();

  public function __construct() {
    $this->plugin   = __FILE__;
    $this->version  = $this->get_plugin_version();
    $this->settings = get_option('faq_schema_shortcode_dogbytemarketing_settings');
  }
  
  /**
   * Initialize
   *
   * @return void
   */
  public function init() {
    $shortcode_alias = isset($this->settings['shortcode_alias']) ? sanitize_text_field($this->settings['shortcode_alias']) : false;
    $content_faqs    = isset($this->settings['content_faqs']) ? sanitize_text_field($this->settings['content_faqs']) : '';

		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_init', array($this, 'settings_init'));
    
    if ($content_faqs) {
      add_action('add_meta_boxes', array($this, 'register_faq_meta_box'));
      add_action('save_post', array($this, 'save_faq_meta'));
      add_action('init', array($this, 'register_term_faq_support'), 20);
    }

    add_shortcode('faqs_dbm', array($this, 'faq_container_shortcode'));
    add_shortcode('faq_dbm', array($this, 'faq_shortcode'));

    if ($shortcode_alias) {
      add_shortcode('faqs', array($this, 'faq_container_shortcode'));
      add_shortcode('faq', array($this, 'faq_shortcode'));
    }

    add_action('wp_enqueue_scripts', array($this, 'enqueue'));
    add_action('admin_enqueue_scripts', array($this, 'admin_enqueue'));
    
    add_action('wp', array($this, 'maybe_update'));
    add_action('admin_notices', array($this, 'maybe_show_notice'));
    add_action('wp_ajax_faq_schema_shortcode_dismiss_notice_nonce', array($this, 'dismiss_notice'));
  }

  /**
   * Enqueue scripts and styles
   *
   * @return void
   */
  public function enqueue() {
    global $post;

    $needs_assets = false;

    if (isset($post->post_content) &&
      (
        has_shortcode($post->post_content, 'faqs_dbm') ||
        has_shortcode($post->post_content, 'faq_dbm') ||
        has_shortcode($post->post_content, 'faqs') ||
        has_shortcode($post->post_content, 'faq')
      )
    ) {
      $needs_assets = true;
    }

    if (!$needs_assets) {
      $content_faqs = isset($this->settings['content_faqs']) ? sanitize_text_field($this->settings['content_faqs']) : '';
      $term         = get_queried_object();
      if ($content_faqs && $term instanceof \WP_Term && in_array($term->taxonomy, $this->get_faq_taxonomies(), true)) {
        $term_faqs = get_term_meta($term->term_id, '_faqs_dogbytemarketing', true);
        if (!empty($term_faqs) && is_array($term_faqs)) {
          $needs_assets = true;
        }
      }
    }

    if ($needs_assets) {
      $accordion = isset($this->settings['accordion']) ? sanitize_text_field($this->settings['accordion']) : '';

      if ($accordion) {
        wp_enqueue_style('faq-schema-shortcode-dogbytemarketing', plugins_url('/css/style.css', __FILE__), array(), $this->version);
        wp_enqueue_script('faq-schema-shortcode-dogbytemarketing', plugins_url('/js/main.js', __FILE__), array('jquery', 'wp-i18n'), $this->version, true);
      }

      $additional_css = isset($this->settings['additional_css']) ? sanitize_text_field($this->settings['additional_css']) : '';

      if ($additional_css) {
        wp_register_style('faq-schema-shortcode-custom-dogbytemarketing', false, array(), $this->version);
        wp_enqueue_style('faq-schema-shortcode-custom-dogbytemarketing');
        wp_add_inline_style('faq-schema-shortcode-custom-dogbytemarketing', $additional_css);
      }
    }
  }
  
  /**
   * Admin enqueue
   *
   * @param  mixed $hook
   * @return void
   */
  public function admin_enqueue($hook) {
    $content_faqs = isset($this->settings['content_faqs']) ? sanitize_text_field($this->settings['content_faqs']) : '';

    if ($content_faqs) {
      if ($hook === 'post.php' || $hook === 'post-new.php' || $hook === 'term.php' || $hook === 'edit-tags.php') {
        wp_enqueue_editor();
        
        wp_enqueue_style('faq-schema-shortcode-admin-dogbytemarketing', plugins_url('/css/admin.css', __FILE__), array(), $this->version);
        wp_enqueue_script('faq-schema-shortcode-admin-dogbytemarketing', plugins_url('/js/admin.js', __FILE__), array('jquery', 'wp-editor'), $this->version, true);
      }
    }
    
    wp_enqueue_script('faq-schema-shortcode-dismiss-notices', plugins_url('/js/dismiss-notices.js', __FILE__), array('jquery'), $this->version, true);
    wp_localize_script(
      'faq-schema-shortcode-dismiss-notices',
      'faq_schema_shortcode_object',
      array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('faq_schema_shortcode_dismiss_notice_nonce')
      )
    );
  }

  /**
   * Register FAQ meta box
   *
   * @return void
   */
  public function register_faq_meta_box() {
    $post_types = get_post_types(array('public' => true), 'names');

    foreach ($post_types as $post_type) {
      add_meta_box(
        'faq_schema_meta_box',
        __('FAQs', 'faq-schema-shortcode'),
        array($this, 'render_faq_meta_box'),
        $post_type,
        'normal',
        'high'
      );
    }
  }

  /**
   * Render FAQ meta box
   *
   * @param  mixed $post
   * @return void
   */
  public function render_faq_meta_box($post) {
    $faqs            = get_post_meta($post->ID, '_faqs_dogbytemarketing', true);
    $shortcode_alias = isset($this->settings['shortcode_alias']) ? sanitize_text_field($this->settings['shortcode_alias']) : '';
    wp_nonce_field('save_faq_schema', 'faq_schema_nonce');
    ?>
    <div id="faq-schema-wrapper">
      <?php if (!empty($faqs) && is_array($faqs)) : ?>
        <?php foreach ($faqs as $i => $faq) : ?>
          <div class="faq-schema-item">
            <div class="faq-schema-question">
              <span class="faq-schema-question-title"><?php echo esc_html($faq['question']); ?></span>
              <div class="faq-schema-actions">
                <a href="javascript:void(0);" class="trash">
                  <span class="dashicons dashicons-trash"></span>
                </a>
                <span class="caret"><span class="dashicons dashicons-arrow-down-alt2"></span></span>
              </div>
            </div>
            <div class="faq-schema-answer">
              <p><input type="text" class="question" name="faq_schema[<?php echo esc_html($i); ?>][question]" value="<?php echo esc_attr($faq['question']); ?>" placeholder="<?php esc_attr_e('Question', 'faq-schema-shortcode'); ?>" style="width:100%;" /></p>
              <p><?php wp_editor($faq['answer'], 'faq_schema_' . $i, array('textarea_name' => 'faq_schema[' . $i . '][answer]', 'media_buttons' => true, 'textarea_rows' => 4)); ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <button type="button" class="add-faq button"><?php esc_html_e('Add FAQ', 'faq-schema-shortcode'); ?></button>
    <p>Add <?php echo !empty($shortcode_alias) ? '[faqs]' : '[faqs_dbm]'; ?> shortcode where you would like to display the FAQs.</p>
    <?php
  }
  
  /**
   * Save FAQ meta
   *
   * @param  mixed $post_id
   * @return void
   */
  public function save_faq_meta($post_id) {
    if (!isset($_POST['faq_schema_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['faq_schema_nonce'])), 'save_faq_schema')) {
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['faq_schema']) && is_array($_POST['faq_schema'])) {
      $faq_schema = map_deep(wp_unslash($_POST['faq_schema']), 'wp_kses_post');
      $faqs       = array();

      foreach ($faq_schema as $faq) {
        if (!empty($faq['question']) || !empty($faq['answer'])) {
          $faqs[] = array(
            'question' => $faq['question'],
            'answer'   => $faq['answer']
          );
        }
      }

      update_post_meta($post_id, '_faqs_dogbytemarketing', $faqs);
    } else {
      delete_post_meta($post_id, '_faqs_dogbytemarketing');
    }
  }

  /**
   * Register term form hooks for FAQ support. Runs on init priority 20 so taxonomies
   * like product_cat (WooCommerce) are registered first.
   *
   * @return void
   */
  public function register_term_faq_support() {
    foreach ($this->get_faq_taxonomies() as $taxonomy) {
      add_action($taxonomy . '_edit_form', array($this, 'render_faq_term_form'), 10, 2);
      add_action('edited_' . $taxonomy, array($this, 'save_faq_term_meta'), 10, 2);
      add_action('created_' . $taxonomy, array($this, 'save_faq_term_meta'), 10, 2);
    }
  }

  /**
   * Taxonomies that get the FAQ meta box on term edit (e.g. category, product_cat).
   *
   * @return array
   */
  private function get_faq_taxonomies() {
    $taxonomies = array('category');
    if (taxonomy_exists('product_cat')) {
      $taxonomies[] = 'product_cat';
    }
    return $taxonomies;
  }

  /**
   * Render FAQ box on term edit form.
   *
   * @param  \WP_Term $term
   * @param  string   $taxonomy
   * @return void
   */
  public function render_faq_term_form($term, $taxonomy) {
    $faqs = get_term_meta($term->term_id, '_faqs_dogbytemarketing', true);
    $this->render_faq_term_fields($faqs);
  }

  /**
   * Render FAQ fields on term add form.
   *
   * @param  string $taxonomy
   * @return void
   */
  public function render_faq_term_add_form($taxonomy) {
    $this->render_faq_term_fields(array());
  }

  /**
   * Output FAQ fields markup for term forms (shared by edit and add).
   *
   * @param  array $faqs
   * @return void
   */
  private function render_faq_term_fields($faqs) {
    $shortcode_alias = isset($this->settings['shortcode_alias']) ? sanitize_text_field($this->settings['shortcode_alias']) : '';
    wp_nonce_field('save_faq_schema', 'faq_schema_nonce');
    ?>
    <div id="faq_schema_meta_box" class="postbox tax">
      <div class="postbox-header"><h2 class="hndle"><?php echo esc_html(__('FAQs', 'faq-schema-shortcode')); ?></h2></div>
      <div class="inside">
    <div id="faq-schema-wrapper">
      <?php if (!empty($faqs) && is_array($faqs)) : ?>
        <?php foreach ($faqs as $i => $faq) : ?>
          <div class="faq-schema-item">
            <div class="faq-schema-question">
              <span class="faq-schema-question-title"><?php echo esc_html($faq['question']); ?></span>
              <div class="faq-schema-actions">
                <a href="javascript:void(0);" class="trash">
                  <span class="dashicons dashicons-trash"></span>
                </a>
                <span class="caret"><span class="dashicons dashicons-arrow-down-alt2"></span></span>
              </div>
            </div>
            <div class="faq-schema-answer">
              <p><input type="text" class="question" name="faq_schema[<?php echo esc_html($i); ?>][question]" value="<?php echo esc_attr($faq['question']); ?>" placeholder="<?php esc_attr_e('Question', 'faq-schema-shortcode'); ?>" style="width:100%;" /></p>
              <p><?php wp_editor($faq['answer'], 'faq_schema_' . $i, array('textarea_name' => 'faq_schema[' . $i . '][answer]', 'media_buttons' => true, 'textarea_rows' => 4)); ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <button type="button" class="add-faq button"><?php esc_html_e('Add FAQ', 'faq-schema-shortcode'); ?></button>
    <p>Add <?php echo !empty($shortcode_alias) ? '[faqs]' : '[faqs_dbm]'; ?> shortcode where you would like to display the FAQs (e.g. in the term archive template).</p>
      </div>
    </div>
    <?php
  }

  /**
   * Save FAQ term meta when term is created or updated.
   *
   * @param  int $term_id
   * @param  int $tt_id
   * @return void
   */
  public function save_faq_term_meta($term_id, $tt_id = 0) {
    if (!isset($_POST['faq_schema_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['faq_schema_nonce'])), 'save_faq_schema')) {
      return;
    }

    if (!current_user_can('edit_term', $term_id)) {
      return;
    }

    if (isset($_POST['faq_schema']) && is_array($_POST['faq_schema'])) {
      $faq_schema = map_deep(wp_unslash($_POST['faq_schema']), 'wp_kses_post');
      $faqs       = array();

      foreach ($faq_schema as $faq) {
        if (!empty($faq['question']) || !empty($faq['answer'])) {
          $faqs[] = array(
            'question' => $faq['question'],
            'answer'   => $faq['answer']
          );
        }
      }

      update_term_meta($term_id, '_faqs_dogbytemarketing', $faqs);
    } else {
      delete_term_meta($term_id, '_faqs_dogbytemarketing');
    }
  }
  
  /**
   * Handle adding the FAQ container
   *
   * @param  mixed $atts
   * @param  mixed $content
   * @return void
   */
  public function faq_container_shortcode($atts, $content = null) {
    global $post;

    $content_faqs = isset($this->settings['content_faqs']) ? sanitize_text_field($this->settings['content_faqs']) : '';

    if ($content_faqs) {
      $faqs = $this->get_content_faqs_for_current_context();
      if (!empty($faqs) && is_array($faqs)) {
        $custom_field_faqs = $this->custom_field_faqs($faqs);
        return $custom_field_faqs;
      }
    }

    // Reset FAQ items array at the start of each call
    $this->faq_items = [];
    
    // Remove line breaks
    $clean_content = str_replace(array("\r\n", "\r", "\n", "<br />"), '', $content);

    // Process inner shortcodes and capture output
    $output = '<div class="faq-container">' . do_shortcode(wp_kses_post($clean_content)) . '</div>';

    $accordion                        = isset($this->settings['accordion']) ? sanitize_text_field($this->settings['accordion']) : '';
    $accordion_text_color             = isset($this->settings['accordion_text_color']) ? sanitize_hex_color($this->settings['accordion_text_color']) : '';
    $accordion_background_color       = isset($this->settings['accordion_background_color']) ? sanitize_hex_color($this->settings['accordion_background_color']) : '';
    $accordion_background_hover_color = isset($this->settings['accordion_background_hover_color']) ? sanitize_hex_color($this->settings['accordion_background_hover_color']) : '';
    
    if ($accordion) {
      if ($accordion_text_color) {
        $output .= '<style>.faq-question { color: ' . esc_html($accordion_text_color) . '; }</style>';
      }
      if ($accordion_background_color) {
        $output .= '<style>.faq-question { background-color: ' . esc_html($accordion_background_color) . '; }</style>';
      }
      if ($accordion_background_hover_color) {
        $output .= '<style>.faq-question:hover { background-color: ' . esc_html($accordion_background_hover_color) . '; }</style>';
      }
    }

    // If there are FAQ items, generate the JSON-LD schema
    if (!empty($this->faq_items)) {
      $faq_schema = [
        "@context" => "https://schema.org",
        "@type" => "FAQPage",
        "mainEntity" => $this->faq_items
      ];

      // Encode schema as JSON-LD and append to the output
      $output .= '<script type="application/ld+json">' . wp_json_encode($faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    return $output;
  }
  
  /**
   * Handle adding individual FAQs
   *
   * @param  mixed $atts
   * @return void
   */
  public function faq_shortcode($atts)
  {
    // Extract question (q) and answer (a) from shortcode attributes
    $question     = isset($atts['q']) ? $atts['q'] : '';
    $answer       = isset($atts['a']) ? $atts['a'] : '';
    $allowed_html = [
      'a' => [
        'href' => true,
        'title' => true,
        'target' => true,
      ],
      'strong' => [],
      'em' => [],
      'ul' => [],
      'ol' => [],
      'li' => [],
    ];

    if ($question && $answer) {
      // Add each Q&A pair to the FAQ items array for JSON-LD schema
      $this->faq_items[] = [
        "@type" => "Question",
        "name" => $question,
        "acceptedAnswer" => [
          "@type" => "Answer",
          "text" => $answer
        ]
      ];
    }

    $accordion      = isset($this->settings['accordion']) ? sanitize_text_field($this->settings['accordion']) : '';
    $question_label = isset($this->settings['question_label']) ? sanitize_text_field($this->settings['question_label']) : __('Q:', 'faq-schema-shortcode');
    $answer_label   = isset($this->settings['answer_label']) ? sanitize_text_field($this->settings['answer_label']) : __('A:', 'faq-schema-shortcode');

    ob_start();

    // Return HTML output for each FAQ item
    if ($accordion) {
      ?>
      <div class="faq-item">
        <p class="faq-question" aria-expanded="false">
          <span><?php echo wp_kses($question, $allowed_html); ?></span>
          <span class="faq-toggle-icon">+</span>
        </p>
        <p class="faq-answer" style="display: none;">
          <?php echo wp_kses($answer, $allowed_html); ?>
        </p>
      </div>
      <?php
    } else {
      ?>
      <div class="faq-item">
        <p class="faq-question"><strong><?php echo esc_html($question_label); ?> <?php echo wp_kses($question, $allowed_html); ?></strong></p>
        <p class="faq-answer"><?php echo esc_html($answer_label); ?> <?php echo wp_kses($answer, $allowed_html); ?></p>
      </div>
      <?php
    }

    return ob_get_clean();
  }

	/**
	 * Add admin menu to backend
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page('options-general.php', __('FAQ Shortcode', 'faq-schema-shortcode'), __('FAQ Shortcode', 'faq-schema-shortcode'), 'manage_options', 'faq-schema-shortcode', array($this, 'options_page'));
	}

  /**
	 * Initialize Settings
	 *
	 * @return void
	 */
	public function settings_init() {
		register_setting(
			'faq_schema_shortcode_dogbytemarketing',
			'faq_schema_shortcode_dogbytemarketing_settings', 
			array($this, 'sanitize')
		);

    add_settings_section(
			'faq_schema_shortcode_dogbytemarketing_section',
			'',
			'',
			'faq_schema_shortcode_dogbytemarketing'
		);
    
    add_settings_field(
      'shortcode_alias',
      __('Shortcode Alias', 'faq-schema-shortcode'),
      array($this, 'shortcode_alias_render'),
      'faq_schema_shortcode_dogbytemarketing',
      'faq_schema_shortcode_dogbytemarketing_section'
    );
    
    add_settings_field(
      'content_faqs',
      __('Content FAQs', 'faq-schema-shortcode'),
      array($this, 'content_faqs_render'),
      'faq_schema_shortcode_dogbytemarketing',
      'faq_schema_shortcode_dogbytemarketing_section'
    );
    
    add_settings_field(
      'question_label',
      __('Question Label', 'faq-schema-shortcode'),
      array($this, 'question_label_render'),
      'faq_schema_shortcode_dogbytemarketing',
      'faq_schema_shortcode_dogbytemarketing_section'
    );
    
    add_settings_field(
      'answer_label',
      __('Answer Label', 'faq-schema-shortcode'),
      array($this, 'answer_label_render'),
      'faq_schema_shortcode_dogbytemarketing',
      'faq_schema_shortcode_dogbytemarketing_section'
    );
    
    add_settings_field(
      'accordion',
      __('Accordion', 'faq-schema-shortcode'),
      array($this, 'accordion_render'),
      'faq_schema_shortcode_dogbytemarketing',
      'faq_schema_shortcode_dogbytemarketing_section'
    );
    
    add_settings_field(
      'accordion_text_color',
      __('Accordion Text Color', 'faq-schema-shortcode'),
      array($this, 'accordion_text_color_render'),
      'faq_schema_shortcode_dogbytemarketing',
      'faq_schema_shortcode_dogbytemarketing_section'
    );
    
    add_settings_field(
      'accordion_background_color',
      __('Accordion Background Color', 'faq-schema-shortcode'),
      array($this, 'accordion_background_color_render'),
      'faq_schema_shortcode_dogbytemarketing',
      'faq_schema_shortcode_dogbytemarketing_section'
    );
    
    add_settings_field(
      'accordion_background_hover_color',
      __('Accordion Background Hover Color', 'faq-schema-shortcode'),
      array($this, 'accordion_background_hover_color_render'),
      'faq_schema_shortcode_dogbytemarketing',
      'faq_schema_shortcode_dogbytemarketing_section'
    );
    
    add_settings_field(
      'additional_css',
      __('Additional CSS', 'faq-schema-shortcode'),
      array($this, 'additional_css_render'),
      'faq_schema_shortcode_dogbytemarketing',
      'faq_schema_shortcode_dogbytemarketing_section'
    );
	}

	/**
	 * Render Shortcode Alias Field
	 *
	 * @return void
	 */
	public function shortcode_alias_render() {
    $shortcode_alias = isset($this->settings['shortcode_alias']) ? sanitize_text_field($this->settings['shortcode_alias']) : '';
	  ?>
    <div style="padding-top: 6px;">
      <input type="checkbox" name="faq_schema_shortcode_dogbytemarketing_settings[shortcode_alias]" id="shortcode_alias" <?php checked(1, $shortcode_alias, true); ?> /> Enable
      <p><strong><?php esc_html_e('WordPress standards requires a prefix or suffix for shortcodes, but this can be difficult to remember. Check this box to make the shortcode available with', 'faq-schema-shortcode'); ?>:</strong></p>
      <div style="display: inline-block; padding: 10px; background-color: #ccc;">
        [faqs]<br />
        [faq q="<?php esc_html_e('The question', 'faq-schema-shortcode'); ?>" a="<?php esc_html_e('The answer', 'faq-schema-shortcode'); ?>"]<br />
        [/faqs]
      </div>
    </div>
	  <?php
	}

	/**
	 * Render Shortcode Alias Field
	 *
	 * @return void
	 */
	public function content_faqs_render() {
    $content_faqs = isset($this->settings['content_faqs']) ? sanitize_text_field($this->settings['content_faqs']) : '';
	  ?>
    <div style="padding-top: 6px;">
      <input type="checkbox" name="faq_schema_shortcode_dogbytemarketing_settings[content_faqs]" id="content_faqs" <?php checked(1, $content_faqs, true); ?> /> Enable
      <p><strong><?php esc_html_e('This adds a meta box to all post types to allow you to add FAQs easier via input fields.', 'faq-schema-shortcode'); ?></strong></p>
    </div>
	  <?php
	}

	/**
	 * Render Question Label Field
	 *
	 * @return void
	 */
	public function question_label_render() {
    $option = isset($this->settings['question_label']) ? sanitize_text_field($this->settings['question_label']) : __('Q:', 'faq-schema-shortcode');
	  ?>
    <div style="padding-top: 6px;">
      <input type="text" name="faq_schema_shortcode_dogbytemarketing_settings[question_label]" id="question_label" value="<?php echo esc_html($option); ?>" />
      <p><strong><?php esc_html_e('The label for the question.', 'faq-schema-shortcode'); ?><br /><?php esc_html_e('EX', 'faq-schema-shortcode'); ?>: <?php esc_attr_e('Q:', 'faq-schema-shortcode'); ?></strong></p>
    </div>
	  <?php
	}

	/**
	 * Render Answer Label Field
	 *
	 * @return void
	 */
	public function answer_label_render() {
    $option = isset($this->settings['answer_label']) ? sanitize_text_field($this->settings['answer_label']) : __('A:', 'faq-schema-shortcode');
	  ?>
    <div style="padding-top: 6px;">
      <input type="text" name="faq_schema_shortcode_dogbytemarketing_settings[answer_label]" id="answer_label" value="<?php echo esc_html($option); ?>" />
      <p><strong><?php esc_html_e('The label for the answer.', 'faq-schema-shortcode'); ?><br /><?php esc_html_e('EX', 'faq-schema-shortcode'); ?>: <?php esc_attr_e('A:', 'faq-schema-shortcode'); ?></strong></p>
    </div>
	  <?php
	}

	/**
	 * Render Accordion Field
	 *
	 * @return void
	 */
	public function accordion_render() {
    $accordion = isset($this->settings['accordion']) ? sanitize_text_field($this->settings['accordion']) : '';
	  ?>
    <div style="padding-top: 6px;">
      <input type="checkbox" name="faq_schema_shortcode_dogbytemarketing_settings[accordion]" id="accordion" <?php checked(1, $accordion, true); ?> /> Enable
      <p><strong><?php esc_html_e('Makes the FAQ function like an accordion', 'faq-schema-shortcode'); ?></strong></p>
    </div>
	  <?php
	}

	/**
	 * Render Accordion Background Color Field
	 *
	 * @return void
	 */
	public function accordion_text_color_render() {
    $accordion_text_color = isset($this->settings['accordion_text_color']) ? sanitize_hex_color($this->settings['accordion_text_color']) : '';
	  ?>
    <div style="padding-top: 6px;">
      <input type="text" name="faq_schema_shortcode_dogbytemarketing_settings[accordion_text_color]" id="accordion_text_color" value="<?php echo esc_html($accordion_text_color); ?>" />
      <p><strong><?php esc_html_e('The text color of the accordion in hex.', 'faq-schema-shortcode'); ?><br /><?php esc_html_e('EX', 'faq-schema-shortcode'); ?>: #ff0000</strong></p>
    </div>
	  <?php
	}

	/**
	 * Render Accordion Background Color Field
	 *
	 * @return void
	 */
	public function accordion_background_color_render() {
    $accordion_background_color = isset($this->settings['accordion_background_color']) ? sanitize_hex_color($this->settings['accordion_background_color']) : '';
	  ?>
    <div style="padding-top: 6px;">
      <input type="text" name="faq_schema_shortcode_dogbytemarketing_settings[accordion_background_color]" id="accordion_background_color" value="<?php echo esc_html($accordion_background_color); ?>" />
      <p><strong><?php esc_attr_e('The background color of the accordion in hex.', 'faq-schema-shortcode'); ?><br /><?php esc_attr_e('EX', 'faq-schema-shortcode'); ?>: #ff0000</strong></p>
    </div>
	  <?php
	}

	/**
	 * Render Accordion Background Hover Color Field
	 *
	 * @return void
	 */
	public function accordion_background_hover_color_render() {
    $accordion_background_hover_color = isset($this->settings['accordion_background_hover_color']) ? sanitize_hex_color($this->settings['accordion_background_hover_color']) : '';
	  ?>
    <div style="padding-top: 6px;">
      <input type="text" name="faq_schema_shortcode_dogbytemarketing_settings[accordion_background_hover_color]" id="accordion_background_hover_color" value="<?php echo esc_html($accordion_background_hover_color); ?>" />
      <p><strong><?php esc_attr_e('The background color of the accordion in hex.', 'faq-schema-shortcode'); ?><br /><?php esc_attr_e('EX', 'faq-schema-shortcode'); ?>: #ff0000</strong></p>
    </div>
	  <?php
	}

	/**
	 * Render Additional CSS Field
	 *
	 * @return void
	 */
	public function additional_css_render() {
    $option = isset($this->settings['additional_css']) ? sanitize_text_field($this->settings['additional_css']) : '';
	  ?>
    <div style="padding-top: 6px;">
      <textarea name="faq_schema_shortcode_dogbytemarketing_settings[additional_css]" id="additional_css" rows="5" cols="50"><?php echo esc_html($option); ?></textarea>
      <p><strong><?php esc_attr_e('Any additional CSS you want to add.', 'faq-schema-shortcode'); ?><br /><?php esc_attr_e("Note: It would be more beneficial to add the CSS to your theme's stylesheet.", 'faq-schema-shortcode'); ?></strong></p>
    </div>
	  <?php
	}
	
	/**
	 * Render options page
	 *
	 * @return void
	 */
	public function options_page() {
	?>
		<form action='options.php' method='post'>
			<h2><?php esc_attr_e('FAQ Schema Shortcode Settings', 'faq-schema-shortcode'); ?></h2>

			<p style="background-color: #00A32A; text-align: center; padding: 20px; color: #fff; width: 95%;">
				If you need assistance with your Search Engine Optimization efforts, we at <a href="https://www.dogbytemarketing.com" style="text-decoration: none; color: #fff; font-weight: 700;" target="_blank">Dog Byte Marketing</a> are here to help! We offer a wide array of services.<br />Feel free to give us a call at <a href="tel:4237248922" style="text-decoration: none; color: #fff; font-weight: 700;" target="_blank">(423) 724 - 8922</a>.<br />
				We're USA based in Tennessee.</p>
			<?php
			settings_fields('faq_schema_shortcode_dogbytemarketing');
			do_settings_sections('faq_schema_shortcode_dogbytemarketing');
			?>
			<?php
			submit_button();
			?>

		</form>
	<?php
	}

  /**
   * Sanitize Options
   *
   * @param  array $input Array of option inputs
   * @return array $sanitary_values Array of sanitized options
   */
  public function sanitize($input) {
		$sanitary_values = array();

		if (isset($input['shortcode_alias']) && $input['shortcode_alias']) {
      $sanitary_values['shortcode_alias'] = $input['shortcode_alias'] === 'on' ? true : false;
    } else {
      $sanitary_values['shortcode_alias'] = false;
    }

		if (isset($input['content_faqs']) && $input['content_faqs']) {
      $sanitary_values['content_faqs'] = $input['content_faqs'] === 'on' ? true : false;
    } else {
      $sanitary_values['content_faqs'] = false;
    }

		if (isset($input['question_label']) && $input['question_label']) {
			$sanitary_values['question_label'] = sanitize_text_field($input['question_label']);
		}

		if (isset($input['answer_label']) && $input['answer_label']) {
			$sanitary_values['answer_label'] = sanitize_text_field($input['answer_label']);
		}

		if (isset($input['accordion']) && $input['accordion']) {
      $sanitary_values['accordion'] = $input['accordion'] === 'on' ? true : false;
    } else {
      $sanitary_values['accordion'] = false;
    }

		if (isset($input['accordion_text_color']) && $input['accordion_text_color']) {
			$sanitary_values['accordion_text_color'] = sanitize_hex_color($input['accordion_text_color']);
		}

		if (isset($input['accordion_background_color']) && $input['accordion_background_color']) {
			$sanitary_values['accordion_background_color'] = sanitize_hex_color($input['accordion_background_color']);
		}

		if (isset($input['accordion_background_hover_color']) && $input['accordion_background_hover_color']) {
			$sanitary_values['accordion_background_hover_color'] = sanitize_hex_color($input['accordion_background_hover_color']);
		}

		if (isset($input['additional_css']) && $input['additional_css']) {
			$sanitary_values['additional_css'] = sanitize_text_field($input['additional_css']);
		}

    return $sanitary_values;
  }
  
  /**
   * Maybe handle update
   *
   * @return void
   */
  public function maybe_update() {
    $stored_version = get_option('faq_schema_shortcode_version_dogbytemarketing');

    if (!$stored_version) {
      $stored_version = $this->version;

      update_option('faq_schema_shortcode_version_dogbytemarketing', $this->version);
    }

    if (version_compare($stored_version, '1.2.0', '<')) {
      $stored_version = '1.2.0';

      $this->update_to_120();

      update_option('faq_schema_shortcode_version_dogbytemarketing', $stored_version);
    }
  }

  /**
   * Activation
   *
   * @return void
   */
  public static function activation() {
    $settings = get_option('faq_schema_shortcode_dogbytemarketing_settings');

    if (!$settings || !is_array($settings)) {
      $defaults = array('content_faqs' => true);
      add_option('faq_schema_shortcode_dogbytemarketing_settings', $defaults);
      update_option('faq_schema_shortcode_notice_dismissed_dogbytemarketing', true);
    }
  }
  
  /**
   * Maybe show notice
   *
   * @return void
   */
  public function maybe_show_notice() {
    $is_dismissed = get_option('faq_schema_shortcode_notice_dismissed_dogbytemarketing');

    if ($is_dismissed) {
      return;
    }
    
    if ($this->version == '1.2.0') {
      $this->notice_120();
    }
  }
  
  /**
   * Handle dismissing notice
   *
   * @return void
   */
  public function dismiss_notice() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'faq_schema_shortcode_dismiss_notice_nonce')) {
      $this->send_error('Invalid session', 403);
    }

    if (!current_user_can('manage_options')) {
      $this->send_error('Unauthorized', 401);
    }

    update_option('faq_schema_shortcode_notice_dismissed_dogbytemarketing', true);
  }

  /**
   * Send success json
   *
   * @param  string $message The message to send
   * @param  int    $code    The status code
   * @return void
   */
  public function send_success($message, $code = 200) {
    $message = $message ? sanitize_text_field($message) : __('Success', 'faq-schema-shortcode');
    $code    = is_numeric($code) ? (int) $code : 200;

    wp_send_json_success(array(
      'message' => sanitize_text_field($message),
      'status' => $code
    ), $code);
  }
  
  /**
   * Send error json
   *
   * @param  mixed $message
   * @param  mixed $code
   * @return void
   */
  public function send_error($message, $code = 400) {
    $message = $message ? sanitize_text_field($message) : __('Error', 'faq-schema-shortcode');
    $code    = is_numeric($code) ? (int) $code : 400;

    wp_send_json_error(array(
      'message' => sanitize_text_field($message),
      'status' => $code
    ), $code);
  }
  
  /**
   * Notice 1.2.0
   *
   * @return void
   */
  public function notice_120() {
    ?>
    <div class="notice notice-success is-dismissible faq-schema-shortcode" style="padding-bottom: 10px;">
      <p><?php echo esc_html__('FAQ Schema Shortcode has been updated to allow easier management of FAQs. The Content FAQs feature is now enabled by default and gives all post types along with article categories and product categories taxonomies an editor in the backend where you can add FAQs using HTML.', 'faq-schema-shortcode'); ?></p>
      <a href="options-general.php?page=faq-schema-shortcode"><?php echo esc_html__('Disable in Settings', 'faq-schema-shortcode'); ?></a> |
      <a href="https://wordpress.org/plugins/faq-schema-shortcode/#developers" target="_blank"><?php echo esc_html__('View Changelog', 'faq-schema-shortcode'); ?></a>
    </div>
    <?php
  }
  
  /**
   * Update to 1.2.0
   * 
   * @return void
   */
  private function update_to_120() {
    $settings = get_option('faq_schema_shortcode_dogbytemarketing_settings');
    if (!is_array($settings)) {
      $settings = array();
    }
    if (empty($settings['content_faqs'])) {
      $settings['content_faqs'] = true;
      update_option('faq_schema_shortcode_dogbytemarketing_settings', $settings);
    }
    update_option('faq_schema_shortcode_notice_dismissed_dogbytemarketing', false);
  }
  
  /**
   * Get FAQs for current context: term archive (category/product_cat) or current post.
   *
   * @return array
   */
  private function get_content_faqs_for_current_context() {
    $term = get_queried_object();
    if ($term instanceof \WP_Term && in_array($term->taxonomy, $this->get_faq_taxonomies(), true)) {
      $faqs = get_term_meta($term->term_id, '_faqs_dogbytemarketing', true);
      if (!empty($faqs) && is_array($faqs)) {
        return $faqs;
      }
    }

    global $post;
    if (!empty($post->ID)) {
      $faqs = get_post_meta($post->ID, '_faqs_dogbytemarketing', true);
      if (!empty($faqs) && is_array($faqs)) {
        return $faqs;
      }
    }

    return array();
  }

  /**
   * Handle displaying FAQs from custom field.
   *
   * @param  mixed $faqs
   * @return void
   */
  private function custom_field_faqs($faqs) {
    if (empty($faqs) || !is_array($faqs)) {
      return '';
    }

    $this->faq_items = [];
    $output = '<div class="faq-container">';

    $accordion      = isset($this->settings['accordion']) ? sanitize_text_field($this->settings['accordion']) : '';
    $question_label = isset($this->settings['question_label']) ? sanitize_text_field($this->settings['question_label']) : __('Q:', 'faq-schema-shortcode');
    $answer_label   = isset($this->settings['answer_label']) ? sanitize_text_field($this->settings['answer_label']) : __('A:', 'faq-schema-shortcode');

    foreach ($faqs as $faq) {
      $question = wp_kses_post($faq['question']);
      $answer   = wp_kses_post($faq['answer']);

      $this->faq_items[] = [
        "@type" => "Question",
        "name" => $question,
        "acceptedAnswer" => [
          "@type" => "Answer",
          "text" => $answer
        ]
      ];

      $output .= '<div class="faq-item">';

      if ($accordion) {
        $output .= '<p class="faq-question" aria-expanded="false">';
        $output .= '<span>' . wp_kses_post($question, []) . '</span>';
        $output .= '<span class="faq-toggle-icon">+</span>';
        $output .= '</p>';
        $output .= '<div class="faq-answer" style="display: none;">' . wp_kses_post($answer, []) . '</div>';
      } else {
        $output .= '<p class="faq-question"><strong>' . wp_kses_post($question_label) . ' ' . wp_kses_post($question, []) . '</strong></p>';
        $output .= '<p class="faq-answer">' . wp_kses_post($answer_label) . ' ' . wp_kses_post($answer, []) . '</p>';
      }

      $output .= '</div>';
    }

    $output .= '</div>';

    // Accordion styling
    if ($accordion) {
      $accordion_text_color             = isset($this->settings['accordion_text_color']) ? sanitize_hex_color($this->settings['accordion_text_color']) : '';
      $accordion_background_color       = isset($this->settings['accordion_background_color']) ? sanitize_hex_color($this->settings['accordion_background_color']) : '';
      $accordion_background_hover_color = isset($this->settings['accordion_background_hover_color']) ? sanitize_hex_color($this->settings['accordion_background_hover_color']) : '';

      if ($accordion_text_color) {
        $output .= '<style>.faq-question { color: ' . esc_html($accordion_text_color) . '; }</style>';
      }
      if ($accordion_background_color) {
        $output .= '<style>.faq-question { background-color: ' . esc_html($accordion_background_color) . '; }</style>';
      }
      if ($accordion_background_hover_color) {
        $output .= '<style>.faq-question:hover { background-color: ' . esc_html($accordion_background_hover_color) . '; }</style>';
      }
    }

    // Add JSON-LD
    if (!empty($this->faq_items)) {
      $faq_schema = [
        "@context"   => "https://schema.org",
        "@type"      => "FAQPage",
        "mainEntity" => $this->faq_items
      ];

      $output .= '<script type="application/ld+json">' . wp_json_encode($faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    return $output;
  }

  /**
   * Get the plugin version
   *
   * @return string $version The plugin version
   */
  private function get_plugin_version() {
    $plugin_data = get_file_data($this->plugin, array('Version' => 'Version'), false);
    $version     = $plugin_data['Version'];

    return $version;
  }
  
}

// Instantiate and initialize the class
$faq_schema_shortcode = new FAQ_Schema_Shortcode;
$faq_schema_shortcode->init();
