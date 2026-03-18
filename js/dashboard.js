(function () {
  const state = getAppState();

  if (!getCurrentEmail() || !state.registered) {
    window.location.href = 'index.html';
    return;
  }

  document.getElementById('user-email').textContent = state.email || '—';
  document.getElementById('user-mobile').textContent = state.mobile || '—';

  const status = state.verificationStatus || (state.idUploaded ? 'pending' : 'incomplete');
  document.getElementById('verification-status').textContent =
    state.verified ? 'Verified' : (state.idUploaded ? 'Pending' : 'Not submitted');

  const roleBadge = document.getElementById('role-badge');
  var roleLabel = (state.role || 'civilian');
  if (roleLabel === 'responder') roleLabel = 'Responder / Authority';
  else if (roleLabel === 'admin') roleLabel = 'System Admin';
  else roleLabel = 'Civilian';
  roleBadge.textContent = roleLabel;

  const verifiedBadge = document.getElementById('verified-badge');
  if (state.verified) verifiedBadge.style.display = 'inline-flex';

  // OCR comparison (name, birthdate, address match)
  var ocr = state.ocrResult;
  if (state.idUploaded && ocr) {
    document.getElementById('ocr-section').style.display = 'block';
    document.getElementById('ocr-name').textContent = ocr.nameMatch ? '✓ Match' : '✗ No match';
    document.getElementById('ocr-name').className = ocr.nameMatch ? 'match-ok' : 'match-fail';
    document.getElementById('ocr-birthdate').textContent = ocr.birthdateMatch ? '✓ Match' : '✗ No match';
    document.getElementById('ocr-birthdate').className = ocr.birthdateMatch ? 'match-ok' : 'match-fail';
    document.getElementById('ocr-address').textContent = ocr.addressMatch ? '✓ Match' : '✗ No match';
    document.getElementById('ocr-address').className = ocr.addressMatch ? 'match-ok' : 'match-fail';
  }

  // Admin panel link for system admins
  if (state.role === 'admin') {
    document.getElementById('admin-link-wrap').style.display = 'block';
  }
})();
