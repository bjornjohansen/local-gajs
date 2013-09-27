=== Local GAjs ===
Contributors: bjornjohansen
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=NLUWR4SHCJRBJ
Tags: Google Analytics, ga.js, javascript, optimize, performance
Author URI: https://bjornjohansen.no
Requires at least: 3.6.1
Stable tag: 0.0.1

Host the ga.js locally for improved load speed. Integrates with Analytics for WordPress by Joost de Valk.

== Description ==

Checks with Google twice a day if a new ga.js is available and downloads it automaticly. Settings for Analytics for WordPress is automaticly updated to use the locally hosted version.

Locally hosted version have a serialized filename, so browsers will download the new version when available, so you can set a far-future HTTP expires header.

There is no admin panel, everything is automatic.

== Installation ==
1. Download and unzip plugin
2. Upload the 'local-gajs' folder to the '/wp-content/plugins/' directory,
3. Activate the plugin through the 'Plugins' menu in WordPress.

== Changelog ==

= Version 0.0.1 =
* It works (or at least it does for me)

