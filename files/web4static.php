<?php

define('WEB4STATIC_DIR', '/opt/share/www/w4s');
define('FILES_DIR', WEB4STATIC_DIR . '/files');

$w4s_version = '1.5.2';
$config = parse_ini_file(__DIR__ . '/files/config.ini');
$baseUrl = $config['base_url'];
$url = $baseUrl . '/w4s/web4static.php';
$fileRun = 'files/run4Static.php';

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
    return array_combine(
        array_map(fn($file) => basename($file, '.' . $extension) . $suffix, $files),
        $files
    );
}

function getFilesByShell(string $shellCmd, string $extension = 'list', string $suffix = ''): array {
    $path = rtrim(shell_exec($shellCmd));
    if (is_dir($path)) {
        $files = explode("\n", trim(shell_exec("ls $path/*.$extension 2>/dev/null")));
        return array_combine(
            array_map(fn($file) => basename($file, '.' . $extension) . $suffix, $files),
            $files
        );
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $content) {
        $normalizedKey = str_replace('_conf', '.conf', $key);
        if (isset($files[$normalizedKey])) {
            $file = $files[$normalizedKey];
            if (is_link($file)) {
                $target = readlink($file);
                if ($target !== false && is_writable($target)) {
                    file_put_contents($target, $content);
                }
            } else {
                file_put_contents($file, $content);
            }
            $targetFile = is_link($file) ? $target : $file;
            shell_exec("tr -d '\r' < " . escapeshellarg($targetFile) . " > " . escapeshellarg($targetFile) . ".tmp && mv " . escapeshellarg($targetFile) . ".tmp " . escapeshellarg($targetFile));
        }
    }
    http_response_code(200);
    exit();
}

$texts = array_map('file_get_contents', $files);

if (isset($_GET['export_all'])) {
    $tempDir = sys_get_temp_dir() . '/web4static_backup_' . time();
    mkdir($tempDir, 0777, true);
    foreach ($files as $key => $path) {
        $backupFile = $tempDir . '/' . $key . '.txt';
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <title>web4static</title>
    <!-- Для iOS -->
    <link rel="apple-touch-icon" href="https://raw.githubusercontent.com/spatiumstas/web4static/refs/heads/main/files/apple-touch-icon.png">

    <!-- Для иконки в формате .ico для браузеров -->
    <link rel="icon" href="https://img.icons8.com/external-vectorslab-flat-vectorslab/48/external-Vpn-ai-security-and-security-vectorslab-flat-vectorslab-2.png" alt="external-Vpn-ai-security-and-security-vectorslab-flat-vectorslab-2" sizes="48x48" type="image/x-icon">

    <!-- Для Android и других платформ -->
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
    <svg style="display: none;">
    <svg style="display: none;">
        <symbol xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none" id="download-file">
            <path d="M11.727 9.2a.8.8 0 0 0-1.054-1.204L8.8 9.635V1.798a.8.8 0 1 0-1.6 0v7.837l-1.873-1.64A.8.8 0 1 0 4.273 9.2l3.2 2.8a.8.8 0 0 0 1.054 0l3.2-2.8ZM1.6 11.398a.8.8 0 0 0-1.6 0v2.4a1.6 1.6 0 0 0 1.6 1.6h12.8a1.6 1.6 0 0 0 1.6-1.6v-2.4a.8.8 0 0 0-1.6 0v2.4H1.6v-2.4Z" fill="currentColor"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 16 16" id="swap">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M3.513 8.228a.8.8 0 1 1 1.119 1.144L2.762 11.2H15.2a.8.8 0 0 1 0 1.6H2.762l1.87 1.828a.8.8 0 0 1-1.119 1.144l-3.272-3.2a.8.8 0 0 1 0-1.144l3.272-3.2Zm8.974-8a.8.8 0 0 0-1.119 1.144l1.87 1.828H.8a.8.8 0 1 0 0 1.6h12.438l-1.87 1.828a.8.8 0 0 0 1.119 1.144l3.272-3.2a.8.8 0 0 0 0-1.144l-3.272-3.2Z" fill="currentColor"/>
        </symbol>
    </svg>
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
        <svg id="sun-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="5"></circle>
            <line x1="12" y1="1" x2="12" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="23"></line>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
            <line x1="1" y1="12" x2="3" y2="12"></line>
            <line x1="21" y1="12" x2="23" y2="12"></line>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
        </svg>
        <svg id="moon-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
            <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path>
        </svg>
    </button>
    <button type="button" onclick="exportAllFiles()" aria-label="Save all lists" title="Save all lists">
        <svg width="24" height="24"><use href="#download-file"/></svg>
    </button>
    <a href="https://github.com/spatiumstas/web4static" target="_blank">
        <svg id="github-light" class="github-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0,0,256,256">
            <g fill="var(--primary-color)" fill-rule="nonzero">
                <g transform="scale(5.12,5.12)">
                    <path d="M25,2c-12.68866,0 -23,10.31134 -23,23c0,12.68867 10.31134,23 23,23c12.68867,0 23,-10.31133 23,-23c0,-12.68866 -10.31133,-23 -23,-23zM25,4c11.60734,0 21,9.39266 21,21c0,0.07137 -0.00515,0.14169 -0.00586,0.21289c-0.63961,-0.05904 -1.37863,-0.11511 -2.31836,-0.14844c-1.32872,-0.04712 -3.00352,-0.03347 -4.90234,0.06055c0.07041,-0.49035 0.11977,-0.98762 0.12109,-1.49805c0.09683,-1.87262 -0.53201,-3.62449 -1.55469,-5.17187c0.24707,-0.85373 0.5369,-1.93986 0.60938,-3.17187c0.0826,-1.40428 -0.03862,-2.96144 -1.16602,-4.01367l-0.28906,-0.26953h-0.39453c-2.68307,0 -4.51959,1.12321 -5.64258,2.01367c-1.6215,-0.62765 -3.44481,-1.01367 -5.45703,-1.01367c-2.02363,0 -3.86447,0.39142 -5.55273,1.01758c-1.12235,-0.89089 -2.96048,-2.01758 -5.64648,-2.01758h-0.39258l-0.28906,0.26758c-1.09819,1.01974 -1.19936,2.53418 -1.13086,3.93164c0.06041,1.23229 0.33345,2.35292 0.57617,3.24805c-1.03996,1.57759 -1.66406,3.37475 -1.66406,5.15234c0,0.51163 0.04758,1.01046 0.11719,1.50195c-1.80576,-0.08375 -3.41548,-0.09989 -4.69336,-0.05469c-0.9397,0.03324 -1.67878,0.08884 -2.31836,0.14844c-0.0006,-0.06534 -0.00586,-0.12983 -0.00586,-0.19531c0,-11.60733 9.39266,-21 21,-21zM14.39648,13.13086c2.01758,0.19118 3.53551,1.09211 4.23828,1.7168l0.46875,0.41406l0.57813,-0.23633c1.58145,-0.65119 3.34534,-1.02539 5.31836,-1.02539c1.97302,0 3.73739,0.37608 5.19922,1.01563l0.58594,0.25781l0.47852,-0.42578c0.70301,-0.6249 2.22351,-1.5261 4.24219,-1.7168c0.2684,0.44498 0.50163,1.07781 0.44531,2.03516c-0.0674,1.14572 -0.37387,2.39364 -0.60547,3.13477l-0.15039,0.48242l0.29883,0.4082c0.98947,1.34928 1.49398,2.80953 1.4082,4.35352l-0.00195,0.02734v0.02734c0,2.49545 -0.89861,4.49273 -2.8125,5.97266c-1.91384,1.47993 -4.93523,2.42773 -9.1875,2.42773c-4.25227,0 -7.27556,-0.94781 -9.18945,-2.42773c-1.91389,-1.47993 -2.81055,-3.4772 -2.81055,-5.97266c0,-1.46471 0.52892,-3.07601 1.50586,-4.4082l0.29297,-0.39844l-0.14062,-0.47461c-0.23173,-0.78787 -0.51577,-2.06426 -0.57227,-3.2168c-0.04699,-0.95862 0.17989,-1.56453 0.41016,-1.9707zM8.88477,26.02148c0.70669,0.00903 1.51669,0.04717 2.32813,0.08789c0.07753,0.3118 0.16593,0.61852 0.27344,0.91797c-3.30736,0.06975 -5.7816,0.40233 -7.30664,0.6875c-0.06448,-0.50035 -0.11584,-1.00482 -0.14453,-1.51562c1.12705,-0.10696 2.71584,-0.20499 4.84961,-0.17773zM41.11523,26.03711c2.13229,-0.02708 3.72049,0.07105 4.84766,0.17773c-0.02866,0.50348 -0.07914,1.00082 -0.14258,1.49414c-1.57954,-0.28977 -4.12064,-0.6223 -7.51367,-0.67578c0.1053,-0.29353 0.19299,-0.59498 0.26953,-0.90039c0.89512,-0.04798 1.76839,-0.08592 2.53906,-0.0957zM11.91211,28.01953c0.59674,1.1958 1.44941,2.26349 2.57617,3.13477c1.54054,1.19123 3.54334,2.02354 5.98828,2.46875c-0.31986,0.32865 -0.61078,0.68955 -0.86914,1.07031l-0.06055,-0.05273c0.0055,-0.0063 -0.49713,0.24525 -1.24805,0.3125c-0.75092,0.06725 -1.67721,0.04688 -2.49805,0.04688c-1.225,0 -1.76457,-0.57879 -2.62695,-1.63281c-0.47755,-0.64363 -1.05973,-1.16486 -1.625,-1.56055c-0.57881,-0.40517 -1.07257,-0.69113 -1.68359,-0.79297l-0.08203,-0.01367h-0.08398c-0.46667,0 -0.91824,0.03379 -1.33984,0.51563c-0.2108,0.24092 -0.3561,0.68694 -0.26172,1.06445c0.09438,0.37752 0.3332,0.6095 0.54688,0.75195c1.36672,0.91115 1.60826,2.71448 2.46484,4.31836c0.79992,1.59392 2.52629,2.34961 4.29102,2.34961h2.59961v4.80273c-7.03219,-2.4822 -12.33532,-8.59812 -13.66797,-16.09961c1.5309,-0.28835 4.09451,-0.63502 7.58008,-0.68359zM37.88281,28.02734c3.56273,0.0305 6.19824,0.37672 7.78711,0.66992c-1.33087,7.50424 -6.63585,13.6226 -13.66992,16.10547v-5.20312c0,-1.58457 -0.52036,-3.3319 -1.42578,-4.78906c-0.2749,-0.44241 -0.59827,-0.86081 -0.95898,-1.23633c2.31522,-0.45738 4.21713,-1.2754 5.69727,-2.41992c1.12432,-0.86939 1.97509,-1.93387 2.57031,-3.12695zM23.69922,34.09961h2.80078c0.81282,0 1.68042,0.64779 2.375,1.76563c0.69458,1.11783 1.125,2.61894 1.125,3.73438v5.79883c-1.60259,0.3908 -3.27621,0.60156 -5,0.60156c-1.72379,0 -3.39741,-0.21077 -5,-0.60156v-5.79883c0,-1.09074 0.46783,-2.5883 1.20898,-3.71094c0.74116,-1.12264 1.67741,-1.78906 2.49023,-1.78906zM12.30859,35.28125c0.86577,0.89801 1.91393,1.71875 3.49219,1.71875c0.77917,0 1.7517,0.02807 2.67578,-0.05469c0.00329,-0.00029 0.00648,-0.00166 0.00977,-0.00195c-0.12175,0.35 -0.21258,0.70217 -0.28906,1.05664h-2.79687c-1.23333,0 -2.10462,-0.44557 -2.50586,-1.24805l-0.00781,-0.01367l-0.00586,-0.01172c-0.1644,-0.30537 -0.38021,-0.91597 -0.57226,-1.44531z"></path>
                </g>
            </g>
        </svg>
        <svg id="github-dark" class="github-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0,0,256,256">
            <g fill="var(--primary-color)" fill-rule="nonzero">
                <g transform="scale(5.12,5.12)">
                    <path d="M17.791,46.836c0.711,-0.306 1.209,-1.013 1.209,-1.836v-5.4c0,-0.197 0.016,-0.402 0.041,-0.61c-0.014,0.004 -0.027,0.007 -0.041,0.01c0,0 -3,0 -3.6,0c-1.5,0 -2.8,-0.6 -3.4,-1.8c-0.7,-1.3 -1,-3.5 -2.8,-4.7c-0.3,-0.2 -0.1,-0.5 0.5,-0.5c0.6,0.1 1.9,0.9 2.7,2c0.9,1.1 1.8,2 3.4,2c2.487,0 3.82,-0.125 4.622,-0.555c0.934,-1.389 2.227,-2.445 3.578,-2.445v-0.025c-5.668,-0.182 -9.289,-2.066 -10.975,-4.975c-3.665,0.042 -6.856,0.405 -8.677,0.707c-0.058,-0.327 -0.108,-0.656 -0.151,-0.987c1.797,-0.296 4.843,-0.647 8.345,-0.714c-0.112,-0.276 -0.209,-0.559 -0.291,-0.849c-3.511,-0.178 -6.541,-0.039 -8.187,0.097c-0.02,-0.332 -0.047,-0.663 -0.051,-0.999c1.649,-0.135 4.597,-0.27 8.018,-0.111c-0.079,-0.5 -0.13,-1.011 -0.13,-1.543c0,-1.7 0.6,-3.5 1.7,-5c-0.5,-1.7 -1.2,-5.3 0.2,-6.6c2.7,0 4.6,1.3 5.5,2.1c1.699,-0.701 3.599,-1.101 5.699,-1.101c2.1,0 4,0.4 5.6,1.1c0.9,-0.8 2.8,-2.1 5.5,-2.1c1.5,1.4 0.7,5 0.2,6.6c1.1,1.5 1.7,3.2 1.6,5c0,0.484 -0.045,0.951 -0.11,1.409c3.499,-0.172 6.527,-0.034 8.204,0.102c-0.002,0.337 -0.033,0.666 -0.051,0.999c-1.671,-0.138 -4.775,-0.28 -8.359,-0.089c-0.089,0.336 -0.197,0.663 -0.325,0.98c3.546,0.046 6.665,0.389 8.548,0.689c-0.043,0.332 -0.093,0.661 -0.151,0.987c-1.912,-0.306 -5.171,-0.664 -8.879,-0.682c-1.665,2.878 -5.22,4.755 -10.777,4.974v0.031c2.6,0 5,3.9 5,6.6v5.4c0,0.823 0.498,1.53 1.209,1.836c9.161,-3.032 15.791,-11.672 15.791,-21.836c0,-12.682 -10.317,-23 -23,-23c-12.683,0 -23,10.318 -23,23c0,10.164 6.63,18.804 15.791,21.836z"></path>
                </g>
            </g>
        </svg>
    </a>
    <div id="loader" style="display: none;">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
        </svg>
    </div>
</footer>
</html>