#!/bin/sh

RED='\033[1;31m'
GREEN='\033[1;32m'
CYAN='\033[0;36m'
NC='\033[0m'
USER="spatiumstas"
REPO="web4static"
WEB4STATIC_DIR="/opt/share/www/w4s"
PHP_FILE="$WEB4STATIC_DIR/web4static.php"
UHTTPD_CONF="/opt/etc/uhttpd.conf"
PHP_INI="/opt/etc/php.ini"

print_menu() {
  printf "\033c"
  printf "${CYAN}"
  cat <<'EOF'
                __    __ __       __        __  _
 _      _____  / /_  / // / _____/ /_____ _/ /_(_)____
| | /| / / _ \/ __ \/ // /_/ ___/ __/ __ `/ __/ / ___/
| |/ |/ /  __/ /_/ /__  __(__  ) /_/ /_/ / /_/ / /__
|__/|__/\___/_.___/  /_/ /____/\__/\__,_/\__/_/\___/
EOF
  printf "${NC}"
  echo ""
  echo "1. Установить/Обновить web-интерфейс"
  echo "2. Удалить web-интерфейс"
  echo ""
  echo "77. Удалить используемые пакеты"
  echo "99. Обновить скрипт"
  echo "00. Выход"
  echo ""
}

main_menu() {
  print_menu
  read -p "Выберите действие: " choice branch
  echo ""
  choice=$(echo "$choice" | tr -d '\032' | tr -d '[A-Z]')

  if [ -z "$choice" ]; then
    main_menu
  else
    case "$choice" in
    1) install_web "${branch:-legacy}" ;;
    2) remove_web ;;
    77) packages_delete ;;
    99) script_update "legacy" ;;
    00) exit ;;
    *)
      echo "Неверный выбор. Попробуйте снова."
      sleep 1
      main_menu
      ;;
    esac
  fi
}

print_message() {
  local message=$1
  local color=${2:-$NC}
  local border=$(printf '%0.s-' $(seq 1 $((${#message} + 2))))
  printf "${color}\n+${border}+\n| ${message} |\n+${border}+\n${NC}\n"
  sleep 1
}

get_user_ip() {
  ip -f inet addr show dev br0 2>/dev/null | grep inet | sed -n 's/.*inet \([0-9.]\+\).*/\1/p'
}

packages_checker() {
  check_keenetic_repo
  if ! opkg list-installed | grep -q "^php8-cgi" || ! opkg list-installed | grep -q "^curl" || ! opkg list-installed | grep -q "^uhttpd_kn"; then
    opkg update
    opkg install php8-cgi uhttpd_kn curl
    wait
    echo ""
  fi
}

exit_function() {
  echo ""
  read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
  main_menu
}

get_architecture() {
  arch=$(opkg print-architecture | grep -oE 'mips-3|mipsel-3|aarch64-3' | head -n 1)

  case "$arch" in
  "mips-3") echo "mips" ;;
  "mipsel-3") echo "mipsel" ;;
  "aarch64-3") echo "aarch64" ;;
  *) echo "unknown_arch" ;;
  esac
}

check_keenetic_repo() {
  if [ ! -f /opt/var/opkg-lists/keendev ]; then
    print_message "Не найден репозиторий Keenetic, добавляю..." "$CYAN"
    arch=$(get_architecture)
    printf "${GREEN}Архитектура устройства - $arch${NC}\n"
    echo ""
    mkdir -p /opt/etc/opkg
    case "$arch" in
    "mips")
      echo "src/gz keendev http://bin.entware.net/mipssf-k3.4/keenetic" >/opt/etc/opkg/w4s-keenetic.conf
      ;;
    "mipsel")
      echo "src/gz keendev http://bin.entware.net/mipselsf-k3.4/keenetic" >/opt/etc/opkg/w4s-keenetic.conf
      ;;
    "aarch64")
      echo "src/gz keendev http://bin.entware.net/aarch64-k3.10/keenetic" >/opt/etc/opkg/w4s-keenetic.conf
      ;;
    *)
      printf "${RED}Неподдерживаемая архитектура: $arch${NC}\n"
      echo ""
      exit_function
      ;;
    esac
  fi
}

packages_delete() {
  packages="php8 php8-cgi uhttpd_kn"
  delete_log=$(opkg remove $packages --autoremove 2>&1)
  removed_packages=""
  failed_packages=""

  for package in $packages; do
    if echo "$delete_log" | grep -q "Package $package is depended upon by packages"; then
      failed_packages="$failed_packages $package"
    else
      removed_packages="$removed_packages $package"
    fi
  done

  if [ -n "$removed_packages" ]; then
    print_message "Пакеты$removed_packages успешно удалены" "$GREEN"
  fi

  if [ -n "$failed_packages" ]; then
    print_message "Пакет$failed_packages не были удалены из-за зависимостей" "$RED"
  fi

  exit_function
}

install_web() {
  BRANCH="$1"
  if [ "$BRANCH" = "dev" ]; then
    print_message "Устанавливаем Web-интерфейс из ветки $BRANCH..." "$GREEN"
  else
    print_message "Устанавливаем Web-интерфейс..." "$GREEN"
  fi
  packages_checker
  mkdir -p "$WEB4STATIC_DIR/files"

  API_URL="https://api.github.com/repos/${USER}/${REPO}/contents/files?ref=${BRANCH}"
  printf "Получаем список файлов из репозитория...\n\n"

  files_list=$(curl -s --connect-timeout 5 --max-time 10 \
    -H "Accept: application/vnd.github.v3+json" \
    -H "User-Agent: web4static-updater" "$API_URL")

  if [ $? -ne 0 ] || [ -z "$files_list" ]; then
    print_message "Ошибка: не удалось подключиться к GitHub API." "$RED"
    exit_function
  fi

  error_message=$(echo "$files_list" | grep -Po '"message":.*?[^\\]",' | awk -F'"' '{print $4}')
  if [ -n "$error_message" ]; then
    print_message "Ошибка при получении списка файлов с GitHub" "$RED"
    print_message "$error_message" "$RED"
    exit_function
  fi

  echo "$files_list" | grep -o '"download_url":"[^"]*"' | sed 's/"download_url":"//' | sed 's/"//' | while read -r url; do
    filename=$(basename "$url")
    if [ "$filename" = "web4static.php" ]; then
      download_file "$url" "$WEB4STATIC_DIR/web4static.php"
    else
      download_file "$url" "$WEB4STATIC_DIR/files/$filename"
    fi
  done

  user_ip=$(get_user_ip)
  replace_path "$user_ip"
  file_count=$(find "$WEB4STATIC_DIR/files" -type f 2>/dev/null | wc -l)

  if [ "$file_count" -ge 3 ] && [ -f "$WEB4STATIC_DIR/web4static.php" ]; then
    echo ""
    read -p "Открывать Web-интерфейс напрямую по порту? (y/n): " choice
    case "$choice" in
    [yY]*) set_static true ;;
    *) set_static false ;;
    esac
  else
    print_message "Ошибка: не все файлы были установлены." "$RED"
  fi

  exit_function
}

download_file() {
  local url="$1"
  local path="$2"
  local filename=$(basename "$path")
  echo "Скачиваю $filename..."
  curl -s -L "$url" -o "$path" 2>/dev/null
  if [ $? -ne 0 ] || [ ! -f "$path" ]; then
    print_message "Ошибка при скачивании $filename" "$RED"
    exit_function
  fi
}

replace_path() {
  if grep -q '^ARGS=' "/opt/etc/init.d/S80uhttpd"; then
    if ! grep -q ' -I web4static.php' "/opt/etc/init.d/S80uhttpd"; then
      sed -i 's|^\(ARGS=.*\)"|\1 -I web4static.php"|' "/opt/etc/init.d/S80uhttpd"
      echo ""
    fi
  else
    print_message "Ошибка: строка 'ARGS=' не найдена в файле /opt/etc/init.d/S80uhttpd" "$RED"
    exit_function
  fi
}

remove_web() {
  echo ""
  echo "Удаляю директорию $WEB4STATIC_DIR..."
  sleep 1
  rm -r "$WEB4STATIC_DIR"
  rm /opt/bin/web4static
  if grep -q '^ARGS=' "/opt/etc/init.d/S80uhttpd"; then
    sed -i 's| -I web4static.php||' "/opt/etc/init.d/S80uhttpd"
  fi
  set_static false "" "delete"
  print_message "Успешно удалено" "$GREEN"
  exit_function
}

script_update() {
  BRANCH="$1"
  SCRIPT="web4static.sh"
  TMP_DIR="/tmp"

  curl -L -s "https://raw.githubusercontent.com/$USER/$REPO/$BRANCH/$SCRIPT" --output $TMP_DIR/$SCRIPT

  if [ -f "$TMP_DIR/$SCRIPT" ]; then
    mv "$TMP_DIR/$SCRIPT" "$WEB4STATIC_DIR/$SCRIPT"
    chmod +x $WEB4STATIC_DIR/$SCRIPT
    cd /opt/bin
    ln -sf $WEB4STATIC_DIR/$SCRIPT /opt/bin/web4static
    if [ "$BRANCH" = "dev" ]; then
      print_message "Скрипт успешно обновлён на $BRANCH ветку..." "$GREEN"
    else
      print_message "Скрипт успешно обновлён" "$GREEN"
    fi
    sleep 1
    $WEB4STATIC_DIR/$SCRIPT post_update
  else
    print_message "Ошибка при скачивании скрипта" "$RED"
    exit_function
  fi
}

post_update() {
  SCRIPT_VERSION=$(awk -F"['\"]" '/\$w4s_version/{print $2}' "$PHP_FILE")
  URL=$(echo "aHR0cHM6Ly9sb2cuc3BhdGl1bS5rZWVuZXRpYy5wcm8=" | base64 -d)
  JSON_DATA="{\"script_update\": \"w4s_update_$SCRIPT_VERSION\"}"
  curl -X POST -H "Content-Type: application/json" -d "$JSON_DATA" "$URL" -o /dev/null -s
  main_menu
}

set_static() {
  local user_ip=$(get_user_ip)
  local action="$3"
  if [ "$1" = "true" ]; then
    sed -i "s|^DOCROOT=.*|DOCROOT=\"$WEB4STATIC_DIR\"|" "$UHTTPD_CONF"
    sed -i 's|^doc_root = "/opt/share/www"|# doc_root = "/opt/share/www"|' "$PHP_INI"
    print_message "Web-интерфейс установлен и доступен по адресу http://$user_ip:88" "$GREEN"
  else
    sed -i 's|^DOCROOT=.*|DOCROOT="/opt/share/www"|' "$UHTTPD_CONF"
    sed -i 's|^# doc_root = "/opt/share/www"|doc_root = "/opt/share/www"|' "$PHP_INI"
    if [ ! "$action" = "delete" ]; then
      print_message "Web-интерфейс установлен и доступен по адресу http://$user_ip:88/w4s" "$GREEN"
    fi
  fi
  /opt/etc/init.d/S80uhttpd restart
}

if [ "$1" = "script_update" ]; then
  script_update "legacy"
elif [ "$1" = "post_update" ]; then
  post_update
else
  main_menu
fi
