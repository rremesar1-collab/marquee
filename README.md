# Marquee – Show Website for xLights & FPP

Marquee turns a WordPress site into the home page for your Christmas light show: show status and countdown, Now Playing from Remote Falcon, weather, song voting, a Kids' Corner with the Light Lab and a contest, **Your Name in Lights** on your FPP screens, an installable app with alerts, and a Show Manager you can run from your phone.

**See it live:** [lightsonridgeroad.com](https://lightsonridgeroad.com), the show it was first built for.

Your website never connects into your house. Remote Falcon is reached from the server with your API keys, and your FPP reaches out to the website, so you don't open any ports.

## Features

**For your visitors**
- Show status that updates itself (on tonight, starting soon, canceled, off season) and a countdown to Christmas, Halloween or your own date
- Tonight's Show: radio station, hours, directions, Now Playing and Up Next from Remote Falcon
- Pick the Next Song right on your site (Remote Falcon voting or requests), plus favorite song voting with a weekly Fan Favorite
- Weather now and tonight hour by hour with live radar (National Weather Service in the US, Windy.com everywhere else, °C for metric countries)
- Sequence videos and a YouTube live cam
- Add it to the home screen like an app and get a buzz when the lights come on
- Guestbook, donation link with QR code, Before You Visit tips and social links

**Kids' Corner**
- **Your Name in Lights:** kids send a first name and it goes up on your matrix, projector or TV between songs, never during one. Or on a spare screen during songs.
- Names said out loud over your radio in an ElevenLabs voice (Santa works great), or with your own names break audio
- Kids pick a themed show for their name (Candy Cane Swirl, Snowfall, Rainbow Party or your own) and pick their message
- Light Lab: kids design a light show on a house, with an optional contest and a Show Wall where people vote
- Coloring pages with your show's name, and games
- Bad-word filter always on, your own blocked words, and names can be limited to phones at your show

**For you**
- Show Manager on your phone (yourshow.com/manage): cancel a night, pause names, approve messages and contest entries, send alerts. "Show helper" logins for family that can't touch anything else
- Show Control dashboard: tonight's status, FPP, visitors, names and stats
- Cancel a night and it posts to your Facebook Page, Instagram and Threads
- One-tap traffic and parking alerts on your website, phones and screens
- Setup wizard, a setup checklist, and "Show me how" walkthroughs on the real Settings page
- Built-in help assistant, and an optional Marquee Support session where support can sign in for 24 hours with every change shown to you

## What's here

| Folder | What it is |
|---|---|
| `plugin/marquee/` | The **Marquee** plugin. It has all the show features and settings, and works with any theme (blocks and shortcodes). |
| `theme/ridgeroad/` | The **Marquee Festive** theme: garlands, bows, the night-sky scene and glowing show cards. Needs the plugin. (The folder is named `ridgeroad` after the show it was first built for. Keep that name so updates keep working.) |
| `plugin/marquee/core/fpp/` | `marquee_names.py` and the setup guide for Your Name in Lights on FPP. |
| [fpp-marquee](https://github.com/rremesar1-collab/fpp-marquee) | The **Marquee plugin for FPP** (its own repo): runs the names script for you and keeps it updated. |

## What you need

- A WordPress website (6.0 or newer, PHP 7.4 or newer). Any host works; https is needed for the app and alerts.
- **For Now Playing, the playlist and song voting:** a free [Remote Falcon](https://remotefalcon.com) account **and the Remote Falcon plugin installed and running on your FPP** (FPP → Content Setup → Plugin Manager → Remote Falcon). Marquee reads what's playing from Remote Falcon, which gets it from that plugin.
- **For Your Name in Lights:** FPP and a matrix, projector or TV that FPP can draw on (a Pixel Overlay Model). The Marquee FPP plugin needs FPP 10 or newer; on FPP 9, put the names script on by hand (see below).
- **To have each name said out loud (optional):** an [ElevenLabs](https://elevenlabs.io) account and FPP 10 or newer. Marquee makes the spoken names and the names break audio (intro, soft music, outro) with your ElevenLabs voice, and the FPP script sets up the break playlists by itself.

Everything else (status, weather, Kids' Corner, guestbook, app) works with no extra setup.

## Your Name in Lights: connecting your FPP

1. **On the FPP that plays your show**, install the **Marquee** plugin: Status/Control → Settings → UI → User Interface Level: Developer, then Content Setup → Plugin Manager, paste the plugin address shown in Marquee → Settings → Names & FPP, and click Install.
2. Open **Content Setup → Marquee**, paste the **connection code** from your website, and click **Connect**.
3. Back on your website, **pick your screen** from the list (your FPP sends its overlay models) and Save.

The setup panel at the top of **Names & FPP** shows every step with a ✓ or ✗ and turns green by itself as your FPP checks in. **Check again** reruns the FPP's setup check, **Send a test name** puts a name on your screens right away, and **Put it in line** adds a test name to the line like a kid would, so you can try a full names break from your computer.

**On FPP 9, or if you'd rather not use the plugin:** download `marquee_names.py` and `marquee_names.json` from card 1 ("Or put the script on your FPP by hand"), upload both in File Manager → Scripts, and add a Run Script command preset for FPPD_STARTED. The setup guide in card 1 has every step.

### The names break

When names are waiting, the FPP slips your **Names in Lights** playlist in after the current song and the names go up on your screens one at a time.

- **No names sequence yet?** Leave "Make a simple names break for me" on and Marquee makes one: the show pauses, the names go up, then your show carries on.
- **Names break audio:** Marquee can build the break's sound for you (intro, soft music under the names, outro) in your ElevenLabs voice, plus short breaks for 1 or 2 names. Already have your own? Turn on **Use my own names break audio** and set when the first name appears and the time between names to match it.
- **Names per break:** fewer names, each up longer, so scrolling text on a small matrix moves slower.
- **Names break after every 1 to 6 songs:** names collect and go up together, so a busy night doesn't put a break between every song. If 6 are waiting, the break comes sooner.
- **Only during these playlists:** names wait during something like a static display and go up once your show playlist plays.
- **Text, size and scroll speed** are set per screen. Use the overlay model your sequence draws on (not the raw HDMI "FB" model), so names go up on top of your sequence instead of blanking it; the setup check tells you if you picked the wrong one.

## Sequence maker

Marquee → Settings → Names & FPP → **Sequence maker** builds the names break sequence, and themed versions kids can pick (Candy Cane Swirl, Snowfall… or describe your own with the AI), from your xLights layout. It runs in the browser: `xlights_rgbeffects.xml` is never uploaded. It works out the names timing for you. Download the `.xsq`, render it in xLights, and upload the `.fseq` to FPP (File Manager, or straight from xLights with FPP Connect). The **Show me how** buttons walk through each step.

## Social posts

Cancel tonight's show in Show Control or the Show Manager and Marquee posts it on your Facebook Page, Instagram and Threads (once a night, with your picture), using your own free Meta app. Marquee → Settings → Social posts has the steps.

## Traffic & parking alerts

One tap in Show Control or the Show Manager (Heavy traffic, Driveway blocked, Please keep moving, Parking full, or your own words) puts a strip at the top of your website, optionally sends a phone alert and shows it on your screens. It turns itself off when the time is up.

## Help assistant (optional)

Marquee → Settings has a **Need help?** button: an assistant that knows how Marquee works and already sees the site's setup and the FPP's setup check, so the owner never has to describe their setup and it can point at the exact problem. The owner picks the AI: **ChatGPT** (OpenAI) or **Claude** (Anthropic). Paste either API key in the panel and Marquee works out which one it is. Hosts can include it for their customers instead by adding to `wp-config.php`:

```php
define( 'MARQUEE_AI_KEY', 'sk-...' );          // the host's key (ChatGPT or Claude), never shown to the site owner
define( 'MARQUEE_AI_PROVIDER', 'chatgpt' );    // optional: 'chatgpt' or 'claude' (worked out from the key if left out)
define( 'MARQUEE_AI_DAILY', 40 );              // optional: questions per site per day
```

Questions and the site's setup status (never passwords or keys) are sent to the AI picked (OpenAI or Anthropic) to answer.

## Install

1. Download `marquee-x.y.z.zip` (and `marquee-festive-x.y.z.zip` for the festive look) from the [latest release](../../releases/latest).
2. WordPress → Plugins → Add New → Upload Plugin → choose the Marquee zip → Install → Activate. The setup wizard opens.
3. Optional: Appearance → Themes → Add New → Upload Theme → choose the Marquee Festive zip.
4. Follow the setup checklist at the top of Marquee → Settings.

After that, new versions show up as normal WordPress updates.

## Releasing a new version (maintainers)

1. Bump the version in `plugin/marquee/marquee.php` (header **and** `MARQUEE_VERSION`) and `plugin/marquee/readme.txt` (Stable tag + changelog).
2. If the theme changed, bump `Version:` in `theme/ridgeroad/style.css`, `theme/ridgeroad/readme.txt` and `RIDGEROAD_VERSION` in `functions.php`.
3. Add the notes as `release-notes/vX.Y.Z.md`, commit, and push.
4. On GitHub: **Actions → Release → Run workflow**. It reads the version from `marquee.php`, runs `./build.sh`, and publishes release `vX.Y.Z` with both zips (`marquee-<version>.zip` and `marquee-festive-<version>.zip`, the names the update checker looks for). Pushing a tag `vX.Y.Z` does the same.

WordPress sites check for a new release every 6 hours, or right away from Dashboard → Updates → Check again. The theme update is only offered to sites running Marquee Festive (it has a `.marquee-dist` marker file), never to a show's own custom copy of the theme.

## Support Marquee

Marquee is free. If it makes your show better, you can [buy me a coffee ☕](https://www.buymeacoffee.com/marqueeforxlights).

## License

GPLv2 or later. Bundled fonts are under the SIL Open Font License (see `theme/ridgeroad/assets/fonts`).
