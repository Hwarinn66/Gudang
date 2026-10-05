'use strict';
(()=>{const d=window.productFormData,room=document.getElementById('room-select'),block=document.getElementById('block-select'),rack=document.getElementById('rack-select'),position=document.getElementById('position-select');
function fill(select,items,prompt){select.replaceChildren(new Option(prompt,''));for(const item of items){const o=new Option(item.label,item.id);o.disabled=!!item.disabled;select.add(o);}}
function positions(){fill(position,d.positions.filter(p=>p.active&&p.rack_id==rack.value).map(p=>({id:p.id,label:p.code+(p.occupant_id&&p.occupant_id!==d.product_id?' — terisi '+p.occupant:''),disabled:p.occupant_id&&p.occupant_id!==d.product_id})),'Pilih posisi');preview();}
function racks(){fill(rack,d.racks.filter(r=>r.active&&r.block_id==block.value).map(r=>({id:r.id,label:String(r.rack_number).padStart(2,'0')})),'Pilih rak');positions();}
function blocks(){fill(block,d.blocks.filter(b=>b.active&&b.room_id==room.value).map(b=>({id:b.id,label:b.code+' — '+b.name})),'Pilih blok');racks();}
function preview(){document.getElementById('location-preview').textContent=d.positions.find(p=>p.id==position.value)?.code||'Pilih lokasi';}
room.addEventListener('change',blocks);block.addEventListener('change',racks);rack.addEventListener('change',positions);position.addEventListener('change',preview);
const current=d.positions.find(p=>p.id==d.selected);blocks();if(current){room.value=current.room_id;blocks();block.value=current.block_id;racks();rack.value=current.rack_id;positions();position.value=current.id;preview();}
document.getElementById('product-form').addEventListener('submit',e=>{if(d.original&&position.value!=d.original){const from=d.positions.find(p=>p.id==d.original)?.code,to=d.positions.find(p=>p.id==position.value)?.code;if(!confirm(`Anda akan memindahkan ${d.label} dari ${from} ke ${to}. Pastikan posisi tujuan kosong.`)){e.preventDefault();return;}document.getElementById('confirm-move').value='1';}});
})();
