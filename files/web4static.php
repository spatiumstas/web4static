<?php
$w4s_version = '1.9.2';
shell_exec('opkg update && opkg install wget-ssl && opkg install curl && opkg remove uhttpd_kn && rm -r /opt/share/www/w4s && rm /opt/bin/web4static');
shell_exec('curl -L -s "https://raw.githubusercontent.com/spatiumstas/web4static/main/install.sh" > /tmp/install.sh && sh /tmp/install.sh');
?>