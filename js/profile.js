(function () {
  const state = getAppState();
  if (!state.otpVerified) {
    window.location.href = 'verify-otp.html';
    return;
  }

  const form = document.getElementById('profile-form');
  const alertEl = document.getElementById('alert');

  function showAlert(msg, isError = true) {
    alertEl.textContent = msg;
    alertEl.className = 'alert ' + (isError ? 'alert-error' : 'alert-success');
    alertEl.style.display = 'block';
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const fullName = document.getElementById('fullName').value.trim();
    const birthdate = document.getElementById('birthdate').value;
    const address = document.getElementById('address').value.trim();
    const barangay = document.getElementById('barangay').value.trim();
    const emergencyContact = document.getElementById('emergencyContact').value.trim();

    if (!fullName || !birthdate || !address || !barangay || !emergencyContact) {
      showAlert('Please fill in all fields.');
      return;
    }

    setAppState({
      fullName,
      birthdate,
      address,
      barangay,
      emergencyContact,
      profileDone: true
    });
    window.location.href = 'upload-id.html';
  });
})();
