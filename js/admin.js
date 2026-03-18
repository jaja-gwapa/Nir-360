(function () {
  const loginDiv = document.getElementById('admin-login');
  const panelDiv = document.getElementById('admin-panel');
  const pendingList = document.getElementById('pending-list');
  const noPending = document.getElementById('no-pending');

  // If current user is admin, show panel; else show login
  var state = getAppState();
  if (state.role === 'admin') {
    loginDiv.style.display = 'none';
    panelDiv.style.display = 'block';
    renderPending();
  } else {
    document.getElementById('admin-form').addEventListener('submit', function (e) {
      e.preventDefault();
      var email = (document.getElementById('admin-email').value || '').trim();
      if (!email) return;
      // Mock: create admin user if needed and sign in
      var users = getUsers();
      if (!users[email]) {
        users[email] = {
          email: email,
          registered: true,
          otpVerified: true,
          profileDone: true,
          idUploaded: false,
          verified: false,
          role: 'admin'
        };
        setUsers(users);
      } else {
        users[email].role = 'admin';
        setUsers(users);
      }
      setCurrentEmail(email);
      loginDiv.style.display = 'none';
      panelDiv.style.display = 'block';
      renderPending();
    });
  }

  function renderPending() {
    var users = getUsers();
    var pending = [];
    for (var email in users) {
      var u = users[email];
      if (u.role === 'admin') continue;
      if (u.idUploaded && !u.verified) pending.push({ email: email, user: u });
    }
    pendingList.innerHTML = '';
    if (pending.length === 0) {
      noPending.style.display = 'block';
      return;
    }
    noPending.style.display = 'none';
    pending.forEach(function (p) {
      var u = p.user;
      var row = document.createElement('div');
      row.className = 'admin-row';
      row.style.cssText = 'border: 1px solid var(--border); border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem;';
      row.innerHTML =
        '<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;">' +
        '<div><strong>' + (u.fullName || u.email) + '</strong><br><span style="font-size: 0.85rem; color: var(--text-muted);">' + u.email + '</span></div>' +
        '<div><button type="button" class="btn btn-primary approve-btn" data-email="' + p.email + '" style="width: auto; padding: 0.4rem 0.75rem; margin-right: 0.5rem;">Approve</button>' +
        '<button type="button" class="btn btn-secondary reject-btn" data-email="' + p.email + '" style="width: auto; padding: 0.4rem 0.75rem;">Reject</button></div>' +
        '</div>';
      pendingList.appendChild(row);
    });
    pendingList.querySelectorAll('.approve-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setUserByEmail(btn.getAttribute('data-email'), { verified: true, verificationStatus: 'verified' });
        renderPending();
      });
    });
    pendingList.querySelectorAll('.reject-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setUserByEmail(btn.getAttribute('data-email'), { verificationStatus: 'rejected' });
        renderPending();
      });
    });
  }
})();
