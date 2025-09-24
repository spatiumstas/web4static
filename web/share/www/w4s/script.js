/** Theme **/

const themeCache = {theme: localStorage.getItem('theme') || 'dark'};

function toggleTheme() {
    document.body.classList.toggle('dark-theme');
    const footer = document.querySelector('footer');
    if (footer) footer.classList.toggle('dark-theme');

    themeCache.theme = document.body.classList.contains('dark-theme') ? 'dark' : 'light';
    localStorage.setItem('theme', themeCache.theme);

    updateThemeUI();
}

function updateThemeUI() {
    const isDarkTheme = document.body.classList.contains('dark-theme');
    const sunIcon = document.getElementById('sun-icon');
    const moonIcon = document.getElementById('moon-icon');
    const rootStyles = getComputedStyle(document.documentElement);

    if (sunIcon && moonIcon) {
        sunIcon.style.display = isDarkTheme ? 'none' : 'inline';
        moonIcon.style.display = isDarkTheme ? 'inline' : 'none';
    }

    const lightThemeColor = rootStyles.getPropertyValue('--background-color').trim();
    const darkThemeColor = rootStyles.getPropertyValue('--background-color-dark').trim();
    let themeColorMeta = document.querySelector('meta[name="theme-color"]');
    let statusBarStyleMeta = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');

    if (themeColorMeta) themeColorMeta.remove();
    if (statusBarStyleMeta) statusBarStyleMeta.remove();

    themeColorMeta = document.createElement('meta');
    themeColorMeta.setAttribute('name', 'theme-color');
    themeColorMeta.setAttribute('content', isDarkTheme ? darkThemeColor : lightThemeColor);

    statusBarStyleMeta = document.createElement('meta');
    statusBarStyleMeta.setAttribute('name', 'apple-mobile-web-app-status-bar-style');
    statusBarStyleMeta.setAttribute('content', isDarkTheme ? 'black-translucent' : 'default');

    document.head.appendChild(themeColorMeta);
    document.head.appendChild(statusBarStyleMeta);
}

function applySavedTheme() {
    const footer = document.querySelector('footer');
    if (!themeCache.theme || themeCache.theme === 'dark') {
        document.body.classList.add('dark-theme');
        if (footer) footer.classList.add('dark-theme');
        themeCache.theme = 'dark';
        localStorage.setItem('theme', 'dark');
    } else {
        document.body.classList.remove('dark-theme');
        if (footer) footer.classList.remove('dark-theme');
    }

    updateThemeUI();
}

function detectSystemTheme() {
    const prefersDarkScheme = window.matchMedia("(prefers-color-scheme: dark)");
    const footer = document.querySelector('footer');

    if (prefersDarkScheme.matches && !themeCache.theme) {
        document.body.classList.add('dark-theme');
        if (footer) footer.classList.add('dark-theme');
        themeCache.theme = 'dark';
        localStorage.setItem('theme', 'dark');
    }

    updateThemeUI();
}

window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", (e) => {
    const footer = document.querySelector('footer');
    if (e.matches) {
        document.body.classList.add('dark-theme');
        if (footer) footer.classList.add('dark-theme');
        themeCache.theme = 'dark';
    } else {
        document.body.classList.remove('dark-theme');
        if (footer) footer.classList.remove('dark-theme');
        themeCache.theme = 'light';
    }
    localStorage.setItem('theme', themeCache.theme);
    updateThemeUI();
});

/** Compare files **/
const fileVersions = new Map();

function saveFileVersion(textarea) {
    const fileKey = textarea.name;
    fileVersions.set(fileKey, textarea.value);
}

function compareFileVersions(oldVersion, newVersion) {
    const oldLines = oldVersion.split('\n');
    const newLines = newVersion.split('\n');

    const changes = {
        added: [],
        removed: [],
        modified: []
    };

    const oldLinesMap = new Map(oldLines.map((line, index) => [line, index]));
    const newLinesMap = new Map(newLines.map((line, index) => [line, index]));

    for (const [line, newIndex] of newLinesMap) {
        if (!oldLinesMap.has(line)) {
            changes.added.push({line, lineNumber: newIndex + 1});
        }
    }

    for (const [line, oldIndex] of oldLinesMap) {
        if (!newLinesMap.has(line)) {
            changes.removed.push({line, lineNumber: oldIndex + 1});
        }
    }

    return changes;
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('textarea').forEach(textarea => {
        saveFileVersion(textarea);

        textarea.addEventListener('input', function () {
            saveFileVersion(this);
        });
    });
});

document.getElementById('mainForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const button = this.querySelector('input[type="submit"]');
    console.log('Форма отправлена, сохраняю...');

    const formData = new FormData(this);
    const changesByCategory = {};
    const changedCategories = new Set();

    document.querySelectorAll('textarea').forEach(textarea => {
        const fileKey = textarea.name;
        const newContent = textarea.value;
        const oldContent = textarea.defaultValue;

        if (newContent !== oldContent) {
            const [category, fileName] = fileKey.split('/');
            changedCategories.add(category);

            if (!changesByCategory[category]) {
                changesByCategory[category] = [];
            }
            changesByCategory[category].push(fileName || fileKey);
        }
    });

    for (const [category, files] of Object.entries(changesByCategory)) {
        if (files.length > 0) {
            console.log(`Изменения в ${category}: ${files.join(', ')}. Перезапускаю сервис`);
        }
    }

    if (changedCategories.size === 0) {
        console.log('Нет изменений. Перезапускаю все сервисы');
    }

    formData.append('changed_categories', JSON.stringify(Array.from(changedCategories)));

    animateSave(button, 'saving');

    setTimeout(() => {
        animateSave(button, 'restarting');

        fetch(this.action, {
            method: 'POST',
            body: formData
        }).then(response => {
            console.log('Ответ от сервера получен:', response);
            if (response.ok) {
                animateSave(button, 'success');
                document.querySelectorAll('textarea').forEach(textarea => {
                    textarea.defaultValue = textarea.value;
                });
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

/** Actions **/
function showSection(section) {
    console.log('Showing section:', section);
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
    } else {
        console.error('Section not found:', section);
    }
}

function exportFile(fileKey, extension, category = '') {
    const textareaName = category ? `${category}/${fileKey}` : fileKey;
    console.log('Exporting file:', textareaName);
    const textarea = document.querySelector(`textarea[name="${textareaName}"]`);
    if (!textarea) {
        console.error('Textarea not found for:', textareaName);
        return;
    }
    const content = textarea.value;
    const blob = new Blob([content], {type: 'text/plain'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const fileName = category ? `${category}/${fileKey}.${extension}` : `${fileKey}.${extension}`;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function importFile(fileKey, input, category = '') {
    const file = input.files[0];
    const textareaName = category ? `${category}/${fileKey}` : fileKey;
    if (file && confirm(`Заменить содержимым ${textareaName} поле ввода?`)) {
        const reader = new FileReader();
        reader.onload = function (e) {
            const textarea = document.querySelector(`textarea[name="${textareaName}"]`);
            if (!textarea) {
                console.error('Textarea not found for:', textareaName);
                return;
            }
            textarea.value = e.target.result;
            input.value = '';
        };
        reader.readAsText(file);
    }
}

function exportAllFiles() {
    const date = new Date().toISOString().slice(0, 10);
    const archiveName = `w4s_backup_${date}.tar.gz`;
    const a = document.createElement('a');
    a.href = window.location.pathname + '?export_all=1';
    a.download = archiveName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function showSubSection(section) {
    console.log('Showing subsection:', section);
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
    } else {
        console.warn('Active button not found for subsection:', section);
    }

    const sectionElement = document.getElementById(section);
    if (sectionElement) {
        sectionElement.style.display = 'block';
    } else {
        console.error('Subsection not found:', section);
    }
}

document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.code === 'KeyS') {
        e.preventDefault();
        document.querySelector('input[type="submit"]').click();
    }
});

/** Updates **/
let isUpdating = false;
let remoteVersion = null;

function versionToNumber(version) {
    if (!version || version === 'unknown' || version === '') return 0;
    const parts = version.split('.');
    return parseInt(parts[0]) * 10000 + parseInt(parts[1] || 0) * 100 + parseInt(parts[2] || 0);
}

function isRemoteNewer(local, remote) {
    const a = String(local || '0').split('.').map(n => parseInt(n, 10) || 0);
    const b = String(remote || '0').split('.').map(n => parseInt(n, 10) || 0);
    for (let i = 0; i < 3; i++) {
        const av = a[i] || 0;
        const bv = b[i] || 0;
        if (bv > av) return true;
        if (bv < av) return false;
    }
    for (let i = 3; i < b.length; i++) {
        if ((b[i] || 0) > 0) return true;
    }
    return false;
}

function setElementVisibility(element, isVisible) {
    if (element) {
        element.style.display = isVisible ? 'flex' : 'none';
    }
}

function opkgUpdate() {
    if (!confirm('Обновить OPKG пакеты?')) {
        return;
    }
    isUpdating = true;

    const updatePanel = document.getElementById('update-w4s-panel');
    const opkgIcon = document.getElementById('opkg-icon');
    const wasPanelVisible = updatePanel.style.display !== 'none';

    toggleProgressBar(true);

    fetch('index.php?update&type=packages')
        .then(response => response.json())
        .then(data => {
            const title = 'Обновление OPKG';
            showOutputModal(title, data.output);
        })
        .catch(err => {
            console.error(err);
            showOutputModal('Ошибка OPKG', err.message);
        })
        .finally(() => {
            toggleProgressBar(false, {
                wasPanelVisible,
                showElement: opkgIcon,
                onClickAfterHide: () => showUpdateAlert(local_version, remoteVersion)
            });
            isUpdating = false;
        });
}

function checkForUpdates() {
    fetch('index.php?check_update')
        .then(response => response.json())
        .then(data => {
            console.log('Check update response:', data);
            remoteVersion = data.remote_version;
            const newer = isRemoteNewer(data.local_version, data.remote_version);
            toggleUpdateIcon(data.local_version, data.remote_version, newer);
        })
        .catch(err => console.error('Ошибка при проверке обновлений:', err));
}

function manageUpdatePanel({showPanel = false, showText = false, showProgressBar = false, text = 'Доступно обновление', onClick = null}) {
    const updatePanel = document.getElementById('update-w4s-panel');
    const updateSpan = updatePanel.querySelector('span');
    const progressBar = updatePanel.querySelector('.progress-bar');
    const footer = document.querySelector('footer');

    setElementVisibility(updatePanel, showPanel);

    if (showPanel) {
        footer.classList.add('panel-above');
    } else {
        footer.classList.remove('panel-above');
    }

    setElementVisibility(updateSpan, showText);
    setElementVisibility(progressBar, showProgressBar);

    if (showText) {
        updateSpan.textContent = text;
    }
    updatePanel.onclick = onClick;
}

function toggleProgressBar(show, {hideElement = null, showElement = null, wasPanelVisible = false, onClickAfterHide = null} = {}) {
    if (show) {

        manageUpdatePanel({
            showPanel: true,
            showText: false,
            showProgressBar: true
        });
        setElementVisibility(hideElement, false);
    } else {

        setElementVisibility(showElement, true);

        if (wasPanelVisible) {
            manageUpdatePanel({
                showPanel: true,
                showText: true,
                showProgressBar: false,
                text: 'Доступно обновление',
                onClick: onClickAfterHide
            });
        } else {
            manageUpdatePanel({showPanel: false});
        }
    }
}

function toggleUpdateIcon(localVersion, remoteVersion, show = true) {
    if (!localVersion || localVersion === '' || localVersion === 'unknown') {
        show = false;
    }

    if (isUpdating) {
        show = false;
    }

    manageUpdatePanel({
        showPanel: show,
        showText: show,
        showProgressBar: false,
        text: 'Доступно обновление',
        onClick: show ? () => showUpdateAlert(localVersion, remoteVersion) : null
    });
}

function showUpdateAlert(localVersion, remoteVersion) {
    if (isUpdating) {
        return;
    }

    fetch('index.php?get_release_notes&v=' + remoteVersion)
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

            const message = `Доступно обновление: ${remoteVersion} (текущая: ${localVersion})\n\n${releaseNotes}\n\nОбновить?`;
            if (confirm(message)) {
                updateScript();
            }
        })
        .catch(err => {
            console.error('Ошибка при получении списка изменений:', err);
            const message = `Доступно обновление: ${remoteVersion} (текущая: ${localVersion})\n\nСписок изменений недоступен.\n\nОбновить?`;
            if (confirm(message)) {
                updateScript();
            }
        });
}

function updateScript() {
    if (isUpdating) {
        showOutputModal('Обновление', 'Дождитесь завершения текущего обновления.');
        return;
    }
    isUpdating = true;

    manageUpdatePanel({showPanel: false});

    toggleProgressBar(true);

    fetch(`index.php?update&type=web`)
        .then(response => response.json())
        .then(data => {
            const title = 'Обновление веб-интерфейса';
            showOutputModal(title, data.output);
        })
        .catch(err => {
            console.error(err);
            showOutputModal('Ошибка обновления веб-интерфейса', err.message);
        })
        .finally(() => {
            toggleProgressBar(false);
            isUpdating = false;
            manageUpdatePanel({showPanel: false});
        });
}

/** Textarea **/
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

/** JSON **/
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

function formatJson(textareaName) {
    console.log('Formatting JSON:', textareaName);
    const textarea = document.querySelector(`textarea[name="${textareaName}"]`);
    if (!textarea) {
        console.error('Textarea не найдено:', textareaName);
        return;
    }
    const content = textarea.value.trim();

    try {
        const parsedJson = JSON.parse(content);
        const formattedJson = JSON.stringify(parsedJson, null, 2);
        textarea.value = formattedJson;
    } catch (error) {
        showOutputModal('Ошибка JSON', error.message);
    }
}

let statusRequestInProgress = false;
function getServiceStatus(category) {
    if (statusRequestInProgress) return;
    statusRequestInProgress = true;
    fetch(`index.php?service_status=${encodeURIComponent(category)}`)
        .then(r => r.json())
        .then(data => {
            showOutputModal('Статус сервиса', data.status);
        })
        .catch(() => showOutputModal('Ошибка', 'Ошибка получения статуса сервиса'))
        .finally(() => { statusRequestInProgress = false; });
}

function showOutputModal(title, message) {
    const modal = document.getElementById('output-modal');
    const modalTitle = modal.querySelector('.output-modal-header h3');
    const modalText = document.getElementById('output-modal-text');

    modalTitle.textContent = title;
    modalText.textContent = message;
    modal.classList.add('show');
    document.body.classList.add('modal-open');
}

function hideOutputModal() {
    const modal = document.getElementById('output-modal');
    modal.classList.remove('show');
    document.body.classList.remove('modal-open');
}

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('output-modal');
    const closeButtons = modal.querySelectorAll('.close-modal-btn');

    closeButtons.forEach(button => {
        button.addEventListener('click', hideOutputModal);
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            hideOutputModal();
        }
    });
});