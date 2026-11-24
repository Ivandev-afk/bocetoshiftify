// Aviso del turno en la página del cliente (turno.php).
//
// - Consulta estado_turno.php cada 5 s sin recargar la página: así no se pierde
//   el permiso de sonido, que los navegadores solo dan tras un toque del usuario.
// - Pre-aviso suave cuando queda 1 persona (o ninguna) antes.
// - Al llamarlo: timbre, vibración, notificación del sistema y título parpadeando.
// Limitaciones de los navegadores: el iPhone no vibra desde una página web, y si
// el navegador se cierra o el celular lo suspende mucho tiempo, no se puede sonar.
(function () {
  const script = document.currentScript;
  const urlEstado = script.dataset.estadoUrl;
  const turnoId = script.dataset.turnoId;
  const negocio = script.dataset.negocio;
  let estado = script.dataset.estado;

  const clavePreaviso = "shiftify-preaviso-" + turnoId;
  const tituloOriginal = document.title;
  let audio = null;
  let registroSw = null;
  let parpadeo = null;

  const $ = (id) => document.getElementById(id);

  // ---------- Sonido (generado con Web Audio: no hace falta un archivo) ----------
  function tono(frecuencia, inicio, duracion, volumen) {
    const osc = audio.createOscillator();
    const ganancia = audio.createGain();
    const t = audio.currentTime + inicio;
    osc.type = "sine";
    osc.frequency.value = frecuencia;
    ganancia.gain.setValueAtTime(0.0001, t);
    ganancia.gain.exponentialRampToValueAtTime(volumen, t + 0.02);
    ganancia.gain.exponentialRampToValueAtTime(0.0001, t + duracion);
    osc.connect(ganancia).connect(audio.destination);
    osc.start(t);
    osc.stop(t + duracion + 0.05);
  }

  function sonar(tipo) {
    if (!audio) return;
    audio.resume();
    if (tipo === "timbre") {
      // Ding-dong fuerte, tres veces
      for (let i = 0; i < 3; i++) {
        tono(1046, i * 1.6, 0.6, 0.6);
        tono(784, i * 1.6 + 0.45, 0.9, 0.6);
      }
    } else {
      // Dos pitidos suaves
      tono(880, 0, 0.25, 0.3);
      tono(880, 0.35, 0.25, 0.3);
    }
  }

  function vibrar(patron) {
    if (navigator.vibrate) navigator.vibrate(patron);
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
      revisar();
    }
  });

  // ---------- Botón «Activar aviso con sonido» ----------
  const boton = $("activar-aviso");
  const textoAviso = $("aviso-estado");

  if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("sw.js").then((r) => { registroSw = r; }).catch(() => {});
  }

  if (boton) {
    boton.addEventListener("click", () => {
      try {
        audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      } catch (e) {
        audio = null;
      }
      sonar("suave"); // prueba: el cliente oye cómo sonará
      vibrar(150);

      boton.disabled = true;
      boton.textContent = "✓ Aviso activado";
      textoAviso.textContent = "Deja esta página abierta: sonará cuando estés cerca y cuando te llamen.";

      if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
      }

      // Si ya estaba cerca al activar, el pre-aviso sale ahora
      revisar();
    });
  }

  // ---------- Pre-aviso y llamado ----------
  function preaviso(datos) {
    if (sessionStorage.getItem(clavePreaviso)) return;
    sessionStorage.setItem(clavePreaviso, "1");

    const cuerpo = datos.antes === 0 ? "¡Eres el siguiente! Acércate." : "Solo 1 persona antes de ti. Acércate.";
    sonar("suave");
    vibrar([200, 100, 200]);
    notificar("¡Ya casi! " + datos.codigo, cuerpo + " · " + negocio);
    if (document.hidden) parpadear("⏳ ¡Ya casi!");
  }

  function llamado(datos) {
    const caja = datos.caja || "ventanilla";
    sonar("timbre");
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
        if (datos.antes <= 1 && audio) preaviso(datos);
      }
    } catch (e) {
      // Sin conexión un momento: se reintenta en la próxima vuelta
    } finally {
      revisando = false;
    }
  }

  setInterval(revisar, 5000);
})();
