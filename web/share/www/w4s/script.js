let statusRequestInProgress = false;

function decodeIfEncoded(text) {
    if (typeof text !== 'string' || !text) return null;
    if (text.includes('\n') || text.includes('\r')) return null;
    if (!/%[0-9A-Fa-f]{2}/.test(text)) return null;
    try {
        const prepared = (text.includes('+') && !text.includes(' ')) ? text.replace(/\+/g, ' ') : text;
        const decoded = decodeURIComponent(prepared);
        if (
            decoded !== text &&
            (decoded.includes('\n') || decoded.includes('\r')) &&
            !/%[0-9A-Fa-f]{2}/.test(decoded)
        ) {
            return decoded.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        }
    } catch (_) {}
    return null;
}

function insertTextAtCursor(textarea, text) {
    const value = String(textarea.value || '');
    const start = (typeof textarea.selectionStart === 'number') ? textarea.selectionStart : value.length;
    const end = (typeof textarea.selectionEnd === 'number') ? textarea.selectionEnd : value.length;
    const next = value.slice(0, start) + text + value.slice(end);
    textarea.value = next;
    const pos = start + text.length;
    if (typeof textarea.setSelectionRange === 'function') {
        textarea.setSelectionRange(pos, pos);
    }
}

async function readJsonSafe(response) {
    try {
        return await response.json();
    } catch (_) {
        return null;
    }
}

async function readResponseError(response) {
    const data = await readJsonSafe(response);
    if (data && typeof data === 'object') {
        if (typeof data.error === 'string' && data.error.trim()) return data.error;
        if (typeof data.message === 'string' && data.message.trim()) return data.message;
    }
    return `HTTP ${response.status}`;
}

async function fetchJsonOrThrow(url, options) {
    try {
        const response = await fetch(url, options);
        if (!response.ok) {
            throw new Error('http_error');
        }
        const data = await readJsonSafe(response);
        if (data === null) {
            throw new Error('invalid_json');
        }
        return data;
    } catch (_) {
        throw new Error('Не удалось выполнить запрос');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('textarea').forEach(textarea => {
        const section = textarea.closest('.form-section');
        textarea._jsonBtn = section ? section.querySelector('.format-json-btn') : null;
        if (textarea._jsonBtn) {
            toggleJsonButton(textarea, textarea._jsonBtn);
        }

        textarea.addEventListener('paste', function (e) {
            const cd = e.clipboardData;
            if (!cd) return;
            const pasted = cd.getData('text/plain');
            const decoded = decodeIfEncoded(pasted);
            if (!decoded) return;

            e.preventDefault();
            insertTextAtCursor(this, decoded);
            this.dispatchEvent(new Event('input', {bubbles: true}));
        });

        textarea.addEventListener('beforeinput', function (e) {
            this._w4sPaste = !!(e && e.inputType === 'insertFromPaste');
        });

        textarea.addEventListener('input', function () {
            if (this._w4sPaste) {
                this._w4sPaste = false;
                const decoded = decodeIfEncoded(String(this.value || ''));
                if (decoded) this.value = decoded;
            }

            if (this._jsonBtn) {
                toggleJsonButton(this, this._jsonBtn);
            }
        });
    });

    if (window.navigator.standalone === true) {
        document.body.classList.add('pwa-mode');
    }

    restoreTextareaSizes();
    setupTextareaResizeListeners();
    checkForUpdates();

    const header = document.getElementById('asciiHeader');
    if (header) {
        header.addEventListener('click', function () {
            try {
                localStorage.clear();
            } catch (e) {
            }
            location.reload();
        });
    }

    const modal = document.getElementById('output-modal');
    if (modal) {
        const closeButtons = modal.querySelectorAll('.close-modal-btn');
        closeButtons.forEach(button => {
            button.addEventListener('click', hideOutputModal);
        });
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                hideOutputModal();
            }
        });
    }
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
        }).then(async response => {
            console.log('Ответ от сервера получен:', response);
            if (response.ok) {
                animateSave(button, 'success');
                document.querySelectorAll('textarea').forEach(textarea => {
                    textarea.defaultValue = textarea.value;
                });
            } else {
                const message = await readResponseError(response);
                console.error('Ошибка при сохранении данных:', message);
                button.value = 'Error';
                showOutputModal('Ошибка сохранения', message);
            }
        }).catch(err => {
            console.error('Ошибка при отправке данных:', err);
            button.value = 'Error';
            showOutputModal('Ошибка сохранения', err && err.message ? err.message : 'Ошибка при отправке данных');
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
            textarea.dispatchEvent(new Event('input', {bubbles: true}));
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
    return parseInt(parts[0], 10) * 10000 + parseInt(parts[1] || 0, 10) * 100 + parseInt(parts[2] || 0, 10);
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

    fetchJsonOrThrow('index.php?update&type=packages')
        .then(data => showOutputModal('Обновление OPKG', data.output))
        .catch(err => {
            console.error(err);
            showOutputModal('Ошибка OPKG', err && err.message ? err.message : 'Ошибка OPKG');
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
    fetchJsonOrThrow('index.php?check_update')
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

function apiCall(params) {
    const body = new URLSearchParams(params);
    return fetchJsonOrThrow(window.location.pathname, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body
    }).then(data => {
        if (!data || data.ok !== true) {
            throw new Error((data && data.error) || 'Ошибка API');
        }
        return data;
    });
}

function createFilePrompt(category) {
    const name = prompt('Введите имя файла с расширением, например config.json');
    if (!name) return;
    apiCall({create_file: '1', category, name})
        .then(() => {
            alert(`Файл ${name} успешно создан`);
            location.reload();
        })
        .catch(err => alert(err && err.message ? err.message : 'Ошибка создания файла'));
}

function deleteFile(category, name) {
    if (!confirm(`Удалить файл ${name}?`)) return;
    apiCall({delete_file: '1', category, name})
        .then(() => {
            alert(`Файл ${name} успешно удалён`);
            location.reload();
        })
        .catch(err => alert(err && err.message ? err.message : 'Ошибка удаления файла'));
}

window.getServiceStatus = getServiceStatus;
window.createFilePrompt = createFilePrompt;
window.deleteFile = deleteFile;

function showUpdateAlert(localVersion, remoteVersion) {
    if (isUpdating) {
        return;
    }

    fetchJsonOrThrow('index.php?get_release_notes&v=' + remoteVersion)
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

    fetchJsonOrThrow(`index.php?update&type=web`)
        .then(data => showOutputModal('Обновление веб-интерфейса', data.output))
        .catch(err => {
            console.error(err);
            showOutputModal('Ошибка обновления веб-интерфейса', err && err.message ? err.message : 'Ошибка обновления');
        })
        .finally(() => {
            toggleProgressBar(false);
            isUpdating = false;
            manageUpdatePanel({showPanel: false});
        });
}

/** Textarea **/
let _textareaSizeSaveTimer = null;
let _lastTextareaSizeKey = null;

function saveAndApplyTextareaSize(textarea) {
    const size = {
        width: textarea.style.width || getComputedStyle(textarea).width,
        height: textarea.style.height || getComputedStyle(textarea).height
    };
    const key = `${size.width}x${size.height}`;
    if (key === _lastTextareaSizeKey) return;
    _lastTextareaSizeKey = key;

    if (_textareaSizeSaveTimer) clearTimeout(_textareaSizeSaveTimer);
    _textareaSizeSaveTimer = setTimeout(() => {
        try {
            localStorage.setItem('textarea_size', JSON.stringify(size));
        } catch (_) {}
    }, 150);

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
    const observer = new ResizeObserver((entries) => {
        const entry = entries && entries[0];
        if (!entry || !entry.target) return;
        saveAndApplyTextareaSize(entry.target);
    });
    textareas.forEach(textarea => observer.observe(textarea));
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
            return false;
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
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
    } catch (error) {
        showOutputModal('Ошибка JSON', error.message);
    }
}

function getServiceStatus(category, filePath) {
    if (statusRequestInProgress) return;
    statusRequestInProgress = true;
    const params = new URLSearchParams();
    params.set('service_status', category);
    if (filePath) {
        params.set('config', filePath);
    }
    fetchJsonOrThrow(`index.php?${params.toString()}`)
        .then(data => showOutputModal('Статус сервиса', data.status))
        .catch(err => showOutputModal('Ошибка', err && err.message ? err.message : 'Ошибка получения статуса сервиса'))
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