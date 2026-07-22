(function () {
  var tocLinks = document.querySelectorAll('.toc a[href^="#"]');
  if (!tocLinks.length) {
    return;
  }

  var headings = Array.prototype.map.call(tocLinks, function (link) {
    return document.getElementById(link.getAttribute('href').slice(1));
  }).filter(Boolean);

  if (!headings.length) {
    return;
  }

  // Matches .prose h2/h3's scroll-margin-top, so the active link flips
  // right around where an anchor jump would land the heading.
  var offset = 80;
  var ticking = false;

  function setActive() {
    var current = headings[0];

    for (var i = 0; i < headings.length; i++) {
      if (headings[i].getBoundingClientRect().top - offset <= 0) {
        current = headings[i];
      } else {
        break;
      }
    }

    tocLinks.forEach(function (link) {
      if (link.getAttribute('href') === '#' + current.id) {
        link.setAttribute('aria-current', 'location');
      } else {
        link.removeAttribute('aria-current');
      }
    });

    ticking = false;
  }

  function onScroll() {
    if (!ticking) {
      requestAnimationFrame(setActive);
      ticking = true;
    }
  }

  document.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  setActive();
})();
