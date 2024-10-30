=== KASSA.AT For WooCommerce ===
Contributors: brandonpirk, manuelschultz
Tags: woocommerce, stock-management, stock, kassa.at, kasse.pro, lager, lagerhaltung
Donate link: http://kassa.at
Requires at least: 5.7
Tested up to: 5.9
Requires PHP: 7.0.33
Stable tag: 1.1.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This Plugin makes a connection to your KASSA.AT account to automate your experiences and synchronize stocks between physical-store and onlineshop.

== Description ==
This Plugin is only useable with an account for KASSA.AT!
If you dont have one, you can get one inside of the plugin.
The plugin synchronizes automatically the stock amounts between the onlineshop and the KASSA.AT System.

= Features =
* Logging in to KASSA.AT
* Choosing a warehouse, the stocks should synchronize with.
* Stock amounts are queried whenever a visitor enters the Page of an article to have the correct numbers here.
* Stock amounts are queried whenever a visitor enters his cart, so we have the right amount of stock here too.
* Whenever a customer completes an order your KASSA.AT account will register that and will adapt to the new stock amounts.

= Attention =
This plugin only works with a KASSA.AT account and requires WooCommerce.
The serialnumber in WC-products must equal articlenumber in KASSA.AT-articles
You can find a detailed handbook for this plugin on [our website](https://kassa.at/plugin-fuer-woocommerce/).

= Supported languages =
* German (de_AT, de_DE, de_DE_formal, de_CH, de_CH_formal)
* English (Default language)
* Turkish (automatic translation) (< 100%)
* Hungarian (automatic translation) (< 100%)
* Slovenian (automatic translation) (< 100%)
* Croatian (automatic translation) (< 100%)
* Polish (automatic translation) (< 100%)

== Installation ==
= Custom installation =
1. Upload and extract the "kassa-at-for-woocommerce" archive to the "/wp-content/plugins/" directory (inside a folder named after the archive).
2. Activate the plugin through the "Plugins" menu in WordPress.

= Automatic installation =
1. Find the Plugin in the "Plugins" > "Add new" section of your wordpress site.
2. Click on "Install now".
3. Wait until the plugin is installed.
4. Activate the plugin.

== Set up ==
To make the plugin work, there are a view things you need to do before:
1. Go to the Admin panel and click on the KASSA.AT menu.
2. If you don't have a KASSA.AT account create one with the given link. When you have done this, go back to the Wordpress page.
3. Connect your Wordpress site with your KASSA.AT account.
4. Choose the warehouse we should use to make all stock transactions.

== Frequently Asked Questions ==

= How can I use your plugin without a KASSA.AT account? =

Short answer: You cant! The plugin needs an account to synchronize to.

= Is there a chance, to use the plugin without a WooCommerce shop? =

No. The plugin is based on a woocommerce-shop.

= I don't need the synchronization every time, and it slows down my server, how can I limit the synchronization attempts? =

There are options for disabling the synchronization at different pages. Read more about that in the handbook.

== Changelog ==

= 1.1.1 (2023-04-22) =
* Added an check for log entry creation if data are empty or not ( Hotfix )

= 1.1.0 (2022-01-26) =
* Added Functions to disable synchronization on single product page and cart individually.
* Added Logging for debugging and error-management purposes.
Added functions to customize the logs (Log disabling; Log deleting; Log downloading; Log length defining)
* Displaying importent database entries beneath the logs.

= 1.0.2 (2021-10-08) =
* Removed the necessity to reload for stock synchronization on single-product and cart page.

= 1.0.1 (2021-09-24) =
* Backlog Products won't be overridden to no backlog anymore.

= 1.0.0 (2021-01-05) =
* The initial plugin all the basic features are available.
* Making connection to KASSA.AT services.
* One click option to synchronize all articles (in the backend).
* Check the stockamount when a single article is viewed (on the frontend).
* Check the stockamount for every item in cart when cart is entered (on the frontend).

== Upgrade Notice ==

= 1.1.0 =
* You can disable specific synchronizations now.
* There are error logs helping in finding the error if one occurs.

= 1.0.2 =
* Site doesn't require reload to synchronize stocks.

= 1.0.1 =
* You should upgrade to this version if you allow backlogs on your products. Otherwise sometimes the products can't be ordered.

= 1.0.0 =
* You cant be below this version.

== Screenshots ==

1. KASSA.AT menu before connecting to your KASSA.AT account.
2. KASSA.AT menu after connecting to your KASSA.AT account. Now, you have unlimited power.
