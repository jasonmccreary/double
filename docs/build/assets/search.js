(function () {
  var config = window.__ALGOLIA__;
  if (!config || !config.appId || !config.apiKey || !config.indexName || typeof window.algoliasearch !== 'function') {
    return;
  }

  var index = window.algoliasearch(config.appId, config.apiKey).initIndex(config.indexName);
  var trigger = document.getElementById('search-trigger');
  if (!trigger) {
    return;
  }

  var backdrop, input, results, empty, hits = [], selectedIndex = -1, debounceId;

  function buildDialog() {
    backdrop = document.createElement('div');
    backdrop.className = 'search-dialog-backdrop';
    backdrop.innerHTML =
      '<div class="search-dialog" role="dialog" aria-modal="true" aria-label="Search documentation">' +
        '<div class="search-dialog-form">' +
          '<svg class="search-dialog-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M17.25 17.25l-3.75-3.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>' +
          '<input type="text" class="search-dialog-input" placeholder="Search documentation…" aria-label="Search documentation" autocomplete="off" spellcheck="false">' +
        '</div>' +
        '<div class="search-dialog-results" role="listbox"></div>' +
        '<p class="search-dialog-empty"></p>' +
        '<div class="search-dialog-footer">' +
          '<span>↑↓ Navigate</span><span>↵ Select</span><span>Esc Close</span>' +
        '</div>' +
      '</div>';
    document.body.appendChild(backdrop);

    input = backdrop.querySelector('.search-dialog-input');
    results = backdrop.querySelector('.search-dialog-results');
    empty = backdrop.querySelector('.search-dialog-empty');

    backdrop.addEventListener('mousedown', function (e) {
      if (e.target === backdrop) close();
    });
    input.addEventListener('input', onInput);
    input.addEventListener('keydown', onKeydown);
  }

  function onInput() {
    clearTimeout(debounceId);
    var query = input.value.trim();

    if (!query) {
      showEmpty('Start typing to search the docs.');
      return;
    }

    debounceId = setTimeout(function () {
      index.search(query, { hitsPerPage: 8 }).then(function (res) {
        renderHits(res.hits, query);
      });
    }, 150);
  }

  function showEmpty(message) {
    hits = [];
    selectedIndex = -1;
    results.innerHTML = '';
    empty.textContent = message;
    empty.hidden = false;
  }

  function renderHits(newHits, query) {
    hits = newHits;
    results.innerHTML = '';

    if (!hits.length) {
      showEmpty('No results for “' + query + '”.');
      return;
    }

    empty.hidden = true;
    selectedIndex = 0;

    hits.forEach(function (hit, i) {
      var a = document.createElement('a');
      a.className = 'search-result';
      a.href = hit.url || hit.permalink || '#';
      a.setAttribute('role', 'option');

      var path = document.createElement('span');
      path.className = 'search-result-path';
      path.textContent = breadcrumb(hit);

      var title = document.createElement('span');
      title.className = 'search-result-title';
      title.innerHTML = highlightedTitle(hit);

      a.appendChild(path);
      a.appendChild(title);
      a.addEventListener('mouseenter', function () {
        select(i);
      });
      results.appendChild(a);
    });

    select(0);
  }

  function breadcrumb(hit) {
    var h = hit.hierarchy || {};
    return [h.lvl0, h.lvl1].filter(Boolean).join(' › ') || hit.title || '';
  }

  function highlightedTitle(hit) {
    var hr = hit._highlightResult || {};
    var field = (hr.hierarchy && (hr.hierarchy.lvl2 || hr.hierarchy.lvl1)) || hr.title || hr.content;
    if (field && field.value) {
      return field.value;
    }
    return escapeHtml(hit.title || hit.name || hit.content || hit.url || 'Untitled');
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function select(i) {
    if (!results.children.length) return;
    selectedIndex = i;
    Array.prototype.forEach.call(results.children, function (el, idx) {
      el.setAttribute('aria-selected', idx === selectedIndex ? 'true' : 'false');
    });
    var el = results.children[selectedIndex];
    if (el) el.scrollIntoView({ block: 'nearest' });
  }

  function onKeydown(e) {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (hits.length) select((selectedIndex + 1) % hits.length);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (hits.length) select((selectedIndex - 1 + hits.length) % hits.length);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      var el = results.children[selectedIndex];
      if (el) window.location.href = el.href;
    } else if (e.key === 'Escape') {
      close();
    }
  }

  function open() {
    if (!backdrop) buildDialog();
    backdrop.classList.add('is-open');
    document.body.classList.add('search-open');
    input.value = '';
    input.focus();
    showEmpty('Start typing to search the docs.');
  }

  function close() {
    if (!backdrop || !backdrop.classList.contains('is-open')) return;
    backdrop.classList.remove('is-open');
    document.body.classList.remove('search-open');
    trigger.focus();
  }

  trigger.addEventListener('click', open);

  document.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      if (backdrop && backdrop.classList.contains('is-open')) {
        close();
      } else {
        open();
      }
    }
  });
})();
