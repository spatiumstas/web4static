function toggleTheme() {
    document.body.classList.toggle('dark-theme');

    const footer = document.querySelector('footer');
    footer.classList.toggle('dark-theme');

    if (document.body.classList.contains('dark-theme')) {
        localStorage.setItem('theme', 'dark');
    } else {
        localStorage.setItem('theme', 'light');
    }

    updateIconDisplay();
}

function updateIconDisplay() {
    const sunIcon = document.getElementById('sun-icon');
    const moonIcon = document.getElementById('moon-icon');

    if (document.body.classList.contains('dark-theme')) {
        sunIcon.style.display = 'none';
        moonIcon.style.display = 'inline';
    } else {
        sunIcon.style.display = 'inline';
        moonIcon.style.display = 'none';
    }
}

function applySavedTheme() {
    const savedTheme = localStorage.getItem('theme');
    const footer = document.querySelector('footer');

    if (!savedTheme) {
        document.body.classList.add('dark-theme');
        footer.classList.add('dark-theme');
        localStorage.setItem('theme', 'dark');
    } else if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
        footer.classList.add('dark-theme');
    } else {
        document.body.classList.remove('dark-theme');
        footer.classList.remove('dark-theme');
    }

    updateIconDisplay();
}

function detectSystemTheme() {
    const prefersDarkScheme = window.matchMedia("(prefers-color-scheme: dark)");

    if (prefersDarkScheme.matches) {
        document.body.classList.add('dark-theme');
        document.querySelector('footer').classList.add('dark-theme');
    }

    updateIconDisplay();
}

window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", (e) => {
    const footer = document.querySelector('footer');
    if (e.matches) {
        document.body.classList.add('dark-theme');
        footer.classList.add('dark-theme');
        localStorage.setItem('theme', 'dark');
    } else {
        document.body.classList.remove('dark-theme');
        footer.classList.remove('dark-theme');
        localStorage.setItem('theme', 'light');
    }

    updateIconDisplay();
});

applySavedTheme();

function showSection(section) {
    const sections = document.getElementsByClassName('form-section');
    Array.from(sections).forEach(sec => {
        sec.style.display = 'none';
        const subsections = sec.querySelectorAll('.form-section');
        subsections.forEach(sub => sub.style.display = 'none');
    });

    const buttons = document.querySelectorAll('input[type="button"]');
    buttons.forEach(button => {
        button.classList.remove('button-active');
    });

    const activeButton = Array.from(buttons).find(button => button.value === section);
    if (activeButton) {
        activeButton.classList.add('button-active');
    }

    const sectionElement = document.getElementById(section);
    if (sectionElement) {
        sectionElement.style.display = 'block';
    }
}

document.getElementById('mainForm').addEventListener('submit', function (event) {
    event.preventDefault();

    const button = this.querySelector('input[type="submit"]');

    console.log('Форма отправлена, сохраняю...');
    animateSave(button, 'saving');

    const formData = new FormData(this);

    fetch(this.action, {
        method: 'POST',
        body: formData
    }).then(response => {
        console.log('Ответ от сервера получен:', response);

        if (response.ok) {
            console.log('Данные успешно сохранены, перезапуск сервиса...');

            setTimeout(() => {
                animateSave(button, 'restarting');

                setTimeout(() => {
                    fetch(fileRun, {
                        method: 'POST'
                    }).then(res => {
                        console.log('Ответ от сервиса:', res);

                        if (res.ok) {
                            console.log("Перезапуск выполнен успешно");
                        } else {
                            console.error('Ошибка при перезапуске сервиса');
                        }

                        button.disabled = false;
                        button.value = 'Save & Restart';
                        button.classList.remove('loading');
                    }).catch(err => {
                        console.error('Ошибка при обращении к fileRun:', err);

                        button.disabled = false;
                        button.value = 'Save & Restart';
                        button.classList.remove('loading');
                    });
                });
            }, 1000);
        } else {
            console.error('Ошибка при сохранении данных на сервере');
            button.disabled = false;
            button.value = 'Save & Restart';
            button.classList.remove('loading');
        }
    }).catch(err => {
        console.error('Ошибка при отправке данных на сервер:', err);
        button.disabled = false;
        button.value = 'Save & Restart';
        button.classList.remove('loading');
    });

    return false;
});

function animateSave(button, state) {
    if (state === 'saving') {
        button.value = 'Saving...';
        button.disabled = true;
        button.classList.add('loading');
    } else if (state === 'restarting') {
        button.value = 'Restarting...';
    }
}

function exportFile(fileKey) {
    const textarea = document.querySelector(`textarea[name="${fileKey}"]`);
    const content = textarea.value;
    const blob = new Blob([content], {type: 'text/plain'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${fileKey}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function importFile(fileKey, input) {
    const file = input.files[0];
    if (file && confirm(`Перезаписать ${fileKey} загруженным файлом?`)) {
        const reader = new FileReader();
        reader.onload = function (e) {
            const textarea = document.querySelector(`textarea[name="${fileKey}"]`);
            textarea.value = e.target.result;
            input.value = '';
        };
        reader.readAsText(file);
    }
}

function exportAllFiles() {
    const a = document.createElement('a');
    a.href = window.location.pathname + '?export_all=1';
    a.download = 'w4s_backup.tar.gz';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function showSubSection(section) {
    const subsections = document.querySelectorAll('.form-section .form-section');
    subsections.forEach(sub => {
        sub.style.display = 'none';
    });

    const buttons = document.querySelectorAll('.form-section input[type="button"]');
    buttons.forEach(button => {
        button.classList.remove('button-active');
    });

    const activeButton = Array.from(buttons).find(button => button.value === section);
    if (activeButton) {
        activeButton.classList.add('button-active');
    }

    const sectionElement = document.getElementById(section);
    if (sectionElement) {
        sectionElement.style.display = 'block';
    }
}

function checkForUpdates() {
    fetch('web4static.php?check_update')
        .then(response => response.json())
        .then(data => {
            console.log('Check update response:', data);
            const currentNum = versionToNumber(data.current_version);
            const remoteNum = versionToNumber(data.remote_version);

            if (remoteNum > currentNum) {
                toggleUpdateIcon(data.current_version, data.remote_version, true);
            } else {
                toggleUpdateIcon(data.current_version, data.remote_version, false);
            }
        })
        .catch(err => console.error('Ошибка при проверке обновлений:', err));
}

function versionToNumber(version) {
    if (!version || version === 'unknown') return 0;
    const parts = version.replace('v', '').split('.');
    return parseInt(parts[0]) * 10000 + parseInt(parts[1] || 0) * 100 + parseInt(parts[2] || 0);
}

function toggleUpdateIcon(currentVersion, remoteVersion, show = true) {
    const updateIcon = document.getElementById('update-icon') || document.createElement('button');
    updateIcon.id = 'update-icon';
    updateIcon.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" id="update">
            <path fill="currentColor" d="M19.544 11.418C18.892 7.752 15.988 5 12.5 5 9.73 5 7.325 6.742 6.127 9.293 3.243 9.633 1 12.341 1 15.625 1 19.142 3.578 22 6.75 22h12.458C21.853 22 24 19.62 24 16.687c0-2.805-1.965-5.078-4.456-5.27z"/>
            <path fill="#fff" d="M10.7 10h3.15v4.05H17L12.5 19 8 14.05h2.7V10z"/>
        </svg>
    `;
    updateIcon.title = `Доступна новая версия: ${remoteVersion} (текущая: ${currentVersion})`;
    updateIcon.style.cursor = 'pointer';
    updateIcon.addEventListener('click', () => showUpdateAlert(currentVersion, remoteVersion));

    const footer = document.querySelector('footer');
    if (show) {
        if (!document.getElementById('update-icon')) {
            footer.appendChild(updateIcon);
        }
    } else {
        if (document.getElementById('update-icon')) {
            updateIcon.remove();
        }
    }
}

function showUpdateAlert(currentVersion, remoteVersion) {
    fetch('web4static.php?get_release_notes&v=' + remoteVersion)
        .then(response => response.json())
        .then(data => {
            let releaseNotes = data.notes || 'Информация об изменениях недоступна.';
            if (Array.isArray(data.notes)) {
                releaseNotes = data.notes.join('\n');
            }
            const message = `Доступна новая версия: ${remoteVersion} (текущая: ${currentVersion})\n\nСписок изменений:\n${releaseNotes}\n\nОбновить?`;
            if (confirm(message)) {
                updateScript();
            }
        })
        .catch(err => {
            console.error('Ошибка при получении списка изменений:', err);
            const message = `Доступна новая версия: ${remoteVersion} (текущая: ${currentVersion})\n\nСписок изменений недоступен.\n\nОбновить?`;
            if (confirm(message)) {
                updateScript();
            }
        });
}

function updateScript() {
    const updateIcon = document.getElementById('update-icon');
    const loader = document.getElementById('loader');

    if (updateIcon) updateIcon.style.display = 'none';
    loader.style.display = 'flex';

    fetch('web4static.php?update_script')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Веб-интерфейс успешно обновлён!\n' + data.output + '\nПерезагружаю страницу...');
                location.reload();
            } else {
                alert('Ошибка при обновлении:\n' + data.output);
            }
            loader.style.display = 'none';
            if (updateIcon) updateIcon.style.display = 'flex';
        })
        .catch(err => {
            console.error('Ошибка при обновлении:', err);
            alert('Ошибка при обновлении веб-интерфейса');
            loader.style.display = 'none';
            if (updateIcon) updateIcon.style.display = 'flex';
        });
}