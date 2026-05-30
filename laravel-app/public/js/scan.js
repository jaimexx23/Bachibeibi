// Copied and adapted from original static/scan.js
let lastScanTime = 0;
let lastCode = "";

function extractStudentCode(rawText) {
  const text = String(rawText || "").trim();
  if (!text) return "";
  try {
    if (text.includes('://')) {
      const url = new URL(text);
      const fromQuery = url.searchParams.get('code') || url.searchParams.get('student_code') || url.searchParams.get('alumno') || url.searchParams.get('id');
      if (fromQuery) return fromQuery.trim().toUpperCase();
      const parts = url.pathname.split('/').filter(Boolean);
      if (parts.length > 0) return parts[parts.length - 1].trim().toUpperCase();
    }
  } catch (_err) {}
  const prefixed = text.match(/(?:ALUMNO|STUDENT(?:_CODE)?|CODIGO|CODE)\s*[:=\-]\s*([A-Z0-9\-_.]+)/i);
  if (prefixed) return prefixed[1].trim().toUpperCase();
  const token = text.toUpperCase().match(/[A-Z0-9][A-Z0-9\-_.]{1,}/);
  return token ? token[0].trim().toUpperCase() : "";
}

async function sendCheckin(code, source = "qr") {
  const normalizedCode = extractStudentCode(code);
  const resultBox = document.getElementById('result');
  if (!normalizedCode) {
    resultBox.innerHTML = `<div class="error">No se pudo leer un codigo valido del QR.</div>`;
    return;
  }
  const now = Date.now();
  if (normalizedCode === lastCode && now - lastScanTime < 4000) return;
  lastCode = normalizedCode; lastScanTime = now;
  
  const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';
  try {
    resultBox.innerHTML = `<div class="info">Enviando...</div>`;
    const response = await fetch('/api/checkin', { 
      method: 'POST', 
      credentials: 'same-origin',
      headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken}, 
      body: JSON.stringify({ student_code: normalizedCode, source }) 
    });

    // Read response body once as text, then try to parse JSON
    let data, text;
    try {
      text = await response.text();
      try { data = JSON.parse(text); } catch (e) { data = null; }
    } catch (e) {
      text = null;
      data = null;
    }

      // Debug: log response for troubleshooting on mobile
      try { console.log('checkin response', { status: response.status, ok: response.ok, text: text, data: data }); } catch (e) {}

      // Determine duplicate case by HTTP status or response message (more robust)
      const combinedText = String((data && data.message) ? data.message : (text || '')).trim();
      const isDuplicate = response.status === 409 || /ya registrada|asistencia ya registrada|ya escaneado|already registered|already scanned/i.test(combinedText);
    if (isDuplicate) {
      setReaderState('duplicate');
      const studentPart = data && data.student ? ` <strong>${data.student}</strong>` : '';
      const short = data && data.student ? `${data.student}` : 'QR ya escaneado hoy';
      showScanFeedback(`QR ya escaneado hoy — ${short}`, 'duplicate');
      resultBox.innerHTML = `<div class="info">QR ya escaneado hoy${studentPart}. ${data && data.message ? data.message : ''}</div>`;
      updateLastScanned(short, (data && data.time) ? data.time : data && data.date ? data.date : '');
      return;
    }

    if (response.ok) {
      // Successful check-in
      setReaderState('success');
      if (data) {
        resultBox.innerHTML = `<div class="ok">Escaneo exitoso — <strong>${data.student}</strong> (${data.classroom})<br><small>${data.message} — ${data.time || ''}</small></div>`;
        showScanFeedback(`¡Escaneo exitoso — ${data.student}!`, 'success');
        updateLastScanned(data.student, data.time || data.date || '');
      } else {
        resultBox.innerHTML = `<div class="ok">Escaneo exitoso — Asistencia registrada</div>`;
        showScanFeedback('¡Escaneo exitoso!', 'success');
        updateLastScanned('Asistencia registrada', '');
      }
    } else {
      // Show useful debug info for other errors
      const msg = data && data.message ? data.message : (text ? text : `HTTP ${response.status}`);
      const studentPart = data && data.student ? `: <strong>${data.student}</strong>` : '';
      resultBox.innerHTML = `<div class="error">${msg}${studentPart}</div>`;
      setReaderState('error');
      showScanFeedback(msg, 'error');
      console.error('checkin error', { status: response.status, data: data, text: text });

      // If CSRF (419), retry with GET fallback endpoint
      if (response.status === 419 || response.status === 0) {
        try {
          const fallback = await fetch(`/api/checkin/${encodeURIComponent(normalizedCode)}`, { method: 'GET', credentials: 'same-origin' });
          const ftext = await fallback.text();
          if (fallback.ok) {
            resultBox.innerHTML = `<div class="ok">Escaneo exitoso (fallback)</div>`;
            return;
          } else {
            console.error('fallback error', { status: fallback.status, body: ftext });
            resultBox.innerHTML = `<div class="error">Error fallback: HTTP ${fallback.status}</div>`;
            return;
          }
        } catch (e) {
          console.error('fallback fetch failed', e);
        }
      }
    }
  } catch (err) {
    console.error('checkin error', err);
    resultBox.innerHTML = `<div class="error">Error al enviar la solicitud. Si el problema persiste, usa el formulario sin JavaScript más abajo.</div>`;
  }
}

function onScanSuccess(decodedText) {
  try {
    const resultBox = document.getElementById('result');
    if (resultBox) {
      const code = String(decodedText || '').trim();
      resultBox.innerHTML = `<div class="info">Detectado: <strong>${code}</strong>. Enviando...</div>`;
    }
  } catch (e) { console.warn('onScanSuccess display error', e); }
  sendCheckin(decodedText, 'qr');
}
function onScanError() { return; }

window.manualCheckin = function manualCheckin() {
  const input = document.getElementById('manualCode');
  const code = input.value.trim(); if (!code) return; sendCheckin(code, 'manual'); input.value = '';
};

const readerElement = document.getElementById('reader');

// Visual state helper for reader box: 'success', 'duplicate', 'error', or null
let _readerStateTimer = null;
function setReaderState(state) {
  if (!readerElement) return;
  readerElement.classList.remove('scan-success', 'scan-duplicate', 'scan-error');
  if (_readerStateTimer) { clearTimeout(_readerStateTimer); _readerStateTimer = null; }
  if (!state) return;
  if (state === 'success') readerElement.classList.add('scan-success');
  else if (state === 'duplicate') readerElement.classList.add('scan-duplicate');
  else if (state === 'error') readerElement.classList.add('scan-error');
  _readerStateTimer = setTimeout(() => { readerElement.classList.remove('scan-success', 'scan-duplicate', 'scan-error'); _readerStateTimer = null; }, 2500);
}

// Big overlay feedback inside reader for immediate visibility
let _feedbackTimer = null;
function showScanFeedback(message, type) {
  try {
    const fb = document.getElementById('scanFeedback');
    if (!fb) return;
    fb.className = 'scan-feedback';
    if (type === 'success' || type === 'ok') fb.classList.add('ok');
    else if (type === 'duplicate') fb.classList.add('duplicate');
    else if (type === 'error') fb.classList.add('error');
    fb.innerText = String(message || '').trim();
    fb.classList.add('visible');
    if (_feedbackTimer) clearTimeout(_feedbackTimer);
    _feedbackTimer = setTimeout(() => { try { fb.classList.remove('visible'); } catch (e) {} _feedbackTimer = null; }, 2500);
  } catch (e) { console.warn('showScanFeedback failed', e); }
}

function updateLastScanned(name, time) {
  try {
    const el = document.getElementById('lastScanned');
    if (!el) return;
    el.hidden = false;
    el.innerText = `${name} — ${time || ''}`;
    // Fade out after a while
    setTimeout(() => { try { el.hidden = false; } catch (e) {} }, 1000);
  } catch (e) { console.warn('updateLastScanned failed', e); }
}
async function tryStartCamera() {
  if (!readerElement) return false;
  // Prefer the high-level scanner if available
  if (typeof Html5QrcodeScanner !== 'undefined') {
    try {
      // Prefer rear camera via videoConstraints facingMode
      const cfg = { fps: 10, qrbox: { width: 250, height: 250 }, videoConstraints: { facingMode: { ideal: 'environment' } } };
      const html5QrCode = new Html5QrcodeScanner('reader', cfg);
      html5QrCode.render(onScanSuccess, onScanError);
      return true;
    } catch (e) {
      console.warn('Html5QrcodeScanner failed, falling back', e);
    }
  }

  // Fallback to Html5Qrcode direct usage
  if (typeof Html5Qrcode !== 'undefined') {
    try {
      const cameras = await Html5Qrcode.getCameras();
      let cameraId = null;
      if (cameras && cameras.length) {
        // Prefer a rear/back/environment camera when available
        const preferred = cameras.find(c => /back|rear|environment|rear camera|camera 1/i.test(c.label || ''))
          || cameras[cameras.length - 1];
        cameraId = preferred.id;
      }
      const html5Qr = new Html5Qrcode('reader');
      // First try starting with facingMode environment to force rear camera on mobiles
      try {
        await html5Qr.start({ facingMode: { ideal: 'environment' } }, { fps: 10, qrbox: 250 }, (decoded) => onScanSuccess(decoded), (err) => onScanError(err));
        return true;
      } catch (startErr) {
        console.warn('start with facingMode failed, trying camera list fallback', startErr);
        if (cameraId) {
          try {
            await html5Qr.start(cameraId, { fps: 10, qrbox: 250 }, (decoded) => onScanSuccess(decoded), (err) => onScanError(err));
            return true;
          } catch (err2) {
            console.warn('Failed to start camera by id', err2);
          }
        }
      }
      console.warn('No cameras started');
    } catch (e) {
      console.warn('Html5Qrcode start failed', e);
    }
  }
  return false;
}

// Try to start camera on load
tryStartCamera().then((started) => {
  if (!started) {
    console.info('Camera scanner did not start; file upload fallback available.');
  }
});

// DOM-ready safety: ensure manual button uses the JS handler and result element exists
document.addEventListener('DOMContentLoaded', () => {
  const resultBox = document.getElementById('result');
  if (!resultBox) {
    // If UI missing, create a minimal result box to show messages
    const fallback = document.createElement('div');
    fallback.id = 'result';
    fallback.className = 'result';
    const main = document.querySelector('main.container') || document.body;
    main.appendChild(fallback);
    console.warn('Result box was missing; created fallback #result element.');
  }

  // Replace inline onclick manual handler with an event listener to avoid scope issues
  const inlineBtn = document.querySelector('button[onclick="manualCheckin()"]');
  if (inlineBtn) {
    try { inlineBtn.removeAttribute('onclick'); } catch (e) {}
    inlineBtn.addEventListener('click', () => { try { window.manualCheckin(); } catch (e) { console.error(e); } });
  }

  console.log('scan.js initialized');
});

// Populate debug panel if present
// debug panel removed in production

// File upload scanning fallback
const scanFileBtn = document.getElementById('scanFileBtn');
const qrFileInput = document.getElementById('qrFileInput');
if (qrFileInput) {
  // Always open file picker when user clicks the button
  if (scanFileBtn) {
    scanFileBtn.addEventListener('click', (e) => {
      e.preventDefault();
      qrFileInput.click();
    });
  }

  // When a file is selected, try to scan it
  qrFileInput.addEventListener('change', async () => {
    const files = qrFileInput.files;
    const resultBox = document.getElementById('result');
    if (!files || files.length === 0) { resultBox.innerHTML = `<div class="error">Selecciona una imagen primero.</div>`; return; }
    const file = files[0];
    resultBox.innerHTML = `<div class="info">Escaneando imagen...</div>`;
    // Try Html5Qrcode APIs if available
    if (typeof Html5Qrcode !== 'undefined') {
      try {
        if (typeof Html5Qrcode.scanFileV2 === 'function') {
          const r = await Html5Qrcode.scanFileV2(file, { fps: 1, qrbox: 250 });
          if (r && r.decodedText) {
            onScanSuccess(r.decodedText);
            return;
          }
        }
        // legacy fallback
        if (typeof Html5Qrcode.scanFile === 'function') {
          const legacy = await Html5Qrcode.scanFile(file, true);
          if (legacy) { onScanSuccess(legacy); return; }
        }
        resultBox.innerHTML = `<div class="error">No se encontró un QR válido en la imagen.</div>`;
      } catch (e) {
        console.error('scanFile error', e);
        resultBox.innerHTML = `<div class="error">Error al procesar la imagen: ${String(e)}</div>`;
      }
    } else {
      resultBox.innerHTML = `<div class="error">Lib de escaneo no disponible en esta página.</div>`;
    }
  });
}
