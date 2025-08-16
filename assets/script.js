(function(){
  const scope  = document.getElementById('cb-subscribe-scope');
  if (!scope) return;

  const star   = document.getElementById('cb-star');
  const card   = document.getElementById('cb-subscribe-card');
  const formEl = card.querySelector('.cb-form');
  const submit = document.getElementById('cb-submit');

  const modal  = document.getElementById('cb-thanks');
  const closeBtn = modal.querySelector('.cb-thanks-close');

  let tsPageOpen = Date.now();

  function setExpanded(x){
    scope.setAttribute('aria-expanded',x);
    star.setAttribute('aria-expanded',x);
    card.setAttribute('aria-hidden',!x);
  }
  function toggle(e){
    const expanded=scope.getAttribute('aria-expanded')==='true';
    const inside=e && e.target.closest && e.target.closest('#cb-subscribe-card');
    if(!expanded || !inside) setExpanded(!expanded);
  }
  star.addEventListener('click',toggle);
  star.addEventListener('keydown',e=>{
    if(e.key==='Enter'||e.key===' '){ e.preventDefault(); toggle(e); }
  });

  function openThanks(){
    modal.setAttribute('aria-hidden','false');
    closeBtn.focus({preventScroll:true});
    clearTimeout(openThanks._t);
    openThanks._t = setTimeout(closeThanks, 2800);
  }
  function closeThanks(){
    modal.setAttribute('aria-hidden','true');
    clearTimeout(openThanks._t);
  }
  closeBtn.addEventListener('click', closeThanks);
  modal.addEventListener('click', (e)=>{ if(e.target === modal) closeThanks(); });
  document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape' && modal.getAttribute('aria-hidden')==='false') closeThanks(); });

  async function safeJson(res){
    const text = await res.text();
    try { return {res, data: text? JSON.parse(text): null, text}; }
    catch { return {res, data: null, text}; }
  }

  submit.addEventListener('click', async (e)=>{
    e.preventDefault();
    if (submit.disabled) return;

    const name  = formEl.querySelector('input[name="NAME"]').value.trim();
    const email = formEl.querySelector('input[name="EMAIL"]').value.trim();
    const hp    = formEl.querySelector('#cb-hp').value.trim();
    if (!name || !email){ alert('Please enter your name and email.'); return; }

    const originalText = submit.textContent;
    submit.disabled = true;
    submit.textContent = 'Sending…';

    try{
      const resp = await fetch(window.CB_SUBSCRIBE_ENDPOINT || '/wp-json/colorbliss/v1/subscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, hp, ts: String(tsPageOpen) })
      });
      await safeJson(resp);
      formEl.reset();
      setExpanded(false);
      openThanks();
    } catch(err){
      // Even on network hiccup, still show the pop-up (per client request)
      openThanks();
    } finally {
      submit.textContent = originalText;
      submit.disabled = false;
    }
  });
})();
