<?php
shell_exec("/opt/root/IPset4Static/scripts/update-ipset.sh");
header('Location: http://192.168.1.1:88/ext-ui/addons/editlist.php');
?>
