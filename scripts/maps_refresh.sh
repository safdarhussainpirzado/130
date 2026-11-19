#!/usr/bin/env bash
# Weekly refresh of the self-hosted Pakistan basemap (OpenStreetMap data, Protomaps daily build).
# Runs from root's crontab on the production host. Never leaves a half-written file in place and keeps the
# previous map if anything fails. Data (c) OpenStreetMap contributors, ODbL; basemap tiles by Protomaps.
set -euo pipefail

PM="${PMTILES:-/root/bin/pmtiles}"
RUNNER_IMAGE="${RUNNER_IMAGE:-nginx:alpine}"   # any local image with /bin/sh; the tool is mounted in read-only
OUT_DIR="${OUT_DIR:-/var/lib/nhmp-geo}"
OUT="$OUT_DIR/pakistan.pmtiles"
TMP="$OUT_DIR/.pakistan.pmtiles.part"
BBOX="${BBOX:-60.8,23.5,77.9,37.2}"     # Pakistan incl. Kashmir/Gilgit-Baltistan margin
MAXZ="${MAXZ:-13}"                       # deeper zoom is overzoomed in the browser. KEEP THE FILE UNDER 600 MB: the network gateway in front of
                                         # the site returns HTTP 500 for any range request starting beyond byte 600,000,000.
MIN_BYTES="${MIN_BYTES:-50000000}"

trap 'rm -f "$TMP"' EXIT
URL=""
for d in 0 1 2 3 4 5 6; do
  U="https://build.protomaps.com/$(date -u -d "-$d day" +%Y%m%d).pmtiles"
  if curl -sfI --max-time 30 "$U" >/dev/null; then URL="$U"; break; fi
done
[ -n "$URL" ] || { echo "no recent Protomaps build reachable"; exit 1; }

echo "$(date -u +%FT%TZ) extracting $URL bbox=$BBOX maxzoom=$MAXZ"
# The downloaded tool runs sandboxed: no extra privileges, read-only root fs, only the output dir writable.
pm() {
  docker run --rm --read-only --cap-drop ALL --security-opt no-new-privileges --user 82:82 \
    -v "$PM":/pm:ro -v "$OUT_DIR":/out --entrypoint /pm "$RUNNER_IMAGE" "$@"
}
nice -n 15 ionice -c3 docker run --rm --read-only --cap-drop ALL --security-opt no-new-privileges --user 82:82 \
  -v "$PM":/pm:ro -v "$OUT_DIR":/out --entrypoint /pm "$RUNNER_IMAGE" \
  extract "$URL" /out/.pakistan.pmtiles.part --bbox="$BBOX" --maxzoom="$MAXZ"
pm verify /out/.pakistan.pmtiles.part
SIZE=$(stat -c %s "$TMP")
[ "$SIZE" -ge "$MIN_BYTES" ] || { echo "extract too small ($SIZE bytes), keeping previous map"; exit 1; }
chmod 644 "$TMP"
mv -f "$TMP" "$OUT"
echo "$(date -u +%FT%TZ) installed $OUT ($SIZE bytes)"
