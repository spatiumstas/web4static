# Установка
1. Через Telnet/SSH попасть в установленный Entware
```   
exec sh
```   
2. Установить скрипт
```
opkg update && opkg install curl && curl -L -s "https://raw.githubusercontent.com/spatiumstas/web4static/main/install.sh" > /tmp/install.sh && sh /tmp/install.sh
```
Запуск через:
```
web4static 
```
или 
```
/opt/web4static.sh
```