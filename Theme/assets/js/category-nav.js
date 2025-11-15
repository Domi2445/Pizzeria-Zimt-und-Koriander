/**
 * Sticky category navigation — one item per category-section.
 * Exposes window.ThemeCategoryNav.onFilterUpdate() that gets called
 * after client-side filtering so the nav can e.g. hide empty pills.
 */
(function(){
  'use strict';

  var NAV_ID = 'theme-category-nav';
  var NAV_ITEM_CLASS = 'theme-category-nav-item';
  var SCROLL_OFFSET = (window.ThemeCategoryNav && ThemeCategoryNav.offset) ? parseInt(ThemeCategoryNav.offset,10) : 88;
  var HEADLINE_SELECTORS = 'h2, h3, .section-title, .woocommerce-loop-category__title';

  function qsAll(sel, ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(sel)); }
  function elVisible(el){ if(!el) return false; var r = el.getBoundingClientRect(); return !(r.width === 0 && r.height === 0); }

  function collectSections(){
    // Find sections by looking for elements with id starting with "kategorie-" (we set that in template)
    var secs = qsAll('section[id^="kategorie-"]');
    // fallback: find headings and derive sections
    if (!secs.length) {
      var heads = qsAll(HEADLINE_SELECTORS).filter(function(h){ return (h.textContent||'').trim() && elVisible(h); });
      var unique = [];
      heads.forEach(function(h){ var node = h; var picked = null; var depth = 0; while(node && node !== document.body && depth < 8){ if(node.tagName && (node.tagName.toLowerCase()==='section' || node.tagName.toLowerCase()==='article')){ picked = node; break; } var cls = node.className || ''; if(typeof cls === 'string' && /(category|section|products|grid)/i.test(cls)){ picked = node; break; } node = node.parentElement; depth++; } if (!picked) picked = h; if (unique.indexOf(picked) === -1) unique.push(picked); }); secs = unique;
    }
    return secs;
  }

  function buildNav(){
    if (document.getElementById(NAV_ID)) return;
    var sections = collectSections();
    if (!sections.length) return;

    var nav = document.createElement('nav'); nav.id = NAV_ID; nav.className = 'theme-category-nav';
    var inner = document.createElement('div'); inner.className = 'theme-category-nav-inner';

    sections.forEach(function(s){
      var titleEl = s.querySelector(HEADLINE_SELECTORS) || s.querySelector('h2,h3');
      var title = titleEl ? (titleEl.textContent||'').trim() : (s.getAttribute('data-title') || s.getAttribute('data-cat-name') || 'Kategorie');
      if (!title) return;
      if (!s.id) {
        var slug = title.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^\-+|\-+$/g,'');
        var id = 'kategorie-' + slug;
        var tryId = id; var i = 1;
        while (document.getElementById(tryId)) { tryId = id + '-' + (i++); }
        try { s.id = tryId; } catch(e){}
      }
      var btn = document.createElement('button');
      btn.className = NAV_ITEM_CLASS;
      btn.type = 'button';
      btn.setAttribute('data-target', s.id);
      btn.textContent = title;
      inner.appendChild(btn);
    });

    nav.appendChild(inner);
    var left = document.createElement('button'); left.className = 'theme-category-nav-arrow left'; left.type='button'; left.innerHTML='◀';
    var right = document.createElement('button'); right.className = 'theme-category-nav-arrow right'; right.type='button'; right.innerHTML='▶';
    nav.appendChild(left); nav.appendChild(right);

    var hero = document.querySelector('.lief-hero, .page-hero, header');
    var main = document.querySelector('main') || document.body;
    if (hero && hero.parentNode) hero.parentNode.insertBefore(nav, hero.nextSibling);
    else if (main && main.parentNode) main.parentNode.insertBefore(nav, main);
    else document.body.insertBefore(nav, document.body.firstChild);

    inner.addEventListener('click', function(e){
      var btn = e.target.closest('.' + NAV_ITEM_CLASS);
      if (!btn) return;
      var id = btn.getAttribute('data-target');
      var el = document.getElementById(id);
      if (!el) return;
      var rect = el.getBoundingClientRect();
      var top = window.pageYOffset + rect.top - SCROLL_OFFSET;
      window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
      setActiveById(id);
    });
    left.addEventListener('click', function(){ inner.scrollBy({left:-220, behavior:'smooth'}); });
    right.addEventListener('click', function(){ inner.scrollBy({left:220, behavior:'smooth'}); });

    // scroll spy
    var secs = Array.prototype.map.call(inner.querySelectorAll('.' + NAV_ITEM_CLASS), function(btn){ return document.getElementById(btn.getAttribute('data-target')); });

    var ticking = false;
    window.addEventListener('scroll', function(){
      if (!ticking) {
        window.requestAnimationFrame(function(){ updateActiveByScroll(); ticking = false; });
        ticking = true;
      }
    }, { passive: true });
    window.addEventListener('resize', updateActiveByScroll);

    function updateActiveByScroll(){
      var offset = SCROLL_OFFSET + 8;
      var cur = null;
      for (var i=0;i<secs.length;i++){
        var el = secs[i];
        if (!el) continue;
        var top = el.getBoundingClientRect().top + window.pageYOffset;
        if (top <= window.pageYOffset + offset) cur = el.id;
        else break;
      }
      if (!cur && secs.length && secs[0]) cur = secs[0].id;
      if (cur) setActiveById(cur);
    }
    function setActiveById(id){
      var nav = document.getElementById(NAV_ID);
      if (!nav) return;
      var items = nav.querySelectorAll('.' + NAV_ITEM_CLASS);
      Array.prototype.forEach.call(items, function(it){ it.classList.toggle('active', it.getAttribute('data-target') === id); });
      var active = nav.querySelector('.' + NAV_ITEM_CLASS + '.active');
      if (active) {
        var inner = nav.querySelector('.theme-category-nav-inner');
        var aRect = active.getBoundingClientRect(), iRect = inner.getBoundingClientRect();
        if (aRect.left < iRect.left || aRect.right > iRect.right) {
          var delta = aRect.left - iRect.left - (iRect.width/2) + (aRect.width/2);
          inner.scrollBy({ left: delta, behavior: 'smooth' });
        }
      }
    }
  }

  // Expose small API so archive-product search can call updates
  window.ThemeCategoryNav = window.ThemeCategoryNav || {};
  window.ThemeCategoryNav.offset = window.ThemeCategoryNav.offset || SCROLL_OFFSET;
  window.ThemeCategoryNav.onFilterUpdate = function(){ /* no-op (archive triggers visibility toggle directly) */ };

  // Init
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', buildNav);
  else buildNav();

  // Mutation observer: if sections are injected later, build nav
  var mo = new MutationObserver(function(){ if (!document.getElementById(NAV_ID)) buildNav(); });
  mo.observe(document.body, { childList: true, subtree: true });

})();