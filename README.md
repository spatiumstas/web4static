## Веб-интерфейс для управления списками [Bird4Static](https://github.com/DennoN-RUS/Bird4Static) / [IPset4Static](https://github.com/DennoN-RUS/IPset4Static) / [NFQWS](https://github.com/Anonym-tsk/nfqws-keenetic) / [XKeen](https://github.com/Skrill0/XKeen) / [object-group](https://support.keenetic.ru/eaeu/start/kn-1112/ru/12209-latest-preview-release.html#38763-keeneticos4-3-beta-1) / [HydraRoute](https://github.com/Ground-Zerro/HydraRoute)

![IMG_0671-round-corners](https://github.com/user-attachments/assets/8b0e44b3-bf50-464f-b389-04a7e8f8f29c)


## Установка

1. В `SSH` ввести команду
```shell
opkg update && opkg install curl && curl -L -s "https://raw.githubusercontent.com/spatiumstas/web4static/legacy/install.sh" > /tmp/install.sh && sh /tmp/install.sh
```
<img src="https://github.com/user-attachments/assets/4283eba2-e76c-43af-8646-28409c5f5460" alt="" width="700">

2. В скрипте выбрать установку web-интерфейса

3. Открыть веб-интерфейс в [отдельном окне](http://192.168.1.1:88/w4s/)
   - Ручной запуска скрипта через `web4static` или `/opt/share/www/w4s/web4static.sh`
