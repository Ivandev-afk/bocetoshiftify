// Aviso del turno en la página del cliente (turno.php).
//
// - Consulta estado_turno.php sin recargar la página (cada 5 s cerca del turno,
//   10–15 s si falta mucho): así no se pierde
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
  const clavePush = script.dataset.clavePush;
  const urlSuscribir = script.dataset.suscribirUrl;
  let estado = script.dataset.estado;
  let antes = Number(script.dataset.antes || 0);

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
    // Llegó un push (la caja lo llamó): ponerse al día ya, sin esperar la vuelta
    navigator.serviceWorker.addEventListener("message", (e) => {
      if (e.data && e.data.tipo === "push") revisar();
    });
  }

  // ---------- Push: el aviso llega aunque esté en otra app o con el celular bloqueado ----------
  function claveABytes(base64) {
    const relleno = "=".repeat((4 - (base64.length % 4)) % 4);
    const binario = atob((base64 + relleno).replace(/-/g, "+").replace(/_/g, "/"));
    return Uint8Array.from(binario, (c) => c.charCodeAt(0));
  }

  async function activarPush(pedirPermiso) {
    if (!("serviceWorker" in navigator) || !("PushManager" in window) || !("Notification" in window) || !clavePush) {
      return "sin-soporte";
    }
    let permiso = Notification.permission;
    if (permiso === "default" && pedirPermiso) permiso = await Notification.requestPermission();
    if (permiso !== "granted") return permiso;

    // Si el service worker no se pudo instalar, ready no termina nunca: se corta a los 8 s
    const registro = await Promise.race([
      navigator.serviceWorker.ready,
      new Promise((_, falla) => setTimeout(() => falla(new Error("el servicio de avisos no arrancó")), 8000)),
    ]);
    let suscripcion = await registro.pushManager.getSubscription();
    if (!suscripcion) {
      suscripcion = await registro.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: claveABytes(clavePush) });
    }
    const respuesta = await fetch(urlSuscribir, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({ endpoint: suscripcion.endpoint }),
    });
    if (!respuesta.ok) throw new Error("el servidor respondió " + respuesta.status);
    return "ok";
  }

  function mostrarPush(texto, tipo) {
    const linea = $("aviso-push");
    if (!linea) return;
    linea.hidden = false;
    linea.textContent = texto;
    linea.className = "turno-aviso__push" + (tipo ? " turno-aviso__push--" + tipo : "");
  }

  // Si ya dio permiso antes (otro turno), este turno queda suscrito sin preguntar
  if ("Notification" in window && Notification.permission === "granted") {
    activarPush(false).then((r) => {
      if (r === "ok") mostrarPush("✓ Aviso con el celular bloqueado activo para este turno.", "ok");
    }).catch(() => {});
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

      // Push: lo que permite avisar con el celular bloqueado o en otra app
      // Push: el resultado va en su propia línea, para que el texto del sonido no lo tape
      mostrarPush("⏳ Activando el aviso con el celular bloqueado…", "");
      activarPush(true).then((resultado) => {
        if (resultado === "ok") {
          mostrarPush("✓ Listo: te avisaremos aunque estés en otra app o con el celular bloqueado.", "ok");
        } else if (resultado === "denied") {
          mostrarPush("✗ Bloqueaste las notificaciones: solo sonará con esta página abierta. Permítelas en los ajustes del navegador (candado junto a la dirección) y vuelve a tocar el botón.", "error");
          boton.disabled = false;
          boton.textContent = "🔔 Activar aviso con sonido";
        } else if (resultado === "default") {
          mostrarPush("No respondiste al permiso de notificaciones. Vuelve a tocar el botón y elige «Permitir».", "error");
          boton.disabled = false;
          boton.textContent = "🔔 Activar aviso con sonido";
        } else if (resultado === "sin-soporte") {
          mostrarPush(/iPhone|iPad/.test(navigator.userAgent)
            ? "En iPhone: toca Compartir → «Añadir a pantalla de inicio», abre Shiftify desde ese ícono y activa el aviso otra vez."
            : "Este navegador no permite avisos con el celular bloqueado. Usa Chrome o deja esta página abierta.", "error");
        } else {
          mostrarPush("✗ No se pudo registrar el aviso. Recarga la página y vuelve a tocar el botón.", "error");
        }
      }).catch((e) => {
        mostrarPush("✗ No se pudo activar el aviso con el celular bloqueado (" + (e && e.message ? e.message : e) + "). Recarga y vuelve a intentarlo.", "error");
      });

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
        antes = datos.antes;
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

  // Cada cuánto preguntar: rápido cuando está cerca, más espaciado cuando falta.
  // Con mucha gente esperando, el servidor recibe muchas menos consultas.
  function intervalo() {
    if (estado !== "esperando" || antes <= 2) return 5000;
    if (antes <= 10) return 10000;
    return 15000;
  }

  function programar() {
    setTimeout(async () => {
      await revisar();
      programar();
    }, intervalo());
  }
  programar();
})();
