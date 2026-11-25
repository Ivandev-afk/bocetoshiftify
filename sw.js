// Service worker de Shiftify.
// - Recibe los avisos push del servidor y los muestra como notificación del
//   sistema, aunque el cliente esté en otra app o con el celular bloqueado.
// - Al tocar la notificación, abre (o trae al frente) la página del turno.
self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (evento) => evento.waitUntil(self.clients.claim()));

// El aviso llega sin contenido: se pregunta al servidor qué decir
self.addEventListener("push", (evento) => {
  evento.waitUntil((async () => {
    let datos = { titulo: "Shiftify", cuerpo: "Revisa tu turno.", url: "./", tag: "turno", llamado: false };
    try {
      const suscripcion = await self.registration.pushManager.getSubscription();
      const respuesta = await fetch(new URL("estado_push.php", self.registration.scope), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ endpoint: suscripcion ? suscripcion.endpoint : "" }),
        cache: "no-store",
      });
      datos = Object.assign(datos, await respuesta.json());
    } catch (e) {
      // Sin conexión: se muestra el aviso genérico (el navegador exige mostrar algo)
    }

    await self.registration.showNotification(datos.titulo, {
      body: datos.cuerpo,
      tag: datos.tag,
      renotify: true,
      // El llamado se queda en pantalla hasta que la persona lo toque
      requireInteraction: !!datos.llamado,
      vibrate: datos.llamado ? [600, 200, 600, 200, 900] : [200, 100, 200],
      icon: new URL("assets/img/shiftify-logo.png", self.registration.scope).href,
      badge: new URL("assets/img/shiftify-logo.png", self.registration.scope).href,
      data: { url: new URL(datos.url, self.registration.scope).href },
    });

    // Si la página del turno está abierta, que se ponga al día (suena el timbre)
    const ventanas = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
    ventanas.forEach((v) => v.postMessage({ tipo: "push" }));
  })());
});

self.addEventListener("notificationclick", (evento) => {
  evento.notification.close();
  const destino = (evento.notification.data && evento.notification.data.url) || self.registration.scope;
  evento.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((ventanas) => {
      const turno = ventanas.find((v) => v.url.includes("turno.php"));
      if (turno) return turno.focus();
      return self.clients.openWindow(destino);
    })
  );
});
