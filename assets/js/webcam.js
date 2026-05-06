/* ============================================================
   webcam.js — Webcam Capture for Visitor Photos v2
   IDs used by register_visitor.php:
     webcam-video, webcam-canvas, webcam-hidden, webcam-idle,
     webcam-status, btn-start-cam, btn-capture, btn-retake,
     ps-webcam (radio), upload-panel, webcam-panel
   ============================================================ */
(function () {
  'use strict';

  let stream = null;

  /* ── Public API ─────────────────────────────────────────── */
  window.Webcam = {

    /**
     * Wire up the register-visitor webcam UI.
     * Called automatically on DOMContentLoaded if #btn-start-cam exists.
     */
    initRegisterUI: function () {
      var video   = document.getElementById('webcam-video');
      var canvas  = document.getElementById('webcam-canvas');
      var idle    = document.getElementById('webcam-idle');
      var status  = document.getElementById('webcam-status');
      var hidden  = document.getElementById('webcam-hidden');
      var btnStart   = document.getElementById('btn-start-cam');
      var btnCapture = document.getElementById('btn-capture');
      var btnRetake  = document.getElementById('btn-retake');

      if (!btnStart) return; // not on register page

      // ── Start Camera ──────────────────────────────────────
      btnStart.addEventListener('click', function () {
        btnStart.disabled = true;
        if (status) status.textContent = 'Requesting camera access…';

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
          Webcam._permissionDenied(status, 'Camera API not supported in this browser.');
          btnStart.disabled = false;
          return;
        }

        navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480, facingMode: 'user' } })
          .then(function (s) {
            stream = s;
            video.srcObject = s;
            video.style.display = 'block';
            if (idle)   idle.style.display   = 'none';
            if (status) status.textContent   = '';
            btnStart.style.display   = 'none';
            if (btnCapture) { btnCapture.style.display = 'inline-flex'; }
          })
          .catch(function (err) {
            btnStart.disabled = false;
            var msg = (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError')
              ? 'Camera access was denied. Switching to file upload.'
              : 'Camera unavailable: ' + err.message;
            Webcam._permissionDenied(status, msg);
          });
      });

      // ── Capture ───────────────────────────────────────────
      if (btnCapture) {
        btnCapture.addEventListener('click', function () {
          if (!video || !stream) return;
          var ctx = canvas.getContext('2d');
          canvas.width  = video.videoWidth  || 320;
          canvas.height = video.videoHeight || 240;
          ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

          video.style.display  = 'none';
          canvas.style.display = 'block';
          this.style.display   = 'none';
          if (btnRetake) btnRetake.style.display = 'inline-flex';
          if (status)    status.textContent = 'Photo captured. Click Retake to redo.';

          if (hidden) hidden.value = canvas.toDataURL('image/jpeg', 0.85);

          // Stop stream after capture (saves battery)
          if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
          }

          // Update badge preview with captured photo
          var badgeImg = document.getElementById('badge-photo-img');
          var badgeAv  = document.getElementById('badge-initials-avatar');
          if (badgeImg && hidden.value) {
            badgeImg.src = hidden.value;
            badgeImg.style.display = 'block';
            if (badgeAv) badgeAv.style.display = 'none';
          }
        });
      }

      // ── Retake ────────────────────────────────────────────
      if (btnRetake) {
        btnRetake.addEventListener('click', function () {
          canvas.style.display  = 'none';
          this.style.display    = 'none';
          btnStart.style.display = 'inline-flex';
          btnStart.disabled     = false;
          if (status)  status.textContent = '';
          if (hidden)  hidden.value = '';

          // Restore badge initials avatar
          var badgeImg = document.getElementById('badge-photo-img');
          var badgeAv  = document.getElementById('badge-initials-avatar');
          if (badgeImg) badgeImg.style.display = 'none';
          if (badgeAv)  badgeAv.style.display  = 'flex';
        });
      }
    },

    /** Stop all camera tracks and release stream */
    stop: function () {
      if (stream) {
        stream.getTracks().forEach(function (t) { t.stop(); });
        stream = null;
      }
    },

    /**
     * Returns the current photo data string (base64) or empty string.
     * Reads from #webcam-hidden (webcam capture) or the file upload preview.
     */
    getPhotoData: function () {
      var hidden = document.getElementById('webcam-hidden');
      return hidden ? hidden.value : '';
    },

    /* ── Internal ─────────────────────────────────────────── */
    _permissionDenied: function (statusEl, msg) {
      // Switch to upload mode automatically
      var uploadRadio = document.getElementById('ps-upload');
      var webcamPanel = document.getElementById('webcam-panel');
      var uploadPanel = document.getElementById('upload-panel');
      if (uploadRadio) uploadRadio.checked = true;
      if (webcamPanel) webcamPanel.style.display = 'none';
      if (uploadPanel) uploadPanel.style.display = 'block';
      Webcam.stop();

      // Show info banner inside the photo widget
      if (statusEl) {
        statusEl.innerHTML =
          '<span style="color:var(--warning);"><i class="bi bi-info-circle"></i> ' +
          msg + ' Please upload a photo instead.</span>';
      }
      if (window.SVMS && SVMS.toast) {
        SVMS.toast(msg, 'warning');
      }
    }
  };

  /* ── Legacy generic API (used by older pages if any) ────── */
  Webcam.init = function (videoId, canvasId, triggerBtnId, retakeBtnId, hiddenInputId) {
    var video   = document.getElementById(videoId);
    var canvas  = document.getElementById(canvasId);
    var trigger = document.getElementById(triggerBtnId);
    var retake  = document.getElementById(retakeBtnId);
    var hidden  = document.getElementById(hiddenInputId);
    if (!video || !canvas) return;

    navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480, facingMode: 'user' } })
      .then(function (s) {
        stream = s;
        video.srcObject = s; video.play();
        video.style.display = 'block'; canvas.style.display = 'none';
        if (retake) retake.style.display = 'none';
      })
      .catch(function (err) {
        if (window.SVMS && SVMS.toast) SVMS.toast('Camera access denied: ' + err.message, 'error');
      });

    if (trigger) {
      trigger.addEventListener('click', function () {
        var ctx = canvas.getContext('2d');
        canvas.width = video.videoWidth || 320; canvas.height = video.videoHeight || 240;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        video.style.display = 'none'; canvas.style.display = 'block';
        if (retake) retake.style.display = 'inline-flex';
        trigger.style.display = 'none';
        if (hidden) hidden.value = canvas.toDataURL('image/jpeg', 0.85);
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
      });
    }
    if (retake) {
      retake.addEventListener('click', function () {
        Webcam.init(videoId, canvasId, triggerBtnId, retakeBtnId, hiddenInputId);
        if (trigger) trigger.style.display = 'inline-flex';
        this.style.display = 'none'; canvas.style.display = 'none';
        if (hidden) hidden.value = '';
      });
    }
  };

  // Stop on page unload
  window.addEventListener('beforeunload', Webcam.stop);

  // Auto-wire register-visitor UI on DOM ready
  document.addEventListener('DOMContentLoaded', function () {
    Webcam.initRegisterUI();
  });

})();
