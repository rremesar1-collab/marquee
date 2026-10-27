=== Marquee – Show Website for xLights & FPP ===
Contributors: alateka
Donate link: https://www.buymeacoffee.com/marqueeforxlights
Tags: christmas lights, xlights, fpp, remote falcon, light show
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.12.3
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

For Your Name in Lights, see Marquee → Settings → Names in Lights → Connect your FPP.

== Frequently Asked Questions ==

= Do I need Remote Falcon? =
No. Without it the site still shows status, hours, weather and the Kids' Corner. Remote Falcon adds Now Playing, the playlist and song voting.

To use it you need a free Remote Falcon account **and the Remote Falcon plugin installed and running on your FPP** (FPP → Content Setup → Plugin Manager → Remote Falcon, then paste your show token from Remote Falcon). That plugin tells Remote Falcon what is playing; Marquee reads it from Remote Falcon with your API keys. Without the FPP plugin, Now Playing stays empty even if the connection test passes.

= Does it work with my theme? =
Yes. With the Marquee Festive theme the show sections use its look. With any other theme they get Marquee's own clean styling and use your theme's fonts.

= Where do I report problems? =
Marquee → Settings → "Send feedback", or email support@alateka.com.

== Changelog ==

= 0.12.3 =
* Turning the language button on or off now clears your page cache (WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, SiteGround), so cached pages don't keep showing the old button.

= 0.12.2 =
* Marquee Festive: on phones, Christmas Village and Santa's North Pole put the donation box and Stay Connected one under the other again (the donation box was cut off).

= 0.12.1 =
* New switch in Customize → Show: “Hide the “show on” banner”. The status banner then only shows when you cancel a night (and in the off season), and pops up by itself the moment you cancel.

= 0.12.0 =
* Your own words in every language: with the language button on, Customize has an extra box under each of your texts (banner messages, Before You Visit tips, titles, button words) for each other language. Visitors who pick that language see those words; leave a box empty to use your main wording.

= 0.11.3 =
* Marquee Festive: on phones, Christmas Village and Santa's North Pole no longer show a second copy of the picture behind the page while scrolling (seen on iPhones).

= 0.11.2 =
* Behind the Scenes and Before You Visit: a musical note is now one of the icons you can pick (Customize → Show Website).

= 0.11.1 =
* With a theme other than Marquee Festive, the Pick the Next Song, show video and Kids' Show Wall pop-ups could show black text with no background (when the theme or a page builder moves them out of the page). They now keep their own look wherever they end up.

= 0.11.0 =
* Language button for visitors: tick English, Español and/or Français in Settings → Website basics → Languages and a small button on your website lets each visitor switch. Their pick is remembered. The status sign, Kids' Corner, names form, dates and live updates all switch; words you typed yourself stay as you wrote them.
* Standard texts you saved unchanged in Customize now follow the site's language too (before, they stayed in English).

= 0.10.0 =
* Settings reorganized: Setup home (your next step, what's done, your show's connections), Website basics, Features & pages, Names in Lights in six steps, Remote Falcon, Artwork & Kids' Corner, Sharing & donations, Help. Same settings, same values.
* Kids' Corner: each part has its own switch (Your Name in Lights, Make a Light Show, the contest, Coloring Pages, Games), grouped under Kids' Corner in Settings → Features & pages.
* Kids' pictures on your screens (off unless you turn it on): kids can add a cartoon of themselves, or a real photo, to their name. You approve every picture in Show Control or the Show Manager first; a parent has to tick a box; pictures are deleted right after they're shown. Needs FPP 10+ and names script 3.15.
* Optional automatic check for kids' pictures with your own Google Cloud Vision key: a clean picture with one face and no words goes up without waiting for you; anything else waits, with the reason.
* Your own names break audio: set when the intro ends and the closing starts on your FPP (Content Setup → Marquee) and names are only said in between, never over your announcer. Names script 3.15.
* Spanish and French: the website, Kids' Corner and Marquee's settings show in the language picked in Settings → General → Site Language. No language buttons for visitors; English sites are unchanged.
* Two new looks for the Marquee Festive theme: Christmas Village and Santa's North Pole (Appearance → Customize → Show Website → Look). Classic stays the default.
* Song covers in Tonight's Playlist and Vote for Your Favorite Song: the artwork you set in Remote Falcon, or an album cover Marquee finds by song and artist (saved on your site, only when the song matches). Change any cover, or pick No cover, in Settings → Remote Falcon → Song covers. Turn covers or the automatic finding off there too.

= 0.9.6 =
* Show videos can be a YouTube link: paste it in the video's Video box. The card shows the YouTube picture and the video plays in YouTube's player in the pop-up. Handy when your website limits upload size.

= 0.9.5 =
* Check for updates now: a link under Marquee on the Plugins page asks GitHub right away, so a new version shows up at once instead of within 6 hours.
* With "Names break after every" set to more than 1 song, the names form tells kids about how many songs until their name goes up (instead of "after this song"), Show Control says when the next break is, and the phone buzz comes when the names break is actually next. Needs names script 3.13.

= 0.9.4 =
* Names script 3.12.1: a scrolling name always finishes within its turn. On Slow, a long message could outlast its turn; when it finished, FPP cleared the screen and wiped out the next name, so that name was said but never seen. Slow now uses the whole turn and never runs into the next name.

= 0.9.3 =
* Names break after every 1 to 6 songs (Settings → Names & FPP → card 1): names collect while your show plays and go up together after that many songs, so a busy night doesn't put a break between every song. If 6 names are waiting, the break comes sooner. Needs names script 3.12.

= 0.9.2 =
* Names script 3.11.1: when your website redirects the FPP (http to https, or with/without www), the names it showed, its setup check and its version were lost on the way, so the same name came back after every song and the setup panel never updated. The script now follows the redirect without losing anything, and uses the right address from then on.

= 0.9.1 =
* Names per break (Settings → Names & FPP → card 1): fewer names in a break, each up longer. At 3, every name gets twice the time, so scrolling messages move half as fast and are easier to read on small matrices. The names still fit the same part of your break audio; extra names go up in the next break. Needs names script 3.11.

= 0.9.0 =
* Names & FPP: a setup panel at the top shows each step (FPP connected, your screen, the names break, which playlist, a test name) and turns green by itself as your FPP reports in. "Show me" opens the right card.
* Pick your screen from a list: the FPP sends its Pixel Overlay Models (with their size), so there's nothing to type. A screen picked with no FPP address now uses this FPP instead of being dropped.
* Check again: reruns the FPP's setup check from the website. The check also reruns by itself when you change your screens or playlists.
* Send a test name: puts a name on your screens right away.
* No names break sequence needed: if your FPP has no names break playlist, Marquee makes a simple one (the show pauses, the names go up, with your names break audio if you built it). A names break playlist you already have is never changed. Settings → Names & FPP → "Make a simple names break for me".
* Names only during your show: when your FPP schedules more than one playlist, Marquee suggests your show playlist with a one-click button. Nothing is changed unless you click it.
* A tip when names would scroll too fast to read on a screen big enough to show them still.
* Use my own names break audio (Settings → Names & FPP → card 3): Marquee leaves the audio in your names break playlists alone and puts names up at your own timing.
* Put it in line: a test name goes in the queue like a kid's name (voice and all), for testing the names break from your computer.
* Names script 3.10.0 (in the Marquee FPP plugin):
  * The start of a names break is caught within a quarter second: checking in with your website and downloading spoken names happen in the background, and the break uses the names it already has instead of waiting on the internet.
  * A screen whose FPP is switched off or unplugged no longer delays every name (and Santa's voice) by several seconds: names go to all screens at once, and a screen that doesn't answer is skipped for a minute.
  * Spoken names are fetched as soon as they arrive, even before the show starts, so the first name isn't late.
  * A name running late skips its turn instead of two going up at once.
  * A name going round again (fewer names than turns) isn't said over the last one or over the outro.
  * A theme pointed at your regular names break playlist now uses the break audio's timing, so names stay in step with the announcer.
  * The setup check warns when a screen is set to the raw HDMI model (FB), which blanks your sequence while a name is up, and names the model to use instead.

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
