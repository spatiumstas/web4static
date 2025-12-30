<?php
$allowedExtensions = ['list', 'json', 'conf', 'txt', 'yaml', 'sh', 'log'];
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
    'Bird4Static' => [
        'init' => '/opt/etc/init.d/S70bird',
        'path' => [
            "readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/lists/'",
            "readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/scripts/'"
        ],
        'non-package' => true,
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
    ],
    'KeenSnap' => [
        'path' => [
            "/opt/root/KeenSnap/config.sh",
            "/opt/var/log/keensnap.log"
        ],
        'non-package' => true,
        'useShell' => false,
    ],
    'HydraRoute' => [
        'path' => ['/opt/etc/HydraRoute', '/opt/etc/AdGuardHome/domain.conf'],
        'useShell' => false,
        'packages' => ['hydraroute', 'hrneo'],
        'restart' => function($self) {
            return is_dir('/opt/etc/AdGuardHome') ? ['agh restart'] : [];
        },
    ],
    'IPset4Static' => [
        'path' => [
            "readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/lists/'",
            "readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/scripts/'"
        ],
        'non-package' => true,
        'useShell' => true,
        'restart' => function($self) {
            $ipset = trim(shell_exec("readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/scripts/'"));
            return $ipset ? [escapeshellcmd("$ipset/update-ipset.sh")] : [];
        },
    ],
    'Mihomo' => [
        'init' => '/opt/etc/init.d/S99mihomo',
        'path' => '/opt/etc/mihomo',
        'useShell' => false,
        'packages' => ['mihomo'],
        'validate_config' => true,
        'restart' => function($self) {
            return is_file($self['init']) ? [$self['init'] . ' restart'] : [];
        },
        'status' => function($self, $configPath = null) {
            $out = is_file($self['init']) ? shell_exec($self['init'] . ' status 2>&1') : '';
            if ($configPath) {
                $cfg = (string)$configPath;
                $out .= "\n" . shell_exec('mihomo -t -f ' . escapeshellarg($cfg) . " 2>&1");
            }
            return trim((string)$out) !== '' ? $out : 'Нет статуса';
        }
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
    'sms2gram' => [
        'path' => [
            "/opt/root/sms2gram/config.sh",
            "/opt/var/log/sms2gram.log"
        ],
        'non-package' => true,
        'useShell' => false,
    ],
    'XKeen' => [
        'init' => 'xkeen',
        'path' => '/opt/etc/xray/configs',
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
        'status' => function($self) {
            return is_file($self['init']) ? shell_exec($self['init'] . ' status 2>&1') : 'Нет статуса';
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
        $useShell = !empty($config['useShell']);

        if (!empty($config['non-package'])) {
            $lists = getLists($config['path'], $useShell);
            if (!empty($lists)) {
                $categories[$category] = $lists;
            }
            continue;
        }

        if (isset($config['packages'])) {
            $packages = (array)$config['packages'];
            if (isPackageInstalled($packages)) {
                $categories[$category] = getLists($config['path'], $useShell);
            }
            continue;
        }
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
        json_error('Failed to fetch release info', 502);
    }

    $remoteVersion = $release['tag_name'];

    json_send([
        'local_version' => getVersion(),
        'remote_version' => $remoteVersion,
    ]);
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
    } else {
        exec("opkg update && opkg upgrade 2>&1", $output);
    }
    json_send(['output' => implode("\n", $output)]);
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

    json_send(['notes' => $notes]);
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
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
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
                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
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
    global $SERVICES;

    $changedCategories = [];
    if (isset($_POST['changed_categories'])) {
        $decoded = json_decode((string)$_POST['changed_categories'], true);
        if (is_array($decoded)) {
            $changedCategories = array_values(array_unique(array_filter($decoded, function($v) use ($SERVICES) {
                return is_string($v) && $v !== '' && isset($SERVICES[$v]);
            })));
        }
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

    $savedFiles = [];
    $failedFiles = [];

    foreach ($_POST as $textareaKey => $content) {
        if (!isset($validMap[$textareaKey])) {
            continue;
        }
        $normalizedContent = str_replace("\r", '', $content);
        $path = $validMap[$textareaKey];
        $ok = @file_put_contents($path, $normalizedContent) !== false;
        if ($ok) {
            $savedFiles[] = $textareaKey;
        } else {
            $failedFiles[] = $textareaKey;
        }
    }

    if (!empty($failedFiles)) {
        json_error('Не удалось сохранить: ' . implode(', ', $failedFiles), 500);
    }

    restartServices($changedCategories);
    json_ok(['saved' => $savedFiles]);
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
    json_send(['status' => $status]);
}

function json_send($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)
    );
    exit();
}

function json_ok($data = []): void {
    json_send(array_merge(['ok' => true], $data), 200);
}

function json_error($message, int $status = 400): void {
    json_send(['ok' => false, 'error' => (string)$message], $status);
}

function validate_file_name($name, $allowedExtensions) {
    $name = trim((string)$name);
    if ($name === '') return false;
    if ($name !== basename($name)) return false;
    if (strpos($name, '..') !== false || strpos($name, '/') !== false || strpos($name, "\\") !== false) return false;
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, $allowedExtensions, true)) return false;
    return true;
}

function resolve_writable_dir_for_category($category, $SERVICES) {
    if (!isset($SERVICES[$category])) return null;
    $paths = (array)$SERVICES[$category]['path'];
    $useShell = !empty($SERVICES[$category]['useShell']);
    foreach ($paths as $p) {
        $resolved = $useShell ? rtrim((string)shell_exec($p)) : $p;
        if ($resolved === '') continue;
        if (is_dir($resolved) && is_writable($resolved)) return $resolved;
        if (is_file($resolved)) {
            $dirName = dirname($resolved);
            if (is_dir($dirName) && is_writable($dirName)) return $dirName;
        }
    }
    return null;
}

function find_file_in_category($category, $name, $SERVICES) {
    if (!isset($SERVICES[$category])) return null;
    $fileMap = getLists($SERVICES[$category]['path'], !empty($SERVICES[$category]['useShell']));
    foreach ($fileMap as $filePath) {
        if (basename($filePath) === $name) {
            return $filePath;
        }
    }
    return null;
}

if (isset($_GET['create_file']) || (isset($_POST['create_file']) && $_POST['create_file'])) {
    $category = $_POST['category'] ?? ($_GET['category'] ?? '');
    $name = $_POST['name'] ?? ($_GET['name'] ?? '');
    $category = trim((string)$category);
    $name = trim((string)$name);

    if (!validate_file_name($name, $allowedExtensions)) json_error('Недопустимое имя или расширение файла');

    $targetDir = resolve_writable_dir_for_category($category, $SERVICES);
    if ($targetDir === null) json_error('Каталог сервиса недоступен для записи');

    $filePath = rtrim($targetDir, '/') . '/' . $name;
    if (file_exists($filePath)) json_error('Файл уже существует');

    $ok = @file_put_contents($filePath, '') !== false;
    if (!$ok) json_error('Не удалось создать файл');

    json_ok(['path' => $filePath]);
}

if (isset($_GET['delete_file']) || (isset($_POST['delete_file']) && $_POST['delete_file'])) {
    $category = $_POST['category'] ?? ($_GET['category'] ?? '');
    $name = $_POST['name'] ?? ($_GET['name'] ?? '');
    $category = trim((string)$category);
    $name = trim((string)$name);

    $path = find_file_in_category($category, $name, $SERVICES);
    if ($path === null) json_error('Файл не найден в категории');

    $ok = @unlink($path);
    if (!$ok) json_error('Не удалось удалить файл');

    json_ok();
}

function getVersion() {
    $output = shell_exec('opkg list-installed 2>/dev/null | grep "^web4static - "');
    return trim(substr($output, strlen('web4static - ')));
}