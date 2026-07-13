// Template generator (multi-orientation): author base fabric JSON (gemini-3.1-pro-preview)
// -> hero image (gemini-3-pro-image, one per design, shared) -> render (fabric+playwright)
// -> vision-refine + score -> reflow to each other orientation -> save variants.
// Bleed-aware. Quality gate (>=7). Usage: node generate-templates.cjs <productKey> <count> [start]
const { chromium } = require('playwright-core');
const fs = require('fs');
const KEY = (fs.readFileSync('.env','utf8').match(/^GEMINI_API_KEY=(.+)$/m)||[])[1]?.trim().replace(/^["']|["']$/g,'');
const GL='https://generativelanguage.googleapis.com/v1beta/models', AUTHOR='gemini-3.1-pro-preview', IMG='gemini-3-pro-image';
const OUT='/tmp/claude-0/-root-work-print/6fc57cdb-3cd9-4601-99d8-4c7e4f78bbb0/scratchpad/gen';
const PRODUCTS = {
  bcard:      { kind:'business card (3.5x2in)', cat:'business-cards',      product:'matte-business-cards', orients:[{o:'landscape',w:760,h:434},{o:'square',w:540,h:540}] },
  flyer:      { kind:'flyer',                    cat:'marketing-materials', product:'flyers',               orients:[{o:'portrait',w:538,h:760},{o:'landscape',w:760,h:538},{o:'square',w:640,h:640}] },
  poster:     { kind:'poster',                   cat:'signs-banners',       product:'custom-posters',       orients:[{o:'portrait',w:570,h:760},{o:'landscape',w:760,h:570}] },
  postcard:   { kind:'postcard (6x4in)',         cat:'marketing-materials', product:'standard-postcards',   orients:[{o:'landscape',w:760,h:507},{o:'portrait',w:507,h:760},{o:'square',w:640,h:640}] },
  letterhead: { kind:'A4 letterhead (logo+contact header, body blank)', cat:'stationery', product:'company-letterhead', orients:[{o:'portrait',w:587,h:760}] },
  banner:     { kind:'tall roll-up banner',      cat:'signs-banners',       product:'retractable-banners',  orients:[{o:'portrait',w:323,h:760}] },
};
const FONTS=['Inter','Montserrat','Outfit','Manrope','Space Grotesk','Playfair Display','League Spartan','Cormorant Garamond','Raleway','Sora','Urbanist','Work Sans','Lora','Oswald','Figtree','Poppins','DM Sans','Archivo'];
const LOGO='data:image/webp;base64,'+fs.readFileSync('storage/app/public/brand/logo-placeholder.webp').toString('base64');
const safe=(w,h)=>`at least ${Math.round(w*0.06)}px from the sides and ${Math.round(h*0.06)}px from top/bottom`;

function parseJson(parts){let s=parts.map(p=>p.text||'').join('').trim().replace(/^```(?:json)?\s*/i,'').replace(/\s*```$/,'').trim();try{return JSON.parse(s);}catch(e){const a=s.indexOf('{'),b=s.lastIndexOf('}');if(a>=0&&b>a)return JSON.parse(s.slice(a,b+1));throw e;}}
async function gemini(model,parts,jsonOut){const body={contents:[{parts}],generationConfig:jsonOut?{responseMimeType:'application/json'}:{responseModalities:['IMAGE']}};for(let a=0;a<5;a++){try{const r=await fetch(`${GL}/${model}:generateContent?key=${KEY}`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});if(!r.ok){if([429,500,502,503,529].includes(r.status)){await new Promise(s=>setTimeout(s,2500*(a+1)));continue;}throw new Error('HTTP '+r.status);}return (await r.json()).candidates?.[0]?.content?.parts||[];}catch(e){if(a===4)throw e;await new Promise(s=>setTimeout(s,2500*(a+1)));}}}
function norm(t){for(const o of t.objects||[]){if(o.src==='LOGO_SLOT')o.src=LOGO;if(o.type&&!/^[A-Z]/.test(o.type))o.type=o.type[0].toUpperCase()+o.type.slice(1);}return t;}
function slotted(objs){return objs.map(o=>({...o,src:o.src&&o.src.startsWith('data:')?(o.rmpRole==='logo'?'LOGO_SLOT':'IMAGE_SLOT'):o.src}));}
function reinject(objs,logo,hero){for(const o of objs){if(o.src==='LOGO_SLOT')o.src=logo;if(o.src==='IMAGE_SLOT')o.src=hero||logo;}return objs;}

function authorPrompt(kind,w,h,i){return `You are a senior B2B print designer. Design ONE ${kind} template as fabric.js v6 JSON. Canvas trim=${w}x${h}px, origin top-left. Return STRICT JSON: {"background":"#hex","font":"<one from list>","style":"<short name>","imagePrompt":"<hero/background image prompt or empty>","objects":[...]}
RULES: B2B, professional, clean, high-contrast, print-ready; distinct (variation #${i}); great whitespace. font: ONE of ${FONTS.join(', ')} on EVERY text object. Objects PascalCase "type"("Rect","IText","Line"); props left,top,width,height,fill,originX,originY,angle,opacity,rx; text adds text,fontFamily,fontSize,fontWeight,textAlign,charSpacing,lineHeight. MANDATORY text with exact rmpRole: companyName("Company Name"),name("Your Name"),title("Title / Role"),url("www.yourcompany.com"),phone("+1 (555) 123-4567"). MANDATORY logo: Image "rmpRole":"logo","src":"LOGO_SLOT","width":512,"height":279,"originX":"center","originY":"center",scaleX/scaleY~0.2-0.5. If imagePrompt set, ALSO add Image "src":"IMAGE_SLOT" (no rmpRole) with left/top/width/height. BLEED: full-bleed backgrounds/hero reach all trim edges (start 0,0, full w/h). SAFETY: keep all text+logo ${safe(w,h)}. Return ONLY JSON.`;}
function reflowPrompt(kind,w,h,orient){return `Adapt this B2B ${kind} design to a NEW ${orient} canvas ${w}x${h}px. Reflow/reposition/resize elements to fit the new aspect tastefully (do NOT stretch). Keep the same style, colours, font, ALL text objects + their rmpRole, the logo (src) and any hero (src). Full-bleed backgrounds must reach every edge; keep text+logo ${safe(w,h)}. Return STRICT JSON {background,font,style,imagePrompt,objects}.`;}

(async()=>{
  const pk=process.argv[2]||'bcard', count=Number(process.argv[3]||3), start=Number(process.argv[4]||1);
  const spec=PRODUCTS[pk]; fs.mkdirSync(`${OUT}/${pk}`,{recursive:true});
  const browser=await chromium.launch({args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--js-flags=--max-old-space-size=256']});
  const page=await browser.newPage({viewport:{width:1000,height:1000}});
  await page.goto('http://localhost:8091/render.html',{waitUntil:'load'}); await page.waitForFunction('window.__ready===true',{timeout:15000});
  const render=async(json,w,h)=>{const fam=[...new Set((json.objects||[]).map(o=>o.fontFamily).filter(Boolean))];await page.evaluate(f=>window.loadFonts(f),fam);const u=await page.evaluate(([j,w,h])=>window.renderTemplate(j,w,h),[json,w,h]);return Buffer.from(u.split(',')[1],'base64');};
  const refine=async(kind,w,h,png,t,hero)=>{try{const rp=await gemini(AUTHOR,[{text:`Rendered preview of a ${kind} template + its fabric JSON. Fix: text overflow/off the ${w}x${h} canvas, overlaps, logo over text, poor contrast, spacing. PRINT: full-bleed backgrounds reach every edge (no white gap); text+logo ${safe(w,h)}. Keep style, all rmpRole objects, logo src, IMAGE_SLOT src. Return STRICT JSON {background,font,style,imagePrompt,objects} + top-level "score" 1-10.`},{inlineData:{mimeType:'image/png',data:png.toString('base64')}},{text:'JSON:\n'+JSON.stringify({background:t.background,font:t.font,style:t.style,objects:slotted(t.objects)})}],true);const f=parseJson(rp);if(f&&Array.isArray(f.objects)&&f.objects.length>=5){reinject(f.objects,LOGO,hero);const nt=norm(f);return{t:nt,score:Number(f.score)||6,png:await render(nt,w,h)};}}catch(e){}return null;};

  const meta=[];
  for(let n=0;n<count;n++){
    const num=String(start+n).padStart(3,'0'); const P=spec.orients[0];
    let base=null;
    for(let a=0;a<3;a++){
      try{
        const ap=await gemini(AUTHOR,[{text:authorPrompt(spec.kind,P.w,P.h,start+n)}],true); let t=norm(parseJson(ap));
        if(!Array.isArray(t.objects)||t.objects.length<5) continue;
        if(t.imagePrompt&&t.imagePrompt.trim()){try{const ip=await gemini(IMG,[{text:`${t.imagePrompt}. Clean professional B2B, subtle for text overlay, no text/logos/watermark.`}],false);const inl=(ip.find(p=>p.inlineData||p.inline_data)||{});const d=inl.inlineData||inl.inline_data;if(d){const uri='data:'+(d.mimeType||d.mime_type||'image/png')+';base64,'+d.data;for(const o of t.objects)if(o.src==='IMAGE_SLOT')o.src=uri;}}catch(e){for(const o of t.objects)if(o.src==='IMAGE_SLOT')o.src=LOGO;}}
        const png=await render(t,P.w,P.h); const r=await refine(spec.kind,P.w,P.h,png,t,(t.objects.find(o=>o.src&&o.src.startsWith('data:')&&o.rmpRole!=='logo')||{}).src);
        const cand=r||{t,score:6,png};
        if(!base||cand.score>base.score)base=cand;
        if(base.score>=7)break;
      }catch(e){}
    }
    if(!base){console.log('FAIL',`${pk}-${num}`);continue;}
    const heroUri=(base.t.objects.find(o=>o.src&&o.src.startsWith('data:')&&o.rmpRole!=='logo')||{}).src;
    let heroRef=null;
    if(heroUri){const ext=((heroUri.slice(5,heroUri.indexOf(';')).split('/')[1])||'png').replace('jpeg','jpg');fs.writeFileSync(`${OUT}/${pk}/${pk}-${num}.hero.${ext}`,Buffer.from(heroUri.split(',')[1],'base64'));heroRef=`/storage/templates/${pk}-${num}.hero.${ext}`;}
    const variants=[{...P,t:base.t,png:base.png,score:base.score}];
    for(const o of spec.orients.slice(1)){
      try{const rf=await gemini(AUTHOR,[{text:reflowPrompt(spec.kind,o.w,o.h,o.o)},{text:'JSON:\n'+JSON.stringify({background:base.t.background,font:base.t.font,style:base.t.style,objects:slotted(base.t.objects)})}],true);
        let vt=parseJson(rf); if(!Array.isArray(vt.objects)||vt.objects.length<5)continue; reinject(vt.objects,LOGO,heroUri); vt=norm(vt);
        let vpng=await render(vt,o.w,o.h); const vr=await refine(spec.kind,o.w,o.h,vpng,vt,heroUri); if(vr){vt=vr.t;vpng=vr.png;}
        variants.push({...o,t:vt,png:vpng,score:vr?vr.score:6});
      }catch(e){}
    }
    for(const v of variants){
      const ref=`${pk}-${num}-${v.o}`; const save=JSON.parse(JSON.stringify(v.t));
      for(const o of save.objects){if(o.src&&o.src.startsWith('data:')){o.src=o.rmpRole==='logo'?'/storage/brand/logo-placeholder.webp':(heroRef||'/storage/brand/logo-placeholder.webp');}}
      fs.writeFileSync(`${OUT}/${pk}/${ref}.json`,JSON.stringify(save)); fs.writeFileSync(`${OUT}/${pk}/${ref}.png`,v.png);
      meta.push({ref,orientation:v.o,style:base.t.style||'B2B',font:base.t.font||'Inter',score:v.score,cat:spec.cat,product:spec.product,hero:!!heroRef,heroBase:`${pk}-${num}`});
    }
    console.log('OK',`${pk}-${num}`,'| orients',variants.map(v=>v.o+':'+v.score).join(' '),'| font',base.t.font,'| hero',!!heroRef);
  }
  fs.writeFileSync(`${OUT}/${pk}/meta.json`,JSON.stringify(meta,null,2));
  await browser.close(); console.log('DONE',pk,meta.length+' variants');
})().catch(e=>{console.error('FATAL',e.message);process.exit(1);});
