#!/bin/sh

REPO="web4static"
SCRIPT="web4static.sh"
TMP_DIR="/tmp"
WEB4STATIC_DIR="/opt/share/www/w4s"

if ! opkg list-installed | grep -q "^curl"; then
  opkg update
  opkg install curl
fi

mkdir -p "$WEB4STATIC_DIR"
curl -L -s "https://raw.githubusercontent.com/spatiumstas/$REPO/legacy/$SCRIPT" --output $TMP_DIR/$SCRIPT
mv "$TMP_DIR/$SCRIPT" "$WEB4STATIC_DIR/$SCRIPT"
chmod +x $WEB4STATIC_DIR/$SCRIPT
cd /opt/bin
ln -sf $WEB4STATIC_DIR/$SCRIPT /opt/bin/web4static
URL=$(echo "aHR0cHM6Ly9sb2cuc3BhdGl1bS5rZWVuZXRpYy5wcm8=" | base64 -d)
JSON_DATA="{\"script_update\": \"web4static_install\"}"
curl -X POST -H "Content-Type: application/json" -d "$JSON_DATA" "$URL" -o /dev/null -s
$WEB4STATIC_DIR/$SCRIPT
