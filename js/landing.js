(function () {
  var modal = document.getElementById('create-account-modal');
  if (!modal) return;

  function openModal() {
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.getElementById('modal-alert').style.display = 'none';
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.btn-modal-open').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal();
    });
  });

  modal.querySelector('[data-modal-close]').addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });

  var form = document.getElementById('modal-register-form');
  var alertEl = document.getElementById('modal-alert');
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var email = (document.getElementById('modal-email').value || '').trim();
    var mobile = (document.getElementById('modal-mobile').value || '').trim();
    var password = document.getElementById('modal-password').value;
    var role = (document.getElementById('modal-role') && document.getElementById('modal-role').value) || 'civilian';

    if (!email || !mobile || !password) {
      alertEl.textContent = 'Please fill in all required fields.';
      alertEl.className = 'alert alert-error';
      alertEl.style.display = 'block';
      return;
    }
    if (!isValidEmail(email)) {
      alertEl.textContent = 'Please enter a valid email address.';
      alertEl.className = 'alert alert-error';
      alertEl.style.display = 'block';
      return;
    }
    if (!isValidMobile(mobile)) {
      alertEl.textContent = 'Please enter a valid mobile number (10–15 digits).';
      alertEl.className = 'alert alert-error';
      alertEl.style.display = 'block';
      return;
    }
    if (isEmailTaken(email)) {
      alertEl.textContent = 'This email is already registered.';
      alertEl.className = 'alert alert-error';
      alertEl.style.display = 'block';
      return;
    }
    if (isMobileTaken(mobile)) {
      alertEl.textContent = 'This mobile number is already registered.';
      alertEl.className = 'alert alert-error';
      alertEl.style.display = 'block';
      return;
    }
    var pwdCheck = checkPasswordStrength(password);
    if (!pwdCheck.ok) {
      alertEl.textContent = 'Password: ' + pwdCheck.message;
      alertEl.className = 'alert alert-error';
      alertEl.style.display = 'block';
      return;
    }

    setCurrentEmail(email);
    setAppState({
      email: email,
      mobile: mobile,
      registered: true,
      otpVerified: false,
      profileDone: false,
      idUploaded: false,
      verified: false,
      role: role
    });
    closeModal();
    window.location.href = 'verify-otp.html';
  });
})();
