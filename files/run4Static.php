<?php
$config = parse_ini_file(__DIR__ . '/files/config.ini');
$baseUrl = $config['base_url'];

shell_exec("/opt/root/IPset4Static/scripts/update-ipset.sh; /opt/root/Bird4Static/scripts/add-bird4_routes.sh; /opt/root/Bird4Static/IPset4Static/scripts/update-ipset.sh; /opt/etc/init.d/S51nfqws restart; /opt/etc/init.d/S51tpws restart");
header('Location: ' . $baseUrl . '/w4s/web4static.php');