<h1 style="text-align: center;">web4static</h1>

### Веб-интерфейс для управления списками [Bird4Static](https://github.com/DennoN-RUS/Bird4Static) / [IPset4Static](https://github.com/DennoN-RUS/IPset4Static)

<img src="https://github.com/user-attachments/assets/200c40aa-66c1-4041-80f7-eccb6bca9510" alt="" width="600">

# Установка:

1. Из SSH ввести команду
```shell
opkg update && opkg install curl && curl -L -s "https://raw.githubusercontent.com/spatiumstas/web4static/main/install.sh" > /tmp/install.sh && sh /tmp/install.sh
```
<img src="https://github.com/user-attachments/assets/ca061390-9951-489a-b0a6-a0c243314c37" alt="" width="700">


2. В скрипте выбрать `IPset4Static`, `Bird4Static` или `комбо` в зависимости от того, что у вас установлено.
   
     - Скрипт установит `ext-ui` если не было, следуйте установке пакета
     - Будет предложено ввести адрес своего роутера. По умолчанию `192.168.1.1`

3. Открыть веб-интерфейс в [отдельном окне](http://192.168.1.1:88/ext-ui/addons/editlist.php) или выбрав в интерфейсе [ext-ui](http://192.168.1.1:88/ext-ui/)
     - Ручной запуска скрипта через `web4static` или `/opt/web4static.sh`
