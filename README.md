# Marquee – Show Website for xLights & FPP

Marquee turns a WordPress site into the home page for your Christmas light show: show status and countdown, Now Playing from Remote Falcon, weather, song voting, a Kids' Corner with the Light Lab and a contest, **Your Name in Lights** on your FPP screens, an installable app with alerts, and a Show Manager you can run from your phone.

Your website never connects into your house. Remote Falcon is reached from the server with your API keys, and your FPP reaches out to the website, so you don't open any ports.

## What's here

| Folder | What it is |
|---|---|
| `plugin/marquee/` | The **Marquee** plugin. It has all the show features and settings, and works with any theme (blocks and shortcodes). |
| `theme/ridgeroad/` | The **Marquee Festive** theme: garlands, bows, the night-sky scene and glowing show cards. Needs the plugin. (The folder is named `ridgeroad` after the show it was first built for. Keep that name so updates keep working.) |
| `plugin/marquee/core/fpp/` | `marquee_names.py` and the setup guide for Your Name in Lights on FPP. |

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

## License

GPLv2 or later. Bundled fonts are under the SIL Open Font License (see `theme/ridgeroad/assets/fonts`).
