// Service worker mínimo de Shiftify: solo existe para poder mostrar
// notificaciones del sistema en Android (Chrome no permite new Notification()
// desde la página). Al tocar la notificación se vuelve a la página del turno.
self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (evento) => evento.waitUntil(self.clients.claim()));

self.addEventListener("notificationclick", (evento) => {
  evento.notification.close();
  evento.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((ventanas) => {
      const turno = ventanas.find((v) => v.url.includes("turno.php"));
      if (turno) return turno.focus();
      return self.clients.openWindow(evento.notification.data && evento.notification.data.url || "./");
    })
  );
});
