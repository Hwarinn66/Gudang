'use strict';
window.Warehouse = {
  csrf: document.querySelector('meta[name="csrf-token"]')?.content,
  escape(value) { const el=document.createElement('span');el.textContent=value??'';return el.innerHTML; },
  async request(url,method='GET',data) {
    const controller=new AbortController(); const timer=setTimeout(()=>controller.abort(),15000);
    try {
      const response=await fetch(url,{method,credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':this.csrf,...(data?{'Content-Type':'application/json'}:{})},body:data?JSON.stringify(data):undefined,cache:'no-store',signal:controller.signal});
      let json;try{json=await response.json();}catch{throw new Error('Respons server tidak valid. Periksa koneksi atau login ulang.');}
      if(!response.ok||!json.success){const error=new Error(json.message||'Permintaan gagal.');error.data=json;error.status=response.status;throw error;}return json;
    }catch(e){if(e.name==='AbortError')throw new Error('Koneksi melewati batas waktu. Periksa status transaksi sebelum mengulang.');throw e;}finally{clearTimeout(timer);}
  },
  toast(message) {const el=document.getElementById('toast');if(!el)return;el.textContent=message;el.classList.remove('hidden');clearTimeout(this.toastTimer);this.toastTimer=setTimeout(()=>el.classList.add('hidden'),5500);}
};
document.getElementById('menu-toggle')?.addEventListener('click',e=>{const sidebar=document.getElementById('sidebar');sidebar.classList.toggle('hidden');sidebar.classList.toggle('flex');e.currentTarget.setAttribute('aria-expanded',!sidebar.classList.contains('hidden'));});
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault();}));
document.querySelectorAll('[data-print]').forEach(button=>button.addEventListener('click',()=>window.print()));
(() => {
 const url=document.querySelector('meta[name="pending-url"]')?.content;if(!url)return;
 const list=document.getElementById('pending-list'),status=document.getElementById('poll-status'),api=document.querySelector('meta[name="api-base"]').content;
 const esc=Warehouse.escape; const models=new Map(); let running=false, stopped=false;
 function preview(card,t) {
   const input=card.querySelector('[name="quantity"]'),raw=input.value,quantity=Number(raw),valid=/^[0-9]+$/.test(raw)&&Number.isSafeInteger(quantity)&&quantity>0&&quantity<=2147483647;
   const after=t.product.stock+(t.transaction_type==='IN'?quantity:-quantity);
   card.querySelector('[data-before]').textContent=t.product.stock;
   card.querySelector('[data-after]').textContent=valid?after:'—';
   card.querySelector('[data-after]').classList.toggle('text-red-700',valid&&after<0);
   card.querySelector('[data-approve]').disabled=!valid||after<0||after>2147483647||card.dataset.busy==='1';
   card.querySelector('[data-stock]').textContent=t.product.stock+' '+t.product.unit;
 }
 function render(items) {
   document.querySelectorAll('[data-pending-count]').forEach(el=>el.textContent=items.length);
   if(!list)return;
   list.querySelectorAll('[data-empty]').forEach(el=>el.remove());
   if(!list.dataset.ready){list.innerHTML='';list.dataset.ready='1';}
   const ids=new Set(items.map(t=>String(t.id)));
   list.querySelectorAll('[data-transaction]').forEach(el=>{if(!ids.has(el.dataset.transaction)){models.delete(el.dataset.transaction);el.remove();}});
   for(const t of items){
     const id=String(t.id);models.set(id,t);let card=list.querySelector(`[data-transaction="${id}"]`);
     if(!card){
       card=document.createElement('article');card.className='panel overflow-hidden';card.dataset.transaction=id;
       card.innerHTML=`<div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><span class="badge ${t.transaction_type==='IN'?'bg-teal-50 text-teal-800':'bg-orange-50 text-orange-800'}">${t.transaction_type==='IN'?'+ BARANG MASUK':'− BARANG KELUAR'}</span><span class="text-xs text-slate-500">${esc(t.transaction_number)}</span></div><div class="p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="mono text-xl font-bold text-slate-950">${esc(t.product.product_code)}</p><h3 class="mt-1 font-semibold">${esc(t.product.name)} <span class="font-normal">${esc(t.product.specification)}</span></h3><p class="muted mt-2">${esc(t.product.room)} · <span class="mono">${esc(t.product.location)}</span></p></div><div class="text-right"><p class="muted">Stok saat ini</p><p class="mt-1 text-lg font-semibold" data-stock></p></div></div><p class="muted mt-4">Scan ${esc(t.scanned_at)} · ${esc(t.product.moving_type||'Belum diklasifikasikan')}</p><form class="mt-5"><label>Quantity (${esc(t.product.unit)})<input name="quantity" type="number" min="1" max="2147483647" step="1" inputmode="numeric" required placeholder="Masukkan quantity" value="${t.quantity??''}"></label><div class="mt-4 flex items-center justify-between rounded-lg bg-slate-50 p-3 text-sm"><span>Stok sebelum <strong data-before></strong></span><span>Stok sesudah <strong class="ml-1 text-lg" data-after></strong></span></div><p data-error role="alert" class="mt-3 text-sm text-red-700"></p><div class="mt-4 flex gap-3"><button type="button" class="btn-light flex-1" data-cancel>Batalkan</button><button type="submit" class="btn flex-1" data-approve>Approve</button></div></form></div>`;
       const input=card.querySelector('input');input.addEventListener('input',()=>{preview(card,models.get(id));card.querySelector('[data-error]').textContent='';});
       card.querySelector('form').addEventListener('submit',async e=>{e.preventDefault();const current=models.get(id);const q=Number(input.value);if(!confirm(`Approve ${current.transaction_type} ${q} ${current.product.unit} untuk ${current.product.product_code} — ${current.product.name} ${current.product.specification||''}?`))return;await process(card,`${api}/transactions/${id}/approve`,{quantity:q});});
       card.querySelector('[data-cancel]').addEventListener('click',async()=>{if(confirm('Batalkan transaksi ini? Stok tidak berubah.'))await process(card,`${api}/transactions/${id}/cancel`,{});});
       list.appendChild(card);
     }
     preview(card,t);
   }
   if(!items.length)list.innerHTML='<div data-empty class="panel empty xl:col-span-2"><p class="font-medium text-slate-700">Tidak ada transaksi pending</p><p class="mt-2 text-sm">Scan QR dari HP untuk memulai transaksi.</p></div>';
 }
 async function process(card,url,data){
   if(card.dataset.busy==='1')return;card.dataset.busy='1';card.querySelectorAll('button').forEach(b=>b.disabled=true);
   try{const result=await Warehouse.request(url,'POST',data);Warehouse.toast(result.message);card.remove();await poll();}
   catch(e){card.querySelector('[data-error]').textContent=e.message+(e.data?.data?.stock!==undefined?` Stok tersedia: ${e.data.data.stock}.`:'');}
   finally{card.dataset.busy='0';card.querySelector('[data-cancel]').disabled=false;const t=models.get(card.dataset.transaction);if(t)preview(card,t);}
 }
 async function poll(){if(running||stopped||document.hidden)return;running=true;
   try{const result=await Warehouse.request(url);render(result.data);if(status){status.textContent='Tersinkron · '+new Date(result.server_time).toLocaleTimeString('id-ID',{timeZone:'Asia/Jakarta'});status.className='muted';}}
   catch(e){if(status){status.textContent=e.message;status.className='text-sm text-red-700';}if(e.status===401||e.status===419)stopped=true;}
   finally{running=false;}
 }
 async function loop(){await poll();if(!stopped)setTimeout(loop,list?3000:10000);}loop();document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll();});
})();
