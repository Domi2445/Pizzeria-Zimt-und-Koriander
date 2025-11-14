(function(){
  'use strict';

  var SECTION_CANDIDATES = [
    '.gf-category-section',
    '.category-section',
    '.archive-section',
    '.product-category',
    '.woocommerce-archive',
    '.products-section',
    '.products',
    '.woocommerce'
  ];

  var HEADLINE_SELECTORS = 'h2, h3, .section-title, .woocommerce-loop-category__title';
  var NAV_ID = 'theme-category-nav';
  var NAV_ITEM_CLASS = 'theme-category-nav-item';
  var ACTIVE_CLASS = 'active';
  var SCROLL_OFFSET = (window.ThemeCategoryNav && ThemeCategoryNav.offset) ? parseInt(ThemeCategoryNav.offset,10) : 90;

  function qsAll(sel, ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(sel)); }
  function elementIsVisible(el){ if (!el) return false; var r = el.getBoundingClientRect(); return !(r.width === 0 && r.height === 0); }

  function findSectionWrappers() {
    var found = [];
    for (var i=0;i<SECTION_CANDIDATES.length;i++){
      var els = qsAll(SECTION_CANDIDATES[i]);
      els.forEach(function(e){ if (elementIsVisible(e)) found.push(e); });
      if (found.length) break;
    }
    return found;
  }

  function findSectionsFromHeadlines(){
    var heads = qsAll(HEADLINE_SELECTORS).filter(function(h){ return (h.textContent || '').trim().length > 0 && elementIsVisible(h); });
    var sections = [];
    heads.forEach(function(h){
      var node = h; var picked = null; var maxDepth = 6; var depth = 0;
      while (node && node !== document.body && depth < maxDepth){
        if (node.querySelectorAll && (node.querySelectorAll('.product, .product-item, .product-column').length >= 2)) { picked = node; break; }
        if (node.tagName && (node.tagName.toLowerCase() === 'section' || node.tagName.toLowerCase() === 'article')) { picked = node; break; }
        var cls = node.className || '';
        if (typeof cls === 'string' && /(section|category|archive|products|grid)/i.test(cls)) { picked = node; break; }
        node = node.parentElement; depth++;
      }
      if (!picked) picked = h;
      sections.push({ wrapper: picked, titleNode: h });
    });
    var unique = []; var seen = new Set();
    sections.forEach(function(s){ var id = s.wrapper && s.wrapper._theme_cat_uid ? s.wrapper._theme_cat_uid : null; if (!id) { id = 'w_' + Math.random().toString(36).slice(2,9); try { s.wrapper._theme_cat_uid = id; } catch(e){} } if (!seen.has(id)) { seen.add(id); unique.push({ wrapper: s.wrapper, titleNode: s.titleNode }); } });
    return unique;
  }

  function buildNav() {
    if (document.getElementById(NAV_ID)) return;
    var wrappers = findSectionWrappers();
    var sections = [];
    if (wrappers && wrappers.length) {
      wrappers.forEach(function(wrap){
        var h = wrap.querySelector(HEADLINE_SELECTORS);
        var title = h ? (h.textContent || '').trim() : (wrap.getAttribute('data-title') || wrap.getAttribute('aria-label') || '');
        if (!title) {
          var p = wrap.querySelector('.product, .product-item, .product-column');
          if (p) { var t = p.querySelector('.product_title, .woocommerce-loop-product__title, h2, h3'); if (t) title = (t.textContent||'').trim(); }
        }
        if (!title) return;
        if (!wrap.id) {
          var slug = title.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^
-+|
-+$/g,'');
          var id = 'kategorie-' + slug; var tryId = id; var i = 1; while (document.getElementById(tryId)) { tryId = id + '-' + (i++); }
          try { wrap.id = tryId; } catch(e){}
        }
        sections.push({ id: wrap.id, title: title, el: wrap });
      });
    }
    if (!sections.length) {
      var fromHeads = findSectionsFromHeadlines();
      fromHeads.forEach(function(s){
        var wrap = s.wrapper;
        var title = s.titleNode ? (s.titleNode.textContent || '').trim() : (wrap.getAttribute('data-title') || '');
        if (!title) return;
        if (!wrap.id) {
          var slug = title.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^
-+|
-+$/g,'');
          var id = 'kategorie-' + slug;
          var tryId = id; var i = 1; while (document.getElementById(tryId)) { tryId = id + '-' + (i++); }
          try { wrap.id = tryId; } catch(e){}
        }
        sections.push({ id: wrap.id, title: title, el: wrap });
      });
    }
    if (!sections.length) return;
    var nav = document.createElement('nav'); nav.id = NAV_ID; nav.className = 'theme-category-nav';
    var inner = document.createElement('div'); inner.className = 'theme-category-nav-inner';
    sections.forEach(function(s){ var btn = document.createElement('button'); btn.className = NAV_ITEM_CLASS; btn.type = 'button'; btn.setAttribute('data-target', s.id); btn.textContent = s.title; inner.appendChild(btn); });
    nav.appendChild(inner);
    var left = document.createElement('button'); left.className = 'theme-category-nav-arrow left'; left.type='button'; left.innerHTML='◀';
    var right = document.createElement('button'); right.className = 'theme-category-nav-arrow right'; right.type='button'; right.innerHTML='▶';
    nav.appendChild(left); nav.appendChild(right);
    var hero = document.querySelector('.page-hero, .store-hero, .archive-header'); var main = document.querySelector('main') || document.body;
    if (hero && hero.parentNode) hero.parentNode.insertBefore(nav, hero.nextSibling);
    else if (main && main.parentNode) main.parentNode.insertBefore(nav, main);
    else document.body.insertBefore(nav, document.body.firstChild);
    inner.addEventListener('click', function(e){ var btn = e.target.closest('.' + NAV_ITEM_CLASS); if (!btn) return; var id = btn.getAttribute('data-target'); var el = document.getElementById(id); if (!el) return; var rect = el.getBoundingClientRect(); var top = window.pageYOffset + rect.top - SCROLL_OFFSET; window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' }); setActiveById(id); });
    left.addEventListener('click', function(){ inner.scrollBy({left:-200, behavior:'smooth'}); });
    right.addEventListener('click', function(){ inner.scrollBy({left:200, behavior:'smooth'}); });
    var ticking = false; var secs = sections.map(function(s){ return {id:s.id, el: document.getElementById(s.id)}; });
    window.addEventListener('scroll', function(){ if (!ticking) { window.requestAnimationFrame(function(){ onScrollUpdate(secs); ticking=false; }); ticking = true; } }, { passive: true });
    window.addEventListener('resize', function(){ onScrollUpdate(secs); });
    onScrollUpdate(secs);
  }

  function onScrollUpdate(sections) {
    var offset = SCROLL_OFFSET + 6;
    var fromTop = window.pageYOffset + offset;
    var currentId = null;
    for (var i=0;i<sections.length;i++){ var s = sections[i]; if (!s.el) continue; var top = s.el.getBoundingClientRect().top + window.pageYOffset; if (top <= fromTop) currentId = s.id; else break; }
    if (!currentId && sections.length) currentId = sections[0].id;
    if (currentId) setActiveById(currentId);
  }

  function setActiveById(id) {
    var nav = document.getElementById(NAV_ID); if (!nav) return; var items = nav.querySelectorAll('.' + NAV_ITEM_CLASS); Array.prototype.forEach.call(items, function(it){ it.classList.toggle('active', it.getAttribute('data-target') === id); }); var active = nav.querySelector('.' + NAV_ITEM_CLASS + '.active'); if (active){ var inner = nav.querySelector('.theme-category-nav-inner'); if (inner) { var aRect = active.getBoundingClientRect(); var iRect = inner.getBoundingClientRect(); if (aRect.left < iRect.left || aRect.right > iRect.right) { var delta = aRect.left - iRect.left - (iRect.width/2) + (aRect.width/2); inner.scrollBy({ left: delta, behavior: 'smooth' }); } } }
  }

  function initWhenReady(){ if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', buildNav); else buildNav(); var m = new MutationObserver(function(){ if (!document.getElementById(NAV_ID)) buildNav(); }); m.observe(document.body, { childList: true, subtree: true }); }

  initWhenReady();
})();
