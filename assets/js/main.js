(() => {
  'use strict';

  const root = document.documentElement;
  root.classList.add('js');

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  // SVG filters inside backdrop-filter only render in Chromium — enable true refraction there.
  const brands = (navigator.userAgentData && navigator.userAgentData.brands) || [];
  if (brands.some((b) => /Chromium/i.test(b.brand))) root.classList.add('liquid');

  if (reduceMotion) {
    document.querySelectorAll('svg').forEach((svg) => svg.pauseAnimations && svg.pauseAnimations());
  }

  /* ---------- Toast ---------- */
  const toastEl = document.querySelector('.toast');
  const toastMsg = toastEl.querySelector('.toast__msg');
  let toastTimer;
  const toast = (message) => {
    toastMsg.textContent = message;
    toastEl.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('is-visible'), 3400);
  };

  /* ---------- Navigation ---------- */
  const nav = document.querySelector('.nav');
  const onScroll = () => nav.classList.toggle('is-scrolled', window.scrollY > 40);
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  const menuToggle = document.querySelector('.nav__toggle');
  const mobileMenu = document.getElementById('mobile-menu');
  const setMenu = (open) => {
    document.body.classList.toggle('menu-open', open);
    menuToggle.setAttribute('aria-expanded', String(open));
    menuToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  };
  menuToggle.addEventListener('click', () => setMenu(menuToggle.getAttribute('aria-expanded') !== 'true'));
  mobileMenu.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => setMenu(false)));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.body.classList.contains('menu-open')) {
      setMenu(false);
      menuToggle.focus();
    }
  });

  /* ---------- Reveal on scroll ---------- */
  const revealIO = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-in');
      revealIO.unobserve(entry.target);
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
  document.querySelectorAll('[data-reveal]').forEach((el) => revealIO.observe(el));

  /* ---------- Glass specular light follows the pointer ---------- */
  if (finePointer) {
    document.addEventListener('pointermove', (e) => {
      const glass = e.target.closest && e.target.closest('.glass');
      if (!glass) return;
      const r = glass.getBoundingClientRect();
      glass.style.setProperty('--mx', `${e.clientX - r.left}px`);
      glass.style.setProperty('--my', `${e.clientY - r.top}px`);
    }, { passive: true });
  }

  /* ---------- Hero parallax & card tilt ---------- */
  if (finePointer && !reduceMotion) {
    const hero = document.querySelector('.hero');
    const showcase = document.querySelector('.showcase');
    hero.addEventListener('pointermove', (e) => {
      const r = hero.getBoundingClientRect();
      showcase.style.setProperty('--px', ((e.clientX - r.left) / r.width - 0.5).toFixed(3));
      showcase.style.setProperty('--py', ((e.clientY - r.top) / r.height - 0.5).toFixed(3));
    });
    hero.addEventListener('pointerleave', () => {
      showcase.style.setProperty('--px', 0);
      showcase.style.setProperty('--py', 0);
    });

    document.querySelectorAll('[data-tilt]').forEach((el) => {
      const max = parseFloat(el.dataset.tilt) || 5;
      let frame = 0;
      el.addEventListener('pointermove', (e) => {
        const r = el.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - 0.5;
        const y = (e.clientY - r.top) / r.height - 0.5;
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
          el.style.transition = 'transform .25s ease-out, box-shadow .8s cubic-bezier(.22,1,.36,1)';
          el.style.transform = `perspective(1100px) rotateX(${(-y * max).toFixed(2)}deg) rotateY(${(x * max).toFixed(2)}deg) translateY(-4px)`;
        });
      });
      el.addEventListener('pointerleave', () => {
        cancelAnimationFrame(frame);
        el.style.transition = '';
        el.style.transform = '';
      });
    });
  }

  /* ---------- Collection filters ---------- */
  const filterButtons = document.querySelectorAll('[data-filter]');
  const cards = document.querySelectorAll('.card[data-category]');
  const filterStatus = document.querySelector('[data-filter-status]');
  filterButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const filter = button.dataset.filter;
      let shown = 0;
      filterButtons.forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
      cards.forEach((card) => {
        const match = filter === 'all' || card.dataset.category === filter;
        const wasHidden = card.hidden;
        card.hidden = !match;
        if (match) {
          shown += 1;
          if (wasHidden) {
            card.classList.remove('is-in');
            void card.offsetWidth; // restart the reveal transition
            card.classList.add('is-in');
          }
        }
      });
      filterStatus.textContent = `Showing ${shown} ${shown === 1 ? 'piece' : 'pieces'}`;
    });
  });

  /* ---------- Bag & wishlist ---------- */
  const bag = document.querySelector('.bag');
  const bagCount = bag.querySelector('[data-bag-count]');
  let bagItems = 0;

  document.addEventListener('click', (e) => {
    const addButton = e.target.closest('[data-add]');
    if (addButton) {
      bagItems += 1;
      bagCount.textContent = bagItems;
      bag.classList.add('has-items');
      bag.classList.remove('bump');
      void bag.offsetWidth;
      bag.classList.add('bump');
      addButton.classList.add('is-added');
      setTimeout(() => addButton.classList.remove('is-added'), 1400);
      toast(`${addButton.dataset.add} has been placed in your bag`);
      return;
    }

    const wishButton = e.target.closest('[data-wish]');
    if (wishButton) {
      const saved = wishButton.getAttribute('aria-pressed') !== 'true';
      wishButton.setAttribute('aria-pressed', String(saved));
      toast(saved ? `${wishButton.dataset.wish} saved to your wishlist` : `${wishButton.dataset.wish} removed from your wishlist`);
    }
  });

  /* ---------- Story chapters ---------- */
  const chapters = [...document.querySelectorAll('.chapter')];
  const layers = [...document.querySelectorAll('.stage-layer')];
  const stageIndex = document.querySelector('.stage-index');
  const stageTrack = document.querySelector('.stage-track');
  const setChapter = (index) => {
    chapters.forEach((c, i) => c.classList.toggle('is-active', i === index));
    layers.forEach((l, i) => l.classList.toggle('is-active', i === index));
    stageIndex.textContent = String(index + 1).padStart(2, '0');
    stageTrack.style.setProperty('--progress', (index + 1) / chapters.length);
  };
  const storyIO = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) setChapter(chapters.indexOf(entry.target));
    });
  }, { rootMargin: '-45% 0px -45% 0px' });
  chapters.forEach((c) => storyIO.observe(c));

  /* ---------- Counters ---------- */
  const formatNumber = (n) => n.toLocaleString('en-US');
  const countIO = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      countIO.unobserve(entry.target);
      const el = entry.target;
      const end = Number(el.dataset.count);
      if (reduceMotion) { el.textContent = formatNumber(end); return; }
      const duration = 2000;
      const start = performance.now();
      const tick = (now) => {
        const p = Math.min((now - start) / duration, 1);
        el.textContent = formatNumber(Math.round(end * (1 - Math.pow(1 - p, 4))));
        if (p < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    });
  }, { threshold: 0.6 });
  document.querySelectorAll('[data-count]').forEach((el) => countIO.observe(el));

  /* ---------- Membership request ---------- */
  const form = document.getElementById('invite-form');
  const fields = [form.elements.name, form.elements.email];
  const validate = (input) => {
    const valid = input.value.trim() !== '' && input.checkValidity();
    input.closest('.field').classList.toggle('is-invalid', !valid);
    input.setAttribute('aria-invalid', String(!valid));
    return valid;
  };
  fields.forEach((input) => {
    input.addEventListener('blur', () => { if (input.value) validate(input); });
    input.addEventListener('input', () => {
      if (input.closest('.field').classList.contains('is-invalid')) validate(input);
    });
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const invalid = fields.filter((input) => !validate(input));
    if (invalid.length) { invalid[0].focus(); return; }

    const submit = form.querySelector('button[type="submit"]');
    const label = submit.querySelector('.btn__label');
    submit.disabled = true;
    label.textContent = 'Submitting your request…';

    setTimeout(() => {
      const firstName = form.elements.name.value.trim().split(/\s+/)[0];
      form.querySelector('[data-success-name]').textContent = firstName ? `, ${firstName}` : '';
      form.querySelector('[data-success-tier]').textContent = form.elements.tier.value;
      form.classList.add('is-sent');
      toast('Your invitation request has been received');
    }, 1400);
  });

  /* ---------- Footer year ---------- */
  document.querySelector('[data-year]').textContent = new Date().getFullYear();
})();
