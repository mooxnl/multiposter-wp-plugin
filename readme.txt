=== Multiposter ===
Contributors: multiposternl
Tags: vacancies, jobs, recruitment, job board, vacatures
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 2.1
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

== Source code ==

The full, non-minified source code for this plugin (including the un-compiled JavaScript for the Gutenberg blocks) is publicly available at:

https://github.com/mooxnl/multiposter-wp-plugin

The compiled block scripts shipped under `assets/js/blocks/` are generated from the matching files in `src/blocks/` using `@wordpress/scripts`.

To build the block assets locally:

1. Clone the repository.
2. Install dependencies: `npm install`
3. Build the production bundles: `npm run build`
4. For development with file watching: `npm run start`

The build writes the compiled JavaScript and `*.asset.php` files to `assets/js/blocks/`.

== External services ==

This plugin connects to external services under specific conditions. The plugin is fully functional in standalone mode without contacting any of these services.

**Multiposter API (app.jobit.nl)**

Used to synchronize vacancies and, optionally, to submit job applications and candidate registrations. Only contacted when a Multiposter API key is configured in the plugin settings.

* What is sent — vacancy synchronisation: the configured API key and vacancy fetch parameters (channel ID, page, limit). No visitor data.
* What is sent — application form submissions (when "API" or "API and email" is selected for the application form): the configured API key and the form fields the visitor provides (name, email, phone, message, uploaded CV, vacancy ID).
* What is sent — registration form submissions (when "API" or "API and email" is selected for the registration form): the configured API key and the form fields the visitor provides (first name, last name, email, phone, motivation, optional uploaded CV).
* When it is sent: on the scheduled vacancy sync interval, on manual re-sync from the admin screen, when a visitor submits the application form, and when a visitor submits the registration form.
* Endpoints contacted: `https://app.jobit.nl/api/vacancies/channel/{channel_id}` (vacancy sync) and `https://app.jobit.nl/api/candidates` (job applications and candidate registrations, distinguished by a `type` field in the request body).
* Service: operated by Multiposter.
* Terms of service: https://multiposter.nl/informatie/voorwaarden
* Privacy policy: https://multiposter.nl/informatie/privacy-policy

**Social share endpoints**

The share buttons rendered on single vacancy pages link to external share URLs provided by LinkedIn, Facebook, X (Twitter), WhatsApp and the visitor's own mail client. No data is transmitted automatically; data is only sent if a visitor clicks a share button and uses the resulting page.

* LinkedIn: https://www.linkedin.com/legal/user-agreement / https://www.linkedin.com/legal/privacy-policy
* Facebook: https://www.facebook.com/legal/terms / https://www.facebook.com/policy.php
* X (Twitter): https://x.com/tos / https://x.com/privacy
* WhatsApp: https://www.whatsapp.com/legal/terms-of-service / https://www.whatsapp.com/legal/privacy-policy

== Frequently Asked Questions ==

= Do I need a Multiposter account? =

No. The plugin works standalone without an API key. You can create and manage vacancies directly in WordPress.

= How do applications work without an API key? =

Applications are sent via email to the configured notification address, or to the site admin email as a fallback.

== Changelog ==

= 2.1 =
* Documented external services (Multiposter API, social share endpoints)
* Switched JSON responses to wp_json_encode
* Hardened output escaping on share buttons and admin settings tabs
* Corrected contributor username

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
