(function () {
  'use strict';

  const csrfToken = typeof window.NIR360_CSRF !== 'undefined' ? window.NIR360_CSRF : '';

  function getBaseUrl() {
    const s = document.querySelector('script[src*="app.js"]');
    if (s && s.src) {
      const u = new URL(s.src);
      return u.origin + u.pathname.replace(/\/assets\/app\.js$/, '');
    }
    return '';
  }
  const baseUrl = getBaseUrl() || (window.location.pathname.replace(/\/$/, ''));

  function showEl(id, show) {
    const el = document.getElementById(id);
    if (el) el.style.display = show ? 'block' : 'none';
  }
  function setText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  // Restrict contact / emergency contact inputs to digits only (max 11), no letters
  function allowDigitsOnly(inputEl, maxLen) {
    if (!inputEl || maxLen == null) return;
    function strip() {
      var val = inputEl.value.replace(/\D/g, '');
      if (val.length > maxLen) val = val.slice(0, maxLen);
      if (inputEl.value !== val) inputEl.value = val;
    }
    inputEl.addEventListener('input', strip);
    inputEl.addEventListener('paste', function () { setTimeout(strip, 0); });
    strip();
  }
  allowDigitsOnly(document.getElementById('reg-mobile'), 11);

  // --- Modals ---
  document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const name = btn.getAttribute('data-modal-open');
      const closeCurrent = btn.getAttribute('data-modal-close-current');
      if (closeCurrent) closeModal(closeCurrent);
      const modal = document.getElementById('modal-' + name);
      if (modal) {
        document.querySelectorAll('.modal').forEach(function (m) { m.classList.remove('is-open'); });
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        if (name === 'login') switchLoginModalView('login');
      }
    });
  });

  // Login modal: switch between Login and Forgot Password views (no separate page)
  function switchLoginModalView(view) {
    var loginView = document.getElementById('login-view');
    var forgotView = document.getElementById('forgot-view');
    var titleEl = document.getElementById('modal-login-title');
    if (view === 'forgot') {
      if (loginView) loginView.style.display = 'none';
      if (forgotView) forgotView.style.display = 'block';
      if (titleEl) titleEl.textContent = 'Forgot Password';
    } else {
      if (loginView) loginView.style.display = 'block';
      if (forgotView) forgotView.style.display = 'none';
      if (titleEl) titleEl.textContent = 'Login';
    }
  }
  document.querySelectorAll('[data-auth-view]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var view = btn.getAttribute('data-auth-view');
      switchLoginModalView(view);
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const modal = btn.closest('.modal');
      if (modal) {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
      }
    });
  });

  document.querySelectorAll('.modal').forEach(function (modal) {
    modal.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
      backdrop.addEventListener('click', function () {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
      });
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal.is-open').forEach(function (m) {
        m.classList.remove('is-open');
        m.setAttribute('aria-hidden', 'true');
      });
    }
  });

  function openModal(name) {
    const modal = document.getElementById('modal-' + name);
    if (modal) {
      document.querySelectorAll('.modal').forEach(function (m) { m.classList.remove('is-open'); });
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
    }
  }
  function closeModal(name) {
    const modal = document.getElementById('modal-' + name);
    if (modal) {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    }
  }

  // --- Register (multipart: basic info + role + ID files) ---
  const formCreate = document.getElementById('form-create-account');
  if (formCreate) {
    formCreate.addEventListener('submit', function (e) {
      e.preventDefault();
      const errEl = document.getElementById('create-account-error');
      errEl.style.display = 'none';
      errEl.textContent = '';

      var regBarangay = document.getElementById('reg-barangay');
      var regStreet = document.getElementById('reg-street-address');
      var regAddress = document.getElementById('reg-address');
      if (regAddress && regStreet && regBarangay) {
        var regProvince = document.getElementById('reg-province');
        var regCity = document.getElementById('reg-city');
        var province = (regProvince && regProvince.value.trim()) || 'Negros Occidental';
        var city = (regCity && regCity.value.trim()) || 'Bago City';
        var parts = [regStreet.value.trim(), regBarangay.value.trim(), city, province].filter(Boolean);
        regAddress.value = parts.join(', ');
      }

      var regMobile = document.getElementById('reg-mobile');
      if (regMobile) {
        var raw = (regMobile.value || '').trim();
        var digits = raw.replace(/\D/g, '');
        if (/\D/.test(raw)) {
          errEl.textContent = 'Contact number must contain only numbers.';
          errEl.style.display = 'block';
          return;
        }
        if (digits.length !== 11) {
          errEl.textContent = 'Contact number must be exactly 11 digits.';
          errEl.style.display = 'block';
          return;
        }
      }

      var confirmPass = document.getElementById('reg-confirm-password');
      var pass = document.getElementById('reg-password');
      if (pass && confirmPass && pass.value !== confirmPass.value) {
        errEl.textContent = 'Password and Confirm Password do not match.';
        errEl.style.display = 'block';
        return;
      }

      var formData = new FormData(formCreate);
      formData.set('csrf_token', csrfToken);

      var submitBtn = document.getElementById('reg-submit-btn');
      var successEl = document.getElementById('create-account-success');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Registering...';
      }
      if (successEl) successEl.style.display = 'none';

      fetch(baseUrl + '/api/register', {
        method: 'POST',
        body: formData
      })
        .then(function (r) {
          var ct = (r.headers.get('Content-Type') || '').toLowerCase();
          if (ct.indexOf('application/json') !== -1) {
            return r.json().then(function (j) { return { ok: r.ok, status: r.status, json: j }; });
          }
          return r.text().then(function (text) {
            console.error('Register response (non-JSON):', r.status, text.substring(0, 500));
            var msg = 'Server error (not JSON). ';
            if (text.indexOf('Fatal error') !== -1 || text.indexOf('PDOException') !== -1) {
              var m = text.match(/Fatal error[^<]*|Column not found[^<.]*|Unknown column [^<]*/);
              if (m) msg += m[0].replace(/\s+/g, ' ').trim().substring(0, 120);
              else msg += 'Database or PHP error. If the error mentions "province", run nir360/sql/fix_users_table_location_only.sql in phpMyAdmin; otherwise run fix_users_table.sql.';
            } else {
              msg += 'Check Console (F12) for details.';
            }
            return { ok: false, status: r.status, json: { success: false, error: msg } };
          });
        })
        .then(function (res) {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Register';
          }
          if (res.json && res.json.success) {
            errEl.style.display = 'none';
            if (successEl) {
              successEl.textContent = 'SUCCESSFUL REGISTERED';
              successEl.style.display = 'block';
              if (successEl.scrollIntoView) successEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            setTimeout(function () {
              closeModal('create-account');
              if (res.json.open_otp_modal) {
                var otpUserId = document.getElementById('otp-user-id');
                if (otpUserId) otpUserId.value = res.json.user_id || '';
                var msg = res.json.message || 'Account created. Verify your OTP.';
                setText('otp-message', msg);
                var devCode = document.getElementById('otp-dev-code');
                if (res.json.dev_otp && devCode) {
                  devCode.textContent = 'DEV OTP: ' + res.json.dev_otp;
                  devCode.style.display = 'block';
                }
                showEl('otp-error', false);
                var oc = document.getElementById('otp-code');
                if (oc) oc.value = '';
              openModal('otp');
              startOtpCooldown(res.json.wait_seconds || 60);
              var smsNote = document.getElementById('otp-sms-note');
              if (smsNote) {
                if (res.json.registration_sms_sent === false || res.json.otp_sms_sent === false) {
                  smsNote.textContent = 'SMS may not have been sent. Check that your number is 09XXXXXXXXX and try Resend OTP, or use the code shown below (if in dev).';
                  smsNote.style.display = 'block';
                } else {
                  smsNote.style.display = 'none';
                  smsNote.textContent = '';
                }
              }
            }
              if (successEl) successEl.style.display = 'none';
            }, 1800);
          } else {
            errEl.textContent = res.json.error || 'Registration failed.';
            errEl.style.display = 'block';
            if (errEl.scrollIntoView) errEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }
        })
        .catch(function (err) {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Register';
          }
          if (successEl) successEl.style.display = 'none';
          errEl.textContent = 'Network or server error. Check the browser Console (F12) for details.';
          errEl.style.display = 'block';
          if (errEl.scrollIntoView) errEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          console.error('Registration error:', err);
        });
    });

    // Verify ID (OCR): shared logic for button and auto-trigger
    var btnVerifyId = document.getElementById('btn-verify-id');
    var idOcrResult = document.getElementById('id-ocr-result');
    function composeFullName(firstName, middleName, lastName) {
      return [firstName, middleName, lastName].map(function (v) { return (v || '').trim(); }).filter(Boolean).join(' ');
    }

    function runIdVerification(isAutoTrigger) {
      var idFront = document.getElementById('reg-id-front');
      var firstName = (document.getElementById('reg-first-name') && document.getElementById('reg-first-name').value) || '';
      var middleName = (document.getElementById('reg-middle-name') && document.getElementById('reg-middle-name').value) || '';
      var lastName = (document.getElementById('reg-last-name') && document.getElementById('reg-last-name').value) || '';
      var fullName = composeFullName(firstName, middleName, lastName);
      var birthdate = (document.getElementById('reg-birthdate') && document.getElementById('reg-birthdate').value) || '';
      var barangayEl = document.getElementById('reg-barangay');
      var barangay = (barangayEl && barangayEl.value) ? barangayEl.value.trim() : '';
      if (!idOcrResult) return;
      idOcrResult.className = 'id-ocr-result';
      idOcrResult.style.display = 'none';
      idOcrResult.innerHTML = '';
      if (!idFront || !idFront.files || !idFront.files[0]) {
        if (!isAutoTrigger) {
          idOcrResult.className = 'id-ocr-result id-ocr-result--error';
          idOcrResult.innerHTML = '<p>Please upload an ID image (front) first. Use JPEG or PNG for verification.</p>';
          idOcrResult.style.display = 'block';
        }
        return;
      }
      var file = idFront.files[0];
      var type = (file.type || '').toLowerCase();
      if (type.indexOf('jpeg') === -1 && type.indexOf('png') === -1 && type !== 'image/jpeg' && type !== 'image/png') {
        idOcrResult.className = 'id-ocr-result id-ocr-result--error';
        idOcrResult.innerHTML = '<p>ID verification supports JPEG or PNG only. Please choose a .jpg or .png image.</p>';
        idOcrResult.style.display = 'block';
        return;
      }
      if (!firstName.trim() || !lastName.trim()) {
        if (isAutoTrigger) {
          idOcrResult.className = 'id-ocr-result';
          idOcrResult.innerHTML = '<p class="hint">Fill first and last name, birthdate, and select Barangay above; verification will run when you have an ID image.</p>';
          idOcrResult.style.display = 'block';
        } else {
          idOcrResult.className = 'id-ocr-result id-ocr-result--error';
          idOcrResult.innerHTML = '<p>Enter your first and last name above (as on the ID) before verifying.</p>';
          idOcrResult.style.display = 'block';
        }
        return;
      }
      if (!barangay) {
        if (!isAutoTrigger) {
          idOcrResult.className = 'id-ocr-result id-ocr-result--error';
          idOcrResult.innerHTML = '<p>Select your Barangay above before verifying. The selected barangay must appear on the ID address.</p>';
          idOcrResult.style.display = 'block';
        }
        return;
      }
      if (!birthdate.trim()) {
        if (!isAutoTrigger) {
          idOcrResult.className = 'id-ocr-result id-ocr-result--error';
          idOcrResult.innerHTML = '<p>Enter your birthdate above (as on the ID) before verifying.</p>';
          idOcrResult.style.display = 'block';
        } else {
          idOcrResult.className = 'id-ocr-result';
          idOcrResult.innerHTML = '<p class="hint">Fill first and last name, birthdate, and select Barangay above; verification will run when all are filled.</p>';
          idOcrResult.style.display = 'block';
        }
        return;
      }
      // --- Show loading state (always) ---
      if (btnVerifyId) {
        btnVerifyId.disabled = true;
        btnVerifyId.setAttribute('aria-busy', 'true');
      }
      idOcrResult.className = 'id-ocr-result id-ocr-result--loading';
      idOcrResult.innerHTML = '<span class="id-ocr-spinner" aria-hidden="true"></span><span>Verifying your government ID, please wait…</span>';
      idOcrResult.style.display = 'flex';
      if (idOcrResult.scrollIntoView) idOcrResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

      var streetEl = document.getElementById('reg-street-address');
      var streetAddress = (streetEl && streetEl.value) ? streetEl.value.trim() : '';
      var fd = new FormData();
      fd.append('id_image', file);
      fd.append('first_name', firstName.trim());
      fd.append('middle_name', middleName.trim());
      fd.append('last_name', lastName.trim());
      fd.append('full_name', fullName.trim());
      fd.append('birthdate', birthdate.trim());
      fd.append('barangay', barangay);
      fd.append('street_address', streetAddress);
      fd.append('csrf_token', csrfToken);

      fetch(baseUrl + '/api/id-ocr/verify', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (btnVerifyId) {
            btnVerifyId.disabled = false;
            btnVerifyId.setAttribute('aria-busy', 'false');
          }
          var verified = (data.verification_status || '').toLowerCase() === 'verified' && !data.error;
          var rejectionMsg = data.rejection_message || (data.verification_status === 'rejected' ? null : null);
          if (!rejectionMsg && (data.verification_status || '').toLowerCase() === 'rejected') {
            rejectionMsg = 'The information on your ID does not match the details you entered.';
          }

          if (data.error) {
            // --- Error: server or validation failure ---
            idOcrResult.className = 'id-ocr-result id-ocr-result--error';
            idOcrResult.style.display = 'block';
            idOcrResult.innerHTML = '<div class="id-ocr-error-heading">Verification failed</div><p>' + (data.error || 'Please ensure your ID image is clear and try again.').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>';
          } else if (verified && !rejectionMsg) {
            // --- Success: ID matches user input ---
            idOcrResult.className = 'id-ocr-result id-ocr-result--success';
            idOcrResult.style.display = 'block';
            idOcrResult.innerHTML = '<div class="id-ocr-success-heading">ID verified successfully</div><p>The information on your ID matches your registration details. You may proceed to register.</p>';
          } else {
            // --- Mismatch: show server message (which field failed) if available ---
            idOcrResult.className = 'id-ocr-result id-ocr-result--error';
            idOcrResult.style.display = 'block';
            var errText = rejectionMsg && rejectionMsg.trim() ? rejectionMsg.trim() : 'The information on your ID does not match the details you entered. Please type your name, birthdate, and address exactly as on the ID and use a clear photo.';
            idOcrResult.innerHTML = '<div class="id-ocr-error-heading">ID does not match</div><p>' + errText.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>';
          }
          if (idOcrResult.scrollIntoView) idOcrResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        })
        .catch(function () {
          if (btnVerifyId) {
            btnVerifyId.disabled = false;
            btnVerifyId.setAttribute('aria-busy', 'false');
          }
          idOcrResult.className = 'id-ocr-result id-ocr-result--error';
          idOcrResult.style.display = 'block';
          idOcrResult.innerHTML = '<div class="id-ocr-error-heading">Verification could not be completed</div><p>Please check your connection and try again.</p>';
          if (idOcrResult.scrollIntoView) idOcrResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }
    if (btnVerifyId && idOcrResult) {
      btnVerifyId.addEventListener('click', function () { runIdVerification(false); });
    }

    // Auto-trigger verification when ID is uploaded or when user edits personal info (debounced for text fields)
    var idFrontInput = document.getElementById('reg-id-front');
    var idPreviewWrap = document.getElementById('id-preview-wrap');
    var idPreviewImg = document.getElementById('id-preview-img');

    function hasIdImage() {
      var idFront = document.getElementById('reg-id-front');
      if (!idFront || !idFront.files || !idFront.files[0]) return false;
      var type = (idFront.files[0].type || '').toLowerCase();
      return type.indexOf('jpeg') !== -1 || type.indexOf('png') !== -1 || type === 'image/jpeg' || type === 'image/png';
    }

    if (idFrontInput && idPreviewWrap && idPreviewImg) {
      idFrontInput.addEventListener('change', function () {
        var file = this.files && this.files[0];
        if (!file || !file.type.match(/^image\/(jpeg|png)/)) {
          idPreviewWrap.style.display = 'none';
          idPreviewImg.src = '';
          if (idOcrResult) {
            idOcrResult.className = 'id-ocr-result';
            idOcrResult.style.display = 'none';
            idOcrResult.innerHTML = '';
          }
          return;
        }
        var reader = new FileReader();
        reader.onload = function (e) {
          idPreviewImg.src = e.target.result;
          idPreviewWrap.style.display = 'block';
        };
        reader.readAsDataURL(file);
      });
    }

  }

  // --- Login ---
  const formLogin = document.getElementById('form-login');
  if (formLogin) {
    formLogin.addEventListener('submit', function (e) {
      e.preventDefault();
      const errEl = document.getElementById('login-error');
      errEl.style.display = 'none';

      const payload = {
        csrf_token: csrfToken,
        email_or_mobile: (document.getElementById('login-email').value || '').trim(),
        password: document.getElementById('login-password').value
      };

      fetch(baseUrl + '/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
        .then(function (res) {
          if (res.json.success && res.json.redirect) {
            window.location.href = baseUrl + res.json.redirect;
          } else {
            errEl.textContent = res.json.error || 'Login failed.';
            errEl.style.display = 'block';
          }
        })
        .catch(function () {
          errEl.textContent = 'Network error. Try again.';
          errEl.style.display = 'block';
        });
    });
  }

  // --- Forgot Password ---
  const formForgot = document.getElementById('form-forgot-password');
  if (formForgot) {
    formForgot.addEventListener('submit', function (e) {
      e.preventDefault();
      const errEl = document.getElementById('forgot-password-error');
      const successEl = document.getElementById('forgot-password-success');
      errEl.style.display = 'none';
      successEl.style.display = 'none';

      const payload = {
        csrf_token: csrfToken,
        email_or_mobile: (document.getElementById('forgot-email').value || '').trim()
      };

      fetch(baseUrl + '/api/forgot-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
        .then(function (res) {
          if (res.json.success) {
            successEl.textContent = res.json.message || 'If the account exists, we sent instructions to reset your password.';
            successEl.style.display = 'block';
            formForgot.reset();
          } else {
            errEl.textContent = res.json.error || 'Something went wrong.';
            errEl.style.display = 'block';
          }
        })
        .catch(function () {
          errEl.textContent = 'Network error. Try again.';
          errEl.style.display = 'block';
        });
    });
  }

  // --- OTP ---
  let otpCooldownTimer = null;
  function startOtpCooldown(seconds) {
    const resendBtn = document.getElementById('otp-resend');
    const cooldownEl = document.getElementById('otp-cooldown');
    if (!resendBtn || !cooldownEl) return;
    resendBtn.disabled = true;
    let left = seconds;
    function tick() {
      if (left <= 0) {
        cooldownEl.textContent = '';
        resendBtn.disabled = false;
        if (otpCooldownTimer) clearInterval(otpCooldownTimer);
        return;
      }
      cooldownEl.textContent = 'Resend in ' + left + 's';
      left--;
    }
    tick();
    if (otpCooldownTimer) clearInterval(otpCooldownTimer);
    otpCooldownTimer = setInterval(tick, 1000);
  }

  const formOtp = document.getElementById('form-otp');
  if (formOtp) {
    formOtp.addEventListener('submit', function (e) {
      e.preventDefault();
      const errEl = document.getElementById('otp-error');
      errEl.style.display = 'none';

      const code = (document.getElementById('otp-code').value || '').trim();
      const userId = document.getElementById('otp-user-id').value;

      if (code.length !== 6) {
        errEl.textContent = 'Enter 6-digit code.';
        errEl.style.display = 'block';
        return;
      }

      fetch(baseUrl + '/api/otp/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: csrfToken, user_id: userId, code: code })
      })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
        .then(function (res) {
          if (res.json.success) {
            closeModal('otp');
            setText('otp-message', 'Account created. Verify your OTP.');
            window.location.reload();
          } else {
            errEl.textContent = res.json.error || 'Invalid code.';
            errEl.style.display = 'block';
          }
        })
        .catch(function () {
          errEl.textContent = 'Network error. Try again.';
          errEl.style.display = 'block';
        });
    });
  }

  const resendBtn = document.getElementById('otp-resend');
  if (resendBtn) {
    resendBtn.addEventListener('click', function () {
      if (resendBtn.disabled) return;
      const userId = document.getElementById('otp-user-id').value;
      const errEl = document.getElementById('otp-error');
      errEl.style.display = 'none';

      fetch(baseUrl + '/api/otp/resend', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: csrfToken, user_id: userId })
      })
        .then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
        .then(function (res) {
          if (res.json.success) {
            startOtpCooldown(res.json.wait_seconds || 60);
            const devCode = document.getElementById('otp-dev-code');
            if (res.json.dev_otp && devCode) {
              devCode.textContent = 'DEV OTP: ' + res.json.dev_otp;
              devCode.style.display = 'block';
            }
          } else {
            errEl.textContent = res.json.error || 'Could not resend.';
            errEl.style.display = 'block';
            if (res.json.wait_seconds) startOtpCooldown(res.json.wait_seconds);
          }
        });
    });
  }
})();
