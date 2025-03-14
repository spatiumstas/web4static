<?php
$config = parse_ini_file(__DIR__ . '/config.ini');
$baseUrl = $config['base_url'];
$url = $baseUrl . '/w4s/web4static.php';
$allowedExtensions = ['list', 'json', 'conf'];

define('WEB4STATIC_DIR', '/opt/share/www/w4s');
define('FILES_DIR', WEB4STATIC_DIR . '/files');

function downloadFile($url, $destination) {
    $command = "curl -s -L \"$url\" --output " . escapeshellarg($destination) . " 2>/dev/null";
    exec($command, $output, $returnCode);
    return $returnCode === 0 && file_exists($destination);
}

function restartServices() {
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
        'current_version' => $GLOBALS['w4s_version'],
        'remote_version' => $remoteVersion,
    ]);
    exit();
}

function updateScript() {
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
        $path = rtrim(shell_exec($path));
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

function handlePostRequest($files) {
    foreach ($_POST as $key => $content) {
        foreach ($files as $fileKey => $filePath) {
            if (pathinfo($fileKey, PATHINFO_FILENAME) === $key) {
                file_put_contents($filePath, $content);
                shell_exec("tr -d '\r' < " . escapeshellarg($filePath) . " > " . escapeshellarg($filePath) . ".tmp && mv " . escapeshellarg($filePath) . ".tmp " . escapeshellarg($filePath));
                break;
            }
        }
    }
    restartServices();
    http_response_code(200);
    exit();
}