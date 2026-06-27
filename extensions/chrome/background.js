chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.action === 'startCapture') {
    chrome.tabs.query({ active: true, currentWindow: true }, async ([tab]) => {
      const { backendUrl, apiToken } = await chrome.storage.local.get(['backendUrl', 'apiToken']);

      if (!backendUrl || !apiToken) {
        chrome.action.openPopup();
        return;
      }

      chrome.scripting.executeScript({
        target: { tabId: tab.id },
        files: ['content.js'],
      });
    });
  }
});
