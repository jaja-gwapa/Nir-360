(function () {
  const state = getAppState();
  if (!state.profileDone) {
    window.location.href = 'profile.html';
    return;
  }

  const form = document.getElementById('id-form');
  const alertEl = document.getElementById('alert');
  const submitBtn = document.getElementById('submit-btn');
  const frontInput = document.getElementById('front');
  const backInput = document.getElementById('back');
  const previewFront = document.getElementById('preview-front');
  const previewBack = document.getElementById('preview-back');
  const displayWrapFront = document.getElementById('display-wrap-front');
  const displayWrapBack = document.getElementById('display-wrap-back');
  const zoneFront = document.getElementById('zone-front');
  const zoneBack = document.getElementById('zone-back');

  let hasFront = false;
  let hasBack = false;

  function showAlert(msg, isError = true) {
    alertEl.textContent = msg;
    alertEl.className = 'alert ' + (isError ? 'alert-error' : 'alert-success');
    alertEl.style.display = 'block';
  }

  function updateSubmitButton() {
    submitBtn.disabled = !(hasFront && hasBack);
  }

  function setupZone(zoneId, input, preview, displayWrap, zoneEl, setFlag) {
    const zone = document.getElementById(zoneId);
    zone.addEventListener('click', function (e) {
      if (!e.target.closest('.id-remove-btn')) input.click();
    });
    zone.addEventListener('dragover', function (e) {
      e.preventDefault();
      zone.classList.add('dragover');
    });
    zone.addEventListener('dragleave', function () { zone.classList.remove('dragover'); });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      zone.classList.remove('dragover');
      if (e.dataTransfer.files.length) input.files = e.dataTransfer.files;
      input.dispatchEvent(new Event('change'));
    });
    input.addEventListener('change', function () {
      const file = input.files[0];
      if (!file || !file.type.startsWith('image/')) {
        setFlag(false);
        if (displayWrap) displayWrap.classList.remove('is-visible');
        if (zoneEl) zoneEl.style.display = '';
        updateSubmitButton();
        return;
      }
      const url = URL.createObjectURL(file);
      preview.src = url;
      preview.alt = 'Uploaded ID image';
      if (displayWrap) displayWrap.classList.add('is-visible');
      if (zoneEl) zoneEl.style.display = 'none';
      setFlag(true);
      updateSubmitButton();
    });
  }

  setupZone('zone-front', frontInput, previewFront, displayWrapFront, zoneFront, function (v) { hasFront = v; });
  setupZone('zone-back', backInput, previewBack, displayWrapBack, zoneBack, function (v) { hasBack = v; });

  function removeImage(side) {
    if (side === 'front') {
      frontInput.value = '';
      previewFront.src = '';
      hasFront = false;
      displayWrapFront.classList.remove('is-visible');
      zoneFront.style.display = '';
    } else {
      backInput.value = '';
      previewBack.src = '';
      hasBack = false;
      displayWrapBack.classList.remove('is-visible');
      zoneBack.style.display = '';
    }
    updateSubmitButton();
  }

  document.querySelectorAll('.id-remove-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      removeImage(btn.getAttribute('data-side'));
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!hasFront || !hasBack) {
      showAlert('Please upload both front and back of your ID.');
      return;
    }
    // Mock: OCR comparison (name, birthdate, address match) – backend would do real OCR
    var state = getAppState();
    var ocrResult = {
      nameMatch: true,
      birthdateMatch: true,
      addressMatch: true
    };
    // Mark submitted; verification pending (admin fallback if OCR fails)
    setAppState({
      idUploaded: true,
      verificationStatus: 'pending',
      verified: false,
      ocrResult: ocrResult
    });
    window.location.href = 'dashboard.html';
  });
})();
