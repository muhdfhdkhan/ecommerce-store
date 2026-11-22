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
    const csrf = addToCartForm.querySelector('input[name="_csrf"]')?.value;

    btn.disabled = true;
    const prevLabel = btn.textContent;
    btn.textContent = 'Adding\u2026';

    try {
      const res = await fetch('/api/cart/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        credentials: 'same-origin',
        body: JSON.stringify({ variant_id: Number(chosen.value), qty, custom_size: customSize }),
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok && data.ok) {
        const counter = document.querySelector('[data-cart-count]');
        if (counter && typeof data.count === 'number') counter.textContent = String(data.count);
        btn.textContent = 'Added \u2713';
        setTimeout(() => { btn.textContent = prevLabel; btn.disabled = false; }, 1200);
      } else {
        btn.textContent = data.error || 'Failed';
        setTimeout(() => { btn.textContent = prevLabel; btn.disabled = false; }, 1600);
      }
    } catch {
      btn.textContent = 'Network error';
      setTimeout(() => { btn.textContent = prevLabel; btn.disabled = false; }, 1600);
    }
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
