<?php
shell_exec("/opt/root/Bird4Static/scripts/add-bird4_routes.sh");
header('Location: http://192.168.1.1:88/ext-ui/addons/BirdEditList.php');
?>
