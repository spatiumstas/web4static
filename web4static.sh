#!/bin/sh

RED='\033[1;31m'
GREEN='\033[1;32m'
CYAN='\033[0;36m'
NC='\033[0m'
USER="spatiumstas"
REPO="web4static"

WEB4STATIC_DIR="/opt/share/www/ext-ui/addons/web4static"
PATH_INDEX="/opt/share/www/ext-ui/index.html"
PATH_EDITLIST="/opt/share/www/ext-ui/addons/web4static.php"
PATH_VPN_ICON="/opt/share/www/ext-ui/addons/web4static/main.png"
PATH_RUN4STATIC="/opt/share/www/ext-ui/addons/web4static/run4Static.php"
PATH_SCRIPT_RUN_BIRD="/opt/root/Bird4Static/scripts/add-bird4_routes.sh"
PATH_SCRIPT_RUN_IPSET="/opt/root/IPset4Static/scripts/update-ipset.sh"
PATH_SCRIPT_RUN_COMBO="/opt/root/Bird4Static/scripts/add-bird4_routes.sh; /opt/root/Bird4Static/IPset4Static/scripts/update-ipset.sh"

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
                __    __ __       __        __  _              ___ ___
 _      _____  / /_  / // / _____/ /_____ _/ /_(_)____   _   _<  /<  /
| | /| / / _ \/ __ \/ // /_/ ___/ __/ __ `/ __/ / ___/  | | / / / / /
| |/ |/ /  __/ /_/ /__  __(__  ) /_/ /_/ / /_/ / /__    | |/ / / / /
|__/|__/\___/_.___/  /_/ /____/\__/\__,_/\__/_/\___/    |___/_(_)_/

EOF
  echo ""
  echo "Куда установить web-интерфейс?"
  printf "${NC}"
  echo "1. IPset4Static"
  echo "2. Bird4Static"
  echo "3. Bird4Static + IPset4Static"
  echo ""
  echo "88. Удалить web-интерфейс"
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
    1) install_web "IPset4Static" ;;
    2) install_web "Bird4Static" ;;
    3) install_web "Combo4Static" ;;
    88) remove_web ;;
    99) script_update "main" ;;
    77) script_update "dev" ;;
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

  echo "Файл $filename скачан успешно."
  echo ""
  return 0
}

set_permissions() {
  local path="$1"
  chmod 777 "$path"
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
  local interface_type=$1
  print_message "Начинаем установку Web-интерфейса $interface_type..." "$GREEN"
  packages_checker

  if [ "$interface_type" == "IPset4Static" ]; then
    run4Static_path="$PATH_SCRIPT_RUN_IPSET"

  elif [ "$interface_type" == "Bird4Static" ]; then
    run4Static_path="$PATH_SCRIPT_RUN_BIRD"

  elif [ "$interface_type" == "Combo4Static" ]; then
    run4Static_path="$PATH_SCRIPT_RUN_COMBO"

  else
    echo "Неверный тип интерфейса."
    return
  fi

  mkdir -p "$WEB4STATIC_DIR"
  download_file "$URL_EDITLIST" "$PATH_EDITLIST"
  download_file "$URL_RUN" "$PATH_RUN4STATIC"
  download_file "$URL_ASCII" "$WEB4STATIC_DIR/ascii.txt"
  download_file "$URL_STYLES" "$WEB4STATIC_DIR/styles.css"
  download_file "$URL_SCRIPT" "$WEB4STATIC_DIR/script.js"
  set_permissions "$PATH_RUN4STATIC"

  modify_index_file

  download_file "$URL_VPN_ICON" "$PATH_VPN_ICON"

  echo ""
  read -p "Введите IP-адрес роутера (по умолчанию 192.168.1.1): " user_ip
  user_ip=${user_ip:-192.168.1.1}

  replace_path "$user_ip" "$run4Static_path"

  echo "Файлы успешно пропатчены"

  print_message "Web-интерфейс установлен и доступен по адресу http://$user_ip:88/ext-ui" "$GREEN"
  read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
  main_menu
}

replace_path() {
  local new_ip="$1"
  local run4Static_path="$2"

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

  replace_with_error_check "http://192.168.1.1:88/ext-ui/addons/web4static.php" "http://$new_ip:88/ext-ui/addons/web4static.php" "$PATH_EDITLIST" "URL"

  replace_with_error_check 'shell_exec("/opt/root/Bird4Static/scripts/add-bird4_routes.sh");' "shell_exec(\"$run4Static_path\");" "$PATH_RUN4STATIC" "shell_exec Bird4Static"
  replace_with_error_check "header('Location: http://192.168.1.1:88/ext-ui/addons/web4static.php');" "header('Location: http://$new_ip:88/ext-ui/addons/web4static.php');" "$PATH_RUN4STATIC" "header URL"
}


remove_web() {
  echo ""
  echo "Удаляю директорию $WEB4STATIC_DIR..."
  sleep 1
  rm -r $WEB4STATIC_DIR
  echo "Удаляю файл $PATH_EDITLIST..."
  sleep 1
  rm $PATH_EDITLIST

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