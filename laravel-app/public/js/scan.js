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
  const response = await fetch('/api/checkin', { 
    method: 'POST', 
    headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken}, 
    body: JSON.stringify({ student_code: normalizedCode, source }) 
  });
  const data = await response.json();
  if (response.ok) { resultBox.innerHTML = `<div class="ok">${data.message}: <strong>${data.student}</strong> (${data.classroom}) - ${data.time}</div>`; }
  else { resultBox.innerHTML = `<div class="error">${data.message}${data.student ? `: <strong>${data.student}</strong>` : ''}</div>`; }
}

function onScanSuccess(decodedText) { sendCheckin(decodedText, 'qr'); }
function onScanError() { return; }

window.manualCheckin = function manualCheckin() {
  const input = document.getElementById('manualCode');
  const code = input.value.trim(); if (!code) return; sendCheckin(code, 'manual'); input.value = '';
};

const readerElement = document.getElementById('reader');
if (readerElement && typeof Html5QrcodeScanner !== 'undefined') {
  const html5QrCode = new Html5QrcodeScanner('reader', { fps: 10, qrbox: { width: 250, height: 250 } });
  html5QrCode.render(onScanSuccess, onScanError);
}
