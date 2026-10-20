# Marquee – Show Website for xLights & FPP

Marquee turns a WordPress site into the home page for your Christmas light show: show status and countdown, Now Playing from Remote Falcon, weather, song voting, a Kids' Corner with the Light Lab and a contest, **Your Name in Lights** on your FPP screens, an installable app with alerts, and a Show Manager you can run from your phone.

Your website never connects into your house. Remote Falcon is reached from the server with your API keys, and your FPP reaches out to the website, so you don't open any ports.

## What's here

| Folder | What it is |
|---|---|
| `plugin/marquee/` | The **Marquee** plugin. It has all the show features and settings, and works with any theme (blocks and shortcodes). |
| `theme/ridgeroad/` | The **Marquee Festive** theme: garlands, bows, the night-sky scene and glowing show cards. Needs the plugin. (The folder is named `ridgeroad` after the show it was first built for. Keep that name so updates keep working.) |
| `plugin/marquee/core/fpp/` | `marquee_names.py` and the setup guide for Your Name in Lights on FPP. |

## What you need

- A WordPress website (6.0 or newer, PHP 7.4 or newer). Any host works; https is needed for the app and alerts.
- **For Now Playing, the playlist and song voting:** a free [Remote Falcon](https://remotefalcon.com) account **and the Remote Falcon plugin installed and running on your FPP** (FPP → Content Setup → Plugin Manager → Remote Falcon). Marquee reads what's playing from Remote Falcon, which gets it from that plugin.
- **For Your Name in Lights:** FPP (tested with FPP 10) and a matrix, projector or TV that FPP can draw on.
- **To have each name said out loud (optional):** an [ElevenLabs](https://elevenlabs.io) account and FPP 10 or newer. Marquee makes the spoken names and the names break audio (intro, soft music, outro) with your ElevenLabs voice, and the FPP script sets up the break playlists by itself.

Everything else (status, weather, Kids' Corner, guestbook, app) works with no extra setup.

## Make your sequences

Marquee → Settings → Names & FPP → **Make your sequences** builds the names break sequence, and themed versions kids can pick (Candy Cane Swirl, Snowfall… or describe your own with the AI), from your xLights layout. It runs in the browser: `xlights_rgbeffects.xml` is never uploaded. Download the `.xsq`, render it in xLights, and upload the `.fseq` to FPP.

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
