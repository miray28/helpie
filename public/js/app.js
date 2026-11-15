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
      if (file.size > 5 * 1024 * 1024) {
        window.alert('Images must be 5 MB or smaller.');
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
})();
