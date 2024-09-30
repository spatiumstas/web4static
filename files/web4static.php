<?php

$fileRun = 'web4static/run4Static.php';
$url = 'http://192.168.1.1:88/ext-ui/addons/web4static.php';

$ipsetPath = rtrim(shell_exec("readlink /opt/etc/init.d/S03ipset-table | sed 's/scripts.*/lists/'"));
$birdPath = rtrim(shell_exec("readlink /opt/etc/init.d/S02bird-table | sed 's/scripts.*/lists/'"));

$ipsetFiles = [];
$birdFiles = [];

if (is_dir($ipsetPath)) {
    $ipsetFiles = explode("\n", trim(shell_exec("ls $ipsetPath/*.list 2>/dev/null")));
}

if (is_dir($birdPath)) {
    $birdFiles = explode("\n", trim(shell_exec("ls $birdPath/*.list 2>/dev/null")));
}

$files = [];

if (!empty($ipsetFiles)) {
    $files = array_merge(
        $files,
        array_combine(array_map(fn($file) => basename($file, '.list'), $ipsetFiles), $ipsetFiles)
    );
}

if (!empty($birdFiles)) {
    $files = array_merge(
        $files,
        array_combine(array_map(fn($file) => basename($file, '.list'), $birdFiles), $birdFiles)
    );
}

foreach ($files as $key => $file) {
    if (isset($_POST[$key])) {
        file_put_contents($file, $_POST[$key]);
        header("Location: $url");
        exit();
    }
}

$texts = array_map('file_get_contents', $files);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>web4static</title>
    <link rel="icon" href="web4static/main.png" type="image/x-icon">
    <link rel="stylesheet" href="web4static/styles.css">
    <script src="web4static/script.js" defer></script>
     <script>
         var fileRun = '<?php echo $fileRun; ?>';
     </script>
</head>
<body>

<header>
    <pre>
        <?php echo htmlspecialchars(file_get_contents('/opt/share/www/ext-ui/addons/web4static/ascii.txt')); ?>
    </pre>
</header>

<body>
    <main>
<form id="selector" action="" method="post">
    <?php foreach ($files as $key => $path): ?>
        <input type="button" onclick="showSection('<?php echo $key; ?>')" value="<?php echo basename($path); ?>"/>
    <?php endforeach; ?>
</form>

<?php foreach ($files as $key => $path): ?>
    <div id="<?php echo $key; ?>" class="form-section" style="display:none;">
        <form id="form-<?php echo $key; ?>" action="" method="post" onsubmit="return handleSaveAndRestart(this);">
            <textarea name="<?php echo $key; ?>"><?php echo htmlspecialchars($texts[$key]); ?></textarea>
            <input type="submit" value="Save & Restart" />
        </form>
    </div>
<?php endforeach; ?>
    </main>
</body>
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
<div class="footer" style="text-align: center; margin-top: 20px;">
    by <a href="https://github.com/spatiumstas" target="_blank">spatiumstas</a>
</div>
</footer>
</html>