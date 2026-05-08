(function () {
  const roleDefinitions = {
    admin: {
      label: 'Admin',
      title: 'Executive control center',
      subtitle: 'Monitor the platform, approve users, and keep operations secure.',
      icon: 'bi-shield-lock-fill',
      accent: 'primary',
      stats: [
        { label: 'Visitors today', value: 148, caption: '+18% since yesterday', icon: 'bi-people-fill', tone: 'primary' },
        { label: 'Pending approvals', value: 12, caption: 'Access requests waiting', icon: 'bi-person-check-fill', tone: 'warning' },
        { label: 'Security alerts', value: 2, caption: 'Needs review now', icon: 'bi-exclamation-triangle-fill', tone: 'success' },
        { label: 'Uptime', value: '99.98%', caption: 'Prototype health is stable', icon: 'bi-graph-up-arrow', tone: 'accent' },
      ],
      quickActions: [
        { title: 'Approve access requests', description: 'Review newly registered accounts and grant the correct role.', icon: 'bi-person-badge-fill' },
        { title: 'Open analytics', description: 'Present daily trends, top desk activity, and occupancy at a glance.', icon: 'bi-graph-up-arrow' },
        { title: 'Review audit log', description: 'Inspect the latest security and operations events.', icon: 'bi-journal-text' },
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
      title: 'Front-desk operations hub',
      subtitle: 'Manage arrivals, check-ins, and visitor communication in one place.',
      icon: 'bi-building-check',
      accent: 'accent',
      stats: [
        { label: 'Check-ins today', value: 64, caption: 'Front desk handled', icon: 'bi-door-open-fill', tone: 'accent' },
        { label: 'Visitors waiting', value: 8, caption: 'Lobby queue right now', icon: 'bi-hourglass-split', tone: 'warning' },
        { label: 'Badges printed', value: 59, caption: 'Smooth reception flow', icon: 'bi-printer-fill', tone: 'success' },
        { label: 'Avg. check-in', value: '42 sec', caption: 'Fast prototype flow', icon: 'bi-lightning-charge-fill', tone: 'primary' },
      ],
      quickActions: [
        { title: 'Register a visitor', description: 'Create a polished front-desk intake in seconds.', icon: 'bi-person-plus-fill' },
        { title: 'Search a guest', description: 'Find appointments, badges, and host details quickly.', icon: 'bi-search' },
        { title: 'Print badge', description: 'Issue a clean, presentation-ready visitor badge.', icon: 'bi-printer-fill' },
      ],
      activity: [
        { title: 'Walk-in checked in', meta: 'Imran Ali · 2 min ago', badge: 'success' },
        { title: 'Appointment confirmed', meta: 'Front desk · 11 min ago', badge: 'info' },
        { title: 'Badge reprint requested', meta: 'North lobby · 19 min ago', badge: 'warning' },
        { title: 'Visitor checked out', meta: 'Main reception · 27 min ago', badge: 'success' },
      ],
      queue: [
        { name: 'Zara Munir', role: 'Appointment arrival', status: 'Ready', time: 'Now' },
        { name: 'Hamza Raza', role: 'Walk-in visitor', status: 'Waiting', time: '3 min' },
        { name: 'Noor Fatima', role: 'Badge reprint', status: 'Processing', time: '6 min' },
      ],
      focus: [
        'Fast registration',
        'Queue management',
        'Badge printing',
        'Host notifications',
      ],
    },
    security: {
      label: 'Security',
      title: 'Perimeter watch console',
      subtitle: 'Track exception handling, incident escalations, and high-risk entries.',
      icon: 'bi-shield-exclamation',
      accent: 'warning',
      stats: [
        { label: 'Active alerts', value: 3, caption: 'Need eyes on them', icon: 'bi-bell-fill', tone: 'danger' },
        { label: 'Screened entries', value: 92, caption: 'All clear at the gate', icon: 'bi-check2-circle', tone: 'success' },
        { label: 'Flagged visitors', value: 4, caption: 'Held for review', icon: 'bi-ban-fill', tone: 'warning' },
        { label: 'Response time', value: '1.8 min', caption: 'Escalations resolved quickly', icon: 'bi-stopwatch-fill', tone: 'primary' },
      ],
      quickActions: [
        { title: 'Open incident log', description: 'Review suspicious visits and resolved incidents.', icon: 'bi-clipboard2-data-fill' },
        { title: 'Review blacklist', description: 'Inspect blocked entries and their matching rules.', icon: 'bi-x-octagon-fill' },
        { title: 'Trigger lockdown', description: 'Keep emergency action visible for presentation.', icon: 'bi-shield-fill-exclamation' },
      ],
      activity: [
        { title: 'Restricted badge denied', meta: 'East gate · 5 min ago', badge: 'danger' },
        { title: 'Escort requested', meta: 'Security desk · 14 min ago', badge: 'warning' },
        { title: 'Incident resolved', meta: 'Front gate · 21 min ago', badge: 'success' },
        { title: 'Perimeter sweep complete', meta: 'North wing · 33 min ago', badge: 'info' },
      ],
      queue: [
        { name: 'Asad Qureshi', role: 'Flag review', status: 'Hold', time: 'Now' },
        { name: 'Hina Shah', role: 'Escort escort', status: 'Escalated', time: '4 min' },
        { name: 'Umer Siddiq', role: 'Device check', status: 'Ready', time: '9 min' },
      ],
      focus: [
        'Risk screening',
        'Incident response',
        'Blacklist control',
        'Gate monitoring',
      ],
    },
    other: {
      label: 'Operations',
      title: 'Team dashboard',
      subtitle: 'A clean role-based workspace for any additional department or custom role.',
      icon: 'bi-grid-1x2-fill',
      accent: 'success',
      stats: [
        { label: 'Tasks completed', value: 31, caption: 'This shift', icon: 'bi-check2-square', tone: 'success' },
        { label: 'Open requests', value: 5, caption: 'Awaiting response', icon: 'bi-inbox-fill', tone: 'warning' },
        { label: 'Team members', value: 14, caption: 'On the roster', icon: 'bi-people-fill', tone: 'accent' },
        { label: 'SLA score', value: '96%', caption: 'Meeting internal targets', icon: 'bi-award-fill', tone: 'primary' },
      ],
      quickActions: [
        { title: 'Open task board', description: 'Organize shift work and keep everyone aligned.', icon: 'bi-kanban-fill' },
        { title: 'Review handoff notes', description: 'Collect the key updates your team needs for today.', icon: 'bi-journal-check' },
        { title: 'Share a status brief', description: 'Present a polished update to stakeholders.', icon: 'bi-megaphone-fill' },
      ],
      activity: [
        { title: 'Task completed', meta: 'Operations team · 8 min ago', badge: 'success' },
        { title: 'Request assigned', meta: 'New owner added · 15 min ago', badge: 'info' },
        { title: 'Report shared', meta: 'Team inbox · 24 min ago', badge: 'warning' },
        { title: 'Meeting scheduled', meta: 'Today at 4:30 PM · 41 min ago', badge: 'primary' },
      ],
      queue: [
        { name: 'Team briefing', role: 'Standup', status: 'Scheduled', time: '9:00' },
        { name: 'Backlog review', role: 'Planning', status: 'Open', time: '13:00' },
        { name: 'Stakeholder sync', role: 'Presentation', status: 'Pending', time: '16:30' },
      ],
      focus: [
        'Task visibility',
        'Team coordination',
        'Clear handoffs',
        'Executive summary',
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
    users: seededUsers.map((user) => ({ ...user, roleSlug: slugifyRole(user.role) })),
    currentUser: null,
    activeAuthView: 'login',
    activeSection: 'overview',
    pendingEmail: '',
    pendingPassword: '',
    mobileSidebarOpen: false,
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
      ['profile', 'Profile', 'bi-person-badge'],
    ];

    return items.map(([slug, label, icon]) => `
      <button class="sidebar-link ${activeSection === slug ? 'active' : ''}" type="button" data-section-link="${slug}">
        <span><i class="bi ${icon}"></i> ${label}</span>
        <small>${slug === 'overview' ? 'Summary' : 'View'}</small>
      </button>
    `).join('');
  }

  function buildSectionMarkup(user, template, section) {
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
    showToast('info', 'Signed out', 'Authentication state was cleared from memory.');
    routeTo('login');
  }

  window.addEventListener('hashchange', render);
  window.addEventListener('load', () => {
    if (!location.hash) {
      location.hash = '#/login';
    }
    render();
  });
})();