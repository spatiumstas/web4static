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
PATH_VPN_ICON="/opt/share/www/w4s/files/main.png"
PATH_RUN4STATIC="/opt/share/www/w4s/files/run4Static.php"

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
  if ! opkg list-installed | grep -q "^php8-cgi" || ! opkg list-installed | grep -q "^curl" || ! opkg list-installed | grep -q "^uhttpd_kn"; then
    printf "${RED}Необходимые пакеты не найдены, устанавливаем...${NC}\n"
    echo ""
    opkg update
    opkg install php8-cgi uhttpd_kn curl
    /opt/etc/init.d/S80uhttpd restart
    echo ""
  fi
}

packages_delete() {
  opkg remove php8-cgi uhttpd_kn curl --force-depends
  wait
  print_message "Пакеты php8-cgi, uhttpd_kn и curl успешно удалены" "$GREEN"
  read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
  main_menu
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
  URL_VPN_ICON="https://raw.githubusercontent.com/${USER}/${REPO}/${BRANCH}/files/main.png"
  URL_RUN="https://raw.githubusercontent.com/${USER}/${REPO}/${BRANCH}/files/run4Static.php"
  URL_STYLES="https://raw.githubusercontent.com/${USER}/${REPO}/${BRANCH}/files/styles.css"
  URL_SCRIPT="https://raw.githubusercontent.com/${USER}/${REPO}/${BRANCH}/files/script.js"
  URL_ASCII="https://raw.githubusercontent.com/${USER}/${REPO}/${BRANCH}/files/ascii.txt"

  download_file "$URL_EDITLIST" "$PATH_WEB4STATIC"
  download_file "$URL_RUN" "$PATH_RUN4STATIC"
  download_file "$URL_ASCII" "$WEB4STATIC_DIR/files/ascii.txt"
  download_file "$URL_STYLES" "$WEB4STATIC_DIR/files/styles.css"
  download_file "$URL_SCRIPT" "$WEB4STATIC_DIR/files/script.js"

  download_file "$URL_VPN_ICON" "$PATH_VPN_ICON"

  echo ""
  read -p "Введите IP-адрес роутера (по умолчанию 192.168.1.1): " user_ip
  user_ip=${user_ip:-192.168.1.1}

  replace_path "$user_ip"
  echo ""
  /opt/etc/init.d/S80uhttpd restart
  print_message "Web-интерфейс установлен и доступен по адресу http://$user_ip:88/w4s" "$GREEN"
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

  replace_with_error_check "http://192.168.1.1:88/${WEB4STATIC_FOLDER}/${MAIN_NAME}" "http://$new_ip:88/${WEB4STATIC_FOLDER}/${MAIN_NAME}" "$PATH_WEB4STATIC" "URL"

  replace_with_error_check "header('Location: http://192.168.1.1:88/${WEB4STATIC_FOLDER}/${MAIN_NAME}');" "header('Location: http://$new_ip:88/${WEB4STATIC_FOLDER}/${MAIN_NAME}');" "$PATH_RUN4STATIC" "header URL"

  if grep -q '^ARGS=' "/opt/etc/init.d/S80uhttpd"; then
    if ! grep -q ' -I web4static.php' "/opt/etc/init.d/S80uhttpd"; then
      sed -i 's|^\(ARGS=.*\)"|\1 -I web4static.php"|' "/opt/etc/init.d/S80uhttpd"
    fi
  else
    echo "Ошибка: строка 'ARGS=' не найдена в файле /opt/etc/init.d/S80uhttpd"
  fi
}

remove_web() {
  echo ""
  echo "Удаляю директорию $WEB4STATIC_DIR..."
  sleep 1
  rm -r $WEB4STATIC_DIR

  print_message "Успешно удалёно" "$GREEN"
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
    $OPT_DIR/$SCRIPT
  else
    print_message "Ошибка при скачивании скрипта" "$RED"
  fi
}

main_menu
