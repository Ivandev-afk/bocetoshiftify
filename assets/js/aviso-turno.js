// Aviso del turno en la página del cliente (turno.php).
//
// - Consulta estado_turno.php cada 5 s sin recargar la página: así no se pierde
//   el permiso de sonido, que los navegadores solo dan tras un toque del usuario.
// - Pre-aviso suave cuando queda 1 persona (o ninguna) antes.
// - Al llamarlo: timbre, vibración, notificación del sistema y título parpadeando.
//
// Por qué así:
// - El sonido va en un <audio> (un WAV generado aquí) y no con Web Audio: en
//   iPhone, Web Audio se calla con el interruptor de silencio y <audio> no.
// - Mientras espera se pide mantener la pantalla encendida (Wake Lock): con la
//   pantalla bloqueada el celular congela la página y no puede sonar.
// Límites de los navegadores: el iPhone no vibra desde una página web, y si el
// navegador se cierra no hay forma de sonar.
(function () {
  const script = document.currentScript;
  const urlEstado = script.dataset.estadoUrl;
  const turnoId = script.dataset.turnoId;
  const negocio = script.dataset.negocio;
  let estado = script.dataset.estado;

  const clavePreaviso = "shiftify-preaviso-" + turnoId;
  const tituloOriginal = document.title;
  let activado = false;
  let registroSw = null;
  let parpadeo = null;
  let bloqueoPantalla = null;

  const $ = (id) => document.getElementById(id);

  // ---------- Sonidos: WAV generados en el navegador (no hace falta subir archivos) ----------
  // notas: [frecuencia, inicio (s), duración (s)]
  function crearWav(notas, volumen) {
    const muestras = 22050;
    const total = Math.ceil(Math.max(...notas.map(([, i, d]) => i + d)) * muestras) + 1000;
    const datos = new Float32Array(total);
    for (const [frecuencia, inicio, duracion] of notas) {
      const desde = Math.floor(inicio * muestras);
      const largo = Math.floor(duracion * muestras);
      for (let n = 0; n < largo; n++) {
        const t = n / muestras;
        const ataque = Math.min(1, n / 200);
        const caida = Math.exp(-3 * t / duracion);
        datos[desde + n] += Math.sin(2 * Math.PI * frecuencia * t) * ataque * caida * volumen;
      }
    }
    const buffer = new ArrayBuffer(44 + total * 2);
    const vista = new DataView(buffer);
    const texto = (pos, s) => { for (let i = 0; i < s.length; i++) vista.setUint8(pos + i, s.charCodeAt(i)); };
    texto(0, "RIFF"); vista.setUint32(4, 36 + total * 2, true); texto(8, "WAVE");
    texto(12, "fmt "); vista.setUint32(16, 16, true); vista.setUint16(20, 1, true); vista.setUint16(22, 1, true);
    vista.setUint32(24, muestras, true); vista.setUint32(28, muestras * 2, true);
    vista.setUint16(32, 2, true); vista.setUint16(34, 16, true);
    texto(36, "data"); vista.setUint32(40, total * 2, true);
    for (let i = 0; i < total; i++) {
      vista.setInt16(44 + i * 2, Math.max(-1, Math.min(1, datos[i])) * 32767, true);
    }
    const audio = new Audio(URL.createObjectURL(new Blob([buffer], { type: "audio/wav" })));
    audio.preload = "auto";
    return audio;
  }

  // Ding-dong fuerte tres veces, y dos pitidos suaves para el pre-aviso
  const timbre = crearWav([
    [1046, 0, 0.6], [784, 0.45, 0.9],
    [1046, 1.6, 0.6], [784, 2.05, 0.9],
    [1046, 3.2, 0.6], [784, 3.65, 0.9],
  ], 0.9);
  const suave = crearWav([[880, 0, 0.25], [880, 0.35, 0.25]], 0.6);

  function sonar(audio) {
    if (!activado) return;
    audio.currentTime = 0;
    audio.play().catch(() => {});
  }

  // Un <audio> solo suena sin toque si antes sonó con uno: se "desbloquean" los dos
  function desbloquearSonidos() {
    timbre.muted = true;
    timbre.play().then(() => {
      timbre.pause();
      timbre.currentTime = 0;
      timbre.muted = false;
    }).catch(() => { timbre.muted = false; });
    suave.currentTime = 0;
    return suave.play(); // este sí se oye: es la prueba de volumen
  }

  function vibrar(patron) {
    if (navigator.vibrate) navigator.vibrate(patron);
  }

  // ---------- Pantalla encendida mientras espera ----------
  async function mantenerPantalla() {
    if (!("wakeLock" in navigator) || document.hidden) return;
    try {
      bloqueoPantalla = await navigator.wakeLock.request("screen");
    } catch (e) {
      // Batería baja o navegador que no lo permite: se sigue sin él
    }
  }

  // ---------- Notificación del sistema (sirve aunque esté en otra app) ----------
  function notificar(titulo, cuerpo) {
    if (!("Notification" in window) || Notification.permission !== "granted") return;
    const opciones = {
      body: cuerpo,
      tag: "turno-" + turnoId,
      renotify: true,
      vibrate: [400, 150, 400],
      data: { url: location.href },
    };
    if (registroSw) {
      registroSw.showNotification(titulo, opciones).catch(() => {});
    } else {
      try { new Notification(titulo, opciones); } catch (e) { /* Android exige service worker */ }
    }
  }

  // ---------- Título de la pestaña parpadeando hasta que vuelva a mirarla ----------
  function parpadear(texto) {
    clearInterval(parpadeo);
    let encendido = false;
    parpadeo = setInterval(() => {
      encendido = !encendido;
      document.title = encendido ? texto : tituloOriginal;
    }, 1000);
  }

  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) {
      clearInterval(parpadeo);
      document.title = tituloOriginal;
      // El navegador suelta el bloqueo de pantalla al salir: se pide otra vez
      if (activado) mantenerPantalla();
      revisar();
    }
  });

  // ---------- Botones «Activar aviso» y «Probar sonido» ----------
  const boton = $("activar-aviso");
  const botonPrueba = $("probar-sonido");
  const textoAviso = $("aviso-estado");

  if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("sw.js").then((r) => { registroSw = r; }).catch(() => {});
  }

  if (boton) {
    boton.addEventListener("click", () => {
      activado = true;
      desbloquearSonidos().then(() => {
        textoAviso.textContent = "¿Oíste el pitido? Si no, sube el volumen" +
          (/iPhone|iPad/.test(navigator.userAgent) ? " y quita el modo silencio." : " multimedia.") +
          " Deja esta página abierta con la pantalla encendida.";
      }).catch(() => {
        textoAviso.textContent = "Tu navegador no dejó sonar. Toca «Probar sonido».";
      });
      vibrar(150);
      mantenerPantalla();

      boton.disabled = true;
      boton.textContent = "✓ Aviso activado";
      if (botonPrueba) botonPrueba.hidden = false;

      if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
      }

      // Si ya estaba cerca al activar, el pre-aviso sale ahora
      revisar();
    });
  }

  if (botonPrueba) {
    botonPrueba.addEventListener("click", () => {
      timbre.currentTime = 0;
      timbre.play().catch(() => {});
      vibrar([300, 100, 300]);
    });
  }

  // ---------- Pre-aviso y llamado ----------
  function preaviso(datos) {
    if (sessionStorage.getItem(clavePreaviso)) return;
    sessionStorage.setItem(clavePreaviso, "1");

    const cuerpo = datos.antes === 0 ? "¡Eres el siguiente! Acércate." : "Solo 1 persona antes de ti. Acércate.";
    sonar(suave);
    vibrar([200, 100, 200]);
    notificar("¡Ya casi! " + datos.codigo, cuerpo + " · " + negocio);
    if (document.hidden) parpadear("⏳ ¡Ya casi!");
  }

  function llamado(datos) {
    const caja = datos.caja || "ventanilla";
    sonar(timbre);
    vibrar([600, 200, 600, 200, 900]);
    notificar("🔔 ¡Te toca! " + datos.codigo, "Acércate a la " + caja + " · " + negocio);
    parpadear("🔔 ¡Te toca!");
    // El timbre dura ~5 s; luego se muestra la pantalla verde del llamado
    setTimeout(() => location.reload(), 5500);
  }

  // Cambia los números de la fila sin recargar
  function pintar(datos) {
    const antes = $("turno-antes");
    if (!antes) return;
    antes.textContent = datos.antes;
    $("turno-antes-texto").textContent = datos.antes === 1 ? "persona antes" : "personas antes";
    $("turno-minutos").textContent = "~" + datos.minutos;
    $("turno-estado").textContent = datos.antes === 0 ? "¡Eres el siguiente!" : "En la fila";

    const caja = $("turno-caja");
    caja.textContent = datos.caja ? "Te atenderá la " : "Te llamará la primera caja que se desocupe";
    if (datos.caja) {
      const fuerte = document.createElement("strong");
      fuerte.textContent = datos.caja;
      caja.appendChild(fuerte);
    }
  }

  let revisando = false;
  async function revisar() {
    if (revisando) return;
    revisando = true;
    try {
      const respuesta = await fetch(urlEstado, { cache: "no-store", credentials: "same-origin" });
      const datos = await respuesta.json();

      if (datos.estado !== estado) {
        const anterior = estado;
        estado = datos.estado;
        if (datos.estado === "atendiendo" && anterior === "esperando") {
          llamado(datos);
        } else {
          // Atendido, cancelado o vencido: la página muestra la pantalla que toca
          location.reload();
        }
        return;
      }

      if (datos.estado === "esperando") {
        pintar(datos);
        // Solo con el aviso activado: si no, se guardaría como dado sin haber sonado
        if (datos.antes <= 1 && activado) preaviso(datos);
      }
    } catch (e) {
      // Sin conexión un momento: se reintenta en la próxima vuelta
    } finally {
      revisando = false;
    }
  }

  setInterval(revisar, 5000);
})();
