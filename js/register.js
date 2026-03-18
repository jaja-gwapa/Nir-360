(function () {
  const form = document.getElementById('register-form');
  const alertEl = document.getElementById('alert');

  // Mobile number: digits only, no letters (max 15 digits)
  const mobileInput = document.getElementById('mobile');
  if (mobileInput) {
    function allowDigitsOnly(el, maxLen) {
      if (!el || maxLen == null) return;
      function strip() {
        var val = el.value.replace(/\D/g, '');
        if (val.length > maxLen) val = val.slice(0, maxLen);
        if (el.value !== val) el.value = val;
      }
      el.addEventListener('input', strip);
      el.addEventListener('paste', function () { setTimeout(strip, 0); });
      strip();
    }
    allowDigitsOnly(mobileInput, 15);
  }

  function showAlert(msg, isError = true) {
    alertEl.textContent = msg;
    alertEl.className = 'alert ' + (isError ? 'alert-error' : 'alert-success');
    alertEl.style.display = 'block';
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const email = document.getElementById('email').value.trim();
    const mobile = document.getElementById('mobile').value.trim();
    const password = document.getElementById('password').value;

    if (!email || !mobile || !password) {
      showAlert('Please fill in all fields.');
      return;
    }
    if (!isValidEmail(email)) {
      showAlert('Please enter a valid email address.');
      return;
    }
    if (!isValidMobile(mobile)) {
      showAlert('Please enter a valid mobile number (10–15 digits).');
      return;
    }
    if (isEmailTaken(email)) {
      showAlert('This email is already registered.');
      return;
    }
    if (isMobileTaken(mobile)) {
      showAlert('This mobile number is already registered.');
      return;
    }
    var pwdCheck = checkPasswordStrength(password);
    if (!pwdCheck.ok) {
      showAlert('Password: ' + pwdCheck.message);
      return;
    }

    const role = (document.getElementById('role') && document.getElementById('role').value) || 'civilian';
    setCurrentEmail(email);
    setAppState({
      email,
      mobile,
      registered: true,
      otpVerified: false,
      profileDone: false,
      idUploaded: false,
      verified: false,
      role: role
    });
    window.location.href = 'verify-otp.html';
  });
})();
