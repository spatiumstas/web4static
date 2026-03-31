#!/bin/sh
printf "\033c"
set -e

printf "\nУстанавливаю репозиторий\n\n"
curl -fsSL https://raw.githubusercontent.com/spatiumstas/feedly/main/add-repo.sh | sh
printf "\n\nНачинаю установку\n\n"
opkg update && opkg install web4static