'use strict';
(function () {
  const csrf = document.querySelector('meta[name="csrf-token"]').content;

  // Confirmation prompts for destructive forms.
  document.addEventListener('submit', (event) => {
    const message = event.target.getAttribute && event.target.getAttribute('data-confirm');
    if (message && !window.confirm(message)) event.preventDefault();
  });

  // Save / unsave without a page reload (falls back to a normal form post).
  document.querySelectorAll('form[data-save]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('button');
      button.disabled = true;
      try {
        const res = await fetch(form.action, {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' },
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        button.setAttribute('aria-pressed', String(data.is_saved));
        const count = form.querySelector('[data-save-count]');
        if (count) count.textContent = data.save_count;
        const label = form.querySelector('[data-save-label]');
        if (label) label.textContent = data.is_saved ? 'Saved' : 'Save';
      } catch (err) {
        form.submit();
      } finally {
        button.disabled = false;
      }
    });
  });

  // Live preview for image inputs.
  document.querySelectorAll('[data-preview-input]').forEach((input) => {
    input.addEventListener('change', () => {
      const preview = input.closest('.field').querySelector('[data-preview]');
      const file = input.files && input.files[0];
      if (!preview || !file) return;
      if (file.size > 4 * 1024 * 1024) {
        window.alert('Images must be 4 MB or smaller.');
        input.value = '';
        return;
      }
      preview.src = URL.createObjectURL(file);
      preview.hidden = false;
    });
  });

  // Post form: condition and expiry only apply to donations.
  const postForm = document.querySelector('[data-post-form]');
  if (postForm) {
    const sync = () => {
      const isDonation = postForm.querySelector('input[name="type"]:checked').value === 'donation';
      postForm.querySelectorAll('[data-donation-only]').forEach((el) => { el.hidden = !isDonation; });
    };
    postForm.querySelectorAll('input[name="type"]').forEach((radio) => radio.addEventListener('change', sync));
    sync();
  }

  // Close the account menu when clicking elsewhere or pressing Escape.
  const menu = document.querySelector('details.menu');
  if (menu) {
    document.addEventListener('click', (event) => { if (!menu.contains(event.target)) menu.open = false; });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') menu.open = false; });
  }

  // Landing page: reveal sections as they scroll into view and count up the stats.
  const revealable = document.querySelectorAll('[data-reveal]');
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (revealable.length && 'IntersectionObserver' in window && !reduced) {
    document.documentElement.classList.add('js-anim');
    const countUp = (el) => {
      const target = Number(el.dataset.count) || 0;
      const start = performance.now();
      const duration = 1100;
      const tick = (now) => {
        const t = Math.min(1, (now - start) / duration);
        el.textContent = Math.round(target * (1 - Math.pow(1 - t, 3))).toLocaleString();
        if (t < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    };
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('in');
        entry.target.querySelectorAll('[data-count]').forEach(countUp);
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    revealable.forEach((el) => observer.observe(el));
  }
})();
