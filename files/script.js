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

    var sections = document.getElementsByClassName('form-section');
    for (var i = 0; i < sections.length; i++) {
        sections[i].style.display = 'none';
    }

    document.getElementById(section).style.display = 'block';
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