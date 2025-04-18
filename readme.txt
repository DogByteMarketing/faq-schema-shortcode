=== FAQ Schema Shortcode ===
Contributors: dogbytemarketing
Donate link: 
Tags: faq, structured data, schema, shortcode, seo
Requires at least: 6.5
Tested up to: 6.8
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Quickly add FAQ sections compatible with structured data to your site using simple shortcodes, improving your SEO.


== Description ==

FAQ Schema Shortcode is a WordPress plugin that allows you to easily add FAQ sections to your site using simple shortcodes. It automatically generates structured data (JSON-LD schema) for each FAQ, helping search engines better understand your content and improving your site's SEO with rich results. By using the [faqs_dbm] and [faq_dbm] shortcodes, you can quickly create FAQs that are both user-friendly and SEO-friendly, enhancing your site's visibility in search engines.


Example:
[faqs_dbm]
[faq_dbm q="What color is the sky?" a="Blue"]
[faq_dbm q="What color is grass?" a="Green"]
[/faqs_dbm]

Want to help? Submit a PR on [Github](https://github.com/DogByteMarketing/faq-schema-shortcode/).


== Features ==

* Shortcode alias for when you do not have any other shortcodes using [faqs] and [faq] then you can enable this feature in the settings.
* Accordion option to let users toggle FAQs open and closed
* Accordion background color, background hover color, and text color options


== Installation ==

1. Backup WordPress
1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress


== Frequently Asked Questions ==

= What is the shortcode for displaying FAQs? =
Use `[faqs_dbm]` as the container shortcode and `[faq_dbm q="Your question?" a="Your answer."]` for each individual FAQ item. Wrap the individual items inside the container like this:

[faqs_dbm]  
[faq_dbm q="What is this plugin for?" a="It helps you display FAQs with JSON-LD schema for SEO."]  
[/faqs_dbm]

= Can I use a simpler shortcode like [faqs] and [faq]? =
Yes, enable the "Shortcode Alias" option in the plugin settings. This will allow you to use `[faqs]` and `[faq]` instead of the default `[faqs_dbm]` and `[faq_dbm]`.

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

= How do I include a link? =

You would simply replace the " with '
[faqs]
[faq q="How to include a link" a="<a href='#'>Just like this</a>"]
[/faqs]


== Screenshots ==

1. Demo
2. Plugin Settings


== Changelog ==

= 1.0.1 =
* Updated: FAQs to allow a, em, and strong

= 1.0.1 =
* Added: Accordion option
* Added: Accordion text color option
* Added: Accordion background color option
* Added: Accordion background hover color option
* Bugfix: Extra line breaks

= 1.0.0 =
* Initial Release

