(function () {
  const state = getAppState();
  if (!state.registered) {
    window.location.href = 'register.html';
    return;
  }

  document.getElementById('send-to').textContent = state.mobile || 'your mobile';

  const form = document.getElementById('otp-form');
  const alertEl = document.getElementById('alert');
  const countdownEl = document.getElementById('countdown');
  const resendLabel = document.getElementById('resend-label');
  const resendLink = document.getElementById('resend-link');

  let secondsLeft = 60;
  let timer = null;

  function showAlert(msg, isError = true) {
    alertEl.textContent = msg;
    alertEl.className = 'alert ' + (isError ? 'alert-error' : 'alert-success');
    alertEl.style.display = 'block';
  }

  function startCountdown() {
    secondsLeft = 60;
    resendLink.style.display = 'none';
    resendLabel.style.display = '';
    countdownEl.textContent = secondsLeft;
    if (timer) clearInterval(timer);
    timer = setInterval(function () {
      secondsLeft--;
      countdownEl.textContent = secondsLeft;
      if (secondsLeft <= 0) {
        clearInterval(timer);
        resendLabel.style.display = 'none';
        resendLink.style.display = '';
      }
    }, 1000);
  }

  startCountdown();

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const otp = document.getElementById('otp').value.trim();
    if (!otp || otp.length !== 6) {
      showAlert('Enter the 6-digit code.');
      return;
    }
    // Mock: any 6 digits accept (e.g. 123456)
    setAppState({ otpVerified: true });
    window.location.href = 'profile.html';
  });

  resendLink.addEventListener('click', function (e) {
    e.preventDefault();
    startCountdown();
    showAlert('Code resent (mock).', false);
  });
})();
