<?php

$url = 'http://192.168.1.1:88/ext-ui/addons/editlist.php';
$files = [
    'vpn-text' => '/opt/root/IPset4Static/lists/user-ipset-vpn.list',
    'vpn-text1' => '/opt/root/IPset4Static/lists/user-vpn1.list',
    'vpn-text2' => '/opt/root/IPset4Static/lists/user-vpn2.list',
    'isp-text' => '/opt/root/IPset4Static/lists/user-ipset-isp.list'
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
            line-height: 1;
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
          font-size: max(0.7rem, 1.7vmin) !important;
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
        function showSection(sectionId) {
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(sectionId).classList.add('active');
        }

        function animateSave(button) {
            const originalText = button.value;
            button.value = 'Saving...';
            button.disabled = true;
            button.classList.add('loading');
            setTimeout(() => {
                button.value = originalText;
                button.disabled = false;
                button.classList.remove('loading');
            }, 2000);
        }

        function handleFormSubmit(form) {
            const button = form.querySelector('input[type="submit"]');
            animateSave(button);
            setTimeout(() => form.submit(), 2000);
            return false;
        }

        function animateRestart(button) {
            const originalText = button.value;
            button.value = 'Restarting...';
            button.disabled = true;
            button.classList.add('loading');
            setTimeout(() => {
                button.value = originalText;
                button.disabled = false;
            }, 2000);
        }

        function handleRestart(form) {
            const button = form.querySelector('input[type="submit"]');
            animateRestart(button);
            setTimeout(() => form.submit(), 2000);
            return false;
        }
    </script>
</head>
<body>

<header>
    <pre>
               _     _  _       _        _   _      
 __      _____| |__ | || |  ___| |_ __ _| |_(_) ___ 
 \ \ /\ / / _ \ '_ \| || |_/ __| __/ _` | __| |/ __|
  \ V  V /  __/ |_) |__   _\__ \ || (_| | |_| | (__ 
   \_/\_/ \___|_.__/   |_| |___/\__\__,_|\__|_|\___|
                                                    
    </pre>
</header>

<form id="selector" action="" method="post">
    <input type="button" onclick="showSection('uservpn')" value="user-vpn.list"/>
    <input type="button" onclick="showSection('uservpn1')" value="user-vpn1.list"/>
    <input type="button" onclick="showSection('uservpn2')" value="user-vpn2.list"/>
    <input type="button" onclick="showSection('userisp')" value="user-isp.list"/>
</form>

<div id="uservpn" class="form-section">
    <form action="" method="post" onsubmit="return handleFormSubmit(this);">
        <legend>VPN list</legend>
        <textarea name="vpn-text"><?php echo htmlspecialchars($texts['vpn-text']); ?></textarea>
        <input type="submit" value="Save & Close"/>
    </form>
</div>

<div id="uservpn1" class="form-section">
    <form action="" method="post" onsubmit="return handleFormSubmit(this);">
        <legend>VPN1 list</legend>
        <textarea name="vpn-text1"><?php echo htmlspecialchars($texts['vpn-text1']); ?></textarea>
        <input type="submit" value="Save & Close"/>
    </form>
</div>

<div id="uservpn2" class="form-section">
    <form action="" method="post" onsubmit="return handleFormSubmit(this);">
        <legend>VPN2 list</legend>
        <textarea name="vpn-text2"><?php echo htmlspecialchars($texts['vpn-text2']); ?></textarea>
        <input type="submit" value="Save & Close"/>
    </form>
</div>

<div id="userisp" class="form-section">
    <form action="" method="post" onsubmit="return handleFormSubmit(this);">
        <legend>ISP list</legend>
        <textarea name="isp-text"><?php echo htmlspecialchars($texts['isp-text']); ?></textarea>
        <input type="submit" value="Save & Close"/>
    </form>
</div>

<form action="runIPset4Static.php" onsubmit="return handleRestart(this);">
    <input type="submit" value="Restart service">
</form>

<div class="footer" style="text-align: center; margin-top: 20px;">
    by <a href="https://t.me/spatiumstas" target="_blank">@spatiumstas</a>
</div>

</body>
</html>
