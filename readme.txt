=== Form Studio for Contact Form 7 ===
Contributors: piyushmultidots
Tags: contact form 7, form builder, drag and drop, multistep forms, conditional logic
Requires at least: 6.1
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Drag-and-drop visual form builder for Contact Form 7 with multistep forms, conditional logic, entries, analytics, and GDPR tools.

== Description ==

Form Studio for Contact Form 7 is a professional drag-and-drop builder that adds a modern visual editing experience on top of Contact Form 7. Design forms in a live canvas, add multistep flows and conditional logic, store and manage submissions, and review built-in analytics — all without leaving the WordPress admin.

This plugin is an add-on for Contact Form 7 and requires Contact Form 7 to be installed and active.

**Visual builder**

* Drag-and-drop field placement with a live preview
* Inline label editing, field duplication and section grouping
* Responsive (desktop / tablet / mobile) preview
* Undo / redo

**Field types**

Text, Textarea, Email, Phone, Number, Select, Checkbox, Radio, File, Date, Hidden, Signature, Rating, Repeater and Submit.

**Multistep forms**

* Progress bars and step indicators
* Animated step transitions
* Autosave and "save & continue later" drafts
* Conditional step skipping

**Conditional logic**

* Visual IF / THEN rule builder
* AND / OR conditions and nested groups
* Conditional emails and redirects

**Submission management**

* Submissions stored in the site database
* Search, filter and status management (unread / read / starred / spam / trash)
* CSV export and print-ready (PDF) export
* Front-end entry list via the `[cf7vb_entries]` shortcode

**Email**

* Works with Contact Form 7 mail and second mail (autoresponder)
* Optional HTML email wrapper
* Conditional email workflows

**Analytics**

* Views, starts, submissions and conversion rate
* Abandonment and device analytics
* Field interaction reporting
* Dashboard under Contact &rarr; Analytics

**Privacy & security**

* GDPR acceptance field support
* Honeypot and rate limiting for spam protection
* Accessibility-minded markup (ARIA, focus handling, high contrast)

== Installation ==

1. Make sure Contact Form 7 is installed and active.
2. Upload the `form-studio-for-contact-form-7` folder to the `/wp-content/plugins/` directory, or install the plugin through the Plugins screen in WordPress.
3. Activate the plugin through the "Plugins" screen. The required database tables are created automatically on activation.
4. Go to **Contact &rarr; Contact Forms**, edit a form, and open the **Visual Builder** tab.
5. Use **Contact &rarr; Entries** and **Contact &rarr; Analytics** for submission management and reporting.

== Frequently Asked Questions ==

= Does this plugin require Contact Form 7? =

Yes. It is an add-on for Contact Form 7 and will not function without Contact Form 7 installed and active. An admin notice is shown if Contact Form 7 is missing.

= Where are submissions stored? =

Submissions are stored in dedicated custom database tables created when the plugin is activated. You can browse, search, filter and export them from the Entries screen.

= Can visitors save a form and finish later? =

Yes. Multistep forms support autosave and a "save & continue later" draft link so visitors can return and complete the form.

= How do I show a visitor their own submissions on the front end? =

Add the `[cf7vb_entries]` shortcode to any page. Logged-in users will see the submissions associated with their account.

= Does it support conditional logic? =

Yes. You can build IF/THEN rules with AND/OR conditions and nested groups to show, hide or require fields, and to drive conditional emails and redirects.

== Screenshots ==

1. Visual drag-and-drop form builder.
2. Conditional logic rule builder.
3. Multistep form with progress indicator.
4. Submissions (Entries) management screen.
5. Form analytics dashboard.

== Changelog ==

= 1.0.0 =
* Initial release.
* Drag-and-drop visual form builder for Contact Form 7.
* Multistep forms with autosave and save & continue later.
* Conditional logic (AND/OR, nested groups, conditional emails and redirects).
* Submission storage, search, filters, status management, CSV and print/PDF export.
* Front-end entry list shortcode `[cf7vb_entries]`.
* Built-in form analytics (views, conversions, abandonment, devices, field interactions).
* GDPR acceptance field, honeypot and rate-limit spam protection.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
