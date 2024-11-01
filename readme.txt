=== wp2flickr ===
Contributors: fsimo
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=ZCCHQ2ZHUGHDG&lc=US&item_name=wp2flickr%20plugin%20donation&item_number=wp2flickr&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: yapb,  photoblog, photo blog, photo blogging, images, yet another photoblog, flickr, flickr upload
Requires at least: 3.8
Tested up to: 3.9.1
Stable tag: 0.15

Uploads photos from WordPress posts to Flickr.
It works with standard Wordpress media and with YAPB plugin (recomended).

== Description ==
Uploads photos from WordPress posts to Flickr.
It works with standard Wordpress media and with YAPB plugin (recomended).
Perfect for photoblogging.

= Typical usage =
* Anytime you publish a new post it will be uploaded to flickr.

== Installation ==
Download and activate.
Go to Settings->wp2flickr menu and follow the steps.

== Frequently Asked Questions ==
None for now.

== Screenshots ==

1. wp2flickr settings

== Changelog ==

= 0.15 =
* Flickr HTTPS endpoints (+CURL options)  

= 0.14 =
* Flickr HTTPS endpoints (CURL options and some regexpr un phpFlickr)  

= 0.13 =
* Flickr HTTPS endpoints (Flickr API Going SSL-Only)  

= 0.12 =
* PHP 5.3 warnings about deprecateds  

= 0.11 =
* Ensure the log file doesn't exceed 5Mb.

= 0.10 =
* Add photos to groups depending on its categories

= 0.9.2 =
* Multiple groups post bug fixed
* Added message "join wp2flikgr users group" 

= 0.9.1 =
* Add manual token option for problems with callback to flickr
* Rename phpFlickr class to avoid collitions with other plugins
* if token is not set exit publish hook

= 0.9 =
* Initial public released version