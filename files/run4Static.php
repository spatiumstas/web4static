<?php
$config = parse_ini_file(__DIR__ . '/config.ini');
$baseUrl = $config['base_url'];
$ipsetPath = trim(shell_exec("readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/scripts/'"));
$birdPath = trim(shell_exec("readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/scripts/'"));

$commands = [];

if (!empty($ipsetPath)) {
    $commands[] = escapeshellcmd("$ipsetPath/update-ipset.sh");
}
if (!empty($birdPath)) {
    $commands[] = escapeshellcmd("$birdPath/add-bird4_routes.sh");
    $commands[] = escapeshellcmd("$birdPath/IPset4Static/scripts/update-ipset.sh");
}

if (is_file('/opt/etc/init.d/S51nfqws')) {
    $commands[] = "/opt/etc/init.d/S51nfqws restart";
}

if (is_file('/opt/etc/init.d/S51tpws')) {
    $commands[] = "/opt/etc/init.d/S51tpws restart";
}

if (is_dir('/opt/etc/xray/configs/')) {
    $commands[] = "xkeen -restart > /dev/null 2>&1 &";
}

if (!empty($commands)) {
    shell_exec(implode("; ", $commands));
}

header('Location: ' . $baseUrl . '/w4s/web4static.php');