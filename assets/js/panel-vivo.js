// Panel de atención en vivo: cada 10 s pide panel.php por detrás y cambia solo
// las secciones marcadas con data-vivo (métricas, cajas y fila).
// Antes se recargaba la página entera cada 20 s: parpadeaba, volvía arriba y
// tardaba más. Una sección donde el asesor está escribiendo no se toca.
// La misma petición sirve de «sigo aquí» para que la caja no se libere sola.
(function () {
  const CADA = 10000;
  let ocupado = false;

  async function actualizar() {
    if (ocupado) return;
    ocupado = true;
    try {
      const respuesta = await fetch(location.pathname, { cache: "no-store", credentials: "same-origin" });
      // La sesión venció: el servidor manda al login
      if (respuesta.redirected || !respuesta.ok) {
        location.reload();
        return;
      }
      const nuevo = new DOMParser().parseFromString(await respuesta.text(), "text/html");

      document.querySelectorAll("[data-vivo]").forEach((seccion) => {
        if (seccion.contains(document.activeElement) && document.activeElement !== document.body) return;
        const reemplazo = nuevo.getElementById(seccion.id);
        if (reemplazo && reemplazo.innerHTML !== seccion.innerHTML) {
          seccion.innerHTML = reemplazo.innerHTML;
        }
      });
    } catch (e) {
      // Sin conexión un momento: se reintenta en la próxima vuelta
    } finally {
      ocupado = false;
    }
  }

  // Re-llamado automático: a quien no respondió «Ya voy», otro aviso cada 30 s (hasta 3).
  // No se espera la respuesta: el servidor contesta de una y manda los avisos después.
  function rellamar() {
    fetch("rellamar.php", { method: "POST", credentials: "same-origin" }).catch(() => {});
  }

  setInterval(() => {
    rellamar();
    actualizar();
  }, CADA);
  // Al volver a la pestaña, al día de inmediato
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) actualizar();
  });
})();
