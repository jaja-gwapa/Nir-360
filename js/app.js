// Mock app state – use localStorage so flow persists across pages
// Multi-user: users keyed by email so admin can list and approve
const USERS_KEY = 'verify_capstone_users';
const CURRENT_KEY = 'verify_capstone_current';

function getUsers() {
  try {
    const raw = localStorage.getItem(USERS_KEY);
    return raw ? JSON.parse(raw) : {};
  } catch {
    return {};
  }
}

function setUsers(users) {
  localStorage.setItem(USERS_KEY, JSON.stringify(users));
}

function getCurrentEmail() {
  return localStorage.getItem(CURRENT_KEY) || '';
}

function setCurrentEmail(email) {
  if (email) localStorage.setItem(CURRENT_KEY, email);
  else localStorage.removeItem(CURRENT_KEY);
}

function getAppState() {
  const cur = getCurrentEmail();
  const users = getUsers();
  return cur ? (users[cur] || {}) : {};
}

function setAppState(updates) {
  const cur = getCurrentEmail();
  if (!cur) return;
  const users = getUsers();
  users[cur] = Object.assign({}, users[cur] || {}, updates);
  setUsers(users);
}

function getAllUsers() {
  return getUsers();
}

function setUserByEmail(email, updates) {
  const users = getUsers();
  users[email] = Object.assign({}, users[email] || {}, updates);
  setUsers(users);
}

function clearAppState() {
  setCurrentEmail('');
}

// --- Validation (User Registration & Identity Verification policies) ---

function isValidEmail(email) {
  if (!email || typeof email !== 'string') return false;
  const trimmed = email.trim();
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(trimmed) && trimmed.length <= 254;
}

function isValidMobile(mobile) {
  if (!mobile || typeof mobile !== 'string') return false;
  const digits = mobile.replace(/\D/g, '');
  return digits.length >= 10 && digits.length <= 15;
}

function isEmailTaken(email) {
  if (!email) return false;
  const em = email.trim().toLowerCase();
  const users = getUsers();
  for (const key in users) {
    if (key.toLowerCase() === em) return true;
  }
  return false;
}

function isMobileTaken(mobile) {
  if (!mobile) return false;
  const digits = mobile.replace(/\D/g, '');
  const users = getUsers();
  for (const key in users) {
    const u = users[key];
    if (u && u.mobile && (u.mobile.replace(/\D/g, '') === digits)) return true;
  }
  return false;
}

function checkPasswordStrength(password) {
  if (!password || password.length < 8) return { ok: false, message: 'At least 8 characters.' };
  var hasLetter = /[a-zA-Z]/.test(password);
  var hasNumber = /\d/.test(password);
  if (!hasLetter) return { ok: false, message: 'Include at least one letter.' };
  if (!hasNumber) return { ok: false, message: 'Include at least one number.' };
  return { ok: true, message: '' };
}

// Redirect to correct step if user hasn't completed previous steps
function requireStep(allowedSteps) {
  const state = getAppState();
  if (state.registered && !state.otpVerified && !allowedSteps.includes('otp')) return 'verify-otp.html';
  if (state.otpVerified && !state.profileDone && !allowedSteps.includes('profile')) return 'profile.html';
  if (state.profileDone && !state.idUploaded && !allowedSteps.includes('upload')) return 'upload-id.html';
  if (state.idUploaded && allowedSteps.includes('dashboard')) return null;
  return null;
}
