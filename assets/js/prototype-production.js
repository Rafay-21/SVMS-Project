(function () {
    const role = user.roleSlug || 'other';
    const commonNote = '<p class="section-note">Use the tools below to manage operations; actions persist to localStorage for demo purposes.</p>';

    if (role === 'admin') {
      return `
        <div class="dashboard-content">
          <section class="dashboard-card">
            <div class="section-heading">
              <div>
                <div class="eyebrow"><i class="bi bi-person-badge-fill"></i> User management</div>
                <h2>Manage user accounts</h2>
                ${commonNote}
              </div>
              <div>
                <button class="button" data-action="add-user"><i class="bi bi-person-plus"></i> Add user</button>
              </div>
            </div>
            <div style="margin-top:12px;">
              <table class="table" id="__users_table">
                <thead>
                  <tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  ${state.users.map(u => `
                    <tr data-user-email="${u.email}">
                      <td>${u.name}</td>
                      <td>${u.email}</td>
                      <td>${u.role}</td>
                      <td style="display:flex;gap:8px;">
                        <button class="button-tertiary" data-action="edit-user" data-email="${u.email}"><i class="bi bi-pencil"></i> Edit</button>
                        <button class="button-secondary" data-action="delete-user" data-email="${u.email}"><i class="bi bi-trash"></i> Delete</button>
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          </section>
        </div>
      `;
    }

    if (role === 'receptionist') {
      return `
        <div class="dashboard-content">
          <section class="dashboard-card">
            <div class="section-heading">
              <div>
                <div class="eyebrow"><i class="bi bi-door-open"></i> Visitor management</div>
                <h2>Check-in / Check-out</h2>
                ${commonNote}
              </div>
              <div style="display:flex;gap:8px;align-items:center;">
                <input id="__visitor_search" class="input" placeholder="Search visitors..." style="min-width:200px;" />
                <button class="button" data-action="register-visitor"><i class="bi bi-person-plus-fill"></i> Register</button>
              </div>
            </div>
            <div style="margin-top:12px;">
              <table class="table" id="__visitors_table">
                <thead>
                  <tr><th>Name</th><th>Company</th><th>Host</th><th>Purpose</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  ${dataStore.visitors.map(v => `
                    <tr data-visitor-id="${v.id}">
                      <td>${v.name}</td>
                      <td>${v.company || ''}</td>
                      <td>${v.hostName || ''}</td>
                      <td>${v.purpose || ''}</td>
                      <td><span class="status-badge ${v.status === 'checked-in' ? 'success' : 'warning'}">${v.status || 'waiting'}</span></td>
                      <td style="display:flex;gap:8px;">
                        <button class="button" data-action="toggle-checkin" data-visitor-id="${v.id}">${v.status==='checked-in' ? '<i class="bi bi-box-arrow-in-left"></i> Check-out' : '<i class="bi bi-box-arrow-in-right"></i> Check-in'}</button>
                        <button class="button-tertiary" data-action="print-badge" data-visitor-id="${v.id}"><i class="bi bi-printer-fill"></i> Badge</button>
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          </section>
        </div>
      `;
    }

    if (role === 'security') {
      return `
        <div class="dashboard-content">
          <section class="dashboard-card">
            <div class="section-heading">
              <div>
                <div class="eyebrow"><i class="bi bi-shield-exclamation"></i> Security operations</div>
                <h2>Incident management</h2>
                ${commonNote}
              </div>
              <div>
                <button class="button" data-action="log-incident"><i class="bi bi-exclamation-triangle"></i> Log incident</button>
              </div>
            </div>
            <div style="margin-top:12px;">
              <table class="table" id="__incidents_table">
                <thead>
                  <tr><th>Title</th><th>Severity</th><th>Reported</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  ${dataStore.incidents.map(i => `
                    <tr data-incident-id="${i.id}">
                      <td>${i.title}</td>
                      <td>${i.severity}</td>
                      <td>${i.meta || ''}</td>
                      <td style="display:flex;gap:8px;">
                        <button class="button-secondary" data-action="incident-resolve" data-incident-id="${i.id}"><i class="bi bi-check2-circle"></i> Resolve</button>
                        <button class="button-tertiary" data-action="incident-delete" data-incident-id="${i.id}"><i class="bi bi-trash"></i> Delete</button>
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          </section>
        </div>
      `;
    }

    // default operations for other roles
    return `
      <div class="dashboard-content">
        <section class="dashboard-card">
          <div class="section-heading">
            <div>
              <div class="eyebrow"><i class="bi bi-gear"></i> Operations</div>
              <h2>Team operations</h2>
              ${commonNote}
            </div>
          </div>
          <div style="margin-top:12px;">
            <p>Use the Reports page to generate exports and the Overview to see stats.</p>
          </div>
        </section>
      </div>
    `;


  const seededUsers = [
    { name: 'Ayesha Khan', email: 'admin@demo.local', password: 'Admin@123', role: 'Admin' },
    { name: 'Farah Ali', email: 'reception@demo.local', password: 'Frontdesk2026!', role: 'Receptionist' },
    { name: 'Saad Malik', email: 'security@demo.local', password: 'Guard2026!', role: 'Security' },
    { name: 'Mariam Noor', email: 'ops@demo.local', password: 'Ops2026!', role: 'Operations' },
  ];

  const state = {
    users: seededUsers.map((user) => ({ ...user, roleSlug: slugifyRole(user.role) })),
    currentUser: null,
    activeAuthView: 'login',
    activeSection: 'overview',
    pendingEmail: '',
    pendingPassword: '',
    mobileSidebarOpen: false,
    modalOpen: false,
    modalContent: null,
  };

  // Persistence helpers: simple autosave to localStorage for demo persistence
  function saveStateToLocalStorage() {
    try {
      const snapshot = {
        currentUser: state.currentUser,
        users: state.users,
        dataStore: {
          visitors: dataStore.visitors,
          appointments: dataStore.appointments,
          incidents: dataStore.incidents,
          blacklist: dataStore.blacklist,
          reports: dataStore.reports,
        }
      };
      localStorage.setItem('svms_state', JSON.stringify(snapshot));
    } catch (e) {
      // ignore storage errors for environments without localStorage
    }
  }

  function loadStateFromLocalStorage() {
    try {
      const raw = localStorage.getItem('svms_state');
      if (!raw) return;
      const snap = JSON.parse(raw);
      if (snap?.users) state.users = snap.users;
      if (snap?.dataStore) {
        dataStore.visitors = snap.dataStore.visitors || dataStore.visitors;
        dataStore.appointments = snap.dataStore.appointments || dataStore.appointments;
        dataStore.incidents = snap.dataStore.incidents || dataStore.incidents;
        dataStore.blacklist = snap.dataStore.blacklist || dataStore.blacklist;
        dataStore.reports = snap.dataStore.reports || dataStore.reports;
      }
    } catch (e) {
      // ignore parse errors
    }
  }

  const app = document.getElementById('app');
  const toastStack = document.createElement('div');
  toastStack.className = 'toast-stack';
  document.body.appendChild(toastStack);

  // ─────────────────────────────────────────────────────────────────────────────────────
  // UTILITY FUNCTIONS
  // ─────────────────────────────────────────────────────────────────────────────────────

  function slugifyRole(role) {
    return String(role || 'other').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') || 'other';
  }

  function capitalize(value) {
    return String(value || '')
      .split('-')
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ');
  }

  function getRoute() {
    const raw = (location.hash || '#/login').replace(/^#/, '').replace(/^\//, '');
    const parts = raw.split('/').filter(Boolean);
    const page = parts[0] || 'login';
    const role = parts[1] || '';
    const section = parts[2] || 'overview';
    return { page, role, section };
  }

  function routeTo(path) {
    if (location.hash !== `#/${path}`) {
      location.hash = `#/${path}`;
    } else {
      render();
    }
  }

  function roleTemplate(roleSlug) {
    return roleDefinitions[roleSlug] || roleDefinitions.other;
  }

  function getUserByEmail(email) {
    return state.users.find((user) => user.email.toLowerCase() === email.toLowerCase());
  }

  function setFieldError(form, name, message) {
    const target = form.querySelector(`[data-error-for="${name}"]`);
    if (target) {
      target.textContent = message || '';
    }
  }

  function clearErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach((el) => {
      el.textContent = '';
    });
  }

  function showToast(type, title, message) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const iconClass = type === 'success' ? 'bi-check2-circle' : type === 'error' ? 'bi-exclamation-triangle-fill' : type === 'warning' ? 'bi-exclamation-circle' : 'bi-info-circle-fill';
    toast.innerHTML = `
      <div class="icon"><i class="bi ${iconClass}"></i></div>
      <div>
        <strong>${title}</strong>
        <p>${message}</p>
      </div>
    `;
    toastStack.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-6px)';
      setTimeout(() => toast.remove(), 220);
    }, 3200);
  }

  // Attach simple table sorting and search highlighter after render
  function makeTablesInteractive() {
    // add click-to-sort on table headers
    document.querySelectorAll('.dashboard-main table.table').forEach((table) => {
      const ths = table.querySelectorAll('thead th');
      ths.forEach((th, idx) => {
        th.style.cursor = 'pointer';
        th.setAttribute('role', 'button');
        th.addEventListener('click', () => sortTable(table, idx));
      });
    });
    // attach ripple handlers to actionable buttons
    attachButtonRipples();
    // attach report handlers if reports section present
    attachReportHandlers();
    // attach operations handlers if operations section present
    attachOperationsHandlers();
  }

  function sortTable(table, colIndex) {
    const tbody = table.tBodies[0];
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const currentDirection = table.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
    rows.sort((a, b) => {
      const aText = (a.children[colIndex]?.textContent || '').trim();
      const bText = (b.children[colIndex]?.textContent || '').trim();
      const aNum = parseFloat(aText.replace(/[^0-9.-]+/g, ''));
      const bNum = parseFloat(bText.replace(/[^0-9.-]+/g, ''));
      if (!isNaN(aNum) && !isNaN(bNum)) return currentDirection === 'asc' ? aNum - bNum : bNum - aNum;
      return currentDirection === 'asc' ? aText.localeCompare(bText) : bText.localeCompare(aText);
    });
    rows.forEach((r) => tbody.appendChild(r));
    table.setAttribute('data-sort-dir', currentDirection);
  }

  function openSearchModal() {
    const content = `
      <div style="display:flex;gap:8px;align-items:center;">
        <input id="__svms_search" class="input" type="search" placeholder="Search tables (press Enter)" style="flex:1;" aria-label="Search tables">
        <button class="button" id="__svms_search_go">Search</button>
      </div>
    `;
    showModal('Quick Search', content, [
      { id: 'cancel', label: 'Close', variant: 'secondary', callback: () => {} },
      { id: 'do', label: 'Find', variant: 'primary', icon: 'bi-search', callback: () => {
        const q = document.getElementById('__svms_search')?.value.trim().toLowerCase();
        if (!q) return showToast('warning', 'Empty query', 'Please enter search text');
        let matches = 0;
        document.querySelectorAll('.dashboard-main table.table tbody tr').forEach((tr) => {
          const text = tr.textContent.toLowerCase();
          const visible = text.indexOf(q) !== -1;
          tr.style.display = visible ? '' : 'none';
          if (visible) matches += 1;
        });
        showToast('info', 'Search results', `${matches} matching rows`);
      } }
    ]);
    setTimeout(() => {
      const input = document.getElementById('__svms_search');
      input?.focus();
      // press Enter to trigger search
      input?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          document.querySelector('[data-action="do"]')?.click();
        }
      });
    }, 120);
  }

  // Add ripple micro-interaction to buttons with .ripple class
  function attachButtonRipples() {
    document.querySelectorAll('button').forEach((btn) => {
      if (btn.classList.contains('__ripple-attached')) return;
      btn.classList.add('__ripple-attached');
      btn.classList.add('ripple');
      btn.addEventListener('click', function (e) {
        const rect = this.getBoundingClientRect();
        const ink = document.createElement('span');
        ink.className = 'ripple-ink';
        const size = Math.max(rect.width, rect.height);
        ink.style.width = ink.style.height = size + 'px';
        ink.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ink.style.top = (e.clientY - rect.top - size / 2) + 'px';
        this.appendChild(ink);
        setTimeout(() => ink.remove(), 700);
      });
    });
  }

  function openHelpOverlay() {
    const content = `
      <div style="max-width:520px;">
        <h3>Keyboard Shortcuts</h3>
        <ul>
          <li><strong>Ctrl+K</strong>: Quick search</li>
          <li><strong>Esc</strong>: Close modal</li>
          <li><strong>Click table headers</strong>: Sort column</li>
        </ul>
        <p>These shortcuts speed up navigation and searching across the dashboard.</p>
      </div>
    `;
    showModal('Help & Shortcuts', content, [
      { id: 'close', label: 'Close', variant: 'primary', callback: () => {} }
    ]);
  }

  function formatDateTime() {
    return new Intl.DateTimeFormat('en', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    }).format(new Date());
  }

  function formatTimeShort() {
    return new Intl.DateTimeFormat('en', { hour: 'numeric', minute: '2-digit' }).format(new Date());
  }

  function formatLongDate() {
    return new Intl.DateTimeFormat('en', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
    }).format(new Date());
  }

  function initials(name) {
    return String(name || '')
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part.charAt(0).toUpperCase())
      .join('');
  }

  function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').toLowerCase());
  }

  function escapeAttr(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function closeMobileSidebarOnOutsideClick(event) {
    if (!state.mobileSidebarOpen) return;
    const sidebar = document.getElementById('dashboard-sidebar');
    const toggle = document.getElementById('mobile-sidebar-toggle');
    if (sidebar && !sidebar.contains(event.target) && toggle && !toggle.contains(event.target)) {
      state.mobileSidebarOpen = false;
      renderDashboard();
    }
  }

  // ─────────────────────────────────────────────────────────────────────────────────────
  // MODAL AND FORM HANDLING
  // ─────────────────────────────────────────────────────────────────────────────────────

  function showModal(title, content, actions = []) {
    state.modalOpen = true;
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
      <div class="modal-content">
        <div class="modal-header">
          <h2>${title}</h2>
          <button class="button-tertiary" type="button" data-modal-close><i class="bi bi-x"></i></button>
        </div>
        <div class="modal-body">
          ${content}
        </div>
        <div class="modal-footer">
          ${actions.map(action => `
            <button class="button${action.variant === 'primary' ? '' : '-secondary'}" type="button" data-action="${action.id}">
              ${action.icon ? `<i class="bi ${action.icon}"></i>` : ''} ${action.label}
            </button>
          `).join('')}
        </div>
      </div>
    `;
    
    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);

    // Accessibility: role/aria and focus management
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-label', title || 'Dialog');

    const previouslyFocused = document.activeElement;
    const focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
    const focusable = Array.from(modal.querySelectorAll(focusableSelector));
    // focus first focusable element or modal
    (focusable[0] || modal).focus();

    function trapFocus(e) {
      if (e.key === 'Escape') {
        closeModal();
        return;
      }
      if (e.key !== 'Tab') return;
      const nodes = Array.from(modal.querySelectorAll(focusableSelector));
      if (!nodes.length) return;
      const first = nodes[0];
      const last = nodes[nodes.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }

    backdrop.querySelector('[data-modal-close]')?.addEventListener('click', closeModal);
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) closeModal();
    });

    actions.forEach(action => {
      backdrop.querySelector(`[data-action="${action.id}"]`)?.addEventListener('click', () => {
        action.callback();
        closeModal();
      });
    });

    document.addEventListener('keydown', trapFocus);

    // store handler for cleanup
    backdrop._trapFocus = trapFocus;
    backdrop._previousFocus = previouslyFocused;
  }

  function closeModal() {
    state.modalOpen = false;
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) {
      backdrop.style.opacity = '0';
      // cleanup focus trap
      if (backdrop._trapFocus) document.removeEventListener('keydown', backdrop._trapFocus);
      setTimeout(() => {
        const prev = backdrop._previousFocus;
        backdrop.remove();
        if (prev && typeof prev.focus === 'function') prev.focus();
      }, 200);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────────────────
  // OPERATION HANDLERS
  // ─────────────────────────────────────────────────────────────────────────────────────

  function handleRegisterVisitor() {
    const content = `
      <form class="modal-form" id="register-visitor-form">
        <div class="form-row">
          <label class="form-label">Visitor Name</label>
          <input class="input" type="text" name="name" placeholder="Full name" required>
          <div class="field-error" data-error-for="name"></div>
        </div>
        <div class="form-row-two">
          <div class="form-row">
            <label class="form-label">Email</label>
            <input class="input" type="email" name="email" placeholder="visitor@email.com" required>
            <div class="field-error" data-error-for="email"></div>
          </div>
          <div class="form-row">
            <label class="form-label">Company</label>
            <input class="input" type="text" name="company" placeholder="Company name" required>
            <div class="field-error" data-error-for="company"></div>
          </div>
        </div>
        <div class="form-row-two">
          <div class="form-row">
            <label class="form-label">Host Name</label>
            <input class="input" type="text" name="hostName" placeholder="Who to visit" required>
            <div class="field-error" data-error-for="hostName"></div>
          </div>
          <div class="form-row">
            <label class="form-label">Purpose</label>
            <select class="select" name="purpose" required>
              <option value="">Select purpose</option>
              <option>Meeting</option>
              <option>Interview</option>
              <option>Delivery</option>
              <option>Consultation</option>
              <option>Training</option>
              <option>Other</option>
            </select>
            <div class="field-error" data-error-for="purpose"></div>
          </div>
        </div>
      </form>
    `;

    showModal('Register New Visitor', content, [
      {
        id: 'cancel',
        label: 'Cancel',
        variant: 'secondary',
        callback: () => {}
      },
      {
        id: 'submit',
        label: 'Register & Check-in',
        variant: 'primary',
        icon: 'bi-person-plus-fill',
        callback: () => {
          const form = document.getElementById('register-visitor-form');
          const name = form.name.value.trim();
          const email = form.email.value.trim();
          const company = form.company.value.trim();
          const hostName = form.hostName.value.trim();
          const purpose = form.purpose.value;

          if (!name || !email || !company || !hostName || !purpose) {
            showToast('error', 'Validation failed', 'Please fill in all fields.');
            return;
          }

          const newVisitor = {
            id: 'V' + String(Date.now()).slice(-6),
            name,
            email,
            company,
            hostName,
            purpose,
            checkInTime: formatTimeShort(),
            checkOutTime: null,
            status: 'checked-in',
            badge: '#B-2026-' + String(dataStore.visitors.length + 1).padStart(3, '0')
          };

          dataStore.visitors.push(newVisitor);
          showToast('success', 'Visitor registered', `${name} has been checked in and assigned badge ${newVisitor.badge}`);
        }
      }
    ]);
  }

  function handleCheckInCheckOut(visitorId, action) {
    const visitor = dataStore.visitors.find(v => v.id === visitorId);
    if (!visitor) return;

    if (action === 'checkout') {
      visitor.checkOutTime = formatTimeShort();
      visitor.status = 'checked-out';
      showToast('success', 'Checked out', `${visitor.name} has been checked out at ${visitor.checkOutTime}`);
    } else if (action === 'checkin') {
      visitor.checkInTime = formatTimeShort();
      visitor.checkOutTime = null;
      visitor.status = 'checked-in';
      showToast('success', 'Checked in', `${visitor.name} has been checked in at ${visitor.checkInTime}`);
    }
  }

  function handlePrintBadge(visitorId) {
    const visitor = dataStore.visitors.find(v => v.id === visitorId);
    if (!visitor) return;

    showToast('success', 'Badge printed', `Badge ${visitor.badge} for ${visitor.name} sent to printer.`);
  }

  function handleGenerateReport() {
    const content = `
      <div class="form-row">
        <label class="form-label">Report Type</label>
        <select class="select" id="report-type" required>
          <option value="">Select report type</option>
          <option>Daily Visitor Summary</option>
          <option>Security Incidents</option>
          <option>Compliance Audit</option>
          <option>Peak Hours Analysis</option>
          <option>Visitor Traffic Report</option>
        </select>
      </div>
      <div class="form-row-two">
        <div class="form-row">
          <label class="form-label">Date From</label>
          <input class="input" type="date" id="date-from" value="2026-05-09">
        </div>
        <div class="form-row">
          <label class="form-label">Date To</label>
          <input class="input" type="date" id="date-to" value="2026-05-09">
        </div>
      </div>
      <div class="status-note" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); padding: 12px; border-radius: 8px; margin-top: 16px;">
        <strong style="color: #10b981;">PDF Export Ready</strong>
        <p style="font-size: 0.9rem; margin-top: 4px;">Reports are generated in real-time and include all visitor data, incidents, and compliance records.</p>
      </div>
    `;

    showModal('Generate Report', content, [
      {
        id: 'cancel',
        label: 'Cancel',
        variant: 'secondary',
        callback: () => {}
      },
      {
        id: 'generate',
        label: 'Generate PDF',
        variant: 'primary',
        icon: 'bi-file-earmark-pdf-fill',
        callback: () => {
          const reportType = document.getElementById('report-type').value;
          if (!reportType) {
            showToast('error', 'Invalid', 'Please select a report type.');
            return;
          }
          const report = {
            id: 'RPT' + String(Date.now()).slice(-6),
            type: reportType,
            dateFrom: document.getElementById('date-from').value,
            dateTo: document.getElementById('date-to').value,
            generatedAt: formatDateTime(),
            visitorCount: dataStore.visitors.length
          };
          dataStore.reports.push(report);
          showToast('success', 'Report generated', `${reportType} exported and ready for download.`);
        }
      }
    ]);
  }

  function handleApproveRequests() {
    const content = `
      <div class="approval-list">
        <div class="approval-item">
          <div style="margin-bottom: 12px;">
            <strong>Ahmed Hassan</strong>
            <p style="font-size: 0.9rem; color: #64748b;">Email: ahmed.hassan@email.com</p>
            <p style="font-size: 0.9rem; color: #64748b;">Request: Receptionist access for one day</p>
          </div>
          <button class="button-secondary" type="button" onclick="alert('Approved: Ahmed Hassan access granted')"><i class="bi bi-check2-circle"></i> Approve</button>
        </div>
        <div class="approval-item" style="border-top: 1px solid var(--line); padding-top: 12px;">
          <div style="margin-bottom: 12px;">
            <strong>Fatima Ali</strong>
            <p style="font-size: 0.9rem; color: #64748b;">Email: fatima.ali@email.com</p>
            <p style="font-size: 0.9rem; color: #64748b;">Request: Admin role temporary access</p>
          </div>
          <button class="button-secondary" type="button" onclick="alert('Approved: Fatima Ali admin access granted')"><i class="bi bi-check2-circle"></i> Approve</button>
        </div>
      </div>
      <div class="status-note" style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); padding: 12px; border-radius: 8px; margin-top: 16px;">
        <strong style="color: #f59e0b;">2 Pending Approvals</strong>
        <p style="font-size: 0.9rem; margin-top: 4px;">Review and approve access requests as they come in.</p>
      </div>
    `;

    showModal('Approve Access Requests', content, [
      {
        id: 'close',
        label: 'Done',
        variant: 'primary',
        callback: () => {}
      }
    ]);
  }

  function handleIncidentLog() {
    const incidentRows = dataStore.incidents.map(incident => `
      <tr>
        <td>${incident.visitorName}</td>
        <td>${incident.type}</td>
        <td><span class="status-badge ${incident.severity === 'high' ? 'danger' : incident.severity === 'medium' ? 'warning' : 'info'}">${capitalize(incident.severity)}</span></td>
        <td>${incident.status}</td>
        <td>${incident.date} ${incident.time}</td>
      </tr>
    `).join('');

    const content = `
      <table class="table" style="font-size: 0.9rem;">
        <thead>
          <tr>
            <th>Visitor</th>
            <th>Incident Type</th>
            <th>Severity</th>
            <th>Status</th>
            <th>Date & Time</th>
          </tr>
        </thead>
        <tbody>
          ${incidentRows}
        </tbody>
      </table>
    `;

    showModal('Incident Log', content, [
      {
        id: 'close',
        label: 'Close',
        variant: 'secondary',
        callback: () => {}
      }
    ]);
  }

  // ─────────────────────────────────────────────────────────────────────────────────────
  // RENDER FUNCTIONS
  // ─────────────────────────────────────────────────────────────────────────────────────

  function render() {
    const route = getRoute();
    if (!route.page || route.page === 'login' || route.page === 'register') {
      state.activeAuthView = route.page === 'register' ? 'register' : 'login';
      renderAuth();
      return;
    }

    if (route.page === 'dashboard') {
      if (!state.currentUser) {
        state.activeAuthView = 'login';
        state.pendingEmail = '';
        renderAuth('Please sign in to continue.');
        return;
      }

      if (route.role && route.role !== state.currentUser.roleSlug) {
        routeTo(`dashboard/${state.currentUser.roleSlug}/${route.section || 'overview'}`);
        return;
      }

      state.activeSection = route.section || 'overview';
      renderDashboard();
      // wire up interactive behaviors for tables and controls after DOM updated
      makeTablesInteractive();
      return;
    }

    routeTo(state.currentUser ? `dashboard/${state.currentUser.roleSlug}/overview` : 'login');
  }

  function renderAuth(message) {
    const errorBanner = message ? `<div class="status-pill" style="background:#fee2e2;color:#991b1b;border-radius:18px;padding:12px 14px;"><i class="bi bi-exclamation-triangle-fill"></i>${message}</div>` : '';
    const view = state.activeAuthView;
    app.innerHTML = `
      <div class="auth-layout">
        <section class="auth-hero">
          <div class="auth-brand">
            <div class="brand-badge"><i class="bi bi-building-check"></i></div>
            <div class="eyebrow" style="background:rgba(255,255,255,.12);color:#fff;"><i class="bi bi-lightning-fill"></i> Enterprise-grade platform</div>
            <h1>Smart Visitor Management System</h1>
            <p class="hero-tagline">Streamline visitor intake, secure facility access, and maintain operational excellence with intelligent role-based workflows.</p>
            
            <div class="value-props">
              <div class="value-prop">
                <div class="value-icon"><i class="bi bi-people-fill"></i></div>
                <div class="value-text">
                  <strong>Streamlined Check-in</strong>
                  <span>Fast, secure visitor intake with QR badge generation and real-time notifications to hosts.</span>
                </div>
              </div>
              <div class="value-prop">
                <div class="value-icon"><i class="bi bi-shield-check"></i></div>
                <div class="value-text">
                  <strong>Security & Compliance</strong>
                  <span>Multi-layer access control, blacklist screening, and audit logging for regulatory requirements.</span>
                </div>
              </div>
              <div class="value-prop">
                <div class="value-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="value-text">
                  <strong>Real-time Analytics</strong>
                  <span>Monitor visitor trends, peak hours, and facility occupancy with actionable insights.</span>
                </div>
              </div>
              <div class="value-prop">
                <div class="value-icon"><i class="bi bi-diagram-3"></i></div>
                <div class="value-text">
                  <strong>Role-based workflows</strong>
                  <span>Tailored interfaces for Admin, Receptionist, Security, and Operations teams.</span>
                </div>
              </div>
            </div>

            <div class="hero-divider"></div>

            <div class="core-features">
              <h3>Core Capabilities</h3>
              <div class="feature-grid">
                <div class="feature-item">
                  <i class="bi bi-door-open-fill"></i>
                  <strong>Visitor Registration</strong>
                  <p>Collect essential data, verify appointments, and generate security badges instantly.</p>
                </div>
                <div class="feature-item">
                  <i class="bi bi-clock-history"></i>
                  <strong>Check-in / Check-out</strong>
                  <p>Automatic time tracking, host notifications, and stay duration monitoring.</p>
                </div>
                <div class="feature-item">
                  <i class="bi bi-camera-fill"></i>
                  <strong>Photo Capture</strong>
                  <p>Integrated webcam support for visitor photo records and badge printing.</p>
                </div>
                <div class="feature-item">
                  <i class="bi bi-ban-fill"></i>
                  <strong>Blacklist Management</strong>
                  <p>Prevent unauthorized access with rule-based visitor screening and alerts.</p>
                </div>
                <div class="feature-item">
                  <i class="bi bi-calendar-event"></i>
                  <strong>Appointment Sync</strong>
                  <p>Verify scheduled meetings and automate pre-clearance for expected guests.</p>
                </div>
                <div class="feature-item">
                  <i class="bi bi-file-earmark-pdf"></i>
                  <strong>Report Generation</strong>
                  <p>Export compliance reports, visitor logs, and security summaries as PDF.</p>
                </div>
              </div>
            </div>

            <div class="proof-points">
              <div class="proof-badge">
                <div class="proof-stat">98%</div>
                <div class="proof-label">Check-in success</div>
              </div>
              <div class="proof-badge">
                <div class="proof-stat">24/7</div>
                <div class="proof-label">Monitoring</div>
              </div>
              <div class="proof-badge">
                <div class="proof-stat">&lt;60s</div>
                <div class="proof-label">Badge print time</div>
              </div>
              <div class="proof-badge">
                <div class="proof-stat">4</div>
                <div class="proof-label">Specialized roles</div>
              </div>
            </div>
          </div>
        </section>
        <section class="auth-card-shell">
          <div class="auth-card ${message ? 'shake' : ''}">
            <div class="auth-card-inner">
              <div class="auth-header">
                <div>
                  <div class="eyebrow"><i class="bi bi-shield-check"></i> In-memory access</div>
                  <h2>${view === 'register' ? 'Create a new demo user' : 'Sign in to continue'}</h2>
                  <p>${view === 'register' ? 'Register with a name, email, password, and role. Your account exists only until the page refreshes.' : 'Log in with any account created during this session or use one of the seeded demo users.'}</p>
                </div>
                <div class="segment-tabs" role="tablist" aria-label="Authentication views">
                  <button class="${view === 'login' ? 'active' : ''}" data-auth-switch="login" type="button">Login</button>
                  <button class="${view === 'register' ? 'active' : ''}" data-auth-switch="register" type="button">Register</button>
                </div>
              </div>

              ${errorBanner}

              <div class="login-showcase">
                <div class="pill"><i class="bi bi-arrow-repeat"></i> Refresh = reset prototype</div>
              </div>

              ${renderDemoCredentials()}

              ${view === 'register' ? renderRegisterForm() : renderLoginForm()}
            </div>
          </div>
        </section>
      </div>
    `;
    bindAuthEvents();
  }

  function renderLoginForm() {
    const currentEmail = state.pendingEmail || 'admin@demo.local';
    const currentPassword = state.pendingPassword || '';
    return `
      <form class="form-grid" id="login-form" novalidate>
        <div class="form-row">
          <label class="form-label" for="login-email">Email address</label>
          <input class="input" id="login-email" name="email" type="email" value="${escapeAttr(currentEmail)}" placeholder="admin@demo.local" autocomplete="email" required>
          <div class="field-error" data-error-for="email"></div>
        </div>
        <div class="form-row">
          <label class="form-label" for="login-password">Password</label>
          <div class="password-wrap">
            <input class="input with-toggle" id="login-password" name="password" type="password" value="${escapeAttr(currentPassword)}" placeholder="Enter your password" autocomplete="current-password" required>
            <button class="password-toggle" type="button" data-password-toggle="login-password" aria-label="Show or hide password"><i class="bi bi-eye"></i></button>
          </div>
          <div class="field-error" data-error-for="password"></div>
        </div>
        <div class="action-row">
          <label class="inline-note"><input type="checkbox" checked disabled> No persistence after refresh</label>
          <button class="button" type="submit"><i class="bi bi-box-arrow-in-right"></i> Sign in</button>
        </div>
      </form>
    `;
  }

  function renderDemoCredentials() {
    return `
      <section class="demo-credential-panel">
        <div class="section-heading" style="margin-bottom:14px;">
          <div>
            <div class="eyebrow"><i class="bi bi-key-fill"></i> Dummy credentials</div>
            <h2 style="font-size:1.1rem;margin-top:10px;">Click a role to autofill login</h2>
            <p class="section-note">Each account is stored only in memory for the current browser session.</p>
          </div>
        </div>
        <div class="demo-account-grid">
          ${seededUsers.map((user) => `
            <button class="demo-account-button" type="button" data-demo-login data-email="${escapeAttr(user.email)}" data-password="${escapeAttr(user.password)}" data-role="${escapeAttr(user.roleSlug)}">
              <div class="demo-account-main">
                <strong>${user.role}</strong>
                <span>${user.email}</span>
              </div>
              <i class="bi bi-arrow-right-circle-fill"></i>
            </button>
          `).join('')}
        </div>
      </section>
    `;
  }

  function renderRegisterForm() {
    return `
      <form class="form-grid" id="register-form" novalidate>
        <div class="form-row">
          <label class="form-label" for="register-name">Full name</label>
          <input class="input" id="register-name" name="name" type="text" placeholder="Enter full name" autocomplete="name" required>
          <div class="field-error" data-error-for="name"></div>
        </div>
        <div class="form-row-two">
          <div class="form-row">
            <label class="form-label" for="register-email">Email address</label>
            <input class="input" id="register-email" name="email" type="email" placeholder="name@example.com" autocomplete="email" required>
            <div class="field-error" data-error-for="email"></div>
          </div>
          <div class="form-row">
            <label class="form-label" for="register-role">Role</label>
            <select class="select" id="register-role" name="role" required>
              <option value="">Select role</option>
              <option>Admin</option>
              <option>Receptionist</option>
              <option>Security</option>
              <option>Operations</option>
            </select>
            <div class="field-error" data-error-for="role"></div>
          </div>
        </div>
        <div class="form-row">
          <label class="form-label" for="register-password">Password</label>
          <div class="password-wrap">
            <input class="input with-toggle" id="register-password" name="password" type="password" placeholder="Create a strong password" autocomplete="new-password" required>
            <button class="password-toggle" type="button" data-password-toggle="register-password" aria-label="Show or hide password"><i class="bi bi-eye"></i></button>
          </div>
          <div class="field-help">Use at least 8 characters for a polished prototype demo.</div>
          <div class="field-error" data-error-for="password"></div>
        </div>
        <div class="action-row">
          <button class="button-secondary" type="button" data-auth-switch="login">Back to login</button>
          <button class="button" type="submit"><i class="bi bi-person-plus-fill"></i> Register user</button>
        </div>
      </form>
    `;
  }

  function bindAuthEvents() {
    document.querySelectorAll('[data-auth-switch]').forEach((btn) => {
      btn.addEventListener('click', () => {
        state.activeAuthView = btn.getAttribute('data-auth-switch') === 'register' ? 'register' : 'login';
        routeTo(state.activeAuthView);
      });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const target = document.getElementById(btn.getAttribute('data-password-toggle'));
        const icon = btn.querySelector('i');
        const isHidden = target.type === 'password';
        target.type = isHidden ? 'text' : 'password';
        icon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
      });
    });

    document.querySelectorAll('[data-demo-login]').forEach((btn) => {
      btn.addEventListener('click', () => {
        state.activeAuthView = 'login';
        state.pendingEmail = btn.getAttribute('data-email') || '';
        state.pendingPassword = btn.getAttribute('data-password') || '';
        showToast('info', 'Credentials loaded', `Autofilled the ${btn.getAttribute('data-role')} demo account.`);
        routeTo('login');
      });
    });

    const loginForm = document.getElementById('login-form');
    if (loginForm) {
      loginForm.addEventListener('submit', (event) => {
        event.preventDefault();
        clearErrors(loginForm);

        const email = loginForm.email.value.trim();
        const password = loginForm.password.value;
        let valid = true;

        if (!validateEmail(email)) {
          setFieldError(loginForm, 'email', 'Enter a valid email address.');
          valid = false;
        }
        if (!password) {
          setFieldError(loginForm, 'password', 'Enter your password.');
          valid = false;
        }
        if (!valid) return;

        const user = getUserByEmail(email);
        if (!user || user.password !== password) {
          setFieldError(loginForm, 'password', 'Email or password is incorrect.');
          showToast('error', 'Login failed', 'The entered credentials do not match any in-memory account.');
          loginForm.classList.add('shake');
          setTimeout(() => loginForm.classList.remove('shake'), 340);
          return;
        }

        state.currentUser = { ...user, lastLogin: new Date().toISOString() };
        state.activeSection = 'overview';
        state.pendingEmail = '';
        state.pendingPassword = '';
        showToast('success', 'Welcome back', `${user.name} is now signed in as ${user.role}.`);
        routeTo(`dashboard/${user.roleSlug}/overview`);
      });
    }

    const registerForm = document.getElementById('register-form');
    if (registerForm) {
      registerForm.addEventListener('submit', (event) => {
        event.preventDefault();
        clearErrors(registerForm);

        const name = registerForm.name.value.trim();
        const email = registerForm.email.value.trim();
        const password = registerForm.password.value;
        const role = registerForm.role.value;
        let valid = true;

        if (name.length < 3) {
          setFieldError(registerForm, 'name', 'Enter at least 3 characters.');
          valid = false;
        }
        if (!validateEmail(email)) {
          setFieldError(registerForm, 'email', 'Enter a valid email address.');
          valid = false;
        }
        if (password.length < 8) {
          setFieldError(registerForm, 'password', 'Use at least 8 characters.');
          valid = false;
        }
        if (!role) {
          setFieldError(registerForm, 'role', 'Choose a role for the dashboard.');
          valid = false;
        }

        if (getUserByEmail(email)) {
          setFieldError(registerForm, 'email', 'An account with this email already exists in this session.');
          valid = false;
        }

        if (!valid) return;

        const newUser = { name, email, password, role, roleSlug: slugifyRole(role) };
        state.users.push(newUser);
        state.pendingEmail = email;
        state.pendingPassword = password;
        state.activeAuthView = 'login';
        showToast('success', 'User registered', `${name} has been added to the in-memory roster.`);
        routeTo('login');
      });
    }
  }

  function renderDashboard() {
    const user = state.currentUser;
    const template = roleTemplate(user.roleSlug);
    const section = state.activeSection || 'overview';
    const sidebarLinks = buildSidebarLinks(user.roleSlug, section);

    app.innerHTML = `
      <div class="app-layout">
        <aside class="dashboard-sidebar ${state.mobileSidebarOpen ? 'open' : ''}" id="dashboard-sidebar">
          <div class="sidebar-brand">
            <div style="display:grid;place-items:center;width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff;font-weight:600;"><i class="bi ${template.icon}"></i></div>
            <div>
              <strong>SVMS</strong>
              <span>${template.label} panel</span>
            </div>
          </div>

          <div class="sidebar-card">
            <strong>${user.name}</strong>
            <span>${template.title}</span>
            <div class="auth-meta" style="margin-top:12px;">
              <span class="role-pill" style="background:rgba(255,255,255,.12);color:#fff;">${template.label}</span>
              <span class="pill" style="background:rgba(255,255,255,.08);color:#fff;"><i class="bi bi-clock"></i> ${formatTimeShort()}</span>
            </div>
          </div>

          <div class="sidebar-links">${sidebarLinks}</div>

          <div class="sidebar-card">
            <strong>System Status</strong>
            <div style="margin-top:10px;">
              <div style="display:flex;gap:8px;margin-bottom:8px;"><span style="width:8px;height:8px;background:#10b981;border-radius:50%;margin-top:2px;"></span><span style="font-size:0.85rem;">All systems operational</span></div>
              <div style="display:flex;gap:8px;"><span style="width:8px;height:8px;background:#10b981;border-radius:50%;margin-top:2px;"></span><span style="font-size:0.85rem;">In-memory mode: No data persistence</span></div>
            </div>
          </div>

          <div class="sidebar-footer">
            <button class="logout-button" type="button" id="logout-button"><i class="bi bi-box-arrow-right"></i> Logout</button>
            <div class="sidebar-meta">In-memory prototype · Refresh to reset</div>
          </div>
        </aside>

        <main class="dashboard-main">
          <div class="mobile-topbar">
            <div class="topbar-title">
              <span class="eyebrow"><i class="bi ${template.icon}"></i> ${template.label}</span>
            </div>
            <button class="button-secondary" type="button" id="mobile-sidebar-toggle"><i class="bi bi-list"></i></button>
          </div>

          <div class="dashboard-topbar">
            <div class="topbar-title">
              <span class="eyebrow"><i class="bi ${template.icon}"></i> ${template.label}</span>
              <h1 class="dashboard-title">${template.title}</h1>
              <p class="dashboard-subtitle">${template.subtitle}</p>
            </div>
            <div class="topbar-actions">
              <span class="pill"><i class="bi bi-calendar3"></i> ${formatLongDate()}</span>
              <span class="pill"><i class="bi bi-person-circle"></i> ${user.email}</span>
              <button class="button-secondary" type="button" id="dashboard-logout"><i class="bi bi-box-arrow-right"></i> Logout</button>
            </div>
          </div>

          ${buildSectionMarkup(user, template, section)}
        </main>
      </div>
    `;

    bindDashboardEvents(user);
  }

  function buildSidebarLinks(roleSlug, activeSection) {
    const items = [
      ['overview', 'Overview', 'bi-speedometer2'],
      ['operations', 'Operations', 'bi-layers-half'],
      ['activity', 'Activity', 'bi-activity'],
      ['queue', 'Queue', 'bi-list-check'],
      ['reports', 'Reports', 'bi-file-earmark-pdf'],
      ['profile', 'Profile', 'bi-person-badge'],
    ];

    return items.map(([slug, label, icon]) => `
      <button class="sidebar-link ${activeSection === slug ? 'active' : ''}" type="button" data-section-link="${slug}">
        <span><i class="bi ${icon}"></i> ${label}</span>
        <small>${slug === 'overview' ? 'Dashboard' : capitalize(slug)}</small>
      </button>
    `).join('');
  }

  function buildSectionMarkup(user, template, section) {
    // Route to section-specific rendering
    switch (section) {
      case 'operations':
        return renderOperationsSection(user, template);
      case 'activity':
        return renderActivitySection(user, template);
      case 'queue':
        return renderQueueSection(user, template);
      case 'reports':
        return renderReportsSection(user, template);
      case 'profile':
        return renderProfileSection(user, template);
      case 'overview':
      default:
        return renderOverviewSection(user, template);
    }
  }

  function renderOverviewSection(user, template) {
    const stats = template.stats;
    const quickActions = template.quickActions;
    const activity = template.activity;
    const queue = template.queue;
    const focus = template.focus;

    return `
      <div class="dashboard-content">
        <div class="summary-grid">
          ${stats.map((item) => `
            <article class="summary-card ${item.tone}">
              <div class="meta">
                <div>
                  <div class="eyebrow"><i class="bi ${item.icon}"></i> ${item.label}</div>
                  <div class="value">${item.value}</div>
                </div>
              </div>
              <div class="caption">${item.caption}</div>
            </article>
          `).join('')}
        </div>

        <div class="dashboard-columns">
          <div class="dashboard-stack">
            <section class="dashboard-card">
              <div class="section-heading">
                <div>
                  <div class="eyebrow"><i class="bi bi-stars"></i> Quick actions</div>
                  <h2>Session-ready shortcuts</h2>
                  <p class="section-note">${focus.map((item) => item).join(' · ')}</p>
                </div>
              </div>
              <div class="quick-grid">
                ${quickActions.map((action) => `
                  <button class="quick-action" type="button" data-action="${action.action}">
                    <div class="icon"><i class="bi ${action.icon}"></i></div>
                    <strong>${action.title}</strong>
                    <p>${action.description}</p>
                  </button>
                `).join('')}
              </div>
            </section>

            <section class="table-card">
              <div class="card-title">
                <div>
                  <div class="eyebrow"><i class="bi bi-table"></i> Live queue</div>
                  <h3>Current worklist</h3>
                  <p>Presentation-friendly queue of the most important items on this role's desk.</p>
                </div>
              </div>
              <table class="table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Context</th>
                    <th>Status</th>
                    <th>Age</th>
                  </tr>
                </thead>
                <tbody>
                  ${queue.map((item) => `
                    <tr>
                      <td>${item.name}</td>
                      <td>${item.role}</td>
                      <td><span class="status-badge ${queueTone(item.status)}">${item.status}</span></td>
                      <td>${item.time}</td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </section>
          </div>

          <div class="dashboard-stack">
            <section class="activity-card">
              <div class="card-title">
                <div>
                  <div class="eyebrow"><i class="bi bi-activity"></i> Recent activity</div>
                  <h3>What just happened</h3>
                  <p>Live feed of system activity and role-specific operations.</p>
                </div>
              </div>
              <div class="timeline">
                ${activity.map((item) => `
                  <div class="timeline-item">
                    <div class="timeline-dot" style="background:${activityTone(item.badge)}; box-shadow:0 0 0 5px ${activityGlow(item.badge)};"></div>
                    <div class="timeline-content">
                      <strong>${item.title}</strong>
                      <span>${item.meta}</span>
                    </div>
                  </div>
                `).join('')}
              </div>
            </section>

            <section class="info-panel">
              <div class="card-title">
                <div>
                  <div class="eyebrow"><i class="bi bi-person-badge"></i> Signed in user</div>
                  <h3>${user.name}</h3>
                  <p>${user.email}</p>
                </div>
              </div>
              <div class="panel-grid">
                <div class="status-card">
                  <strong>Role</strong>
                  <p>${user.role}</p>
                </div>
                <div class="status-card">
                  <strong>Last login</strong>
                  <p>${formatDateTime()}</p>
                </div>
                <div class="status-card">
                  <strong>Prototype state</strong>
                  <p>In-memory only</p>
                </div>
                <div class="status-card">
                  <strong>Visitors registered</strong>
                  <p>${dataStore.visitors.length} total</p>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>
    `;
  }

  function renderOperationsSection(user, template) {
    const opsByRole = {
      admin: `
        <div class="dashboard-content">
          <section class="dashboard-card">
            <div class="section-heading">
              <div>
                <div class="eyebrow"><i class="bi bi-sliders"></i> System management</div>
                <h2>Administrative operations</h2>
                <p class="section-note">Manage users, approve requests, and monitor system health</p>
              </div>
            </div>
            <div class="ops-grid">
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-person-badge-fill"></i></div>
                <strong>User Management</strong>
                <p>Create, modify, or deactivate user accounts. ${state.users.length} users in system.</p>
                <button class="button-secondary" type="button" onclick="alert('User management interface - ${state.users.length} accounts')">
                  <i class="bi bi-arrow-right-circle"></i> Manage users
                </button>
              </div>
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-shield-check"></i></div>
                <strong>Access Control</strong>
                <p>Review and approve ${dataStore.visitors.length} pending access requests.</p>
                <button class="button-secondary" type="button" onclick="alert('Access control - Approve visitor access and roles')">
                  <i class="bi bi-arrow-right-circle"></i> Review requests
                </button>
              </div>
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-cpu"></i></div>
                <strong>System Health</strong>
                <p>Monitor uptime, performance metrics, and system status.</p>
                <button class="button-secondary" type="button" onclick="alert('System uptime: 99.98% - All systems operational')">
                  <i class="bi bi-arrow-right-circle"></i> View metrics
                </button>
              </div>
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-gear"></i></div>
                <strong>Configuration</strong>
                <p>Configure system settings, security policies, and integrations.</p>
                <button class="button-secondary" type="button" onclick="alert('System configuration - Security and feature settings')">
                  <i class="bi bi-arrow-right-circle"></i> Configure
                </button>
              </div>
            </div>
          </section>
        </div>
      `,
      receptionist: `
        <div class="dashboard-content">
          <section class="dashboard-card">
            <div class="section-heading">
              <div>
                <div class="eyebrow"><i class="bi bi-door-open"></i> Reception operations</div>
                <h2>Front-desk management</h2>
                <p class="section-note">Manage visitor intake, check-ins, and badge operations</p>
              </div>
            </div>
            <div class="ops-grid">
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-person-plus-fill"></i></div>
                <strong>Register Visitor</strong>
                <p>Create new visitor records with all required information.</p>
                <button class="button-secondary" type="button" data-action="register-visitor">
                  <i class="bi bi-arrow-right-circle"></i> Register now
                </button>
              </div>
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-door-open"></i></div>
                <strong>Check-in/Check-out</strong>
                <p>Track ${dataStore.visitors.length} active visitors in system.</p>
                <button class="button-secondary" type="button" onclick="alert('Check-in/out interface - ${dataStore.visitors.filter(v => v.status === 'checked-in').length} checked in')">
                  <i class="bi bi-arrow-right-circle"></i> Manage visitors
                </button>
              </div>
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-printer-fill"></i></div>
                <strong>Badge Printing</strong>
                <p>Generate and print visitor identification badges.</p>
                <button class="button-secondary" type="button" data-action="print-badge">
                  <i class="bi bi-arrow-right-circle"></i> Print badges
                </button>
              </div>
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-search"></i></div>
                <strong>Search & Lookup</strong>
                <p>Find visitor information, appointments, and host details.</p>
                <button class="button-secondary" type="button" data-action="search-guest">
                  <i class="bi bi-arrow-right-circle"></i> Search guests
                </button>
              </div>
            </div>
          </section>
        </div>
      `,
      security: `
        <div class="dashboard-content">
          <section class="dashboard-card">
            <div class="section-heading">
              <div>
                <div class="eyebrow"><i class="bi bi-shield-exclamation"></i> Security operations</div>
                <h2>Access control & monitoring</h2>
                <p class="section-note">Monitor threats, manage blacklist, and respond to incidents</p>
              </div>
            </div>
            <div class="ops-grid">
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-clipboard2-data-fill"></i></div>
                <strong>Incident Log</strong>
                <p>View and manage ${dataStore.incidents.length} recorded security incidents.</p>
                <button class="button-secondary" type="button" data-action="incident-log">
                  <i class="bi bi-arrow-right-circle"></i> View incidents
                </button>
              </div>
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-x-octagon-fill"></i></div>
                <strong>Blacklist Management</strong>
                <p>Review ${dataStore.blacklist.length} blocked and flagged visitors.</p>
                <button class="button-secondary" type="button" data-action="blacklist-review">
                  <i class="bi bi-arrow-right-circle"></i> View blacklist
                </button>
              </div>
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-ban-fill"></i></div>
                <strong>Flag Visitor</strong>
                <p>Add suspicious visitors to watch list for future reference.</p>
                <button class="button-secondary" type="button" data-action="flag-visitor">
                  <i class="bi bi-arrow-right-circle"></i> Flag visitor
                </button>
              </div>
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-bell-fill"></i></div>
                <strong>Alert Management</strong>
                <p>Review active security alerts and configure thresholds.</p>
                <button class="button-secondary" type="button" onclick="alert('3 active security alerts - Review configuration')">
                  <i class="bi bi-arrow-right-circle"></i> Manage alerts
                </button>
              </div>
            </div>
          </section>
        </div>
      `,
      other: `
        <div class="dashboard-content">
          <section class="dashboard-card">
            <div class="section-heading">
              <div>
                <div class="eyebrow"><i class="bi bi-graph-up-arrow"></i> Operations management</div>
                <h2>Team coordination</h2>
                <p class="section-note">Generate reports, manage data, and coordinate team activities</p>
              </div>
            </div>
            <div class="ops-grid">
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-file-earmark-pdf-fill"></i></div>
                <strong>Generate Reports</strong>
                <p>Create compliance and operational reports in multiple formats.</p>
                <button class="button-secondary" type="button" data-action="generate-report">
                  <i class="bi bi-arrow-right-circle"></i> Generate report
                </button>
              </div>
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <strong>View Statistics</strong>
                <p>Monitor visitor trends, peak hours, and facility occupancy.</p>
                <button class="button-secondary" type="button" data-action="view-stats">
                  <i class="bi bi-arrow-right-circle"></i> View stats
                </button>
              </div>
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-download"></i></div>
                <strong>Export Data</strong>
                <p>Export visitor logs and compliance records for external use.</p>
                <button class="button-secondary" type="button" data-action="export-data">
                  <i class="bi bi-arrow-right-circle"></i> Export data
                </button>
              </div>
              <div class="ops-card">
                <div class="ops-icon"><i class="bi bi-people-fill"></i></div>
                <strong>Team Coordination</strong>
                <p>Manage shift schedules and team assignments.</p>
                <button class="button-secondary" type="button" onclick="alert('Team coordination - 14 members on roster')">
                  <i class="bi bi-arrow-right-circle"></i> Manage team
                </button>
              </div>
            </div>
          </section>
        </div>
      `
    };

    return opsByRole[user.roleSlug] || opsByRole.other;
  }

  function attachOperationsHandlers() {
    document.querySelectorAll('[data-action="add-user"]').forEach((button) => {
      button.addEventListener('click', () => {
        const form = `
          <form id="__add_user_form" class="modal-form">
            <div class="form-row"><label class="form-label">Full name</label><input class="input" name="name" required></div>
            <div class="form-row"><label class="form-label">Email</label><input class="input" name="email" type="email" required></div>
            <div class="form-row"><label class="form-label">Role</label><select class="select" name="role"><option>Admin</option><option>Receptionist</option><option>Security</option><option>Operations</option></select></div>
          </form>
        `;
        showModal('Add user', form, [
          { id: 'cancel', label: 'Cancel', variant: 'secondary', callback: () => {} },
          {
            id: 'save',
            label: 'Create',
            variant: 'primary',
            callback: () => {
              const formElement = document.getElementById('__add_user_form');
              const nextUser = {
                name: formElement.name.value.trim(),
                email: formElement.email.value.trim(),
                password: 'Temp@1234',
                role: formElement.role.value,
              };
              if (!nextUser.name || !nextUser.email) {
                showToast('error', 'Invalid user', 'Name and email are required');
                return;
              }
              state.users.push({ ...nextUser, roleSlug: slugifyRole(nextUser.role) });
              saveStateToLocalStorage();
              showToast('success', 'User added', `${nextUser.name} was created`);
              render();
            },
          },
        ]);
      });
    });

    document.querySelectorAll('[data-action="edit-user"]').forEach((button) => {
      button.addEventListener('click', () => {
        const email = button.getAttribute('data-email');
        const user = state.users.find((item) => item.email === email);
        if (!user) return showToast('error', 'Not found', 'User not found');

        const form = `
          <form id="__edit_user_form" class="modal-form">
            <div class="form-row"><label class="form-label">Full name</label><input class="input" name="name" value="${user.name}" required></div>
            <div class="form-row"><label class="form-label">Email</label><input class="input" name="email" type="email" value="${user.email}" required></div>
            <div class="form-row"><label class="form-label">Role</label><select class="select" name="role"><option${user.role === 'Admin' ? ' selected' : ''}>Admin</option><option${user.role === 'Receptionist' ? ' selected' : ''}>Receptionist</option><option${user.role === 'Security' ? ' selected' : ''}>Security</option><option${user.role === 'Operations' ? ' selected' : ''}>Operations</option></select></div>
          </form>
        `;
        showModal('Edit user', form, [
          { id: 'cancel', label: 'Cancel', variant: 'secondary', callback: () => {} },
          {
            id: 'save',
            label: 'Save',
            variant: 'primary',
            callback: () => {
              const formElement = document.getElementById('__edit_user_form');
              user.name = formElement.name.value.trim();
              user.email = formElement.email.value.trim();
              user.role = formElement.role.value;
              user.roleSlug = slugifyRole(user.role);
              saveStateToLocalStorage();
              showToast('success', 'Saved', 'User updated');
              render();
            },
          },
        ]);
      });
    });

    document.querySelectorAll('[data-action="delete-user"]').forEach((button) => {
      button.addEventListener('click', () => {
        const email = button.getAttribute('data-email');
        if (!confirm('Delete this user?')) return;
        const index = state.users.findIndex((item) => item.email === email);
        if (index !== -1) state.users.splice(index, 1);
        saveStateToLocalStorage();
        showToast('success', 'Deleted', 'User removed');
        render();
      });
    });

    const visitorSearch = document.getElementById('__visitor_search');
    if (visitorSearch) {
      visitorSearch.addEventListener('input', (event) => {
        const query = event.target.value.toLowerCase();
        document.querySelectorAll('#__visitors_table tbody tr').forEach((row) => {
          row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
        });
      });
    }

    document.querySelectorAll('[data-action="toggle-checkin"]').forEach((button) => {
      button.addEventListener('click', () => {
        const visitorId = button.getAttribute('data-visitor-id');
        const visitor = dataStore.visitors.find((item) => String(item.id) === String(visitorId));
        if (!visitor) return showToast('error', 'Not found', 'Visitor not found');
        visitor.status = visitor.status === 'checked-in' ? 'checked-out' : 'checked-in';
        saveStateToLocalStorage();
        showToast('success', 'Updated', `${visitor.name} is now ${visitor.status}`);
        render();
      });
    });

    document.querySelectorAll('[data-action="print-badge"]').forEach((button) => {
      button.addEventListener('click', () => {
        const visitorId = button.getAttribute('data-visitor-id');
        const visitor = dataStore.visitors.find((item) => String(item.id) === String(visitorId));
        if (!visitor) return showToast('error', 'Not found', 'Visitor not found');
        showModal('Print badge', `
          <div style="min-width:320px;">
            <h3>Badge for ${visitor.name}</h3>
            <p>${visitor.company || ''}</p>
            <p>${visitor.email || ''}</p>
            <div style="margin-top:12px;">
              <button class="button" type="button" id="__simulate_print">Simulate Print</button>
            </div>
          </div>
        `, [
          { id: 'close', label: 'Close', variant: 'secondary', callback: () => {} },
        ]);
        setTimeout(() => {
          document.getElementById('__simulate_print')?.addEventListener('click', () => {
            showToast('info', 'Print', `Simulated print for ${visitor.name}`);
          });
        }, 100);
      });
    });

    document.querySelectorAll('[data-action="incident-resolve"]').forEach((button) => {
      button.addEventListener('click', () => {
        const incidentId = button.getAttribute('data-incident-id');
        const index = dataStore.incidents.findIndex((item) => String(item.id) === String(incidentId));
        if (index !== -1) dataStore.incidents.splice(index, 1);
        saveStateToLocalStorage();
        showToast('success', 'Resolved', 'Incident removed');
        render();
      });
    });

    document.querySelectorAll('[data-action="incident-delete"]').forEach((button) => {
      button.addEventListener('click', () => {
        const incidentId = button.getAttribute('data-incident-id');
        if (!confirm('Delete incident?')) return;
        const index = dataStore.incidents.findIndex((item) => String(item.id) === String(incidentId));
        if (index !== -1) dataStore.incidents.splice(index, 1);
        saveStateToLocalStorage();
        showToast('success', 'Deleted', 'Incident removed');
        render();
      });
    });

    document.querySelectorAll('[data-action="log-incident"]').forEach((button) => {
      button.addEventListener('click', () => {
        const form = `
          <form id="__incident_form" class="modal-form">
            <div class="form-row"><label class="form-label">Title</label><input class="input" name="title" required></div>
            <div class="form-row"><label class="form-label">Severity</label><select class="select" name="severity"><option>Low</option><option>Medium</option><option>High</option></select></div>
            <div class="form-row"><label class="form-label">Notes</label><textarea class="input" name="notes"></textarea></div>
          </form>
        `;
        showModal('Log incident', form, [
          { id: 'cancel', label: 'Cancel', variant: 'secondary', callback: () => {} },
          {
            id: 'save',
            label: 'Log',
            variant: 'primary',
            callback: () => {
              const formElement = document.getElementById('__incident_form');
              const incident = {
                id: `inc-${Date.now()}`,
                title: formElement.title.value.trim(),
                severity: formElement.severity.value,
                meta: formElement.notes.value.trim(),
              };
              dataStore.incidents.unshift(incident);
              saveStateToLocalStorage();
              showToast('success', 'Logged', 'Incident recorded');
              render();
            },
          },
        ]);
      });
    });
  }

  function renderActivitySection(user, template) {
    const activityList = template.activity;
    
    return `
      <div class="dashboard-content">
        <section class="dashboard-card">
          <div class="section-heading">
            <div>
              <div class="eyebrow"><i class="bi bi-activity"></i> Full activity feed</div>
              <h2>Complete event history</h2>
              <p class="section-note">All operations and events in the system for ${user.role}</p>
            </div>
            <div class="filter-controls" style="display: flex; gap: 10px;">
              <select class="select" style="flex: 1; max-width: 200px;">
                <option>All events</option>
                <option>Last 24 hours</option>
                <option>Last 7 days</option>
                <option>This month</option>
              </select>
              <button class="button-secondary"><i class="bi bi-funnel"></i> Filter</button>
            </div>
          </div>
        </section>

        <section class="dashboard-card">
          <div class="timeline" style="padding: 20px;">
            ${activityList.map((item, idx) => `
              <div class="timeline-item" style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: ${idx < activityList.length - 1 ? '1px solid var(--line)' : 'none'};">
                <div class="timeline-dot" style="background:${activityTone(item.badge)}; box-shadow:0 0 0 8px ${activityGlow(item.badge)}; width: 16px; height: 16px;"></div>
                <div class="timeline-content" style="margin-left: 32px;">
                  <strong style="font-size: 1.05rem;">${item.title}</strong>
                  <p style="margin: 8px 0; color: var(--muted);">${item.meta}</p>
                  <span class="status-badge ${item.badge}">${capitalize(item.badge)}</span>
                </div>
              </div>
            `).join('')}
          </div>
        </section>
      </div>
    `;
  }

  function renderQueueSection(user, template) {
    const queueItems = template.queue;
    
    return `
      <div class="dashboard-content">
        <section class="dashboard-card">
          <div class="section-heading">
            <div>
              <div class="eyebrow"><i class="bi bi-list-check"></i> Complete queue</div>
              <h2>All pending items</h2>
              <p class="section-note">${queueItems.length} items in queue for ${user.role}</p>
            </div>
            <button class="button-secondary"><i class="bi bi-arrow-repeat"></i> Refresh</button>
          </div>
        </section>

        <table class="table" style="margin: 0; font-size: 0.95rem;">
          <thead>
            <tr>
              <th style="width: 25%;">Name/Item</th>
              <th style="width: 25%;">Context/Type</th>
              <th style="width: 20%;">Status</th>
              <th style="width: 15%;">Age</th>
              <th style="width: 15%;">Action</th>
            </tr>
          </thead>
          <tbody>
            ${queueItems.map((item, idx) => `
              <tr>
                <td><strong>${item.name}</strong></td>
                <td>${item.role}</td>
                <td><span class="status-badge ${queueTone(item.status)}">${item.status}</span></td>
                <td>${item.time}</td>
                <td>
                  <button class="button-tertiary" style="font-size: 0.85rem;" onclick="alert('Action on: ${item.name}')">
                    <i class="bi bi-arrow-right"></i> Handle
                  </button>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;
  }

  function renderReportsSection(user, template) {
    return `
      <div class="dashboard-content">
        <section class="dashboard-card">
          <div class="section-heading">
            <div>
              <div class="eyebrow"><i class="bi bi-file-earmark-pdf"></i> Reports center</div>
              <h2>Generate and manage reports</h2>
              <p class="section-note">${dataStore.reports.length} reports generated</p>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
              <button class="button" data-action="open-report-form"><i class="bi bi-file-earmark-plus"></i> New report</button>
              <select id="__report_type_filter" class="select">
                <option value="">All types</option>
                <option>Daily Summary</option>
                <option>Security Incidents</option>
                <option>Compliance Audit</option>
                <option>Traffic Analysis</option>
                <option>Bulk Export</option>
              </select>
            </div>
          </div>
        </section>

        <section class="dashboard-card">
          <div class="card-title">
            <h3>Available report types</h3>
            <p>Select a report to generate and export</p>
          </div>
          <div class="ops-grid" style="margin-top: 16px;">
            ${renderReportTypeCard('Daily Summary', 'Visitor check-in/check-out summary for today')}
            ${renderReportTypeCard('Security Incidents', `${dataStore.incidents.length} incidents logged and categorized`)}
            ${renderReportTypeCard('Compliance Audit', 'Monthly compliance verification and audit report')}
            ${renderReportTypeCard('Traffic Analysis', 'Peak hours and facility occupancy trends')}
            ${renderReportTypeCard('Bulk Export', 'Export all visitor data and records as CSV')}
            ${renderReportTypeCard('Archive Reports', 'View and manage historical reports and archives')}
          </div>
        </section>

        ${dataStore.reports.length > 0 ? `
          <section class="dashboard-card">
            <div class="card-title">
              <h3>Recent reports</h3>
              <p>Previously generated reports</p>
            </div>
            <table class="table" style="margin-top: 16px;" id="__reports_table">
              <thead>
                <tr>
                  <th>Report Type</th>
                  <th>Generated</th>
                  <th>Date Range</th>
                  <th>Records</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                ${dataStore.reports.map(rpt => `
                  <tr data-report-id="${rpt.id}">
                    <td><strong>${rpt.type}</strong></td>
                    <td>${rpt.generatedAt}</td>
                    <td>${rpt.dateFrom} to ${rpt.dateTo}</td>
                    <td>${rpt.visitorCount}</td>
                    <td style="display:flex;gap:8px;">
                      <button class="button-tertiary" data-action="report-download" data-report-id="${rpt.id}"><i class="bi bi-download"></i> Download</button>
                      <button class="button-secondary" data-action="report-preview" data-report-id="${rpt.id}"><i class="bi bi-eye"></i> Preview</button>
                      <button class="button-tertiary" data-action="report-delete" data-report-id="${rpt.id}"><i class="bi bi-trash"></i> Delete</button>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </section>
        ` : ''}
      </div>
    `;
  }

  function renderReportTypeCard(title, caption) {
    return `
      <div class="ops-card">
        <div class="ops-icon"><i class="bi bi-file-earmark"></i></div>
        <strong>${title}</strong>
        <p>${caption}</p>
        <button class="button-secondary" data-action="report-generate" data-report-type="${title}">Generate</button>
      </div>
    `;
  }

  // Report operations
  function generateReport(type, options = {}) {
    showToast('info', 'Generating', `Preparing ${type}...`);
    // simulate generation delay
    setTimeout(() => {
      const id = `rpt-${Date.now()}`;
      const now = new Date();
      const dateFrom = options.dateFrom || formatLongDate();
      const dateTo = options.dateTo || formatLongDate();
      const visitorCount = dataStore.visitors.length;
      const report = {
        id,
        type,
        generatedAt: now.toLocaleString(),
        dateFrom,
        dateTo,
        visitorCount,
      };
      dataStore.reports.unshift(report);
      saveStateToLocalStorage();
      showToast('success', 'Report ready', `${type} is ready for download`);
      render();
    }, 900 + Math.floor(Math.random() * 700));
  }

  function downloadReport(id) {
    const rpt = dataStore.reports.find(r => r.id === id);
    if (!rpt) return showToast('error', 'Not found', 'Report not found');
    // generate simple CSV from visitors snapshot
    const rows = [ ['Name','Email','Company','Host','Purpose','Status'] ];
    dataStore.visitors.forEach(v => rows.push([v.name || '', v.email || '', v.company || '', v.hostName || '', v.purpose || '', v.status || '']));
    const csv = rows.map(r => r.map(cell => `"${String(cell).replace(/"/g,'""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${rpt.type.replace(/\s+/g,'_')}_${rpt.id}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
    showToast('success', 'Download started', `${rpt.type} download initiated`);
  }

  function deleteReport(id) {
    const idx = dataStore.reports.findIndex(r => r.id === id);
    if (idx === -1) return showToast('error', 'Not found', 'Report not found');
    dataStore.reports.splice(idx, 1);
    saveStateToLocalStorage();
    showToast('success', 'Deleted', 'Report removed');
    render();
  }

  function previewReport(id) {
    const rpt = dataStore.reports.find(r => r.id === id);
    if (!rpt) return showToast('error', 'Not found', 'Report not found');
    const content = `
      <div style="min-width:360px;">
        <h3>${rpt.type}</h3>
        <p><strong>Generated:</strong> ${rpt.generatedAt}</p>
        <p><strong>Date range:</strong> ${rpt.dateFrom} → ${rpt.dateTo}</p>
        <p><strong>Records:</strong> ${rpt.visitorCount}</p>
        <hr />
        <p style="color:var(--muted);">This preview shows report metadata. Use Download to export the full CSV.</p>
      </div>
    `;
    showModal('Report preview', content, [
      { id: 'close', label: 'Close', variant: 'secondary', callback: () => {} },
      { id: 'download', label: 'Download', variant: 'primary', icon: 'bi-download', callback: () => downloadReport(id) }
    ]);
  }

  function attachReportHandlers() {
    // generate from top button
    document.querySelectorAll('[data-action="open-report-form"]').forEach(btn => btn.addEventListener('click', () => {
      const form = `
        <form id="__report_form" class="modal-form">
          <div class="form-row">
            <label class="form-label">Report Type</label>
            <select name="type" class="select" required>
              <option>Daily Summary</option>
              <option>Security Incidents</option>
              <option>Compliance Audit</option>
              <option>Traffic Analysis</option>
              <option>Bulk Export</option>
            </select>
          </div>
          <div class="form-row-two">
            <div class="form-row">
              <label class="form-label">From</label>
              <input type="date" name="from" class="input">
            </div>
            <div class="form-row">
              <label class="form-label">To</label>
              <input type="date" name="to" class="input">
            </div>
          </div>
        </form>
      `;
      showModal('New Report', form, [
        { id: 'cancel', label: 'Cancel', variant: 'secondary', callback: () => {} },
        { id: 'generate', label: 'Generate', variant: 'primary', icon: 'bi-file-earmark-plus', callback: () => {
          const f = document.getElementById('__report_form');
          const type = f.type.value;
          const dateFrom = f.from.value || formatLongDate();
          const dateTo = f.to.value || formatLongDate();
          generateReport(type, { dateFrom, dateTo });
        } }
      ]);
    }));

    // report-type quick generate buttons
    document.querySelectorAll('[data-action="report-generate"]').forEach(btn => btn.addEventListener('click', (e) => {
      const type = btn.getAttribute('data-report-type');
      generateReport(type);
    }));

    // report table actions
    document.querySelectorAll('[data-action="report-download"]').forEach(btn => btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-report-id');
      downloadReport(id);
    }));
    document.querySelectorAll('[data-action="report-delete"]').forEach(btn => btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-report-id');
      if (confirm('Delete this report?')) deleteReport(id);
    }));
    document.querySelectorAll('[data-action="report-preview"]').forEach(btn => btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-report-id');
      previewReport(id);
    }));

    // filter by type
    const filter = document.getElementById('__report_type_filter');
    if (filter) filter.addEventListener('change', () => {
      const val = filter.value;
      document.querySelectorAll('#__reports_table tbody tr').forEach(tr => {
        const type = tr.children[0].textContent.trim();
        tr.style.display = (!val || val === type) ? '' : 'none';
      });
    });
  }

  function renderProfileSection(user, template) {
    return `
      <div class="dashboard-content">
        <div class="dashboard-columns">
          <div class="dashboard-stack">
            <section class="dashboard-card">
              <div class="section-heading">
                <div>
                  <div class="eyebrow"><i class="bi bi-person-circle"></i> User profile</div>
                  <h2>${user.name}</h2>
                  <p>${user.email}</p>
                </div>
              </div>
              <div class="panel-grid" style="margin-top: 20px;">
                <div class="status-card">
                  <strong>Full Name</strong>
                  <p>${user.name}</p>
                </div>
                <div class="status-card">
                  <strong>Email Address</strong>
                  <p>${user.email}</p>
                </div>
                <div class="status-card">
                  <strong>Assigned Role</strong>
                  <p>${user.role}</p>
                </div>
                <div class="status-card">
                  <strong>Role Permissions</strong>
                  <p>Dashboard access + Operations</p>
                </div>
                <div class="status-card">
                  <strong>Last Login</strong>
                  <p>${formatDateTime()}</p>
                </div>
                <div class="status-card">
                  <strong>Account Status</strong>
                  <p><span class="status-badge success">Active</span></p>
                </div>
              </div>
            </section>

            <section class="dashboard-card">
              <div class="section-heading">
                <div>
                  <div class="eyebrow"><i class="bi bi-gear"></i> Account settings</div>
                  <h2>Preferences & security</h2>
                  <p class="section-note">Manage your account preferences and security settings</p>
                </div>
              </div>
              <div style="padding: 16px 0; border-bottom: 1px solid var(--line);">
                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                  <input type="checkbox" checked>
                  <div>
                    <strong>Email notifications</strong>
                    <p style="font-size: 0.9rem; color: var(--muted); margin: 4px 0;">Receive alerts and updates via email</p>
                  </div>
                </label>
              </div>
              <div style="padding: 16px 0; border-bottom: 1px solid var(--line);">
                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                  <input type="checkbox" checked>
                  <div>
                    <strong>Two-factor authentication</strong>
                    <p style="font-size: 0.9rem; color: var(--muted); margin: 4px 0;">Enhance account security</p>
                  </div>
                </label>
              </div>
              <div style="padding: 16px 0;">
                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                  <input type="checkbox">
                  <div>
                    <strong>Activity log export</strong>
                    <p style="font-size: 0.9rem; color: var(--muted); margin: 4px 0;">Auto-export activity logs weekly</p>
                  </div>
                </label>
              </div>
              <button class="button" style="margin-top: 16px; width: 100%;"><i class="bi bi-check"></i> Save preferences</button>
            </section>
          </div>

          <div class="dashboard-stack">
            <section class="dashboard-card">
              <div class="section-heading">
                <div>
                  <div class="eyebrow"><i class="bi bi-key-fill"></i> Security center</div>
                  <h2>Password & access</h2>
                  <p class="section-note">Keep your account secure</p>
                </div>
              </div>
              <div style="padding: 16px; background: rgba(37,99,235,0.05); border-radius: 12px; margin-bottom: 16px;">
                <strong style="color: var(--primary);">Current password strength: Strong</strong>
                <p style="font-size: 0.9rem; color: var(--muted); margin-top: 4px;">Your password meets all security requirements</p>
              </div>
              <button class="button-secondary" style="width: 100%; margin-bottom: 8px;"><i class="bi bi-lock"></i> Change password</button>
              <button class="button-secondary" style="width: 100%;"><i class="bi bi-shield-check"></i> Security log</button>
            </section>

            <section class="dashboard-card">
              <div class="section-heading">
                <div>
                  <div class="eyebrow"><i class="bi bi-info-circle"></i> System information</div>
                  <h2>About this session</h2>
                </div>
              </div>
              <div class="panel-grid">
                <div class="status-card">
                  <strong>Session Type</strong>
                  <p>In-memory</p>
                </div>
                <div class="status-card">
                  <strong>Data Persistence</strong>
                  <p>None (Demo)</p>
                </div>
                <div class="status-card">
                  <strong>System Version</strong>
                  <p>1.0.0</p>
                </div>
                <div class="status-card">
                  <strong>API Version</strong>
                  <p>v2.0</p>
                </div>
                <div class="status-card">
                  <strong>Uptime</strong>
                  <p>99.98%</p>
                </div>
                <div class="status-card">
                  <strong>Load Status</strong>
                  <p>Optimal</p>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>
    `;
  }

  function bindDashboardEvents(user) {
    document.getElementById('logout-button')?.addEventListener('click', logout);
    document.getElementById('dashboard-logout')?.addEventListener('click', logout);
    document.getElementById('mobile-sidebar-toggle')?.addEventListener('click', () => {
      state.mobileSidebarOpen = !state.mobileSidebarOpen;
      renderDashboard();
      if (state.mobileSidebarOpen) {
        setTimeout(() => {
          document.addEventListener('click', closeMobileSidebarOnOutsideClick, { once: true });
        }, 0);
      }
    });

    document.querySelectorAll('[data-section-link]').forEach((button) => {
      button.addEventListener('click', () => {
        state.mobileSidebarOpen = false;
        state.activeSection = button.getAttribute('data-section-link');
        routeTo(`dashboard/${user.roleSlug}/${state.activeSection}`);
      });
    });

    document.querySelectorAll('[data-action]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const action = btn.getAttribute('data-action');
        handleQuickAction(action);
      });
    });
  }

  function handleQuickAction(action) {
    switch (action) {
      case 'register-visitor':
        handleRegisterVisitor();
        break;
      case 'search-guest':
        showToast('info', 'Search', 'Guest search functionality - check visitors database');
        break;
      case 'print-badge':
        showToast('success', 'Print ready', 'Badge printing queue updated');
        break;
      case 'generate-report':
        handleGenerateReport();
        break;
      case 'approve-requests':
        handleApproveRequests();
        break;
      case 'open-analytics':
        showToast('info', 'Analytics', 'Real-time analytics dashboard loaded');
        break;
      case 'audit-log':
        showToast('info', 'Audit', 'Comprehensive audit log available');
        break;
      case 'incident-log':
        handleIncidentLog();
        break;
      case 'blacklist-review':
        showToast('info', 'Blacklist', `${dataStore.blacklist.length} entries in watch list`);
        break;
      case 'flag-visitor':
        showToast('warning', 'Flag added', 'Visitor has been flagged for security review');
        break;
      case 'view-stats':
        showToast('info', 'Statistics', 'Occupancy and traffic statistics available');
        break;
      case 'export-data':
        showToast('success', 'Export', 'Visitor data export prepared for download');
        break;
      default:
        showToast('info', 'Action', `Action: ${action}`);
    }
  }

  function queueTone(status) {
    const value = String(status || '').toLowerCase();
    if (value.includes('wait') || value.includes('hold') || value.includes('pending')) return 'warning';
    if (value.includes('review') || value.includes('processing') || value.includes('open')) return 'info';
    if (value.includes('ready') || value.includes('scheduled')) return 'success';
    if (value.includes('escalated') || value.includes('esc')) return 'danger';
    return 'info';
  }

  function activityTone(badge) {
    if (badge === 'success') return '#10b981';
    if (badge === 'warning') return '#f59e0b';
    if (badge === 'danger') return '#ef4444';
    return '#2563eb';
  }

  function activityGlow(badge) {
    if (badge === 'success') return 'rgba(16,185,129,.12)';
    if (badge === 'warning') return 'rgba(245,158,11,.12)';
    if (badge === 'danger') return 'rgba(239,68,68,.12)';
    return 'rgba(37,99,235,.12)';
  }

  function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  function logout() {
    // clear current session but keep data persisted for demo
    state.currentUser = null;
    state.pendingEmail = '';
    state.pendingPassword = '';
    state.activeSection = 'overview';
    state.mobileSidebarOpen = false;
    state.activeAuthView = 'login';
    try { localStorage.removeItem('svms_state'); } catch (e) {}
    showToast('info', 'Signed out', 'Session cleared. Demo data saved separately.');
    routeTo('login');
  }

  // ─────────────────────────────────────────────────────────────────────────────────────
  // INITIALIZATION
  // ─────────────────────────────────────────────────────────────────────────────────────

  window.addEventListener('hashchange', render);
  window.addEventListener('load', () => {
    state.currentUser = null;
    if (!location.hash) {
      location.hash = '#/login';
    }
    // load persisted demo state if available
    loadStateFromLocalStorage();
    routeTo('login');
    render();

    // autosave demo state periodically
    setInterval(() => saveStateToLocalStorage(), 5000);

    // keyboard shortcuts: Ctrl+K to open quick search, ? to open help
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        openSearchModal();
      }
      if (e.key === '?' || (e.shiftKey && e.key === '/')) {
        e.preventDefault();
        openHelpOverlay();
      }
    });
  });
})();
