function showSection(section) {
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