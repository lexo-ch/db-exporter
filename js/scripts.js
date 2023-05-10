
const ldbeAjaxWrapper = async (parameters = {}) => {

    const data = new FormData();

    for (const key in parameters) {
        data.append(key, parameters[key]);
    }

    let response = [];

    try {
        response = await fetch(
            ajaxurl,
            {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
                body: data,
            },
        );
    }
    catch (e) {
        console.log(e.message);
    }

    return response.json();
}

const ldbeDisableBackupArea = () => {
    const wrapper = document.getElementById('lexo-db-export-wrapper');

    if (!wrapper) {
        return;
    }

    wrapper.classList.add('exporting');
}

const ldbeEnablePreviewArea = () => {
    const wrapper = document.getElementById('lexo-db-export-wrapper');

    if (!wrapper) {
        return;
    }

    wrapper.classList.remove('exporting');
}

const ldbeExportDb = async () => {
    ldbeDisableBackupArea();

    const response = await ldbeAjaxWrapper({
        'action': 'exportDatabase',
        'security': document.getElementById('ldbe-submit').dataset.nonce,
        'ldbe-old-string': document.getElementById('ldbe-old-string').value,
        'ldbe-new-string': document.getElementById('ldbe-new-string').value
    });

    if (response.success === false) {
        console.error(response);
        alert(response.data.message);
        ldbeEnablePreviewArea();
        return;
    }

    ldbeEnablePreviewArea();

    let blob = new Blob([response.data.message], {type: 'application/octet-stream'});
    let url = URL.createObjectURL(blob);
    let a = document.createElement('a');

    a.href = url;
    a.download = response.data.filename;
    a.click();
    a.remove();
}

const ldbeHandleExportDb = () => {
    const submit = document.getElementById('ldbe-submit');

    if (!submit) {
        return;
    }

    submit.addEventListener('click', (e) => {
        const inputs = document.querySelectorAll('.ldbe-input');

        let valid = true;

        inputs.forEach((input) => {
            if (input.value === '') {
                valid = false;
                return;
            }
        });

        if (!valid) {
            alert(ldbe_translations.confirmation_message);
            return;
        }

        ldbeExportDb();
    });
}

document.addEventListener("DOMContentLoaded", () => {
    ldbeHandleExportDb();
});