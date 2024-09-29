# web4static


Веб-интерфейс для управления списками [Bird4Static](https://github.com/DennoN-RUS/Bird4Static)/[IPset4Static](https://github.com/DennoN-RUS/IPset4Static)

![Screenshot_4](https://github.com/user-attachments/assets/137b6037-998c-4a3d-9e9e-ebf3879697c6)


Установка:

1. Из SSH ввести команду
```shell
opkg update && opkg install curl && curl -L -s "https://raw.githubusercontent.com/spatiumstas/web4static/main/install.sh" > /tmp/install.sh && sh /tmp/install.sh
```
2. В скрипте выбрать `IPset4Static`, `Bird4Static` или `комбо` в зависимости от того, что у вас установлено.
![Screenshot_5](https://github.com/user-attachments/assets/ca34e58e-45ce-40d5-a3fd-38357e239bf5)


- Скрипт установит `ext-ui` если не было, следуйте установке пакета
- Будет предложено ввести адрес своего роутера. По умолчанию `192.168.1.1`

3. Открыть веб-интерфейс в [отдельном окне](http://192.168.1.1:88/ext-ui/addons/editlist.php) или выбрав в интерфейсе [ext-ui](http://192.168.1.1:88/ext-ui/)


- Ручной запуска скрипта через `web4static` или `/opt/web4static.sh`
