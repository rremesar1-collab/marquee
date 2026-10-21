=== Marquee – Show Website for xLights & FPP ===
Contributors: alateka
Donate link: https://www.buymeacoffee.com/marqueeforxlights
Tags: christmas lights, xlights, fpp, remote falcon, light show
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.8.6
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

= 0.8.6 =
* Pick the Next Song leaves out the songs ticked under "Leave out of song voting" (like your intro), the same as favorite song voting.

= 0.8.5 =
* Countdown: count down to Christmas, Halloween or a date you pick (with its own name, like New Year's Eve). Automatic picks Halloween when your last night is in August to October. Settings → Schedule → Countdown.
* Pick the Next Song: add your Remote Falcon viewer page (Settings → Remote Falcon) and Tonight's Show gets a "Pick the Next Song" button while the show is running. Visitors see your songs and vote or request right on your website; Remote Falcon's own rules (one pick each, queue size, location check) still apply.

= 0.8.4 =
* Names script 3.8.1: restarts itself with the new version when it's updated (by the Marquee FPP plugin or a new copy put on by hand), so there's nothing to restart after an update.
* Marquee FPP plugin: updating or reinstalling it on the FPP keeps your connection, so names keep going up without connecting again.

= 0.8.3 =
* Your screens: each screen now has Text (Automatic, Still or Scrolling), Size (Automatic to Fill the screen, or Custom with an exact font size) and Scroll speed. Automatic gives small pixel matrices (64 pixels tall or less, like a 40 x 10 seed matrix) one big line that scrolls across when it won't fit; TVs and projectors look the same as before. Needs names script 3.8.
* Kids' Corner: the Show Wall is now right on the Kids' Corner page, under the activities, instead of a third tab in Make a Light Show. Links to #show-wall still work. The Kids' Corner button on the home page says visitors can vote for the kids' shows while the contest is open.
* Kids' Show Wall on the home page: the newest approved contest shows with their hearts, and a "See all shows & vote" button. Tap one to watch it on the Kids' Corner. Off by default; turn it on in Customize → Kids' Corner.
* Publishing a contest entry or show video now clears your page cache, so it shows up for visitors right away.
* Behind the Lights: when you hide some tiles, the rest stay centered.
* Marquee FPP plugin: install "Marquee" from FPP's Plugin Manager, paste the connection code from Settings → Names & FPP, and it runs the names script for you. No files to upload, no Command Presets, and changes to your screens on the website reach the FPP by themselves.
* Names script 3.7: takes your screens and names break timing from the website on every check-in, restarts itself when its settings file changes, and won't run twice if both the plugin and a copy put on by hand are there.
* Get help: Marquee Support can run the setup check, set up the names break playlists again or restart the names script on your FPP during a support session. Each one shows in your chat.

= 0.8.2 =
* Get help from Marquee Support (Marquee → Get help): describe the problem and Marquee sends a support report (your setup without any keys or passwords). You can let Marquee Support sign in for 24 hours to fix it with you: they only get Marquee's pages and your media library, every setting they change is written into the chat in plain words, and you can end it any time. Chat on the page with pictures, email when the other side answers, and a live look at your FPP (status, playlists, screens, names script log, test names) through the names script.
* Names script 3.6.1: FPP 10.1 reports "playing media" even while a scheduled playlist is playing, so older scripts never put names up on FPP 10.1. The script now checks the playlist itself.
* Names script 3.6: shows its log and FPP status to Marquee Support while a support session is open, and checks in every 5 seconds during the session.
* Show me how: Connect your FPP, Connect Remote Falcon and Say each name out loud are now guided walkthroughs on the real Settings page (like the others), with every step: turning names on, the names break playlist, saving before downloading, FPP 10's UI level, starting the show as a playlist, and a test name. Steps follow the names mode you picked.
* Setup guide: Command Presets is under Status/Control.
* Names & FPP: the FPP walkthrough ends with "Start your show as a playlist" (on the FPP that runs the names script), and How it works says so too.
* Names script 3.5.1: when names are waiting but FPP isn't playing a playlist (FPP 10 shows "playing media" for audio or a single sequence), the log and the setup check say so plainly instead of waiting silently.
* Names & FPP setup: FPP 10 hides "Run Script" in Command Presets when the User Interface Level is Basic. The setup guide, the walkthrough and the help assistant now say to set it to Advanced first.

= 0.8.1 =
* Your show: shows your app icon and warns when it's too small (blurry on phones and in Google), with a link to change it.
* Behind the Lights: the radio tile shows your station from Settings when you leave its number empty.
* Google: the show's event info now includes a performer (your show), so Search Console stops reporting "Missing field performer".
* Google: the home page tells Google its one true address (canonical), so links with Facebook's ?fbclid=… tracking aren't treated as separate pages; the share link no longer copies those tracking bits. The event's street address no longer repeats the ZIP code or shouts in capitals, and the map point is written with sensible precision.

= 0.8.0 =
* Names form on iPhones: picking a spelling suggestion now fixes the word in the name box. The box no longer shows the name in capitals as you type or has a built-in letter limit (both stopped it); the 14-letter limit is checked when you send.
* Blocked names and words: see and undo the names you blocked, and add your own words (Settings → Names & FPP). The bad-word filter is always on.
* Messages kids can pick: write your own messages for "Pick your message" on the names form (up to 6, with NAME where the name goes), with a preview of how each looks on your screens. Settings → Names & FPP → 💬 Messages kids can pick.
* Names & FPP (renamed from "Your Name in Lights"): a "How it works" picture at the top, and a choice of how names go up: between songs in a names break, or during songs on a spare TV or projector (no extra sequence; names script 3.5).
* Show me how: guided walkthroughs on the Settings page for Sequence maker, Names break audio and Themed sequences. A pointer moves to each control with a spotlight and a short explanation, and the steps in xLights (Render All, FPP Connect) and on the FPP (File Manager, playlists) are shown as pictures. Plays by itself; pause, back and next any time.
* No timing to set: Sequence maker works out when the names go up (from your names break audio, or the Names timing track in your sequence, or your settings), writes it into every sequence it makes (moving the Name marks and the prop flashes to match), and "Use it" fills the numbers in for the theme.
* Sequence maker: Marquee makes your names break sequence and themed versions (Candy Cane Swirl, Snowfall, Rainbow Party… or describe your own with the AI) from your xLights layout, right in your browser. Download the .xsq, render it in xLights, upload the .fseq. The themed versions can be made from your own names break so they keep its music, timing and singing faces.
* Pick your show: each theme card kids tap shows an animated house in that theme's colors, or your own picture; the Classic card can have a picture too. Settings show each card as kids see it.
* Social posts: when you cancel tonight's show, Marquee posts it on your Facebook Page, Instagram and Threads (once a night, with your picture), and optionally "We're back on" if you undo it. Editable messages with fill-ins, a preview, Post it now, and a posting history. Posting runs in the background, and Show Control and the Show Manager show what was posted, plus "Copy the post for other sites".
* Traffic & parking alerts: one tap in Show Control or the Show Manager (Heavy traffic, Driveway blocked, Please keep moving, Parking full, or your own words) puts a strip at the top of every page of your website for 30 minutes to the rest of the night. Optionally a phone alert, and on your screens every 3 minutes (names script 3.5).
* Show Control: new look with a header showing tonight's status, a clock and quick actions; tiles for FPP, names, visitors, weather, social posts and phones getting alerts; Traffic & parking and Social posts cards; more stats (names said out loud, themed picks, social posts, traffic alerts, phone alerts, app installs).
* Themed sequences: kids pick the show their name goes up in (Settings → Names & FPP → Themes kids can pick). The names script (3.4) plays the theme the longest-waiting kid picked; same-theme names go up together.
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
