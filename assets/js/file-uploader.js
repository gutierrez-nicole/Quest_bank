

function setupFileDropZone(dropZoneId, fileInputId, textId, detailsId) {
    const dropZone = document.getElementById(dropZoneId);
    const fileInput = document.getElementById(fileInputId);
    const textElem = document.getElementById(textId);

    if (!dropZone || !fileInput) return;

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-orange-500', 'bg-orange-50/50');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-orange-500', 'bg-orange-50/50');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-orange-500', 'bg-orange-50/50');

        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            if (textElem) {
                textElem.textContent = e.dataTransfer.files[0].name;
            }
        }
    });
}

function displaySelectedFile() {
    const fileInput = document.getElementById('lesson_file');
    const textElem = document.getElementById('upload_text');
    if (fileInput && fileInput.files.length > 0 && textElem) {
        textElem.textContent = "Selected: " + fileInput.files[0].name;
    }
}

function triggerFileSelect() {
    const fileInput = document.getElementById('lesson_file');
    if (fileInput) {
        fileInput.click();
    }
}
