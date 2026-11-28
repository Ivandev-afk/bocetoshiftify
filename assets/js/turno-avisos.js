// Prepara los avisos push desde que el cliente pide el turno, para que suene
// aunque se vaya a otra app o bloquee el celular (incluidos los re-llamados).
//
// 1. Detecta dónde está abierta la página y avisa ANTES si ahí no puede sonar:
//    - Navegador interno de WhatsApp / Instagram / Facebook → «Abrir en Chrome».
//    - iPhone sin instalar → pasos para «Añadir a pantalla de inicio».
// 2. Al tocar «Tomar turno» pide el permiso de notificaciones (ese toque es el
//    que exigen los navegadores). En la página siguiente, aviso-turno.js
//    registra el celular solo, sin que el cliente tenga que hacer nada más.
(function () {
  const ua = navigator.userAgent;
  const esIphone = /iPhone|iPad|iPod/.test(ua);
  const esAndroid = /Android/.test(ua);
  const enApp = /FBAN|FBAV|FB_IAB|Instagram|WhatsApp|Line\/|; wv\)/.test(ua);
  const instalada = window.matchMedia("(display-mode: standalone)").matches || navigator.standalone === true;
  const conPush = "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;

  const caja = document.getElementById("avisos-ayuda");

  function ayuda(html) {
    if (!caja) return;
    caja.innerHTML = html;
    caja.hidden = false;
  }

  if (enApp) {
    // Android: abrir la misma dirección en Chrome. iPhone: copiar y abrir en Safari.
    const destino = location.href;
    const enChrome = "intent://" + location.host + location.pathname + location.search +
      "#Intent;scheme=https;package=com.android.chrome;S.browser_fallback_url=" + encodeURIComponent(destino) + ";end";
    ayuda(esAndroid
      ? "<strong>Ábrelo en Chrome para que suene aunque salgas.</strong> Desde aquí (WhatsApp, Instagram…) el aviso solo suena con esta página abierta. <a class=\"turno-ayuda__boton\" href=\"" + enChrome + "\">Abrir en Chrome</a>"
      : "<strong>Ábrelo en Safari para que suene aunque salgas.</strong> Toca <b>⋯</b> o el ícono de compartir y elige «Abrir en Safari». Desde aquí el aviso solo suena con esta página abierta.");
  } else if (esIphone && !instalada) {
    ayuda("<strong>En iPhone, para que suene aunque salgas:</strong> toca <b>Compartir ⬆️</b> → <b>«Añadir a pantalla de inicio»</b>, abre Shiftify desde ese ícono y pide tu turno ahí. Si no, solo sonará con esta página abierta.");
  } else if (!conPush) {
    ayuda("Este navegador no puede avisarte con el celular bloqueado: el aviso solo sonará con esta página abierta. Para que suene aunque salgas, usa <b>Chrome</b>.");
  }

  // Se instala el service worker desde ya, para que la suscripción sea inmediata
  if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("sw.js").catch(() => {});
  }

  // «Tomar turno»: primero el permiso, luego se envía el formulario
  const formulario = document.getElementById("form-pedir");
  if (!formulario || !conPush || Notification.permission !== "default") return;

  let enviando = false;
  formulario.addEventListener("submit", (evento) => {
    if (enviando) return;
    evento.preventDefault();
    enviando = true;
    // Si la persona no responde al permiso, el turno igual se pide a los 8 s
    const pedir = Notification.requestPermission().catch(() => "default");
    const limite = new Promise((listo) => setTimeout(listo, 8000));
    Promise.race([pedir, limite]).finally(() => formulario.submit());
  });
})();
