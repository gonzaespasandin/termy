# Display View Redesign — Spec

**Fecha:** 2026-06-02  
**Estado:** Aprobado  
**Scope:** Solo `frontend/src/views/DisplayView.vue` — sin cambios en backend ni en panel de administración.

---

## Objetivo

Rediseñar la pantalla pública de display para que sea legible en un televisor. El diseño actual usa fondo oscuro y tipografías pequeñas. El nuevo diseño usa fondo claro, tipografía grande, y reemplaza el panel de información secundaria (pendientes, desglose por servicio) por una zona de video con placeholder.

---

## Layout

### Estructura general

```
┌─────────────────────────────────────────────────────┐
│  BARRA SUPERIOR (7% alto)                           │
│  Reloj | Fecha | Clima (placeholder)                │
├─────────────────────────────┬───────────────────────┤
│                             │                       │
│   PANEL TURNOS (70%)        │   PANEL VIDEO (30%)   │
│                             │                       │
│   Hero card                 │   Placeholder 9:16    │
│   (turno llamado actual)    │   con instrucciones   │
│                             │   de resolución       │
│   Historial                 │                       │
│   (últimos 3 llamados)      │                       │
│                             │                       │
└─────────────────────────────┴───────────────────────┘
```

Proporciones en TV 1920×1080:
- Barra superior: ~76px de alto
- Panel turnos: 1344px de ancho (~70%)
- Panel video: 576px de ancho (~30%), altura ~1004px → proporción 9:16 exacta

---

## Paleta de colores (tema claro)

| Token | Valor | Uso |
|---|---|---|
| `--bg-root` | `#f8fafc` | Fondo del área de contenido |
| `--bg-card` | `#ffffff` | Cards (hero + historial) |
| `--bg-topbar` | `#1e40af` | Barra superior |
| `--bg-video` | `#1e293b` | Panel de video |
| `--text-primary` | `#0f172a` | Números de ticket, nombre de estación |
| `--text-secondary` | `#334155` | Nombre de la estación (hero) |
| `--text-muted` | `#64748b` | Labels, etiquetas secundarias |
| `--text-faint` | `#94a3b8` | Hora de llamado, separadores |
| `--divider` | `#e2e8f0` | Línea entre paneles |
| `--svc-color` | CSS var por turno | Color del servicio (proviene de `service.color`) |

Los colores de servicio (`--svc-color`) se aplican en: borde izquierdo del hero card, borde izquierdo de history cards, punto del eyebrow, nombre del servicio en el hero.

---

## Componentes / Secciones

### 1. Barra superior

- **Izquierda:** Hora en formato `HH:MM:SS`, fuente monoespaciada, peso 900, color blanco
- **Centro:** Fecha en formato `Lunes 2 de junio de 2026`, mayúsculas, color `#bfdbfe`
- **Derecha:** Widget de clima — icono + temperatura + ciudad, encapsulado en pill con fondo semitransparente. Por ahora es un **placeholder estático** con datos hardcodeados ("–°C", "–"). La integración real con API de clima es un task futuro.

### 2. Hero card (turno actual)

Muestra `recentCalled[0]`. Si no hay turno llamado, muestra estado vacío.

Contenido:
- Eyebrow: punto pulsante animado + texto "LLAMANDO AHORA" en mayúsculas
- Nombre del servicio: `turn.service.name`, color = `turn.service.color`
- Número de ticket: `turn.ticket_number`, tipografía ~8.5vw, peso 900
- Nombre de la estación: `turn.window.name` directamente — **sin etiqueta "Ventanilla" fija**. Si no hay ventanilla asignada, no se muestra esta línea.
- Hora de llamado: "Llamado a las HH:MM" en texto pequeño, al pie del card
- Animación: `heroPulse` — sombra que respira en el color del servicio cada 2.5s
- Glow overlay: degradado radial sutil en el color del servicio (esquina derecha del card)

Estado vacío:
- Ícono + texto "Esperando próximo llamado..." con 40% opacidad

### 3. Historial (últimos llamados)

Muestra `recentCalled.slice(1, 4)` — máximo 3 cards anteriores.

Cada card contiene:
- Número de ticket (peso 900, ~1.6vw)
- Nombre del servicio (truncado con `text-overflow: ellipsis`)
- Nombre de la estación (`window.name`, sin etiqueta fija)
- Hora de llamado

Borde izquierdo en color del servicio.

### 4. Panel de video (placeholder)

Fondo oscuro `#1e293b`. Contenido centrado:
- Ícono ▶
- Texto "TU VIDEO AQUÍ" (letras mayúsculas)
- Descripción: "Subí tu video desde el panel de administración"
- Badge con resolución ideal: **576 × 1020 px**

Este panel no tiene interactividad. Cuando en el futuro se implemente la subida de video, este componente se reemplaza por un `<video>` con `loop autoplay muted`.

---

## Comportamiento de datos (sin cambios respecto al actual)

- Polling cada 2 segundos a `GET /api/turns/queue` (mantener `USE_SSE = false` por ahora)
- Notificación de sonido al detectar nuevo turno llamado (mantener lógica actual sin cambios)
- Reloj actualizado cada segundo vía `setInterval`

---

## Fuente

Mantener `'Plus Jakarta Sans'` (ya cargada en el proyecto).

---

## Lo que NO entra en este task

- Integración con API de clima (el widget queda como placeholder visual)
- Upload de video desde el admin (task separado)
- Cambios en el backend
- Cambios en otras vistas
- Activar SSE (`USE_SSE` se mantiene en `false`)

---

## Archivos a modificar

| Archivo | Cambio |
|---|---|
| `frontend/src/views/DisplayView.vue` | Reemplazar template + estilos completos. La lógica JS (`<script setup>`) se mantiene sin cambios excepto eliminar `waitingByService` computed (ya no se usa). |

---

## Criterios de aceptación

1. En un navegador a pantalla completa (1920×1080), el número de ticket del hero ocupa al menos 20% del alto de la pantalla.
2. El color del borde del hero card y el historial coincide con `service.color`.
3. El nombre de la estación muestra `window.name` sin prefijo "Ventanilla".
4. Si no hay turno llamado, el hero muestra el estado vacío (sin errores JS).
5. El panel de video muestra el placeholder con la resolución "576 × 1020 px".
6. La barra superior muestra el reloj actualizado en tiempo real.
7. No hay regresiones en otras vistas (HomeView, OperatorView, AdminView, LoginView).
