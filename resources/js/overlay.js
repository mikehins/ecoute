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

        if (e.ctrlKey  === shortcut.ctrl
         && e.altKey   === shortcut.alt
         && e.shiftKey === shortcut.shift
         && e.metaKey  === shortcut.meta
         && e.key.toLowerCase() === shortcut.key) {
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
                    <label for="ecoute-prompt">Describe the issue:</label>
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
            'position:fixed', 'bottom:24px', 'right:24px', 'z-index:2147483647',
            'width:340px', 'background:#fff', 'border:1px solid #d1d5db',
            'border-radius:12px', 'box-shadow:0 8px 30px rgba(0,0,0,.15)',
            'font-family:system-ui,sans-serif', 'font-size:14px',
            'display:none',
        ].join(';');

        el.querySelector('#ecoute-close').addEventListener('click', deactivate);
        el.querySelector('#ecoute-preview-btn').addEventListener('click', requestPreview);
        el.querySelector('#ecoute-edit-btn').addEventListener('click', showFormView);
        el.querySelector('#ecoute-submit').addEventListener('click', submitCapture);

        return el;
    }

    function showPanel(element) {
        panel.style.display = 'block';
        showFormView();
        panel.querySelector('#ecoute-prompt').value = '';
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

        const userPrompt = panel.querySelector('#ecoute-prompt').value.trim();
        if (! userPrompt) { showStatus('Please describe the issue.'); return; }
        if (! selectedElement) { showStatus('No element selected. Click an element first.'); return; }

        const btn = panel.querySelector('#ecoute-preview-btn');
        btn.disabled = true;
        btn.textContent = 'Loading…';
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
            btn.textContent = 'Preview';
        }
    }

    // ── Submission ────────────────────────────────────────────────────────────

    async function submitCapture() {
        const userPrompt = panel.querySelector('#ecoute-prompt').value.trim();
        if (!userPrompt) {
            showStatus('Please describe the issue.');
            return;
        }

        if (!selectedElement) {
            showStatus('No element selected. Click an element first.');
            return;
        }

        const submitBtn = panel.querySelector('#ecoute-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending…';
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
            submitBtn.textContent = 'Send';
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
                input_method: 'text',
            },
        };

        if (screenshot) {
            payload.screenshot = screenshot;
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

    // ── Styles ────────────────────────────────────────────────────────────────

    const style = document.createElement('style');
    style.textContent = `
        .ecoute-active, .ecoute-active * { cursor: crosshair !important; }
        #ecoute-panel, #ecoute-panel * { cursor: auto !important; }
        #ecoute-panel button, #ecoute-panel a, #ecoute-panel [role="button"] { cursor: pointer !important; }
        #ecoute-panel textarea, #ecoute-panel input, #ecoute-panel select { cursor: text !important; }
        #ecoute-panel select { cursor: default !important; }
        .ecoute-highlight {
            outline: 3px solid #6366f1 !important;
            outline-offset: 2px !important;
            background-color: rgba(99, 102, 241, 0.05) !important;
        }
        #ecoute-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
        }
        #ecoute-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #6b7280;
            line-height: 1;
            padding: 0 4px;
        }
        #ecoute-close:hover { color: #111827; }
        #ecoute-panel-body { padding: 12px 16px 16px; }
        #ecoute-panel-body label { display: block; margin-bottom: 6px; color: #374151; font-weight: 500; }
        #ecoute-prompt {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px;
            resize: vertical;
            font-family: inherit;
            font-size: 13px;
        }
        #ecoute-actions { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
        #ecoute-submit {
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 7px 16px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        #ecoute-submit:hover:not(:disabled) { background: #4f46e5; }
        #ecoute-submit:disabled { opacity: .6; cursor: not-allowed; }
        .ecoute-status { color: #6b7280; font-size: 12px; }
        #ecoute-form-view, #ecoute-preview-view { padding: 12px 16px 16px; }
        #ecoute-preview-title-input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px 10px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 14px;
        }
        #ecoute-preview-title-input:focus {
            outline: none;
            border-color: #6366f1;
        }
        #ecoute-preview-sections {
            max-height: 420px;
            overflow-y: auto;
        }
        .ecoute-section { margin-bottom: 14px; }
        .ecoute-section-label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #111827;
            margin-bottom: 4px;
        }
        .ecoute-section-desc {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 5px;
            line-height: 1.4;
        }
        .ecoute-section-textarea,
        .ecoute-section-select,
        .ecoute-section-input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 7px 9px;
            font-family: inherit;
            font-size: 13px;
            color: #374151;
            background: #fff;
        }
        .ecoute-section-textarea {
            resize: vertical;
            line-height: 1.5;
        }
        .ecoute-section-textarea:focus,
        .ecoute-section-select:focus,
        .ecoute-section-input:focus {
            outline: none;
            border-color: #6366f1;
        }
        #ecoute-preview-fallback {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px;
            font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace;
            font-size: 12px;
            line-height: 1.6;
            color: #374151;
            background: #f9fafb;
            resize: vertical;
        }
        #ecoute-preview-fallback:focus {
            outline: none;
            border-color: #6366f1;
            background: #fff;
        }
        #ecoute-preview-actions { display: flex; align-items: center; gap: 8px; margin-top: 12px; padding-top: 10px; border-top: 1px solid #e5e7eb; }
        #ecoute-edit-btn {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 7px 14px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }
        #ecoute-edit-btn:hover { background: #e5e7eb; }
        #ecoute-template-wrap { margin-bottom: 10px; }
        #ecoute-template-wrap label { display: block; margin-bottom: 4px; color: #374151; font-weight: 500; }
        #ecoute-template {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 6px 8px;
            font-family: inherit;
            font-size: 13px;
            background: #fff;
            color: #111827;
        }
        #ecoute-toast {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 2147483647;
            width: 300px;
            background: #111827;
            color: #f9fafb;
            border-radius: 10px;
            padding: 14px 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,.25);
            font-family: system-ui, sans-serif;
            font-size: 13px;
            opacity: 0;
            transform: translateY(-8px);
            transition: opacity .2s ease, transform .2s ease;
            pointer-events: none;
        }
        #ecoute-toast.ecoute-toast-visible {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        #ecoute-toast-title { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
        #ecoute-toast-message { color: #9ca3af; margin-bottom: 8px; }
        #ecoute-toast-link {
            display: inline-block;
            color: #818cf8;
            text-decoration: none;
            font-weight: 500;
        }
        #ecoute-toast-link:hover { text-decoration: underline; }
    `;
    document.head.appendChild(style);

})();
