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
    updateBarColor();
}

function updateBarColor() {
    const rootStyles = getComputedStyle(document.documentElement);
    const lightThemeColor = rootStyles.getPropertyValue('--background-color').trim();
    const darkThemeColor = rootStyles.getPropertyValue('--background-color-dark').trim();

    let themeColorMeta = document.querySelector('meta[name="theme-color"]');
    let statusBarStyleMeta = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');

    if (themeColorMeta) themeColorMeta.remove();
    if (statusBarStyleMeta) statusBarStyleMeta.remove();

    themeColorMeta = document.createElement('meta');
    themeColorMeta.setAttribute('name', 'theme-color');

    statusBarStyleMeta = document.createElement('meta');
    statusBarStyleMeta.setAttribute('name', 'apple-mobile-web-app-status-bar-style');

    const isDarkTheme = document.body.classList.contains('dark-theme');
    themeColorMeta.setAttribute("content", isDarkTheme ? darkThemeColor : lightThemeColor);
    statusBarStyleMeta.setAttribute("content", isDarkTheme ? "black-translucent" : "default");

    document.head.appendChild(themeColorMeta);
    document.head.appendChild(statusBarStyleMeta);
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
    updateBarColor();
}

function detectSystemTheme() {
    const prefersDarkScheme = window.matchMedia("(prefers-color-scheme: dark)");

    if (prefersDarkScheme.matches) {
        document.body.classList.add('dark-theme');
        document.querySelector('footer').classList.add('dark-theme');
    }

    updateIconDisplay();
    updateBarColor();
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
    updateBarColor();
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
    console.log('Форма отправлена, сохраняю и перезапускаю...');
    animateSave(button, 'saving');
    const formData = new FormData(this);

    setTimeout(() => {
        animateSave(button, 'restarting');

        fetch(this.action, {
            method: 'POST',
            body: formData
        }).then(response => {
            console.log('Ответ от сервера получен:', response);
            if (response.ok) {
                animateSave(button, 'success');
            } else {
                console.error('Ошибка при сохранении данных');
                button.value = 'Error';
            }
        }).catch(err => {
            console.error('Ошибка при отправке данных:', err);
            button.value = 'Error';
        }).finally(() => {
            setTimeout(() => {
                button.value = 'Save & Restart';
                button.classList.remove('loading');
                button.disabled = false;
            }, 1500);
        });
    }, 1000);
});

function animateSave(button, state) {
    button.disabled = true;
    button.classList.add('loading');
    if (state === 'saving') {
        button.value = 'Saving...';
    } else if (state === 'restarting') {
        button.value = 'Restarting...';
    } else if (state === 'success') {
        button.value = 'Success!';
    }
}

function exportFile(fileKey, extension) {
    const textarea = document.querySelector(`textarea[name="${fileKey}"]`);
    const content = textarea.value;
    const blob = new Blob([content], {type: 'text/plain'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${fileKey}.${extension}`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function importFile(fileKey, input) {
    const file = input.files[0];
    if (file && confirm(`Заменить содержимым ${fileKey} поле ввода?`)) {
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

    const activeButton = Array.from(buttons).find(button => button.getAttribute('onclick') === `showSubSection('${section}')`);
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
            const localNum = versionToNumber(data.local_version);
            const remoteNum = versionToNumber(data.remote_version);

            if (remoteNum > localNum) {
                toggleUpdateIcon(data.local_version, data.remote_version, true);
            } else {
                toggleUpdateIcon(data.local_version, data.remote_version, false);
            }
        })
        .catch(err => console.error('Ошибка при проверке обновлений:', err));
}

function versionToNumber(version) {
    if (!version || version === 'unknown') return 0;
    const parts = version.replace('v', '').split('.');
    return parseInt(parts[0]) * 10000 + parseInt(parts[1] || 0) * 100 + parseInt(parts[2] || 0);
}

function toggleUpdateIcon(local_version, remoteVersion, show = true) {
    const updateIcon = document.getElementById('update-icon') || document.createElement('button');
    updateIcon.id = 'update-icon';
    updateIcon.innerHTML = `
        <svg width="24" height="24"><use href="#update"/></svg>
    `;
    updateIcon.title = `Доступно обновление`;
    updateIcon.style.cursor = 'pointer';
    updateIcon.addEventListener('click', () => showUpdateAlert(local_version, remoteVersion));

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

function showUpdateAlert(local_version, remoteVersion) {
    fetch('web4static.php?get_release_notes&v=' + remoteVersion)
        .then(response => response.json())
        .then(data => {
            let releaseNotes = 'Информация об изменениях недоступна.';

            if (data.notes) {
                if (typeof data.notes === 'object' && !Array.isArray(data.notes)) {
                    releaseNotes = Object.values(data.notes)
                        .filter(note => note && note.trim())
                        .map(note => note.trim().replace(/\r/g, ''))
                        .join('\n');
                } else if (Array.isArray(data.notes)) {
                    releaseNotes = data.notes
                        .filter(note => note && note.trim())
                        .map(note => note.trim().replace(/\r/g, ''))
                        .join('\n');
                }
            }

            const message = `Доступно обновление: ${remoteVersion} (текущая: ${local_version})\n\n${releaseNotes}\n\nОбновить?`;
            if (confirm(message)) {
                updateScript(remoteVersion);
            }
        })
        .catch(err => {
            console.error('Ошибка при получении списка изменений:', err);
            const message = `Доступно обновление: ${remoteVersion} (текущая: ${local_version})\n\nСписок изменений недоступен.\n\nОбновить?`;
            if (confirm(message)) {
                updateScript(remoteVersion);
            }
        });
}

function updateScript(remoteVersion) {
    const updateIcon = document.getElementById('update-icon');
    const loader = document.getElementById('loader-icon');

    if (updateIcon) updateIcon.style.display = 'none';
    loader.style.display = 'flex';

    fetch(`web4static.php?update_script&remote_version=${encodeURIComponent(remoteVersion)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Веб-интерфейс успешно обновлён!\n' + data.output);
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

function saveAndApplyTextareaSize(textarea) {
    const size = {
        width: textarea.style.width || getComputedStyle(textarea).width,
        height: textarea.style.height || getComputedStyle(textarea).height
    };
    localStorage.setItem('textarea_size', JSON.stringify(size));

    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(t => {
        t.style.width = size.width;
        t.style.height = size.height;
    });
}

function restoreTextareaSizes() {
    const savedSize = localStorage.getItem('textarea_size');
    if (savedSize) {
        const {width, height} = JSON.parse(savedSize);
        const textareas = document.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            textarea.style.width = width;
            textarea.style.height = height;
        });
    }
}

function setupTextareaResizeListeners() {
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        const observer = new ResizeObserver(() => {
            saveAndApplyTextareaSize(textarea);
        });
        observer.observe(textarea);
    });
}

function deleteGroup(groupName) {
    if (confirm(`Удалить группу ${groupName}?`)) {
        fetch('web4static.php?delete_group=' + encodeURIComponent(groupName), {
            method: 'POST'
        })
            .then(response => {
                if (response.ok) {
                    alert(`Группа ${groupName} удалена!`);
                    location.reload();
                } else {
                    alert('Ошибка при удалении группы');
                }
            })
            .catch(err => {
                console.error('Ошибка при удалении группы:', err);
                alert('Ошибка при удалении группы');
            });
    }
}

function createGroup() {
    const groupName = prompt('Введите название новой группы:');
    if (!groupName) {
        return;
    }
    if (!/^[a-zA-Z0-9_-]+$/.test(groupName.trim())) {
        alert('Название группы может содержать только буквы, цифры, подчеркивания и дефисы!');
        return;
    }
    fetch('web4static.php?create_group=' + encodeURIComponent(groupName.trim()), {
        method: 'POST'
    })
        .then(response => {
            if (response.ok) {
                alert(`Группа ${groupName.trim()} создана!`);
                location.reload();
            } else {
                alert('Ошибка при создании группы');
            }
        })
        .catch(err => {
            console.error('Ошибка при создании группы:', err);
            alert('Ошибка при создании группы');
        });
}

function opkgUpdate() {
    if (!confirm('Обновить OPKG пакеты?')) {
        return;
    }
    const loader = document.getElementById('loader-icon');
    const opkgIcon = document.getElementById('opkg-icon');

    opkgIcon.style.display = 'none';
    loader.style.display = 'flex';

    fetch('web4static.php?opkg_update', {
        method: 'POST'
    })
        .then(response => response.json())
        .then(data => {
            loader.style.display = 'none';
            opkgIcon.style.display = 'flex';

            alert('Результат выполнения:\n' + data.output);
            console.log(data.output)
        });
}

function isJson(text) {
    const trimmed = text.trim();
    if (!trimmed) return false;

    if (
        (trimmed.startsWith('{') && trimmed.endsWith('}')) ||
        (trimmed.startsWith('[') && trimmed.endsWith(']'))
    ) {
        try {
            JSON.parse(trimmed);
            return true;
        } catch (e) {
            return true;
        }
    }
    return false;
}

function toggleJsonButton(textarea, button) {
    const content = textarea.value;
    if (isJson(content)) {
        button.style.display = 'flex';
    } else {
        button.style.display = 'none';
    }
}

function formatJson(fileKey) {
    const textarea = document.querySelector(`textarea[name="${fileKey}"]`);
    const content = textarea.value.trim();

    try {
        const parsedJson = JSON.parse(content);
        const formattedJson = JSON.stringify(parsedJson, null, 2);
        textarea.value = formattedJson;
    } catch (error) {
        alert('Неверный формат JSON\n' + error.message);
    }
}