<?php
shell_exec("/opt/root/IPset4Static/scripts/update-ipset.sh; /opt/root/Bird4Static/scripts/add-bird4_routes.sh; /opt/root/Bird4Static/IPset4Static/scripts/update-ipset.sh");
header('Location: http://192.168.1.1:88/ext-ui/addons/web4static.php');
?>