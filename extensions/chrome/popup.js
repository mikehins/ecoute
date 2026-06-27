const startBtn   = document.getElementById('start-btn');
const statusEl   = document.getElementById('status');
const urlInput   = document.getElementById('url');
const tokenInput = document.getElementById('token');

// Views & Nav
const viewRecording = document.getElementById('view-recording');
const viewSettings  = document.getElementById('view-settings');
const navRecording  = document.getElementById('nav-recording');
const navSettings   = document.getElementById('nav-settings');
const navLibrary    = document.getElementById('nav-library');

// Camera & Mic Toggles
const toggleCamera = document.getElementById('toggle-camera');
const cameraState  = document.getElementById('camera-state');
const toggleMic    = document.getElementById('toggle-mic');
const micState     = document.getElementById('mic-state');

let isCameraOn = false;
let isMicOn = true;

// Tab Navigation logic
function showView(viewName) {
  viewRecording.classList.remove('active');
  viewSettings.classList.remove('active');
  navRecording.classList.remove('active');
  navSettings.classList.remove('active');
  navLibrary.classList.remove('active');

  if (viewName === 'recording') {
    viewRecording.classList.add('active');
    navRecording.classList.add('active');
  } else if (viewName === 'settings') {
    viewSettings.classList.add('active');
    navSettings.classList.add('active');
  } else if (viewName === 'library') {
    navLibrary.classList.add('active');
    // Open the backend captures page in a new tab if URL is configured
    chrome.storage.local.get(['backendUrl'], function(items) {
      if (items.backendUrl) {
        chrome.tabs.create({ url: items.backendUrl + '/ecoute/captures' });
      } else {
        alert('Configure Backend URL in Settings first.');
        showView('settings');
      }
    });
  }
}

navRecording.addEventListener('click', () => showView('recording'));
navSettings.addEventListener('click', () => showView('settings'));
navLibrary.addEventListener('click', () => showView('library'));

// Toggle Camera / Mic UI
toggleCamera.addEventListener('click', () => {
  isCameraOn = !isCameraOn;
  cameraState.textContent = isCameraOn ? 'On' : 'Off';
  cameraState.className = 'toggle-badge ' + (isCameraOn ? '' : 'off');
});

toggleMic.addEventListener('click', () => {
  isMicOn = !isMicOn;
  micState.textContent = isMicOn ? 'On' : 'Off';
  micState.className = 'toggle-badge ' + (isMicOn ? '' : 'off');
});

// Settings Save/Load
async function loadSettings() {
  const { backendUrl, apiToken } = await chrome.storage.local.get(['backendUrl', 'apiToken']);
  urlInput.value = backendUrl || '';
  tokenInput.value = apiToken || '';
}

function status(msg, ok) {
  statusEl.textContent = msg;
  statusEl.className = 'status-alert ' + (ok ? 'status-ok' : 'status-err');
}

document.getElementById('save-btn').addEventListener('click', async () => {
  const urlVal = urlInput.value.trim();
  const tokenVal = tokenInput.value.trim();

  await chrome.storage.local.set({
    backendUrl: urlVal,
    apiToken: tokenVal,
  });
  
  status('Settings saved.', true);
  setTimeout(() => { 
    status('', true); 
    showView('recording');
  }, 1000);
});

// Close button
document.getElementById('close-btn').addEventListener('click', () => {
  window.close();
});

// Start Recording Action
startBtn.addEventListener('click', async () => {
  const { backendUrl, apiToken } = await chrome.storage.local.get(['backendUrl', 'apiToken']);

  if (!backendUrl || !apiToken) {
    status('Configure connection settings first.', false);
    setTimeout(() => {
      status('', false);
      showView('settings');
    }, 1200);
    return;
  }

  startBtn.disabled = true;
  startBtn.textContent = 'Starting...';
  status('', true);

  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

    // Set the auto-start flag and settings in storage so content.js triggers immediately upon injection
    await chrome.storage.local.set({ 
      shouldStartRecording: true,
      recordCamera: isCameraOn,
      recordMic: isMicOn
    });

    // Inject hook.js in the page main world context
    await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      files: ['hook.js'],
      world: 'MAIN',
    });

    // Inject content.js in the isolated context
    await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      files: ['content.js'],
      world: 'ISOLATED',
    });

    window.close();
  } catch (err) {
    status('Cannot inject on this page.', false);
    startBtn.disabled = false;
    startBtn.textContent = 'Start recording';
  }
});

// Initial Load
loadSettings();
showView('recording');
