<?php
$w4s_version = '1.8.4';
$cache_buster = $w4s_version;
require_once __DIR__ . '/files/functions.php';

if (isset($_GET['check_update'])) {
    checkUpdate();
}
if (isset($_GET['update_script'])) {
    updateScript();
}
if (isset($_GET['get_release_notes']) && isset($_GET['v'])) {
    getReleaseNotes(htmlspecialchars($_GET['v']));
}
if (isset($_GET['delete_group'])) {
    $groupName = htmlspecialchars($_GET['delete_group']);
    shell_exec("/bin/ndmc -c \"no object-group fqdn $groupName\"");
    http_response_code(200);
    exit();
}
if (isset($_GET['create_group'])) {
    $groupName = htmlspecialchars($_GET['create_group']);
    shell_exec("/bin/ndmc -c \"object-group fqdn $groupName\"");
    shell_exec("/bin/ndmc -c \"opkg object-group fqdn $groupName enable\"");
    http_response_code(200);
    exit();
}

if (isset($_GET['opkg_update'])) {
    $output = [];

    exec("opkg update && opkg upgrade 2>&1", $output);
    $outputString = implode("\n", $output);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'output' => $outputString]);
    exit();
}

$categories = getCategories();

$files = [];
foreach ($categories as $category => $categoryFiles) {
    if ($category === 'object-group') {
        if (is_array($categoryFiles)) {
            foreach ($categoryFiles as $fileName => $content) {
                $files[$fileName] = $content;
            }
        }
    } else {
        if (is_array($categoryFiles)) {
            $files = array_merge($files, $categoryFiles);
        }
    }
}

$texts = [];
foreach ($files as $fileName => $data) {
    if (is_array($categories['object-group']) && array_key_exists($fileName, $categories['object-group'])) {
        $texts[$fileName] = $data;
    } else {
        $texts[$fileName] = file_get_contents($data);
    }
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
    <link rel="apple-touch-icon" href="https://raw.githubusercontent.com/spatiumstas/web4static/refs/heads/legacy/icons/apple-touch-icon.png">
    <link rel="icon" href="https://raw.githubusercontent.com/spatiumstas/web4static/legacy/icons/favicon.png">
    <link rel="stylesheet" href="files/styles.css?v=<?php echo $cache_buster; ?>">
    <link rel="manifest" href="files/manifest.json?v=<?php echo $cache_buster; ?>">
    <script src="files/script.js?v=<?php echo $cache_buster; ?>" defer></script>
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
                location.reload();
            });
        });
    </script>
</head>
<body class="dark-theme">
    <header id="asciiHeader">
        <pre>
            <?php echo htmlspecialchars(file_get_contents('files/ascii.txt')); ?>
        </pre>
    </header>
    <?php include 'files/icons.svg'; ?>
    <main>
        <form id="mainForm" action="" method="post">
            <?php foreach ($categories as $category => $categoryFiles): ?>
                <?php if ($category !== 'object-group' && !empty($categoryFiles) || $category === 'object-group' && $categoryFiles !== false): ?>
                    <input type="button" onclick="showSection('<?php echo htmlspecialchars($category); ?>')" value="<?php echo htmlspecialchars($category); ?>" />
                <?php endif; ?>
            <?php endforeach; ?>

            <?php foreach ($categories as $category => $categoryFiles): ?>
                <?php if ($category !== 'object-group' && !empty($categoryFiles) || $category === 'object-group' && $categoryFiles !== false): ?>
                    <div id="<?php echo htmlspecialchars($category); ?>" class="form-section" style="display:none;">
                        <div class="button-container">
                            <?php if (is_array($categoryFiles)): ?>
                                <?php foreach ($categoryFiles as $key => $path): ?>
                                    <div class="group-button-wrapper">
                                        <input type="button" onclick="showSubSection('<?php echo htmlspecialchars($category . '/' . pathinfo($key, PATHINFO_FILENAME)); ?>')" value="<?php echo htmlspecialchars(pathinfo($key, PATHINFO_FILENAME)); ?>" />
                                        <?php if ($category === 'object-group'): ?>
                                            <button type="button" class="delete-group-btn" onclick="deleteGroup('<?php echo htmlspecialchars(pathinfo($key, PATHINFO_FILENAME)); ?>')" aria-label="Delete group">
                                                <svg width="16" height="16"><use href="#x"/></svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if ($category === 'object-group'): ?>
                                <div class="group-button-wrapper">
                                    <button type="button" class="add-group-btn" onclick="createGroup()" aria-label="Add new group">
                                        <svg width="24" height="24"><use href="#plus"/></svg>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($category === 'object-group' && empty($categoryFiles) && $categoryFiles !== false): ?>
                            <div id="new_group" class="form-section">
                                <div class="textarea-container">
                                    <textarea name="object-group/new_group.list"></textarea>
                                </div>
                                <div class="button-container">
                                    <input type="file" id="import-new_group" style="display:none;" accept=".txt,.list,.json,.conf" onchange="importFile('new_group', this, 'object-group')">
                                    <button type="button" onclick="document.getElementById('import-new_group').click()" aria-label="Replace file" title="Replace">
                                        <svg width="24" height="24"><use href="#swap"/></svg>
                                    </button>
                                    <button type="button" onclick="exportFile('new_group', 'list', 'object-group')" aria-label="Save file" title="Save">
                                        <svg width="24" height="24"><use href="#download-file"/></svg>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (is_array($categoryFiles)): ?>
                            <?php foreach ($categoryFiles as $key => $path): ?>
                                <div id="<?php echo htmlspecialchars($category . '/' . pathinfo($key, PATHINFO_FILENAME)); ?>" class="form-section" style="display:none;">
                                    <div class="textarea-container">
                                        <textarea name="<?php echo htmlspecialchars($category . '/' . pathinfo($key, PATHINFO_FILENAME)); ?>"><?php echo htmlspecialchars($texts[$key]); ?></textarea>
                                    </div>
                                    <div class="button-container">
                                        <input type="file" id="import-<?php echo htmlspecialchars($category . '/' . pathinfo($key, PATHINFO_FILENAME)); ?>" style="display:none;" accept=".txt,.list,.json,.conf" onchange="importFile('<?php echo htmlspecialchars(pathinfo($key, PATHINFO_FILENAME)); ?>', this, '<?php echo htmlspecialchars($category); ?>')">
                                        <button type="button" onclick="document.getElementById('import-<?php echo htmlspecialchars($category . '/' . pathinfo($key, PATHINFO_FILENAME)); ?>').click()" aria-label="Replace file" title="Replace">
                                            <svg width="24" height="24"><use href="#swap"/></svg>
                                        </button>
                                        <button type="button" onclick="exportFile('<?php echo htmlspecialchars(pathinfo($key, PATHINFO_FILENAME)); ?>', '<?php echo htmlspecialchars(pathinfo($key, PATHINFO_EXTENSION)); ?>', '<?php echo htmlspecialchars($category); ?>')" aria-label="Save file" title="Save">
                                            <svg width="24" height="24"><use href="#download-file"/></svg>
                                        </button>
                                        <button type="button" class="format-json-btn" onclick="formatJson('<?php echo htmlspecialchars($category . '/' . pathinfo($key, PATHINFO_FILENAME)); ?>')" aria-label="Format JSON" title="Format JSON" style="display: none;">
                                            <svg width="24" height="24"><use href="#json"/></svg>
                                        </button>
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
</body>
</html>