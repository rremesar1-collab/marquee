=== Marquee – Show Website for xLights & FPP ===
Contributors: alateka
Tags: christmas lights, xlights, fpp, remote falcon, light show
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete website for your Christmas light show: show status, Now Playing from Remote Falcon, weather, song voting, a Kids' Corner with a Light Lab and contest, Your Name in Lights on your FPP screens, and an installable app with alerts.

== Description ==

Marquee turns a WordPress site into your light show's home page. Answer a few questions in the setup wizard and it is ready.

* **Show status and countdown** – show tonight, starting soon, canceled or off season, updated by itself.
* **Tonight's Show** – radio station, show hours, Now Playing and Up Next from Remote Falcon, directions.
* **Weather** – now and tonight hour by hour, with the radar (US).
* **Songs & Videos** – this year's sequence videos and favorite-song voting with a weekly Fan Favorite.
* **Kids' Corner** – Your Name in Lights, Make a Light Show (the Light Lab, with an optional contest and Show Wall), coloring pages and games.
* **Your Name in Lights** – kids send a first name; a small script on your FPP shows it on your matrix, projector or TV between songs. Optionally only from phones at your show.
* **App and alerts** – visitors add the show to their phone's home screen and get a buzz when the lights come on (needs https).
* **Show Control** – night-of switches: cancel tonight's show, pause names, send an alert.
* **Show Manager** – the same show-night controls on your phone at yourshow.com/manage, plus contest and guestbook approval. Give helpers a "Show helper" account that can use it and nothing else.
* **Guestbook**, **donation link** with QR code, **Before You Visit** tips and social links.

Use it with the free **Marquee Festive** theme for the full Christmas look, or with any theme: every section is a block (look for the "Marquee (light show)" group) or a shortcode like `[marquee_kids]`.

Your website never connects into your house. Remote Falcon is reached with your API keys from the server, and your FPP reaches out to the website, so no ports are opened.

== Installation ==

1. Plugins → Add New → Upload Plugin → choose the Marquee zip → Install → Activate.
2. The setup wizard opens. Fill in your show, location, schedule and (optionally) Remote Falcon.
3. Optional: install the Marquee Festive theme (Appearance → Themes → Add New → Upload Theme).
4. Follow the setup checklist at the top of Marquee → Settings.

For Your Name in Lights, see Marquee → Settings → Connections → "Setup guide".

== Frequently Asked Questions ==

= Do I need Remote Falcon? =
No. Without it the site still shows status, hours, weather and the Kids' Corner. Remote Falcon adds Now Playing, the playlist and song voting.

= Does it work with my theme? =
Yes. With the Marquee Festive theme the show sections use its look. With any other theme they get Marquee's own clean styling and use your theme's fonts.

= Where do I report problems? =
Marquee → Settings → "Send feedback", or email support@alateka.com.

== Changelog ==

= 0.7.4 =
* Maintenance release to confirm automatic updates. No changes to how your site works.

= 0.7.3 =
* Updates work on shared hosting: when GitHub's hourly limit for your server is used up, Marquee reads the public release page instead.
* Plugins page shows when Marquee last checked for updates; Dashboard → Updates says why if a check failed.

= 0.7.2 =
* Admin messages and error logs say Marquee instead of the original show's name.

= 0.7.1 =
* Your Name in Lights: "Only at the show" – names can only be sent from phones within a distance you choose (the phone's location is checked and never saved).
* "Show owner" link at the bottom of every page, to the Show Manager.

= 0.7.0 =
* Show Manager: run show nights from your phone at yourshow.com/manage (cancel the show, names, contest entries, guestbook messages, alerts).
* New "Show helper" role for family and friends: Show Manager only, no other WordPress access.
* Setup wizard: "Your show's website is ready" screen and a setup checklist at the top of Marquee → Settings.
* Contest entries, show videos and Names in Lights now sit under the Marquee menu.
* Updates: new versions show up as normal WordPress updates.
* "Send feedback" link.

= 0.6.0 =
* Leave out of song voting: hide intros and announcements from Pick Your Favorite.
* Your Name in Lights for any show: marquee_names.py, a settings file to download, a setup guide, and the FPP setup check shown in Marquee → Settings.

= 0.5.0 =
* Light Lab: the yard sign shows your radio station; upload your own songs; Light Lab on/off switch.

= 0.4.1 =
* The guestbook shows on every Marquee site (no outside key needed).

= 0.4.0 =
* Artwork: your own house coloring page, extra coloring pages and door pictures. Coloring pages print your show's name or logo.

= 0.3.0 =
* Every show section is a block and a shortcode; works with any theme. "Make my show pages" in the wizard.

= 0.2.1 =
* Safe to install in any order with the festive theme.

= 0.1.0 =
* First version: setup wizard, Remote Falcon connection.
