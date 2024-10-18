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
        detectSystemTheme();
    } else if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
        footer.classList.add('dark-theme');
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
    const buttons = document.querySelectorAll('input[type="button"]');

    buttons.forEach(button => {
        button.classList.remove('button-active');
    });

    const activeButton = Array.from(buttons).find(button => button.value === section);
    if (activeButton) {
        activeButton.classList.add('button-active');
    }

    const sections = document.getElementsByClassName('form-section');
    Array.from(sections).forEach(section => {
        section.style.display = 'none';
    });

    document.getElementById(section).style.display = 'block';
}

document.getElementById('mainForm').addEventListener('submit', function(event) {
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