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

function refreshVariant() {
  const chosen = document.querySelector('input[name="variant_id"]:checked');
  if (!chosen || chosen.dataset.out === '1') return;
  const v = variantsData.find((x) => x.id === Number(chosen.value));
  if (v && priceEl) priceEl.textContent = 'Rs ' + v.price.toLocaleString('en-PK');
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
    const csrf = addToCartForm.querySelector('input[name="_csrf"]')?.value;

    btn.disabled = true;
    const prevLabel = btn.textContent;
    btn.textContent = 'Adding\u2026';

    try {
      const res = await fetch('/api/cart/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        credentials: 'same-origin',
        body: JSON.stringify({ variant_id: Number(chosen.value), qty }),
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
