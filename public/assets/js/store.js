// Patches&Motifs — storefront behaviour (vanilla ES module, self-hosted).
// SECURITY.md §6.3: no innerHTML with dynamic data; textContent / dataset only.

// Product gallery thumbnails.
const mainImage = document.getElementById('main-image');
for (const thumb of document.querySelectorAll('[data-gallery-thumb]')) {
  thumb.addEventListener('click', () => {
    const src = thumb.dataset.galleryThumb;
    const img = mainImage?.querySelector('img');
    if (img && src) img.src = src;
  });
}

// Variant chips -> price + SKU update.
const variantsData = JSON.parse(document.getElementById('variants-data')?.textContent || '[]');
const priceEl = document.getElementById('selected-price');
const radios = document.querySelectorAll('input[name="variant_id"]');

// Custom size (made-to-order products): slider <-> typed inches.
const sizeRange = document.querySelector('[data-size-range]');
const sizeNumber = document.querySelector('[data-size-number]');
const sizeError = document.querySelector('[data-size-error]');
const sizeMin = Number(sizeRange?.min || sizeNumber?.min || 0);
const sizeMax = Number(sizeRange?.max || sizeNumber?.max || 0);

function hideSizeError() {
  if (sizeError) sizeError.classList.add('hidden');
}

function syncSize(source) {
  if (!sizeRange || !sizeNumber) return;
  // Let the shopper clear the field while typing; submit validates it.
  const raw = source === 'range' ? sizeRange.value : sizeNumber.value.trim();
  if (raw === '') return;
  const value = Number(raw);
  if (!Number.isFinite(value)) return;
  if (value < sizeMin - 0.001 || value > sizeMax + 0.001) {
    if (sizeError) {
      sizeError.textContent = 'Enter a size between ' + sizeMin + ' and ' + sizeMax + ' inches.';
      sizeError.classList.remove('hidden');
    }
    return;
  }
  hideSizeError();
  if (source === 'range') {
    sizeNumber.value = String(value);
  }
  sizeRange.value = String(Math.min(sizeMax, Math.max(sizeMin, value)));
}

// Inline cart feedback (errors are never squeezed into the button label).
const cartError = document.querySelector('[data-cart-error]');

function showCartMessage(message) {
  if (!cartError) return;
  cartError.textContent = message;
  cartError.classList.remove('hidden');
}

function hideCartMessage() {
  if (!cartError) return;
  cartError.textContent = '';
  cartError.classList.add('hidden');
}

/** Current session CSRF token: the layout meta tag, or a form field on older pages. */
function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta?.content || document.querySelector('input[name="_csrf"]')?.value || '';
}

/** Keep every token carrier on the page in sync after a token refresh. */
function storeCsrfToken(token) {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) meta.content = token;
  document.querySelectorAll('input[name="_csrf"]').forEach((field) => { field.value = token; });
}

/** Ask the server for the current session CSRF token (recovers a rotated/expired one). */
async function refreshCsrfToken() {
  try {
    const res = await fetch('/api/csrf', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    });
    if (!res.ok) return null;
    const data = await res.json().catch(() => ({}));
    return typeof data.token === 'string' && data.token !== '' ? data.token : null;
  } catch {
    return null;
  }
}

sizeRange?.addEventListener('input', () => syncSize('range'));
sizeNumber?.addEventListener('input', () => syncSize('number'));

// A preset chip with a numeric size keeps the custom size control in sync.
function syncSizeFromChip(chosen) {
  if (!sizeRange || !sizeNumber || !chosen) return;
  const match = String(chosen.dataset.size || '').match(/(\d+(?:\.\d+)?)/);
  if (!match) return;
  const value = Math.min(sizeMax, Math.max(sizeMin, Number(match[1])));
  sizeRange.value = String(value);
  sizeNumber.value = String(value);
  hideSizeError();
}

function refreshVariant() {
  const chosen = document.querySelector('input[name="variant_id"]:checked');
  if (!chosen || chosen.dataset.out === '1') return;
  const v = variantsData.find((x) => x.id === Number(chosen.value));
  if (v && priceEl) priceEl.textContent = 'Rs ' + v.price.toLocaleString('en-PK');
  syncSizeFromChip(chosen);
}

radios.forEach((r) => r.addEventListener('change', refreshVariant));
refreshVariant();

// Add to cart via fetch (AJAX, Fetch API).
const addToCartForm = document.getElementById('add-to-cart-form');
if (addToCartForm) {
  addToCartForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const chosen = document.querySelector('input[name="variant_id"]:checked');
    const btn = addToCartForm.querySelector('button[type="submit"]');
    if (!chosen || chosen.dataset.out === '1') return false;

    const qty = Number(addToCartForm.querySelector('#qty')?.value || 1);
    const customSize = sizeNumber?.value.trim() || '';
    if (sizeNumber) {
      const value = Number(customSize);
      if (customSize === '' || !Number.isFinite(value)
          || value < sizeMin - 0.001 || value > sizeMax + 0.001) {
        if (sizeError) {
          sizeError.textContent = 'Enter a size between ' + sizeMin + ' and ' + sizeMax + ' inches.';
          sizeError.classList.remove('hidden');
        }
        sizeNumber.focus();
        return false;
      }
    }
    const csrf = getCsrfToken();

    btn.disabled = true;
    const prevLabel = btn.textContent;
    btn.textContent = 'Adding\u2026';
    hideCartMessage();

    const postAdd = (token) => fetch('/api/cart/add', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      credentials: 'same-origin',
      body: JSON.stringify({ variant_id: Number(chosen.value), qty, custom_size: customSize }),
    });

    try {
      let res = await postAdd(csrf);
      let data = await res.json().catch(() => ({}));
      // Session expired, or the token rotated when another tab signed in:
      // fetch a fresh token and retry once instead of dead-ending on "Failed".
      if (!res.ok && (res.status === 419 || res.status === 403)) {
        const token = await refreshCsrfToken();
        if (token) {
          storeCsrfToken(token);
          res = await postAdd(token);
          data = await res.json().catch(() => ({}));
        }
      }
      if (res.ok && data.ok) {
        const counter = document.querySelector('[data-cart-count]');
        if (counter && typeof data.count === 'number') counter.textContent = String(data.count);
        btn.textContent = 'Added \u2713';
        setTimeout(() => { btn.textContent = prevLabel; btn.disabled = false; }, 1200);
      } else {
        showCartMessage(data.error || 'Could not add to cart. Please try again.');
        btn.textContent = prevLabel;
        btn.disabled = false;
      }
    } catch {
      showCartMessage('Network error. Check your connection and try again.');
      btn.textContent = prevLabel;
      btn.disabled = false;
    }
  });
}

// Cart page: remove lines and change quantities through the JSON API, with a
// no-JS fallback already handled by the server-side forms.
const cartLines = document.getElementById('cart-lines');
if (cartLines) {
  const cartNotice = document.querySelector('[data-cart-notice]');

  const showCartNotice = (message) => {
    if (!cartNotice) return;
    cartNotice.textContent = message;
    cartNotice.classList.remove('hidden');
  };

  const hideCartNotice = () => {
    if (!cartNotice) return;
    cartNotice.textContent = '';
    cartNotice.classList.add('hidden');
  };

  const syncCartSummary = (data) => {
    const counter = document.querySelector('[data-cart-count]');
    if (counter && typeof data.count === 'number') counter.textContent = String(data.count);
    const subtotal = document.querySelector('[data-subtotal]');
    if (subtotal && typeof data.subtotal === 'string') subtotal.textContent = data.subtotal;
  };

  const cartApiPost = async (action, payload) => {
    const send = (token) => fetch('/api/cart/' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });
    let res = await send(getCsrfToken());
    let data = await res.json().catch(() => ({}));
    // Same stale-token recovery as add-to-cart.
    if (!res.ok && (res.status === 403 || res.status === 419)) {
      const token = await refreshCsrfToken();
      if (token) {
        storeCsrfToken(token);
        res = await send(token);
        data = await res.json().catch(() => ({}));
      }
    }
    return { res, data };
  };

  cartLines.querySelectorAll('[data-remove-form]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('[data-remove-line]');
      if (!btn || btn.disabled) return;
      btn.disabled = true;
      hideCartNotice();

      const lineId = Number(btn.dataset.removeLine || 0);
      const { res, data } = await cartApiPost('remove', { cart_item_id: lineId });
      if (res.ok && data.ok) {
        btn.closest('li')?.remove();
        syncCartSummary(data);
        if (!cartLines.querySelector('li')) {
          window.location.reload(); // show the server-rendered empty state
        }
      } else {
        btn.disabled = false;
        showCartNotice(data.error || 'Could not remove this item. Please try again.');
      }
    });
  });

  cartLines.querySelectorAll('[data-qty-input]').forEach((input) => {
    input.addEventListener('change', async () => {
      const line = input.closest('li');
      const lineId = Number(line?.dataset.lineId || 0);
      const qty = Math.max(1, Math.min(99, Number(input.value) || 1));
      if (!lineId) return;
      const previous = input.defaultValue;
      input.value = String(qty);
      input.disabled = true;
      hideCartNotice();

      const { res, data } = await cartApiPost('update', { cart_item_id: lineId, qty });
      if (res.ok && data.ok) {
        // Line totals are server-side; refresh so every number stays correct.
        window.location.reload();
      } else {
        input.value = previous;
        input.disabled = false;
        showCartNotice(data.error || 'Could not update the quantity. Please try again.');
      }
    });
  });
}

// Storefront mobile drawer.
const menu = document.querySelector('[data-store-menu]');
const menuBackdrop = document.querySelector('[data-store-menu-backdrop]');
function setMenu(open) {
  if (!menu || !menuBackdrop) return;
  menu.classList.toggle('-translate-x-full', !open);
  menuBackdrop.classList.toggle('hidden', !open);
  document.body.classList.toggle('overflow-hidden', open);
}
document.querySelector('[data-store-menu-open]')?.addEventListener('click', () => setMenu(true));
document.querySelector('[data-store-menu-close]')?.addEventListener('click', () => setMenu(false));
menuBackdrop?.addEventListener('click', () => setMenu(false));
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setMenu(false); });

// Hero slider: auto-advance + dots. Disabled under prefers-reduced-motion.
const hero = document.querySelector('[data-hero]');
if (hero && hero.querySelectorAll('[data-hero-slide]').length > 1) {
  const slides = Array.from(hero.querySelectorAll('[data-hero-slide]'));
  const dots = Array.from(hero.querySelectorAll('[data-hero-dot]'));
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let current = 0;
  let timer = null;

  function show(idx) {
    current = idx % slides.length;
    slides.forEach((el, i) => el.classList.toggle('hidden', i !== current));
    dots.forEach((el, i) => {
      el.classList.toggle('bg-ink', i === current);
      el.classList.toggle('bg-ink/25', i !== current);
    });
  }
  function play() {
    if (reduced) return;
    timer = window.setInterval(() => show(current + 1), 6000);
  }
  function stop() { if (timer) { window.clearInterval(timer); timer = null; } }

  dots.forEach((dot, i) => dot.addEventListener('click', () => { stop(); show(i); play(); }));
  hero.addEventListener('mouseenter', stop);
  hero.addEventListener('mouseleave', () => { stop(); play(); });
  document.addEventListener('visibilitychange', () => (document.hidden ? stop() : play()));
  play();
}

// Header search: expand/collapse the search bar beside the cart button.
const searchToggle = document.querySelector('[data-search-toggle]');
const searchBar = document.querySelector('[data-search-bar]');
if (searchToggle && searchBar) {
  searchToggle.addEventListener('click', () => {
    const open = searchBar.classList.toggle('hidden') === false;
    searchToggle.setAttribute('aria-expanded', String(open));
    if (open) searchBar.querySelector('input')?.focus();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !searchBar.classList.contains('hidden')) {
      searchBar.classList.add('hidden');
      searchToggle.setAttribute('aria-expanded', 'false');
    }
  });
}

// Product sharing: native share sheet, clipboard copy, and social links.
const shareStatus = document.querySelector('[data-share-status]');
let shareTimer = null;

function showShareStatus(message) {
  if (!shareStatus) return;
  shareStatus.textContent = message;
  shareStatus.classList.remove('hidden');
  if (shareTimer) window.clearTimeout(shareTimer);
  shareTimer = window.setTimeout(() => shareStatus.classList.add('hidden'), 2600);
}

function legacyCopy(text) {
  const area = document.createElement('textarea');
  area.value = text;
  area.setAttribute('readonly', '');
  area.className = 'sr-only';
  document.body.appendChild(area);
  area.select();
  let ok = false;
  try { ok = document.execCommand('copy'); } catch { ok = false; }
  area.remove();
  return ok;
}

async function copyShareLink(url) {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(url);
      showShareStatus('Link copied.');
      return;
    }
  } catch { /* fall through to the legacy path */ }
  showShareStatus(legacyCopy(url) ? 'Link copied.' : 'Could not copy the link.');
}

const copyButton = document.querySelector('[data-share-copy]');
copyButton?.addEventListener('click', () => copyShareLink(copyButton.dataset.shareUrl || location.href));

const nativeShareButton = document.querySelector('[data-share-native]');
nativeShareButton?.addEventListener('click', async () => {
  const url = nativeShareButton.dataset.shareUrl || location.href;
  if (navigator.share) {
    try {
      await navigator.share({
        title: nativeShareButton.dataset.shareTitle || document.title,
        text: nativeShareButton.dataset.shareText || '',
        url,
      });
      return;
    } catch { /* user cancelled or share failed */ }
  }
  copyShareLink(url);
});
