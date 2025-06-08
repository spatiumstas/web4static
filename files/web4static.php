<?php
$w4s_version = '1.9';

// Удалить старые файлы
shell_exec('opkg remove uhttpd_kn && rm -r /opt/share/www/w4s');

// Установить новый пакет
shell_exec('opkg install wget-ssl && opkg install https://github.com/spatiumstas/web4static/releases/download/1.9/web4static_1.9_kn.ipk');
?>