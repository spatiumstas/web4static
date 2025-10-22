<?php
$allowedExtensions = ['list', 'json', 'conf', 'txt', 'yaml', 'sh'];
$AUTH_FLAG = getenv('BASIC_AUTH');
define('WEB4STATIC_DIR', '/opt/share/www/w4s');

if ($AUTH_FLAG === false && isset($_SERVER['BASIC_AUTH'])) {
    $AUTH_FLAG = $_SERVER['BASIC_AUTH'];
}

function isAuthEnabled(): bool {
    global $AUTH_FLAG;
    $flag = strtolower(trim((string)$AUTH_FLAG));
    return in_array($flag, ['1', 'true', 'on', 'yes'], true);
}

function authenticateUser(string $username, string $password): bool {
    $rootDir = file_exists('/opt/etc/passwd') ? '/opt' : '';
    $passwdFile = $rootDir . '/etc/passwd';
    $shadowFile = $rootDir . '/etc/shadow';

    $sourceFile = file_exists($shadowFile) ? $shadowFile : $passwdFile;
    if (!is_readable($sourceFile)) {
        return false;
    }

    $users = file($sourceFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($users as $line) {
        if (strpos($line, $username . ':') === 0) {
            $parts = explode(':', $line);
            $passwdInDB = $parts[1] ?? '';
            if ($passwdInDB === '') {
                return $password === '';
            }
            return crypt($password, $passwdInDB) === $passwdInDB;
        }
    }
    return false;
}

function parseBasicAuthFromHeader(): array {
    $user = $_SERVER['PHP_AUTH_USER'] ?? null;
    $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
    if ($user !== null) {
        return [$user, (string)$pass];
    }
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
    if (stripos($authHeader, 'Basic ') === 0) {
        $decoded = base64_decode(substr($authHeader, 6));
        if ($decoded !== false && strpos($decoded, ':') !== false) {
            return explode(':', $decoded, 2);
        }
    }
    return [null, null];
}

function enforceBasicAuth(string $realm = 'web4static'): void {
    if (!isAuthEnabled()) {
        return;
    }
    list($user, $pass) = parseBasicAuthFromHeader();
    if ($user === null || !authenticateUser($user, $pass)) {
        header('WWW-Authenticate: Basic realm="' . $realm . '", charset="UTF-8"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Authorization required';
        exit();
    }
}

$SERVICES = [
    'Bird4Static' => [
        'init' => '/opt/etc/init.d/S70bird',
        'path' => [
            "readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/lists/'",
            "readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/scripts/'"
        ],
        'useShell' => true,
        'restart' => function($self) {
            $bird = trim(shell_exec("readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/scripts/'"));
            return $bird ? [
                escapeshellcmd("$bird/add-bird4_routes.sh"),
                escapeshellcmd("$bird/IPset4Static/scripts/update-ipset.sh")
            ] : [];
        },
        'status' => function($self) {
            return is_file($self['init']) ? shell_exec($self['init'] . ' status 2>&1') : 'Нет статуса';
        }
    ],
    'IPset4Static' => [
        'path' => [
            "readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/lists/'",
            "readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/scripts/'"
        ],
        'useShell' => true,
        'restart' => function($self) {
            $ipset = trim(shell_exec("readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/scripts/'"));
            return $ipset ? [escapeshellcmd("$ipset/update-ipset.sh")] : [];
        },
    ],
    'NFQWS' => [
        'init' => '/opt/etc/init.d/S51nfqws',
        'path' => '/opt/etc/nfqws',
        'useShell' => false,
        'packages' => ['nfqws-keenetic'],
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
        'packages' => ['xkeen'],
        'validate_config' => true,
        'restart' => function($self) {
            foreach ((array)$self['path'] as $p) {
                if (is_dir($p) || is_file($p)) {
                    return [$self['init'] . ' -restart'];
                }
            }
            return [];
        },
        'status' => function($self, $configPath = null) {
            $out = shell_exec($self['init'] . ' -status 2>&1');
            if ($configPath) {
                $cfg = (string)$configPath;
                if (preg_match('/\.(ya?ml)$/i', $cfg)) {
                    $out .= "\n" . shell_exec('mihomo -t -f ' . escapeshellarg($cfg) . ' 2>&1');
                } else {
                    $out .= "\n" . shell_exec('xray -test -config ' . escapeshellarg($cfg) . " 2>&1 | sed '1,2d'");
                }
            }
            return $out;
        }
    ],
    'sing-box' => [
        'init' => '/opt/etc/init.d/S99sing-box',
        'path' => '/opt/etc/sing-box',
        'useShell' => false,
        'packages' => ['sing-box-go'],
        'validate_config' => true,
        'restart' => function($self) {
            return is_file($self['init']) ? [$self['init'] . ' restart'] : [];
        },
        'status' => function($self, $configPath = null) {
            $out = is_file($self['init']) ? shell_exec($self['init'] . ' status 2>&1') : '';
            if ($configPath) {
                $cfg = (string)$configPath;
                $out .= "\n" . shell_exec('sing-box check -c ' . escapeshellarg($cfg) . " 2>&1");
            }
            return trim((string)$out) !== '' ? $out : 'Нет статуса';
        }
    ],
    'Xray' => [
        'init' => '/opt/etc/init.d/S24xray',
        'path' => '/opt/etc/xray',
        'useShell' => false,
        'packages' => ['xray'],
        'validate_config' => true,
        'restart' => function($self) {
            return is_file($self['init']) ? [$self['init'] . ' restart'] : [];
        },
        'status' => function($self, $configPath = null) {
            $out = is_file($self['init']) ? shell_exec($self['init'] . ' status 2>&1') : '';
            if ($configPath) {
                $cfg = (string)$configPath;
                $out .= "\n" . shell_exec('xray -test -config ' . escapeshellarg($cfg) . " 2>&1 | sed '1,2d'");
            }
            return trim((string)$out) !== '' ? $out : 'Нет статуса';
        }
    ],
    'HydraRoute' => [
        'path' => ['/opt/etc/HydraRoute', '/opt/etc/AdGuardHome/domain.conf'],
        'useShell' => false,
        'packages' => ['hydraroute', 'hrneo'],
        'restart' => function($self) {
            return is_dir('/opt/etc/AdGuardHome') ? ['agh restart'] : [];
        },
    ],
    'Antiscan' => [
        'init' => '/opt/etc/init.d/S99ascn',
        'path' => '/opt/etc/antiscan',
        'useShell' => false,
        'packages' => ['antiscan'],
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
        'packages' => ['dnsmasq-full'],
        'restart' => function($self) {
            return is_file($self['init']) ? [$self['init'] . ' restart'] : [];
        },
        'status' => function($self) {
            return is_file($self['init']) ? shell_exec($self['init'] . ' status 2>&1') : 'Нет статуса';
        }
    ]
];

function isPackageInstalled(array $packageNames): bool {
    static $installed;
    if ($installed === null) {
        $installed = [];
        $out = shell_exec('opkg list-installed 2>/dev/null');
        $lines = $out ? explode("\n", trim($out)) : [];
        foreach ($lines as $line) {
            if ($line === '') continue;
            $parts = explode(' - ', $line, 2);
            $name = trim($parts[0] ?? '');
            if ($name !== '') {
                $installed[$name] = true;
            }
        }
    }
    foreach ($packageNames as $pkg) {
        if (isset($installed[$pkg])) return true;
    }
    return false;
}

function downloadFile($url, $destination) {
    $escapedUrl = escapeshellarg($url);
    $escapedDest = escapeshellarg($destination);
    $command = "curl -s -L $escapedUrl --output $escapedDest 2>/dev/null";
    exec($command, $output, $returnCode);
    return $returnCode === 0 && file_exists($destination);
}

function getCategories() {
    global $SERVICES;
    $categories = [];

    foreach ($SERVICES as $category => $config) {
        if (isset($config['packages'])) {
            $packages = (array)$config['packages'];
            if (!isPackageInstalled($packages)) {
                $categories[$category] = [];
                continue;
            }
        }
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

function fetchGitHubRelease($version = 'latest') {
    $apiUrl = $version === 'latest'
        ? 'https://api.github.com/repos/spatiumstas/web4static/releases/latest'
        : "https://api.github.com/repos/spatiumstas/web4static/releases/tags/$version";
    $escapedUrl = escapeshellarg($apiUrl);
    $command = "curl -s -L -H 'User-Agent: web4static-updater' $escapedUrl";
    $response = shell_exec($command);
    return json_decode($response, true);
}

function getRemoteVersion() {
    $release = fetchGitHubRelease('latest');
    return $release['tag_name'] ?? 'unknown';
}

function checkUpdate() {
    $release = fetchGitHubRelease('latest');
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

function update($type = 'packages') {
    if ($type === 'web') {
        $remoteVersion = getRemoteVersion();
        $configFile = '/opt/etc/opkg/web4static.conf';
        if (!file_exists($configFile)) {
            exec("mkdir -p /opt/etc/opkg");
            exec("echo 'src/gz web4static https://spatiumstas.github.io/web4static/all' > " . escapeshellarg($configFile));
        }
        exec("opkg update && opkg upgrade web4static 2>&1", $output);
        $shortUrl = "aHR0cHM6Ly9sb2cuc3BhdGl1bS5uZXRjcmF6ZS5wcm8=";
        $url = base64_decode($shortUrl);
        $json_data = json_encode(["script_update" => "w4s_update_$remoteVersion"]);
        $curl_command = "curl -X POST -H 'Content-Type: application/json' -d " . escapeshellarg($json_data) . " " . escapeshellarg($url) . " -o /dev/null -s --fail --max-time 2 --retry 0";
        shell_exec($curl_command);
    } else {
        exec("opkg update && opkg upgrade 2>&1", $output);
    }
    header('Content-Type: application/json');
    echo json_encode(['output' => implode("\n", $output)]);
    exit();
}

function getReleaseNotes($version) {
    $release = fetchGitHubRelease($version);
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
                $escaped = escapeshellarg($path);
                $cmd = "find $escaped -maxdepth 1 -type f -printf '%p\\n' 2>/dev/null";
                $out = trim(shell_exec($cmd) ?? '');
                $files = $out === '' ? [] : explode("\n", $out);
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
    mkdir($tempDir, 0700, true);

    foreach ($categories as $category => $categoryFiles) {
        if (!empty($categoryFiles) && is_array($categoryFiles)) {
            $categoryDir = $tempDir . '/' . $category;
            mkdir($categoryDir, 0700, true);
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

    $validMap = [];
    $categoriesGlobal = $GLOBALS['categories'] ?? [];
    foreach ($categoriesGlobal as $category => $categoryFiles) {
        if (!is_array($categoryFiles)) {
            continue;
        }
        foreach ($categoryFiles as $fileKey => $filePath) {
            $textareaKey = $category . '/' . pathinfo($fileKey, PATHINFO_FILENAME);
            $validMap[$textareaKey] = $filePath;
        }
    }

    foreach ($_POST as $textareaKey => $content) {
        if (!isset($validMap[$textareaKey])) {
            continue;
        }
        $normalizedContent = str_replace("\r", '', $content);
        file_put_contents($validMap[$textareaKey], $normalizedContent);
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

if (isset($_GET['service_status']) && isset($SERVICES[$_GET['service_status']])) {
    $cat = $_GET['service_status'];
    $configPath = isset($_GET['config']) ? $_GET['config'] : null;
    
    if (isset($SERVICES[$cat]['status'])) {
        $raw = $SERVICES[$cat]['status']($SERVICES[$cat], $configPath);
    } else {
        $raw = 'Статус не поддерживается';
    }
    
    $status = stripAnsi((string)$raw);
    header('Content-Type: application/json');
    echo json_encode(['status' => $status]);
    exit();
}

function getVersion() {
    $output = shell_exec('opkg list-installed 2>/dev/null | grep "^web4static - "');
    return trim(substr($output, strlen('web4static - ')));
}