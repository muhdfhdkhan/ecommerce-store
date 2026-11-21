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

// Image uploads: instant local thumbnails before the form is submitted.
// FileReader data URLs (not blob:) so the page CSP img-src 'self' data: allows them.
for (const input of document.querySelectorAll('[data-image-input]')) {
  input.addEventListener('change', () => {
    const box = input.closest('form')?.querySelector('[data-image-preview]')
      || input.closest('section')?.querySelector('[data-image-preview]');
    if (!box) return;
    box.innerHTML = '';
    for (const file of Array.from(input.files).slice(0, 8)) {
      if (!file.type.startsWith('image/')) continue;
      const img = document.createElement('img');
      img.alt = file.name;
      img.className = 'h-16 w-16 rounded-xl border border-line object-cover';
      const reader = new FileReader();
      reader.onload = () => { img.src = String(reader.result); };
      reader.readAsDataURL(file);
      box.appendChild(img);
    }
  });
}

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

// ---- CSV product import ----
// Choosing a CSV immediately uploads it and opens the preview page.
const csvInput = document.querySelector('[data-import-file]');
if (csvInput) {
  csvInput.addEventListener('change', () => {
    if (csvInput.files && csvInput.files.length > 0) {
      csvInput.closest('form')?.requestSubmit();
    }
  });
}

// Per-row image thumbnails inside the CSV preview (scoped to that row only).
for (const input of document.querySelectorAll('[data-import-images]')) {
  input.addEventListener('change', () => {
    const box = input.closest('[data-import-row]')?.querySelector('[data-import-preview]');
    if (!box) return;
    box.replaceChildren();
    for (const file of Array.from(input.files).slice(0, 8)) {
      if (!file.type.startsWith('image/')) continue;
      const img = document.createElement('img');
      img.alt = file.name;
      img.className = 'h-16 w-16 rounded-xl border border-line object-cover';
      const reader = new FileReader();
      reader.onload = () => { img.src = String(reader.result); };
      reader.readAsDataURL(file);
      box.appendChild(img);
    }
  });
}

// The import preview is an overlay; keep the page behind it still.
if (document.querySelector('[role="dialog"][aria-labelledby="import-title"]')) {
  document.body.classList.add('overflow-hidden');
}
