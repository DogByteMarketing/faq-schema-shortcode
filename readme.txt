=== FAQ Schema Shortcode ===
Contributors: dogbytemarketing
Donate link: 
Tags: faq, structured data, schema, shortcode, seo
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 1.3.0
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Quickly add FAQ sections compatible with structured data to your site using simple shortcodes, improving your SEO.


== Description ==

FAQ Schema Shortcode is a WordPress plugin that allows you to easily add FAQ sections to your site using simple shortcodes. It automatically generates structured data (JSON-LD schema) for each FAQ, helping search engines better understand your content and improving your site's SEO with rich results. Use the `_dbm` shortcodes below unless you enable Shortcode Alias in the plugin settings. The shorter tags, such as `[faqs]`, `[question]`, and `[answer]`, only work after that option is turned on.


Example:

[faqs_dbm]
[question_dbm]
What color is the sky?
[/question_dbm]
[answer_dbm]
Blue
[/answer_dbm]
[/faqs_dbm]

The original attribute shortcode still works:

[faqs_dbm]
[faq_dbm q="What color is grass?" a="Green"]
[/faqs_dbm]

After Shortcode Alias is enabled, drop `_dbm` from every tag:

[faqs]
[question]
What color is the sky?
[/question]
[answer]
Blue
[/answer]
[/faqs]

Want to help? Submit a PR on [Github](https://github.com/DogByteMarketing/faq-schema-shortcode/).


== Features ==

* [NEW] Content FAQs section within backend editor to add FAQs using HTML across all post types and also article categories and product categories. (posts, pages, products, product_cat, ect)
* [NEW] Question and answer shortcodes for longer FAQ text. Use `[question_dbm]` and `[answer_dbm]` inside `[faqs_dbm]` unless Shortcode Alias is enabled.
* Shortcode alias for when you do not have any other shortcodes using `[faqs]`, `[faq]`, `[question]`, and `[answer]`. Enable this in the settings before using tags without `_dbm`.
* Customizable Question and Answer labels
* Accordion option to let users toggle FAQs open and closed
* Accordion background color, background hover color, and text color options
* Add additional CSS


## GDPR

We are not lawyers and always recommend doing your own compliance research into third party plugins, libraries, ect, as we've seen other plugins not be in compliance with these regulations.

This plugin does not track visitors, set its own cookies, or send form submissions to an outside service.

### General

* Storing plugin settings
* Remembering that the admin notice was dismissed

### WordPress plugin (stored on your server)

* FAQ question and answer text saved as post meta and printed on the page, including in the public JSON-LD schema
* Plugin settings such as the shortcode alias, content FAQs, labels, accordion options, colors, and additional CSS
* A site option recording that the admin notice was dismissed
* The installed plugin version

### External links

* The plugin settings may contain links to external websites for support, recommendations, and additional resources. Visiting these websites is optional and may be subject to their own privacy policies, cookies, and tracking practices.


== Installation ==

1. Backup WordPress
1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress


== Frequently Asked Questions ==

= What is the shortcode for displaying FAQs? =
Use `[faqs_dbm]` as the container. Each FAQ can be a question block followed by an answer block:

[faqs_dbm]
[question_dbm]
What is this plugin for?
[/question_dbm]
[answer_dbm]
It helps you display FAQs with JSON-LD schema for SEO.
[/answer_dbm]
[/faqs_dbm]

You can also keep using one shortcode per FAQ:

[faqs_dbm]
[faq_dbm q="What is this plugin for?" a="It helps you display FAQs with JSON-LD schema for SEO."]
[/faqs_dbm]

`[question]`, `[answer]`, `[faqs]`, and `[faq]` do not work until Shortcode Alias is enabled. Until then, every tag needs the `_dbm` suffix.

= Can I use a simpler shortcode like [faqs], [question], and [answer]? =
Yes. Go to Settings > FAQ Shortcode and enable Shortcode Alias. That removes the need for `_dbm`, so `[faqs_dbm]` becomes `[faqs]`, `[question_dbm]` becomes `[question]`, `[answer_dbm]` becomes `[answer]`, and `[faq_dbm]` becomes `[faq]`. Leave the option off if another plugin already uses those tag names.

= How do I enable accordion functionality? =
Go to **Settings > FAQ Shortcode**, and check the box labeled **Accordion**. This makes the FAQ entries collapsible and expandable.

= How can I change the accordion colors? =
In the settings page, you can set:
- Text color
- Background color
- Background hover color  
Just enter valid HEX values (like `#ff0000`) for each.

= Does this plugin add FAQ schema for SEO? =
Yes! It automatically generates [JSON-LD structured data](https://developers.google.com/search/docs/appearance/structured-data/faqpage) so search engines like Google can understand and feature your FAQs.

= Can I use HTML in the question or answer? =
Yes, but it's sanitized. Only the following tags are allowed in the answers:
- `<a>` with `href`, `title`, and `target`
- `<strong>`
- `<em>`
- `<ol>`
- `<ul>`
- `<li>`

= How do I include a link? =

You would simply replace the " with '. This example uses `_dbm` because that is required unless Shortcode Alias is enabled.

[faqs_dbm]
[question_dbm]
How to include a link
[/question_dbm]
[answer_dbm]
<a href='#'>Just like this</a>
[/answer_dbm]
[/faqs_dbm]


== Screenshots ==

1. Demo
2. General Settings
3. Appearance Settings
4. Advanced Settings


== Changelog ==

= 1.3.0 =
* Added: Improved shortcode structure. Your existing shortcodes will continue to work without any changes.
* Added: The new Content FAQs feature to add FAQs using HTML across all post types and also article categories and product categories.

= 1.2.0 =
* Added: Content FAQs to give all post types an editor in the backend where you can add FAQs using HTML.
* Added: Handling for updates
* Updated: Translations

= 1.1.1 =
* Bugfix: FAQ showing at top of content

= 1.1.0 =
* Added: Setting to customize the question label
* Added: Setting to customize the answer label
* Added: Setting to add additional CSS
* Added: Translation support

= 1.0.2 =
* Updated: FAQs to allow a, em, strong, ol, ul, li

= 1.0.1 =
* Added: Accordion option
* Added: Accordion text color option
* Added: Accordion background color option
* Added: Accordion background hover color option
* Bugfix: Extra line breaks

= 1.0.0 =
* Initial Release

