(function() {
  'use strict';

  chrome.storage.local.get(['backendUrl', 'apiToken'], function(items) {
    var backendUrl = items.backendUrl;
    var apiToken = items.apiToken;

    if (!backendUrl || !apiToken) {
      console.warn('Ecoute: extension not configured. Open the popup to set backend URL and API token.');
      return;
    }

    if (window.__ecouteActive) {
      window.__ecouteToggle();
      return;
    }
    window.__ecouteActive = true;

    // Load Geist fonts globally
    var geistLink = document.createElement('link');
    geistLink.href = 'https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Geist+Mono:wght@100..900&display=swap';
    geistLink.rel = 'stylesheet';
    document.head.appendChild(geistLink);

    // Global CSS for page cursor and highlight border
    var globalCss = document.createElement('style');
    globalCss.textContent = `
      .ecoute-active,.ecoute-active *{cursor:crosshair!important}
      .ecoute-highlight{outline:2.5px solid #818cf8!important;outline-offset:1px!important;background-color:rgba(129,140,248,.06)!important;border-radius:4px!important}
    `;
    document.head.appendChild(globalCss);

    // Container and Shadow DOM for UI isolation
    var container = document.createElement('div');
    container.id = 'ecoute-ext-container';
    var shadow = container.attachShadow({ mode: 'open' });

    var shadowCss = document.createElement('style');
    shadowCss.textContent = `
      .ecoute-panel,.ecoute-panel *{box-sizing:border-box}
      .ecoute-panel,.ecoute-panel *{cursor:auto!important}
      .ecoute-panel button,.ecoute-panel a{cursor:pointer!important}
      .ecoute-panel{position:fixed;bottom:24px;right:24px;z-index:2147483647;width:350px;background:rgba(255,255,255,0.85);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(15,23,42,0.08);border-radius:16px;box-shadow:0 10px 25px -5px rgba(15,23,42,0.04),0 20px 48px -10px rgba(15,23,42,0.1),0 0 0 1px rgba(15,23,42,0.03);font-family:Geist,system-ui,-apple-system,sans-serif;font-size:13px;display:none;animation:ecoute-slide-in 0.28s cubic-bezier(0.16,1,0.3,1)}
      @keyframes ecoute-slide-in{from{transform:translateY(12px);opacity:0}to{transform:translateY(0);opacity:1}}
      .ecoute-panel-header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid rgba(15,23,42,0.06)}
      .ecoute-panel-header span{color:#0f172a;font-size:14px;font-weight:600;letter-spacing:-0.02em;display:flex;align-items:center;gap:8px}
      .ecoute-header-dot{width:6px;height:6px;background:#10b981;border-radius:50%;box-shadow:0 0 0 2px rgba(16,185,129,0.25);display:inline-block;animation:ecoute-pulse-dot 2s infinite}
      @keyframes ecoute-pulse-dot{0%,100%{transform:scale(1);box-shadow:0 0 0 2px rgba(16,185,129,0.25)}50%{transform:scale(1.1);box-shadow:0 0 0 5px rgba(16,185,129,0)}}
      .ecoute-close{background:none;border:none;font-size:18px;cursor:pointer;color:#94a3b8;line-height:1;padding:4px;border-radius:8px;transition:all 0.15s ease;display:flex;align-items:center;justify-content:center}
      .ecoute-close:hover{color:#475569;background:rgba(15,23,42,0.05)}
      .ecoute-panel-body{padding:16px}
      .ecoute-prompt-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
      .ecoute-prompt-header label{color:#475569;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em}
      .ecoute-prompt-tools{display:flex;align-items:center;gap:6px}
      .ecoute-rec-timer{font-size:11px;color:#ef4444;font-variant-numeric:tabular-nums;font-weight:600;min-width:32px;display:none}
      .ecoute-mic-btn,.ecoute-rec-btn{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid rgba(15,23,42,0.08);border-radius:9px;background:rgba(255,255,255,0.8);color:#64748b;cursor:pointer;padding:0;transition:all 0.15s ease}
      .ecoute-mic-btn:hover,.ecoute-rec-btn:hover{background:#fff;color:#0f172a;border-color:rgba(15,23,42,0.15);box-shadow:0 2px 4px rgba(0,0,0,0.02)}
      .ecoute-mic-btn.ecoute-recording,.ecoute-rec-btn.ecoute-recording{background:#fef2f2;border-color:#fca5a5;color:#ef4444;animation:ecoute-pulse 1.6s ease-in-out infinite}
      @keyframes ecoute-pulse{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,0.2)}50%{box-shadow:0 0 0 6px rgba(239,68,68,0)}}
      .ecoute-prompt{width:100%;box-sizing:border-box;border:1px solid rgba(15,23,42,0.12);border-radius:10px;padding:12px;resize:none;font-family:inherit;font-size:13px;line-height:1.5;color:#1e293b;background:rgba(248,250,252,0.5);transition:all 0.2s ease}
      .ecoute-prompt:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,0.15);background:#fff}
      .ecoute-actions{display:flex;align-items:center;gap:12px;margin-top:16px}
      .ecoute-submit{background:linear-gradient(135deg,#4f46e5 0%,#6366f1 100%);color:#fff;border:none;border-radius:10px;padding:8px 18px;cursor:pointer;font-size:13px;font-weight:600;letter-spacing:-0.01em;transition:all 0.2s ease;box-shadow:0 2px 4px rgba(79,70,229,0.12),0 4px 12px rgba(79,70,229,0.12)}
      .ecoute-submit:hover:not(:disabled){background:linear-gradient(135deg,#4338ca 0%,#4f46e5 100%);box-shadow:0 4px 8px rgba(79,70,229,0.18),0 8px 20px rgba(79,70,229,0.18);transform:translateY(-1px)}
      .ecoute-submit:active:not(:disabled){transform:translateY(0);box-shadow:0 2px 4px rgba(79,70,229,0.12)}
      .ecoute-submit:disabled{opacity:.45;cursor:not-allowed;transform:none;box-shadow:none}
      .ecoute-status{color:#64748b;font-size:11.5px}
      
      /* Diagnostics Drawer styling (trust builder) */
      .ecoute-diagnostics-drawer{margin-top:16px;border:1px solid rgba(15,23,42,0.06);border-radius:10px;background:rgba(248,250,252,0.6);overflow:hidden;transition:all 0.2s ease}
      .ecoute-diagnostics-drawer[open]{border-color:rgba(99,102,241,0.25);background:#fff;box-shadow:inset 0 1px 3px rgba(0,0,0,0.01)}
      .ecoute-diagnostics-drawer summary{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;font-size:11px;font-weight:600;color:#475569;cursor:pointer;user-select:none;list-style:none}
      .ecoute-diagnostics-drawer summary::-webkit-details-marker{display:none}
      .ecoute-event-badge{background:#e2e8f0;color:#475569;padding:2px 8px;border-radius:20px;font-size:10px;font-variant-numeric:tabular-nums;font-weight:700}
      .ecoute-diagnostics-drawer[open] .ecoute-event-badge{background:rgba(99,102,241,0.12);color:#4f46e5}
      .ecoute-timeline-list{padding:8px 14px 12px;border-top:1px solid rgba(15,23,42,0.04);max-height:140px;overflow-y:auto}
      .ecoute-timeline-item{display:flex;align-items:center;gap:8px;font-size:11px;color:#64748b;padding:6px 0;border-bottom:1px dashed rgba(0,0,0,0.04)}
      .ecoute-timeline-item:last-child{border-bottom:none}
      .ecoute-timeline-time{font-family:monospace;font-size:10px;color:#94a3b8}
      .ecoute-timeline-icon{font-size:11px}
      .ecoute-timeline-label{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1}
      .ecoute-empty-timeline{font-size:11px;color:#94a3b8;text-align:center;padding:12px 0}
    `;
    shadow.appendChild(shadowCss);

    var selectedElement = null;
    var isActive = false;

    var panel = document.createElement('div');
    panel.className = 'ecoute-panel';
    panel.id = 'ecoute-ext-panel';
    panel.innerHTML = [
      '<div class="ecoute-panel-header">',
        '<span><span class="ecoute-header-dot"></span>Ecoute Feedback</span>',
        '<button class="ecoute-close" title="Close overlay">&times;</button>',
      '</div>',
      '<div class="ecoute-panel-body">',
        '<div class="ecoute-prompt-header">',
          '<label>Describe the issue:</label>',
          '<div class="ecoute-prompt-tools">',
            '<span class="ecoute-rec-timer"></span>',
            '<button class="ecoute-mic-btn ecoute-rec-btn" title="Record screen">',
              '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>',
            '</button>',
            '<button class="ecoute-mic-btn" title="Dictate description">',
              '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>',
            '</button>',
          '</div>',
        '</div>',
        '<textarea class="ecoute-prompt" rows="4" maxlength="2000" placeholder="What\'s wrong here?"></textarea>',
        
        // Trust-builder Diagnostics Drawer
        '<details class="ecoute-diagnostics-drawer">',
          '<summary>',
            '<span>Session Timeline</span>',
            '<span class="ecoute-event-badge" id="ecoute-timeline-count">0</span>',
          '</summary>',
          '<div class="ecoute-timeline-list" id="ecoute-timeline-list">',
            '<div class="ecoute-empty-timeline">No interactions captured yet. Try clicking around the page.</div>',
          '</div>',
        '</details>',

        '<div class="ecoute-actions">',
          '<button class="ecoute-submit">Send</button>',
          '<span class="ecoute-status"></span>',
        '</div>',
      '</div>',
    ].join('');
    shadow.appendChild(panel);
    document.body.appendChild(container);

    panel.querySelector('.ecoute-close').addEventListener('click', deactivate);
    panel.querySelector('.ecoute-rec-btn').addEventListener('click', toggleRecording);
    panel.querySelector('.ecoute-mic-btn').addEventListener('click', toggleDictation);
    panel.querySelector('.ecoute-submit').addEventListener('click', submit);

    // ── Timeline Sync (from hook.js) ───────────────────────────────────

    var timeline = [];
    var TIMELINE_MAX = 200;

    function updateTimelineUI() {
      try {
        var countEl = panel.querySelector('#ecoute-timeline-count');
        if (countEl) countEl.textContent = timeline.length;

        var listEl = panel.querySelector('#ecoute-timeline-list');
        if (listEl) {
          if (timeline.length === 0) {
            listEl.innerHTML = '<div class="ecoute-empty-timeline">No interactions captured yet. Try clicking around the page.</div>';
            return;
          }
          
          var html = [];
          var slice = timeline.slice(-4).reverse(); // Last 4 events
          for (var i = 0; i < slice.length; i++) {
            var evt = slice[i];
            var icon = '⏱️';
            if (evt.type === 'click') icon = '🖱️';
            else if (evt.type === 'input') icon = '⌨️';
            else if (evt.type === 'console') icon = '🖥️';
            else if (evt.type === 'network') icon = '🌐';

            var label = '';
            if (evt.type === 'click') {
              label = 'Clicked ' + evt.label;
            } else if (evt.type === 'input') {
              label = 'Input in ' + evt.label;
            } else if (evt.type === 'console') {
              var badge = evt.level === 'error' ? 'ERROR' : (evt.level === 'warn' ? 'WARN' : 'LOG');
              label = 'Console [' + badge + '] ' + (evt.args ? evt.args.join(' ') : '');
            } else if (evt.type === 'network') {
              var statusText = evt.status === 0 ? 'FAIL' : String(evt.status);
              label = 'Fetch: ' + evt.method + ' ' + statusText + ' (' + evt.duration + 'ms)';
            } else {
              label = evt.message || '';
            }

            html.push(
              '<div class="ecoute-timeline-item">' +
                '<span class="ecoute-timeline-time">' + evt.timestamp.split('.')[0] + '</span>' +
                '<span class="ecoute-timeline-icon">' + icon + '</span>' +
                '<span class="ecoute-timeline-label" title="' + label + '">' + label.slice(0, 42) + (label.length > 42 ? '...' : '') + '</span>' +
              '</div>'
            );
          }
          listEl.innerHTML = html.join('');
        }
      } catch (_) {}
    }

    window.addEventListener('message', function(e) {
      if (e.data) {
        if (e.data.source === 'ecoute-hook-event') {
          if (timeline.length >= TIMELINE_MAX) {
            timeline.shift();
          }
          timeline.push(e.data.event);
          updateTimelineUI();
        } else if (e.data.source === 'ecoute-hook-timeline') {
          timeline = e.data.timeline || [];
          updateTimelineUI();
        }
      }
    });

    // Request loaded history from hook
    window.postMessage({ source: 'ecoute-content-ping' }, '*');

    // ── Offscreen Screen Recording ──────────────────────────────────────────

    var recDuration = 0;
    var maxRecordingDuration = 15; // Max 15 seconds

    function toggleRecording() {
      chrome.runtime.sendMessage({ action: 'getRecordingState' }, function(response) {
        if (response && response.state === 'recording') {
          chrome.runtime.sendMessage({ action: 'stopRecording' });
        } else {
          startRecording();
        }
      });
    }

    function startRecording() {
      showStatus('Initializing screen recording...');
      chrome.runtime.sendMessage({
        action: 'startRecording',
        maxDuration: maxRecordingDuration
      });
    }

    function updateRecTimerUI(duration) {
      var timer = panel.querySelector('.ecoute-rec-timer');
      if (timer) {
        timer.style.display = 'inline';
        var m = Math.floor(duration / 60);
        var s = duration % 60;
        timer.textContent = m + ':' + (s < 10 ? '0' : '') + s;
      }
    }

    // Listen to background messaging for recorder updates
    chrome.runtime.onMessage.addListener(function(message, sender, sendResponse) {
      if (message.action === 'recordingTick') {
        recDuration = message.duration;
        updateRecTimerUI(recDuration);
      }
      else if (message.action === 'recordingStarted') {
        recDuration = message.duration;
        var btn = panel.querySelector('.ecoute-rec-btn');
        btn.classList.add('ecoute-recording');
        btn.title = 'Stop recording';
        updateRecTimerUI(recDuration);
        showStatus('');
      }
      else if (message.action === 'recordingSaved') {
        var btn = panel.querySelector('.ecoute-rec-btn');
        btn.classList.remove('ecoute-recording');
        btn.title = 'Record screen';
        var timer = panel.querySelector('.ecoute-rec-timer');
        if (timer) {
          timer.style.display = 'none';
          timer.textContent = '';
        }
        showStatus('Recording saved.');
        setTimeout(function() { showStatus(''); }, 2000);
      }
      else if (message.action === 'recordingError') {
        var btn = panel.querySelector('.ecoute-rec-btn');
        btn.classList.remove('ecoute-recording');
        showStatus('Recording failed: ' + message.error);
        setTimeout(function() { showStatus(''); }, 3000);
      }
    });

    // Query active recording state on load to restore UI after tab navigation
    chrome.runtime.sendMessage({ action: 'getRecordingState' }, function(response) {
      if (response && response.state === 'recording') {
        selectedElement = document.body;
        var btn = panel.querySelector('.ecoute-rec-btn');
        btn.classList.add('ecoute-recording');
        btn.title = 'Stop recording';
        recDuration = response.duration;
        updateRecTimerUI(recDuration);
        
        // Show the panel automatically since recording is in progress
        panel.style.display = 'block';
        panel.querySelector('.ecoute-prompt').focus();
        updateTimelineUI();
      }
    });

    // ── Voice Dictation ────────────────────────────────────────────────

    var recognition = null;
    var isDictating = false;
    var interimBuffer = '';
    var voiceUsed = false;

    function initRecognition() {
      if (recognition) return recognition;
      var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
      if (!SR) return null;
      recognition = new SR();
      recognition.continuous = true;
      recognition.interimResults = true;
      recognition.lang = document.documentElement.lang || 'en-US';
      return recognition;
    }

    // Toggle speech input
    function toggleDictation() {
      isDictating ? stopDictation() : startDictation();
    }

    function startDictation() {
      var rec = initRecognition();
      var btn = panel.querySelector('.ecoute-mic-btn');
      var prompt = panel.querySelector('.ecoute-prompt');
      if (!rec) { showStatus('Voice not supported in this browser.'); return; }
      isDictating = true;
      interimBuffer = '';
      voiceUsed = true;
      btn.classList.add('ecoute-recording');
      btn.title = 'Stop dictation';
      rec.onresult = function(e) {
        var final = '', interim = '';
        for (var i = e.resultIndex; i < e.results.length; i++) {
          if (e.results[i].isFinal) final += e.results[i][0].transcript;
          else interim += e.results[i][0].transcript;
        }
        if (final) { prompt.value = prompt.value + (prompt.value ? ' ' : '') + final; interimBuffer = ''; }
        else if (interim) { interimBuffer = interim; }
      };
      rec.onerror = function(e) {
        if (e.error === 'not-allowed') showStatus('Microphone denied.');
        stopDictation();
      };
      rec.onend = function() { stopDictation(); };
      rec.start();
    }

    function stopDictation() {
      isDictating = false;
      var btn = panel.querySelector('.ecoute-mic-btn');
      btn.classList.remove('ecoute-recording');
      btn.title = 'Dictate description';
      if (interimBuffer) {
        var p = panel.querySelector('.ecoute-prompt');
        p.value = p.value + (p.value ? ' ' : '') + interimBuffer;
        interimBuffer = '';
      }
      if (recognition) { recognition.onresult = recognition.onerror = recognition.onend = null; }
    }

    // ── Core ───────────────────────────────────────────────────────────

    function activate() {
      isActive = true;
      document.body.classList.add('ecoute-active');
      document.addEventListener('click', handleClick, true);
      showStatus('Click any element to capture.');
    }

    function deactivate() {
      isActive = false;
      document.body.classList.remove('ecoute-active');
      document.removeEventListener('click', handleClick, true);
      if (selectedElement) selectedElement.classList.remove('ecoute-highlight');
      panel.style.display = 'none';
      selectedElement = null;
      // Stop and discard any active recording
      chrome.runtime.sendMessage({ action: 'cancelRecording' });
    }

    function handleClick(e) {
      if (container.contains(e.target)) return;
      e.preventDefault();
      e.stopPropagation();
      if (selectedElement) selectedElement.classList.remove('ecoute-highlight');
      selectedElement = e.target;
      selectedElement.classList.add('ecoute-highlight');
      document.body.classList.remove('ecoute-active');
      document.removeEventListener('click', handleClick, true);
      isActive = false;
      showPanel();
    }

    function showPanel() {
      panel.style.display = 'block';
      panel.querySelector('.ecoute-prompt').value = '';
      voiceUsed = false;
      showStatus('');
      panel.querySelector('.ecoute-prompt').focus();
      updateTimelineUI();
    }

    function showStatus(msg) {
      panel.querySelector('.ecoute-status').textContent = msg;
    }

    function getNearbyText(el) {
      var texts = [];
      var addText = function(element) {
        if (!element) return;
        var text = element.textContent?.trim().slice(0, 200);
        if (text) texts.push(text);
      };

      addText(el);
      if (el) {
        addText(el.previousElementSibling);
        addText(el.nextElementSibling);
        addText(el.parentElement);
      }

      var uniqueTexts = [];
      for (var i = 0; i < texts.length; i++) {
        if (uniqueTexts.indexOf(texts[i]) === -1) {
          uniqueTexts.push(texts[i]);
        }
      }
      return uniqueTexts.slice(0, 10);
    }

    function getSelector(el) {
      if (!el || el === document.body) return 'body';
      if (el.id) return '#' + CSS.escape(el.id);
      var parts = [], current = el;
      while (current && current !== document.body) {
        var s = current.tagName.toLowerCase();
        if (current.className) {
          var cls = Array.from(current.classList).filter(function(c){return !c.startsWith('ecoute-')}).slice(0,2).map(CSS.escape).join('.');
          if (cls) s += '.' + cls;
        }
        if (current.parentElement) {
          var sibs = Array.from(current.parentElement.children).filter(function(s){return s.tagName===current.tagName});
          if (sibs.length > 1) s += ':nth-of-type(' + (sibs.indexOf(current)+1) + ')';
        }
        parts.unshift(s);
        current = current.parentElement;
      }
      return parts.join(' > ') || 'body';
    }

    async function submit() {
      stopDictation();

      var prompt = panel.querySelector('.ecoute-prompt').value.trim();
      if (!prompt) { showStatus('Please describe the issue.'); return; }
      if (!selectedElement) { showStatus('No element selected.'); return; }

      var btn = panel.querySelector('.ecoute-submit');
      btn.disabled = true;
      btn.textContent = 'Sending…';
      showStatus('');

      // Check if we are currently recording. If so, stop it and wait for it to save.
      var stateResponse = await new Promise(function(resolve) {
        chrome.runtime.sendMessage({ action: 'getRecordingState' }, resolve);
      });

      if (stateResponse && stateResponse.state === 'recording') {
        showStatus('Saving recording...');
        chrome.runtime.sendMessage({ action: 'stopRecording' });
        
        // Wait for recordingSaved message
        await new Promise(function(resolve) {
          var listener = function(message) {
            if (message.action === 'recordingSaved') {
              chrome.runtime.onMessage.removeListener(listener);
              resolve();
            }
          };
          chrome.runtime.onMessage.addListener(listener);
        });
      }

      // Retrieve recording base64 from local storage
      var storage = await new Promise(function(resolve) {
        chrome.storage.local.get(['tempRecording'], resolve);
      });
      var recordingBase64 = storage.tempRecording;

      // Request state snapshot from hook.js
      window.postMessage({ source: 'ecoute-content-getState' }, '*');
      
      // Wait for state response
      var state = await new Promise(function(resolve) {
        var listener = function(e) {
          if (e.data && e.data.source === 'ecoute-hook-state') {
            window.removeEventListener('message', listener);
            resolve(e.data.state);
          }
        };
        window.addEventListener('message', listener);
        // Timeout backup
        setTimeout(function() {
          window.removeEventListener('message', listener);
          resolve({ localStorage: {}, sessionStorage: {}, cookies: '' });
        }, 300);
      });

      var payload = {
        element_selector: getSelector(selectedElement).slice(0, 5000),
        parent_selector: selectedElement.parentElement ? getSelector(selectedElement.parentElement).slice(0, 5000) : null,
        element_html: selectedElement.outerHTML.slice(0, 49000),
        parent_html: selectedElement.parentElement ? selectedElement.parentElement.outerHTML.slice(0, 49000) : null,
        nearby_text: getNearbyText(selectedElement),
        user_prompt: prompt,
        interaction: {
          page_title: document.title,
          url: window.location.href,
          timestamp: new Date().toISOString().slice(0, 19).replace('T', ' '),
          input_method: voiceUsed ? 'voice' : 'text',
        },
      };

      if (recordingBase64) {
        payload.recording = recordingBase64;
      }

      payload.diagnostics = {
        timeline: timeline.slice(),
        state: state
      };

      try {
        var url = backendUrl.replace(/\/$/, '') + '/ecoute/capture';
        console.log('[Ecoute] Sending to:', url);

        var res = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': 'Bearer ' + apiToken,
          },
          body: JSON.stringify(payload),
        });

        console.log('[Ecoute] Response:', res.status);

        if (res.status === 401) {
          showStatus('Auth failed. Check API token.');
        } else if (res.status === 404) {
          showStatus('Endpoint not found: ' + url);
        } else if (!res.ok) {
          var body = await res.json().catch(function(){ return {}; });
          showStatus('Error ' + res.status + ': ' + (body.message || ''));
        } else {
          showStatus('Captured successfully.');
          // Clear temp recording from storage
          chrome.storage.local.remove('tempRecording');
          setTimeout(deactivate, 1800);
        }
      } catch (err) {
        console.error('[Ecoute]', err);
        showStatus('Network error. Check connection.');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Send';
      }
    }

    window.__ecouteToggle = function() {
      isActive ? deactivate() : activate();
    };

    activate();
  }); // chrome.storage.local.get
})();
