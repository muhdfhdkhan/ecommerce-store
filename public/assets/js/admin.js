// Patches&Motifs — admin behaviour (vanilla ES module, self-hosted; CSP 'self' only).
// Uses textContent-safe DOM building; no innerHTML with dynamic data (SECURITY.md §6.3).

// Password visibility toggle on the login form.
const loginForm = document.querySelector('form[action="/admin/login"]');
if (loginForm) {
  // Mark the time the form was first interacted with (mini time-trap bot check).
  loginForm.addEventListener('input', () => {
    loginForm.dataset.touched = '1';
  }, { once: true });

  for (const btn of loginForm.querySelectorAll('[data-toggle-password]')) {
    btn.addEventListener('click', () => {
      const input = document.querySelector(btn.dataset.togglePassword);
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
    });
  }
}

// Admin drawer (mobile sidebar).
const drawer = document.querySelector('[data-drawer]');
const backdrop = document.querySelector('[data-drawer-backdrop]');
const openBtn = document.querySelector('[data-drawer-open]');

function setDrawer(open) {
  if (!drawer || !backdrop) return;
  drawer.classList.toggle('-translate-x-full', !open);
  backdrop.classList.toggle('hidden', !open);
  document.body.classList.toggle('overflow-hidden', open && window.innerWidth < 1024);
  if (openBtn) openBtn.setAttribute('aria-expanded', String(open));
}

openBtn?.addEventListener('click', () => setDrawer(true));
backdrop?.addEventListener('click', () => setDrawer(false));
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') setDrawer(false);
});

// Purchase order: clone the first line row for quick multi-line entry.
const poLines = document.getElementById('po-lines');
const poAddBtn = document.getElementById('po-add-line');
if (poLines && poAddBtn) {
  poAddBtn.addEventListener('click', () => {
    const first = poLines.firstElementChild;
    if (!first) return;
    const clone = first.cloneNode(true);
    for (const el of clone.querySelectorAll('input, select')) el.value = '';
    poLines.appendChild(clone);
  });
}

// ---- Orders: bulk selection (count badge + select row highlighting) ----
const bulkChecks = Array.from(document.querySelectorAll('[data-bulk-check]'));
const bulkCount = document.querySelector('[data-bulk-count]');
function refreshBulkCount() {
  const n = bulkChecks.filter((c) => c.checked).length;
  if (bulkCount) bulkCount.textContent = String(n);
}
bulkChecks.forEach((c) => c.addEventListener('change', refreshBulkCount));
refreshBulkCount();
document.querySelector('[data-bulk-all]')?.addEventListener('change', (e) => {
  bulkChecks.forEach((c) => { c.checked = e.target.checked; });
  refreshBulkCount();
});

// Bulk print: build a GET url from the current selection.
document.querySelector('[data-bulk-print]')?.addEventListener('click', () => {
  const ids = bulkChecks.filter((c) => c.checked).map((c) => c.value);
  if (ids.length === 0) { alert('Select at least one order to print.'); return; }
  window.open('/admin/orders/print?ids=' + ids.join(',') + '&autoprint=1', '_blank', 'noopener');
});

// Print sheet page: auto-print requested via ?autoprint=1 is handled in print.js.
