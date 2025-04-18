<?php

/**
 * Plugin Name: FAQ Schema Shortcode
 * Plugin URI: https://www.dogbytemarketing.com/contact/
 * Description: Quickly add FAQ sections compatible with structured data to your site using simple shortcodes, improving your SEO.
 * Author: Dog Byte Marketing
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author URI: https://www.dogbytemarketing.com
 * License: GPL3
 */

namespace DogByteMarketing;

register_activation_hook(__FILE__, array(__NAMESPACE__ . '\FAQ_Schema_Shortcode', 'activation'));

class FAQ_Schema_Shortcode
{
  private $settings = array();
  private $faq_items = array();

  public function __construct()
  {
    $this->settings = get_option('faq_schema_shortcode_dogbytemarketing_settings');
  }
  
  /**
   * Initialize
   *
   * @return void
   */
  public function init()
  {
    $shortcode_alias = isset($this->settings['shortcode_alias']) ? $this->settings['shortcode_alias'] : false;

		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_init', array($this, 'settings_init'));

    add_shortcode('faqs_dbm', array($this, 'faq_container_shortcode'));
    add_shortcode('faq_dbm', array($this, 'faq_shortcode'));

    if ($shortcode_alias) {
      add_shortcode('faqs', array($this, 'faq_container_shortcode'));
      add_shortcode('faq', array($this, 'faq_shortcode'));
    }
  }
  
  /**
   * Handle adding the FAQ container
   *
   * @param  mixed $atts
   * @param  mixed $content
   * @return void
   */
  public function faq_container_shortcode($atts, $content = null)
  {
    // Reset FAQ items array at the start of each call
    $this->faq_items = [];

    // Process inner shortcodes and capture output
    $output = '<div class="faq-container">' . do_shortcode(wp_kses_post($content)) . '</div>';

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
    $question = isset($atts['q']) ? esc_html($atts['q']) : '';
    $answer   = isset($atts['a']) ? esc_html($atts['a']) : '';

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

    // Return HTML output for each FAQ item
    return '<div class="faq-item">' .
      '<p class="faq-question"><strong>Q: ' . $question . '</strong></p>' .
      '<p class="faq-answer">A: ' . $answer . '</p>' .
      '</div>';
  }

	/**
	 * Add admin menu to backend
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page('options-general.php', 'FAQ Shortcode', 'FAQ Shortcode', 'manage_options', 'faq-schema-shortcode', array($this, 'options_page'));
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
	}

	/**
	 * Render Debug Field
	 *
	 * @return void
	 */
	public function shortcode_alias_render() {
    $shortcode_alias = isset($this->settings['shortcode_alias']) ? $this->settings['shortcode_alias'] : '';
	  ?>
    <div style="padding-top: 6px;">
      <input type="checkbox" name="faq_schema_shortcode_dogbytemarketing_settings[shortcode_alias]" id="shortcode_alias" <?php checked(1, $shortcode_alias, true); ?> /> Enable
      <p><strong>WordPress standards requires a prefix or suffix for shortcodes, but this can be difficult to remember. Check this box to make the shortcode available with:</strong></p>
      <div style="display: inline-block; padding: 10px; background-color: #ccc;">
        [faqs]<br />
        [faq q="The question" a="The answer"]<br />
        [/faqs]
      </div>
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
			<h2>FAQ Schema Shortcode Settings</h2>

			<?php
			settings_fields('faq_schema_shortcode_dogbytemarketing');
			do_settings_sections('faq_schema_shortcode_dogbytemarketing');
			?>
			<p style="background-color: #00A32A; text-align: center; padding: 20px; color: #fff; width: 62%;">
				If you need assistance with your Search Engine Optimization efforts, we at <a href="https://www.dogbytemarketing.com" style="text-decoration: none; color: #fff; font-weight: 700;" target="_blank">Dog Byte Marketing</a> are here to help! We offer a wide array of services.<br />Feel free to give us a call at <a href="tel:4237248922" style="text-decoration: none; color: #fff; font-weight: 700;" target="_blank">(423) 724 - 8922</a>.<br />
				We're USA based in Tennessee.</p>
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

    return $sanitary_values;
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
  
}

// Instantiate and initialize the class
$faq_schema_shortcode = new FAQ_Schema_Shortcode;
$faq_schema_shortcode->init();
