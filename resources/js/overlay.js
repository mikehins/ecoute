/**
 * Ecoute Overlay — DOM capture and admin feedback widget.
 *
 * Reads configuration from the <script> tag's data attributes:
 *   data-endpoint  The POST URL for submitting captures.
 *   data-csrf      The Laravel CSRF token.
 */
(function () {
    'use strict';

    // document.currentScript is null for deferred scripts; locate the tag by src instead.
    const script        = document.currentScript
        ?? document.querySelector('script[src*="vendor/ecoute/overlay.js"]');
    const endpoint      = script?.dataset.endpoint;
    const previewUrl    = script?.dataset.preview;
    const templatesUrl  = script?.dataset.templates;
    const csrf          = script?.dataset.csrf;

    if (!endpoint || !csrf) {
        console.error('[Ecoute] Missing data-endpoint or data-csrf on script tag.');
        return;
    }

    const diagnosticsEnabled = script?.dataset.diagnostics === 'true';

    // ── Browser Diagnostics ───────────────────────────────────────────────────
    // Privacy-first: never capture request/response bodies, headers, cookies,
    // auth tokens, or full query strings. Console args are stringified only;
    // objects and errors are truncated to a safe depth.

    const CONSOLE_MAX  = 50;
    const NETWORK_MAX  = 100;
    let consoleBuffer  = [];
    let networkBuffer  = [];

    if (diagnosticsEnabled) {
        function captureNetwork(url, method, status, duration) {
            var clean = String(url).replace(/[?#].*$/, '').slice(0, 2000);
            if (networkBuffer.length >= NETWORK_MAX) { networkBuffer.shift(); }
            networkBuffer.push({
                url: clean,
                method: method,
                status: status,
                duration: Math.round(duration),
                timestamp: new Date().toISOString(),
            });
        }

        (function hookConsole() {
            var original = { log: console.log, warn: console.warn, error: console.error };

            function capture(level, args) {
                if (consoleBuffer.length >= CONSOLE_MAX) { consoleBuffer.shift(); }
                consoleBuffer.push({
                    level: level,
                    args: Array.from(args).map(function (a) {
                        if (a instanceof Error) { return a.message; }
                        if (typeof a === 'object') {
                            try { return JSON.stringify(a).slice(0, 200); }
                            catch (_) { return String(a).slice(0, 200); }
                        }
                        return String(a).slice(0, 500);
                    }).slice(0, 10),
                    timestamp: new Date().toISOString(),
                });
            }

            console.log   = function () { capture('log',   arguments); original.log.apply(console, arguments); };
            console.warn  = function () { capture('warn',  arguments); original.warn.apply(console, arguments); };
            console.error = function () { capture('error', arguments); original.error.apply(console, arguments); };
        })();

        (function hookNetwork() {
            // Monkeypatch fetch
            var originalFetch = window.fetch;
            window.fetch = function (url, options) {
                var start = performance.now();
                var method = (options && options.method) || 'GET';
                var urlStr = typeof url === 'string' ? url : (url instanceof Request ? url.url : String(url));

                return originalFetch.apply(this, arguments).then(function (response) {
                    captureNetwork(urlStr, method, response.status, performance.now() - start);
                    return response;
                }).catch(function (err) {
                    captureNetwork(urlStr, method, 0, performance.now() - start);
                    throw err;
                });
            };

            // Monkeypatch XMLHttpRequest
            var OrigXHR = window.XMLHttpRequest;
            window.XMLHttpRequest = function () {
                var xhr = new OrigXHR();
                var method = 'GET';
                var url = '';
                var start = 0;

                var origOpen = xhr.open;
                xhr.open = function (m, u) {
                    method = m;
                    url = String(u);
                    return origOpen.apply(xhr, arguments);
                };

                var origSend = xhr.send;
                xhr.send = function () {
                    start = performance.now();
                    xhr.addEventListener('loadend', function () {
                        captureNetwork(url, method, xhr.status, performance.now() - start);
                    });
                    return origSend.apply(xhr, arguments);
                };

                return xhr;
            };
            window.XMLHttpRequest.prototype = OrigXHR.prototype;
        })();

        (function hookResources() {
            if (!window.PerformanceObserver) { return; }

            try {
                var observer = new PerformanceObserver(function (list) {
                    list.getEntries().forEach(function (entry) {
                        // Only capture resource loads (script, css, img, font, etc.)
                        // that the fetch/XHR hooks may not see.
                        if (entry.initiatorType === 'fetch' || entry.initiatorType === 'xmlhttprequest') {
                            return; // Already captured by fetch/XHR hooks
                        }

                        var url = entry.name;
                        // Same-origin-only: skip third-party CDNs, analytics, etc.
                        if (url.indexOf(window.location.origin) !== 0) { return; }

                        captureNetwork(url, 'GET', 0, entry.duration);
                    });
                });

                observer.observe({ type: 'resource', buffered: true });
            } catch (_) {
                // PerformanceObserver not available or threw — silent ignore
            }
        })();
    }

    // ── html-to-image Loader ──────────────────────────────────────────────────

    /**
     * Load html-to-image from CDN if it is not already on the page.
     * Uses SVG foreignObject rendering so the browser handles all modern CSS
     * color functions (oklch, color-mix, etc.) natively — unlike html2canvas.
     */
    (function loadHtmlToImage() {
        if (window.htmlToImage) { return; }
        return new Promise(function (resolve, reject) {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/html-to-image@1.11.11/dist/html-to-image.js';
            script.integrity = 'sha512-zPMZ/3MBK+R1rv6KcBFcf7rGwLnKS+xtB2OnWkAxgC6anqxlDhl/wMWtDbiYI4rgi/NrCJdXrmNGB8pIq+slJQ==';
            script.crossOrigin = 'anonymous';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }());

    // ── Shortcut Parsing ──────────────────────────────────────────────────────

    /**
     * Parse a shortcut string like "ctrl+shift+e" into a structured object.
     *
     * @param  {string} raw  e.g. "ctrl+shift+e" or "alt+f"
     * @returns {{ ctrl: boolean, alt: boolean, shift: boolean, meta: boolean, key: string }}
     */
    function parseShortcut(raw) {
        const parts    = raw.toLowerCase().split('+');
        const modifier = function (name) { return parts.includes(name); };
        return {
            ctrl:  modifier('ctrl'),
            alt:   modifier('alt'),
            shift: modifier('shift'),
            meta:  modifier('meta'),
            key:   parts.find(function (p) { return !['ctrl', 'alt', 'shift', 'meta'].includes(p); }) || '',
        };
    }

    /**
     * Return a human-readable label for the shortcut, e.g. "Ctrl+Shift+E".
     *
     * @param  {{ ctrl: boolean, alt: boolean, shift: boolean, meta: boolean, key: string }} s
     * @returns {string}
     */
    function shortcutLabel(s) {
        const parts = [];
        if (s.ctrl)  { parts.push('Ctrl'); }
        if (s.alt)   { parts.push('Alt'); }
        if (s.shift) { parts.push('Shift'); }
        if (s.meta)  { parts.push('Meta'); }
        parts.push(s.key.toUpperCase());
        return parts.join('+');
    }

    const shortcut = parseShortcut(script?.dataset.shortcut || 'ctrl+shift+e');

    // ── State ────────────────────────────────────────────────────────────────
    let selectedElement = null;
    let overlayActive   = false;

    // ── UI Elements ──────────────────────────────────────────────────────────
    const panel = createPanel();
    document.body.appendChild(panel);

    // ── Keyboard shortcut (configurable via data-shortcut) ───────────────────
    document.addEventListener('keydown', function (e) {
        // Never steal keystrokes when the user is typing in any input or textarea
        const tag = document.activeElement && document.activeElement.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
            return;
        }

        if (e.key === 'Escape' && overlayActive) {
            deactivate();
            return;
        }

        const eventKey = e.key.toLowerCase();
        const shortcutKey = shortcut.key;

        // Try direct character match first (e.g. 'e' === 'e')
        let keyMatches = eventKey === shortcutKey;
        
        // Fallback for Shift + Digit shortcuts (e.g. shift+8 producing '*')
        if (!keyMatches && /^\d$/.test(shortcutKey)) {
            keyMatches = e.code === 'Digit' + shortcutKey;
        }

        if (e.ctrlKey  === shortcut.ctrl
         && e.altKey   === shortcut.alt
         && e.shiftKey === shortcut.shift
         && e.metaKey  === shortcut.meta
         && keyMatches) {
            overlayActive ? deactivate() : activate();
        }
    });

    // ── Activation ───────────────────────────────────────────────────────────

    function activate() {
        overlayActive = true;
        document.body.classList.add('ecoute-active');
        document.addEventListener('click', handleElementClick, true);
        showStatus('Click any element to capture it. Press ' + shortcutLabel(shortcut) + ' to cancel.');
        loadTemplates();
    }

    function deactivate() {
        overlayActive = false;
        document.body.classList.remove('ecoute-active');
        document.removeEventListener('click', handleElementClick, true);
        clearHighlight();
        hidePanel();
        selectedElement = null;
    }

    // ── Element Selection ─────────────────────────────────────────────────────

    function handleElementClick(e) {
        if (panel.contains(e.target)) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        clearHighlight();
        selectedElement = e.target;
        selectedElement.classList.add('ecoute-highlight');

        document.body.classList.remove('ecoute-active');
        document.removeEventListener('click', handleElementClick, true);
        overlayActive = false;
        void document.body.offsetWidth; // Force repaint
        showPanel(selectedElement);
    }

    function clearHighlight() {
        if (selectedElement) {
            selectedElement.classList.remove('ecoute-highlight');
        }
        document.querySelectorAll('.ecoute-highlight').forEach(function (el) {
            el.classList.remove('ecoute-highlight');
        });
    }

    // ── Panel ─────────────────────────────────────────────────────────────────

    function createPanel() {
        const el = document.createElement('div');
        el.id    = 'ecoute-panel';
        el.innerHTML = `
            <div id="ecoute-panel-inner">
                <div id="ecoute-panel-header">
                    <span id="ecoute-panel-title">Ecoute Feedback</span>
                    <button id="ecoute-close" aria-label="Close Ecoute panel">&times;</button>
                </div>
                <div id="ecoute-form-view">
                    <div id="ecoute-template-wrap" style="display:none">
                        <label for="ecoute-template">Type</label>
                        <select id="ecoute-template"></select>
                    </div>
                    <div id="ecoute-prompt-header">
                        <label for="ecoute-prompt">Describe the issue:</label>
                        <div id="ecoute-prompt-tools">
                            <span id="ecoute-rec-timer"></span>
                            <button id="ecoute-rec-btn" type="button" title="Record screen" aria-label="Record screen">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <button id="ecoute-mic-btn" type="button" title="Dictate description" aria-label="Dictate description">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>
                            </button>
                        </div>
                    </div>
                    <textarea id="ecoute-prompt" rows="4" maxlength="2000" placeholder="What's wrong here?"></textarea>
                    <div id="ecoute-actions">
                        <button id="ecoute-preview-btn">Preview</button>
                        <span id="ecoute-status" class="ecoute-status"></span>
                    </div>
                </div>
                <div id="ecoute-preview-view" style="display:none">
                    <input id="ecoute-preview-title-input" type="text" maxlength="500" placeholder="Issue title" />
                    <div id="ecoute-preview-sections"></div>
                    <textarea id="ecoute-preview-fallback" rows="13" spellcheck="false" style="display:none"></textarea>
                    <div id="ecoute-preview-actions">
                        <button id="ecoute-edit-btn">← Edit</button>
                        <button id="ecoute-submit">Send</button>
                        <span id="ecoute-preview-status" class="ecoute-status"></span>
                    </div>
                </div>
            </div>
        `;

        el.style.cssText = [
            'position:fixed', 'bottom:20px', 'right:20px', 'z-index:2147483647',
            'width:340px', 'background:rgba(255,255,255,.97)', 'backdrop-filter:blur(20px)',
            '-webkit-backdrop-filter:blur(20px)',
            'border:1px solid rgba(0,0,0,.06)', 'border-radius:14px',
            'box-shadow:0 4px 24px rgba(0,0,0,.08),0 0 0 1px rgba(0,0,0,.04)',
            'font-family:Geist,system-ui,-apple-system,sans-serif', 'font-size:13px',
            'display:none',
        ].join(';');

        el.querySelector('#ecoute-close').addEventListener('click', deactivate);
        el.querySelector('#ecoute-rec-btn').addEventListener('click', toggleRecording);
        el.querySelector('#ecoute-mic-btn').addEventListener('click', toggleDictation);
        el.querySelector('#ecoute-preview-btn').addEventListener('click', requestPreview);
        el.querySelector('#ecoute-edit-btn').addEventListener('click', showFormView);
        el.querySelector('#ecoute-submit').addEventListener('click', submitCapture);

        return el;
    }

    function showPanel(element) {
        panel.style.display = 'block';
        showFormView();
        panel.querySelector('#ecoute-prompt').value = '';
        voiceUsed = false;
        showStatus('');
        panel.querySelector('#ecoute-prompt').focus();
    }

    function hidePanel() {
        panel.style.display = 'none';
    }

    function showStatus(message) {
        const statusEl = panel.querySelector('#ecoute-status');
        if (statusEl) {
            statusEl.textContent = message;
        }
        const previewStatusEl = panel.querySelector('#ecoute-preview-status');
        if (previewStatusEl) {
            previewStatusEl.textContent = message;
        }
    }

    /**
     * Show a self-dismissing toast notification.
     *
     * @param {string}      title   Bold heading text.
     * @param {string}      message Body text.
     * @param {string|null} url     Optional link shown as "View issue →".
     */
    function showToast(title, message, url) {
        const toast = document.createElement('div');
        toast.id = 'ecoute-toast';
        toast.innerHTML =
            '<div id="ecoute-toast-title">' + title + '</div>' +
            '<div id="ecoute-toast-message">' + message + '</div>' +
            (url ? '<a id="ecoute-toast-link" href="' + url + '" target="_blank">View issue &rarr;</a>' : '');

        document.body.appendChild(toast);

        // Animate in
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.classList.add('ecoute-toast-visible');
            });
        });

        // Auto-dismiss after 6 s
        setTimeout(function () {
            toast.classList.remove('ecoute-toast-visible');
            toast.addEventListener('transitionend', function () { toast.remove(); }, { once: true });
        }, 6000);
    }

    // ── Template Loader ───────────────────────────────────────────────────────

    function loadTemplates() {
        if (! templatesUrl) { return; }

        const wrap   = panel.querySelector('#ecoute-template-wrap');
        const select = panel.querySelector('#ecoute-template');

        // Show a loading state immediately so the user sees the dropdown
        select.innerHTML = '<option value="">Loading…</option>';
        select.disabled  = true;
        wrap.style.display = 'block';

        fetch(templatesUrl, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        })
            .then(function (r) { return r.json(); })
            .then(function (templates) {
                if (! templates.length) {
                    wrap.style.display = 'none';
                    return;
                }

                select.innerHTML = templates.map(function (t) {
                    return '<option value="' + t.value + '">' + t.label + '</option>';
                }).join('');
                select.disabled = false;
            })
            .catch(function () {
                wrap.style.display = 'none';
            });
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    function showFormView() {
        panel.querySelector('#ecoute-form-view').style.display = 'block';
        panel.querySelector('#ecoute-preview-view').style.display = 'none';
        panel.querySelector('#ecoute-panel-title').textContent = 'Ecoute Feedback';
        panel.style.width = '340px';
    }

    function showPreviewView(title, body, sections) {
        panel.querySelector('#ecoute-form-view').style.display = 'none';
        panel.querySelector('#ecoute-preview-view').style.display = 'block';
        panel.querySelector('#ecoute-panel-title').textContent = 'Issue Preview';
        panel.querySelector('#ecoute-preview-title-input').value = title;
        panel.style.width = '560px';

        const sectionsDiv = panel.querySelector('#ecoute-preview-sections');
        const fallback    = panel.querySelector('#ecoute-preview-fallback');

        if (sections && sections.length) {
            sectionsDiv.innerHTML = sections.map(function (s, i) { return buildSectionField(s, i); }).join('');
            sectionsDiv.style.display = 'block';
            fallback.style.display = 'none';
        } else {
            sectionsDiv.style.display = 'none';
            fallback.style.display = 'block';
            fallback.value = body;
        }
    }

    /**
     * Build an editable field HTML string for a single YAML template section.
     *
     * @param {{ label: string, type: string, description: string|null, options: string[]|undefined, value: string }} section
     * @param {number} index
     * @returns {string}
     */
    function buildSectionField(section, index) {
        const id   = 'ecoute-section-' + index;
        const desc = section.description
            ? '<div class="ecoute-section-desc">' + escHtml(section.description) + '</div>'
            : '';

        let field;

        if (section.type === 'dropdown' && section.options && section.options.length) {
            const opts = section.options.map(function (opt) {
                const sel = opt === section.value ? ' selected' : '';
                return '<option value="' + escHtml(opt) + '"' + sel + '>' + escHtml(opt) + '</option>';
            }).join('');
            field = '<select id="' + id + '" class="ecoute-section-select">' + opts + '</select>';
        } else if (section.type === 'input') {
            field = '<input id="' + id + '" type="text" class="ecoute-section-input" value="' + escHtml(section.value || '') + '" />';
        } else {
            field = '<textarea id="' + id + '" class="ecoute-section-textarea" rows="4">' + escHtml(section.value || '') + '</textarea>';
        }

        return '<div class="ecoute-section" data-label="' + escHtml(section.label) + '">'
            + '<label class="ecoute-section-label" for="' + id + '">' + escHtml(section.label) + '</label>'
            + desc
            + field
            + '</div>';
    }

    /**
     * Escape a string for safe inclusion in HTML attribute values and text content.
     *
     * @param {string} str
     * @returns {string}
     */
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Reconstruct a markdown body from the currently displayed section fields.
     * Used when submitting from the preview view.
     *
     * @returns {string}
     */
    function collectBodyFromSections() {
        const sectionsDiv = panel.querySelector('#ecoute-preview-sections');
        const fallback    = panel.querySelector('#ecoute-preview-fallback');

        if (fallback.style.display !== 'none') {
            return fallback.value.trim();
        }

        return Array.from(sectionsDiv.querySelectorAll('.ecoute-section')).map(function (section) {
            const label = section.dataset.label || '';
            const field = section.querySelector('textarea, select, input');
            const value = field ? field.value.trim() : '';
            return '### ' + label + '\n\n' + value;
        }).join('\n\n');
    }

    async function requestPreview() {
        if (! previewUrl) { showStatus('Preview not available.'); return; }

        stopRecording();
        stopDictation();

        const userPrompt = panel.querySelector('#ecoute-prompt').value.trim();
        if (! userPrompt && ! recordingBase64) { showStatus('Please describe the issue.'); return; }
        if (! selectedElement) { showStatus('No element selected. Click an element first.'); return; }

        const btn = panel.querySelector('#ecoute-preview-btn');
        btn.disabled = true;
        startLoading(btn);
        showStatus('');

        const screenshot = await captureScreenshot();
        const payload    = buildPayload(selectedElement, userPrompt, screenshot);

        try {
            const response = await fetch(previewUrl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body:    JSON.stringify(payload),
            });

            if (! response.ok) {
                const err = await response.json().catch(function () { return {}; });
                const firstField = err.errors ? Object.keys(err.errors)[0] : null;
                const firstMsg   = firstField ? err.errors[firstField][0] : null;
                const displayErr = firstField
                    ? firstField + ': ' + firstMsg
                    : (err.message || response.status);
                showStatus('Error: ' + displayErr);
                return;
            }

            const data = await response.json();
            showPreviewView(data.title, data.body, data.sections || []);
        } catch (err) {
            showStatus('Network error. Please try again.');
        } finally {
            btn.disabled = false;
            stopLoading(btn, 'Preview');
        }
    }

    // ── Submission ────────────────────────────────────────────────────────────

    async function submitCapture() {
        stopRecording();
        stopDictation();
        await recordingReady; // Wait for recording to finish encoding

        var userPrompt = panel.querySelector('#ecoute-prompt').value.trim();
        if (!userPrompt && !recordingBase64) {
            showStatus('Please describe the issue.');
            return;
        }
        if (!userPrompt && recordingBase64) {
            userPrompt = '[Issue described verbally — see recording]';
        }

        if (!selectedElement) {
            showStatus('No element selected. Click an element first.');
            return;
        }

        const submitBtn = panel.querySelector('#ecoute-submit');
        submitBtn.disabled = true;
        startLoading(submitBtn, sendingMessages);
        showStatus('Capturing…');

        const screenshot = await captureScreenshot();
        const payload    = buildPayload(selectedElement, userPrompt, screenshot);

        // If submitting from preview view, include the user's edited title and body
        const previewView = panel.querySelector('#ecoute-preview-view');
        if (previewView.style.display !== 'none') {
            const editedTitle = panel.querySelector('#ecoute-preview-title-input').value.trim();
            if (editedTitle) {
                payload.title_override = editedTitle;
            }

            const editedBody = collectBodyFromSections();
            if (editedBody) {
                payload.body_override = editedBody;
            }
        }

        try {
            const response = await fetch(endpoint, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'Accept':           'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (response.status === 429) {
                showStatus('Too many submissions. Please wait and try again.');
                return;
            }

            if (!response.ok) {
                const body = await response.json().catch(function () { return {}; });
                showStatus('Error: ' + (body.message || response.status));
                return;
            }

            const data = await response.json();

            if (data.deduplicated) {
                showToast('Already captured', 'Similar feedback was already received.', null);
            } else {
                showToast('Issue captured', 'Your feedback is being processed by AI.', data.issue_url ?? null);
            }

            deactivate();
        } catch (err) {
            console.error('[Ecoute] Submission error:', err);
            showStatus('Network error. Please try again.');
        } finally {
            submitBtn.disabled = false;
            stopLoading(submitBtn, 'Send');
        }
    }

    // ── Screen Recording ─────────────────────────────────────────────────────

    let mediaRecorder    = null;
    let recordedChunks   = [];
    let recordStream     = null;
    let recordingBase64  = null;
    let recordingReady   = Promise.resolve();
    let recordingReadyResolve = null;
    let recordingDuration = 0;
    let recordingTimer   = null;

    function toggleRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            stopRecording();
        } else {
            startRecording();
        }
    }

    async function startRecording() {
        var maxDuration = (window.ecouteConfig?.recording?.maxDuration || 15) * 1000;

        try {
            recordStream = await navigator.mediaDevices.getDisplayMedia({
                video: true,
                audio: true,
            });
        } catch (err) {
            if (err.name !== 'AbortError') {
                showStatus('Screen recording not available or was cancelled.');
            }
            return;
        }

        recordedChunks   = [];
        recordingBase64  = null;
        recordingReady   = new Promise(function (resolve) { recordingReadyResolve = resolve; });
        recordingDuration = 0;

        var mimeType = MediaRecorder.isTypeSupported('video/webm;codecs=vp9')
            ? 'video/webm;codecs=vp9'
            : 'video/webm';

        mediaRecorder = new MediaRecorder(recordStream, { mimeType: mimeType });

        mediaRecorder.ondataavailable = function (e) {
            if (e.data.size > 0) { recordedChunks.push(e.data); }
        };

        mediaRecorder.onstop = async function () {
            var blob = new Blob(recordedChunks, { type: mimeType });
            recordingBase64 = await new Promise(function (resolve) {
                var reader = new FileReader();
                reader.onloadend = function () { resolve(reader.result); };
                reader.readAsDataURL(blob);
            });
            if (recordStream) {
                recordStream.getTracks().forEach(function (t) { t.stop(); });
                recordStream = null;
            }
            if (recordingReadyResolve) { recordingReadyResolve(); recordingReadyResolve = null; }
        };

        // Handle user stopping via the browser's share UI
        recordStream.getVideoTracks()[0].addEventListener('ended', function () {
            stopRecording();
        });

        mediaRecorder.start(1000);

        var btn = panel.querySelector('#ecoute-rec-btn');
        btn.classList.add('ecoute-recording');
        btn.title = 'Stop recording';

        updateRecordingTimer();
        recordingTimer = setInterval(updateRecordingTimer, 1000);

        if (maxDuration > 0) {
            setTimeout(function () {
                if (mediaRecorder && mediaRecorder.state === 'recording') {
                    stopRecording();
                }
            }, maxDuration);
        }
    }

    function stopRecording() {
        if (recordingTimer) { clearInterval(recordingTimer); recordingTimer = null; }

        var btn = panel.querySelector('#ecoute-rec-btn');
        btn.classList.remove('ecoute-recording');
        btn.title = 'Record screen';

        var timer = panel.querySelector('#ecoute-rec-timer');
        if (timer) { timer.style.display = 'none'; timer.textContent = ''; }

        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            return recordingReady;
        }
        return Promise.resolve();
    }

    function updateRecordingTimer() {
        recordingDuration++;
        var timer = panel.querySelector('#ecoute-rec-timer');
        if (timer) {
            timer.style.display = 'inline';
            var mins = Math.floor(recordingDuration / 60);
            var secs = recordingDuration % 60;
            timer.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
        }
    }

    // ── Loading State ─────────────────────────────────────────────────────────

    var loadingMessages = [
        'Thinking\u00a0\u00a0\u00a0',
        'Analyzing\u00a0\u00a0\u00a0',
        'Bip bop\u00a0\u00a0\u00a0',
        'Inspecting\u00a0\u00a0\u00a0',
        'AI at work\u00a0\u00a0\u00a0',
        'Almost there\u00a0\u00a0\u00a0',
    ];
    var sendingMessages = [
        'Capturing\u00a0\u00a0\u00a0',
        'Sending\u00a0\u00a0\u00a0',
        'Processing\u00a0\u00a0\u00a0',
    ];
    var loadingTimer = null;
    var loadingIdx = 0;

    function startLoading(btn, messages) {
        var msgs = messages || loadingMessages;
        loadingIdx = 0;
        btn.textContent = msgs[0];
        btn.classList.add('ecoute-loading');
        loadingTimer = setInterval(function () {
            loadingIdx = (loadingIdx + 1) % msgs.length;
            btn.textContent = msgs[loadingIdx];
        }, 1800);
    }

    function stopLoading(btn, fallback) {
        if (loadingTimer) { clearInterval(loadingTimer); loadingTimer = null; }
        btn.classList.remove('ecoute-loading');
        btn.textContent = fallback;
    }

    // ── Voice Dictation ──────────────────────────────────────────────────────

    let recognition   = null;
    let isDictating   = false;
    let interimBuffer = '';
    let voiceUsed     = false;

    /**
     * Initialise the SpeechRecognition instance (lazy, on first use).
     *
     * @returns {SpeechRecognition|null}
     */
    function initRecognition() {
        if (recognition) { return recognition; }

        var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) { return null; }

        recognition = new SpeechRecognition();
        recognition.continuous   = true;
        recognition.interimResults = true;
        recognition.lang         = document.documentElement.lang || 'en-US';
        return recognition;
    }

    /**
     * Toggle voice dictation on / off.
     */
    function toggleDictation() {
        if (isDictating) {
            stopDictation();
        } else {
            startDictation();
        }
    }

    function startDictation() {
        var rec  = initRecognition();
        var btn  = panel.querySelector('#ecoute-mic-btn');
        var prompt = panel.querySelector('#ecoute-prompt');

        if (!rec) {
            showStatus('Voice dictation is not supported in this browser.');
            return;
        }

        isDictating = true;
        interimBuffer = '';
        voiceUsed = true;
        btn.classList.add('ecoute-recording');
        btn.title = 'Stop dictation';

        rec.onresult = function (event) {
            var final = '';
            var interim = '';

            for (var i = event.resultIndex; i < event.results.length; i++) {
                var result = event.results[i];
                if (result.isFinal) {
                    final += result[0].transcript;
                } else {
                    interim += result[0].transcript;
                }
            }

            // Append final text to the textarea (non-destructive)
            if (final) {
                prompt.value = prompt.value + (prompt.value ? ' ' : '') + final;
                interimBuffer = '';
            } else if (interim) {
                interimBuffer = interim;
            }
        };

        rec.onerror = function (event) {
            if (event.error === 'not-allowed') {
                showStatus('Microphone access denied. Check your browser permissions.');
            } else if (event.error === 'no-speech') {
                // Silent — the user simply hasn't spoken yet
            } else if (event.error !== 'aborted') {
                console.error('[Ecoute] Speech recognition error:', event.error);
            }
            stopDictation();
        };

        rec.onend = function () {
            stopDictation();
        };

        rec.start();
    }

    function stopDictation() {
        isDictating = false;
        var btn = panel.querySelector('#ecoute-mic-btn');
        btn.classList.remove('ecoute-recording');
        btn.title = 'Dictate description';

        // Flush any remaining interim result into the textarea
        if (interimBuffer) {
            var prompt = panel.querySelector('#ecoute-prompt');
            prompt.value = prompt.value + (prompt.value ? ' ' : '') + interimBuffer;
            interimBuffer = '';
        }

        if (recognition) {
            recognition.onresult = null;
            recognition.onerror   = null;
            recognition.onend     = null;
        }
    }

    // ── Payload Construction ─────────────────────────────────────────────────

    /**
     * Build the payload for the capture request.
     *
     * @param {Element}     element    The selected DOM element.
     * @param {string}      userPrompt The user's feedback text.
     * @param {string|null} screenshot Base64 JPEG data URL or null.
     * @returns {Object}
     */
    function buildPayload(element, userPrompt, screenshot) {
        const attributes = {};
        for (const attr of element.attributes) {
            if (attr.name.startsWith('data-')) {
                // Coerce to string — frameworks like Alpine.js can bind non-string
                // values (true/false/null) to data-* attributes via :data-x="expr".
                attributes[attr.name] = String(attr.value ?? '').slice(0, 500);
            }
        }

        const payload = {
            element_selector: getCssSelector(element).slice(0, 5000),
            parent_selector:  element.parentElement ? getCssSelector(element.parentElement).slice(0, 5000) : null,
            element_html:     element.outerHTML.slice(0, 49000),
            parent_html:      element.parentElement ? element.parentElement.outerHTML.slice(0, 49000) : null,
            attributes:       attributes,
            nearby_text:      getNearbyText(element).map(String),
            user_prompt:      userPrompt,
            interaction: {
                page_title:   document.title,
                url:          window.location.href,
                timestamp:    new Date().toISOString().slice(0, 19).replace('T', ' '),
                input_method: voiceUsed ? 'voice' : 'text',
            },
        };

        if (screenshot) {
            payload.screenshot = screenshot;
        }

        if (diagnosticsEnabled) {
            payload.diagnostics = {
                console: consoleBuffer.slice(),
                network: networkBuffer.slice(),
            };
        }

        if (recordingBase64) {
            payload.recording = recordingBase64;
        }

        const selectedTemplate = panel.querySelector('#ecoute-template')?.value;
        if (selectedTemplate) {
            payload.template = selectedTemplate;
        }

        return payload;
    }

    // ── CSS Selector Generation ───────────────────────────────────────────────

    /**
     * Generate a reasonably unique CSS selector for an element.
     *
     * @param {Element} element
     * @returns {string}
     */
    function getCssSelector(element) {
        if (!element || element === document.body) {
            return 'body';
        }

        if (element.id) {
            return '#' + CSS.escape(element.id);
        }

        const parts   = [];
        let   current = element;

        while (current && current !== document.body) {
            let selector = current.tagName.toLowerCase();

            if (current.id) {
                selector = '#' + CSS.escape(current.id);
                parts.unshift(selector);
                break;
            }

            if (current.className) {
                const classes = Array.from(current.classList)
                    .filter(function (c) { return !c.startsWith('ecoute-'); })
                    .slice(0, 2)
                    .map(CSS.escape)
                    .join('.');
                if (classes) {
                    selector += '.' + classes;
                }
            }

            // Add nth-child if needed for uniqueness
            const siblings = current.parentElement
                ? Array.from(current.parentElement.children).filter(function (s) {
                    return s.tagName === current.tagName;
                })
                : [];

            if (siblings.length > 1) {
                const index = siblings.indexOf(current) + 1;
                selector += ':nth-of-type(' + index + ')';
            }

            parts.unshift(selector);
            current = current.parentElement;
        }

        return parts.join(' > ') || element.tagName.toLowerCase();
    }

    // ── Nearby Text Extraction ────────────────────────────────────────────────

    /**
     * Extract text from the element and its immediate neighbours.
     *
     * @param  {Element} element
     * @returns {string[]}
     */
    function getNearbyText(element) {
        const texts = [];

        const addText = function (el) {
            if (!el) { return; }
            const text = el.textContent?.trim().slice(0, 200);
            if (text) { texts.push(text); }
        };

        addText(element);
        addText(element.previousElementSibling);
        addText(element.nextElementSibling);
        addText(element.parentElement);

        // Deduplicate and limit
        return [...new Set(texts)].slice(0, 10);
    }

    // ── Screenshot Capture ────────────────────────────────────────────────────

    /**
     * Capture a screenshot of the visible page, masking sensitive elements before capture.
     *
     * Design decisions:
     *  - scale: 1 avoids a double-downscale when combined with canvas resize.
     *  - Resize is performed on a secondary canvas after capture, not via html2canvas options.
     *  - Sensitive element state (value, innerHTML, styles) is saved BEFORE mutation.
     *  - `finally` guarantees restoration even if html2canvas throws.
     *  - Cross-origin images: useCORS:true attempts CORS; if headers are missing the image
     *    is blanked by the browser. allowTaint:true would include the image but taints the canvas,
     *    preventing toDataURL(). The current default is the safer tradeoff.
     *
     * @returns {Promise<string|null>} Base64 JPEG data URL, or null on failure.
     */
    async function captureScreenshot() {
        // Yield to the browser to allow UI updates/painting before heavy DOM capture.
        await new Promise(function (resolve) {
            setTimeout(resolve, 50);
        });

        // If html-to-image is still loading, wait up to 5 s for it to arrive.
        if (!window.htmlToImage) {
            await new Promise(function (resolve) {
                let waited = 0;
                const interval = setInterval(function () {
                    waited += 100;
                    if (window.htmlToImage || waited >= 5000) {
                        clearInterval(interval);
                        resolve();
                    }
                }, 100);
            });
        }
        if (!window.htmlToImage) { return null; }

        const config   = window.ecouteConfig?.screenshot || {};
        const maxWidth = config.maxWidth || 800;
        const quality  = config.quality  || 0.5;

        const sensitiveElements = document.querySelectorAll(
            'input[type="password"], .ecoute-sensitive'
        );

        // Save all style properties we will mutate BEFORE mutating them
        const originalStates = Array.from(sensitiveElements).map(function (el) {
            return {
                el:            el,
                originalValue: (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') ? el.value : null,
                originalHTML:  (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') ? null : el.innerHTML,
                originalStyles: {
                    backgroundColor: el.style.backgroundColor,
                    color:           el.style.color,
                    filter:          el.style.filter,
                },
            };
        });

        // Mask sensitive elements
        originalStates.forEach(function ({ el }) {
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                el.value = '••••••••';
            } else {
                el.textContent = '[REDACTED]';
            }
            el.style.backgroundColor = '#f0f0f0';
            el.style.color           = '#666666';
            el.style.filter          = el.style.filter
                ? el.style.filter + ' blur(4px)'
                : 'blur(4px)';
        });

        try {
            const scale = window.innerWidth > maxWidth ? maxWidth / window.innerWidth : 1;

            // html-to-image uses SVG foreignObject so the browser renders all
            // modern CSS (oklch, color-mix, etc.) natively.
            // skipFonts prevents the library from trying to inline remote font
            // stylesheets (Google Fonts, Vite dev server), which throw a
            // SecurityError on cssRules access due to cross-origin restrictions.
            return await window.htmlToImage.toJpeg(document.body, {
                quality,
                pixelRatio: scale,
                skipFonts: true,
                filter: function (node) {
                    // Exclude the Ecoute panel from the screenshot
                    return !(node.id === 'ecoute-panel');
                },
            });

        } catch (err) {
            console.error('[Ecoute] Screenshot capture failed:', err);
            return null;

        } finally {
            // Restore every element to its pre-capture state
            originalStates.forEach(function ({ el, originalValue, originalHTML, originalStyles }) {
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    el.value = originalValue;
                } else {
                    el.innerHTML = originalHTML;
                }
                el.style.backgroundColor = originalStyles.backgroundColor;
                el.style.color           = originalStyles.color;
                el.style.filter          = originalStyles.filter;
            });
        }
    }

    // ── Font Loader ───────────────────────────────────────────────────────────

    (function loadGeist() {
        var link = document.createElement('link');
        link.href = 'https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Geist+Mono:wght@100..900&display=swap';
        link.rel = 'stylesheet';
        document.head.appendChild(link);
    })();

    // ── Styles ────────────────────────────────────────────────────────────────

    const style = document.createElement('style');
    style.textContent = `

        .ecoute-active, .ecoute-active * { cursor: crosshair !important; }
        #ecoute-panel, #ecoute-panel * { cursor: auto !important; }
        #ecoute-panel button, #ecoute-panel a, #ecoute-panel [role="button"] { cursor: pointer !important; }
        #ecoute-panel textarea, #ecoute-panel input, #ecoute-panel select { cursor: text !important; }
        #ecoute-panel select { cursor: default !important; }

        .ecoute-highlight {
            outline: 2.5px solid #818cf8 !important;
            outline-offset: 1px !important;
            background-color: rgba(129,140,248,.06) !important;
            border-radius: 4px !important;
        }

        #ecoute-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid #e8eaed;
        }
        #ecoute-panel-title { color: #1e293b; font-size: 13px; font-weight: 600; letter-spacing: -.01em; }
        #ecoute-close {
            background: none; border: none; font-size: 18px; cursor: pointer;
            color: #94a3b8; line-height: 1; padding: 2px 6px; border-radius: 6px;
            transition: color .15s, background .15s;
        }
        #ecoute-close:hover { color: #475569; background: #f1f5f9; }

        #ecoute-form-view, #ecoute-preview-view { padding: 14px; }
        #ecoute-prompt-header {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;
        }
        #ecoute-prompt-header label { color: #334155; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        #ecoute-prompt-tools { display: flex; align-items: center; gap: 6px; }
        #ecoute-rec-timer {
            font-size: 11px; color: #ef4444; font-variant-numeric: tabular-nums;
            font-weight: 600; min-width: 32px; display: none;
        }
        #ecoute-mic-btn, #ecoute-rec-btn {
            display: flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border: 1px solid #e2e8f0; border-radius: 8px;
            background: #fff; color: #64748b; cursor: pointer; padding: 0;
            transition: all .15s ease;
        }
        #ecoute-mic-btn:hover, #ecoute-rec-btn:hover {
            background: #f8fafc; color: #334155; border-color: #cbd5e1;
        }
        #ecoute-mic-btn.ecoute-recording, #ecoute-rec-btn.ecoute-recording {
            background: #fef2f2; border-color: #fecaca; color: #ef4444;
            animation: ecoute-pulse 1.6s ease-in-out infinite;
        }
        @keyframes ecoute-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,.15); }
            50%      { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
        }

        #ecoute-template-wrap { margin-bottom: 10px; }
        #ecoute-template-wrap label { display: block; margin-bottom: 4px; color: #334155; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        #ecoute-template {
            width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 8px 10px; font-family: inherit; font-size: 13px; background: #fff; color: #1e293b;
            transition: border-color .15s, box-shadow .15s;
        }
        #ecoute-template:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.12); }

        #ecoute-prompt {
            width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 10px 12px; resize: vertical; font-family: inherit; font-size: 13px;
            line-height: 1.55; color: #1e293b; background: #fafbfc;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        #ecoute-prompt:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.12); background: #fff; }

        #ecoute-actions { display: flex; align-items: center; gap: 10px; margin-top: 12px; }
        #ecoute-preview-btn, #ecoute-edit-btn {
            background: #fff; color: #475569; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 7px 14px; cursor: pointer; font-size: 12.5px; font-weight: 500;
            transition: all .15s ease;
        }
        #ecoute-preview-btn:hover, #ecoute-edit-btn:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
        #ecoute-preview-btn.ecoute-loading, #ecoute-submit.ecoute-loading {
            background: linear-gradient(110deg, #e2e8f0 30%, #f1f5f9 50%, #e2e8f0 70%);
            background-size: 200% 100%;
            animation: ecoute-shimmer 1.8s ease-in-out infinite;
            color: #64748b;
            border-color: #e2e8f0;
            box-shadow: none;
            transform: none;
        }
        @keyframes ecoute-shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        @media (prefers-color-scheme: dark) {
            #ecoute-preview-btn.ecoute-loading, #ecoute-submit.ecoute-loading {
                background: linear-gradient(110deg, #1e293b 30%, #334155 50%, #1e293b 70%);
                background-size: 200% 100%;
                color: #64748b;
            }
        }
        #ecoute-submit {
            background: #4f46e5; color: #fff; border: none; border-radius: 8px;
            padding: 7px 16px; cursor: pointer; font-size: 13px; font-weight: 600;
            letter-spacing: -.01em; transition: all .15s ease; box-shadow: 0 1px 2px rgba(79,70,229,.2);
        }
        #ecoute-submit:hover:not(:disabled) { background: #4338ca; box-shadow: 0 2px 8px rgba(79,70,229,.25); transform: translateY(-1px); }
        #ecoute-submit:active:not(:disabled) { transform: scale(.97) translateY(0); }
        #ecoute-submit:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; }
        .ecoute-status { color: #94a3b8; font-size: 11.5px; }

        #ecoute-preview-title-input {
            width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 9px 12px; font-family: inherit; font-size: 14px; font-weight: 600;
            color: #1e293b; margin-bottom: 14px; background: #fafbfc;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        #ecoute-preview-title-input:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.12); background: #fff; }

        #ecoute-preview-sections { max-height: 380px; overflow-y: auto; }
        .ecoute-section { margin-bottom: 14px; }
        .ecoute-section-label {
            display: block; font-weight: 600; font-size: 12px; color: #334155;
            margin-bottom: 5px; text-transform: uppercase; letter-spacing: .04em;
        }
        .ecoute-section-desc { font-size: 11px; color: #94a3b8; margin-bottom: 6px; line-height: 1.45; }
        .ecoute-section-textarea, .ecoute-section-select, .ecoute-section-input {
            width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 8px 10px; font-family: inherit; font-size: 13px; color: #334155;
            background: #fafbfc; transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .ecoute-section-textarea { resize: vertical; line-height: 1.55; }
        .ecoute-section-textarea:focus, .ecoute-section-select:focus, .ecoute-section-input:focus {
            outline: none; border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.12); background: #fff;
        }

        #ecoute-preview-fallback {
            width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 10px 12px; font-family: Geist Mono,ui-monospace,SFMono-Regular,Menlo,monospace;
            font-size: 12px; line-height: 1.6; color: #334155; background: #f8fafc; resize: vertical;
            transition: border-color .15s, box-shadow .15s;
        }
        #ecoute-preview-fallback:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.12); background: #fff; }
        #ecoute-preview-actions { display: flex; align-items: center; gap: 8px; margin-top: 14px; padding-top: 12px; border-top: 1px solid #f1f5f9; }

        #ecoute-toast {
            position: fixed; top: 20px; right: 20px; z-index: 2147483647;
            width: 300px; background: rgba(15,23,42,.92); backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            color: #f8fafc; border-radius: 12px; padding: 14px 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.12), 0 1px 4px rgba(0,0,0,.08);
            font-family: Geist,system-ui,-apple-system,sans-serif; font-size: 13px;
            opacity: 0; transform: translateY(-12px) scale(.96);
            transition: opacity .25s cubic-bezier(.16,1,.3,1), transform .25s cubic-bezier(.16,1,.3,1);
            pointer-events: none; border: 1px solid rgba(255,255,255,.08);
        }
        #ecoute-toast.ecoute-toast-visible {
            opacity: 1; transform: translateY(0) scale(1); pointer-events: auto;
        }
        #ecoute-toast-title { font-weight: 600; font-size: 13px; margin-bottom: 3px; letter-spacing: -.01em; }
        #ecoute-toast-message { color: #94a3b8; font-size: 12px; margin-bottom: 8px; line-height: 1.45; }
        #ecoute-toast-link {
            display: inline-block; color: #a5b4fc; text-decoration: none; font-weight: 500; font-size: 12px;
            transition: color .15s;
        }
        #ecoute-toast-link:hover { color: #c7d2fe; }

        @media (prefers-color-scheme: dark) {
            #ecoute-panel { background: rgba(15,23,42,.95) !important; border-color: rgba(255,255,255,.06) !important; box-shadow: 0 4px 24px rgba(0,0,0,.25),0 0 0 1px rgba(255,255,255,.04) !important; }
            .ecoute-highlight {
                outline-color: #a5b4fc !important;
                background-color: rgba(165,180,252,.1) !important;
            }
            #ecoute-panel-header { border-bottom-color: #1e293b; }
            #ecoute-panel-title { color: #e2e8f0; }
            #ecoute-close { color: #64748b; }
            #ecoute-close:hover { color: #cbd5e1; background: #1e293b; }
            #ecoute-prompt-header label { color: #94a3b8; }
            #ecoute-mic-btn, #ecoute-rec-btn {
                background: #1e293b; color: #94a3b8; border-color: #334155;
            }
            #ecoute-mic-btn:hover, #ecoute-rec-btn:hover {
                background: #334155; color: #cbd5e1; border-color: #475569;
            }
            #ecoute-mic-btn.ecoute-recording, #ecoute-rec-btn.ecoute-recording {
                background: #450a0a; border-color: #7f1d1d; color: #fca5a5;
            }
            @keyframes ecoute-pulse {
                0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,.25); }
                50%      { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
            }
            #ecoute-template-wrap label { color: #94a3b8; }
            #ecoute-template {
                background: #1e293b; border-color: #334155; color: #e2e8f0;
            }
            #ecoute-template:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.2); }
            #ecoute-prompt {
                background: #1e293b; border-color: #334155; color: #e2e8f0;
            }
            #ecoute-prompt:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.2); background: #0f172a; }
            #ecoute-preview-btn, #ecoute-edit-btn {
                background: #1e293b; color: #94a3b8; border-color: #334155;
            }
            #ecoute-preview-btn:hover, #ecoute-edit-btn:hover {
                background: #334155; color: #cbd5e1; border-color: #475569;
            }
            #ecoute-submit { background: #6366f1; box-shadow: 0 1px 2px rgba(99,102,241,.3); }
            #ecoute-submit:hover:not(:disabled) { background: #818cf8; }
            .ecoute-status { color: #64748b; }
            #ecoute-preview-title-input {
                background: #1e293b; border-color: #334155; color: #e2e8f0;
            }
            #ecoute-preview-title-input:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.2); background: #0f172a; }
            .ecoute-section-label { color: #94a3b8; }
            .ecoute-section-desc { color: #64748b; }
            .ecoute-section-textarea, .ecoute-section-select, .ecoute-section-input {
                background: #1e293b; border-color: #334155; color: #e2e8f0;
            }
            .ecoute-section-textarea:focus, .ecoute-section-select:focus, .ecoute-section-input:focus {
                border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.2); background: #0f172a;
            }
            #ecoute-preview-fallback {
                background: #1e293b; border-color: #334155; color: #e2e8f0;
            }
            #ecoute-preview-fallback:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.2); background: #0f172a; }
            #ecoute-preview-actions { border-top-color: #1e293b; }
        }
    `;
    document.head.appendChild(style);

})();
