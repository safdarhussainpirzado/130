#!/usr/bin/env bash
# Weekly refresh of the self-hosted Pakistan basemap (OpenStreetMap data, Protomaps daily build).
# Runs from root's crontab on the production host. Never leaves a half-written file in place and keeps the
# previous map if anything fails. Data (c) OpenStreetMap contributors, ODbL; basemap tiles by Protomaps.
set -euo pipefail

PM="${PMTILES:-/root/bin/pmtiles}"
OUT_DIR="${OUT_DIR:-/var/lib/nhmp-geo}"
OUT="$OUT_DIR/pakistan.pmtiles"
TMP="$OUT_DIR/.pakistan.pmtiles.part"
BBOX="${BBOX:-60.8,23.5,77.9,37.2}"     # Pakistan incl. Kashmir/Gilgit-Baltistan margin
MAXZ="${MAXZ:-14}"                       # deeper zoom is overzoomed in the browser; keeps the file small
MIN_BYTES="${MIN_BYTES:-50000000}"

trap 'rm -f "$TMP"' EXIT
URL=""
for d in 0 1 2 3 4 5 6; do
  U="https://build.protomaps.com/$(date -u -d "-$d day" +%Y%m%d).pmtiles"
  if curl -sfI --max-time 30 "$U" >/dev/null; then URL="$U"; break; fi
done
[ -n "$URL" ] || { echo "no recent Protomaps build reachable"; exit 1; }

echo "$(date -u +%FT%TZ) extracting $URL bbox=$BBOX maxzoom=$MAXZ"
nice -n 15 ionice -c3 "$PM" extract "$URL" "$TMP" --bbox="$BBOX" --maxzoom="$MAXZ"
"$PM" verify "$TMP"
SIZE=$(stat -c %s "$TMP")
[ "$SIZE" -ge "$MIN_BYTES" ] || { echo "extract too small ($SIZE bytes), keeping previous map"; exit 1; }
chmod 644 "$TMP"
mv -f "$TMP" "$OUT"
echo "$(date -u +%FT%TZ) installed $OUT ($SIZE bytes)"
