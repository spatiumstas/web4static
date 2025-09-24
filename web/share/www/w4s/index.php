<?php
require_once __DIR__ . '/functions.php';
$cache_buster = getVersion();
enforceBasicAuth();

if (isset($_GET['check_update'])) {
    checkUpdate();
}
if (isset($_GET['update'])) {
    $type = $_GET['type'] ?? 'packages';
    update($type);
}
if (isset($_GET['get_release_notes']) && isset($_GET['v'])) {
    getReleaseNotes(htmlspecialchars($_GET['v']));
}

$categories = getCategories();

$serviceStatusSupport = [];
foreach (array_keys($categories) as $cat) {
    global $SERVICES;
    $serviceStatusSupport[$cat] = isset($SERVICES[$cat]['status']);
}

$files = [];
foreach ($categories as $category => $categoryFiles) {
    if (is_array($categoryFiles)) {
        $files = array_merge($files, $categoryFiles);
    }
}

$texts = [];
foreach ($files as $fileName => $data) {
    $texts[$fileName] = file_get_contents($data);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($files);
}

if (isset($_GET['export_all'])) {
    exportAllFiles($categories);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta name="theme-color" content="#fff">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>web4static</title>
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="icon" href="favicon.png">
    <link rel="stylesheet" href="styles.css?v=<?php echo $cache_buster; ?>">
    <link rel="manifest" href="manifest.json?v=<?php echo $cache_buster; ?>">
    <script src="script.js?v=<?php echo $cache_buster; ?>" defer></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (window.navigator.standalone === true) {
                document.body.classList.add("pwa-mode");
            }
        document.querySelectorAll('textarea').forEach(textarea => {
                const fileKey = textarea.name;
                const formatButton = document.querySelector(`.format-json-btn[onclick="formatJson('${fileKey}')"]`);
                if (formatButton) {
                    toggleJsonButton(textarea, formatButton);
                }
            });
            document.addEventListener('input', (e) => {
                if (e.target.tagName === 'TEXTAREA') {
                    const fileKey = e.target.name;
                    const formatButton = document.querySelector(`.format-json-btn[onclick="formatJson('${fileKey}')"]`);
                    if (formatButton) {
                        toggleJsonButton(e.target, formatButton);
                    }
                }
            });
            applySavedTheme();
            restoreTextareaSizes();
            setupTextareaResizeListeners();
            checkForUpdates();
            const header = document.getElementById("asciiHeader");
            header.addEventListener("click", function() {
                try { localStorage.clear(); } catch (e) {}
                location.reload();
            });
            window.getServiceStatus = getServiceStatus;
        });
    </script>
</head>
<body class="dark-theme">
    <header id="asciiHeader">
        <pre>
                __    __ __       __        __  _     
 _      _____  / /_  / // / _____/ /_____ _/ /_(_)____
| | /| / / _ \/ __ \/ // /_/ ___/ __/ __ `/ __/ / ___/
| |/ |/ /  __/ /_/ /__  __(__  ) /_/ /_/ / /_/ / /__  
|__/|__/\___/_.___/  /_/ /____/\__/\__,_/\__/_/\___/  
                                                      
        </pre>
    </header>
    <?php include 'icons.svg'; ?>
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
                            <?php if (is_array($categoryFiles)): ?>
                                <?php foreach ($categoryFiles as $key => $path): ?>
                                    <div class="group-button-wrapper">
                                        <input type="button" onclick="showSubSection('<?php echo htmlspecialchars($category . '/' . pathinfo($key, PATHINFO_FILENAME)); ?>')" value="<?php echo htmlspecialchars(pathinfo($key, PATHINFO_FILENAME)); ?>" />
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (is_array($categoryFiles)): ?>
                            <?php foreach ($categoryFiles as $key => $path): ?>
                                <div id="<?php echo htmlspecialchars($category . '/' . pathinfo($key, PATHINFO_FILENAME)); ?>" class="form-section" style="display:none;">
                                    <div class="textarea-container">
                                        <textarea name="<?php echo htmlspecialchars($category . '/' . pathinfo($key, PATHINFO_FILENAME)); ?>"><?php echo htmlspecialchars($texts[$key]); ?></textarea>
                                    </div>
                                    <div class="button-container">
                                        <input type="file" id="import-<?php echo htmlspecialchars($category . '/' . pathinfo($key, PATHINFO_FILENAME)); ?>" style="display:none;" accept=".list,.json,.conf,.txt,.yaml,.sh" onchange="importFile('<?php echo htmlspecialchars(pathinfo($key, PATHINFO_FILENAME)); ?>', this, '<?php echo htmlspecialchars($category); ?>')">
                                        <button type="button" onclick="document.getElementById('import-<?php echo htmlspecialchars($category . '/' . pathinfo($key, PATHINFO_FILENAME)); ?>').click()" aria-label="Replace file" title="Заменить">
                                            <svg width="24" height="24"><use href="#swap"/></svg>
                                        </button>
                                        <button type="button" onclick="exportFile('<?php echo htmlspecialchars(pathinfo($key, PATHINFO_FILENAME)); ?>', '<?php echo htmlspecialchars(pathinfo($key, PATHINFO_EXTENSION)); ?>', '<?php echo htmlspecialchars($category); ?>')" aria-label="Save file" title="Сохранить">
                                            <svg width="24" height="24"><use href="#download-file"/></svg>
                                        </button>
                                        <button type="button" class="format-json-btn" onclick="formatJson('<?php echo htmlspecialchars($category . '/' . pathinfo($key, PATHINFO_FILENAME)); ?>')" aria-label="Format JSON" title="Форматировать JSON" style="display: none;">
                                            <svg width="24" height="24"><use href="#json"/></svg>
                                        </button>
                                        <?php if (!empty($serviceStatusSupport[$category])): ?>
                                        <button type="button" class="status-btn" onclick="getServiceStatus('<?php echo htmlspecialchars($category); ?>')" aria-label="Service status" title="Статус сервиса">
                                            <svg width="24" height="24"><use href="#status"/></svg>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="button-container">
                <input type="submit" value="Save & Restart" />
            </div>
        </form>
    </main>

    <footer class="dark-theme">
        <button onclick="toggleTheme()" id="theme-toggle" aria-label="Toggle Dark Mode" title="Сменить тему">
            <svg id="sun-icon" width="24" height="24"><use href="#sun"/></svg>
            <svg id="moon-icon" width="24" height="24" style="display:none;"><use href="#moon"/></svg>
        </button>
        <button type="button" onclick="exportAllFiles()" aria-label="Save all lists" title="Сохранить все списки">
            <svg width="24" height="24"><use href="#download-file"/></svg>
        </button>
        <a href="https://github.com/spatiumstas/web4static" target="_blank">
            <svg id="github-light-icon" class="github-icon" width="24" height="24"><use href="#github-light"/></svg>
            <svg id="github-dark-icon" class="github-icon" width="24" height="24"><use href="#github-dark"/></svg>
        </a>
        <button id="opkg-icon" onclick="opkgUpdate()" aria-label="Update opkg" title="Обновить OPKG пакеты">
            <svg width="24" height="24"><use href="#opkg"/></svg>
        </button>
        <div id="update-w4s-panel" style="display: none;">
            <span>Доступно обновление</span>
            <div class="progress-bar" style="display: none;"></div>
        </div>
    </footer>
    <div class="pwa-safe-area"></div>
    <div id="output-modal" class="output-modal">
        <div class="output-modal-content">
            <div class="output-modal-header">
                <h3>Журнал</h3>
                <button class="close-modal-btn">&times;</button>
            </div>
            <pre id="output-modal-text" class="output-modal-text"></pre>
        </div>
    </div>
</body>
</html>
