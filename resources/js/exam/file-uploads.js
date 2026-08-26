/**
 * Preview and client-side validation for file-upload questions.
 *
 * The size and extension checks here are a courtesy, not a control: the server
 * validates both again on submit. What they buy is the student finding out
 * before the upload rather than after.
 */
export function bindFileUploads({ onChange, doc = document }) {
    doc.querySelectorAll('.file-input').forEach(input => {
        input.addEventListener('change', function () {
            const questionId = this.dataset.question;
            const previewDiv = doc.getElementById('file_preview_' + questionId);

            if (this.files && this.files.length > 0) {
                const file = this.files[0];
                const fileName = file.name;
                const fileSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';

                previewDiv.style.display = 'block';
                previewDiv.querySelector('.filename').textContent = fileName;
                previewDiv.querySelector('.filesize').textContent = fileSize;

                const maxSizeMb = parseInt(this.getAttribute('data-max-size') || '10');
                if (file.size > maxSizeMb * 1024 * 1024) {
                    previewDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i><strong>Fayl çox böyükdür!</strong> Maksimum ölçü: ' + maxSizeMb + ' MB.</div>';
                    this.value = '';
                    onChange();

                    return;
                }

                const allowedTypes = this.getAttribute('accept').split(',');
                const fileExtension = '.' + fileName.split('.').pop().toLowerCase();
                if (!allowedTypes.includes(fileExtension)) {
                    previewDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i><strong>Yanlış fayl tipi!</strong> İcazə verilən tiplər: ' + allowedTypes.join(', ') + '</div>';
                    this.value = '';
                    onChange();

                    return;
                }
            } else {
                previewDiv.style.display = 'none';
            }

            onChange();
        });
    });
}
