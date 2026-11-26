<?php

/**
 * Plugin Name: FAQ Schema Shortcode
 * Plugin URI: https://www.dogbytemarketing.com/contact/
 * Description: Quickly add FAQ sections compatible with structured data to your site using simple shortcodes, improving your SEO.
 * Author: Dog Byte Marketing
 * Version: 1.3.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author URI: https://www.dogbytemarketing.com
 * Text Domain: faq-schema-shortcode
 * License: GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace DogByteMarketing;

if ( ! defined( 'ABSPATH' ) ) exit;

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
  private $pending_question = '';

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
    }

    add_shortcode('faqs_dbm', array($this, 'faq_container_shortcode'));
    add_shortcode('faq_dbm', array($this, 'faq_shortcode'));
    add_shortcode('question_dbm', array($this, 'question_shortcode'));
    add_shortcode('answer_dbm', array($this, 'answer_shortcode'));

    if ($shortcode_alias) {
      add_shortcode('faqs', array($this, 'faq_container_shortcode'));
      add_shortcode('faq', array($this, 'faq_shortcode'));
      add_shortcode('question', array($this, 'question_shortcode'));
      add_shortcode('answer', array($this, 'answer_shortcode'));
    }

    add_action('wp_enqueue_scripts', array($this, 'enqueue'));
    add_action('admin_enqueue_scripts', array($this, 'admin_enqueue'));
    
    add_action('wp', array($this, 'maybe_update'));
    add_action('admin_init', array($this, 'maybe_update'));
    add_action('admin_notices', array($this, 'maybe_show_notice'));
    add_action('wp_ajax_faq_schema_shortcode_dismiss_notice_nonce', array($this, 'dismiss_notice'));
    add_action('wp_ajax_faq_schema_shortcode_save_settings', array($this, 'save_settings'));
    add_filter('plugin_action_links_' . plugin_basename($this->plugin), array($this, 'plugin_action_links'));
  }

  /**
   * Enqueue scripts and styles
   *
   * @return void
   */
  public function enqueue() {
    global $post;
    
    if (isset($post->post_content) &&
      (
        has_shortcode($post->post_content, 'faqs_dbm') ||
        has_shortcode($post->post_content, 'faq_dbm') ||
        has_shortcode($post->post_content, 'question_dbm') ||
        has_shortcode($post->post_content, 'answer_dbm') ||
        has_shortcode($post->post_content, 'faqs') ||
        has_shortcode($post->post_content, 'faq') ||
        has_shortcode($post->post_content, 'question') ||
        has_shortcode($post->post_content, 'answer')
      )
    ) {
      $accordion = isset($this->settings['accordion']) ? sanitize_text_field($this->settings['accordion']) : '';

      if ($accordion) {
        wp_enqueue_style('faq-schema-shortcode-dogbytemarketing', plugins_url('/assets/css/style.css', __FILE__), array(), $this->version);
        wp_enqueue_script('faq-schema-shortcode-dogbytemarketing', plugins_url('/assets/js/main.js', __FILE__), array('jquery', 'wp-i18n'), $this->version, true);
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
    $is_editor    = ($hook === 'post.php' || $hook === 'post-new.php');
    $is_settings  = ($hook === 'settings_page_faq-schema-shortcode');

    wp_enqueue_style('faq-schema-shortcode-admin-dogbytemarketing', plugins_url('/assets/css/admin.css', __FILE__), array(), $this->version);

    if ($is_settings) {
      wp_enqueue_style('wp-color-picker');
      wp_enqueue_script(
        'faq-schema-shortcode-settings-dogbytemarketing',
        plugins_url('/assets/js/settings.js', __FILE__),
        array('jquery', 'wp-color-picker'),
        $this->version,
        true
      );
    }

    if ($content_faqs && $is_editor) {
      wp_enqueue_editor();
      wp_enqueue_script('faq-schema-shortcode-admin-dogbytemarketing', plugins_url('/assets/js/admin.js', __FILE__), array('jquery', 'wp-editor'), $this->version, true);
    }
    
    wp_enqueue_script('faq-schema-shortcode-dismiss-notices', plugins_url('/assets/js/dismiss-notices.js', __FILE__), array('jquery'), $this->version, true);
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
    <p>Add <?php echo $shortcode_alias ? '[faqs]' : 'faqs_dbm'; ?> shortcode where you would like to display the FAQs.</p>
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
   * Handle adding the FAQ container
   *
   * @param  mixed $atts
   * @param  mixed $content
   * @return void
   */
  public function faq_container_shortcode($atts, $content = null) {
    global $post;

    $content_faqs = isset($this->settings['content_faqs']) ? sanitize_text_field($this->settings['content_faqs']) : '';
    $prepared     = $this->prepare_faq_content($content);
    $has_inner    = preg_match('/\[(faq|faq_dbm|question|question_dbm|answer|answer_dbm)\b/', $prepared);

    if ($content_faqs && !$has_inner && is_object($post) && isset($post->ID)) {
      $faqs = get_post_meta($post->ID, '_faqs_dogbytemarketing', true);

      if (!empty($faqs) && is_array($faqs)) {
        return $this->custom_field_faqs($faqs);
      }
    }

    // Reset FAQ items array at the start of each call
    $this->faq_items = [];
    $this->pending_question = '';

    // Process inner shortcodes and capture output
    $output = '<div class="faq-container">' . do_shortcode(wp_kses_post($prepared)) . '</div>';

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
  public function faq_shortcode($atts) {
    $question = isset($atts['q']) ? $atts['q'] : '';
    $answer   = isset($atts['a']) ? $atts['a'] : '';

    return $this->render_faq_item($question, $answer);
  }

  /**
   * Store a question until the following answer shortcode renders it.
   *
   * @param  mixed $atts
   * @param  mixed $content
   * @return string
   */
  public function question_shortcode($atts, $content = null) {
    $this->pending_question = $this->sanitize_faq_text($content);

    return '';
  }

  /**
   * Render the answer with the question captured before it.
   *
   * @param  mixed $atts
   * @param  mixed $content
   * @return string
   */
  public function answer_shortcode($atts, $content = null) {
    $question = $this->pending_question;
    $answer   = $this->sanitize_faq_text($content);

    $this->pending_question = '';

    return $this->render_faq_item($question, $answer);
  }

  /**
   * Keep enclosed question and answer shortcodes intact after autop.
   *
   * @param  mixed $content
   * @return string
   */
  private function prepare_faq_content($content) {
    if (!is_string($content)) {
      return '';
    }

    $tags    = 'faq|faq_dbm|faqs|faqs_dbm|question|question_dbm|answer|answer_dbm';
    $content = shortcode_unautop($content);
    $content = preg_replace('/<p>\s*(\[\/?(?:' . $tags . ')(?:\s[^\]]*)?\])\s*<\/p>/i', '$1', $content);
    $content = preg_replace('/(<br\s*\/?>|\r\n|\r|\n)/i', ' ', $content);

    return trim($content);
  }

  /**
   * Sanitize text entered between question or answer shortcodes.
   *
   * @param  mixed $content
   * @return string
   */
  private function sanitize_faq_text($content) {
    $content = is_string($content) ? $content : '';
    $content = wp_kses($content, $this->faq_allowed_html());
    $content = preg_replace('/\s+/', ' ', $content);

    return trim($content);
  }

  /**
   * HTML allowed inside a question or answer.
   *
   * @return array
   */
  private function faq_allowed_html() {
    return array(
      'a' => array(
        'href'   => true,
        'title'  => true,
        'target' => true,
      ),
      'strong' => array(),
      'em'     => array(),
      'ul'     => array(),
      'ol'     => array(),
      'li'     => array(),
    );
  }

  /**
   * Render one FAQ and store it for the schema block.
   *
   * @param  string $question
   * @param  string $answer
   * @return string
   */
  private function render_faq_item($question, $answer) {
    $allowed_html = $this->faq_allowed_html();

    if ($question && $answer) {
      $this->faq_items[] = array(
        '@type' => 'Question',
        'name' => wp_strip_all_tags($question),
        'acceptedAnswer' => array(
          '@type' => 'Answer',
          'text' => wp_strip_all_tags($answer)
        )
      );
    }

    $accordion      = isset($this->settings['accordion']) ? sanitize_text_field($this->settings['accordion']) : '';
    $question_label = isset($this->settings['question_label']) ? sanitize_text_field($this->settings['question_label']) : __('Q:', 'faq-schema-shortcode');
    $answer_label   = isset($this->settings['answer_label']) ? sanitize_text_field($this->settings['answer_label']) : __('A:', 'faq-schema-shortcode');

    ob_start();

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
   * Add a Settings link on the Plugins screen.
   *
   * @param  array $links
   * @return array
   */
  public function plugin_action_links($links) {
    $settings_link = '<a class="faq-schema-plugin-settings" href="' . esc_url(admin_url('options-general.php?page=faq-schema-shortcode')) . '">' . esc_html__('Settings', 'faq-schema-shortcode') . '</a>';
    $support_link  = '<a class="faq-schema-plugin-support" href="' . esc_url(admin_url('options-general.php?page=faq-schema-shortcode#support')) . '">' . esc_html__('Support', 'faq-schema-shortcode') . '</a>';

    array_unshift($links, $settings_link, $support_link);

    return $links;
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
    <label class="faq-schema-toggle" for="shortcode_alias">
      <input type="checkbox" name="faq_schema_shortcode_dogbytemarketing_settings[shortcode_alias]" id="shortcode_alias" <?php checked(1, $shortcode_alias, true); ?> />
      <span class="faq-schema-toggle__switch" aria-hidden="true"></span>
      <span class="screen-reader-text"><?php esc_html_e('Enable shortcode alias', 'faq-schema-shortcode'); ?></span>
    </label>
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
    <label class="faq-schema-toggle" for="content_faqs">
      <input type="checkbox" name="faq_schema_shortcode_dogbytemarketing_settings[content_faqs]" id="content_faqs" <?php checked(1, $content_faqs, true); ?> />
      <span class="faq-schema-toggle__switch" aria-hidden="true"></span>
      <span class="screen-reader-text"><?php esc_html_e('Enable FAQ editor', 'faq-schema-shortcode'); ?></span>
    </label>
	  <?php
	}

	/**
	 * Render the shortcode example beside the general settings
	 *
	 * @return void
	 */
	public function shortcode_reference_render() {
    $shortcode_alias = isset($this->settings['shortcode_alias']) ? sanitize_text_field($this->settings['shortcode_alias']) : '';
	  ?>
		<p class="faq-schema-general__note"><?php esc_html_e('Copy and paste into your content.', 'faq-schema-shortcode'); ?></p>
		<div class="faq-schema-code-switch" role="group" aria-label="<?php esc_attr_e('Shortcode example', 'faq-schema-shortcode'); ?>">
			<button type="button" class="faq-schema-code-switch__button<?php echo $shortcode_alias ? '' : ' is-active'; ?>" data-example="dbm" aria-pressed="<?php echo $shortcode_alias ? 'false' : 'true'; ?>"><?php esc_html_e('Standard', 'faq-schema-shortcode'); ?></button>
			<button type="button" class="faq-schema-code-switch__button<?php echo $shortcode_alias ? ' is-active' : ''; ?>" data-example="alias" aria-pressed="<?php echo $shortcode_alias ? 'true' : 'false'; ?>"><?php esc_html_e('Alias', 'faq-schema-shortcode'); ?></button>
		</div>
		<pre class="faq-schema-code" data-example="dbm"<?php echo $shortcode_alias ? ' hidden' : ''; ?>>[faqs_dbm]

[question_dbm]
<?php echo esc_html__('The question', 'faq-schema-shortcode') . "\n"; ?>[/question_dbm]

[answer_dbm]
<?php echo esc_html__('The answer', 'faq-schema-shortcode') . "\n"; ?>[/answer_dbm]

[question_dbm]
<?php echo esc_html__('Another question', 'faq-schema-shortcode') . "\n"; ?>[/question_dbm]

[answer_dbm]
<?php echo esc_html__('Another answer', 'faq-schema-shortcode') . "\n"; ?>[/answer_dbm]

[/faqs_dbm]</pre>
		<pre class="faq-schema-code" data-example="alias"<?php echo $shortcode_alias ? '' : ' hidden'; ?>>[faqs]

[question]
<?php echo esc_html__('The question', 'faq-schema-shortcode') . "\n"; ?>[/question]

[answer]
<?php echo esc_html__('The answer', 'faq-schema-shortcode') . "\n"; ?>[/answer]

[question]
<?php echo esc_html__('Another question', 'faq-schema-shortcode') . "\n"; ?>[/question]

[answer]
<?php echo esc_html__('Another answer', 'faq-schema-shortcode') . "\n"; ?>[/answer]

[/faqs]</pre>
		<button type="button" class="faq-schema-general__copy" data-copied="<?php esc_attr_e('Copied', 'faq-schema-shortcode'); ?>">
			<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
			<span class="faq-schema-general__copy-label"><?php esc_html_e('Copy Shortcode', 'faq-schema-shortcode'); ?></span>
		</button>
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
    <input type="text" class="faq-schema-appearance__input" name="faq_schema_shortcode_dogbytemarketing_settings[question_label]" id="question_label" value="<?php echo esc_attr($option); ?>" />
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
    <input type="text" class="faq-schema-appearance__input" name="faq_schema_shortcode_dogbytemarketing_settings[answer_label]" id="answer_label" value="<?php echo esc_attr($option); ?>" />
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
    <label class="faq-schema-toggle" for="accordion">
      <input type="checkbox" name="faq_schema_shortcode_dogbytemarketing_settings[accordion]" id="accordion" <?php checked(1, $accordion, true); ?> />
      <span class="faq-schema-toggle__switch" aria-hidden="true"></span>
      <span class="screen-reader-text"><?php esc_html_e('Enable accordion', 'faq-schema-shortcode'); ?></span>
    </label>
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
    <div class="faq-schema-field">
      <input type="text" class="faq-schema-color" name="faq_schema_shortcode_dogbytemarketing_settings[accordion_text_color]" id="accordion_text_color" value="<?php echo esc_attr($accordion_text_color); ?>" />
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
    <div class="faq-schema-field">
      <input type="text" class="faq-schema-color" name="faq_schema_shortcode_dogbytemarketing_settings[accordion_background_color]" id="accordion_background_color" value="<?php echo esc_attr($accordion_background_color); ?>" />
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
    <div class="faq-schema-field">
      <input type="text" class="faq-schema-color" name="faq_schema_shortcode_dogbytemarketing_settings[accordion_background_hover_color]" id="accordion_background_hover_color" value="<?php echo esc_attr($accordion_background_hover_color); ?>" />
    </div>
	  <?php
	}

	/**
	 * Render the appearance live preview
	 *
	 * @return void
	 */
	public function appearance_preview_render() {
		$accordion      = isset($this->settings['accordion']) ? sanitize_text_field($this->settings['accordion']) : '';
		$question_label = isset($this->settings['question_label']) ? sanitize_text_field($this->settings['question_label']) : __('Q:', 'faq-schema-shortcode');
		$answer_label   = isset($this->settings['answer_label']) ? sanitize_text_field($this->settings['answer_label']) : __('A:', 'faq-schema-shortcode');
		$samples        = array(
			array(
				'question' => __('What is FAQ schema?', 'faq-schema-shortcode'),
				'answer'   => __('FAQ schema provides structured information about frequently asked questions and their answers.', 'faq-schema-shortcode'),
			),
			array(
				'question' => __('How do I add FAQs to my website?', 'faq-schema-shortcode'),
				'answer'   => __('Add the FAQ shortcode to a page or post and enter each question and answer.', 'faq-schema-shortcode'),
			),
			array(
				'question' => __('Can I customize the appearance?', 'faq-schema-shortcode'),
				'answer'   => __('Yes. Labels, accordion behavior, and colors can be changed from this screen.', 'faq-schema-shortcode'),
			),
		);
		$example_question = __('Example question', 'faq-schema-shortcode');
		?>
		<div class="faq-schema-appearance__preview-head">
			<h2 class="faq-schema-general__title"><?php esc_html_e('Live Preview', 'faq-schema-shortcode'); ?></h2>
			<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
		</div>
		<p class="faq-schema-general__note"><?php esc_html_e('See how your FAQs will appear on your website.', 'faq-schema-shortcode'); ?></p>
		<h3 class="faq-schema-preview__heading"><?php esc_html_e('Frequently Asked Questions', 'faq-schema-shortcode'); ?></h3>
		<div id="faq-schema-preview" class="faq-schema-preview<?php echo $accordion ? ' is-accordion' : ''; ?>">
			<?php foreach ($samples as $index => $sample) : ?>
				<?php $open = $accordion && 0 === $index; ?>
				<div class="faq-schema-preview__item<?php echo $open ? ' is-open' : ''; ?>">
					<button type="button" class="faq-schema-preview__question" data-question="<?php echo esc_attr($sample['question']); ?>" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>">
						<span class="faq-schema-preview__q"><?php echo esc_html($question_label . ' ' . $sample['question']); ?></span>
						<span class="faq-schema-preview__icon" aria-hidden="true"><?php echo $open ? '−' : '+'; ?></span>
					</button>
					<p class="faq-schema-preview__answer" data-answer="<?php echo esc_attr($sample['answer']); ?>">
						<span class="faq-schema-preview__a"><?php echo esc_html($answer_label . ' ' . $sample['answer']); ?></span>
					</p>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="faq-schema-preview__hover-label"><?php esc_html_e('Hover appearance', 'faq-schema-shortcode'); ?></p>
		<div class="faq-schema-preview__hover" data-question="<?php echo esc_attr($example_question); ?>">
			<span class="faq-schema-preview__hover-text"><?php echo esc_html($question_label . ' ' . $example_question); ?></span>
			<span class="faq-schema-preview__icon" aria-hidden="true">+</span>
		</div>
		<button type="button" class="faq-schema-general__copy faq-schema-preview__reset">
			<span class="dashicons dashicons-update" aria-hidden="true"></span>
			<?php esc_html_e('Reset Preview', 'faq-schema-shortcode'); ?>
		</button>
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
    <div class="faq-schema-field">
      <textarea name="faq_schema_shortcode_dogbytemarketing_settings[additional_css]" id="additional_css" rows="5" cols="50"><?php echo esc_html($option); ?></textarea>
      <p class="faq-schema-field__help"><?php esc_attr_e('Any additional CSS you want to add.', 'faq-schema-shortcode'); ?><br /><?php esc_attr_e("Note: It would be more beneficial to add the CSS to your theme's stylesheet.", 'faq-schema-shortcode'); ?></p>
    </div>
	  <?php
	}
	
	/**
	 * Render the support tab
	 *
	 * @return void
	 */
	public function support_panel_render() {
		$plugin_version   = $this->version;
		$wordpress_version = get_bloginfo('version');
		$php_version      = PHP_VERSION;
		$copy_text        = 'FAQ Schema Shortcode version: ' . $plugin_version . "\n" . 'WordPress version: ' . $wordpress_version . "\n" . 'PHP version: ' . $php_version;
		$forum_url        = 'https://wordpress.org/support/plugin/faq-schema-shortcode/';
		$new_topic_url    = 'https://wordpress.org/support/plugin/faq-schema-shortcode/#new-post';
		$review_url       = 'https://wordpress.org/support/plugin/faq-schema-shortcode/reviews/#new-post';
		?>
		<div class="faq-schema-support">
			<h2 class="faq-schema-support__title"><?php esc_html_e('How can we help?', 'faq-schema-shortcode'); ?></h2>
			<p class="faq-schema-support__intro"><?php esc_html_e('Get help with FAQ Schema Shortcode, report issues, or share your feedback.', 'faq-schema-shortcode'); ?></p>
			<div class="faq-schema-support__cards">
				<article class="faq-schema-support__card">
					<h3 class="faq-schema-support__card-title"><span class="dashicons dashicons-sos" aria-hidden="true"></span><?php esc_html_e('Get Support', 'faq-schema-shortcode'); ?></h3>
					<p class="faq-schema-support__card-text"><?php esc_html_e('Visit the WordPress support forum to ask questions or get help.', 'faq-schema-shortcode'); ?></p>
					<a class="faq-schema-support__button" href="<?php echo esc_url($forum_url); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e('Visit Support Forum', 'faq-schema-shortcode'); ?>
					</a>
				</article>
				<article class="faq-schema-support__card">
					<h3 class="faq-schema-support__card-title"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php esc_html_e('Report a Bug', 'faq-schema-shortcode'); ?></h3>
					<p class="faq-schema-support__card-text"><?php esc_html_e('Found something that isn\'t working as expected? Let us know.', 'faq-schema-shortcode'); ?></p>
					<a class="faq-schema-support__button" href="<?php echo esc_url($new_topic_url); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e('Report an Issue', 'faq-schema-shortcode'); ?>
					</a>
				</article>
				<article class="faq-schema-support__card">
					<h3 class="faq-schema-support__card-title"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><?php esc_html_e('Request a Feature', 'faq-schema-shortcode'); ?></h3>
					<p class="faq-schema-support__card-text"><?php esc_html_e('Have an idea to make FAQ Schema Shortcode better?', 'faq-schema-shortcode'); ?></p>
					<a class="faq-schema-support__button" href="<?php echo esc_url($new_topic_url); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e('Share an Idea', 'faq-schema-shortcode'); ?>
					</a>
				</article>
			</div>
			<div class="faq-schema-support__lower">
				<article class="faq-schema-support__card">
					<h3 class="faq-schema-support__card-title"><span class="dashicons dashicons-editor-ul" aria-hidden="true"></span><?php esc_html_e('System Information', 'faq-schema-shortcode'); ?></h3>
					<dl class="faq-schema-support__info">
						<div class="faq-schema-support__row">
							<dt><?php esc_html_e('FAQ Schema Shortcode version', 'faq-schema-shortcode'); ?></dt>
							<dd><?php echo esc_html($plugin_version); ?></dd>
						</div>
						<div class="faq-schema-support__row">
							<dt><?php esc_html_e('WordPress version', 'faq-schema-shortcode'); ?></dt>
							<dd><?php echo esc_html($wordpress_version); ?></dd>
						</div>
						<div class="faq-schema-support__row">
							<dt><?php esc_html_e('PHP version', 'faq-schema-shortcode'); ?></dt>
							<dd><?php echo esc_html($php_version); ?></dd>
						</div>
					</dl>
					<button type="button" class="faq-schema-support__button faq-schema-support__button--ghost faq-schema-support__copy" data-copy="<?php echo esc_attr($copy_text); ?>" data-copied="<?php esc_attr_e('Copied', 'faq-schema-shortcode'); ?>">
						<span class="faq-schema-support__copy-label"><?php esc_html_e('Copy System Info', 'faq-schema-shortcode'); ?></span>
					</button>
				</article>
				<article class="faq-schema-support__card faq-schema-support__card--review">
					<h3 class="faq-schema-support__card-title"><span class="dashicons dashicons-heart" aria-hidden="true"></span><?php esc_html_e('Enjoying FAQ Schema Shortcode?', 'faq-schema-shortcode'); ?></h3>
					<div class="faq-schema-support__stars" role="group" aria-label="<?php esc_attr_e('Rate FAQ Schema Shortcode', 'faq-schema-shortcode'); ?>">
						<button type="button" class="faq-schema-support__star dashicons dashicons-star-empty" data-rating="1" aria-haspopup="dialog">
							<span class="screen-reader-text"><?php esc_html_e('1 star', 'faq-schema-shortcode'); ?></span>
						</button>
						<button type="button" class="faq-schema-support__star dashicons dashicons-star-empty" data-rating="2" aria-haspopup="dialog">
							<span class="screen-reader-text"><?php esc_html_e('2 stars', 'faq-schema-shortcode'); ?></span>
						</button>
						<button type="button" class="faq-schema-support__star dashicons dashicons-star-empty" data-rating="3" aria-haspopup="dialog">
							<span class="screen-reader-text"><?php esc_html_e('3 stars', 'faq-schema-shortcode'); ?></span>
						</button>
						<a class="faq-schema-support__star dashicons dashicons-star-empty" data-rating="4" href="<?php echo esc_url($review_url); ?>" target="_blank" rel="noopener noreferrer">
							<span class="screen-reader-text"><?php esc_html_e('4 stars', 'faq-schema-shortcode'); ?></span>
						</a>
						<a class="faq-schema-support__star dashicons dashicons-star-empty" data-rating="5" href="<?php echo esc_url($review_url); ?>" target="_blank" rel="noopener noreferrer">
							<span class="screen-reader-text"><?php esc_html_e('5 stars', 'faq-schema-shortcode'); ?></span>
						</a>
					</div>
					<a class="faq-schema-support__button" href="<?php echo esc_url($review_url); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e('Leave a Review', 'faq-schema-shortcode'); ?>
					</a>
				</article>
			</div>
			<dialog class="faq-schema-review-prompt" aria-labelledby="faq-schema-review-prompt-title" aria-describedby="faq-schema-review-prompt-text">
				<h2 class="faq-schema-review-prompt__title" id="faq-schema-review-prompt-title"><?php esc_html_e('We strive to provide top-notch plugins', 'faq-schema-shortcode'); ?></h2>
				<p class="faq-schema-review-prompt__text" id="faq-schema-review-prompt-text"><?php esc_html_e("We'd love to try to make your experience better. Would you be willing to reach out directly to us for help?", 'faq-schema-shortcode'); ?></p>
				<div class="faq-schema-review-prompt__actions">
					<a class="faq-schema-support__button faq-schema-review-prompt__close" href="<?php echo esc_url($review_url); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e('Not Now', 'faq-schema-shortcode'); ?>
					</a>
					<a class="faq-schema-support__button faq-schema-review-prompt__reach" href="<?php echo esc_url('https://www.dogbytemarketing.com/contact/'); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e('Reach Out', 'faq-schema-shortcode'); ?>
					</a>
				</div>
			</dialog>
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
		<div class="wrap faq-schema-settings">
			<form action="options.php" method="post">
				<?php settings_fields('faq_schema_shortcode_dogbytemarketing'); ?>
				<div class="faq-schema-settings__layout">
					<div class="faq-schema-settings__workspace">
						<div class="faq-schema-settings__header">
							<div class="faq-schema-settings__logo">
								<img class="faq-schema-settings__logo-img" src="<?php echo esc_url(plugins_url('assets/img/logo.png', __FILE__)); ?>" alt="<?php esc_attr_e('FAQ Schema Shortcode', 'faq-schema-shortcode'); ?>" width="150" height="50" />
								<img class="faq-schema-settings__logo-icon" src="<?php echo esc_url(plugins_url('assets/img/icon.png', __FILE__)); ?>" alt="" width="48" height="48" />
							</div>
							<div class="faq-schema-settings__heading">
								<div class="faq-schema-settings__heading-text">
									<h1 class="faq-schema-settings__title" id="faq-schema-active-title"><?php esc_html_e('General', 'faq-schema-shortcode'); ?></h1>
									<p class="faq-schema-settings__summary" id="faq-schema-active-summary"><?php esc_html_e('Configure how FAQs are added to your website.', 'faq-schema-shortcode'); ?></p>
								</div>
								<button type="submit" class="faq-schema-save"><?php esc_html_e('Save Changes', 'faq-schema-shortcode'); ?></button>
							</div>
						</div>
						<div class="faq-schema-settings__body">
					<nav class="faq-schema-nav" aria-label="<?php esc_attr_e('Settings sections', 'faq-schema-shortcode'); ?>">
						<div class="faq-schema-nav__list" role="tablist">
							<button type="button" class="faq-schema-nav__tab is-active" id="faq-schema-tab-general" role="tab" data-tab="#faq-schema-panel-general" data-description="<?php esc_attr_e('Configure how FAQs are added to your website.', 'faq-schema-shortcode'); ?>" aria-controls="faq-schema-panel-general" aria-selected="true">
								<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
								<span class="faq-schema-nav__label"><?php esc_html_e('General', 'faq-schema-shortcode'); ?></span>
							</button>
							<button type="button" class="faq-schema-nav__tab" id="faq-schema-tab-appearance" role="tab" data-tab="#faq-schema-panel-appearance" data-description="<?php esc_attr_e('Customize the appearance and behavior of your FAQs.', 'faq-schema-shortcode'); ?>" aria-controls="faq-schema-panel-appearance" aria-selected="false">
								<span class="dashicons dashicons-art" aria-hidden="true"></span>
								<span class="faq-schema-nav__label"><?php esc_html_e('Appearance', 'faq-schema-shortcode'); ?></span>
							</button>
							<button type="button" class="faq-schema-nav__tab" id="faq-schema-tab-advanced" role="tab" data-tab="#faq-schema-panel-advanced" data-description="<?php esc_attr_e('Further customize your FAQs with additional styling.', 'faq-schema-shortcode'); ?>" aria-controls="faq-schema-panel-advanced" aria-selected="false">
								<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
								<span class="faq-schema-nav__label"><?php esc_html_e('Advanced', 'faq-schema-shortcode'); ?></span>
							</button>
							<button type="button" class="faq-schema-nav__tab" id="faq-schema-tab-support" role="tab" data-tab="#faq-schema-panel-support" data-description="<?php esc_attr_e('Get help with FAQ Schema Shortcode, report issues, or share your feedback.', 'faq-schema-shortcode'); ?>" aria-controls="faq-schema-panel-support" aria-selected="false">
								<span class="dashicons dashicons-sos" aria-hidden="true"></span>
								<span class="faq-schema-nav__label"><?php esc_html_e('Support', 'faq-schema-shortcode'); ?></span>
							</button>
						</div>
					</nav>

					<div class="faq-schema-main">
						<section id="faq-schema-panel-general" class="faq-schema-panel is-active" role="tabpanel" aria-labelledby="faq-schema-active-title">
							<div class="faq-schema-general__settings">
								<h2 class="faq-schema-general__title"><?php esc_html_e('FAQ Settings', 'faq-schema-shortcode'); ?></h2>
								<article class="faq-schema-general__card">
									<div class="faq-schema-general__card-head">
										<h3 class="faq-schema-general__card-title"><?php esc_html_e('Shortcode Alias', 'faq-schema-shortcode'); ?></h3>
										<?php $this->shortcode_alias_render(); ?>
									</div>
									<p class="faq-schema-general__help"><?php esc_html_e('Use shorter FAQ shortcodes without the _dbm suffix.', 'faq-schema-shortcode'); ?></p>
								</article>
								<article class="faq-schema-general__card">
									<div class="faq-schema-general__card-head">
										<h3 class="faq-schema-general__card-title"><?php esc_html_e('FAQ Editor', 'faq-schema-shortcode'); ?></h3>
										<?php $this->content_faqs_render(); ?>
									</div>
									<p class="faq-schema-general__help"><?php esc_html_e('Add and manage FAQs directly from the WordPress content editor using dedicated input fields.', 'faq-schema-shortcode'); ?></p>
								</article>
							</div>
							<div class="faq-schema-general__reference" id="faq-schema-shortcode-reference">
								<h2 class="faq-schema-general__title"><?php esc_html_e('Shortcode Reference', 'faq-schema-shortcode'); ?></h2>
								<?php $this->shortcode_reference_render(); ?>
							</div>
						</section>

						<section id="faq-schema-panel-appearance" class="faq-schema-panel" role="tabpanel" aria-labelledby="faq-schema-active-title" hidden>
							<div class="faq-schema-appearance__settings">
								<article class="faq-schema-general__card">
									<h3 class="faq-schema-general__card-title"><?php esc_html_e('FAQ Labels', 'faq-schema-shortcode'); ?></h3>
									<p class="faq-schema-general__help"><?php esc_html_e('Customize the prefixes displayed before questions and answers.', 'faq-schema-shortcode'); ?></p>
									<label class="faq-schema-appearance__label" for="question_label"><?php esc_html_e('Question Label', 'faq-schema-shortcode'); ?></label>
									<?php $this->question_label_render(); ?>
									<label class="faq-schema-appearance__label" for="answer_label"><?php esc_html_e('Answer Label', 'faq-schema-shortcode'); ?></label>
									<?php $this->answer_label_render(); ?>
								</article>
								<article class="faq-schema-general__card">
									<div class="faq-schema-general__card-head">
										<h3 class="faq-schema-general__card-title"><?php esc_html_e('Accordion', 'faq-schema-shortcode'); ?></h3>
										<?php $this->accordion_render(); ?>
									</div>
									<p class="faq-schema-general__help"><?php esc_html_e('Allow visitors to expand and collapse FAQ answers.', 'faq-schema-shortcode'); ?></p>
									<label class="faq-schema-appearance__label" for="accordion_text_color"><?php esc_html_e('Text Color', 'faq-schema-shortcode'); ?></label>
									<?php $this->accordion_text_color_render(); ?>
									<label class="faq-schema-appearance__label" for="accordion_background_color"><?php esc_html_e('Background Color', 'faq-schema-shortcode'); ?></label>
									<?php $this->accordion_background_color_render(); ?>
									<label class="faq-schema-appearance__label" for="accordion_background_hover_color"><?php esc_html_e('Background Hover Color', 'faq-schema-shortcode'); ?></label>
									<?php $this->accordion_background_hover_color_render(); ?>
								</article>
							</div>
							<div class="faq-schema-appearance__preview">
								<?php $this->appearance_preview_render(); ?>
							</div>
						</section>

						<section id="faq-schema-panel-advanced" class="faq-schema-panel" role="tabpanel" aria-labelledby="faq-schema-active-title" hidden>
							<div class="faq-schema-setting">
								<label class="faq-schema-setting__label" for="additional_css"><?php esc_html_e('Additional CSS', 'faq-schema-shortcode'); ?></label>
								<?php $this->additional_css_render(); ?>
							</div>
						</section>

						<section id="faq-schema-panel-support" class="faq-schema-panel" role="tabpanel" aria-labelledby="faq-schema-active-title" hidden>
							<?php $this->support_panel_render(); ?>
						</section>
					</div>
					<div class="faq-schema-settings__savebar">
						<button type="submit" class="faq-schema-save"><?php esc_html_e('Save Changes', 'faq-schema-shortcode'); ?></button>
					</div>
						</div>
					</div>

					<div class="faq-schema-cta-stack">
					<aside class="faq-schema-cta">
						<div class="faq-schema-cta__brand">
							<div class="faq-schema-cta__brand-top">
								<img class="faq-schema-cta__logo" src="<?php echo esc_url(plugins_url('assets/img/dog-byte-logo-white.png', __FILE__)); ?>" alt="<?php esc_attr_e('Dog Byte Marketing', 'faq-schema-shortcode'); ?>" width="120" />
							</div>
							<h2 class="faq-schema-cta__title"><?php esc_html_e('Turn Searches Into Customers', 'faq-schema-shortcode'); ?></h2>
							<p class="faq-schema-cta__desc"><?php esc_html_e('Grow your business with expert SEO, web development, and digital marketing.', 'faq-schema-shortcode'); ?></p>
						</div>
						<div class="faq-schema-cta__body">
							<ul class="faq-schema-cta__benefits">
								<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Improve search visibility', 'faq-schema-shortcode'); ?></li>
								<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Drive more qualified traffic', 'faq-schema-shortcode'); ?></li>
								<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Convert more visitors into customers', 'faq-schema-shortcode'); ?></li>
							</ul>
							<a class="faq-schema-cta__button" href="<?php echo esc_url('https://dogbytemarketing.com/services/?utm_source=faq-schema-shortcode&utm_medium=plugin&utm_campaign=settings'); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e('Explore Our Services', 'faq-schema-shortcode'); ?>
								<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
							</a>
							<a class="faq-schema-cta__button faq-schema-cta__button--call" href="<?php echo esc_url('tel:+14237248922'); ?>">
								<span class="dashicons dashicons-phone" aria-hidden="true"></span>
								<?php esc_html_e('(423) 724-8922', 'faq-schema-shortcode'); ?>
							</a>
							<hr class="faq-schema-cta__rule" />
							<p class="faq-schema-cta__location"><?php esc_html_e('USA Based · Serving Businesses Nationwide', 'faq-schema-shortcode'); ?></p>
						</div>
					</aside>
					<aside class="faq-schema-plugins" aria-roledescription="carousel" aria-label="<?php esc_attr_e('Other plugins', 'faq-schema-shortcode'); ?>">
						<div class="faq-schema-cta__brand faq-schema-plugins__header">
							<h2 class="faq-schema-cta__title"><?php esc_html_e('Explore Our Other Plugins', 'faq-schema-shortcode'); ?></h2>
						</div>
						<div class="faq-schema-plugins__viewport">
							<div class="faq-schema-plugins__track">
								<div class="faq-schema-plugins__slide">
									<img class="faq-schema-plugins__icon" src="<?php echo esc_url(plugins_url('assets/img/plugins/projectplot.png', __FILE__)); ?>" alt="" width="72" height="72" />
									<h3 class="faq-schema-plugins__name"><?php esc_html_e('ProjectPlot', 'faq-schema-shortcode'); ?></h3>
									<p class="faq-schema-plugins__summary"><?php esc_html_e('Simplify task management and keep your team organized directly within WordPress.', 'faq-schema-shortcode'); ?></p>
									<ul class="faq-schema-cta__benefits">
										<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Create and manage tasks with ease', 'faq-schema-shortcode'); ?></li>
										<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Track task priorities and progress', 'faq-schema-shortcode'); ?></li>
										<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Keep your team organized', 'faq-schema-shortcode'); ?></li>
									</ul>
									<a class="faq-schema-cta__button" href="https://wordpress.org/plugins/projectplot/" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e('Check Out ProjectPlot', 'faq-schema-shortcode'); ?>
										<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
									</a>
								</div>
								<div class="faq-schema-plugins__slide">
									<img class="faq-schema-plugins__icon" src="<?php echo esc_url(plugins_url('assets/img/plugins/sync-mautic.png', __FILE__)); ?>" alt="" width="72" height="72" />
									<h3 class="faq-schema-plugins__name"><?php esc_html_e('Sync Mautic', 'faq-schema-shortcode'); ?></h3>
									<p class="faq-schema-plugins__summary"><?php esc_html_e('Connect WordPress and WooCommerce to Mautic for smarter email marketing automation.', 'faq-schema-shortcode'); ?></p>
									<ul class="faq-schema-cta__benefits">
										<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Automatically sync subscribers', 'faq-schema-shortcode'); ?></li>
										<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Tag customers based on purchases', 'faq-schema-shortcode'); ?></li>
										<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Capture more email marketing leads', 'faq-schema-shortcode'); ?></li>
									</ul>
									<a class="faq-schema-cta__button" href="https://wordpress.org/plugins/sync-mautic/" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e('Check Out Sync Mautic', 'faq-schema-shortcode'); ?>
										<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
									</a>
								</div>
								<div class="faq-schema-plugins__slide">
									<img class="faq-schema-plugins__icon" src="<?php echo esc_url(plugins_url('assets/img/plugins/user-notifications.png', __FILE__)); ?>" alt="" width="72" height="72" />
									<h3 class="faq-schema-plugins__name"><?php esc_html_e('User Notifications', 'faq-schema-shortcode'); ?></h3>
									<p class="faq-schema-plugins__summary"><?php esc_html_e('Keep WordPress and WooCommerce users informed with notifications directly on your website.', 'faq-schema-shortcode'); ?></p>
									<ul class="faq-schema-cta__benefits">
										<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Share updates and promotions', 'faq-schema-shortcode'); ?></li>
										<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Automate order notifications', 'faq-schema-shortcode'); ?></li>
										<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Highlight unread notifications', 'faq-schema-shortcode'); ?></li>
									</ul>
									<a class="faq-schema-cta__button" href="https://wordpress.org/plugins/user-notifications/" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e('Check Out User Notifications', 'faq-schema-shortcode'); ?>
										<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
									</a>
								</div>
							</div>
						</div>
						<div class="faq-schema-plugins__dots">
							<button type="button" class="faq-schema-plugins__dot is-active" aria-label="<?php esc_attr_e('Show ProjectPlot', 'faq-schema-shortcode'); ?>" aria-selected="true"></button>
							<button type="button" class="faq-schema-plugins__dot" aria-label="<?php esc_attr_e('Show Sync Mautic', 'faq-schema-shortcode'); ?>" aria-selected="false"></button>
							<button type="button" class="faq-schema-plugins__dot" aria-label="<?php esc_attr_e('Show User Notifications', 'faq-schema-shortcode'); ?>" aria-selected="false"></button>
						</div>
						<div class="faq-schema-plugins__foot">
							<hr class="faq-schema-cta__rule" />
							<p class="faq-schema-cta__location"><?php esc_html_e('Free & Open Source · WordPress.org', 'faq-schema-shortcode'); ?></p>
						</div>
					</aside>
					</div>
				</div>
			</form>
			<p class="faq-schema-toast" role="status" aria-live="polite"></p>
		</div>
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

    if (version_compare($stored_version, '1.3.0', '<')) {
      $stored_version = '1.3.0';

      $this->update_to_130();

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

    if (!$settings) {
      add_option('faq_schema_shortcode_dogbytemarketing_settings');
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

    if ($this->version == '1.3.0') {
      $screen = function_exists('get_current_screen') ? get_current_screen() : null;

      if ($screen && 'settings_page_faq-schema-shortcode' === $screen->id) {
        return;
      }

      $this->notice_130();
    }
  }
  
  /**
   * Save settings from the settings screen without a full reload.
   *
   * @return void
   */
  public function save_settings() {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'faq_schema_shortcode_dogbytemarketing-options')) {
      $this->send_error('Invalid session', 403);
    }

    if (!current_user_can('manage_options')) {
      $this->send_error('Unauthorized', 401);
    }

    $input = array();

    if (isset($_POST['faq_schema_shortcode_dogbytemarketing_settings'])) {
      $posted = map_deep(wp_unslash($_POST['faq_schema_shortcode_dogbytemarketing_settings']), 'sanitize_text_field');

      if (is_array($posted)) {
        $input = $posted;
      }
    }

    // update_option still runs sanitize(), which turns checkbox values into booleans.
    update_option('faq_schema_shortcode_dogbytemarketing_settings', $input);
    $this->settings = get_option('faq_schema_shortcode_dogbytemarketing_settings', array());

    $this->send_success(__('Settings saved.', 'faq-schema-shortcode'));
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
      <p><?php echo esc_html__('FAQ Schema Shortcode has been updated to allow easier management of FAQs. The Content FAQs feature gives all post types an editor in the backend where you can add FAQs using HTML.', 'faq-schema-shortcode'); ?></p>
      <a href="options-general.php?page=faq-schema-shortcode"><?php echo esc_html__('Enable New Content FAQs Feature', 'faq-schema-shortcode'); ?></a> | 
      <a href="https://wordpress.org/plugins/faq-schema-shortcode/#developers" target="_blank"><?php echo esc_html__('View Changelog', 'faq-schema-shortcode'); ?></a>
    </div>
    <?php
  }

  /**
   * Notice 1.3.0
   *
   * @return void
   */
  public function notice_130() {
    $settings_url = admin_url('options-general.php?page=faq-schema-shortcode#faq-schema-shortcode-reference');
    ?>
    <div class="notice is-dismissible faq-schema-shortcode faq-schema-notice">
      <div class="faq-schema-notice__icon" aria-hidden="true">
        <span class="dashicons dashicons-megaphone"></span>
      </div>
      <div class="faq-schema-notice__content">
        <p class="faq-schema-notice__title">
          <?php esc_html_e('Introducing improved FAQ shortcodes', 'faq-schema-shortcode'); ?>
          <span class="faq-schema-notice__version"><?php echo esc_html('v' . $this->version); ?></span>
        </p>
        <p class="faq-schema-notice__text"><?php esc_html_e('FAQ Schema Shortcode now features an improved shortcode structure. Your existing shortcodes will continue to work without any changes.', 'faq-schema-shortcode'); ?></p>
        <a class="faq-schema-notice__button" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('View New Shortcode Examples', 'faq-schema-shortcode'); ?></a>
      </div>
    </div>
    <?php
  }
  
  /**
   * Update to 1.2.0
   * 
   * @return void
   */
  private function update_to_120() {
    update_option('faq_schema_shortcode_notice_dismissed_dogbytemarketing', false);
  }

  /**
   * Update to 1.3.0
   *
   * @return void
   */
  private function update_to_130() {
    update_option('faq_schema_shortcode_notice_dismissed_dogbytemarketing', false);
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
