(function () {
  // ─────────────────────────────────────────────────────────────────────────────────────
  // PRODUCTION-READY VISITOR MANAGEMENT SYSTEM PROTOTYPE
  // ─────────────────────────────────────────────────────────────────────────────────────

  // Simulated data store
  const dataStore = {
    visitors: [
      { id: 'V001', name: 'Ahmed Hassan', email: 'ahmed.hassan@email.com', company: 'Tech Solutions', hostName: 'Ayesha Khan', purpose: 'Meeting', checkInTime: '09:15 AM', checkOutTime: null, status: 'checked-in', badge: '#B-2026-001' },
      { id: 'V002', name: 'Fatima Ali', email: 'fatima.ali@email.com', company: 'Design Studio', hostName: 'Farah Ali', purpose: 'Interview', checkInTime: '09:45 AM', checkOutTime: null, status: 'checked-in', badge: '#B-2026-002' },
      { id: 'V003', name: 'Hassan Khan', email: 'hassan.khan@email.com', company: 'Finance Corp', hostName: 'Saad Malik', purpose: 'Audit', checkInTime: '08:30 AM', checkOutTime: '11:20 AM', status: 'checked-out', badge: '#B-2026-003' },
    ],
    appointments: [
      { id: 'A001', visitorName: 'Zara Munir', hostName: 'Ayesha Khan', date: '2026-05-09', time: '02:00 PM', purpose: 'Project Discussion', status: 'confirmed' },
      { id: 'A002', visitorName: 'Hamza Raza', hostName: 'Farah Ali', date: '2026-05-09', time: '03:30 PM', purpose: 'Interview Round 2', status: 'pending' },
      { id: 'A003', visitorName: 'Noor Fatima', hostName: 'Saad Malik', date: '2026-05-09', time: '04:00 PM', purpose: 'Security Training', status: 'confirmed' },
    ],
    incidents: [
      { id: 'I001', visitorName: 'Unknown Person', date: '2026-05-09', time: '07:45 AM', type: 'Unauthorized entry attempt', severity: 'high', status: 'resolved' },
      { id: 'I002', visitorName: 'John Smith', date: '2026-05-08', time: '05:30 PM', type: 'Visitor without badge', severity: 'medium', status: 'escalated' },
    ],
    blacklist: [
      { id: 'BL001', name: 'Suspicious Individual A', reason: 'Unauthorized access attempt', dateAdded: '2026-04-15', status: 'active' },
      { id: 'BL002', name: 'Former Contractor B', reason: 'Contract violation', dateAdded: '2026-04-10', status: 'active' },
    ],
    reports: [],
  };

  // Role definitions with enhanced sections
  const roleDefinitions = {
    admin: {
      label: 'Admin',
      title: 'Executive control center',
      subtitle: 'Monitor the platform, approve users, and keep operations secure.',
      icon: 'bi-shield-lock-fill',
      accent: 'primary',
      sections: ['overview', 'operations', 'activity', 'queue', 'profile', 'reports'],
      stats: [
        { label: 'Visitors today', value: 148, caption: '+18% since yesterday', icon: 'bi-people-fill', tone: 'primary' },
        { label: 'Pending approvals', value: 12, caption: 'Access requests waiting', icon: 'bi-person-check-fill', tone: 'warning' },
        { label: 'Security alerts', value: 2, caption: 'Needs review now', icon: 'bi-exclamation-triangle-fill', tone: 'success' },
        { label: 'Uptime', value: '99.98%', caption: 'Prototype health is stable', icon: 'bi-graph-up-arrow', tone: 'accent' },
      ],
      quickActions: [
        { title: 'Approve access requests', description: 'Review newly registered accounts and grant the correct role.', icon: 'bi-person-badge-fill', action: 'approve-requests' },
        { title: 'Open analytics', description: 'Present daily trends, top desk activity, and occupancy at a glance.', icon: 'bi-graph-up-arrow', action: 'open-analytics' },
        { title: 'Review audit log', description: 'Inspect the latest security and operations events.', icon: 'bi-journal-text', action: 'audit-log' },
      ],
      activity: [
        { title: 'New visitor registered', meta: 'System · 4 min ago', badge: 'success' },
        { title: 'Security alert triggered', meta: 'North gate · 12 min ago', badge: 'warning' },
        { title: 'Report exported', meta: 'PDF generated · 23 min ago', badge: 'info' },
      ],
      queue: [
        { name: 'Ahmed Hassan', role: 'Visitor Approval', status: 'Pending', time: '2 min' },
        { name: 'Fatima Ali', role: 'Badge Request', status: 'Ready', time: '6 min' },
        { name: 'Hassan Khan', role: 'Incident Report', status: 'Reviewed', time: '11 min' },
      ],
      focus: ['System oversight', 'Security approvals', 'Audit reports', 'User management'],
    },
    receptionist: {
      label: 'Receptionist',
      title: 'Front-desk operations hub',
      subtitle: 'Manage arrivals, check-ins, and visitor communication in one place.',
      icon: 'bi-building-check',
      accent: 'accent',
      sections: ['overview', 'operations', 'activity', 'queue', 'profile'],
      stats: [
        { label: 'Check-ins today', value: 64, caption: 'Front desk handled', icon: 'bi-door-open-fill', tone: 'accent' },
        { label: 'Visitors waiting', value: 8, caption: 'Lobby queue right now', icon: 'bi-hourglass-split', tone: 'warning' },
        { label: 'Badges printed', value: 59, caption: 'Smooth reception flow', icon: 'bi-printer-fill', tone: 'success' },
        { label: 'Avg. check-in', value: '42 sec', caption: 'Fast prototype flow', icon: 'bi-lightning-charge-fill', tone: 'primary' },
      ],
      quickActions: [
        { title: 'Register a visitor', description: 'Create a polished front-desk intake in seconds.', icon: 'bi-person-plus-fill', action: 'register-visitor' },
        { title: 'Search a guest', description: 'Find appointments, badges, and host details quickly.', icon: 'bi-search', action: 'search-guest' },
        { title: 'Print badge', description: 'Issue a clean, presentation-ready visitor badge.', icon: 'bi-printer-fill', action: 'print-badge' },
      ],
      activity: [
        { title: 'Walk-in checked in', meta: 'Ahmed Hassan · 2 min ago', badge: 'success' },
        { title: 'Appointment confirmed', meta: 'Zara Munir · 11 min ago', badge: 'info' },
        { title: 'Badge printed', meta: 'Fatima Ali · 19 min ago', badge: 'success' },
      ],
      queue: [
        { name: 'Zara Munir', role: 'Appointment arrival', status: 'Ready', time: 'Now' },
        { name: 'Hamza Raza', role: 'Walk-in visitor', status: 'Waiting', time: '3 min' },
        { name: 'Noor Fatima', role: 'Badge reprint', status: 'Processing', time: '6 min' },
      ],
      focus: ['Fast registration', 'Queue management', 'Badge printing', 'Host notifications'],
    },
    security: {
      label: 'Security',
      title: 'Perimeter watch console',
      subtitle: 'Track exception handling, incident escalations, and high-risk entries.',
      icon: 'bi-shield-exclamation',
      accent: 'warning',
      sections: ['overview', 'operations', 'activity', 'queue', 'profile'],
      stats: [
        { label: 'Active alerts', value: 3, caption: 'Need eyes on them', icon: 'bi-bell-fill', tone: 'danger' },
        { label: 'Screened entries', value: 92, caption: 'All clear at the gate', icon: 'bi-check2-circle', tone: 'success' },
        { label: 'Flagged visitors', value: 4, caption: 'Held for review', icon: 'bi-ban-fill', tone: 'warning' },
        { label: 'Response time', value: '1.8 min', caption: 'Escalations resolved quickly', icon: 'bi-stopwatch-fill', tone: 'primary' },
      ],
      quickActions: [
        { title: 'Open incident log', description: 'Review suspicious visits and resolved incidents.', icon: 'bi-clipboard2-data-fill', action: 'incident-log' },
        { title: 'Review blacklist', description: 'Inspect blocked entries and their matching rules.', icon: 'bi-x-octagon-fill', action: 'blacklist-review' },
        { title: 'Flag visitor', description: 'Add a visitor to the watch list for security.', icon: 'bi-shield-fill-exclamation', action: 'flag-visitor' },
      ],
      activity: [
        { title: 'Suspicious activity detected', meta: 'East gate · 5 min ago', badge: 'danger' },
        { title: 'Visitor verified', meta: 'Security desk · 14 min ago', badge: 'success' },
        { title: 'Incident resolved', meta: 'Front gate · 21 min ago', badge: 'info' },
      ],
      queue: [
        { name: 'Unknown Person', role: 'Unauthorized entry', status: 'Hold', time: 'Now' },
        { name: 'John Smith', role: 'Badge missing', status: 'Escalated', time: '4 min' },
        { name: 'Visitor Check', role: 'Verification', status: 'Ready', time: '9 min' },
      ],
      focus: ['Risk screening', 'Incident response', 'Blacklist control', 'Gate monitoring'],
    },
    other: {
      label: 'Operations',
      title: 'Team dashboard',
      subtitle: 'A clean role-based workspace for any additional department or custom role.',
      icon: 'bi-grid-1x2-fill',
      accent: 'success',
      sections: ['overview', 'operations', 'activity', 'queue', 'profile'],
      stats: [
        { label: 'Tasks completed', value: 31, caption: 'This shift', icon: 'bi-check2-square', tone: 'success' },
        { label: 'Open requests', value: 5, caption: 'Awaiting response', icon: 'bi-inbox-fill', tone: 'warning' },
        { label: 'Team members', value: 14, caption: 'On the roster', icon: 'bi-people-fill', tone: 'accent' },
        { label: 'SLA score', value: '96%', caption: 'Meeting internal targets', icon: 'bi-award-fill', tone: 'primary' },
      ],
      quickActions: [
        { title: 'Generate report', description: 'Create comprehensive visitor and security reports.', icon: 'bi-file-earmark-pdf-fill', action: 'generate-report' },
        { title: 'View statistics', description: 'Access facility occupancy and traffic patterns.', icon: 'bi-graph-up-arrow', action: 'view-stats' },
        { title: 'Export data', description: 'Download visitor logs and compliance data.', icon: 'bi-download', action: 'export-data' },
      ],
      activity: [
        { title: 'Daily report completed', meta: 'Operations team · 8 min ago', badge: 'success' },
        { title: 'Shift handoff noted', meta: 'Team inbox · 15 min ago', badge: 'info' },
        { title: 'Data backup completed', meta: 'System · 24 min ago', badge: 'success' },
      ],
      queue: [
        { name: 'Daily Report', role: 'PDF Export', status: 'Scheduled', time: '09:00 AM' },
        { name: 'Visitor Statistics', role: 'Analytics', status: 'Ready', time: '01:00 PM' },
        { name: 'Compliance Audit', role: 'Review', status: 'Pending', time: '03:00 PM' },
      ],
      focus: ['Task visibility', 'Team coordination', 'Report generation', 'Data management'],
    },
  };

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
  }

  function closeModal() {
    state.modalOpen = false;
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) {
      backdrop.style.opacity = '0';
      setTimeout(() => backdrop.remove(), 200);
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
              <p class="section-note">${dataStore.reports.length} reports generated this month</p>
            </div>
            <button class="button" data-action="generate-report"><i class="bi bi-file-earmark-plus"></i> New report</button>
          </div>
        </section>

        <section class="dashboard-card">
          <div class="card-title">
            <h3>Available report types</h3>
            <p>Select a report to generate and export</p>
          </div>
          <div class="ops-grid" style="margin-top: 16px;">
            <div class="ops-card">
              <div class="ops-icon" style="background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(37,99,235,0.05));"><i class="bi bi-file-earmark" style="color: var(--primary);"></i></div>
              <strong>Daily Summary</strong>
              <p>Visitor check-in/check-out summary for today</p>
              <button class="button-secondary" onclick="alert('Generating Daily Summary Report...')">Generate</button>
            </div>
            <div class="ops-card">
              <div class="ops-icon" style="background: linear-gradient(135deg, rgba(245,158,11,0.1), rgba(245,158,11,0.05));"><i class="bi bi-exclamation-triangle" style="color: var(--warning);"></i></div>
              <strong>Security Incidents</strong>
              <p>${dataStore.incidents.length} incidents logged and categorized</p>
              <button class="button-secondary" onclick="alert('Generating Security Incidents Report...')">Generate</button>
            </div>
            <div class="ops-card">
              <div class="ops-icon" style="background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(16,185,129,0.05));"><i class="bi bi-check-circle" style="color: var(--success);"></i></div>
              <strong>Compliance Audit</strong>
              <p>Monthly compliance verification and audit report</p>
              <button class="button-secondary" onclick="alert('Generating Compliance Audit Report...')">Generate</button>
            </div>
            <div class="ops-card">
              <div class="ops-icon" style="background: linear-gradient(135deg, rgba(20,184,166,0.1), rgba(20,184,166,0.05));"><i class="bi bi-graph-up" style="color: var(--accent);"></i></div>
              <strong>Traffic Analysis</strong>
              <p>Peak hours and facility occupancy trends</p>
              <button class="button-secondary" onclick="alert('Generating Traffic Analysis Report...')">Generate</button>
            </div>
            <div class="ops-card">
              <div class="ops-icon" style="background: linear-gradient(135deg, rgba(239,68,68,0.1), rgba(239,68,68,0.05));"><i class="bi bi-download" style="color: var(--danger);"></i></div>
              <strong>Bulk Export</strong>
              <p>Export all visitor data and records as CSV</p>
              <button class="button-secondary" onclick="alert('Exporting bulk visitor data...')">Export</button>
            </div>
            <div class="ops-card">
              <div class="ops-icon" style="background: linear-gradient(135deg, rgba(100,116,139,0.1), rgba(100,116,139,0.05));"><i class="bi bi-archive" style="color: var(--muted);"></i></div>
              <strong>Archive Reports</strong>
              <p>View and manage historical reports and archives</p>
              <button class="button-secondary" onclick="alert('Accessing archived reports...')">Browse</button>
            </div>
          </div>
        </section>

        ${dataStore.reports.length > 0 ? `
          <section class="dashboard-card">
            <div class="card-title">
              <h3>Recent reports</h3>
              <p>Previously generated reports</p>
            </div>
            <table class="table" style="margin-top: 16px;">
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
                  <tr>
                    <td><strong>${rpt.type}</strong></td>
                    <td>${rpt.generatedAt}</td>
                    <td>${rpt.dateFrom} to ${rpt.dateTo}</td>
                    <td>${rpt.visitorCount}</td>
                    <td><button class="button-tertiary" onclick="alert('Download: ${rpt.id}')"><i class="bi bi-download"></i> Download</button></td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </section>
        ` : ''}
      </div>
    `;
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
    state.currentUser = null;
    state.pendingEmail = '';
    state.pendingPassword = '';
    state.activeSection = 'overview';
    state.mobileSidebarOpen = false;
    state.activeAuthView = 'login';
    showToast('info', 'Signed out', 'All in-memory state has been cleared.');
    routeTo('login');
  }

  // ─────────────────────────────────────────────────────────────────────────────────────
  // INITIALIZATION
  // ─────────────────────────────────────────────────────────────────────────────────────

  window.addEventListener('hashchange', render);
  window.addEventListener('load', () => {
    if (!location.hash) {
      location.hash = '#/login';
    }
    render();
  });
})();
