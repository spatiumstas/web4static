<?php
$w4s_version = '1.9.1';
shell_exec('opkg remove uhttpd_kn && rm -r /opt/share/www/w4s && rm /opt/bin/web4static');
shell_exec('opkg update && opkg install wget-ssl && opkg install https://github.com/spatiumstas/web4static/releases/download/1.9.1/web4static_1.9.1_kn.ipk');
?>