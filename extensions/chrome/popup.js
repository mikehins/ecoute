const captureBtn = document.getElementById('capture-btn');
const statusEl   = document.getElementById('status');
const urlInput   = document.getElementById('url');
const tokenInput = document.getElementById('token');

async function loadSettings() {
  const { backendUrl, apiToken } = await chrome.storage.local.get(['backendUrl', 'apiToken']);
  urlInput.value = backendUrl || '';
  tokenInput.value = apiToken || '';
  captureBtn.disabled = !backendUrl || !apiToken;
}

function status(msg, ok) {
  statusEl.textContent = msg;
  statusEl.className = 'status ' + (ok ? 'status-ok' : 'status-err');
}

captureBtn.addEventListener('click', async () => {
  const { backendUrl, apiToken } = await chrome.storage.local.get(['backendUrl', 'apiToken']);

  if (!backendUrl || !apiToken) {
    status('Configure your backend URL and API token first.', false);
    return;
  }

  captureBtn.disabled = true;
  captureBtn.textContent = 'Activating…';
  status('', true);

  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

    // Inject hook.js in the page main world context to intercept native API calls
    await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      files: ['hook.js'],
      world: 'MAIN',
    });

    // Inject content.js in the isolated extension world context
    await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      files: ['content.js'],
      world: 'ISOLATED',
    });

    window.close();
  } catch (err) {
    status('Cannot inject on this page (chrome:// or extension pages are blocked).', false);
  } finally {
    captureBtn.disabled = false;
    captureBtn.textContent = 'Capture Element';
  }
});

document.getElementById('save-btn').addEventListener('click', async () => {
  await chrome.storage.local.set({
    backendUrl: urlInput.value.trim(),
    apiToken: tokenInput.value.trim(),
  });
  captureBtn.disabled = !urlInput.value.trim() || !tokenInput.value.trim();
  status('Settings saved.', true);
  setTimeout(() => { if (statusEl.textContent === 'Settings saved.') statusEl.textContent = ''; }, 2000);
});

loadSettings();
