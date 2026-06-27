(function() {
  'use strict';

  // Prevent double-injection
  if (window.__ecouteHooked) return;
  window.__ecouteHooked = true;

  const timeline = [];
  const TIMELINE_MAX = 200;

  function getTime() {
    const d = new Date();
    return d.toTimeString().split(' ')[0] + '.' + String(d.getMilliseconds()).padStart(3, '0');
  }

  function addEvent(event) {
    if (timeline.length >= TIMELINE_MAX) {
      timeline.shift();
    }
    const evt = Object.assign({ timestamp: getTime() }, event);
    timeline.push(evt);

    // Send to content.js in the isolated world
    window.postMessage({ source: 'ecoute-hook-event', event: evt }, '*');
  }

  function getState() {
    var state = {
      localStorage: {},
      sessionStorage: {},
      cookies: document.cookie || ''
    };
    try {
      for (var i = 0; i < localStorage.length; i++) {
        var key = localStorage.key(i);
        if (key) state.localStorage[key] = (localStorage.getItem(key) || '').slice(0, 1000);
      }
    } catch (_) {}
    try {
      for (var j = 0; j < sessionStorage.length; j++) {
        var skey = sessionStorage.key(j);
        if (skey) state.sessionStorage[skey] = (sessionStorage.getItem(skey) || '').slice(0, 1000);
      }
    } catch (_) {}
    return state;
  }

  // Send current timeline when requested by content.js
  window.addEventListener('message', function(e) {
    if (e.data) {
      if (e.data.source === 'ecoute-content-ping') {
        window.postMessage({ source: 'ecoute-hook-timeline', timeline: timeline }, '*');
      } else if (e.data.source === 'ecoute-content-getState') {
        window.postMessage({ source: 'ecoute-hook-state', state: getState() }, '*');
      }
    }
  });

  // 1. Hook Clicks (Breadcrumbs)
  window.addEventListener('click', function(e) {
    if (!e.target) return;
    try {
      const el = e.target;
      // Skip clicking on ecoute's own UI
      if (el.closest && el.closest('#ecoute-ext-panel')) return;

      let tag = el.tagName.toLowerCase();
      let id = el.id ? '#' + el.id : '';
      let cls = el.className && typeof el.className === 'string' 
        ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') 
        : '';
      
      addEvent({
        type: 'click',
        label: `${tag}${id}${cls}`.slice(0, 200)
      });
    } catch (_) {}
  }, true);

  // 2. Hook Inputs (Breadcrumbs)
  window.addEventListener('change', function(e) {
    if (!e.target) return;
    try {
      const el = e.target;
      if (el.closest && el.closest('#ecoute-ext-panel')) return;
      if (['input', 'textarea', 'select'].includes(el.tagName.toLowerCase())) {
        let tag = el.tagName.toLowerCase();
        let name = el.name ? `[name="${el.name}"]` : '';
        let id = el.id ? '#' + el.id : '';
        addEvent({
          type: 'input',
          label: `${tag}${id}${name}`.slice(0, 200)
        });
      }
    } catch (_) {}
  }, true);

  // 3. Hook Console
  const origConsole = {
    log: console.log,
    warn: console.warn,
    error: console.error
  };

  function captureConsole(level, args) {
    try {
      const formattedArgs = Array.from(args).map(function(a) {
        if (a instanceof Error) return a.message;
        if (typeof a === 'object') {
          try { return JSON.stringify(a).slice(0, 200); } catch (_) { return String(a).slice(0, 200); }
        }
        return String(a).slice(0, 500);
      });
      addEvent({
        type: 'console',
        level: level,
        args: formattedArgs
      });
    } catch (_) {}
  }

  console.log = function() {
    captureConsole('log', arguments);
    origConsole.log.apply(console, arguments);
  };
  console.warn = function() {
    captureConsole('warn', arguments);
    origConsole.warn.apply(console, arguments);
  };
  console.error = function() {
    captureConsole('error', arguments);
    origConsole.error.apply(console, arguments);
  };

  // 4. Hook Network (fetch & XMLHttpRequest)
  const origFetch = window.fetch;
  window.fetch = function(url, opts) {
    const start = performance.now();
    const method = (opts && opts.method) || 'GET';
    const urlStr = typeof url === 'string' ? url : (url instanceof Request ? url.url : String(url));
    
    // Skip calling ecoute's own API
    if (urlStr.includes('/ecoute/capture')) {
      return origFetch.apply(this, arguments);
    }

    return origFetch.apply(this, arguments).then(function(r) {
      const duration = Math.round(performance.now() - start);
      const cleanUrl = urlStr.replace(/[?#].*$/, '').slice(0, 250);
      addEvent({
        type: 'network',
        method: method,
        url: cleanUrl,
        status: r.status,
        duration: duration
      });
      return r;
    }).catch(function(e) {
      const duration = Math.round(performance.now() - start);
      const cleanUrl = urlStr.replace(/[?#].*$/, '').slice(0, 250);
      addEvent({
        type: 'network',
        method: method,
        url: cleanUrl,
        status: 0,
        duration: duration
      });
      throw e;
    });
  };

  const OrigXHR = window.XMLHttpRequest;
  window.XMLHttpRequest = function() {
    const xhr = new OrigXHR();
    let method = 'GET';
    let url = '';
    let start = 0;

    const origOpen = xhr.open;
    xhr.open = function(m, u) {
      method = m;
      url = String(u);
      return origOpen.apply(xhr, arguments);
    };

    const origSend = xhr.send;
    xhr.send = function() {
      start = performance.now();
      
      if (!url.includes('/ecoute/capture')) {
        xhr.addEventListener('loadend', function() {
          const duration = Math.round(performance.now() - start);
          const cleanUrl = url.replace(/[?#].*$/, '').slice(0, 250);
          addEvent({
            type: 'network',
            method: method,
            url: cleanUrl,
            status: xhr.status,
            duration: duration
          });
        });
      }
      return origSend.apply(xhr, arguments);
    };

    return xhr;
  };
  window.XMLHttpRequest.prototype = OrigXHR.prototype;

})();
