(function () {
    var input = document.getElementById('photos');
    var previewBlock = document.getElementById('preview-block');
    var previewGrid = document.getElementById('preview-grid');
    var previewMeta = document.getElementById('preview-meta');

    if (!input || !previewBlock || !previewGrid || !previewMeta) {
        return;
    }

    var objectUrls = [];
    var selectedFiles = [];
    var supportsDataTransfer = typeof DataTransfer !== 'undefined';

    function clearPreview() {
        for (var i = 0; i < objectUrls.length; i++) {
            URL.revokeObjectURL(objectUrls[i]);
        }
        objectUrls = [];
        previewGrid.innerHTML = '';
        previewMeta.textContent = '';
        previewBlock.hidden = true;
    }

    function syncInputFiles() {
        if (!supportsDataTransfer) {
            return;
        }

        var dt = new DataTransfer();
        for (var i = 0; i < selectedFiles.length; i++) {
            dt.items.add(selectedFiles[i]);
        }
        input.files = dt.files;
    }

    function formatBytes(bytes) {
        if (bytes < 1024) {
            return bytes + ' B';
        }
        var kb = bytes / 1024;
        if (kb < 1024) {
            return kb.toFixed(1) + ' KB';
        }
        var mb = kb / 1024;
        return mb.toFixed(1) + ' MB';
    }

    function renderPreview() {
        clearPreview();

        if (selectedFiles.length === 0) {
            return;
        }

        var totalBytes = 0;
        for (var i = 0; i < selectedFiles.length; i++) {
            totalBytes += selectedFiles[i].size || 0;

            var item = document.createElement('figure');
            item.className = 'fotoupload-preview-item';

            var img = document.createElement('img');
            img.className = 'fotoupload-preview-thumb';
            img.alt = selectedFiles[i].name;

            if (selectedFiles[i].type && selectedFiles[i].type.indexOf('image/') === 0) {
                var objectUrl = URL.createObjectURL(selectedFiles[i]);
                objectUrls.push(objectUrl);
                img.src = objectUrl;
            }

            var caption = document.createElement('figcaption');
            caption.className = 'fotoupload-preview-caption';
            caption.textContent = selectedFiles[i].name;

            var size = document.createElement('span');
            size.className = 'fotoupload-preview-size';
            size.textContent = formatBytes(selectedFiles[i].size || 0);
            caption.appendChild(size);

            var removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'fotoupload-remove';
            removeButton.textContent = 'Entfernen';
            removeButton.setAttribute('aria-label', selectedFiles[i].name + ' entfernen');

            if (!supportsDataTransfer) {
                removeButton.disabled = true;
            }

            (function (index) {
                removeButton.addEventListener('click', function () {
                    selectedFiles.splice(index, 1);
                    syncInputFiles();
                    renderPreview();
                });
            })(i);

            item.appendChild(img);
            item.appendChild(caption);
            item.appendChild(removeButton);
            previewGrid.appendChild(item);
        }

        var metaText = selectedFiles.length + ' Bild(er), gesamt ' + formatBytes(totalBytes);
        if (!supportsDataTransfer) {
            metaText += ' - Entfernen wird von diesem Browser nicht unterstützt.';
        }
        previewMeta.textContent = metaText;
        previewBlock.hidden = false;
    }

    input.addEventListener('change', function () {
        selectedFiles = [];

        var files = input.files;
        if (!files || files.length === 0) {
            renderPreview();
            return;
        }

        for (var i = 0; i < files.length; i++) {
            selectedFiles.push(files[i]);
        }

        renderPreview();
    });
})();
