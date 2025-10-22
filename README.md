## Веб‑интерфейс управления конфигурациями [Bird4Static](https://github.com/DennoN-RUS/Bird4Static) - [IPset4Static](https://github.com/DennoN-RUS/IPset4Static) - [NFQWS](https://github.com/Anonym-tsk/nfqws-keenetic) - [XKeen](https://github.com/Skrill0/XKeen) - [HydraRoute](https://github.com/Ground-Zerro/HydraRoute) - [Antiscan](https://github.com/dimon27254/antiscan) - Xray - sing-box - dnsmasq

<img src="https://github.com/user-attachments/assets/f1c8096e-5938-4923-ab0e-bf020cdbd377" alt="" width="700">

### Автоустановка

```shell
opkg update && opkg install curl ca-certificates wget-ssl && curl -L -s "https://raw.githubusercontent.com/spatiumstas/web4static/main/install.sh" > /tmp/install.sh && sh /tmp/install.sh
```

### Ручная установка

1. Установите необходимые зависимости
   ```
   opkg update && opkg install ca-certificates wget-ssl && opkg remove wget-nossl
   ```
2. Установите opkg-репозиторий в систему
   ```
   mkdir -p /opt/etc/opkg
   echo "src/gz web4static https://spatiumstas.github.io/web4static/all" > /opt/etc/opkg/web4static.conf
   ```

3. Установите пакет
   ```
   opkg update && opkg install web4static
   ```   

> [!NOTE]
> Веб‑интерфейс доступен по адресу `http://<router_ip>:99` (например http://192.168.1.1:99)<br/>

> [!TIP]
> По-умолчанию php использует только 8Мб памяти. Из-за этого ограничения, могут не загружаться большие списки файлов.
> Вы можете изменить конфигурацию php самостоятельно:<br/>
> Откройте файл `/opt/etc/php.ini` и измените следующие значения
> ```
> memory_limit = 32M
> post_max_size = 32M
> upload_max_filesize = 16M
> ```

###  Авторизация
> [!NOTE]
> Для авторизации используйте `логин:пароль` от Entware (по-умолчанию `root:keenetic`)
>
> Для включения измените флаг на `"BASIC_AUTH" => "1"` в файле `/opt/etc/lighttpd/conf.d/81-w4s-local.conf` и выполните перезапуск
```
/opt/etc/init.d/S80lighttpd restart
```

###  Удаление
```
opkg remove web4static
```
