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
        const editing = Boolean(open.dataset.resourceUrl);
        if (method) method.disabled = !editing;
        dialog.querySelectorAll('[data-modal-mode]').forEach(el => el.textContent = editing ? 'Edit' : 'Create');
        form.querySelectorAll('[data-fill]').forEach(field => {
          const attr = 'field' + field.dataset.fill.charAt(0).toUpperCase() + field.dataset.fill.slice(1);
          if (open.dataset[attr] !== undefined) field.value = open.dataset[attr];
        });
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
      button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });
})();
