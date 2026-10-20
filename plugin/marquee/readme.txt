=== Marquee – Show Website for xLights & FPP ===
Contributors: alateka
Donate link: https://www.buymeacoffee.com/marqueeforxlights
Tags: christmas lights, xlights, fpp, remote falcon, light show
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete website for your Christmas light show: show status, Now Playing from Remote Falcon, weather, song voting, a Kids' Corner with a Light Lab and contest, Your Name in Lights on your FPP screens, and an installable app with alerts.

== Description ==

Marquee turns a WordPress site into your light show's home page. Answer a few questions in the setup wizard and it is ready.

* **Show status and countdown** – show tonight, starting soon, canceled or off season, updated by itself.
* **Tonight's Show** – radio station, show hours, Now Playing and Up Next from Remote Falcon, directions.
* **Weather** – now and tonight hour by hour, with live radar (National Weather Service in the US, Windy.com everywhere else).
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
2. The setup wizard opens. Fill in your show, location, schedule and (optionally) Remote Falcon. For Now Playing, the Remote Falcon plugin must be installed on your FPP.
3. Optional: install the Marquee Festive theme (Appearance → Themes → Add New → Upload Theme).
4. Follow the setup checklist at the top of Marquee → Settings.

For Your Name in Lights, see Marquee → Settings → Names & FPP → "Setup guide".

== Frequently Asked Questions ==

= Do I need Remote Falcon? =
No. Without it the site still shows status, hours, weather and the Kids' Corner. Remote Falcon adds Now Playing, the playlist and song voting.

To use it you need a free Remote Falcon account **and the Remote Falcon plugin installed and running on your FPP** (FPP → Content Setup → Plugin Manager → Remote Falcon, then paste your show token from Remote Falcon). That plugin tells Remote Falcon what is playing; Marquee reads it from Remote Falcon with your API keys. Without the FPP plugin, Now Playing stays empty even if the connection test passes.

= Does it work with my theme? =
Yes. With the Marquee Festive theme the show sections use its look. With any other theme they get Marquee's own clean styling and use your theme's fonts.

= Where do I report problems? =
Marquee → Settings → "Send feedback", or email support@alateka.com.

== Changelog ==

= 0.8.0 =
* Names & FPP (renamed from "Your Name in Lights"): a "How it works" picture at the top, and a choice of how names go up: between songs in a names break, or during songs on a spare TV or projector (no extra sequence; names script 3.5).
* Show me how: guided walkthroughs on the Settings page for Make your sequences, Names break audio and Themed sequences. A pointer moves to each control with a spotlight and a short explanation, and the steps in xLights (Render All, FPP Connect) and on the FPP (File Manager, playlists) are shown as pictures. Plays by itself; pause, back and next any time.
* Make your sequences: Marquee makes your names break sequence and themed versions (Candy Cane Swirl, Snowfall, Rainbow Party… or describe your own with the AI) from your xLights layout, right in your browser. Download the .xsq, render it in xLights, upload the .fseq. The themed versions can be made from your own names break so they keep its music, timing and singing faces.
* Pick your show: each theme card kids tap shows an animated house in that theme's colors, or your own picture; the Classic card can have a picture too. Settings show each card as kids see it.
* Social posts: when you cancel tonight's show, Marquee posts it on your Facebook Page, Instagram and Threads (once a night, with your picture), and optionally "We're back on" if you undo it. Editable messages with fill-ins, a preview, Post it now, and a posting history. Posting runs in the background, and Show Control and the Show Manager show what was posted, plus "Copy the post for other sites".
* Traffic & parking alerts: one tap in Show Control or the Show Manager (Heavy traffic, Driveway blocked, Please keep moving, Parking full, or your own words) puts a strip at the top of every page of your website for 30 minutes to the rest of the night. Optionally a phone alert, and on your screens every 3 minutes (names script 3.5).
* Show Control: new look with a header showing tonight's status, a clock and quick actions; tiles for FPP, names, visitors, weather, social posts and phones getting alerts; Traffic & parking and Social posts cards; more stats (names said out loud, themed picks, social posts, traffic alerts, phone alerts, app installs).
* Themed sequences: kids pick the show their name goes up in (Settings → Names & FPP → Themed sequences). The names script (3.4) plays the theme the longest-waiting kid picked; same-theme names go up together.
* Need help? assistant on the Settings page: pick ChatGPT or Claude. It knows Marquee and already sees your setup and your FPP's setup check, so you don't have to explain it. Use your own API key, or your host can include it.
* Settings: one section at a time, Remote Falcon and Names & FPP (Your Name in Lights) as their own sections, step cards with status.
* Spoken names (optional): an ElevenLabs voice reads each name out loud as it goes up ("Merry Christmas, Emma!"). Set it up in Marquee → Settings → Names & FPP → "Say each name out loud": paste your key, load your voices, test one. Clips are saved, so a repeated name costs nothing. Optional "talking faces" effect moves your singing faces' mouths while a name is spoken. Speaking speed and "My voice is Santa" options. Clips are stereo so FPP plays them at the right speed. Needs marquee_names.py 3.1.3 and FPP 10 on your FPP.
* Spoken names are leveled to about -14 LUFS, the usual level of show songs.
* Names break audio (Settings → Names & FPP → "Names break audio"): your voice says an intro and an outro, music plays soft under the names, all leveled to song loudness. Also makes short breaks for 1 or 2 names (each said twice). The names script (3.3) downloads it and sets up the playlists by itself.
* "First name goes up at" and "Seconds per name" settings (set automatically by the break builder).
* Coloring pages: Remove takes a standard page out of the Settings list (into "Removed pages", where Restore brings it back).

= 0.7.11 =
* Coloring pages: Marquee → Settings → Artwork shows the pages that come with Marquee, each with a Show switch. With every page off and none of your own, the Coloring Pages door is hidden.

= 0.7.10 =
* "Only at the show": pick miles or km right next to the distance (Marquee → Settings → Features). Switching converts the number for you.

= 0.7.9 =
* Logo: Marquee → Settings shows your current logo, previews a new one as soon as you pick it, and has a Remove logo option.
* Settings and docs say clearly that Now Playing needs the Remote Falcon plugin installed on your FPP.

= 0.7.8 =
* Marquee → Settings → Location now shows your country (filled in by Find) and says where your weather and radar come from.
* Shows outside the US always get the Windy.com radar, even if a US radar station was left in the settings.

= 0.7.7 =
* Shows outside the US: a live radar map and a "View Full Weather Forecast" button from Windy.com, in °C and km/h for metric countries.
* "Only at the show" distance is set and shown in kilometres for metric countries.

= 0.7.6 =
* "Buy me a coffee" link on Marquee → Settings and in the Plugins list (admins only; visitors never see it).

= 0.7.5 =
* Instagram and YouTube links, next to Facebook and TikTok.
* Each social link has its own Show switch, in Marquee → Settings → Your show and in Appearance → Customize.

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
