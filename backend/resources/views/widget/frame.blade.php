<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>See your logo on products</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  html,body{height:100%}
  body{font-family:-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#fff;color:#1a1a1a}
  .card{display:flex;flex-direction:column;height:100%;border:1px solid #e6e1d4;border-radius:12px;overflow:hidden}
  .hd{padding:9px 12px 6px}
  .hd h1{font-size:13px;font-weight:700;line-height:1.2}
  .hd p{font-size:11px;color:#8a8577;margin-top:1px}
  .grid{flex:1;display:grid;grid-template-columns:repeat(3,1fr);gap:5px;padding:0 8px}
  .cell{position:relative;background:#f4f1ea;border-radius:7px;overflow:hidden;aspect-ratio:1/1}
  .cell img{width:100%;height:100%;object-fit:cover;display:block}
  .sk{background:linear-gradient(90deg,#f0ece0 25%,#e6e1d4 37%,#f0ece0 63%);background-size:400% 100%;animation:sh 1.3s ease-in-out infinite}
  @keyframes sh{0%{background-position:100% 0}100%{background-position:0 0}}
  .cta{display:block;margin:8px;padding:9px;text-align:center;background:#398aff;color:#fff;text-decoration:none;border-radius:999px;font-size:12.5px;font-weight:700}
  .cta:hover{background:#2f78e0}
  .by{text-align:center;font-size:9.5px;color:#b3ada0;padding-bottom:7px}
  .by a{color:#b3ada0;text-decoration:none}
</style>
</head>
<body>
<div class="card">
  <div class="hd">
    <h1 id="ttl">Your logo on real products</h1>
    <p>Free mockups — made from your brand</p>
  </div>
  <div class="grid" id="grid">
    @for ($i = 0; $i < 6; $i++)<div class="cell sk"></div>@endfor
  </div>
  <a class="cta" id="cta" href="/w/{{ $id }}" target="_blank" rel="noopener">See your logo on more →</a>
  <div class="by">powered by <a href="{{ url('/') }}" target="_blank" rel="noopener">RunMyPrint</a></div>
</div>
<script>
(function(){
  var id = @json($id), grid = document.getElementById('grid'), tries = 0;
  function render(items){
    if(!items.length) return;
    grid.innerHTML = '';
    items.slice(0,6).forEach(function(p){
      var c = document.createElement('div'); c.className='cell';
      var im = document.createElement('img'); im.loading='lazy'; im.src=p.img; im.alt=p.name||'Your logo';
      c.appendChild(im); grid.appendChild(c);
    });
    for(var n=items.length;n<6;n++){ var s=document.createElement('div'); s.className='cell sk'; grid.appendChild(s); }
  }
  function poll(){
    fetch('/api/widget/'+id,{headers:{Accept:'application/json'}}).then(function(r){return r.json();}).then(function(d){
      if(d.products && d.products.length) render(d.products);
      if(d.done || ++tries > 60) return;
      setTimeout(poll, 1500);
    }).catch(function(){ if(++tries<=60) setTimeout(poll, 2500); });
  }
  poll();
})();
</script>
</body>
</html>
