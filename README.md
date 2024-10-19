<h1 style="text-align: center;">web4static</h1>

### Веб-интерфейс для управления списками [Bird4Static](https://github.com/DennoN-RUS/Bird4Static) / [IPset4Static](https://github.com/DennoN-RUS/IPset4Static) / [nfqws](https://github.com/Anonym-tsk/nfqws-keenetic) / [tpws](https://github.com/Anonym-tsk/tpws-keenetic)

![IMG_8897-round-corners](https://github.com/user-attachments/assets/d657739a-a86d-4a99-82a9-a73f1f6b3682)

# Установка:

1. Из SSH ввести команду
```shell
opkg update && opkg install curl && curl -L -s "https://raw.githubusercontent.com/spatiumstas/web4static/main/install.sh" > /tmp/install.sh && sh /tmp/install.sh
```
<img src="https://github.com/user-attachments/assets/4283eba2-e76c-43af-8646-28409c5f5460" alt="" width="700">

2. В скрипте выбрать установку web-интерфейса

   - Скрипт установит `php8-cgi и uhttpd_kn` если не было
   - Будет предложено ввести адрес своего роутера. По умолчанию `192.168.1.1`

3. Открыть веб-интерфейс в [отдельном окне](http://192.168.1.1:88/w4s/)
   - Ручной запуска скрипта через `web4static` или `/opt/web4static.sh`
