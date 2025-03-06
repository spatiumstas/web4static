<?php

$w4s_version = '1.5.3';
$config = parse_ini_file(__DIR__ . '/files/config.ini');
$baseUrl = $config['base_url'];
$url = $baseUrl . '/w4s/web4static.php';
$fileRun = 'files/run4Static.php';

define('WEB4STATIC_DIR', '/opt/share/www/w4s');
define('FILES_DIR', WEB4STATIC_DIR . '/files');

function downloadFile($url, $destination) {
    $command = "curl -s -L \"$url\" --output " . escapeshellarg($destination) . " 2>/dev/null";
    exec($command, $output, $returnCode);
    return $returnCode === 0 && file_exists($destination);
}

if (isset($_GET['check_update'])) {
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
        'current_version' => $w4s_version,
        'remote_version' => $remoteVersion,
    ]);
    exit();
}

if (isset($_GET['update_script'])) {
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

        if ($allFilesDownloaded) {
            $success = true;
        } else {
            $output .= "Не все файлы были успешно скачаны\n";
        }
    } else {
        $output = "Ошибка запроса к GitHub API:\n" . $response;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'output' => $output
    ]);
    exit();
}

if (isset($_GET['get_release_notes']) && isset($_GET['v'])) {
    $version = htmlspecialchars($_GET['v']);
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

function getFilesFromPath(string $path, string $extension = 'list', string $suffix = ''): array {
    $files = glob($path . '/*.' . $extension);
    $result = [];
    foreach ($files as $file) {
        if (!is_link($file)) {
            $key = basename($file, '.' . $extension) . $suffix;
            $result[$key] = $file;
        }
    }
    return $result;
}

function getFilesByShell(string $shellCmd, string $extension = 'list', string $suffix = ''): array {
    $path = rtrim(shell_exec($shellCmd));
    if (is_dir($path)) {
        $files = explode("\n", trim(shell_exec("ls $path/*.$extension 2>/dev/null")));
        $result = [];
        foreach ($files as $file) {
            if ($file && !is_link($file)) {
                $key = basename($file, '.' . $extension) . $suffix;
                $result[$key] = $file;
            }
        }
        return $result;
    }
    return [];
}

$categories = [
    'IPSET' => getFilesByShell("readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/lists/'", 'list'),
    'BIRD' => getFilesByShell("readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/lists/'", 'list'),
    'NFQWS' => array_merge(
        getFilesFromPath('/opt/etc/nfqws', 'list', '-nfqws'),
        is_file('/opt/etc/nfqws/nfqws.conf') ? ['nfqws.conf' => '/opt/etc/nfqws/nfqws.conf'] : []
    ),
    'TPWS' => array_merge(
        getFilesFromPath('/opt/etc/tpws', 'list', '-tpws'),
        is_file('/opt/etc/tpws/tpws.conf') ? ['tpws.conf' => '/opt/etc/tpws/tpws.conf'] : []
    ),
    'XKEEN' => getFilesFromPath('/opt/etc/xray/configs/', 'json')
];

$files = [];
foreach ($categories as $category => $categoryFiles) {
    $files = array_merge($files, $categoryFiles);
}

$texts = array_map('file_get_contents', $files);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $content) {
        $normalizedKey = str_replace('_conf', '.conf', $key);
        if (isset($files[$normalizedKey])) {
            $file = $files[$normalizedKey];
            file_put_contents($file, $content);
            shell_exec("tr -d '\r' < " . escapeshellarg($file) . " > " . escapeshellarg($file) . ".tmp && mv " . escapeshellarg($file) . ".tmp " . escapeshellarg($file));
        }
    }
    http_response_code(200);
    exit();
}

if (isset($_GET['export_all'])) {
    $tempDir = sys_get_temp_dir() . '/web4static_backup_' . time();
    mkdir($tempDir, 0777, true);
    foreach ($files as $key => $path) {
        $extension = pathinfo($path, PATHINFO_EXTENSION);м
        $backupFile = $tempDir . '/' . $key . ($extension ? '.' . $extension : '');
        file_put_contents($backupFile, $texts[$key]);
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
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <?php include 'files/icons.svg'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <title>web4static</title>
    <link rel="apple-touch-icon" href="https://raw.githubusercontent.com/spatiumstas/web4static/refs/heads/main/files/apple-touch-icon.png">
    <link rel="icon" href="https://img.icons8.com/external-vectorslab-flat-vectorslab/48/external-Vpn-ai-security-and-security-vectorslab-flat-vectorslab-2.png" alt="external-Vpn-ai-security-and-security-vectorslab-flat-vectorslab-2" sizes="48x48" type="image/x-icon">
    <link rel="icon" href="https://img.icons8.com/external-vectorslab-flat-vectorslab/192/external-Vpn-ai-security-and-security-vectorslab-flat-vectorslab-2.png" alt="external-Vpn-ai-security-and-security-vectorslab-flat-vectorslab-2" sizes="192x192">

    <link rel="stylesheet" href="files/styles.css">
    <script src="files/script.js" defer></script>
    <script>
        var fileRun = '<?php echo $fileRun; ?>';
        document.addEventListener("DOMContentLoaded", function() {
            checkForUpdates();
            const header = document.getElementById("asciiHeader");
            header.addEventListener("click", function() {
                location.reload();
            });
        });
    </script>
</svg>
</head>
<body class="dark-theme">
    <header id="asciiHeader">
        <pre>
            <?php echo htmlspecialchars(file_get_contents('files/ascii.txt')); ?>
        </pre>
    </header>
    <main>
        <form id="mainForm" action="" method="post">
            <?php foreach ($categories as $category => $categoryFiles): ?>
                <?php if (!empty($categoryFiles)): ?>
                    <input type="button" onclick="showSection('<?php echo htmlspecialchars($category); ?>')" value="<?php echo htmlspecialchars($category); ?>" />
                <?php endif; ?>
            <?php endforeach; ?>

            <?php foreach ($categories as $category => $categoryFiles): ?>
                <?php if (!empty($categoryFiles)): ?>
                    <div id="<?php echo htmlspecialchars($category); ?>" class="form-section" style="display:none;">
                        <div class="button-container">
                            <?php foreach ($categoryFiles as $key => $path): ?>
                                <input type="button" onclick="showSubSection('<?php echo htmlspecialchars($key); ?>')" value="<?php echo htmlspecialchars($key); ?>" />
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ($categoryFiles as $key => $path): ?>
                            <div id="<?php echo htmlspecialchars($key); ?>" class="form-section" style="display:none;">
                                <div class="textarea-container">
                                    <textarea name="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($texts[$key]); ?></textarea>
                                </div>
                                <div class="button-container">
                                    <input type="file" id="import-<?php echo htmlspecialchars($key); ?>" style="display:none;" onchange="importFile('<?php echo htmlspecialchars($key); ?>', this)">
                                    <button type="button" onclick="document.getElementById('import-<?php echo htmlspecialchars($key); ?>').click()" aria-label="Replace file" title="Replace">
                                        <svg width="24" height="24"><use href="#swap"/></svg>
                                    </button>
                                    <button type="button" onclick="exportFile('<?php echo htmlspecialchars($key); ?>')" aria-label="Save file" title="Save">
                                        <svg width="24" height="24"><use href="#download-file"/></svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="button-container">
                <input type="submit" value="Save & Restart" />
            </div>
        </form>
    </main>

    <footer>
        <button onclick="toggleTheme()" id="theme-toggle" aria-label="Toggle Dark Mode">
            <svg id="sun-icon" width="24" height="24"><use href="#sun"/></svg>
            <svg id="moon-icon" width="24" height="24" style="display:none;"><use href="#moon"/></svg>
        </button>
        <button type="button" onclick="exportAllFiles()" aria-label="Save all lists" title="Save all lists">
            <svg width="24" height="24"><use href="#download-file"/></svg>
        </button>
        <a href="https://github.com/spatiumstas/web4static" target="_blank">
            <svg id="github-light-icon" class="github-icon" width="24" height="24"><use href="#github-light"/></svg>
            <svg id="github-dark-icon" class="github-icon" width="24" height="24"><use href="#github-dark"/></svg>
        </a>
        <div id="loader-icon" style="display: none;">
            <svg width="24" height="24"><use href="#loader"/></svg>
        </div>
    </footer>
</body>
</html>