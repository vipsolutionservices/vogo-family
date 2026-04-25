(function(){
  var C = window.VOGO_WA; if(!C) return;
  var isMobile = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
  var dvc = isMobile ? 'mobile' : 'desktop';

  // visibility gate
  if( (dvc==='mobile' && !C.visible.mobile) || (dvc==='desktop' && !C.visible.desktop) ) return;

  var root = document.getElementById('vogo-wa-root'); if(!root) return;
  root.style.zIndex = String(C.zIndex || 9999);

  // position per device
  var pos = C.position[dvc] || {h:'left', v:'bottom', offset:16};
  var o = (typeof pos.offset === 'number' ? pos.offset : 16) + 'px';
  // reset
  root.style.left = ''; root.style.right=''; root.style.top=''; root.style.bottom='';
  // horizontal
  if(pos.h === 'right'){ root.style.right = '16px'; } else { root.style.left = '16px'; }
  // vertical
  if(pos.v === 'top'){ root.style.top = o; } else { root.style.bottom = o; }

  // build UI
  var fab = document.createElement('button');
  fab.className = 'vogo-wa-fab';
  // icon/label flags
  var showIcon = !!(C.ui[dvc] && C.ui[dvc].icon);
  var showLabel= !!(C.ui[dvc] && C.ui[dvc].label);
  if(!showLabel) fab.classList.add('vogo-wa-no-label');
  if(!showLabel && showIcon) fab.classList.add('vogo-wa-round');

  // WhatsApp icon SVG
  var iconSvg = '<svg class="vogo-wa-icon" viewBox="0 0 32 32" width="22" height="22" aria-hidden="true" focusable="false"><path d="M27 15A11 11 0 0 1 5 22.7L4 27l4.4-1A11 11 0 1 0 27 15Z" fill="currentColor"/><path d="M19.6 18.4c-.3-.2-1.9-1.0-2.2-1s-.5.1-.7.4c-.2.2-.6.8-.8.9s-.4.2-.7.1c-.3-.1-1.5-.6-2.8-1.9-1.1-1.1-1.9-2.4-2-2.7-.1-.3 0-.5.1-.7s.6-.8.7-1.1.1-.4 0-.6c0-.2-.7-1.7-.9-2.3-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4s-1.1 1.1-1.1 2.7 1.1 3 1.2 3.2c.2.3 2.1 3.7 5.1 5.2 2.3 1.1 3.2 1.2 3.8 1.1.6-.1 1.9-.8 2.1-1.6.2-.8.2-1.4.2-1.5 0-.1-.1-.2-.3-.3Z" fill="currentColor"/></svg>';

  fab.innerHTML = (showIcon ? iconSvg : '') + (showLabel ? '<span>WhatsApp</span>' : '');
  fab.setAttribute('aria-expanded','false');

  var menu = document.createElement('div');
  menu.className = 'vogo-wa-menu vogo-wa-hide';

  function hrefFor(it){
    var base = 'https://wa.me/' + String(it.phone||'').replace(/\D+/g,'');
    var msg = String(it.msg||'');
    if(C.includeUrl){ msg = msg.replace('{{url}}', window.location.href); }
    return base + '?text=' + encodeURIComponent(msg);
  }

  (C.items||[]).forEach(function(it){
    if(!it || !it.phone) return;
    var a = document.createElement('a');
    a.className = 'vogo-wa-item';
    a.target = '_blank';
    a.rel = 'noopener';
    a.textContent = it.label || 'Contact';
    a.href = hrefFor(it);
    menu.appendChild(a);
  });

  // menu placement relativ la buton (deasupra pentru bottom, dedesubt pentru top)
  if(pos.v === 'top'){ menu.style.top = '56px'; }
  else { menu.style.bottom = '56px'; }
  if(pos.h === 'right'){ menu.style.right = '0'; } else { menu.style.left = '0'; }

  root.appendChild(fab);
  root.appendChild(menu);

  fab.addEventListener('click', function(e){
    e.stopPropagation();
    var open = !menu.classList.contains('vogo-wa-hide');
    if(open){ menu.classList.add('vogo-wa-hide'); fab.setAttribute('aria-expanded','false'); }
    else { menu.classList.remove('vogo-wa-hide'); fab.setAttribute('aria-expanded','true'); }
  });

  document.addEventListener('click', function(e){
    if(!root.contains(e.target)){ menu.classList.add('vogo-wa-hide'); fab.setAttribute('aria-expanded','false'); }
  });
})();
