/**
 * app.js — Core application JS
 *
 * Handles:
 *  - AJAX post creation
 *  - Like button toggles
 *  - Comment submission
 *  - Image preview before upload
 *  - Avatar crop tool
 */

'use strict';

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Get CSRF token from the first hidden input on the page */
function getCsrfToken() {
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
}

/** Escape HTML to prevent XSS when inserting user content */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
}

/**
 * Convert http/https URLs in raw text into safe clickable links, while also
 * HTML-escaping all content. Takes raw (unescaped) user text; do NOT call
 * escapeHtml() on the input first. Only http/https schemes are linkified;
 * links get rel="noopener noreferrer nofollow" and target="_blank".
 */
function linkifyHtml(rawStr) {
    function escStr(s) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(s)));
        return d.innerHTML;
    }
    return String(rawStr).split(/(\bhttps?:\/\/\S+)/g).map(function (part, i) {
        if (i % 2 === 0) return escStr(part);
        const url        = part.replace(/[.,;:!?)'"]+$/, '');
        const escapedUrl = escStr(url);
        return '<a href="' + escapedUrl + '" rel="noopener noreferrer nofollow" target="_blank">'
            + escapedUrl + '</a>'
            + escStr(part.slice(url.length));
    }).join('');
}

/** POST JSON (or FormData) to a URL, return parsed JSON */
async function apiPost(url, data) {
    const body = data instanceof FormData ? data : new URLSearchParams(data);
    const resp = await fetch(url, {
        method: 'POST',
        headers: data instanceof FormData ? {} : { 'Content-Type': 'application/x-www-form-urlencoded' },
        body,
        credentials: 'same-origin',
    });
    return resp.json();
}

// ── AJAX post creation ────────────────────────────────────────────────────────

const postForm = document.getElementById('post-form');

if (postForm) {
    postForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(postForm);
        // Ensure CSRF token is present
        if (!formData.get('csrf_token')) {
            formData.append('csrf_token', getCsrfToken());
        }

        const submitBtn = postForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Posting…';
        }

        try {
            const result = await fetch(postForm.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await result.json();

            if (json.ok) {
                // Reload the post feed by refreshing the page
                window.location.reload();
            } else {
                alert('Error: ' + (json.error || 'Unknown error'));
            }
        } catch (err) {
            console.error('Post creation failed:', err);
            alert('Failed to submit post. Please try again.');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Post';
            }
        }
    });
}

// ── Like button ───────────────────────────────────────────────────────────────

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-like');
    if (!btn) return;

    e.preventDefault();
    btn.disabled = true;

    const postId = btn.dataset.postId;
    const data   = new URLSearchParams({ csrf_token: getCsrfToken(), post_id: postId });

    try {
        // Determine base URL dynamically
        const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';
        const result  = await apiPost(baseUrl + '/modules/wall/like_post.php', data);

        if (result.ok) {
            const countEl = btn.querySelector('.like-count');
            if (countEl) countEl.textContent = result.count;
            btn.classList.toggle('liked', result.liked);
        }
    } catch (err) {
        console.error('Like failed:', err);
    } finally {
        btn.disabled = false;
    }
});

// ── Comment form (AJAX) ───────────────────────────────────────────────────────

document.addEventListener('submit', async (e) => {
    const form = e.target.closest('.comment-form');
    if (!form) return;

    e.preventDefault();

    const postId  = form.dataset.postId;
    const input   = form.querySelector('input[name="content"]');
    const content = input ? input.value.trim() : '';
    if (!content) return;

    const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';
    const data    = new URLSearchParams({
        csrf_token: getCsrfToken(),
        post_id:    postId,
        content,
    });

    try {
        const result = await apiPost(baseUrl + '/modules/wall/add_comment.php', data);

        if (result.ok) {
            // Append new comment
            const section = document.getElementById('comments-' + postId);
            if (section) {
                const commentHtml = `
                <div class="comment-item" id="comment-${escapeHtml(result.comment_id)}">
                    <a href="${escapeHtml(result.profile_url)}">
                        <img src="${escapeHtml(result.avatar)}" alt=""
                             class="avatar avatar-small" width="28" height="28" loading="lazy">
                    </a>
                    <div class="comment-body">
                        <a href="${escapeHtml(result.profile_url)}" class="comment-author">
                            ${escapeHtml(result.username)}
                        </a>
                        <span class="comment-time">${escapeHtml(result.time_ago)}</span>
                        <p class="comment-text">${linkifyHtml(result.content)}</p>
                    </div>
                </div>`;
                section.insertAdjacentHTML('beforeend', commentHtml);
            }
            // Update comment count
            const commentBtn = document.querySelector(`.btn-comment[data-post-id="${postId}"]`);
            if (commentBtn) {
                const current = parseInt(commentBtn.textContent.replace(/\D/g, ''), 10) || 0;
                commentBtn.textContent = '💬 ' + (current + 1);
            }
            if (input) input.value = '';
        } else {
            alert('Error: ' + (result.error || 'Could not post comment'));
        }
    } catch (err) {
        console.error('Comment failed:', err);
    }
});

// ── Image preview before upload ───────────────────────────────────────────────

const postImageInput = document.getElementById('post-image');
const imagePreview   = document.getElementById('image-preview');

if (postImageInput && imagePreview) {
    postImageInput.addEventListener('change', () => {
        const file = postImageInput.files[0];
        if (!file) {
            imagePreview.innerHTML = '';
            return;
        }

        if (!file.type.startsWith('image/') && !file.type.startsWith('video/')) {
            imagePreview.innerHTML = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = (ev) => {
            if (file.type.startsWith('image/')) {
                imagePreview.innerHTML =
                    `<img src="${escapeHtml(ev.target.result)}" alt="Preview">`;
            } else {
                imagePreview.innerHTML =
                    `<span>🎥 ${escapeHtml(file.name)}</span>`;
            }
        };
        reader.readAsDataURL(file);
    });
}

// ── Avatar crop tool ──────────────────────────────────────────────────────────

const avatarInput     = document.getElementById('avatar-input');
const cropContainer   = document.getElementById('avatar-crop-container');
const cropCanvas      = document.getElementById('avatar-crop-canvas');

if (avatarInput && cropContainer && cropCanvas) {
    let cropState = { startX: 0, startY: 0, endX: 0, endY: 0, isDragging: false };
    let sourceImage = null;
    let imgScale    = 1;

    avatarInput.addEventListener('change', () => {
        const file = avatarInput.files[0];
        if (!file || !file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = (ev) => {
            const img = new Image();
            img.onload = () => {
                sourceImage = img;
                const maxW = 360;
                imgScale    = Math.min(maxW / img.width, maxW / img.height, 1);
                cropCanvas.width  = Math.round(img.width  * imgScale);
                cropCanvas.height = Math.round(img.height * imgScale);

                const ctx = cropCanvas.getContext('2d');
                ctx.drawImage(img, 0, 0, cropCanvas.width, cropCanvas.height);
                cropContainer.style.display = 'block';

                // Default crop: full image square
                const side = Math.min(cropCanvas.width, cropCanvas.height);
                const cx   = (cropCanvas.width  - side) / 2;
                const cy   = (cropCanvas.height - side) / 2;
                updateCropInputs(
                    Math.round(cx / imgScale),
                    Math.round(cy / imgScale),
                    Math.round(side / imgScale),
                    Math.round(side / imgScale)
                );
                drawCropOverlay(cx, cy, side, side);
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    /** Normalise a pointer event to canvas-relative coordinates */
    function getCanvasPos(e) {
        const rect = cropCanvas.getBoundingClientRect();
        const src  = e.touches ? e.touches[0] : e;
        return {
            x: Math.min(Math.max(src.clientX - rect.left, 0), cropCanvas.width),
            y: Math.min(Math.max(src.clientY - rect.top,  0), cropCanvas.height),
        };
    }

    function onCropStart(e) {
        e.preventDefault();
        const pos = getCanvasPos(e);
        cropState.startX     = pos.x;
        cropState.startY     = pos.y;
        cropState.isDragging = true;
    }
    function onCropMove(e) {
        if (!cropState.isDragging || !sourceImage) return;
        e.preventDefault();
        const pos = getCanvasPos(e);
        cropState.endX = pos.x;
        cropState.endY = pos.y;

        const w    = Math.abs(cropState.endX - cropState.startX);
        const h    = Math.abs(cropState.endY - cropState.startY);
        const side = Math.max(w, h);
        const x    = Math.min(cropState.startX, cropState.endX);
        const y    = Math.min(cropState.startY, cropState.endY);

        drawCropOverlay(x, y, side, side);
        updateCropInputs(
            Math.round(x / imgScale),
            Math.round(y / imgScale),
            Math.round(side / imgScale),
            Math.round(side / imgScale)
        );
    }
    function onCropEnd(e) { cropState.isDragging = false; }

    cropCanvas.addEventListener('mousedown',  onCropStart);
    cropCanvas.addEventListener('mousemove',  onCropMove);
    cropCanvas.addEventListener('mouseup',    onCropEnd);
    cropCanvas.addEventListener('touchstart', onCropStart, { passive: false });
    cropCanvas.addEventListener('touchmove',  onCropMove,  { passive: false });
    cropCanvas.addEventListener('touchend',   onCropEnd);

    function drawCropOverlay(x, y, w, h) {
        if (!sourceImage) return;
        const ctx = cropCanvas.getContext('2d');
        ctx.clearRect(0, 0, cropCanvas.width, cropCanvas.height);
        ctx.drawImage(sourceImage, 0, 0, cropCanvas.width, cropCanvas.height);
        // Darken non-crop area
        ctx.fillStyle = 'rgba(0,0,0,0.5)';
        ctx.fillRect(0, 0, cropCanvas.width, cropCanvas.height);
        // Clear the crop region
        ctx.clearRect(x, y, w, h);
        ctx.drawImage(sourceImage, x / imgScale, y / imgScale, w / imgScale, h / imgScale, x, y, w, h);
        // Draw border
        ctx.strokeStyle = '#e94560';
        ctx.lineWidth   = 2;
        ctx.strokeRect(x, y, w, h);
    }

    function updateCropInputs(x, y, w, h) {
        document.getElementById('crop-x').value = x;
        document.getElementById('crop-y').value = y;
        document.getElementById('crop-w').value = w;
        document.getElementById('crop-h').value = h;
    }
}

// ── Gallery dropzone ──────────────────────────────────────────────────────────

(function initGalleryDropzone() {
    const dropzone    = document.getElementById('gallery-dropzone');
    const fileInput   = document.getElementById('gallery-file-input');
    const previewsEl  = document.getElementById('dropzone-previews');
    const uploadForm  = document.getElementById('gallery-upload-form');
    const uploadBtn   = document.getElementById('gallery-upload-btn');

    if (!dropzone || !fileInput || !previewsEl || !uploadForm) return;

    let selectedFiles = [];

    // Click anywhere in dropzone to open file picker
    dropzone.addEventListener('click', (e) => {
        if (e.target.closest('.preview-remove') || e.target.closest('#gallery-upload-btn')) return;
        fileInput.click();
    });

    // Drag-and-drop events
    dropzone.addEventListener('dragenter', (e) => { e.preventDefault(); dropzone.classList.add('drag-over'); });
    dropzone.addEventListener('dragover',  (e) => { e.preventDefault(); dropzone.classList.add('drag-over'); });
    dropzone.addEventListener('dragleave', (e) => {
        if (!dropzone.contains(e.relatedTarget)) dropzone.classList.remove('drag-over');
    });
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        addFiles(Array.from(e.dataTransfer.files));
    });

    // File input change
    fileInput.addEventListener('change', () => {
        addFiles(Array.from(fileInput.files));
        fileInput.value = ''; // reset so same file can be re-added
    });

    function addFiles(files) {
        files.forEach((file) => {
            if (!file.type.startsWith('image/') && !file.type.startsWith('video/')) return;
            selectedFiles.push(file);
            renderPreview(file);
        });
        updateUploadButton();
    }

    function renderPreview(file) {
        const item = document.createElement('div');
        item.className = 'dropzone-preview-item';

        const removeBtn = document.createElement('button');
        removeBtn.type        = 'button';
        removeBtn.className   = 'preview-remove';
        removeBtn.textContent = '✕';
        removeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const idx = selectedFiles.indexOf(file);
            if (idx !== -1) selectedFiles.splice(idx, 1);
            item.remove();
            updateUploadButton();
        });

        const nameEl       = document.createElement('div');
        nameEl.className   = 'preview-name';
        nameEl.textContent = file.name;

        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.alt   = '';
            const reader = new FileReader();
            reader.onload = (ev) => { img.src = ev.target.result; };
            reader.readAsDataURL(file);
            item.appendChild(img);
        } else {
            const icon       = document.createElement('div');
            icon.className   = 'dropzone-video-icon';
            icon.textContent = '🎥';
            item.appendChild(icon);
        }

        item.appendChild(removeBtn);
        item.appendChild(nameEl);
        previewsEl.appendChild(item);
    }

    function updateUploadButton() {
        if (!uploadBtn) return;
        const count = selectedFiles.length;
        if (count > 0) {
            uploadBtn.style.display = 'inline-block';
            uploadBtn.textContent   = 'Upload ' + count + ' file' + (count !== 1 ? 's' : '');
        } else {
            uploadBtn.style.display = 'none';
        }
    }

    /**
     * The three server-side processing stages shown in the progress bar.
     * UPLOAD  = data transfer to server  (bar: 0 – 60 %)
     * SCRUB   = EXIF/metadata stripping  (bar: 62 – 80 %)
     * RESIZE  = generating image sizes   (bar: 82 – 99 %)
     */
    const STAGES = [
        { key: 'upload', icon: '↑', label: 'Upload' },
        { key: 'scrub',  icon: '✦', label: 'Scrub'  },
        { key: 'resize', icon: '⊞', label: 'Resize' },
    ];

    // Progress bar percentages for each server-side processing step
    const PCT_SCRUB_START  = 62;   // bar value when the scrub step begins
    const PCT_RESIZE_START = 82;   // bar value when the resize step begins
    const SCRUB_DELAY_MS   = 600;  // ms to show "Scrubbing" before advancing to "Resizing"

    /** Lazily create (or retrieve) the progress bar element inside the dropzone. */
    function getProgress() {
        let el = dropzone.querySelector('.upload-progress');
        if (!el) {
            // Build step indicators
            const stepsHtml = STAGES.map((s, i) => {
                const line = i < STAGES.length - 1
                    ? '<div class="upload-step-line"></div>'
                    : '';
                return '<div class="upload-step" data-step="' + s.key + '">'
                     +   '<div class="upload-step-icon">' + s.icon + '</div>'
                     +   '<div class="upload-step-label">' + s.label + '</div>'
                     + '</div>'
                     + line;
            }).join('');

            el = document.createElement('div');
            el.className  = 'upload-progress';
            el.innerHTML  = '<div class="upload-progress-steps">' + stepsHtml + '</div>'
                          + '<div class="upload-progress-track">'
                          +   '<div class="upload-progress-fill"></div>'
                          + '</div>'
                          + '<div class="upload-progress-info"></div>';
            dropzone.appendChild(el);
        }
        return el;
    }

    /**
     * Update the progress bar.
     * @param {number} pct   - Fill percentage (0–100).
     * @param {string} label - Status text.
     * @param {string} [step] - Key of the current stage ('upload'|'scrub'|'resize'|'done').
     */
    function setProgress(pct, label, step) {
        const el = getProgress();
        el.querySelector('.upload-progress-fill').style.width = pct + '%';
        el.querySelector('.upload-progress-info').textContent = label;
        el.style.display = 'block';

        if (step) {
            const currentIdx = STAGES.findIndex((s) => s.key === step);
            el.querySelectorAll('.upload-step').forEach((stepEl, i) => {
                stepEl.classList.remove('active', 'done');
                if (step === 'done' || i < currentIdx) {
                    stepEl.classList.add('done');
                } else if (i === currentIdx) {
                    stepEl.classList.add('active');
                }
            });
            // Update connector lines
            el.querySelectorAll('.upload-step-line').forEach((line, i) => {
                line.classList.toggle('done', step === 'done' || i < currentIdx);
            });
        }
    }

    function hideProgress() {
        const el = dropzone.querySelector('.upload-progress');
        if (el) el.style.display = 'none';
    }

    // On form submit, upload via XHR to show real progress
    uploadForm.addEventListener('submit', (e) => {
        e.preventDefault();
        if (selectedFiles.length === 0) return;

        const dt = new DataTransfer();
        selectedFiles.forEach((f) => dt.items.add(f));
        fileInput.files = dt.files;

        const formData    = new FormData(uploadForm);
        const totalFiles  = selectedFiles.length;
        const fileWord    = (n) => n + ' file' + (n !== 1 ? 's' : '');

        setProgress(0, 'Preparing ' + fileWord(totalFiles) + '…', 'upload');
        if (uploadBtn) uploadBtn.disabled = true;

        const xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        // Track whether the server has already responded (to avoid overwriting the
        // final state with a late-firing processing animation timer).
        let serverDone = false;
        let processingTimer = null;

        xhr.upload.addEventListener('progress', (ev) => {
            if (ev.lengthComputable) {
                // Map real upload progress to 0–60 % of the bar
                const ratio = ev.loaded / ev.total;
                const pct   = Math.round(ratio * 60);
                setProgress(pct, 'Uploading ' + fileWord(totalFiles) + '… ' + Math.round(ratio * 100) + '%', 'upload');
            }
        });

        // All bytes sent — server is now scrubbing and resizing
        xhr.upload.addEventListener('load', () => {
            if (serverDone) return;
            setProgress(PCT_SCRUB_START, 'Scrubbing metadata…', 'scrub');
            processingTimer = setTimeout(() => {
                if (!serverDone) {
                    setProgress(PCT_RESIZE_START, 'Resizing images…', 'resize');
                }
            }, SCRUB_DELAY_MS);
        });

        xhr.addEventListener('load', () => {
            serverDone = true;
            if (processingTimer) clearTimeout(processingTimer);

            let redirectUrl = window.location.href;
            try {
                const data = JSON.parse(xhr.responseText);
                redirectUrl = data.redirect || redirectUrl;
                const msgs = [];
                if (data.uploaded > 0) {
                    msgs.push(fileWord(data.uploaded) + ' uploaded successfully.');
                }
                if (data.errors && data.errors.length > 0) {
                    msgs.push(...data.errors);
                }
                setProgress(100, msgs.length > 0 ? msgs.join(' ') : 'Done.', 'done');
            } catch (_) {
                // Server returned non-JSON; fall back to the current page
                setProgress(100, 'Done.', 'done');
            }
            setTimeout(() => { window.location.href = redirectUrl; }, 800);
        });

        xhr.addEventListener('error', () => {
            serverDone = true;
            if (processingTimer) clearTimeout(processingTimer);
            hideProgress();
            if (uploadBtn) uploadBtn.disabled = false;
            alert('Upload failed. Please try again.');
        });

        xhr.send(formData);
    });
})();

// ── Cover crop modal ──────────────────────────────────────────────────────────

(function initCoverCropModal() {
    const modal       = document.getElementById('cover-crop-modal');
    const canvas      = document.getElementById('cover-crop-canvas');
    const cancelBtn   = document.getElementById('cover-crop-cancel');
    const albumIdEl   = document.getElementById('cover-album-id');
    const mediaIdEl   = document.getElementById('cover-media-id');

    if (!modal || !canvas) return;

    let coverImage = null;
    let imgScale          = 1;
    let canvasToOrigScale = 1; // converts canvas px → original image px
    let cropState  = { startX: 0, startY: 0, endX: 0, endY: 0, isDragging: false,
                       mode: 'draw', moveOX: 0, moveOY: 0 };
    let currentCrop = { x: 0, y: 0, w: 0, h: 0 };

    // Open modal when "Set as Cover" is clicked
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.set-cover-btn');
        if (!btn) return;

        const mediaSrc  = btn.dataset.mediaSrc;
        const mediaId   = btn.dataset.mediaId;
        const albumId   = btn.dataset.albumId;
        const origWidth  = parseInt(btn.dataset.origWidth,  10) || 0;
        const origHeight = parseInt(btn.dataset.origHeight, 10) || 0;

        albumIdEl.value = albumId;
        mediaIdEl.value = mediaId;

        // Load the image into the canvas
        const img = new Image();
        img.onload = () => {
            coverImage = img;
            const maxW = 480;
            imgScale   = Math.min(maxW / img.width, maxW / img.height, 1);
            canvas.width  = Math.round(img.width  * imgScale);
            canvas.height = Math.round(img.height * imgScale);

            // canvasToOrigScale converts canvas coordinates to original-image coordinates.
            // The mediaSrc is a scaled-down version of the original; use the stored
            // original dimensions so crop coordinates sent to the server are correct.
            canvasToOrigScale = (origWidth > 0 ? origWidth : img.width) / canvas.width;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            // Default: full-square crop centred
            const side = Math.min(canvas.width, canvas.height);
            const cx   = Math.round((canvas.width  - side) / 2);
            const cy   = Math.round((canvas.height - side) / 2);
            currentCrop = { x: cx, y: cy, w: side, h: side };
            drawCoverOverlay(cx, cy, side, side);
            setCoverCropInputs(
                Math.round(cx   * canvasToOrigScale),
                Math.round(cy   * canvasToOrigScale),
                Math.round(side * canvasToOrigScale),
                Math.round(side * canvasToOrigScale)
            );

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        };
        img.src = mediaSrc;
    });

    // Close modal
    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        coverImage = null;
    }
    cancelBtn && cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

    /** Normalise pointer coordinates relative to canvas */
    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const src  = e.touches ? e.touches[0] : e;
        return {
            x: Math.min(Math.max(src.clientX - rect.left, 0), canvas.width),
            y: Math.min(Math.max(src.clientY - rect.top,  0), canvas.height),
        };
    }

    function onStart(e) {
        e.preventDefault();
        const pos = getPos(e);
        // Click inside existing crop box → move it; outside → draw a new one
        if (currentCrop.w > 0 &&
            pos.x >= currentCrop.x && pos.x <= currentCrop.x + currentCrop.w &&
            pos.y >= currentCrop.y && pos.y <= currentCrop.y + currentCrop.h) {
            cropState.mode   = 'move';
            cropState.moveOX = pos.x - currentCrop.x;
            cropState.moveOY = pos.y - currentCrop.y;
        } else {
            cropState.mode   = 'draw';
            cropState.startX = pos.x;
            cropState.startY = pos.y;
        }
        cropState.isDragging = true;
    }
    function onMove(e) {
        if (!coverImage) return;
        const pos = getPos(e);

        if (!cropState.isDragging) {
            // Update cursor to indicate whether a drag will move or draw
            const inside = currentCrop.w > 0 &&
                pos.x >= currentCrop.x && pos.x <= currentCrop.x + currentCrop.w &&
                pos.y >= currentCrop.y && pos.y <= currentCrop.y + currentCrop.h;
            canvas.style.cursor = inside ? 'move' : 'crosshair';
            return;
        }

        e.preventDefault();

        if (cropState.mode === 'move') {
            // Drag the existing crop box
            let nx = pos.x - cropState.moveOX;
            let ny = pos.y - cropState.moveOY;
            nx = Math.min(Math.max(nx, 0), canvas.width  - currentCrop.w);
            ny = Math.min(Math.max(ny, 0), canvas.height - currentCrop.h);
            currentCrop.x = nx;
            currentCrop.y = ny;
            drawCoverOverlay(nx, ny, currentCrop.w, currentCrop.h);
            setCoverCropInputs(
                Math.round(nx             * canvasToOrigScale),
                Math.round(ny             * canvasToOrigScale),
                Math.round(currentCrop.w  * canvasToOrigScale),
                Math.round(currentCrop.h  * canvasToOrigScale)
            );
        } else {
            // Draw a new crop selection (square-constrained)
            cropState.endX = pos.x;
            cropState.endY = pos.y;

            const w    = Math.abs(cropState.endX - cropState.startX);
            const h    = Math.abs(cropState.endY - cropState.startY);
            const side = Math.max(w, h);
            const x    = Math.min(cropState.startX, cropState.endX);
            const y    = Math.min(cropState.startY, cropState.endY);

            currentCrop = { x, y, w: side, h: side };
            drawCoverOverlay(x, y, side, side);
            setCoverCropInputs(
                Math.round(x    * canvasToOrigScale),
                Math.round(y    * canvasToOrigScale),
                Math.round(side * canvasToOrigScale),
                Math.round(side * canvasToOrigScale)
            );
        }
    }
    function onEnd() { cropState.isDragging = false; }

    canvas.addEventListener('mousedown',  onStart);
    canvas.addEventListener('mousemove',  onMove);
    canvas.addEventListener('mouseup',    onEnd);
    canvas.addEventListener('touchstart', onStart, { passive: false });
    canvas.addEventListener('touchmove',  onMove,  { passive: false });
    canvas.addEventListener('touchend',   onEnd);

    function drawCoverOverlay(x, y, w, h) {
        if (!coverImage) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(coverImage, 0, 0, canvas.width, canvas.height);
        ctx.fillStyle = 'rgba(0,0,0,0.52)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        if (w > 0 && h > 0) {
            ctx.clearRect(x, y, w, h);
            ctx.drawImage(coverImage, x / imgScale, y / imgScale, w / imgScale, h / imgScale, x, y, w, h);
            ctx.strokeStyle = '#e94560';
            ctx.lineWidth   = 2;
            ctx.strokeRect(x, y, w, h);
        }
    }

    function setCoverCropInputs(x, y, w, h) {
        document.getElementById('cover-crop-x').value = x;
        document.getElementById('cover-crop-y').value = y;
        document.getElementById('cover-crop-w').value = w;
        document.getElementById('cover-crop-h').value = h;
    }
})();

// ── Banner crop tool (admin/settings.php) ─────────────────────────────────────

(function initBannerCrop() {
    const fileInput  = document.getElementById('banner-file-input');
    const container  = document.getElementById('banner-crop-container');
    const canvas     = document.getElementById('banner-crop-canvas');
    const resetBtn   = document.getElementById('banner-crop-reset');
    const infoEl     = document.getElementById('banner-crop-info');

    if (!fileInput || !container || !canvas) return;

    // Target aspect ratio: 1400 × 250 = 28 : 5
    const RATIO = 1400 / 250;

    let sourceImage = null;
    let imgScale    = 1;
    let cropState   = { startX: 0, startY: 0, endX: 0, endY: 0,
                        isDragging: false, mode: 'draw', moveOX: 0, moveOY: 0 };
    let currentCrop = { x: 0, y: 0, w: 0, h: 0 };

    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (!file || !file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = (ev) => {
            const img = new Image();
            img.onload = () => {
                sourceImage = img;
                const maxW = 700;
                imgScale   = Math.min(maxW / img.width, 1);
                canvas.width  = Math.round(img.width  * imgScale);
                canvas.height = Math.round(img.height * imgScale);

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                container.style.display = 'block';

                // Default crop: widest 7:2 region centred
                setDefaultCrop();
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    function setDefaultCrop() {
        if (!sourceImage) return;
        const cw = canvas.width;
        const ch = canvas.height;

        let w, h;
        if (cw / ch >= RATIO) {
            // Image is wider than 7:2 — constrain by height
            h = ch;
            w = Math.min(cw, Math.round(h * RATIO));
        } else {
            // Image is taller — constrain by width
            w = cw;
            h = Math.min(ch, Math.round(w / RATIO));
        }
        const x = Math.round((cw - w) / 2);
        const y = Math.round((ch - h) / 2);

        currentCrop = { x, y, w, h };
        drawBannerOverlay(x, y, w, h);
        setBannerCropInputs(
            Math.round(x / imgScale),
            Math.round(y / imgScale),
            Math.round(w / imgScale),
            Math.round(h / imgScale)
        );
    }

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const src  = e.touches ? e.touches[0] : e;
        return {
            x: Math.min(Math.max(src.clientX - rect.left, 0), canvas.width),
            y: Math.min(Math.max(src.clientY - rect.top,  0), canvas.height),
        };
    }

    function onStart(e) {
        e.preventDefault();
        const pos = getPos(e);
        if (currentCrop.w > 0 &&
            pos.x >= currentCrop.x && pos.x <= currentCrop.x + currentCrop.w &&
            pos.y >= currentCrop.y && pos.y <= currentCrop.y + currentCrop.h) {
            cropState.mode   = 'move';
            cropState.moveOX = pos.x - currentCrop.x;
            cropState.moveOY = pos.y - currentCrop.y;
        } else {
            cropState.mode   = 'draw';
            cropState.startX = pos.x;
            cropState.startY = pos.y;
        }
        cropState.isDragging = true;
    }

    function onMove(e) {
        if (!sourceImage) return;
        const pos = getPos(e);

        if (!cropState.isDragging) {
            const inside = currentCrop.w > 0 &&
                pos.x >= currentCrop.x && pos.x <= currentCrop.x + currentCrop.w &&
                pos.y >= currentCrop.y && pos.y <= currentCrop.y + currentCrop.h;
            canvas.style.cursor = inside ? 'move' : 'crosshair';
            return;
        }
        e.preventDefault();

        if (cropState.mode === 'move') {
            let nx = pos.x - cropState.moveOX;
            let ny = pos.y - cropState.moveOY;
            nx = Math.min(Math.max(nx, 0), canvas.width  - currentCrop.w);
            ny = Math.min(Math.max(ny, 0), canvas.height - currentCrop.h);
            currentCrop.x = nx;
            currentCrop.y = ny;
            drawBannerOverlay(nx, ny, currentCrop.w, currentCrop.h);
            setBannerCropInputs(
                Math.round(nx / imgScale),
                Math.round(ny / imgScale),
                Math.round(currentCrop.w / imgScale),
                Math.round(currentCrop.h / imgScale)
            );
        } else {
            // Draw new crop constrained to 7:2 ratio
            const rawW = Math.abs(pos.x - cropState.startX);
            const rawH = Math.abs(pos.y - cropState.startY);
            let w = rawW;
            let h = Math.round(w / RATIO);
            if (h > rawH && rawH > 0) {
                h = rawH;
                w = Math.round(h * RATIO);
            }
            w = Math.min(w, canvas.width);
            h = Math.min(h, canvas.height);

            const x = Math.min(Math.max(cropState.startX, 0), canvas.width  - w);
            const y = Math.min(Math.max(cropState.startY, 0), canvas.height - h);

            currentCrop = { x, y, w, h };
            drawBannerOverlay(x, y, w, h);
            setBannerCropInputs(
                Math.round(x / imgScale),
                Math.round(y / imgScale),
                Math.round(w / imgScale),
                Math.round(h / imgScale)
            );
        }
    }

    function onEnd() { cropState.isDragging = false; }

    canvas.addEventListener('mousedown',  onStart);
    canvas.addEventListener('mousemove',  onMove);
    canvas.addEventListener('mouseup',    onEnd);
    canvas.addEventListener('touchstart', onStart, { passive: false });
    canvas.addEventListener('touchmove',  onMove,  { passive: false });
    canvas.addEventListener('touchend',   onEnd);

    resetBtn && resetBtn.addEventListener('click', setDefaultCrop);

    function drawBannerOverlay(x, y, w, h) {
        if (!sourceImage) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(sourceImage, 0, 0, canvas.width, canvas.height);
        ctx.fillStyle = 'rgba(0,0,0,0.52)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        if (w > 0 && h > 0) {
            ctx.clearRect(x, y, w, h);
            ctx.drawImage(
                sourceImage,
                x / imgScale, y / imgScale, w / imgScale, h / imgScale,
                x, y, w, h
            );
            ctx.strokeStyle = '#e94560';
            ctx.lineWidth   = 2;
            ctx.strokeRect(x, y, w, h);
        }
    }

    function setBannerCropInputs(x, y, w, h) {
        document.getElementById('banner-crop-x').value = x;
        document.getElementById('banner-crop-y').value = y;
        document.getElementById('banner-crop-w').value = w;
        document.getElementById('banner-crop-h').value = h;
        if (infoEl) infoEl.textContent = w + ' × ' + h + ' px (original)';
    }
})();

// ── Banner overlay position/size editor (admin/settings.php) ─────────────────

(function initOverlayEditor() {
    const preview      = document.getElementById('overlay-preview');
    const handle       = document.getElementById('overlay-handle');
    const xInput       = document.getElementById('overlay-x-input');
    const yInput       = document.getElementById('overlay-y-input');
    const sizeInput    = document.getElementById('overlay-size-input');
    const sizeRange    = document.getElementById('overlay-size-range');
    const sizeLabel    = document.getElementById('overlay-size-label');
    const colorInput   = document.getElementById('overlay-color-input');
    const fontSelect   = document.getElementById('overlay-font-select');
    const shadowSelect = document.getElementById('overlay-shadow-select');

    if (!preview || !handle) return;

    // CSS font-family stacks (must match admin/settings.php and includes/header.php)
    const fontMap = {
        system:  'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif',
        serif:   'Georgia,"Times New Roman",Times,serif',
        mono:    '"Courier New",Courier,monospace',
        impact:  'Impact,Haettenschweiler,"Arial Narrow Bold",sans-serif',
    };

    // CSS text-shadow presets (must match admin/settings.php and includes/header.php)
    const shadowMap = {
        none:   'none',
        light:  '0 1px 4px rgba(0,0,0,.5)',
        medium: '0 2px 8px rgba(0,0,0,.7)',
        heavy:  '0 3px 12px rgba(0,0,0,.9)',
    };

    let isDragging = false;
    let startMX = 0, startMY = 0;
    let startPX = 0, startPY = 0;   // % at drag start

    handle.addEventListener('mousedown', startDrag);
    handle.addEventListener('touchstart', startDrag, { passive: false });

    function startDrag(e) {
        e.preventDefault();
        isDragging = true;
        const src = e.touches ? e.touches[0] : e;
        startMX = src.clientX;
        startMY = src.clientY;
        startPX = parseFloat(xInput ? xInput.value : handle.style.left) || 50;
        startPY = parseFloat(yInput ? yInput.value : handle.style.top)  || 50;
    }

    document.addEventListener('mousemove', onDrag);
    document.addEventListener('touchmove', onDrag, { passive: false });

    function onDrag(e) {
        if (!isDragging) return;
        e.preventDefault();
        const src = e.touches ? e.touches[0] : e;
        const rect = preview.getBoundingClientRect();
        const dx = src.clientX - startMX;
        const dy = src.clientY - startMY;
        const newX = Math.min(Math.max(startPX + (dx / rect.width)  * 100, 0), 100);
        const newY = Math.min(Math.max(startPY + (dy / rect.height) * 100, 0), 100);

        handle.style.left = newX + '%';
        handle.style.top  = newY + '%';
        if (xInput) xInput.value = newX.toFixed(2);
        if (yInput) yInput.value = newY.toFixed(2);
    }

    document.addEventListener('mouseup',  endDrag);
    document.addEventListener('touchend', endDrag);
    function endDrag() { isDragging = false; }

    // Font-size range slider
    if (sizeRange) {
        sizeRange.addEventListener('input', () => {
            const v = parseFloat(sizeRange.value);
            handle.style.fontSize = v + 'rem';
            if (sizeInput) sizeInput.value = v.toFixed(2);
            if (sizeLabel) sizeLabel.textContent = '— ' + v.toFixed(1) + 'rem';
        });
        // Initialise label
        if (sizeLabel) sizeLabel.textContent = '— ' + parseFloat(sizeRange.value).toFixed(1) + 'rem';
    }

    // Text colour picker
    if (colorInput) {
        colorInput.addEventListener('input', () => {
            handle.style.color = colorInput.value;
        });
    }

    // Font family select — read CSS family from option's data-css-family attribute
    if (fontSelect) {
        fontSelect.addEventListener('change', () => {
            const opt = fontSelect.options[fontSelect.selectedIndex];
            handle.style.fontFamily = (opt && opt.dataset.cssFamily) || fontMap[fontSelect.value] || fontMap.system;
        });
    }

    // Drop shadow select
    if (shadowSelect) {
        shadowSelect.addEventListener('change', () => {
            handle.style.textShadow = shadowMap[shadowSelect.value] || shadowMap.medium;
        });
    }
})();

// ── Info modals (Welcome & How it Works) ─────────────────────────────────────

(function initInfoModals() {
    function openInfoModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.style.display = 'flex';
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeInfoModal(modal) {
        modal.style.display = 'none';
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    document.addEventListener('click', (e) => {
        // Open via data-modal trigger
        const trigger = e.target.closest('[data-modal]');
        if (trigger) {
            openInfoModal(trigger.dataset.modal);
            return;
        }
        // Close button inside modal
        const closeBtn = e.target.closest('.info-modal-close');
        if (closeBtn) {
            const modal = closeBtn.closest('.info-modal');
            if (modal) closeInfoModal(modal);
            return;
        }
        // Backdrop click
        if (e.target.classList.contains('info-modal')) {
            closeInfoModal(e.target);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const openModal = document.querySelector('.info-modal.is-open');
        if (openModal) closeInfoModal(openModal);
    });
})();

// ── Theme swatch selection ────────────────────────────────────────────────────

(function () {
    const swatches = document.querySelectorAll('.theme-swatch');
    const input    = document.getElementById('site-theme-input');
    const form     = document.getElementById('theme-form');

    if (!swatches.length || !input || !form) return;

    const ACTIVE_BORDER   = '#e94560';
    const ACTIVE_SHADOW   = '0 0 0 3px rgba(233,69,96,.35)';
    const INACTIVE_BORDER = 'rgba(255,255,255,.15)';

    /* Hide the manual save button – selection now auto-saves */
    const saveBtn = form.querySelector('button[type="submit"]');
    if (saveBtn) { saveBtn.style.display = 'none'; }

    function selectSwatch(sw) {
        /* Skip if this swatch is already the active theme */
        if (sw.classList.contains('theme-swatch--active')) { return; }

        swatches.forEach((s) => {
            const isActive = s === sw;
            s.setAttribute('aria-checked', isActive ? 'true' : 'false');
            s.style.borderColor = isActive ? ACTIVE_BORDER : INACTIVE_BORDER;
            s.style.boxShadow   = isActive ? ACTIVE_SHADOW : '';
            s.classList.toggle('theme-swatch--active', isActive);
            const label = s.querySelector('div:last-child');
            if (label) { label.textContent = isActive ? '\u2713 Active' : '\u00A0'; }
        });
        input.value = sw.dataset.themeSlug;
        form.submit();
    }

    swatches.forEach((sw) => {
        sw.addEventListener('click', () => selectSwatch(sw));
        sw.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectSwatch(sw);
            }
        });
    });
}());

// ── Back-to-top button ────────────────────────────────────────────────────────
(function () {
    const btn = document.getElementById('back-to-top');
    if (!btn) return;

    let ticking = false;

    function onScroll() {
        if (window.scrollY > 300) {
            btn.classList.add('visible');
        } else {
            btn.classList.remove('visible');
        }
        ticking = false;
    }

    window.addEventListener('scroll', function () {
        if (!ticking) {
            window.requestAnimationFrame(onScroll);
            ticking = true;
        }
    }, { passive: true });

    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Handle initial state (e.g. page restored with scroll position)
    onScroll();
}());

// ── Load More wall posts ──────────────────────────────────────────────────────

(function () {
    'use strict';

    const wrap    = document.getElementById('load-more-wrap');
    const btn     = document.getElementById('load-more-btn');
    const feed    = document.getElementById('post-feed');

    if (!wrap || !btn || !feed) return;

    // Hide the button immediately if there are no more posts to load
    if (feed.dataset.hasMore !== '1') {
        wrap.classList.add('hidden');
        return;
    }

    // Number of posts loaded per batch (must match PHP $limit in load_posts.php)
    const BATCH_SIZE = parseInt(feed.dataset.offset || '10', 10);
    let offset   = BATCH_SIZE;
    let loading  = false;
    let sentinel = null; // IntersectionObserver watching the last post

    /**
     * Show the "Load More" button only when the last post in the feed
     * enters the viewport.  This replaces any previous sentinel observer.
     */
    function watchLastPost() {
        if (sentinel) {
            sentinel.disconnect();
            sentinel = null;
        }

        const posts = feed.querySelectorAll('.post-item');
        if (!posts.length) return;
        const lastPost = posts[posts.length - 1];

        if (!('IntersectionObserver' in window)) {
            // Fallback for very old browsers: always show the button
            wrap.classList.remove('hidden');
            return;
        }

        sentinel = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        wrap.classList.remove('hidden');
                    } else {
                        wrap.classList.add('hidden');
                    }
                });
            },
            { rootMargin: '0px', threshold: 0 }
        );
        sentinel.observe(lastPost);
    }

    // Start hidden; the sentinel will reveal the button as needed
    wrap.classList.add('hidden');
    watchLastPost();

    btn.addEventListener('click', async function () {
        if (loading) return;
        loading = true;
        btn.disabled    = true;
        btn.textContent = 'Loading\u2026';

        const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';

        try {
            const resp   = await fetch(
                baseUrl + '/modules/wall/load_posts.php?offset=' + encodeURIComponent(offset),
                { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const result = await resp.json();

            if (result.ok) {
                // Remember how many post items exist before inserting new ones
                const countBefore = feed.querySelectorAll('.post-item').length;

                feed.insertAdjacentHTML('beforeend', result.html);
                offset += BATCH_SIZE;

                // Initialise lazy images and lightbox triggers in the new posts only
                const allPosts  = feed.querySelectorAll('.post-item');
                const newPosts  = Array.from(allPosts).slice(countBefore);
                const lazyObs   = typeof window.lazyObserveImages === 'function' ? window.lazyObserveImages   : null;
                const lbBindNew = typeof window.lightboxBindNew    === 'function' ? window.lightboxBindNew    : null;
                newPosts.forEach(post => {
                    if (lazyObs)   lazyObs(post);
                    if (lbBindNew) lbBindNew(post);
                });

                if (!result.has_more) {
                    wrap.classList.add('hidden');
                    if (sentinel) {
                        sentinel.disconnect();
                        sentinel = null;
                    }
                } else {
                    // Update the sentinel to watch the new last post
                    watchLastPost();
                }
            } else {
                alert('Could not load more posts. Please try again.');
            }
        } catch (err) {
            console.error('Load more failed:', err);
            alert('Failed to load more posts. Please try again.');
        } finally {
            loading         = false;
            btn.disabled    = false;
            btn.textContent = 'Load More';
        }
    });
}());
