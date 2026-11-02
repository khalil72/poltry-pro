(function () {
  'use strict';
  document.documentElement.classList.add('js');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Header turns solid once you scroll off the hero
  var head = document.getElementById('siteHead');
  function onScroll() { if (head) head.classList.toggle('solid', window.scrollY > 40); }
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  var nav = document.getElementById('mainNav');
  if (nav) nav.addEventListener('show.bs.collapse', function () { head.classList.add('solid'); });

  function fmt(n) { return Math.round(n).toLocaleString(); }

  function countUp(el) {
    var target = parseInt(el.dataset.count, 10) || 0;
    if (reduce) { el.textContent = fmt(target); return; }
    var start = null;
    function step(t) {
      if (start === null) start = t;
      var p = Math.min((t - start) / 1300, 1);
      el.textContent = fmt(target * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  var targets = document.querySelectorAll('[data-count],[data-reveal],[data-hatch]');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target;
        if (el.dataset.count !== undefined) countUp(el);
        if (el.hasAttribute('data-reveal')) el.classList.add('in');
        if (el.hasAttribute('data-hatch')) el.classList.add('hatched');
        io.unobserve(el);
      });
    }, { threshold: 0.1 });
    targets.forEach(function (t) { io.observe(t); });
  } else {
    targets.forEach(function (t) { t.classList.add('in', 'hatched'); });
  }

  // Gallery filter and lightbox
  var filter = document.querySelector('[data-gallery-filter]');
  if (filter) filter.addEventListener('click', function (ev) {
    var b = ev.target.closest('button');
    if (!b) return;
    filter.querySelectorAll('button').forEach(function (x) { x.classList.toggle('active', x === b); });
    document.querySelectorAll('#galleryGrid figure').forEach(function (f) {
      f.style.display = !b.dataset.cat || f.dataset.cat === b.dataset.cat ? '' : 'none';
    });
  });
  var lb = document.getElementById('lightbox');
  if (lb) document.addEventListener('click', function (ev) {
    var b = ev.target.closest('.gallery-open');
    if (!b) return;
    var img = document.getElementById('lightboxImg');
    img.src = b.dataset.full; img.alt = b.dataset.title;
    new bootstrap.Modal(lb).show();
  });
})();
