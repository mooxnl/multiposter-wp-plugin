=== Multiposter ===
Contributors: multiposter
Tags: vacancies, jobs, recruitment, job board, vacatures
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 2.0
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Publish vacancies on your WordPress website. Works standalone or integrated with Multiposter.

== Description ==

Multiposter lets you manage and display job vacancies on your WordPress site. Use it as a standalone job board or connect it to the Multiposter platform for automatic vacancy synchronisation.

**Features:**

* Custom post type for vacancies with detailed meta fields
* Archive page with filters (keyword, position, city, salary range)
* Responsive vacancy cards with thumbnail support
* Built-in application form (API or email submission)
* Favourites system (cookie-based)
* Related vacancies with configurable matching criteria
* Share buttons (LinkedIn, Facebook, X, WhatsApp, Email)
* SEO options with customisable title and description templates
* Open Graph and Twitter Card meta tags
* Configurable pagination and column layouts
* Image gallery with lightbox
* Performance caching with transients
* Gutenberg blocks (archive, latest vacancies, single vacancy, search)
* Full internationalisation support (Dutch translation included)

**Standalone Mode:**

Without an API key the plugin works fully standalone. Create vacancies manually in the WordPress admin, and applications are delivered via email.

**Multiposter Integration:**

Enter your Multiposter API key to enable automatic vacancy synchronisation. Vacancies are imported on a configurable schedule and applications can be sent directly to the Multiposter API.

== Installation ==

1. Upload the `multiposter` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Multiposter > Settings to configure the plugin.
4. (Optional) Enter your Multiposter API key to enable automatic sync.
5. Visit Settings > Permalinks and click "Save Changes" to register the vacancy URL slug.

== Frequently Asked Questions ==

= Do I need a Multiposter account? =

No. The plugin works standalone without an API key. You can create and manage vacancies directly in WordPress.

= How do applications work without an API key? =

Applications are sent via email to the configured notification address, or to the site admin email as a fallback.

== Changelog ==

= 2.0 =
* Added standalone mode (manual vacancy creation)
* Added application form email fallback
* Added security nonces to all AJAX handlers
* Added uninstall cleanup
* WordPress.org directory compliance

= 1.1 =
* Added Dutch date formatting
* Local font support

= 1.0 =
* Initial release
