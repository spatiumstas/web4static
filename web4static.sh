#!/bin/sh

RED='\033[1;31m'
GREEN='\033[1;32m'
CYAN='\033[0;36m'
NC='\033[0m'
USER="spatiumstas"
REPO="web4static"
PATH_INDEX="/opt/share/www/ext-ui/index.html"
PATH_EDITLIST="/opt/share/www/ext-ui/addons/editlist.php"
URL_VPN_ICON="https://raw.githubusercontent.com/${USER}/${REPO}/main/files/web4static.png"
PATH_VPN_ICON="/opt/share/www/ext-ui/addons/img/btn/web4static.png"

# IPset4Static
URL_EDITLIST_IPSET="https://raw.githubusercontent.com/${USER}/${REPO}/main/files/IPsetEditList.php"
URL_RUN_IPSET="https://raw.githubusercontent.com/${USER}/${REPO}/main/files/runIPset4Static.php"
PATH_RUN4STATIC_IPSET="/opt/share/www/ext-ui/addons/runIPset4Static.php"
PATH_LIST_IPSET="IPset4Static"

# Bird4Static
URL_EDITLIST_BIRD="https://raw.githubusercontent.com/${USER}/${REPO}/main/files/BirdEditList.php"
URL_RUN_BIRD="https://raw.githubusercontent.com/${USER}/${REPO}/main/files/runBird4Static.php"
PATH_RUN4STATIC_BIRD="/opt/share/www/ext-ui/addons/runBird4Static.php"
PATH_LIST_BIRD="Bird4Static"

# combo4Static
URL_EDITLIST_COMBO="https://raw.githubusercontent.com/${USER}/${REPO}/main/files/ComboEditList.php"
URL_RUN_COMBO="https://raw.githubusercontent.com/${USER}/${REPO}/main/files/runCombo4Static.php"
PATH_RUN4STATIC_COMBO="/opt/share/www/ext-ui/addons/runCombo4Static.php"
PATH_LIST_COMBO="Bird4Static/IPset4Static"

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
  echo "1. Установить/Обновить Web-интерфейс IPset4Static"
  echo "2. Установить/Обновить Web-интерфейс Bird4Static"
  echo "3. Установить/Обновить Web-интерфейс Bird4Static+IPset4Static"
  echo "4. Удалить Web-интерфейс"
  echo ""
  echo "00. Выход"
  echo "99. Обновить скрипт"
  echo ""
}

main_menu() {
    print_menu
    read -p "Выберите действие: " choice

    if [ -z "$choice" ]; then
        main_menu
    else
        choice=$(echo "$choice" | tr -d ' \n\r')

        case "$choice" in
            1) install_web "IPset4Static" ;;
            2) install_web "Bird4Static" ;;
            3) install_web "Combo4Static" ;;
            4) remove_web ;;
            99) script_update "main" ;;
            88) script_update "dev" ;;
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

check_internet() {
    if ! curl -s --head --request GET http://www.google.com | grep "200 OK" > /dev/null; then
        print_message "Ошибка: нет доступа к интернету" "$RED"
        read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
        main_menu
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

        if ! grep -q '<a href="addons/editlist.php" target="myframe" title="Edit vpn list">' "$PATH_INDEX"; then
            sed -i '/<a href="addons\/info\/index.php" target="myframe" title="System Health and Information"><img src="addons\/img\/btn\/linfo.png"><\/a>/i <a href="addons/editlist.php" target="myframe" title="Edit vpn list"><img src="addons/img/btn/web4static.png"></a>' "$PATH_INDEX"
        fi
    else
        echo "Файл $PATH_INDEX не найден."
    fi
}

install_web() {
    local interface_type=$1
    print_message "Начинаем установку Web-интерфейса $interface_type..." "$GREEN"
    packages_checker
    check_internet

    if [ "$interface_type" == "IPset4Static" ]; then
        URL_EDITLIST="$URL_EDITLIST_IPSET"
        URL_RUN="$URL_RUN_IPSET"
        PATH_RUN4STATIC="$PATH_RUN4STATIC_IPSET"
        TOUCH_LIST="$PATH_LIST_IPSET"
        FILE_VPN1="user-ipset-vpn1.list"
        FILE_VPN2="user-ipset-vpn2.list"
    elif [ "$interface_type" == "Bird4Static" ]; then
        URL_EDITLIST="$URL_EDITLIST_BIRD"
        URL_RUN="$URL_RUN_BIRD"
        PATH_RUN4STATIC="$PATH_RUN4STATIC_BIRD"
        TOUCH_LIST="$PATH_LIST_BIRD"
        FILE_VPN1="user-vpn1.list"
        FILE_VPN2="user-vpn2.list"
    elif [ "$interface_type" == "Combo4Static" ]; then
        URL_EDITLIST="$URL_EDITLIST_COMBO"
        URL_RUN="$URL_RUN_COMBO"
        PATH_RUN4STATIC="$PATH_RUN4STATIC_COMBO"
        TOUCH_LIST="$PATH_LIST_COMBO"
        FILE_VPN1="user-ipset-vpn1.list"
        FILE_VPN2="user-ipset-vpn2.list"
    else
        echo "Неверный тип интерфейса."
        return
    fi

    download_file "$URL_EDITLIST" "$PATH_EDITLIST"
    download_file "$URL_RUN" "$PATH_RUN4STATIC"
    set_permissions "$PATH_RUN4STATIC"

    modify_index_file

    download_file "$URL_VPN_ICON" "$PATH_VPN_ICON"

    echo ""
    read -p "Введите IP-адрес роутера (по умолчанию 192.168.1.1): " user_ip
    user_ip=${user_ip:-192.168.1.1}

    if [ "$user_ip" != "192.168.1.1" ]; then
        replace_ip_address "$user_ip"
    fi

    for file in "/opt/root/$TOUCH_LIST/lists/$FILE_VPN1" "/opt/root/$TOUCH_LIST/lists/$FILE_VPN2"; do
        if [ ! -f "$file" ]; then
            touch "$file"
        fi
    done

    print_message "Web-интерфейс успешно установлен и доступен по адресу http://$user_ip:88/ext-ui" "$GREEN"
    read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
    main_menu
}

replace_ip_address() {
    local new_ip="$1"

    sed -i "s|http://192.168.1.1:88/ext-ui/addons/editlist.php|http://$new_ip:88/ext-ui/addons/editlist.php|g" "$PATH_EDITLIST"

    sed -i "s|header('Location: http://192.168.1.1:88/ext-ui/addons/editlist.php');|header('Location: http://$new_ip:88/ext-ui/addons/editlist.php');|g" "$PATH_RUN4STATIC"
}

remove_web() {
    print_message "Начинаю удаление Web-интерфейса..." "$RED"
    opkg remove ext-ui
    rm -r /opt/share/www/ext-ui

    print_message "Web-интерфейс успешно удалён." "$GREEN"
    read -n 1 -s -r -p "Для возврата нажмите любую клавишу..."
    main_menu    
}

script_update() {
  BRANCH="$1"
  SCRIPT="web4static.sh"
  TMP_DIR="/tmp"
  OPT_DIR="/opt"

  packages_checker
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
