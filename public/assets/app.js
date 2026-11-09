(() => {
  const root = document.documentElement;
  const saved = localStorage.getItem('samith-theme');
  root.dataset.theme = saved === 'light' || saved === 'dark'
    ? saved : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

  const menu = document.getElementById('global-row-menu');
  const closeMenu = () => { if (menu) { menu.classList.remove('open'); menu.replaceChildren(); } };

  document.addEventListener('click', (event) => {
    const theme = event.target.closest('[data-theme-toggle]');
    if (theme) {
      const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
      root.dataset.theme = next;
      localStorage.setItem('samith-theme', next);
    
  document.addEventListener('click', async (event) => {
    const copy = event.target.closest('[data-copy-target]');
    if (copy) {
      const source = document.getElementById(copy.dataset.copyTarget);
      if (!source) return;
      try {
        await navigator.clipboard.writeText(source.textContent);
        const old = copy.textContent;
        copy.textContent = 'Copied';
        setTimeout(() => { copy.textContent = old; }, 1800);
      } catch { copy.textContent = 'Copy unavailable'; }
    }
    const wrap = event.target.closest('[data-editor-wrap]');
    if (wrap) {
      const field = document.getElementById(wrap.dataset.editorTarget);
      if (!field) return;
      const start = field.selectionStart, end = field.selectionEnd;
      const tag = wrap.dataset.editorWrap;
      const selected = field.value.slice(start, end) || 'text';
      const markup = '<' + tag + '>' + selected + '</' + tag + '>';
      field.setRangeText(markup, start, end, 'select');
      field.focus();
    }
  });


  // Rich editor: the hidden HTML field is always the submitted source of truth.
  document.addEventListener('mousedown', (event) => {
    if (event.target.closest('[data-rich-command]')) event.preventDefault();
  });
  document.addEventListener('click', (event) => {
    const command = event.target.closest('[data-rich-command]');
    if (command) {
      const editor = document.getElementById('entry-rich-editor');
      if (!editor || editor.hidden) return;
      editor.focus();
      document.execCommand(command.dataset.richCommand, false, command.dataset.richValue || null);
      document.getElementById('entry-body').value = editor.innerHTML;
      return;
    }
    const toggle = event.target.closest('[data-rich-source-toggle]');
    if (toggle) {
      const editor = document.getElementById('entry-rich-editor');
      const field = document.getElementById('entry-body');
      if (!editor || !field) return;
      if (field.hidden) {
        field.value = editor.innerHTML;
        field.hidden = false;
        editor.hidden = true;
        toggle.textContent = 'HTML source (save to preview)';
        toggle.disabled = true;
      } else {
        // A raw source edit must be saved, sanitized and reloaded before visual preview.
        // Do not inject untrusted HTML directly into contenteditable.
        toggle.textContent = 'HTML source (save to preview)';
      }
    }
  });
  document.addEventListener('input', (event) => {
    if (event.target?.id === 'entry-rich-editor') {
      document.getElementById('entry-body').value = event.target.innerHTML;
    }
  });
  document.addEventListener('submit', (event) => {
    if (event.target?.matches('form[data-resource-form]') && event.target.querySelector('#entry-body')) {
      const editor = document.getElementById('entry-rich-editor');
      const source = document.getElementById('entry-body');
      if (source.hidden) source.value = editor.innerHTML;
    }
  });

  document.querySelectorAll('[data-theme-name]').forEach(el => el.textContent = next === 'dark' ? 'Light mode' : 'Dark mode');
      return;
    }

    const open = event.target.closest('[data-modal-open]');
    if (open) {
      closeMenu();
      const dialog = document.getElementById(open.dataset.modalOpen);
      if (!dialog) return;
      const form = dialog.querySelector('form[data-resource-form]');
      if (form) {
        form.reset();
        form.action = open.dataset.resourceUrl || form.dataset.createUrl;
        const method = form.querySelector('input[name="_method"]');
        const editing = Boolean(open.dataset.resourceUrl) && open.dataset.modalForcePost !== '1';
        if (method) method.disabled = !editing;
        dialog.querySelectorAll('[data-modal-mode]').forEach(el => el.textContent = editing ? 'Edit' : 'Create');
        form.querySelectorAll('[data-fill]').forEach(field => {
          const attr = 'field' + field.dataset.fill.charAt(0).toUpperCase() + field.dataset.fill.slice(1);
          if (open.dataset[attr] !== undefined) field.value = open.dataset[attr];
        });
      }
      if (dialog.id === 'content-modal') {
        const editor = document.getElementById('entry-rich-editor');
        const source = document.getElementById('entry-body');
        editor.innerHTML = source.value;
        source.hidden = true;
        editor.hidden = false;
        const sourceToggle = dialog.querySelector('[data-rich-source-toggle]');
        if (sourceToggle) { sourceToggle.textContent = 'HTML source'; sourceToggle.disabled = false; }
      }
      dialog.showModal();
      return;
    }

    if (event.target.closest('[data-modal-close]')) {
      event.target.closest('dialog')?.close();
      return;
    }

    const toggle = event.target.closest('[data-action-toggle]');
    if (toggle && menu) {
      if (menu.classList.contains('open') && menu.dataset.openFor === toggle.dataset.actionToggle) {
        closeMenu(); return;
      }
      const template = document.getElementById(toggle.dataset.actionToggle);
      if (!template) return;
      menu.replaceChildren(template.content.cloneNode(true));
      const rect = toggle.getBoundingClientRect();
      const w = 190;
      menu.style.left = Math.max(10, Math.min(window.innerWidth - w - 10, rect.right - w)) + 'px';
      menu.style.top = Math.min(window.innerHeight - 190, rect.bottom + 5) + 'px';
      menu.dataset.openFor = toggle.dataset.actionToggle;
      menu.classList.add('open');
      return;
    }
    if (menu && !event.target.closest('#global-row-menu')) closeMenu();
    const dismiss = event.target.closest('[data-toast-dismiss]');
    if (dismiss) dismiss.closest('.toast')?.remove();
  });

  window.addEventListener('scroll', closeMenu, true);
  window.addEventListener('resize', closeMenu);

  document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-confirm]');
    if (!form || form.dataset.confirmed === 'true') return;
    event.preventDefault();
    const dialog = document.getElementById('confirmation-modal');
    if (!dialog) return;
    dialog.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
    const yes = dialog.querySelector('[data-confirm-yes]');
    yes.onclick = () => { dialog.close(); form.dataset.confirmed = 'true'; form.requestSubmit(); };
    dialog.showModal();
  });

  document.querySelectorAll('[data-theme-name]').forEach(el => {
    el.textContent = root.dataset.theme === 'dark' ? 'Light mode' : 'Dark mode';
  });
  document.querySelectorAll('[data-password-toggle]').forEach(button => {
    button.addEventListener('click', () => {
      const field = document.getElementById(button.dataset.passwordToggle);
      if (!field) return;
      const show = field.type === 'password';
      field.type = show ? 'text' : 'password';
      const actionLabel = show ? 'Hide password' : 'Show password';
      button.setAttribute('aria-label', actionLabel);
      button.setAttribute('aria-pressed', String(show));
      button.setAttribute('title', actionLabel);
    });
  });
})();

// Keep native navigation usable without JS; collapse it on small screens when JS is available.
(() => {
  const navigation = document.querySelector('[data-workspace-mobile-nav]');
  if (!navigation || typeof matchMedia !== 'function') return;
  const mobile = matchMedia('(max-width: 760px)');
  const synchronize = () => { navigation.open = !mobile.matches; };
  synchronize();
  if (typeof mobile.addEventListener === 'function') mobile.addEventListener('change', synchronize);
})();


// Global feedback notifications and Appearance Frontend Pages editor.
// Kept independent from other marketplace handlers to avoid UI regressions.
(() => {
  const notifications = [...document.querySelectorAll('.toast-stack .toast')];
  notifications.forEach((toast) => {
    const expiry = toast.classList.contains('error-toast') ? 9000 : 4500;
    const duration = expiry;
    let remaining = duration;
    let started = Date.now();
    let timer = null;
    let closed = false;

    const close = () => {
      if (closed) return;
      closed = true;
      clearTimeout(timer);
      toast.classList.add('is-closing');
      window.setTimeout(() => toast.remove(), window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 190);
    };
    const pause = () => {
      if (closed || timer === null) return;
      clearTimeout(timer);
      timer = null;
      remaining = Math.max(0, remaining - (Date.now() - started));
    };
    const resume = () => {
      if (closed || timer !== null) return;
      started = Date.now();
      timer = window.setTimeout(close, remaining);
    };

    toast.querySelector('[data-toast-dismiss]')?.addEventListener('click', close);
    toast.addEventListener('mouseenter', pause);
    toast.addEventListener('mouseleave', resume);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', () => {
      window.requestAnimationFrame(() => {
        if (!toast.contains(document.activeElement)) resume();
      });
    });
    resume();
  });

  // Native HTML dialogs: Escape/keyboard focus supported; no injected HTML.
  document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-frontend-dialog]');
    if (opener) {
      const dialog = document.getElementById(opener.dataset.frontendDialog);
      if (dialog instanceof HTMLDialogElement && !dialog.open) {
        dialog.showModal();
      }
      return;
    }
    const closer = event.target.closest('[data-frontend-close]');
    if (closer) {
      closer.closest('dialog')?.close();
    }
  });
  document.querySelectorAll('dialog.frontend-config-dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
  });
})();


// Workspace header navigation enhancements, isolated from existing marketplace scripts.
(() => {
  const workspace = document.querySelector('.workspace');
  if (!workspace) return;

  const sidebar = document.getElementById('workspace-sidebar');
  const toggle = document.querySelector('[data-workspace-sidebar-toggle]');
  const storageKey = 'samith-workspace-sidebar-hidden';
  const setSidebarHidden = (hidden, revealMobileNavigation = false) => {
    workspace.classList.toggle('workspace-sidebar-hidden', hidden);
    if (toggle) {
      toggle.setAttribute('aria-expanded', String(!hidden));
      toggle.setAttribute('aria-label', hidden ? 'Show sidebar' : 'Hide sidebar');
      toggle.title = hidden ? 'Show sidebar' : 'Hide sidebar';
    }
    if (sidebar) sidebar.setAttribute('aria-hidden', String(hidden));
    if (!hidden && revealMobileNavigation && matchMedia('(max-width:760px)').matches) {
      const mobileNav = sidebar?.querySelector('[data-workspace-mobile-nav]');
      if (mobileNav) mobileNav.open = true;
    }
  };
  let savedSidebar = false;
  try { savedSidebar = localStorage.getItem(storageKey) === '1'; } catch {}
  setSidebarHidden(savedSidebar);
  toggle?.addEventListener('click', () => {
    const next = !workspace.classList.contains('workspace-sidebar-hidden');
    setSidebarHidden(next, !next);
    try { localStorage.setItem(storageKey, next ? '1' : '0'); } catch {}
  });

  const search = document.querySelector('[data-workspace-search]');
  const field = search?.querySelector('[data-workspace-search-input]');
  const results = search?.querySelector('[data-workspace-search-results]');
  const empty = search?.querySelector('[data-workspace-search-empty]');
  const links = [...(search?.querySelectorAll('[data-workspace-search-item]') || [])];
  const closeSearch = () => {
    if (!results || !field) return;
    results.hidden = true;
    field.setAttribute('aria-expanded', 'false');
  };
  const refresh = () => {
    if (!field || !results) return [];
    const term = field.value.trim().toLocaleLowerCase();
    let shown = 0;
    const matches = [];
    links.forEach((link) => {
      const match = (link.textContent + ' ' + (link.dataset.searchTerms || '')).toLocaleLowerCase().includes(term);
      if (match && shown < 7) { link.hidden = false; matches.push(link); shown++; }
      else link.hidden = true;
    });
    if (empty) empty.hidden = matches.length !== 0;
    results.hidden = false;
    field.setAttribute('aria-expanded', 'true');
    return matches;
  };
  field?.addEventListener('focus', refresh);
  field?.addEventListener('input', refresh);
  field?.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') { closeSearch(); field.blur(); return; }
    if (event.key === 'ArrowDown') { const first = refresh()[0]; if (first) { event.preventDefault(); first.focus(); } }
    if (event.key === 'Enter') { const first = refresh()[0]; if (first) { event.preventDefault(); window.location.assign(first.href); } }
  });
  results?.addEventListener('keydown', (event) => {
    const visible = links.filter((link) => !link.hidden);
    const current = visible.indexOf(document.activeElement);
    if (event.key === 'Escape') { event.preventDefault(); closeSearch(); field?.focus(); }
    else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const next = event.key === 'ArrowDown' ? current + 1 : current - 1;
      if (next < 0) field?.focus();
      else visible[Math.min(next, visible.length - 1)]?.focus();
    }
  });
  const menus = [...document.querySelectorAll('[data-workspace-header-menu]')];
  document.addEventListener('click', (event) => {
    if (search && !search.contains(event.target)) closeSearch();
    menus.forEach((menu) => {
      if (!menu.contains(event.target)) menu.open = false;
    });
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') menus.forEach((menu) => { menu.open = false; });
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k' && field) {
      event.preventDefault();
      field.focus();
      field.select();
    }
  });
})();


// Account-scoped workspace shortcut picker: user-visible, server-persisted controls.
(() => {
  document.querySelectorAll('[data-workspace-launchpad]').forEach((panel) => {
    const context = panel.dataset.workspaceLaunchpad;
    const dialog = document.getElementById('workspace-launchpad-config-' + context);
    const open = panel.querySelector('[data-launchpad-configure]');
    const form = dialog?.querySelector('.workspace-launchpad-form');
    const checks = [...(form?.querySelectorAll('input[name="shortcuts[]"]') || [])];
    const counter = dialog?.querySelector('[data-launchpad-count]');
    const save = dialog?.querySelector('[data-launchpad-save]');
    if (!dialog || !form || !open) return;

    const refresh = () => {
      const selected = checks.filter((box) => box.checked).length;
      checks.forEach((box) => { box.disabled = !box.checked && selected >= 6; });
      if (counter) counter.textContent = selected + ' of 6 selected';
      if (save) save.disabled = selected === 0 || selected > 6;
    };
    open.addEventListener('click', () => { refresh(); dialog.showModal(); });
    checks.forEach((box) => box.addEventListener('change', refresh));
    dialog.querySelectorAll('[data-launchpad-close]').forEach((button) => {
      button.addEventListener('click', () => dialog.close());
    });
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
    form.addEventListener('submit', (event) => {
      const selected = checks.filter((box) => box.checked).length;
      if (selected < 1 || selected > 6) event.preventDefault();
    });
  });
})();
