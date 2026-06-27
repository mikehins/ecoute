(function() {
  'use strict';

  var mediaRecorder = null;
  var recordedChunks = [];
  var recordStream = null;
  var recDuration = 0;
  var recTimer = null;
  var maxDuration = 30; // default 30s limit

  // Listen to instructions from background / content scripts
  chrome.runtime.onMessage.addListener(function(message, sender, sendResponse) {
    if (message.action === 'offscreen-start') {
      maxDuration = message.maxDuration || 30;
      startRecording();
      sendResponse({ status: 'starting' });
    }
    else if (message.action === 'offscreen-stop') {
      stopRecording();
      sendResponse({ status: 'stopping' });
    }
    else if (message.action === 'offscreen-getState') {
      sendResponse({
        state: mediaRecorder ? mediaRecorder.state : 'inactive',
        duration: recDuration
      });
    }
    else if (message.action === 'offscreen-cancel') {
      cancelRecording();
      sendResponse({ status: 'cancelled' });
    }
    return true; // Keep message channel open for async response if needed
  });

  async function startRecording() {
    try {
      // Prompt user to select screen/window/tab
      recordStream = await navigator.mediaDevices.getDisplayMedia({
        video: true,
        audio: true
      });
    } catch (err) {
      console.error('[Ecoute Offscreen] getDisplayMedia error:', err);
      chrome.runtime.sendMessage({ action: 'recordingError', error: err.message || 'Stream denied.' });
      closeOffscreen();
      return;
    }

    recordedChunks = [];
    recDuration = 0;

    var mime = MediaRecorder.isTypeSupported('video/webm;codecs=vp9') ? 'video/webm;codecs=vp9' : 'video/webm';
    mediaRecorder = new MediaRecorder(recordStream, { mimeType: mime });

    mediaRecorder.ondataavailable = function(e) {
      if (e.data.size > 0) {
        recordedChunks.push(e.data);
      }
    };

    mediaRecorder.onstop = async function() {
      var blob = new Blob(recordedChunks, { type: mime });
      
      // Convert to base64
      var reader = new FileReader();
      reader.onloadend = function() {
        var base64 = reader.result;
        // Save to chrome.storage.local so it survives service worker suspend
        chrome.storage.local.set({ tempRecording: base64 }, function() {
          chrome.runtime.sendMessage({ action: 'recordingSaved' });
          closeOffscreen();
        });
      };
      reader.readAsDataURL(blob);

      // Stop all camera/screen capture tracks
      if (recordStream) {
        recordStream.getTracks().forEach(function(t) { t.stop(); });
        recordStream = null;
      }
    };

    // If user clicks "Stop Sharing" native browser button
    recordStream.getVideoTracks()[0].addEventListener('ended', function() {
      stopRecording();
    });

    mediaRecorder.start(1000);

    // Setup timer tick
    recTimer = setInterval(function() {
      recDuration++;
      chrome.runtime.sendMessage({ action: 'recordingTick', duration: recDuration });

      if (recDuration >= maxDuration) {
        stopRecording();
      }
    }, 1000);

    chrome.runtime.sendMessage({ action: 'recordingStarted', duration: 0 });
  }

  function stopRecording() {
    if (recTimer) {
      clearInterval(recTimer);
      recTimer = null;
    }
    if (mediaRecorder && mediaRecorder.state === 'recording') {
      mediaRecorder.stop();
    }
  }

  function cancelRecording() {
    if (recTimer) {
      clearInterval(recTimer);
      recTimer = null;
    }
    if (mediaRecorder && mediaRecorder.state === 'recording') {
      mediaRecorder.onstop = null; // Discard chunks
      mediaRecorder.stop();
    }
    if (recordStream) {
      recordStream.getTracks().forEach(function(t) { t.stop(); });
      recordStream = null;
    }
    chrome.storage.local.remove('tempRecording', function() {
      closeOffscreen();
    });
  }

  function closeOffscreen() {
    chrome.runtime.sendMessage({ action: 'closeOffscreenDoc' });
  }
})();
