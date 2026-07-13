const API_URL = 'api.php';
let currentUser = null;
let claimTargetId = null;

async function api(action, data = {}) {
  const res = await fetch(`${API_URL}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  const json = await res.json().catch(() => ({ ok: false, message: 'Invalid server response' }));
  if (!res.ok || !json.ok) throw new Error(json.message || 'Request failed');
  return json;
}
async function safeApi(action, data = {}) { try { return await api(action, data); } catch (e) { toast(e.message, 'error'); throw e; } }
async function loadSession() { try { currentUser = (await api('session')).user || null; } catch (e) { currentUser = null; } }

async function go(page) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const el = document.getElementById('page-' + page);
  if (!el) return;
  el.classList.add('active');
  window.scrollTo(0, 0);
  try {
    if (page === 'dashboard') await renderDashboard();
    if (page === 'browse') await renderBrowse();
    if (page === 'my-reports') await renderMyReports();
    if (page === 'admin') await renderAdmin();
    if (page === 'admin-claims') await renderAdminClaims();
    if (page === 'admin-users') await renderAdminUsers();
    if (page === 'admin-logs') await renderAdminLogs();
  } catch (e) { console.error(e); }
  ['dash-avatar', 'rl-avatar', 'rf-avatar', 'br-avatar', 'mr-avatar'].forEach(id => {
    const a = document.getElementById(id);
    if (a && currentUser) a.textContent = currentUser.name.charAt(0).toUpperCase();
  });
}

async function doLogin() {
  const email = v('login-email').trim(), password = v('login-password');
  const isAdmin = document.getElementById('login-admin').checked;
  if (!email || !password) return toast('Please enter email and password', 'error');
  try {
    const r = await safeApi('login', { email, password, isAdmin });
    currentUser = r.user;
    toast(currentUser.isAdmin ? 'Welcome, Admin!' : 'Welcome back, ' + currentUser.name.split(' ')[0] + '!');
    go(currentUser.isAdmin ? 'admin' : 'dashboard');
  } catch (e) {}
}
async function doRegister() {
  const name = v('reg-name').trim(), email = v('reg-email').trim(), uid = v('reg-id').trim(), dept = v('reg-dept'), role = v('reg-role'), password = v('reg-pass');
  if (!name || !email || !uid || !dept || !password) return toast('Please fill all required fields', 'error');
  if (!isValidEmail(email)) return toast('Please enter a valid email address', 'error');
  if (password.length < 6) return toast('Password must be at least 6 characters', 'error');
  try {
    const r = await safeApi('register', { name, email, uid, dept, role, password });
    currentUser = r.user;
    toast('Account created! Welcome, ' + name.split(' ')[0] + '!');
    go('dashboard');
  } catch (e) {}
}
async function logout() { try { await api('logout'); } catch (e) {} currentUser = null; go('landing'); }

async function submitLost() {
  if (!currentUser) return go('login');
  const itemName = v('rl-name').trim(), category = v('rl-cat'), dateLost = v('rl-date'), location = v('rl-location').trim();
  if (!itemName || !category || !dateLost || !location) return toast('Please fill all required fields', 'error');
  try {
    const imageData = await fileToBase64('rl-image');
    const r = await safeApi('submit_lost', { itemName, category, description: v('rl-desc'), keywords: v('rl-keywords'), dateLost, location, imageData });
    const matchCount = (r.matches || []).length;
    toast(matchCount ? `Lost item submitted. ${matchCount} possible match${matchCount > 1 ? 'es' : ''} found.` : 'Lost item report submitted!');
    ['rl-name','rl-cat','rl-desc','rl-keywords','rl-date','rl-location','rl-image'].forEach(id => document.getElementById(id).value = '');
    setTodayDates(); go('dashboard');
  } catch (e) {}
}
async function submitFound() {
  if (!currentUser) return go('login');
  const itemName = v('rf-name').trim(), category = v('rf-cat'), dateFound = v('rf-date'), location = v('rf-location').trim();
  if (!itemName || !category || !dateFound || !location) return toast('Please fill all required fields', 'error');
  try {
    const imageData = await fileToBase64('rf-image');
    await safeApi('submit_found', { itemName, category, description: v('rf-desc'), keywords: v('rf-keywords'), dateFound, location, imageData });
    toast('Found item report submitted!');
    ['rf-name','rf-cat','rf-desc','rf-keywords','rf-date','rf-location','rf-image'].forEach(id => document.getElementById(id).value = '');
    setTodayDates(); go('dashboard');
  } catch (e) {}
}

async function openClaimModal(itemId) {
  if (!currentUser) return go('login');
  try {
    const item = (await safeApi('claim_info', { itemId })).item;
    claimTargetId = item.id;
    document.getElementById('claim-item-info').innerHTML = `${itemImage(item, 'claim-image')}<strong>${esc(item.itemName)}</strong><br>Category: ${esc(item.category)} | Location: ${esc(item.location)}<br>Date Found: ${esc(item.dateFound)}<br>Description: ${esc(item.description) || '-'}`;
    document.getElementById('claim-details').value = '';
    document.getElementById('claim-image').value = '';
    openModal('claim-modal');
  } catch (e) {}
}
async function submitClaim() {
  if (!claimTargetId || !currentUser) return;
  const details = v('claim-details').trim();
  if (!details) return toast('Please provide claim details', 'error');
  try {
    const imageData = await fileToBase64('claim-image');
    await safeApi('submit_claim', { itemId: claimTargetId, details, imageData });
    closeModal('claim-modal'); toast('Claim submitted! Awaiting admin review.'); claimTargetId = null;
    if (document.getElementById('page-browse').classList.contains('active')) await renderBrowse();
  } catch (e) {}
}
async function renderDashboard() {
  if (!currentUser) return;
  const r = await safeApi('dashboard');
  document.getElementById('dash-name').textContent = currentUser.name.split(' ')[0];
  document.getElementById('stat-lost').textContent = r.stats.lost;
  document.getElementById('stat-found').textContent = r.stats.found;
  document.getElementById('stat-claims').textContent = r.stats.claims;
  document.getElementById('stat-total').textContent = r.stats.total;
  renderFoundMessages(r.foundMessages || []);
  if (document.getElementById('recent-logs-inner')) renderLogs('recent-logs-inner', r.logs || []);
}
function renderFoundMessages(messages) {
  const target = document.getElementById('found-message-list');
  if (!target) return;
  target.innerHTML = messages.length ? messages.map(m => `
    <div class="found-message">
      ${itemImage(m, 'found-message-image')}
      <div class="found-message-body">
        <div class="found-message-title">${esc(m.itemName)}</div>
        <div class="found-message-text">${esc(m.message)}</div>
        ${m.lostItemName ? `<div class="found-message-meta">Matched with your lost report: ${esc(m.lostItemName)}</div>` : ''}
        ${m.location ? `<div class="found-message-meta">Found at ${esc(m.location)}</div>` : ''}
        ${m.matchReason ? `<div class="found-message-meta">Match: ${esc(m.matchReason)}</div>` : ''}
        ${m.itemId ? `<button class="btn btn-primary btn-sm found-message-action" onclick="openClaimModal(${m.itemId})"><i class="fas fa-paper-plane"></i> Claim item</button>` : ''}
      </div>
    </div>`).join('') : '';
}
async function renderBrowse() {
  const r = await safeApi('browse', { search: (v('browse-search') || '').toLowerCase(), category: v('browse-cat') || '', status: v('browse-status') || '' });
  const items = r.items || [];
  const icons = { Wallet:'👛', Phone:'📱', Bag:'🎒', Laptop:'💻', Books:'📚', 'USB Drive':'💾', Keys:'🔑', 'ID Card':'🪪', Others:'📦' };
  document.getElementById('browse-grid').innerHTML = items.length ? items.map(i => `
    <div class="item-card" onclick="showDetail(${i.id})">
      ${itemImage(i, 'browse-card-image')}
      <div class="item-card-img">${icons[i.category] || '📦'}</div>
      <div class="item-card-body">
        <div class="item-card-title">${esc(i.itemName)}</div>
        <div class="item-card-meta">
          <span><i class="fas fa-tag" style="color:var(--text3);font-size:11px"></i> ${esc(i.category)}</span>
          <span><i class="fas fa-map-marker-alt" style="color:var(--text3);font-size:11px"></i> ${esc(i.location)}</span>
          <span><i class="fas fa-calendar" style="color:var(--text3);font-size:11px"></i> ${esc(i.dateFound)}</span>
        </div>
        <div class="item-card-footer">
          <span class="badge ${i.status === 'Available' ? 'badge-success' : 'badge-gray'}">${esc(i.status)}</span>
          ${i.status === 'Available' ? `<button class="btn btn-primary btn-sm" onclick="event.stopPropagation();openClaimModal(${i.id})">Claim</button>` : ''}
        </div>
      </div>
    </div>`).join('') : '<div class="empty-state" style="grid-column:1/-1"><div class="icon">🔍</div><h3>No items found</h3><p>Try adjusting your search filters</p></div>';
}
async function showDetail(id) {
  try {
    const item = (await safeApi('claim_info', { itemId: id, detailsOnly: true })).item;
    const icons = { Wallet:'👛', Phone:'📱', Bag:'🎒', Laptop:'💻', Books:'📚', 'USB Drive':'💾', Keys:'🔑', 'ID Card':'🪪', Others:'📦' };
    document.getElementById('detail-content').innerHTML = `
      ${itemImage(item, 'detail-item-image')}
      <div style="text-align:center;font-size:56px;margin-bottom:16px">${icons[item.category] || '📦'}</div>
      <h3 style="font-size:1.2rem;font-weight:600;margin-bottom:4px">${esc(item.itemName)}</h3>
      <div style="margin-bottom:16px"><span class="badge ${item.status === 'Available' ? 'badge-success' : 'badge-gray'}">${esc(item.status)}</span></div>
      <div style="display:grid;gap:10px;font-size:13px;margin-bottom:20px"><div class="claim-info">
        <strong>Category:</strong> ${esc(item.category)}<br>
        <strong>Location Found:</strong> ${esc(item.location)}<br>
        <strong>Date Found:</strong> ${esc(item.dateFound)}<br>
        <strong>Description:</strong> ${esc(item.description) || '-'}<br>
        <strong>Keywords:</strong> ${esc(item.keywords) || '-'}<br>
        <strong>Reported by:</strong> ${esc(item.userName)}
      </div></div>
      ${item.status === 'Available' ? `<button class="btn btn-primary" style="width:100%;justify-content:center" onclick="closeModal('detail-modal');openClaimModal(${item.id})"><i class="fas fa-paper-plane"></i> Submit Claim</button>` : `<button class="btn btn-outline" style="width:100%;justify-content:center" disabled>Already Claimed</button>`}`;
    openModal('detail-modal');
  } catch (e) {}
}
async function renderMyReports() {
  if (!currentUser) return;
  const r = await safeApi('my_reports'), myLost = r.lost || [], myFound = r.found || [], myClaims = r.claims || [];
  renderReportMatches(r.matches || []);
  document.getElementById('my-lost-body').innerHTML = myLost.length ? myLost.map(i => `
    <tr><td><strong>${esc(i.itemName)}</strong></td><td>${esc(i.category)}</td><td>${esc(i.location)}</td><td>${esc(i.dateLost)}</td><td><span class="badge ${i.status === 'Active' ? 'badge-active' : 'badge-success'}">${esc(i.status)}</span></td><td>${i.status === 'Active' ? `<button class="btn btn-sm btn-outline" onclick="deleteLost(${i.id})" style="color:var(--danger);border-color:var(--danger)">Delete</button>` : ''}</td></tr>`).join('') : '<tr><td colspan="6" style="text-align:center;color:var(--text3);padding:32px">No lost items reported yet</td></tr>';
  document.getElementById('my-found-body').innerHTML = myFound.length ? myFound.map(i => `
    <tr><td><strong>${esc(i.itemName)}</strong></td><td>${esc(i.category)}</td><td>${esc(i.location)}</td><td>${esc(i.dateFound)}</td><td><span class="badge ${i.status === 'Available' ? 'badge-success' : 'badge-gray'}">${esc(i.status)}</span></td></tr>`).join('') : '<tr><td colspan="5" style="text-align:center;color:var(--text3);padding:32px">No found items reported yet</td></tr>';
  document.getElementById('my-claims-body').innerHTML = myClaims.length ? myClaims.map(c => `
    <tr><td><strong>${esc(c.itemName)}</strong>${c.lostItemName ? `<br><span style="font-size:11px;color:var(--text3)">For lost report: ${esc(c.lostItemName)}</span>` : ''}${c.itemImagePath ? itemImage({ imagePath: c.itemImagePath, itemName: c.itemName }, 'table-proof-image') : ''}</td><td>${esc((c.timestamp || '').split(' ')[0])}</td><td>${c.claimImagePath ? itemImage({ imagePath: c.claimImagePath, itemName: c.itemName + ' proof' }, 'table-proof-image') : '<span style="color:var(--text3)">No image</span>'}</td><td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;color:var(--text2)">${esc(c.details)}</td><td><span class="badge ${c.status === 'Pending' ? 'badge-warn' : c.status === 'Approved' ? 'badge-success' : 'badge-danger'}">${esc(c.status)}</span></td></tr>`).join('') : '<tr><td colspan="5" style="text-align:center;color:var(--text3);padding:32px">No claims submitted yet</td></tr>';
}
function renderReportMatches(matches) {
  const target = document.getElementById('my-match-list');
  if (!target) return;
  target.innerHTML = matches.length ? `
    <div class="card match-section">
      <div class="match-section-title"><i class="fas fa-bell"></i> Possible Matches Found</div>
      ${matches.map(m => `
        <div class="found-message">
          ${itemImage(m, 'found-message-image')}
          <div class="found-message-body">
            <div class="found-message-title">${esc(m.itemName)}</div>
            <div class="found-message-text">${esc(m.message)}</div>
            <div class="found-message-meta">Matched with your lost report: ${esc(m.lostItemName)}</div>
            ${m.location ? `<div class="found-message-meta">Found at ${esc(m.location)}</div>` : ''}
            ${m.matchReason ? `<div class="found-message-meta">Match: ${esc(m.matchReason)}</div>` : ''}
            <button class="btn btn-primary btn-sm found-message-action" onclick="openClaimModal(${m.itemId})"><i class="fas fa-paper-plane"></i> Claim item</button>
          </div>
        </div>`).join('')}
    </div>` : '';
}
async function deleteLost(id) { try { await safeApi('delete_lost', { id }); toast('Report deleted'); await renderMyReports(); } catch (e) {} }
async function renderAdmin() {
  const r = await safeApi('admin_dashboard');
  document.getElementById('a-stat-users').textContent = r.stats.users;
  document.getElementById('a-stat-lost').textContent = r.stats.lost;
  document.getElementById('a-stat-found').textContent = r.stats.found;
  document.getElementById('a-stat-claims').textContent = r.stats.claims;
  document.getElementById('admin-lost-body').innerHTML = r.lost.length ? r.lost.map(i => `<tr><td>${itemImage(i, 'table-proof-image')}<strong>${esc(i.itemName)}</strong><br><span style="font-size:11px;color:var(--text3)">${esc(i.category)}</span></td><td>${esc(i.userName)}</td><td>${esc(i.dateLost)}</td><td><span class="badge ${i.status === 'Active' ? 'badge-active' : 'badge-success'}">${esc(i.status)}</span></td><td><button class="btn btn-sm btn-outline" onclick="adminDeleteItem('lost',${i.id})" style="color:var(--danger);border-color:rgba(239,68,68,.3)">Remove</button></td></tr>`).join('') : '<tr><td colspan="5" style="color:var(--text3);padding:20px;text-align:center">No items</td></tr>';
  document.getElementById('admin-found-body').innerHTML = r.found.length ? r.found.map(i => `<tr><td>${itemImage(i, 'table-proof-image')}<strong>${esc(i.itemName)}</strong><br><span style="font-size:11px;color:var(--text3)">${esc(i.category)}</span></td><td>${esc(i.userName)}</td><td>${esc(i.dateFound)}</td><td><span class="badge ${i.status === 'Available' ? 'badge-success' : 'badge-gray'}">${esc(i.status)}</span></td><td><button class="btn btn-sm btn-outline" onclick="adminDeleteItem('found',${i.id})" style="color:var(--danger);border-color:rgba(239,68,68,.3)">Remove</button></td></tr>`).join('') : '<tr><td colspan="5" style="color:var(--text3);padding:20px;text-align:center">No items</td></tr>';
}
async function adminDeleteItem(type, id) { try { await safeApi('admin_delete_item', { type, id }); toast('Item removed'); await renderAdmin(); } catch (e) {} }
async function renderAdminClaims() {
  const claims = (await safeApi('admin_claims')).claims || [];
  document.getElementById('admin-claims-body').innerHTML = claims.length ? claims.map(c => `<tr><td style="color:var(--text3);font-size:12px">#${c.id}</td><td><strong>${esc(c.itemName)}</strong>${c.itemImagePath ? itemImage({ imagePath: c.itemImagePath, itemName: c.itemName }, 'admin-claim-image') : ''}</td><td>${esc(c.userName)}</td><td>${c.claimImagePath ? itemImage({ imagePath: c.claimImagePath, itemName: c.itemName + ' proof' }, 'admin-claim-image') : '<span style="color:var(--text3);font-size:12px">No image</span>'}</td><td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;color:var(--text2);font-size:12px">${esc(c.details)}</td><td style="font-size:12px">${esc((c.timestamp || '').split(' ')[0])}</td><td><span class="badge ${c.status === 'Pending' ? 'badge-warn' : c.status === 'Approved' ? 'badge-success' : 'badge-danger'}">${esc(c.status)}</span></td><td>${c.status === 'Pending' ? `<button class="btn btn-sm btn-success" onclick="approveClaim(${c.id})" style="margin-right:6px">Approve</button><button class="btn btn-sm btn-danger" onclick="rejectClaim(${c.id})">Reject</button>` : '-'}</td></tr>`).join('') : '<tr><td colspan="8" style="text-align:center;color:var(--text3);padding:40px">No claims submitted yet</td></tr>';
}
async function approveClaim(id) { try { await safeApi('approve_claim', { id }); toast('Claim approved!'); await renderAdminClaims(); } catch (e) {} }
async function rejectClaim(id) { try { await safeApi('reject_claim', { id }); toast('Claim rejected'); await renderAdminClaims(); } catch (e) {} }
async function renderAdminUsers() {
  const users = (await safeApi('admin_users')).users || [];
  document.getElementById('admin-users-body').innerHTML = users.length ? users.map(u => `<tr><td style="color:var(--text3);font-size:12px">${esc(u.uid)}</td><td><strong>${esc(u.name)}</strong></td><td style="color:var(--text2)">${esc(u.email)}</td><td>${esc(u.dept)}</td><td>${esc(u.role)}</td><td><span class="badge ${u.status === 'active' ? 'badge-success' : 'badge-danger'}">${esc(u.status)}</span></td><td><button class="btn btn-sm btn-outline" onclick="toggleUserStatus(${u.id})">${u.status === 'active' ? 'Deactivate' : 'Activate'}</button></td></tr>`).join('') : '<tr><td colspan="7" style="text-align:center;color:var(--text3);padding:40px">No registered users</td></tr>';
}
async function toggleUserStatus(id) { try { await safeApi('toggle_user_status', { id }); toast('User status updated'); await renderAdminUsers(); } catch (e) {} }
async function renderAdminLogs() { renderLogs('audit-log-inner', (await safeApi('admin_logs')).logs || []); }
function renderLogs(targetId, logs) {
  const target = document.getElementById(targetId);
  if (!target) return;
  const icons = { LOGIN:'🔑', LOGOUT:'👋', REGISTER:'✨', REPORT_LOST:'📋', REPORT_FOUND:'🤲', CLAIM_SUBMITTED:'📝', CLAIM_APPROVED:'✅', CLAIM_REJECTED:'❌', ADMIN_DELETE:'🗑', USER_STATUS:'👤', DELETE_REPORT:'🗑', SYSTEM:'⚙️' };
  target.innerHTML = logs.length ? logs.map(l => `<div class="log-entry"><div class="log-icon" style="background:var(--bg2)">${icons[l.action] || '📌'}</div><div style="flex:1"><div style="font-size:13px;font-weight:500">${esc(l.desc)}</div><div style="font-size:11px;color:var(--text3);margin-top:2px;display:flex;gap:12px"><span><i class="fas fa-tag" style="font-size:10px"></i> ${esc(l.action)}</span><span><i class="fas fa-clock" style="font-size:10px"></i> ${esc(l.timestamp)}</span></div></div></div>`).join('') : '<div class="empty-state"><div class="icon">📜</div><p>No logs yet</p></div>';
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
function fileToBase64(inputId) {
  const input = document.getElementById(inputId);
  const file = input && input.files ? input.files[0] : null;
  if (!file) return Promise.resolve('');
  if (!file.type.startsWith('image/')) {
    toast('Please upload an image file only', 'error');
    return Promise.reject(new Error('Invalid image file'));
  }
  if (file.size > 2 * 1024 * 1024) {
    toast('Image must be smaller than 2 MB', 'error');
    return Promise.reject(new Error('Image too large'));
  }
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => reject(new Error('Could not read image'));
    reader.readAsDataURL(file);
  });
}
function itemImage(item, className = 'item-proof-image') {
  return item && item.imagePath ? `<img class="${className}" src="${esc(item.imagePath)}" alt="${esc(item.itemName || 'Item image')}">` : '';
}
function v(id) { const el = document.getElementById(id); return el ? el.value : ''; }
function esc(s) { if (!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function switchTab(btn, tabId) {
  btn.closest('.tabs').querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  btn.closest('.page-body').querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.getElementById(tabId).classList.add('active');
}
function toast(msg, type = 'success') {
  const t = document.getElementById('toast'), m = document.getElementById('toast-msg');
  t.className = 'toast ' + type; m.textContent = msg;
  t.querySelector('i').className = type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
  clearTimeout(window._toastTimer); window._toastTimer = setTimeout(() => t.classList.add('hidden'), 3000);
}
function applyTheme(theme) {
  const mode = theme === 'light' ? 'light' : 'dark';
  document.body.classList.toggle('light', mode === 'light');
  localStorage.setItem('lf_theme', mode);
  const icon = document.querySelector('#theme-toggle i');
  if (icon) icon.className = mode === 'light' ? 'fas fa-sun' : 'fas fa-moon';
}
function toggleTheme() {
  applyTheme(document.body.classList.contains('light') ? 'dark' : 'light');
}
function togglePassword(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  const icon = btn.querySelector('i');
  if (icon) icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
  btn.title = show ? 'Hide password' : 'Show password';
}
function setTodayDates() {
  const today = new Date().toISOString().split('T')[0];
  ['rl-date','rf-date'].forEach(id => { const el = document.getElementById(id); if (el && !el.value) el.value = today; });
}
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); }));
document.addEventListener('DOMContentLoaded', async () => { applyTheme(localStorage.getItem('lf_theme') || 'dark'); setTodayDates(); await loadSession(); if (currentUser) go(currentUser.isAdmin ? 'admin' : 'dashboard'); });
