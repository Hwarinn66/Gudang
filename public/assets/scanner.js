'use strict';
(()=>{
 let mode=null,reader=null,cameraRunning=false,busy=false,paused=false,starting=false,audio=null;
 const byId=id=>document.getElementById(id),esc=Warehouse.escape;
 const status=message=>byId('camera-status').textContent=message;
 function feedback(ok){try{if(audio){const osc=audio.createOscillator(),gain=audio.createGain();osc.connect(gain);gain.connect(audio.destination);osc.frequency.value=ok?880:220;gain.gain.value=.07;osc.start();osc.stop(audio.currentTime+(ok?.13:.3));}navigator.vibrate?.(ok?60:[90,60,90]);}catch{}}
 async function stopCamera(){if(reader&&reader.isScanning){try{await reader.stop();}catch{}cameraRunning=false;}byId('retry-camera').classList.remove('hidden');}
 async function startCamera(){
   if(busy||starting||!mode)return;paused=false;byId('scan-again').classList.add('hidden');byId('retry-camera').classList.add('hidden');
   if(!window.Html5Qrcode){status('Library scanner belum tersedia. Muat ulang atau gunakan token dari scanner fisik.');return;}
   reader??=new Html5Qrcode('qr-reader',{formatsToSupport:[Html5QrcodeSupportedFormats.QR_CODE],verbose:false});
   if(!window.isSecureContext||!navigator.mediaDevices?.getUserMedia){status('Kamera membutuhkan HTTPS terpercaya. Gunakan panduan HTTPS lokal, atau unggah foto QR di bawah.');byId('retry-camera').classList.remove('hidden');return;}
   starting=true;byId('change-mode').disabled=true;
   try {await stopCamera();byId('retry-camera').classList.add('hidden');await reader.start({facingMode:'environment'},{fps:8,qrbox:(w,h)=>({width:Math.min(w,h,280)*.75,height:Math.min(w,h,280)*.75})},text=>submit(text));cameraRunning=true;status('Arahkan kamera ke QR. Periksa kode barang pada label.');}
   catch(e){status('Kamera tidak dapat dibuka. Izinkan akses kamera, tutup aplikasi lain, lalu coba lagi atau gunakan foto QR.');byId('retry-camera').classList.remove('hidden');}
   finally{starting=false;byId('change-mode').disabled=busy;if(document.hidden)await stopCamera();}
 }
 async function submit(token){
   if(!mode||busy||paused)return;busy=true;paused=true;const submittedMode=mode;
   byId('change-mode').disabled=true;byId('manual-form').querySelector('button').disabled=true;
   await stopCamera();status('Memproses QR…');
   try{const result=await Warehouse.request(document.querySelector('meta[name="scan-url"]').content,'POST',{qr_token:token.trim(),mode:submittedMode});show(result,true);feedback(true);status('Scan selesai. Selesaikan transaksi di komputer.');}
   catch(e){show(e.data||{message:e.message},false);feedback(false);status('Periksa pesan hasil scan.');}
   finally{busy=false;byId('change-mode').disabled=false;byId('manual-form').querySelector('button').disabled=false;byId('scan-again').classList.remove('hidden');byId('retry-camera').classList.add('hidden');}
 }
 function show(result,ok){const box=byId('scan-result'),p=result.data?.product;box.className='mt-5 rounded-xl border p-5 '+(ok?'border-teal-700 bg-teal-950':'border-red-700 bg-red-950');box.innerHTML=`<h2 class="text-base text-white">${ok?'✓':'!'} ${esc(result.message)}</h2>${p?`<p class="mono mt-4 text-2xl font-bold">${esc(p.product_code)}</p><p class="mt-1 text-lg">${esc(p.name)} · ${esc(p.specification||'—')}</p><p class="mt-3 text-sm">${esc(p.room)} / ${esc(p.location)}</p><p class="mt-1 text-sm">Stok: ${p.stock} ${esc(p.unit)}</p>`:''}${result.data?.transaction_number?`<p class="mono mt-3 text-xs">${esc(result.data.transaction_number)}</p>`:''}${ok?`<p class="mt-4 text-sm font-semibold">${mode==='IN'?'BARANG MASUK':'BARANG KELUAR'}<br>MENUNGGU APPROVE DI KOMPUTER</p>`:''}`;}
 document.querySelectorAll('[data-mode]').forEach(button=>button.addEventListener('click',async()=>{
   mode=button.dataset.mode;paused=false;try{audio??=new (window.AudioContext||window.webkitAudioContext)();await audio.resume();}catch{}
   byId('mode-chooser').classList.add('hidden');byId('scan-section').classList.remove('hidden');byId('mode-label').textContent=mode==='IN'?'+ BARANG MASUK':'− BARANG KELUAR';byId('mode-banner').className='mt-5 rounded-xl p-5 '+(mode==='IN'?'bg-teal-800':'bg-orange-800');byId('scan-result').classList.add('hidden');await startCamera();
 }));
 byId('change-mode').addEventListener('click',async()=>{if(busy)return;paused=true;await stopCamera();mode=null;byId('scan-section').classList.add('hidden');byId('mode-chooser').classList.remove('hidden');});
 byId('retry-camera').addEventListener('click',startCamera);byId('scan-again').addEventListener('click',async()=>{byId('scan-result').classList.add('hidden');byId('manual-token').value='';await startCamera();});
 byId('manual-form').addEventListener('submit',e=>{e.preventDefault();if(!busy&&!starting){paused=false;submit(byId('manual-token').value);}});
 byId('qr-file').addEventListener('change',async e=>{if(busy||starting||!e.target.files[0])return;await stopCamera();paused=false;try{if(!window.Html5Qrcode)throw new Error('Library scanner belum dimuat.');reader??=new Html5Qrcode('qr-reader',{verbose:false});const token=await reader.scanFile(e.target.files[0],true);await submit(token);}catch{status('QR tidak terdeteksi pada foto. Gunakan foto tajam dengan seluruh QR terlihat.');feedback(false);}finally{e.target.value='';}});
 document.addEventListener('visibilitychange',()=>{if(document.hidden)stopCamera();});window.addEventListener('pagehide',stopCamera);
})();
