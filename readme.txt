=== Plugin Name ===
Contributors: WebsiteBakery
Donate link: http://freshlybakedwebsites.net/say-thanks-with-a-beer/
Tags: events, calendar, housekeeping, clean up, database, tidy
Requires at least: 3.5.1
Tested up to: 3.5.1
Stable tag: 1.2.2
License: GPL3 or later
License URI: http://www.gnu.org/licenses/gpl.html

Love your database. Keep expired events under control.

== Description ==

Developed to work with The Events Calendar 3.0, this plugin allows for expired events to be automatically vacuumed up rather than leaving them to clutter up your database.
_If you are still using The Events Calendar 2.x then you should use [this older version of Housekeeper](http://downloads.wordpress.org/plugin/the-events-calendar-housekeeper.1.0.3.zip)_,
noting that it still has some minor bugs and of course remembering that awesome people use the latest and greatest.

* Configurable: clean up events almost as soon as they have expired or enforce a buffer period of 1 week upto 6 months
* Recurring events are intelligently curtailed (the expired instances will be removed, other events remain untouched)

By default, the plugin schedules clean-up tasks to run once a day and limits itself to handling a maximum of 100 events at any one time. This can be adjusted programmatically for special cases.

= Author =
This plugin was written by [Barry Hughes](http://codingkills.me "WordPress and PHP Developer based in BC, Canada") (it _is not_ an official plugin by Modern Tribe, so don't go pestering them for support). If this helps you out then
[buy the plugin author a beer!](http://freshlybakedwebsites.net/say-thanks-with-a-beer/) More than anything, it will make you feel good about yourself.

== Installation ==

Like any other plugin you simply upload the plugin directory to the wp-content/plugins directory. You can also upload and install it through the WordPress plugins admin page.

*Remember that a prerequisite is the existence of The Events Calendar 2.0.9* (though the latest version targets The Events Calendar 3.0).

Once installed and activated a new "Housekeeping" tab will appear on the Events > Settings page. You must enable garbage collection via this tab or it will not do anything.

== Frequently Asked Questions ==

= How are recurring events handled? =
Any instances of recurring events meeting the _Expiry Criteria_ are deleted - those instances not meeting the criteria are preserved. This seemed like the most logical way to approach recurring events but any other ideas are welcome.

= What if the wrong events are deleted? =
That's completely possible for a variety of reasons. First of all, ensure your server and WordPress date/time settings are correct. Second, but more importantly, back-up before you use it and then keep on backing-up, frequently and often.
You _should_ be doing this anyway - and remember! - a back-up is useless unless you know how to restore it.

= Are they trashed or deleted out-right? =
They are deleted out right. So don't confuse this with the "Trash" function WordPress provides for pages, posts and many custom post types. With this plugin, any events deemed to have expired will effectively be wiped out forever.

= Does an event expire after it has started or after it has ended? =
In the eyes of this plugin an event has expired after it has started and this is flagged up in the settings tab. That may not always be ideal - and for those cases you
can adjust the expiry criteria appropriately or just deactivate this plugin.

= I found a bug =
Please post details on the forum. Better yet, post a fix and add appropriate details. This is free and open source software and comes with no guarantees, so bear that
in mind first of all.

= I need help! =
Feel free to post on the support forum, but remember that support is not guaranteed (nor is the plugin) ... after all, it's free.

== Screenshots ==

1. The Housekeeping tab in Events > Settings

== Changelog ==

= 1.0.3 =
* Recurring event logic added.
* General release.

== Upgrade Notice ==

No major updates yet!