#!/bin/sh
printf "\033c"
set -e

echo "Устанавливаю репозиторий"
mkdir -p /opt/etc/opkg
echo "src/gz web4static https://spatiumstas.github.io/web4static/all" > /opt/etc/opkg/web4static.conf
echo "Начинаю установку"
echo ""
opkg update && opkg install web4static