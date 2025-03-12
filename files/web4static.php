<?php

$w4s_version = '1.5.6';
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

$categories = [
    'IPSET' => getLists("readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/lists/'", true),
    'BIRD' => getLists("readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/lists/'", true),
    'NFQWS' => getLists('/opt/etc/nfqws'),
    'TPWS' => getLists('/opt/etc/tpws'),
    'XKEEN' => getLists('/opt/etc/xray/configs/')
];

$files = [];
foreach ($categories as $category => $categoryFiles) {
    $files = array_merge($files, $categoryFiles);
}

$texts = array_map('file_get_contents', $files);

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
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <title>web4static</title>
    <link rel="apple-touch-icon" href="https://raw.githubusercontent.com/spatiumstas/web4static/refs/heads/main/icons/apple-touch-icon.png">
    <link rel="icon" href="https://raw.githubusercontent.com/spatiumstas/web4static/main/icons/favicon.png" sizes="48x48" type="image/x-icon">
    <link rel="icon" href="https://raw.githubusercontent.com/spatiumstas/web4static/main/icons/favicon.png" sizes="192x192">

    <link rel="stylesheet" href="files/styles.css">
    <script src="files/script.js" defer></script>
    <script>
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
    <?php include 'files/icons.svg'; ?>
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
                                <input type="button" onclick="showSubSection('<?php echo htmlspecialchars($key); ?>')" value="<?php echo htmlspecialchars(pathinfo($key, PATHINFO_FILENAME)); ?>" />
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ($categoryFiles as $key => $path): ?>
                            <div id="<?php echo htmlspecialchars($key); ?>" class="form-section" style="display:none;">
                                <div class="textarea-container">
                                    <textarea name="<?php echo htmlspecialchars(pathinfo($key, PATHINFO_FILENAME)); ?>"><?php echo htmlspecialchars($texts[$key]); ?></textarea>
                                </div>
                                <div class="button-container">
                                    <input type="file" id="import-<?php echo htmlspecialchars($key); ?>" style="display:none;" onchange="importFile('<?php echo htmlspecialchars(pathinfo($key, PATHINFO_FILENAME)); ?>', this)">
                                    <button type="button" onclick="document.getElementById('import-<?php echo htmlspecialchars($key); ?>').click()" aria-label="Replace file" title="Replace">
                                        <svg width="24" height="24"><use href="#swap"/></svg>
                                    </button>
                                    <button type="button" onclick="exportFile('<?php echo htmlspecialchars(pathinfo($key, PATHINFO_FILENAME)); ?>', '<?php echo htmlspecialchars(pathinfo($key, PATHINFO_EXTENSION)); ?>')" aria-label="Save file" title="Save">
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