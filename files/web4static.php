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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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


<form id="selector" action="" method="post">
    <?php foreach ($files as $key => $path): ?>
        <input type="button" onclick="showSection('<?php echo $key; ?>')" value="<?php echo basename($path); ?>"/>
    <?php endforeach; ?>
</form>

<?php foreach ($files as $key => $path): ?>
    <div id="<?php echo $key; ?>" class="form-section" style="display:none;">
        <form id="form-<?php echo $key; ?>" action="" method="post" onsubmit="return handleSaveAndRestart(this);">
            <legend><?php echo ucfirst(str_replace('-', ' ', $key)); ?> list</legend>
            <textarea name="<?php echo $key; ?>"><?php echo htmlspecialchars($texts[$key]); ?></textarea>
            <input type="submit" value="Save & Restart" />
        </form>
    </div>
<?php endforeach; ?>

<div class="footer" style="text-align: center; margin-top: 20px;">
    by <a href="https://github.com/spatiumstas" target="_blank">spatiumstas</a>
</div>

</body>
</html>