#!/bin/sh
printf "\033c"
set -e
RED='\033[1;31m'
GREEN='\033[1;32m'
CYAN='\033[0;36m'
NC='\033[0m'
API_URL="https://api.github.com/repos/spatiumstas/web4static/releases/latest"

print_message() {
  local message="$1"
  local color="${2:-$NC}"
  local border=$(printf '%0.s-' $(seq 1 $((${#message} + 2))))
  printf "${color}\n+${border}+\n| ${message} |\n+${border}+\n${NC}\n"
}

RELEASE_JSON=$(curl -sL "$API_URL")
IPK_URL=$(echo "$RELEASE_JSON" | grep 'browser_download_url' | grep '\.ipk' | cut -d '"' -f4)

if [ -z "$IPK_URL" ]; then
  print_message "Не удалось найти .ipk файл" "$RED"
  exit 1
fi

IPK_FILE="/tmp/$(basename "$IPK_URL")"
print_message "Скачиваем $IPK_URL..." "$GREEN"
curl -L -o "$IPK_FILE" "$IPK_URL"
print_message "Устанавливаем $IPK_FILE ..." "$GREEN"
opkg install "$IPK_FILE"
rm -f "$IPK_FILE"