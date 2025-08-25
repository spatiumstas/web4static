#!/bin/sh
printf "\033c"
set -e

animation() {
  local pid=$1 message=$2 spin='|/-\' i=0
  echo -n "[ ] $message..."
  while kill -0 $pid 2>/dev/null; do
    i=$(( (i+1) %4 ))
    printf "\r[%s] %s..." "${spin:$i:1}" "$message"
    usleep 100000
  done
  wait $pid
  if [ $? -eq 0 ]; then
    printf "\r[✔] %s\n" "$message"
  else
    printf "\r[✖] %s\n" "$message"
    return 1
  fi
}

run_with_animation() {
  local msg="$1"
  shift
  ( "$@" ) >/dev/null 2>&1 &
  animation $! "$msg"
}

run_with_animation "Устанавливаю репозиторий"
mkdir -p /opt/etc/opkg
echo "src/gz web4static https://spatiumstas.github.io/web4static/all" > /opt/etc/opkg/web4static.conf
run_with_animation "Начинаю установку"
echo ""
opkg update && opkg install web4static