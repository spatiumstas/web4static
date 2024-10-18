#!/bin/sh

RED='\033[1;31m'
GREEN='\033[1;32m'
CYAN='\033[0;36m'
NC='\033[0m'
USER="spatiumstas"
REPO="web4static"

WEB4STATIC_DIR="/opt/share/www/ext-ui/addons/web4static"
PATH_INDEX="/opt/share/www/ext-ui/index.html"
PATH_WEB4STATIC="/opt/share/www/ext-ui/addons/web4static.php"
PATH_VPN_ICON="/opt/share/www/ext-ui/addons/web4static/main.png"
PATH_RUN4STATIC="/opt/share/www/ext-ui/addons/web4static/run4Static.php"

URL_EDITLIST="https://raw.githubusercontent.com/${USER}/${REPO}/main/files/web4static.php"
URL_VPN_ICON="https://raw.githubusercontent.com/${USER}/${REPO}/main/files/main.png"
URL_RUN="https://raw.githubusercontent.com/${USER}/${REPO}/main/files/run4Static.php"
URL_STYLES="https://raw.githubusercontent.com/${USER}/${REPO}/main/files/styles.css"
URL_SCRIPT="https://raw.githubusercontent.com/${USER}/${REPO}/main/files/script.js"
URL_ASCII="https://raw.githubusercontent.com/${USER}/${REPO}/main/files/ascii.txt"

print_menu() {
  printf "\033c"
  printf "${CYAN}"
  cat <<'EOF'
                __    __ __       __        __  _              ___ _____
 _      _____  / /_  / // / _____/ /_____ _/ /_(_)____   _   _<  /|__  /
| | /| / / _ \/ __ \/ // /_/ ___/ __/ __ `/ __/ / ___/  | | / / /  /_ <
| |/ |/ /  __/ /_/ /__  __(__  ) /_/ /_/ / /_/ / /__    | |/ / / ___/ /
|__/|__/\___/_.___/  /_/ /____/\__/\__,_/\__/_/\___/    |___/_(_)____/
EOF
  printf "${NC}"
  echo ""
  echo "1. Установить/Обновить web-интерфейс"
  echo "2. Удалить web-интерфейс"
  echo ""
  echo "99. Обновить скрипт"
  echo "00. Выход"
  echo ""
}

main_menu() {
  print_menu
  read -p "Выберите действие: " choice

  choice=$(echo "$choice" | tr -d '\032' | tr -d '[A-Z]')

  if [ -z "$choice" ]; then
    main_menu
  else
    case "$choice" in
    1) install_web ;;
    2) remove_web ;;
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
  local color=$2
  local len=${#message}
  local border=$(printf '%0.s-' $(seq 1 $((len + 2))))

  printf "${color}\n"
  echo -e "\n+${border}+"
  echo -e "| ${message} |"
  echo -e "+${border}+\n"
  printf "${NC}"
  sleep 1
}

packages_checker() {
  if ! opkg list-installed | grep -q "^ext-ui" || ! opkg list-installed | grep -q "^curl"; then
    printf "${RED}Пакеты ext-ui и/или curl не найдены, устанавливаем...${NC}\n"
    echo ""
    opkg update
    opkg install ext-ui curl
    echo ""
  fi
}

download_file() {
  local url="$1"
  local path="$2"
  local filename=$(basename "$path")
  echo "Скачиваем файл $filename..."

  if ! curl -s -f -o "$path" "$url"; then
    print_message "Ошибка при скачивании файла $filename. Возможно, файл не найден" "$RED"
    read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
    main_menu
  fi

  return 0
}

modify_index_file() {
  if [ -f "$PATH_INDEX" ]; then
    if ! grep -q '<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"/>' "$PATH_INDEX"; then
      sed -i '/<meta charset="utf-8" \/>/a <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"/>' "$PATH_INDEX"
    fi

    if ! grep -q '<a href="addons/web4static.php" target="myframe" title="web4static">' "$PATH_INDEX"; then
      sed -i '/<a href="addons\/info\/index.php" target="myframe" title="System Health and Information"><img src="addons\/img\/btn\/linfo.png"><\/a>/i <a href="addons/web4static.php" target="myframe" title="web4static"><img src="addons/web4static/main.png"></a>' "$PATH_INDEX"
    fi
  else
    echo "Файл $PATH_INDEX не найден."
  fi
}

install_web() {
  print_message "Начинаем установку Web-интерфейса..." "$GREEN"
  packages_checker

  mkdir -p "$WEB4STATIC_DIR"
  download_file "$URL_EDITLIST" "$PATH_WEB4STATIC"
  download_file "$URL_RUN" "$PATH_RUN4STATIC"
  download_file "$URL_ASCII" "$WEB4STATIC_DIR/ascii.txt"
  download_file "$URL_STYLES" "$WEB4STATIC_DIR/styles.css"
  download_file "$URL_SCRIPT" "$WEB4STATIC_DIR/script.js"

  modify_index_file

  download_file "$URL_VPN_ICON" "$PATH_VPN_ICON"

  echo ""
  read -p "Введите IP-адрес роутера (по умолчанию 192.168.1.1): " user_ip
  user_ip=${user_ip:-192.168.1.1}

  replace_path "$user_ip"

  echo "Файлы успешно пропатчены"

  print_message "Web-интерфейс установлен и доступен по адресу http://$user_ip:88/ext-ui" "$GREEN"
  read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
  main_menu
}

replace_path() {
  local new_ip="$1"

  replace_with_error_check() {
    local search="$1"
    local replace="$2"
    local file="$3"
    local description="$4"

    if grep -q "$search" "$file"; then
      sed -i "s|$search|$replace|g" "$file"
    else
      echo "Ошибка: строка '$description' не найдена в файле $file"
    fi
  }

  replace_with_error_check "http://192.168.1.1:88/ext-ui/addons/web4static.php" "http://$new_ip:88/ext-ui/addons/web4static.php" "$PATH_WEB4STATIC" "URL"

  replace_with_error_check "header('Location: http://192.168.1.1:88/ext-ui/addons/web4static.php');" "header('Location: http://$new_ip:88/ext-ui/addons/web4static.php');" "$PATH_RUN4STATIC" "header URL"
}

remove_web() {
  echo ""
  echo "Удаляю директорию $WEB4STATIC_DIR..."
  sleep 1
  rm -r $WEB4STATIC_DIR
  echo "Удаляю файл $PATH_WEB4STATIC..."
  sleep 1
  rm $PATH_WEB4STATIC

  print_message "Успешно удалёно, пакет ext-ui не затронут" "$GREEN"
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
    print_message "Скрипт успешно обновлён" "$GREEN"
    $OPT_DIR/$SCRIPT
  else
    print_message "Ошибка при скачивании скрипта" "$RED"
  fi
}

main_menu
