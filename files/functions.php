<?php
$allowedExtensions = ['list', 'json', 'conf'];
$rci = "http://localhost:79/rci/";

define('WEB4STATIC_DIR', '/opt/share/www/w4s');
define('FILES_DIR', WEB4STATIC_DIR . '/files');

function downloadFile($url, $destination) {
    $command = "curl -s -L \"$url\" --output " . escapeshellarg($destination) . " 2>/dev/null";
    exec($command, $output, $returnCode);
    return $returnCode === 0 && file_exists($destination);
}

function restartServices() {
    $commands = [];
    $ipset = trim(shell_exec("readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/scripts/'"));
    $bird  = trim(shell_exec("readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/scripts/'"));

    $commands = array_merge($commands,
        $ipset ? [escapeshellcmd("$ipset/update-ipset.sh")] : [],
        $bird ? [
            escapeshellcmd("$bird/add-bird4_routes.sh"),
            escapeshellcmd("$bird/IPset4Static/scripts/update-ipset.sh")
        ] : [],
        is_file('/opt/etc/init.d/S51nfqws') ? ['/opt/etc/init.d/S51nfqws restart'] : [],
        is_file('/opt/etc/init.d/S51tpws') ? ['/opt/etc/init.d/S51tpws restart'] : [],
        is_dir('/opt/etc/xray/configs/') ? ['xkeen -restart'] : [],
        is_dir('/opt/etc/sing-box/') ? ['/opt/etc/init.d/S99sing-box restart'] : []
    );

    if ($commands) {
        $cmd = "sh -c '" . implode(" ; ", $commands) . "' >/dev/null 2>&1 & echo $!";
        shell_exec($cmd);
    }
}

function checkUpdate() {
    $fileUrl = 'https://raw.githubusercontent.com/spatiumstas/web4static/refs/heads/main/files/web4static.php';
    $fileContent = trim(shell_exec("curl -s $fileUrl"));

    if (!$fileContent) {
        die(json_encode(['error' => 'Failed to fetch file']));
    }

    $remoteVersion = 'unknown';
    if (preg_match("/\\\$w4s_version\s*=\s*'([^']+)';/", $fileContent, $matches)) {
        $remoteVersion = $matches[1];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'local_version' => $GLOBALS['w4s_version'],
        'remote_version' => $remoteVersion,
    ]);
    exit();
}

function updateScript() {
    $remoteVersion = isset($_GET['remote_version']) ? $_GET['remote_version'] : 'unknown';

    $apiUrl = 'https://api.github.com/repos/spatiumstas/web4static/contents/files?ref=main';
    $command = "curl -s -L -H 'User-Agent: web4static-updater' \"$apiUrl\"";
    $response = shell_exec($command);
    $files = json_decode($response, true);

    $output = '';
    $success = false;

    if ($files && is_array($files)) {
        if (!is_dir(FILES_DIR)) {
            mkdir(FILES_DIR, 0777, true);
        }

        $allFilesDownloaded = true;
        foreach ($files as $file) {
            if ($file['type'] === 'file') {
                $fileUrl = $file['download_url'];
                $fileName = $file['name'];

                if ($fileName === 'config.ini') {
                    continue;
                }

                $destination = FILES_DIR . '/' . $fileName;
                if ($fileName === 'web4static.php') {
                    $destination = WEB4STATIC_DIR . '/web4static.php';
                }

                if (downloadFile($fileUrl, $destination)) {
                } else {
                    $output .= "Ошибка при скачивании файла: $fileName\n";
                    $allFilesDownloaded = false;
                }
            }
        }

        $success = $allFilesDownloaded ? true : false;
        $output .= $allFilesDownloaded ? '' : "Не все файлы были успешно скачаны\n";
    } else {
        $output = "Ошибка запроса к GitHub API:\n" . $response;
    }

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

function getLists(string $path, bool $useShell = false): array {
    global $allowedExtensions;
    $result = [];
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
                $result[basename($file)] = $file;
            }
        }
    }
    return $result;
}

function exportAllFiles($categories) {
    $tempDir = sys_get_temp_dir() . '/w4s_backup_' . time();
    mkdir($tempDir, 0777, true);

    foreach ($categories as $category => $categoryFiles) {
        if (!empty($categoryFiles)) {
            $categoryDir = $tempDir . '/' . $category;
            mkdir($categoryDir, 0777, true);
            foreach ($categoryFiles as $fileName => $filePath) {
                $backupFile = $categoryDir . '/' . $fileName;
                file_put_contents($backupFile, file_get_contents($filePath));
            }
        }
    }

    $archiveName = 'w4s_backup.tar.gz';
    $tarCmd = "tar -czf " . escapeshellarg($archiveName) . " -C " . escapeshellarg($tempDir) . " .";
    shell_exec($tarCmd);
    shell_exec("rm -rf " . escapeshellarg($tempDir));
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $archiveName . '"');
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

    foreach ($_POST as $key => $content) {
        foreach ($files as $fileKey => $filePath) {
            $fileName = pathinfo($fileKey, PATHINFO_FILENAME);
            if ($fileName === $key) {
                if (is_array($GLOBALS['categories']['object-group']) && array_key_exists($fileKey, $GLOBALS['categories']['object-group'])) {
                    $oldLines = explode("\n", trim($files[$fileKey]));
                    $newLines = explode("\n", trim($content));

                    $oldDomains = array_filter($oldLines, function($line) {
                        return !empty(trim($line)) && strpos(trim($line), '#') !== 0;
                    });
                    $newDomains = array_filter($newLines, function($line) {
                        return !empty(trim($line)) && strpos(trim($line), '#') !== 0;
                    });

                    $oldDomains = array_map('trim', array_values($oldDomains));
                    $newDomains = array_map('trim', array_values($newDomains));

                    $toInclude = array_diff($newDomains, $oldDomains);
                    $toExclude = array_diff($oldDomains, $newDomains);

                    foreach ($toInclude as $domain) {
                        $commands[] = "object-group fqdn $key include $domain";
                    }

                    foreach ($toExclude as $domain) {
                        $commands[] = "no object-group fqdn $key include $domain";
                    }
                } else {
                    file_put_contents($filePath, $content);
                    shell_exec("tr -d '\r' < " . escapeshellarg($filePath) . " > " . escapeshellarg($filePath) . ".tmp && mv " . escapeshellarg($filePath) . ".tmp " . escapeshellarg($filePath));
                }
                break;
            }
        }
    }

    if (!empty($commands) && is_array($GLOBALS['categories']['object-group'])) {
        $response = sendRciRequest($commands);
        if ($response && is_array($response)) {
            foreach ($response['status'] as $status) {
            }
        } else {
            http_response_code(500);
            exit();
        }
    }

    restartServices();
    http_response_code(200);
    exit();
}

function getObjectGroupLists() {
    global $rci;
    if (!file_exists('/bin/ndmc')) {
        return false;
    }

    $command = "/bin/ndmc -c 'show version' | grep 'title' | awk -F': ' '{print \$2}' 2>/dev/null";
    $versionOutput = trim(shell_exec($command));
    if (!$versionOutput || version_compare(strtok($versionOutput, ' ') ?? '0.0', '4.3', '<')) {
        return false;
    }

    $request = "$rci/show/object-group/fqdn";
    $response = file_get_contents($request);
    $data = json_decode($response, true);

    $lists = [];
    if (is_array($data) && isset($data['group']) && !empty($data['group'])) {
        foreach ($data['group'] as $group) {
            $fileName = "{$group['group-name']}.list";
            $domains = array_map(function ($entry) {
                return $entry['fqdn'];
            }, array_filter($group['entry'] ?? [], function ($entry) {
                return isset($entry['type']) && $entry['type'] === 'config';
            }));

            $lists[$fileName] = implode("\n", $domains);
        }
    }
    return $lists;
}