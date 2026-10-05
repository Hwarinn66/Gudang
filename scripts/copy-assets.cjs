const fs = require('node:fs');
fs.copyFileSync('node_modules/html5-qrcode/html5-qrcode.min.js','public/assets/html5-qrcode.min.js');
fs.copyFileSync('node_modules/html5-qrcode/LICENSE','public/assets/html5-qrcode.LICENSE');
fs.copyFileSync('node_modules/tailwindcss/LICENSE','public/assets/tailwindcss.LICENSE');
