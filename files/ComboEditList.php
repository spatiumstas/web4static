<?php
$fileRun = 'runCombo4Static.php';
$url = 'http://192.168.1.1:88/ext-ui/addons/editlist.php';
$files = [
    'vpn-text' => '/opt/root/Bird4Static/IPset4Static/lists/user-ipset-vpn.list',
    'vpn-text1' => '/opt/root/Bird4Static/IPset4Static/lists/user-ipset-vpn1.list',
    'vpn-text2' => '/opt/root/Bird4Static/IPset4Static/lists/user-ipset-vpn1.list',
    'isp-text' => '/opt/root/Bird4Static/IPset4Static/lists/user-ipset-isp.list'
];

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
    <title>Configuration</title>
    <style>
        html, body {
            overflow-x: hidden;
            overflow-y: auto;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f4f4f4;
            border-radius: 8px;
            grid-template-rows: minmax(3.5rem, 15vmin) 1fr 6rem;
        }
        .header {
            text-align: center;
            color: #333;
            white-space: pre;
            margin: 0;
            line-height: 1.2;
            padding: 20px 0;
        }
        .subtitle {
            display: inline-block;
            font-size: 16px;
            margin-left: 10px;
        }
        .form-section { display: none; }
        .form-section.active { display: block; }
        textarea {
            width: 100%;
            height: 200px;
            resize: none;
            overflow: auto;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        input[type="submit"], input[type="reset"], input[type="button"] {
            margin: 10px 0;
            padding: 10px 15px;
            border-radius: 4px;
            border: none;
            background-color: #007BFF;
            color: white;
            cursor: pointer;
            position: relative;
            transition: background-color 0.3s;
        }
        input[type="submit"]:hover, input[type="reset"]:hover, input[type="button"]:hover {
            background-color: #0056b3;
        }
        .loading {
            opacity: 0.5;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: #555;
        }
        .footer a {
            color: #007BFF;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        header pre {
          display: grid;
          font-size: max(0.6rem, 1.9vmin) !important;
          justify-content: center;
          align-content: center;
          text-align: center;
          font-size: 70%;
        }

        @media (max-width: 600px) {
            pre {
                font-size: 12px;
            }
        }
    </style>
    <script>
        const fileRun = <?php echo json_encode($fileRun); ?>;

        function showSection(sectionId) {
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(sectionId).classList.add('active');
        }
        function handleSaveAndRestart(form) {
            const button = form.querySelector('input[type="submit"]');
            animateSave(button);
            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    setTimeout(() => {
                        fetch(fileRun, {
                            method: 'POST'
                        }).then(res => {
                            if (res.ok) {
                            }
                            button.disabled = false;
                            button.value = 'Save & Restart';
                            button.classList.remove('loading');
                        });
                    }, 1000);
                } else {
                    button.disabled = false;
                    button.value = 'Save & Restart';
                    button.classList.remove('loading');
                }
            }).catch(err => {
                button.disabled = false;
                button.value = 'Save & Restart';
                button.classList.remove('loading');
                console.error('Ошибка:', err);
            });

            return false;
        }
        function animateSave(button) {
            const originalText = button.value;
            button.value = 'Saving...';
            button.disabled = true;
            button.classList.add('loading');
        }
    </script>
</head>
<body>

<header>
    <pre>
                __    __ __       __        __  _
 _      _____  / /_  / // / _____/ /_____ _/ /_(_)____
| | /| / / _ \/ __ \/ // /_/ ___/ __/ __ `/ __/ / ___/
| |/ |/ /  __/ /_/ /__  __(__  ) /_/ /_/ / /_/ / /__
|__/|__/\___/_.___/  /_/ /____/\__/\__,_/\__/_/\___/

    </pre>
</header>

<form id="selector" action="" method="post">
    <input type="button" onclick="showSection('uservpn')" value="user-ipset-vpn.list"/>
    <input type="button" onclick="showSection('uservpn1')" value="user-ipset-vpn1.list"/>
    <input type="button" onclick="showSection('uservpn2')" value="user-ipset-vpn1.list"/>
    <input type="button" onclick="showSection('userisp')" value="user-ipset-isp.list"/>
</form>

<div id="uservpn" class="form-section">
    <form id="form-vpn" action="" method="post" onsubmit="return handleSaveAndRestart(this);">
        <legend>VPN list</legend>
        <textarea name="vpn-text"><?php echo htmlspecialchars($texts['vpn-text']); ?></textarea>
        <input type="submit" value="Save & Restart" />
    </form>
</div>

<div id="uservpn1" class="form-section">
    <form id="form-vpn1" action="" method="post" onsubmit="return handleSaveAndRestart(this);">
        <legend>VPN1 list</legend>
        <textarea name="vpn-text1"><?php echo htmlspecialchars($texts['vpn-text1']); ?></textarea>
        <input type="submit" value="Save & Restart" />
    </form>
</div>

<div id="uservpn2" class="form-section">
    <form id="form-vpn2" action="" method="post" onsubmit="return handleSaveAndRestart(this);">
        <legend>VPN2 list</legend>
        <textarea name="vpn-text2"><?php echo htmlspecialchars($texts['vpn-text2']); ?></textarea>
        <input type="submit" value="Save & Restart" />
    </form>
</div>

<div id="userisp" class="form-section">
    <form id="form-isp" action="" method="post" onsubmit="return handleSaveAndRestart(this);">
        <legend>ISP list</legend>
        <textarea name="isp-text"><?php echo htmlspecialchars($texts['isp-text']); ?></textarea>
        <input type="submit" value="Save & Restart" />
    </form>
</div>
<div class="footer" style="text-align: center; margin-top: 20px;">
    by <a href="https://github.com/spatiumstas" target="_blank">spatiumstas</a>
</div>

</body>
</html>