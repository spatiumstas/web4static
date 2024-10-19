<h1 style="text-align: center;">web4static</h1>

### Веб-интерфейс для управления списками [Bird4Static](https://github.com/DennoN-RUS/Bird4Static) / [IPset4Static](https://github.com/DennoN-RUS/IPset4Static)

![IMG_8897-round-corners](https://github.com/user-attachments/assets/d657739a-a86d-4a99-82a9-a73f1f6b3682)

# Установка:

1. Из SSH ввести команду
```shell
opkg update && opkg install curl && curl -L -s "https://raw.githubusercontent.com/spatiumstas/web4static/main/install.sh" > /tmp/install.sh && sh /tmp/install.sh
```
<img src="https://github.com/user-attachments/assets/d2a164a9-13dc-487d-a8d1-26e68d6fda72" alt="" width="700">

2. В скрипте выбрать установку web-интерфейса

   - Скрипт установит `ext-ui` если не было, следуйте установке пакета
   - Будет предложено ввести адрес своего роутера. По умолчанию `192.168.1.1`

3. Открыть веб-интерфейс в [отдельном окне](http://192.168.1.1:88/ext-ui/addons/web4static.php) или выбрав в интерфейсе [ext-ui](http://192.168.1.1:88/ext-ui/)
   - Ручной запуска скрипта через `web4static` или `/opt/web4static.sh`
