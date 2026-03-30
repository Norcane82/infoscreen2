#!/bin/bash
set -euo pipefail

SERVICE_NAME="infoscreen2-kiosk.service"
SCRIPT="/usr/local/bin/infoscreen2-kiosk.sh"

pkill -f "chromium.*infoscreen2" >/dev/null 2>&1 || true
pkill -f "chromium-browser.*infoscreen2" >/dev/null 2>&1 || true
pkill -x unclutter >/dev/null 2>&1 || true
sleep 2

if systemctl list-unit-files | grep -q "^${SERVICE_NAME}"; then
  systemctl restart "$SERVICE_NAME"
  exit 0
fi

nohup "$SCRIPT" >/tmp/infoscreen2-kiosk.log 2>&1 &
