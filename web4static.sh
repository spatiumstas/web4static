#!/bin/sh

RED='\033[1;31m'
GREEN='\033[1;32m'
CYAN='\033[0;36m'
NC='\033[0m'
USER="spatiumstas"
REPO="web4static"
MAIN_NAME="web4static.php"

WEB4STATIC_FOLDER="w4s"
WEB4STATIC_DIR="/opt/share/www/w4s"
PATH_WEB4STATIC="/opt/share/www/w4s/web4static.php"
PATH_RUN4STATIC="/opt/share/www/w4s/files/run4Static.php"
PATH_CONFIG="/opt/share/www/w4s/files/config.ini"

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
    1) install_web "${branch:-master}" ;;
    2) remove_web ;;
    77) packages_delete ;;
    88) script_update "dev" ;;
    99) script_update "main" ;;
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

packages_checker() {
  check_keenetic_repo
  if ! opkg list-installed | grep -q "^php8-cgi" || ! opkg list-installed | grep -q "^curl" || ! opkg list-installed | grep -q "^uhttpd_kn"; then
    opkg update
    opkg install php8-cgi uhttpd_kn curl
    wait
    echo ""
  fi
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
        echo "src/gz keendev http://bin.entware.net/mipssf-k3.4/keenetic" > /opt/etc/opkg/w4s-keenetic.conf
        ;;
      "mipsel")
        echo "src/gz keendev http://bin.entware.net/mipselsf-k3.4/keenetic" > /opt/etc/opkg/w4s-keenetic.conf
        ;;
      "aarch64")
        echo "src/gz keendev http://bin.entware.net/aarch64-k3.10/keenetic" > /opt/etc/opkg/w4s-keenetic.conf
        ;;
      *)
        printf "${RED}Неподдерживаемая архитектура: $arch${NC}\n"
        echo ""
        read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
        main_menu
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

  read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
  main_menu
}

download_file() {
  local url="$1"
  local path="$2"
  local filename=$(basename "$path")
  echo "Скачиваю файл $filename..."

  if ! curl -s -f -o "$path" "$url"; then
    print_message "Ошибка при скачивании файла $filename. Возможно, файл не найден" "$RED"
    read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
    main_menu
  fi
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
  URL_EDITLIST="https://raw.githubusercontent.com/${USER}/${REPO}/${BRANCH}/files/web4static.php"
  URL_VPN_ICON="https://raw.githubusercontent.com/${USER}/${REPO}/${BRANCH}/files/icons.svg"
  URL_RUN="https://raw.githubusercontent.com/${USER}/${REPO}/${BRANCH}/files/run4Static.php"
  URL_STYLES="https://raw.githubusercontent.com/${USER}/${REPO}/${BRANCH}/files/styles.css"
  URL_SCRIPT="https://raw.githubusercontent.com/${USER}/${REPO}/${BRANCH}/files/script.js"
  URL_ASCII="https://raw.githubusercontent.com/${USER}/${REPO}/${BRANCH}/files/ascii.txt"
  URL_CONFIG="https://raw.githubusercontent.com/${USER}/${REPO}/${BRANCH}/files/config.ini"

  download_file "$URL_EDITLIST" "$PATH_WEB4STATIC"
  download_file "$URL_RUN" "$PATH_RUN4STATIC"
  download_file "$URL_ASCII" "$WEB4STATIC_DIR/files/ascii.txt"
  download_file "$URL_STYLES" "$WEB4STATIC_DIR/files/styles.css"
  download_file "$URL_SCRIPT" "$WEB4STATIC_DIR/files/script.js"
  download_file "$URL_CONFIG" "$WEB4STATIC_DIR/files/config.ini"
  download_file "$URL_VPN_ICON" "$WEB4STATIC_DIR/files/icons.svg"

  echo ""
  user_ip=$(ip -f inet addr show dev br0 2>/dev/null | grep inet | sed -n 's/.*inet \([0-9.]\+\).*/\1/p')

  replace_path "$user_ip"
  echo ""
  print_message "Web-интерфейс установлен и доступен по адресу http://$user_ip:88/w4s" "$GREEN"
  read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
  main_menu
}

replace_path() {
  local new_ip="$1"
  local config_file="/files/config.ini"

  update_config() {
    local key="$1"
    local value="$2"
    local file="$3"

    if grep -q "^$key" "$file"; then
      sed -i "s|^$key.*|$key = \"$value\"|" "$file"
    else
      echo "$key = \"$value\"" >> "$file"
    fi
  }

  update_config "base_url" "http://$new_ip:88" "$PATH_CONFIG"

  if grep -q '^ARGS=' "/opt/etc/init.d/S80uhttpd"; then
    if ! grep -q ' -I web4static.php' "/opt/etc/init.d/S80uhttpd"; then
      sed -i 's|^\(ARGS=.*\)"|\1 -I web4static.php"|' "/opt/etc/init.d/S80uhttpd"
      /opt/etc/init.d/S80uhttpd restart
    fi
  else
    print_message "Ошибка: строка 'ARGS=' не найдена в файле /opt/etc/init.d/S80uhttpd" "$RED"
    read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
    main_menu
  fi
}

remove_web() {
  echo ""
  echo "Удаляю директорию $WEB4STATIC_DIR..."
  sleep 1
  rm -r "$WEB4STATIC_DIR"

  if grep -q '^ARGS=' "/opt/etc/init.d/S80uhttpd"; then
    sed -i 's| -I web4static.php||' "/opt/etc/init.d/S80uhttpd"
  fi

  print_message "Успешно удалено" "$GREEN"
  read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
  main_menu
}

script_update() {
  BRANCH="$1"
  SCRIPT="web4static.sh"
  TMP_DIR="/tmp"
  OPT_DIR="/opt"

  curl -L -s "https://raw.githubusercontent.com/$USER/$REPO/$BRANCH/$SCRIPT" --output $TMP_DIR/$SCRIPT

  if [ -f "$TMP_DIR/$SCRIPT" ]; then
    mv "$TMP_DIR/$SCRIPT" "$OPT_DIR/$SCRIPT"
    chmod +x $OPT_DIR/$SCRIPT
    cd $OPT_DIR/bin
    ln -sf $OPT_DIR/$SCRIPT $OPT_DIR/bin/web4static
    if [ "$BRANCH" = "dev" ]; then
      print_message "Скрипт успешно обновлён на $BRANCH ветку..." "$GREEN"
    else
      print_message "Скрипт успешно обновлён" "$GREEN"
    fi
    sleep 2
    $OPT_DIR/$SCRIPT
  else
    print_message "Ошибка при скачивании скрипта" "$RED"
  fi
}

if [ "$1" = "script_update" ]; then
  script_update
else
  main_menu
fi
