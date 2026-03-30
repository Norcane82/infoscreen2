#!/bin/bash
set -euo pipefail

URL_PLAYER="http://127.0.0.1/infoscreen2/index.php"
URL_FALLBACK="http://127.0.0.1/infoscreen2/fallback.php"
FLAG="/var/www/html/infoscreen2/cache/fallback_active.flag"
PROFILE_DIR="/home/pi/.config/chromium-infoscreen2"
LOG_FILE="/tmp/infoscreen2-kiosk.log"

export XDG_RUNTIME_DIR="${XDG_RUNTIME_DIR:-/run/user/1000}"
export WAYLAND_DISPLAY="${WAYLAND_DISPLAY:-wayland-0}"
export DISPLAY="${DISPLAY:-:0}"
export XAUTHORITY="${XAUTHORITY:-/home/pi/.Xauthority}"

if [ -f "$FLAG" ]; then
  TARGET_URL="$URL_FALLBACK"
  MODE="fallback"
else
  TARGET_URL="$URL_PLAYER"
  MODE="player"
fi

echo "[$(date '+%F %T')] infoscreen2-kiosk start mode=$MODE url=$TARGET_URL" >>"$LOG_FILE"

for _ in $(seq 1 20); do
  if [ -S "$XDG_RUNTIME_DIR/$WAYLAND_DISPLAY" ]; then
    break
  fi
  sleep 1
done

for _ in $(seq 1 20); do
  if curl -fsS --max-time 5 "$TARGET_URL" >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

pkill -f "chromium.*infoscreen2" >/dev/null 2>&1 || true
pkill -f "chromium-browser.*infoscreen2" >/dev/null 2>&1 || true
pkill -x unclutter >/dev/null 2>&1 || true
sleep 2

mkdir -p "$PROFILE_DIR"
rm -f "$PROFILE_DIR"/SingletonLock "$PROFILE_DIR"/SingletonCookie "$PROFILE_DIR"/SingletonSocket 2>/dev/null || true

CHROMIUM_BIN=""
if command -v chromium >/dev/null 2>&1; then
  CHROMIUM_BIN="$(command -v chromium)"
elif command -v chromium-browser >/dev/null 2>&1; then
  CHROMIUM_BIN="$(command -v chromium-browser)"
else
  echo "[$(date '+%F %T')] chromium binary not found" >>"$LOG_FILE"
  exit 1
fi

if command -v unclutter >/dev/null 2>&1; then
  nohup unclutter -idle 0.1 -root >/dev/null 2>&1 &
fi

nohup "$CHROMIUM_BIN" \
  --user-data-dir="$PROFILE_DIR" \
  --class=infoscreen2 \
  --kiosk \
  --no-first-run \
  --no-default-browser-check \
  --disable-session-crashed-bubble \
  --disable-infobars \
  --disable-pings \
  --disable-component-update \
  --disable-background-networking \
  --disable-sync \
  --disable-features=Translate,MediaRouter \
  --autoplay-policy=no-user-gesture-required \
  --check-for-update-interval=31536000 \
  --disable-dev-shm-usage \
  --ozone-platform=wayland \
  --enable-features=UseOzonePlatform \
  --hide-scrollbars \
  --force-renderer-accessibility \
  "$TARGET_URL" >>"$LOG_FILE" 2>&1 &
