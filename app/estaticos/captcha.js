// «No soy un robot»: al tildar la casilla, el navegador busca el número n tal
// que sha256(sal + n) sea el desafío que mandó el servidor. Corre en un Web
// Worker para no trabar la página, y con un sha256 propio porque
// crypto.subtle no existe fuera de https (la copia de la LAN es http).
(() => {
  const cajas = document.querySelectorAll('[data-captcha]');
  if (!cajas.length) return;

  const trabajador = `
    const K = new Uint32Array([0x428a2f98,0x71374491,0xb5c0fbcf,0xe9b5dba5,0x3956c25b,0x59f111f1,0x923f82a4,0xab1c5ed5,0xd807aa98,0x12835b01,0x243185be,0x550c7dc3,0x72be5d74,0x80deb1fe,0x9bdc06a7,0xc19bf174,0xe49b69c1,0xefbe4786,0x0fc19dc6,0x240ca1cc,0x2de92c6f,0x4a7484aa,0x5cb0a9dc,0x76f988da,0x983e5152,0xa831c66d,0xb00327c8,0xbf597fc7,0xc6e00bf3,0xd5a79147,0x06ca6351,0x14292967,0x27b70a85,0x2e1b2138,0x4d2c6dfc,0x53380d13,0x650a7354,0x766a0abb,0x81c2c92e,0x92722c85,0xa2bfe8a1,0xa81a664b,0xc24b8b70,0xc76c51a3,0xd192e819,0xd6990624,0xf40e3585,0x106aa070,0x19a4c116,0x1e376c08,0x2748774c,0x34b0bcb5,0x391c0cb3,0x4ed8aa4a,0x5b9cca4f,0x682e6ff3,0x748f82ee,0x78a5636f,0x84c87814,0x8cc70208,0x90befffa,0xa4506ceb,0xbef9a3f7,0xc67178f2]);
    const W = new Uint32Array(64);
    function sha256hex(str) {
      const b = [];
      for (let i = 0; i < str.length; i++) b.push(str.charCodeAt(i) & 0xff);
      const l = b.length * 8;
      b.push(0x80);
      while (b.length % 64 !== 56) b.push(0);
      for (let i = 7; i >= 0; i--) b.push(i > 3 ? 0 : (l >>> (i * 8)) & 0xff);
      let h0=0x6a09e667,h1=0xbb67ae85,h2=0x3c6ef372,h3=0xa54ff53a,h4=0x510e527f,h5=0x9b05688c,h6=0x1f83d9ab,h7=0x5be0cd19;
      for (let o = 0; o < b.length; o += 64) {
        for (let t = 0; t < 16; t++) W[t] = (b[o+t*4]<<24)|(b[o+t*4+1]<<16)|(b[o+t*4+2]<<8)|b[o+t*4+3];
        for (let t = 16; t < 64; t++) {
          const x = W[t-15], y = W[t-2];
          const s0 = ((x>>>7)|(x<<25))^((x>>>18)|(x<<14))^(x>>>3);
          const s1 = ((y>>>17)|(y<<15))^((y>>>19)|(y<<13))^(y>>>10);
          W[t] = (W[t-16] + s0 + W[t-7] + s1) | 0;
        }
        let a=h0,c=h2,d=h3,e=h4,f=h5,g=h6,h=h7,bb=h1;
        for (let t = 0; t < 64; t++) {
          const S1 = ((e>>>6)|(e<<26))^((e>>>11)|(e<<21))^((e>>>25)|(e<<7));
          const t1 = (h + S1 + ((e&f)^(~e&g)) + K[t] + W[t]) | 0;
          const S0 = ((a>>>2)|(a<<30))^((a>>>13)|(a<<19))^((a>>>22)|(a<<10));
          const t2 = (S0 + ((a&bb)^(a&c)^(bb&c))) | 0;
          h=g; g=f; f=e; e=(d+t1)|0; d=c; c=bb; bb=a; a=(t1+t2)|0;
        }
        h0=(h0+a)|0; h1=(h1+bb)|0; h2=(h2+c)|0; h3=(h3+d)|0; h4=(h4+e)|0; h5=(h5+f)|0; h6=(h6+g)|0; h7=(h7+h)|0;
      }
      return [h0,h1,h2,h3,h4,h5,h6,h7].map(v => (v>>>0).toString(16).padStart(8,'0')).join('');
    }
    onmessage = (m) => {
      const { sal, desafio, max } = m.data;
      for (let n = 0; n <= max; n++) if (sha256hex(sal + n) === desafio) { postMessage(n); return; }
      postMessage(-1);
    };`;
  const url = URL.createObjectURL(new Blob([trabajador], { type: 'text/javascript' }));

  cajas.forEach((caja) => {
    const check = caja.querySelector('[data-captcha-check]');
    const texto = caja.querySelector('[data-captcha-texto]');
    const campo = caja.querySelector('input[name=captcha]');
    const form = caja.closest('form');
    let estado = 'nada';

    const resolver = () => new Promise((listo) => {
      if (estado === 'ok') return listo(true);
      estado = 'calculando';
      caja.classList.add('calculando');
      texto.textContent = 'Verificando…';
      const w = new Worker(url);
      w.onmessage = (m) => {
        w.terminate();
        caja.classList.remove('calculando');
        if (m.data < 0) {
          estado = 'nada'; check.checked = false; texto.textContent = 'No se pudo verificar. Recargá la página.';
          return listo(false);
        }
        campo.value = btoa(JSON.stringify({ sal: caja.dataset.sal, numero: m.data, desafio: caja.dataset.desafio, firma: caja.dataset.firma }));
        estado = 'ok'; check.checked = true; check.disabled = true;
        caja.classList.remove('falta');
        caja.classList.add('verificado');
        texto.textContent = 'Verificado';
        listo(true);
      };
      w.postMessage({ sal: caja.dataset.sal, desafio: caja.dataset.desafio, max: +caja.dataset.max });
    });

    check.addEventListener('change', () => {
      if (check.checked && estado === 'nada') resolver();
      if (estado === 'calculando') check.checked = true;
    });
    form.addEventListener('submit', (ev) => {
      if (estado === 'ok') return;
      ev.preventDefault();
      if (estado === 'nada') {
        caja.classList.add('falta');
        texto.textContent = 'Tildá «No soy un robot» para seguir';
        setTimeout(() => caja.classList.remove('falta'), 1200);
      }
    });
  });
})();
