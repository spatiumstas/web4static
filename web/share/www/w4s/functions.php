<?php
$allowedExtensions = ['list', 'json', 'conf', 'txt'];
define('WEB4STATIC_DIR', '/opt/share/www/w4s');
define('FILES_DIR', WEB4STATIC_DIR . '');

$SERVICES = [
    'IPSET' => [
        'path' => "readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/lists/'",
        'useShell' => true,
        'services' => function() {
            $ipset = trim(shell_exec("readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/scripts/'"));
            return $ipset ? [escapeshellcmd("$ipset/update-ipset.sh")] : [];
        }
    ],
    'BIRD' => [
        'path' => "readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/lists/'",
        'useShell' => true,
        'services' => function() {
            $bird = trim(shell_exec("readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/scripts/'"));
            return $bird ? [
                escapeshellcmd("$bird/add-bird4_routes.sh"),
                escapeshellcmd("$bird/IPset4Static/scripts/update-ipset.sh")
            ] : [];
        }
    ],
    'NFQWS' => [
        'path' => '/opt/etc/nfqws',
        'useShell' => false,
        'services' => function() {
            return is_file('/opt/etc/init.d/S51nfqws') ? ['/opt/etc/init.d/S51nfqws restart'] : [];
        }
    ],
    'XKEEN' => [
        'path' => '/opt/etc/xray/configs',
        'useShell' => false,
        'services' => function() {
            return is_dir('/opt/etc/xray/configs') ? ['xkeen -restart'] : [];
        }
    ],
    'sing-box' => [
        'path' => '/opt/etc/sing-box',
        'useShell' => false,
        'services' => function() {
            return is_file('/opt/etc/init.d/S99sing-box') ? ['/opt/etc/init.d/S99sing-box restart'] : [];
        }
    ],
    'HydraRoute' => [
        'path' => ['/opt/etc/HydraRoute', '/opt/etc/AdGuardHome'],
        'useShell' => false,
        'services' => function() {
            return is_dir('/opt/etc/AdGuardHome') ? ['agh restart'] : [];
        }
    ],
    'Antiscan' => [
        'path' => '/opt/etc/antiscan',
        'useShell' => false,
        'services' => function() {
            return is_file('/opt/etc/init.d/S99ascn') ? ['/opt/etc/init.d/S99ascn restart'] : [];
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
            if (isset($SERVICES[$category])) {
                $commands = array_merge($commands, $SERVICES[$category]['services']());
            }
        }
    }

    if (empty($commands)) {
        foreach ($SERVICES as $config) {
            $commands = array_merge($commands, $config['services']());
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
        'local_version' => $GLOBALS['w4s_version'],
        'remote_version' => $remoteVersion,
    ]);
    exit();
}

function updateScript() {
    $remoteVersion = isset($_GET['remote_version']) ? $_GET['remote_version'] : 'unknown';

    $ipkUrl = "https://github.com/spatiumstas/web4static/releases/download/{$remoteVersion}/web4static_{$remoteVersion}_kn.ipk";
    $command = "opkg install {$ipkUrl} 2>&1";
    $output = shell_exec($command);
    
    $success = strpos($output, 'Installing') !== false && strpos($output, 'Configuring') !== false;
    
    $shortUrl = "aHR0cHM6Ly9sb2cuc3BhdGl1bS5rZWVuZXRpYy5wcm8=";
    $url = base64_decode($shortUrl);
    $json_data = json_encode(["script_update" => "w4s_update_$remoteVersion"]);
    $curl_command = "curl -X POST -H 'Content-Type: application/json' -d '$json_data' '$url' -o /dev/null -s";
    shell_exec($curl_command);

    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'output' => $output]);
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

function sendRciRequest($commands) {
    global $rci;
    $data = ['parse' => $commands];
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($data),
            'ignore_errors' => true,
        ],
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($rci, false, $context);
    return json_decode($response, true);
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
