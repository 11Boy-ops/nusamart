(() => {
  'use strict';

  const toast = document.getElementById('toast');
  let toastTimer;

  function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('is-visible');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 3000);
  }

  const menuToggle = document.querySelector('.mobile-menu-toggle');
  const mobileMenu = document.getElementById('mobile-menu');
  if (menuToggle && mobileMenu) {
    menuToggle.addEventListener('click', () => {
      const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
      menuToggle.setAttribute('aria-expanded', String(!isExpanded));
      mobileMenu.hidden = isExpanded;
    });
    mobileMenu.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        mobileMenu.hidden = true;
        menuToggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
      const inputId = button.getAttribute('data-toggle-password');
      const input = inputId ? document.getElementById(inputId) : null;
      if (!input) return;
      const shouldShow = input.type === 'password';
      input.type = shouldShow ? 'text' : 'password';
      button.setAttribute('aria-label', shouldShow ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });
  });

  const demoButton = document.getElementById('fill-demo');
  if (demoButton) {
    demoButton.addEventListener('click', () => {
      const email = document.getElementById('email');
      const password = document.getElementById('password');
      if (email && password) {
        email.value = 'demo@nusamart.id';
        password.value = 'Belanja123!';
        email.focus();
        showToast('Akun demo sudah diisi. Klik “Masuk ke akun” untuk mencoba.');
      }
    });
  }

  const confirmationInput = document.getElementById('confirm_password');
  if (confirmationInput) {
    confirmationInput.addEventListener('input', () => confirmationInput.setCustomValidity(''));
  }

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (form.matches('.auth-form')) {
        const password = form.querySelector('input[name="password"]');
        const confirmPassword = form.querySelector('input[name="confirm_password"]');
        if (confirmPassword && password) {
          if (password.value !== confirmPassword.value) {
            event.preventDefault();
            confirmPassword.setCustomValidity('Konfirmasi kata sandi belum sama.');
            confirmPassword.reportValidity();
          } else {
            confirmPassword.setCustomValidity('');
          }
        }
      }
    });
  });

  document.querySelectorAll('img').forEach((image) => {
    image.addEventListener('error', () => {
      if (image.dataset.fallbackApplied) return;
      image.dataset.fallbackApplied = 'true';
      if (image.classList.contains('product-image')) {
        image.src = 'assets/img/product-placeholder.svg';
      } else if (image.classList.contains('hero-photo') || image.classList.contains('auth-showcase-image') || image.closest('.editorial-image-main')) {
        image.style.display = 'none';
      }
    }, { once: true });
  });

  const searchInputs = document.querySelectorAll('input[type="search"]');
  searchInputs.forEach((input) => {
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') input.value = '';
    });
  });


  // Kontrol jumlah produk pada halaman detail dan keranjang.
  document.querySelectorAll('[data-quantity-step]').forEach((button) => {
    button.addEventListener('click', () => {
      const targetId = button.getAttribute('data-quantity-target');
      const input = targetId ? document.getElementById(targetId) : button.parentElement?.querySelector('input[type="number"]');
      if (!input) return;
      const step = Number(button.getAttribute('data-quantity-step') || '0');
      const min = input.min === '' ? 1 : Number(input.min);
      const max = input.max === '' ? 99 : Number(input.max);
      const value = Number(input.value || min);
      input.value = String(Math.max(min, Math.min(max, value + step)));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

  // Salin nomor pesanan ke clipboard.
  document.querySelectorAll('[data-copy-text]').forEach((button) => {
    button.addEventListener('click', async () => {
      const text = button.getAttribute('data-copy-text') || '';
      try {
        await navigator.clipboard.writeText(text);
        showToast('Nomor pesanan berhasil disalin.');
      } catch (error) {
        const helper = document.createElement('textarea');
        helper.value = text;
        helper.setAttribute('readonly', '');
        helper.style.position = 'absolute';
        helper.style.left = '-9999px';
        document.body.appendChild(helper);
        helper.select();
        const copied = document.execCommand('copy');
        helper.remove();
        showToast(copied ? 'Nomor pesanan berhasil disalin.' : 'Silakan salin nomor pesanan secara manual.');
      }
    });
  });

  // Smooth scroll for in-page navigation; respects OS reduced-motion setting via CSS.
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', (event) => {
      const targetId = anchor.getAttribute('href');
      if (!targetId || targetId === '#') return;
      const target = document.querySelector(targetId);
      if (!target) return;
      event.preventDefault();
      target.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    });
  });
})();
