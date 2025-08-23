<?php
$allowedExtensions = ['list', 'json', 'conf', 'txt', 'yaml'];
define('WEB4STATIC_DIR', '/opt/share/www/w4s');
define('FILES_DIR', WEB4STATIC_DIR . '');

$SERVICES = [
    'IPSET' => [
        'path' => "readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/lists/'",
        'useShell' => true,
        'restart' => function($self) {
            $ipset = trim(shell_exec("readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/scripts/'"));
            return $ipset ? [escapeshellcmd("$ipset/update-ipset.sh")] : [];
        },
    ],
    'BIRD' => [
        'path' => "readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/lists/'",
        'useShell' => true,
        'restart' => function($self) {
            $bird = trim(shell_exec("readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/scripts/'"));
            return $bird ? [
                escapeshellcmd("$bird/add-bird4_routes.sh"),
                escapeshellcmd("$bird/IPset4Static/scripts/update-ipset.sh")
            ] : [];
        },
    ],
    'NFQWS' => [
        'init' => '/opt/etc/init.d/S51nfqws',
        'path' => '/opt/etc/nfqws',
        'useShell' => false,
        'restart' => function($self) {
            return is_file($self['init']) ? [$self['init'] . ' restart'] : [];
        },
        'status' => function($self) {
            return is_file($self['init']) ? shell_exec($self['init'] . ' status 2>&1') : 'Нет статуса';
        }
    ],
    'XKeen' => [
        'init' => 'xkeen',
        'path' => ['/opt/etc/xray/configs', '/opt/etc/mihomo/config.yaml'],
        'useShell' => false,
        'restart' => function($self) {
            return is_dir($self['path']) ? [$self['init'] . ' -restart'] : [];
        },
        'status' => function($self) {
            return shell_exec($self['init'] . ' -status 2>&1');
        }
    ],
    'sing-box' => [
        'init' => '/opt/etc/init.d/S99sing-box',
        'path' => '/opt/etc/sing-box',
        'useShell' => false,
        'restart' => function($self) {
            return is_file($self['init']) ? [$self['init'] . ' restart'] : [];
        },
        'status' => function($self) {
            return is_file($self['init']) ? shell_exec($self['init'] . ' status 2>&1') : 'Нет статуса';
        }
    ],
    'HydraRoute' => [
        'path' => ['/opt/etc/HydraRoute', '/opt/etc/AdGuardHome/domain.conf'],
        'useShell' => false,
        'restart' => function($self) {
            return is_dir('/opt/etc/AdGuardHome') ? ['agh restart'] : [];
        },
    ],
    'Antiscan' => [
        'init' => '/opt/etc/init.d/S99ascn',
        'path' => '/opt/etc/antiscan',
        'useShell' => false,
        'restart' => function($self) {
            return is_file($self['init']) ? [$self['init'] . ' restart'] : [];
        },
        'status' => function($self) {
            return is_file($self['init']) ? shell_exec($self['init'] . ' status 2>&1') : 'Нет статуса';
        }
    ],
    'dnsmasq' => [
        'init' => '/opt/etc/init.d/S56dnsmasq',
        'path' => '/opt/etc/dnsmasq.conf',
        'useShell' => false,
        'restart' => function($self) {
            return is_file($self['init']) ? [$self['init'] . ' restart'] : [];
        },
        'status' => function($self) {
            return is_file($self['init']) ? shell_exec($self['init'] . ' status 2>&1') : 'Нет статуса';
        }
    ]
];

function downloadFile($url, $destination) {
    $command = "curl -s -L \"$url\" --output " . escapeshellarg($destination) . " 2>/dev/null";
    exec($command, $output, $returnCode);
    return $returnCode === 0 && file_exists($destination);
}

function getCategories() {
    global $SERVICES;
    $categories = [];

    foreach ($SERVICES as $category => $config) {
        $categories[$category] = getLists($config['path'], $config['useShell']);
    }

    return $categories;
}

function restartServices($changedCategories = null) {
    global $SERVICES;
    $commands = [];

    if ($changedCategories && is_array($changedCategories)) {
        foreach ($changedCategories as $category) {
            if (isset($SERVICES[$category]) && isset($SERVICES[$category]['restart'])) {
                $commands = array_merge($commands, $SERVICES[$category]['restart']($SERVICES[$category]));
            }
        }
    }

    if (empty($commands)) {
        foreach ($SERVICES as $cat => $config) {
            if (isset($config['restart'])) {
                $commands = array_merge($commands, $config['restart']($config));
            }
        }
    }

    if ($commands) {
        $cmd = "sh -c '" . implode(" ; ", $commands) . "' >/dev/null 2>&1 & echo $!";
        shell_exec($cmd);
    }
}

function checkUpdate() {
    $apiUrl = 'https://api.github.com/repos/spatiumstas/web4static/releases/latest';
    $command = "curl -s -L -H 'User-Agent: web4static-updater' \"$apiUrl\"";
    $response = shell_exec($command);
    $release = json_decode($response, true);

    if (!$release || !isset($release['tag_name'])) {
        die(json_encode(['error' => 'Failed to fetch release info']));
    }

    $remoteVersion = $release['tag_name'];

    header('Content-Type: application/json');
    echo json_encode([
        'local_version' => getVersion(),
        'remote_version' => $remoteVersion,
    ]);
    exit();
}

function updateScript() {
    $remoteVersion = isset($_GET['remote_version']) ? $_GET['remote_version'] : 'unknown';
    $output = null;
    $retval = null;

    $configFile = '/opt/etc/opkg/web4static.conf';
    if (!file_exists($configFile)) {
        exec("mkdir -p /opt/etc/opkg", $output, $retval);
        exec("echo 'src/gz web4static https://spatiumstas.github.io/web4static/all/' > $configFile", $output, $retval);
    }
    exec("opkg update 2>&1");
    exec("opkg upgrade web4static 2>&1", $output, $retval);

    if (empty($output)) {
        $output[] = 'Nothing to update';
    }

    $shortUrl = "aHR0cHM6Ly9sb2cuc3BhdGl1bS5uZXRjcmF6ZS5wcm8=";
    $url = base64_decode($shortUrl);
    $json_data = json_encode(["script_update" => "w4s_update_$remoteVersion"]);
    $curl_command = "curl -X POST -H 'Content-Type: application/json' -d '$json_data' '$url' -o /dev/null -s --fail --max-time 2 --retry 0";
    shell_exec($curl_command);

    header('Content-Type: application/json');
    echo json_encode(['output' => implode("\n", $output), 'status' => $retval]);
    exit();
}

function getReleaseNotes($version) {
    $apiUrl = "https://api.github.com/repos/spatiumstas/web4static/releases/tags/$version";
    $command = "curl -s -L -H 'User-Agent: web4static-updater' \"$apiUrl\"";
    $response = shell_exec($command);
    $release = json_decode($response, true);

    $notes = [];
    if ($release && isset($release['body'])) {
        $notes = explode("\n", trim($release['body']));
        $notes = array_filter($notes, function($line) {
            return !empty(trim($line)) && strpos($line, '#') !== 0;
        });
    }

    header('Content-Type: application/json');
    echo json_encode(['notes' => $notes]);
    exit();
}

function getLists($paths, bool $useShell = false): array {
    global $allowedExtensions;
    $result = [];

    if (is_string($paths)) {
        $paths = [$paths];
    }

    foreach ($paths as $path) {
        if ($useShell) {
            $path = rtrim(shell_exec($path) ?? '');
        }
        if (is_file($path)) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            if (in_array($extension, $allowedExtensions)) {
                $result[$path] = $path;
            }
            continue;
        }
        if (is_dir($path)) {
            if ($useShell) {
                $files = explode("\n", trim(shell_exec("ls $path/* 2>/dev/null")));
            } else {
                $files = glob($path . '/*');
            }
            foreach ($files as $file) {
                if ($file && !is_link($file) && is_file($file)) {
                    $extension = pathinfo($file, PATHINFO_EXTENSION);
                    if (in_array($extension, $allowedExtensions)) {
                        $result[$path . '/' . basename($file)] = $file;
                    }
                }
            }
        }
    }
    return $result;
}

function exportAllFiles($categories) {
    $tempDir = sys_get_temp_dir() . '/w4s_backup_' . time();
    mkdir($tempDir, 0777, true);

    foreach ($categories as $category => $categoryFiles) {
        if (!empty($categoryFiles) && is_array($categoryFiles)) {
            $categoryDir = $tempDir . '/' . $category;
            mkdir($categoryDir, 0777, true);
            foreach ($categoryFiles as $fileName => $filePath) {
                $baseFileName = basename($filePath);
                $backupFile = $categoryDir . '/' . $baseFileName;
                file_put_contents($backupFile, file_get_contents($filePath));
            }
        }
    }

    $archiveName = sys_get_temp_dir() . '/w4s_backup_' . date('Y-m-d') . '.tar.gz';
    $tarCmd = "tar -czf " . escapeshellarg($archiveName) . " -C " . escapeshellarg($tempDir) . " .";
    shell_exec($tarCmd);
    shell_exec("rm -rf " . escapeshellarg($tempDir));
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="w4s_backup_' . date('Y-m-d') . '.tar.gz"');
    header('Content-Length: ' . filesize($archiveName));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($archiveName);
    unlink($archiveName);
    exit();
}

function handlePostRequest($files) {
    $commands = [];
    $changedCategories = [];

    if (isset($_POST['changed_categories'])) {
        $changedCategories = json_decode($_POST['changed_categories'], true);
    }

    foreach ($_POST as $key => $content) {
        $parts = explode('/', $key);
        if (count($parts) === 2) {
            $category = $parts[0];
            $fileName = $parts[1];
        } else {
            $category = '';
            $fileName = $key;
        }

        foreach ($files as $fileKey => $filePath) {
            $baseFileName = pathinfo($fileKey, PATHINFO_FILENAME);
            if ($baseFileName === $fileName && ($category === '' || array_key_exists($fileKey, $GLOBALS['categories'][$category] ?? []))) {
                file_put_contents($filePath, $content);
                $tmpFile = $filePath . '.tmp';
                shell_exec("tr -d '\r' < " . escapeshellarg($filePath) . " > " . escapeshellarg($tmpFile) . " && mv " . escapeshellarg($tmpFile) . " " . escapeshellarg($filePath));
                break;
            }
        }
    }

    restartServices($changedCategories);
    http_response_code(200);
    exit();
}

function stripAnsi($text) {
    $text = preg_replace('/\e\[[;?0-9]*[a-zA-Z]/', '', $text);
    $text = str_replace(["\t", "\u{00A0}"], ' ', $text);
    return $text;
}

function getServiceStatus($category) {
    global $SERVICES;
    if (isset($SERVICES[$category]['status'])) {
        $status = $SERVICES[$category]['status']($SERVICES[$category]);
        return stripAnsi($status);
    }
    return 'Статус не поддерживается';
}

if (isset($_GET['service_status']) && isset($SERVICES[$_GET['service_status']])) {
    $cat = $_GET['service_status'];
    $status = getServiceStatus($cat);
    header('Content-Type: application/json');
    echo json_encode(['status' => $status]);
    exit();
}

function getVersion() {
    $output = shell_exec('opkg list-installed 2>/dev/null | grep "^web4static - "');
    return trim(substr($output, strlen('web4static - ')));
}