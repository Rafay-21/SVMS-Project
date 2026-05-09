(function () {
  // ENHANCED ROLE DEFINITIONS WITH ADVANCED FEATURES AND ANALYTICS
  const roleDefinitions = {
    admin: {
      label: 'Admin',
      title: 'Executive Control Center',
      subtitle: 'Monitor the platform, approve users, manage security policies, and maintain operational excellence.',
      icon: 'bi-shield-lock-fill',
      accent: 'primary',
      stats: [
        { label: 'Visitors today', value: 248, caption: '+28% since yesterday', icon: 'bi-people-fill', tone: 'primary', trend: 'up' },
        { label: 'Pending approvals', value: 7, caption: '2 urgent · 5 normal', icon: 'bi-person-check-fill', tone: 'warning', trend: 'stable' },
        { label: 'Security alerts', value: 1, caption: '0 critical · 1 warning', icon: 'bi-exclamation-triangle-fill', tone: 'success', trend: 'down' },
        { label: 'System uptime', value: '99.98%', caption: '24/7 monitoring active', icon: 'bi-graph-up-arrow', tone: 'accent', trend: 'up' },
      ],
      quickActions: [
        { title: 'Approve access requests', description: 'Review newly registered accounts and grant the correct role.', icon: 'bi-person-badge-fill', action: 'approve-requests' },
        { title: 'View analytics dashboard', description: 'Real-time visitor trends, peak hours, and occupancy analytics.', icon: 'bi-graph-up-arrow', action: 'open-analytics' },
        { title: 'Review audit log', description: 'Inspect the latest security and operations events with timestamps.', icon: 'bi-journal-text', action: 'audit-log' },
        { title: 'Configure security policies', description: 'Manage blacklist rules, access controls, and compliance settings.', icon: 'bi-shield-check', action: 'config-security' },
      ],
      activity: [
        { title: 'New admin created', meta: 'Nadia Hussain · 4 min ago', badge: 'success' },
        { title: 'Elevated reception queue', meta: '12 visitors waiting · 12 min ago', badge: 'info' },
        { title: 'Blacklisted visitor blocked', meta: 'Security desk · 23 min ago', badge: 'danger' },
        { title: 'Daily report generated', meta: 'PDF export ready · 38 min ago', badge: 'warning' },
      ],
      queue: [
        { name: 'Amina Zahid', role: 'Reception request', status: 'Pending', time: '2 min' },
        { name: 'Bilal Ahmed', role: 'Access role change', status: 'Review', time: '6 min' },
        { name: 'Sara Khan', role: 'Visitor exception', status: 'Escalated', time: '11 min' },
      ],
      focus: [
        '1-click approvals',
        'Role management',
        'Audit visibility',
        'Health overview',
      ],
    },
    receptionist: {
      label: 'Receptionist',
      title: 'Front-Desk Operations Hub',
      subtitle: 'Manage arrivals, check-ins, visitor communication, and badge printing with efficient workflows.',
      icon: 'bi-building-check',
      accent: 'accent',
      stats: [
        { label: 'Check-ins today', value: 94, caption: 'Smooth reception flow', icon: 'bi-door-open-fill', tone: 'accent', trend: 'up' },
        { label: 'Visitors in lobby', value: 12, caption: '8 waiting · 4 processing', icon: 'bi-hourglass-split', tone: 'warning', trend: 'stable' },
        { label: 'Badges printed', value: 88, caption: 'Average print time: 38s', icon: 'bi-printer-fill', tone: 'success', trend: 'up' },
        { label: 'Avg. check-in', value: '38 sec', caption: 'Optimized reception flow', icon: 'bi-lightning-charge-fill', tone: 'primary', trend: 'down' },
      ],
      quickActions: [
        { title: 'Register a visitor', description: 'Fast intake form with auto-verification and badge generation.', icon: 'bi-person-plus-fill', action: 'register-visitor' },
        { title: 'Search visitor profile', description: 'Find appointments, badges, history, and host information quickly.', icon: 'bi-search', action: 'search-guest' },
        { title: 'Print or reprint badge', description: 'Issue visitor badges with QR codes for streamlined access.', icon: 'bi-printer-fill', action: 'print-badge' },
        { title: 'Notify host of arrival', description: 'Send real-time notifications to hosts about visitor arrivals.', icon: 'bi-bell-fill', action: 'notify-host' },
      ],
      activity: [
        { title: 'Walk-in visitor checked in', meta: 'Imran Ali · 2 min ago', badge: 'success' },
        { title: 'Appointment auto-confirmed', meta: 'Front desk system · 8 min ago', badge: 'info' },
        { title: 'Badge reprint completed', meta: 'North lobby station · 15 min ago', badge: 'warning' },
        { title: 'Visitor checked out', meta: 'Main reception · 22 min ago', badge: 'success' },
        { title: 'Same-day appointment verified', meta: 'Calendar sync · 31 min ago', badge: 'success' },
      ],
      queue: [
        { name: 'Zara Munir', role: 'Appointment arrival', status: 'Ready', time: 'Now' },
        { name: 'Hamza Raza', role: 'Walk-in visitor', status: 'Waiting', time: '3 min' },
        { name: 'Noor Fatima', role: 'Badge reprint', status: 'Processing', time: '6 min' },
      ],
      focus: [
        'Fast registration',
        'Queue management',
        'Badge operations',
        'Host notifications',
        'Appointment verification',
      ],
    },
    security: {
      label: 'Security',
      title: 'Perimeter Watch Console',
      subtitle: 'Track access control, manage incidents, monitor threats, and maintain facility security protocols.',
      icon: 'bi-shield-exclamation',
      accent: 'warning',
      stats: [
        { label: 'Active alerts', value: 2, caption: 'All monitored · escalation ready', icon: 'bi-bell-fill', tone: 'danger', trend: 'stable' },
        { label: 'Screened entries', value: 156, caption: 'All cleared · no blocks', icon: 'bi-check2-circle', tone: 'success', trend: 'up' },
        { label: 'Flagged visitors', value: 3, caption: '1 high-risk · 2 watch-list', icon: 'bi-ban-fill', tone: 'warning', trend: 'stable' },
        { label: 'Response time', value: '1.2 min', caption: 'Well under SLA', icon: 'bi-stopwatch-fill', tone: 'primary', trend: 'down' },
      ],
      quickActions: [
        { title: 'View incident log', description: 'Review all security events, suspicious visits, and resolved incidents.', icon: 'bi-clipboard2-data-fill', action: 'incident-log' },
        { title: 'Manage blacklist', description: 'Add, review, or remove visitors from the security watch list.', icon: 'bi-x-octagon-fill', action: 'blacklist-review' },
        { title: 'Flag visitor for review', description: 'Mark suspicious visitors for enhanced monitoring and screening.', icon: 'bi-exclamation-circle-fill', action: 'flag-visitor' },
        { title: 'Trigger security alert', description: 'Escalate situations to management with automated notifications.', icon: 'bi-shield-fill-exclamation', action: 'security-alert' },
      ],
      activity: [
        { title: 'Unauthorized badge denied', meta: 'East gate · 3 min ago', badge: 'danger' },
        { title: 'Escort request approved', meta: 'Security desk · 12 min ago', badge: 'info' },
        { title: 'Incident logged & resolved', meta: 'Front gate · 18 min ago', badge: 'success' },
        { title: 'Blacklist match detected', meta: 'Visitor screening · 26 min ago', badge: 'danger' },
        { title: 'Perimeter sweep completed', meta: 'All entrances · 42 min ago', badge: 'success' },
      ],
      queue: [
        { name: 'Asad Qureshi', role: 'Flag review', status: 'Hold', time: 'Now' },
        { name: 'Hina Shah', role: 'Escort request', status: 'Escalated', time: '4 min' },
        { name: 'Umer Siddiq', role: 'Background check', status: 'Pending', time: '9 min' },
      ],
      focus: [
        'Risk assessment',
        'Threat detection',
        'Incident response',
        'Access control',
        'Blacklist management',
      ],
    },
    other: {
      label: 'Operations',
      title: 'Team Coordination Dashboard',
      subtitle: 'Manage operations, coordinate teams, generate reports, and monitor key performance indicators.',
      icon: 'bi-grid-1x2-fill',
      accent: 'success',
      stats: [
        { label: 'Tasks completed', value: 47, caption: 'This shift', icon: 'bi-check2-square', tone: 'success', trend: 'up' },
        { label: 'Open requests', value: 8, caption: '3 urgent · 5 standard', icon: 'bi-inbox-fill', tone: 'warning', trend: 'stable' },
        { label: 'Team members', value: 18, caption: 'All available', icon: 'bi-people-fill', tone: 'accent', trend: 'up' },
        { label: 'SLA compliance', value: '98.5%', caption: 'Exceeding targets', icon: 'bi-award-fill', tone: 'primary', trend: 'up' },
      ],
      quickActions: [
        { title: 'Generate daily report', description: 'Create comprehensive reports on visitors, incidents, and compliance.', icon: 'bi-file-earmark-pdf-fill', action: 'generate-report' },
        { title: 'View team statistics', description: 'Monitor team performance, shift metrics, and operational KPIs.', icon: 'bi-graph-up-arrow', action: 'view-stats' },
        { title: 'Export visitor data', description: 'Bulk export visitor logs and records for analysis and archiving.', icon: 'bi-download', action: 'export-data' },
        { title: 'Schedule team briefing', description: 'Organize standup meetings and share shift updates with team.', icon: 'bi-calendar-event', action: 'schedule-briefing' },
      ],
      activity: [
        { title: 'Shift report submitted', meta: 'Operations lead · 5 min ago', badge: 'success' },
        { title: 'New task assigned', meta: 'Team coordinator · 14 min ago', badge: 'info' },
        { title: 'Performance metrics updated', meta: 'Analytics system · 22 min ago', badge: 'info' },
        { title: 'Team briefing scheduled', meta: 'Today at 5:30 PM · 39 min ago', badge: 'warning' },
        { title: 'Monthly report finalized', meta: 'Finance team · 1 hour ago', badge: 'success' },
      ],
      queue: [
        { name: 'Morning standup', role: 'Team meeting', status: 'Ready', time: '9:00 AM' },
        { name: 'Backlog refinement', role: 'Planning', status: 'Scheduled', time: '1:00 PM' },
        { name: 'Stakeholder sync', role: 'Executive brief', status: 'Pending', time: '4:30 PM' },
      ],
      focus: [
        'Task visibility',
        'Team coordination',
        'Performance tracking',
        'Report generation',
        'Data analytics',
      ],
    },
  };

  const seededUsers = [
    { name: 'Ayesha Khan', email: 'admin@demo.local', password: 'Admin@123', role: 'Admin' },
    { name: 'Farah Ali', email: 'reception@demo.local', password: 'Frontdesk2026!', role: 'Receptionist' },
    { name: 'Saad Malik', email: 'security@demo.local', password: 'Guard2026!', role: 'Security' },
    { name: 'Mariam Noor', email: 'ops@demo.local', password: 'Ops2026!', role: 'Operations' },
  ];

  const state = {
    users: seededUsers.map((user) => ({ ...user, roleSlug: slugifyRole(user.role), lastActive: new Date() })),
    currentUser: null,
    activeAuthView: 'login',
    activeSection: 'overview',
    pendingEmail: '',
    pendingPassword: '',
    mobileSidebarOpen: false,
    notifications: [],
    recentActivity: [],
    analyticsData: {
      visitorsByHour: [12, 18, 25, 32, 28, 35, 42, 38, 44, 48, 52, 58],
      peakHour: '2:00 PM - 3:00 PM',
      avgCheckInTime: 38,
      avgCheckOutTime: 12,
    },
    searchHistory: [],
    filters: {
      dateRange: 'today',
      visitorStatus: 'all',
      sortBy: 'recent',
    },
  };

  const app = document.getElementById('app');
  const toastStack = document.createElement('div');
  toastStack.className = 'toast-stack';
  document.body.appendChild(toastStack);

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
    toast.innerHTML = `
      <div class="icon"><i class="bi ${type === 'success' ? 'bi-check2' : type === 'error' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill'}"></i></div>
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

    // Add to recent notifications
    state.notifications.push({ type, title, message, timestamp: new Date() });
    if (state.notifications.length > 50) state.notifications.shift();
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

  function initials(name) {
    return String(name || '')
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part.charAt(0).toUpperCase())
      .join('');
  }

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
              <option>Other</option>
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
      btn.addEventListener('click', () => {
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
            <img src="assets/img/logo.svg" alt="SVMS logo">
            <div>
              <strong>Smart Visitor Management System</strong>
              <span>${template.label} dashboard</span>
            </div>
          </div>

          <div class="sidebar-card">
            <strong>${user.name}</strong>
            <span>${template.title}</span>
            <div class="auth-meta" style="margin-top:12px;">
              <span class="role-pill" style="background:rgba(255,255,255,.12);color:#fff;">${template.icon ? `<i class="bi ${template.icon}"></i>` : ''} ${template.label}</span>
              <span class="pill" style="background:rgba(255,255,255,.08);color:#fff;"><i class="bi bi-clock"></i> ${formatTimeShort()}</span>
            </div>
          </div>

          <div class="sidebar-links">${sidebarLinks}</div>

          <div class="sidebar-card">
            <strong>Presentation mode</strong>
            <span>Everything here is a clean front-end prototype. Refresh the page to reset all auth state.</span>
          </div>

          <div class="sidebar-footer">
            <button class="logout-button" type="button" id="logout-button"><i class="bi bi-box-arrow-right"></i> Logout</button>
            <div class="sidebar-meta">Signed in locally only · no storage used</div>
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

  }

  function buildSidebarLinks(roleSlug, activeSection) {
    const items = [
      ['overview', 'Overview', 'bi-speedometer2'],
      ['operations', 'Operations', 'bi-layers-half'],
      ['activity', 'Activity', 'bi-activity'],
      ['queue', 'Queue', 'bi-list-check'],
      ['analytics', 'Analytics', 'bi-graph-up-arrow'],
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
    const stats = template.stats;
    const quickActions = template.quickActions;
    const activity = template.activity;
    const queue = template.queue;
    const focus = template.focus;

    if (section === 'analytics') {
      return `
        <div class="dashboard-content">
          <section class="dashboard-card">
            <div class="section-heading">
              <h2>Real-time Analytics Dashboard</h2>
              <p class="section-note">Visitor trends, performance metrics, and operational insights</p>
            </div>
            <div style="padding: 20px; background: rgba(37,99,235,0.05); border-radius: 12px; margin-top: 16px;">
              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div style="padding: 16px; background: white; border-radius: 8px; border-left: 4px solid #2563eb;">
                  <strong style="font-size: 0.85rem; color: #64748b;">Hourly Visitors</strong>
                  <div style="font-size: 1.5rem; font-weight: 600; margin-top: 8px;">${state.analyticsData.visitorsByHour.reduce((a,b) => a+b, 0)}</div>
                  <small style="color: #10b981;">↑ 12% from last hour</small>
                </div>
                <div style="padding: 16px; background: white; border-radius: 8px; border-left: 4px solid #f59e0b;">
                  <strong style="font-size: 0.85rem; color: #64748b;">Peak Hour</strong>
                  <div style="font-size: 1rem; font-weight: 600; margin-top: 8px;">${state.analyticsData.peakHour}</div>
                  <small style="color: #f59e0b;">Expected surge</small>
                </div>
                <div style="padding: 16px; background: white; border-radius: 8px; border-left: 4px solid #10b981;">
                  <strong style="font-size: 0.85rem; color: #64748b;">Avg Check-in</strong>
                  <div style="font-size: 1.5rem; font-weight: 600; margin-top: 8px;">${state.analyticsData.avgCheckInTime}s</div>
                  <small style="color: #10b981;">↓ 5% improvement</small>
                </div>
              </div>
            </div>
          </section>
        </div>
      `;
    }

    if (section === 'activity') {
      return `
        <div class="dashboard-content">
          <section class="dashboard-card">
            <div class="section-heading">
              <h2>Activity Feed</h2>
              <p class="section-note">Real-time system events and operations</p>
            </div>
            <div class="timeline" style="padding: 20px;">
              ${activity.map((item) => `
                <div class="timeline-item">
                  <div class="timeline-dot" style="background:${activityTone(item.badge)};"></div>
                  <div class="timeline-content">
                    <strong>${item.title}</strong>
                    <p>${item.meta}</p>
                  </div>
                </div>
              `).join('')}
            </div>
          </section>
        </div>
      `;
    }

    if (section === 'queue') {
      return `
        <div class="dashboard-content">
          <section class="dashboard-card">
            <div class="section-heading">
              <h2>Current Queue</h2>
              <p class="section-note">Items pending your attention</p>
            </div>
            <table class="table" style="margin-top: 16px;">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Time</th>
                </tr>
              </thead>
              <tbody>
                ${queue.map((item) => `
                  <tr>
                    <td><strong>${item.name}</strong></td>
                    <td>${item.role}</td>
                    <td><span class="status-badge ${queueTone(item.status)}">${item.status}</span></td>
                    <td>${item.time}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </section>
        </div>
      `;
    }

    if (section === 'operations') {
      return `
        <div class="dashboard-content">
          <section class="dashboard-card">
            <div class="section-heading">
              <h2>Operations & Controls</h2>
              <p class="section-note">Role-specific operations and workflows</p>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-top: 16px;">
              ${quickActions.map((action) => `
                <div style="padding: 16px; border: 1px solid var(--line); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(37,99,235,0.05)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='transparent'; this.style.transform='translateY(0)'">
                  <div style="font-size: 1.5rem; color: var(--primary); margin-bottom: 12px;"><i class="bi ${action.icon}"></i></div>
                  <strong>${action.title}</strong>
                  <p style="font-size: 0.9rem; color: var(--muted); margin: 8px 0;">${action.description}</p>
                </div>
              `).join('')}
            </div>
          </section>
        </div>
      `;
    }

    if (section === 'profile') {
      return `
        <div class="dashboard-content">
          <section class="dashboard-card">
            <div class="section-heading">
              <h2>User Profile</h2>
              <p class="section-note">Your account information and settings</p>
            </div>
            <div style="padding: 20px; background: rgba(37,99,235,0.05); border-radius: 8px; margin-top: 16px;">
              <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                <div><strong>Name</strong><p style="color: var(--muted);">${user.name}</p></div>
                <div><strong>Email</strong><p style="color: var(--muted);">${user.email}</p></div>
                <div><strong>Role</strong><p style="color: var(--muted);">${user.role}</p></div>
                <div><strong>Status</strong><p style="color: var(--muted);">Active</p></div>
              </div>
            </div>
          </section>
        </div>
      `;
    }

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
                <span class="pill"><i class="bi bi-lightning-charge-fill"></i> ${capitalize(section)}</span>
              </div>
              <div class="quick-grid">
                ${quickActions.map((action) => `
                  <button class="quick-action" type="button">
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
                  <p>Presentation-friendly queue of the most important items on this role’s desk.</p>
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
                  <p>Use this panel to show responsive, role-aware operations in a clean way.</p>
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
                  <p>${template.label}</p>
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
                  <strong>Refresh behavior</strong>
                  <p>Always returns to login</p>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>
    `;
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

  function logout() {
    state.currentUser = null;
    state.pendingEmail = '';
    state.pendingPassword = '';
    state.activeSection = 'overview';
    state.mobileSidebarOpen = false;
    state.activeAuthView = 'login';
    showToast('info', 'Signed out', 'Session cleared from memory.');
    routeTo('login');
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

  window.addEventListener('hashchange', render);
  window.addEventListener('load', () => {
    if (!location.hash) {
      location.hash = '#/login';
    }
    render();
  });
})();