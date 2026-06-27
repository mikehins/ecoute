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

    var geistLink = document.createElement('link');
    geistLink.href = 'https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Geist+Mono:wght@100..900&display=swap';
    geistLink.rel = 'stylesheet';
    document.head.appendChild(geistLink);

    var css = document.createElement('style');
    css.textContent = `
      .ecoute-active,.ecoute-active *{cursor:crosshair!important}
      .ecoute-panel,.ecoute-panel *{cursor:auto!important}
      .ecoute-panel button,.ecoute-panel a{cursor:pointer!important}
      .ecoute-highlight{outline:2.5px solid #818cf8!important;outline-offset:1px!important;background-color:rgba(129,140,248,.06)!important;border-radius:4px!important}
      .ecoute-panel{position:fixed;bottom:20px;right:20px;z-index:2147483647;width:340px;background:rgba(255,255,255,.97);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(0,0,0,.06);border-radius:14px;box-shadow:0 4px 24px rgba(0,0,0,.08),0 0 0 1px rgba(0,0,0,.04);font-family:Geist,system-ui,-apple-system,sans-serif;font-size:13px;display:none}
      .ecoute-panel-header{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid #e8eaed}
      .ecoute-panel-header span{color:#1e293b;font-size:13px;font-weight:600;letter-spacing:-.01em}
      .ecoute-close{background:none;border:none;font-size:18px;cursor:pointer;color:#94a3b8;line-height:1;padding:2px 6px;border-radius:6px;transition:color .15s,background .15s}
      .ecoute-close:hover{color:#475569;background:#f1f5f9}
      .ecoute-panel-body{padding:14px}
      .ecoute-prompt-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
      .ecoute-prompt-header label{color:#334155;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
      .ecoute-prompt-tools{display:flex;align-items:center;gap:6px}
      .ecoute-rec-timer{font-size:11px;color:#ef4444;font-variant-numeric:tabular-nums;font-weight:600;min-width:32px;display:none}
      .ecoute-mic-btn,.ecoute-rec-btn{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;color:#64748b;cursor:pointer;padding:0;transition:all .15s ease}
      .ecoute-mic-btn:hover,.ecoute-rec-btn:hover{background:#f8fafc;color:#334155;border-color:#cbd5e1}
      .ecoute-mic-btn.ecoute-recording,.ecoute-rec-btn.ecoute-recording{background:#fef2f2;border-color:#fecaca;color:#ef4444;animation:ecoute-pulse 1.6s ease-in-out infinite}
      @keyframes ecoute-pulse{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.15)}50%{box-shadow:0 0 0 6px rgba(239,68,68,0)}}
      .ecoute-prompt{width:100%;box-sizing:border-box;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;resize:vertical;font-family:inherit;font-size:13px;line-height:1.55;color:#1e293b;background:#fafbfc;transition:border-color .15s,box-shadow .15s,background .15s}
      .ecoute-prompt:focus{outline:none;border-color:#818cf8;box-shadow:0 0 0 3px rgba(129,140,248,.12);background:#fff}
      .ecoute-actions{display:flex;align-items:center;gap:10px;margin-top:12px}
      .ecoute-submit{background:#4f46e5;color:#fff;border:none;border-radius:8px;padding:7px 16px;cursor:pointer;font-size:13px;font-weight:600;letter-spacing:-.01em;transition:all .15s ease;box-shadow:0 1px 2px rgba(79,70,229,.2)}
      .ecoute-submit:hover:not(:disabled){background:#4338ca;box-shadow:0 2px 8px rgba(79,70,229,.25);transform:translateY(-1px)}
      .ecoute-submit:disabled{opacity:.45;cursor:not-allowed;transform:none;box-shadow:none}
      .ecoute-status{color:#94a3b8;font-size:11.5px}
    `;
    document.head.appendChild(css);

    var selectedElement = null;
    var isActive = false;

    var panel = document.createElement('div');
    panel.className = 'ecoute-panel';
    panel.id = 'ecoute-ext-panel';
    panel.innerHTML = [
      '<div class="ecoute-panel-header">',
        '<span>Ecoute Feedback</span>',
        '<button class="ecoute-close">&times;</button>',
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
        '<div class="ecoute-actions">',
          '<button class="ecoute-submit">Send</button>',
          '<span class="ecoute-status"></span>',
        '</div>',
      '</div>',
    ].join('');
    document.body.appendChild(panel);

    panel.querySelector('.ecoute-close').addEventListener('click', deactivate);
    panel.querySelector('.ecoute-rec-btn').addEventListener('click', toggleRecording);
    panel.querySelector('.ecoute-mic-btn').addEventListener('click', toggleDictation);
    panel.querySelector('.ecoute-submit').addEventListener('click', submit);

    // ── Screen Recording ──────────────────────────────────────────────

    var mediaRecorder = null;
    var recordedChunks = [];
    var recordStream = null;
    var recordingBase64 = null;
    var recDuration = 0;
    var recTimer = null;

    function toggleRecording() {
      if (mediaRecorder && mediaRecorder.state === 'recording') {
        stopRecording();
      } else {
        startRecording();
      }
    }

    async function startRecording() {
      var maxDur = (window.ecouteConfig && window.ecouteConfig.recording && window.ecouteConfig.recording.maxDuration || 15) * 1000;

      try {
        recordStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: true });
      } catch (e) {
        if (e.name !== 'AbortError') showStatus('Screen recording not available.');
        return;
      }

      recordedChunks = [];
      recordingBase64 = null;
      recDuration = 0;

      var mime = MediaRecorder.isTypeSupported('video/webm;codecs=vp9') ? 'video/webm;codecs=vp9' : 'video/webm';
      mediaRecorder = new MediaRecorder(recordStream, { mimeType: mime });

      mediaRecorder.ondataavailable = function(e) { if (e.data.size > 0) recordedChunks.push(e.data); };
      mediaRecorder.onstop = async function() {
        var blob = new Blob(recordedChunks, { type: mime });
        recordingBase64 = await new Promise(function(resolve) {
          var reader = new FileReader();
          reader.onloadend = function() { resolve(reader.result); };
          reader.readAsDataURL(blob);
        });
        if (recordStream) { recordStream.getTracks().forEach(function(t){t.stop()}); recordStream = null; }
      };

      recordStream.getVideoTracks()[0].addEventListener('ended', function() { stopRecording(); });
      mediaRecorder.start(1000);

      var btn = panel.querySelector('.ecoute-rec-btn');
      btn.classList.add('ecoute-recording');
      btn.title = 'Stop recording';

      updateRecTimer();
      recTimer = setInterval(updateRecTimer, 1000);

      if (maxDur > 0) setTimeout(function() { if (mediaRecorder && mediaRecorder.state === 'recording') stopRecording(); }, maxDur);
    }

    function stopRecording() {
      if (recTimer) { clearInterval(recTimer); recTimer = null; }
      var btn = panel.querySelector('.ecoute-rec-btn');
      btn.classList.remove('ecoute-recording');
      btn.title = 'Record screen';
      var timer = panel.querySelector('.ecoute-rec-timer');
      if (timer) { timer.style.display = 'none'; timer.textContent = ''; }
      if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
    }

    function updateRecTimer() {
      recDuration++;
      var timer = panel.querySelector('.ecoute-rec-timer');
      if (timer) {
        timer.style.display = 'inline';
        var m = Math.floor(recDuration / 60);
        var s = recDuration % 60;
        timer.textContent = m + ':' + (s < 10 ? '0' : '') + s;
      }
    }

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
      showStatus('Click any element to capture it.');
    }

    function deactivate() {
      isActive = false;
      document.body.classList.remove('ecoute-active');
      document.removeEventListener('click', handleClick, true);
      if (selectedElement) selectedElement.classList.remove('ecoute-highlight');
      panel.style.display = 'none';
      selectedElement = null;
    }

    function handleClick(e) {
      if (panel.contains(e.target)) return;
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
    }

    function showStatus(msg) {
      panel.querySelector('.ecoute-status').textContent = msg;
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
      stopRecording();
      stopDictation();

      var prompt = panel.querySelector('.ecoute-prompt').value.trim();
      if (!prompt) { showStatus('Please describe the issue.'); return; }
      if (!selectedElement) { showStatus('No element selected.'); return; }

      var btn = panel.querySelector('.ecoute-submit');
      btn.disabled = true;
      btn.textContent = 'Sending…';
      showStatus('');

      var payload = {
        element_selector: getSelector(selectedElement).slice(0, 5000),
        parent_selector: selectedElement.parentElement ? getSelector(selectedElement.parentElement).slice(0, 5000) : null,
        element_html: selectedElement.outerHTML.slice(0, 49000),
        parent_html: selectedElement.parentElement ? selectedElement.parentElement.outerHTML.slice(0, 49000) : null,
        nearby_text: [selectedElement.textContent && selectedElement.textContent.trim().slice(0, 200)].filter(Boolean),
        user_prompt: prompt,
        interaction: {
          page_title: document.title,
          url: window.location.href,
          timestamp: new Date().toISOString().slice(0, 19).replace('T', ' '),
          input_method: voiceUsed ? 'voice' : 'text',
        },
      };

      if (recordingBase64) payload.recording = recordingBase64;

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
          showStatus('Auth failed. Check your API token or Laravel auth:sanctum middleware.');
        } else if (res.status === 404) {
          showStatus('Endpoint not found. Check backend URL: ' + url);
        } else if (!res.ok) {
          var body = await res.json().catch(function(){ return {}; });
          showStatus('Error ' + res.status + ': ' + (body.message || ''));
        } else {
          showStatus('Issue captured — processing by AI.');
          setTimeout(deactivate, 2000);
        }
      } catch (err) {
        console.error('[Ecoute]', err);
        showStatus('Network error: ' + (err.message || 'Check console for details.'));
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
