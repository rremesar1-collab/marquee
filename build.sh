#!/usr/bin/env bash
# Builds the release zips into dist/:
#   marquee-<version>.zip          (plugin, unzips to marquee/)
#   marquee-festive-<version>.zip  (theme, unzips to ridgeroad/)
# The file names matter: the plugin's update checker looks for these on each GitHub release.
set -euo pipefail
cd "$(dirname "$0")"
pv=$(sed -n "s/^define( 'MARQUEE_VERSION', '\(.*\)' );/\1/p" plugin/marquee/marquee.php)
tv=$(sed -n 's/^Version: //p' theme/ridgeroad/style.css | head -1)
test -f theme/ridgeroad/.marquee-dist || { echo "theme/ridgeroad/.marquee-dist is missing"; exit 1; }
rm -rf dist && mkdir dist
(cd plugin && zip -qrX "../dist/marquee-$pv.zip" marquee -x '*.DS_Store')
(cd theme && zip -qrX "../dist/marquee-festive-$tv.zip" ridgeroad -x '*.DS_Store')
echo "Built dist/marquee-$pv.zip and dist/marquee-festive-$tv.zip"
