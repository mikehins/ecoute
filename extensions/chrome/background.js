let offscreenCreating = null;

async function setupOffscreen() {
  const existingContexts = await chrome.runtime.getContexts({
    contextTypes: ['OFFSCREEN_DOCUMENT']
  });

  if (existingContexts.length > 0) {
    return;
  }

  if (offscreenCreating) {
    await offscreenCreating;
    return;
  }

  offscreenCreating = chrome.offscreen.createDocument({
    url: 'offscreen.html',
    reasons: ['DISPLAY_MEDIA'],
    justification: 'Record screen audio and video for bug reports'
  });

  await offscreenCreating;
  offscreenCreating = null;
}

async function closeOffscreen() {
  const existingContexts = await chrome.runtime.getContexts({
    contextTypes: ['OFFSCREEN_DOCUMENT']
  });
  if (existingContexts.length > 0) {
    await chrome.offscreen.closeDocument();
  }
}

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.action === 'startCapture') {
    chrome.tabs.query({ active: true, currentWindow: true }, async ([tab]) => {
      const { backendUrl, apiToken } = await chrome.storage.local.get(['backendUrl', 'apiToken']);

      if (!backendUrl || !apiToken) {
        chrome.action.openPopup();
        return;
      }

      await chrome.scripting.executeScript({
        target: { tabId: tab.id },
        files: ['hook.js'],
        world: 'MAIN',
      });

      await chrome.scripting.executeScript({
        target: { tabId: tab.id },
        files: ['content.js'],
        world: 'ISOLATED',
      });
    });
  }
  else if (message.action === 'startRecording') {
    (async () => {
      try {
        await setupOffscreen();
        // Delay slightly to ensure offscreen script listeners are bound
        setTimeout(() => {
          chrome.runtime.sendMessage({ action: 'offscreen-start', maxDuration: message.maxDuration });
        }, 100);
        sendResponse({ status: 'starting' });
      } catch (err) {
        sendResponse({ status: 'error', error: err.message });
      }
    })();
    return true;
  }
  else if (message.action === 'stopRecording') {
    chrome.runtime.sendMessage({ action: 'offscreen-stop' });
    sendResponse({ status: 'stopping' });
    return true;
  }
  else if (message.action === 'cancelRecording') {
    chrome.runtime.sendMessage({ action: 'offscreen-cancel' });
    sendResponse({ status: 'cancelled' });
    return true;
  }
  else if (message.action === 'closeOffscreenDoc') {
    closeOffscreen();
    sendResponse({ status: 'closed' });
    return true;
  }
  else if (message.action === 'getRecordingState') {
    (async () => {
      const existingContexts = await chrome.runtime.getContexts({
        contextTypes: ['OFFSCREEN_DOCUMENT']
      });
      if (existingContexts.length === 0) {
        sendResponse({ state: 'inactive', duration: 0 });
        return;
      }
      try {
        const response = await chrome.runtime.sendMessage({ action: 'offscreen-getState' });
        sendResponse(response || { state: 'inactive', duration: 0 });
      } catch (_) {
        sendResponse({ state: 'inactive', duration: 0 });
      }
    })();
    return true;
  }
});

// Automatically re-inject extension scripts if navigation happens during recording
chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
  if (changeInfo.status === 'complete' && tab.url && !tab.url.startsWith('chrome://') && !tab.url.startsWith('about:')) {
    (async () => {
      try {
        const existingContexts = await chrome.runtime.getContexts({
          contextTypes: ['OFFSCREEN_DOCUMENT']
        });
        if (existingContexts.length > 0) {
          const { backendUrl, apiToken } = await chrome.storage.local.get(['backendUrl', 'apiToken']);
          if (backendUrl && apiToken) {
            await chrome.scripting.executeScript({
              target: { tabId: tabId },
              files: ['hook.js'],
              world: 'MAIN',
            });
            await chrome.scripting.executeScript({
              target: { tabId: tabId },
              files: ['content.js'],
              world: 'ISOLATED',
            });
          }
        }
      } catch (_) {}
    })();
  }
});
