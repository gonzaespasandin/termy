# Spec: Manejo de errores en el totem (servidor caído / impresora)

**Fecha:** 2026-06-10
**Estado:** Aprobado

---

## Problema

Al intentar generar/imprimir un ticket con el backend apagado (probado tanto con `ask_customer_dni = true` como `false`), el totem queda "colgado" mucho tiempo antes de mostrar "Error al crear turno". Además:

1. El cliente axios no tiene `timeout` configurado (default = sin límite). Si el servidor está apagado/inalcanzable, el navegador espera el timeout TCP del SO de Windows (puede ser 20-60+ segundos) antes de que la promesa rechace.
2. El error actual es un toast genérico de 4 segundos ("Error al crear turno") que no le indica al cliente del totem qué hacer.
3. No existe forma de detectar/avisar cuando la impresora térmica (KPOS 58) se queda sin papel — `EndDoc()` puede devolver éxito aunque no haya salido nada en papel.

---

## Solución

Cuatro cambios independientes pero relacionados:

1. **Timeout global de axios** (8s) en `frontend/src/api/index.js`.
2. **Modal de pantalla completa** "No se pudo generar el turno — llame a un asistente" para errores de red/timeout en `confirmTurn()`, con botones "Reintentar" y "Volver al inicio". Los errores de validación (422) siguen usando el toast actual.
3. **Mensaje de impresión actualizado**: si falla la impresión (el turno ya existe), el área `printError` del modal de ticket indica que el turno fue registrado y que avise a un asistente para recibir el comprobante.
4. **Detección best-effort de "sin papel"** en `print_ticket.py` vía `win32print.GetPrinter()`, propagada hasta el frontend con un mensaje específico.

---

## 1. Timeout global de axios

**Archivo:** `frontend/src/api/index.js`

Agregar `timeout: 8000` a la config de `axios.create({...})`. Aplica a todas las llamadas del cliente (totem, operador, admin, display). 8 segundos es ampliamente suficiente para LAN y reduce drásticamente la espera con servidor caído (de hasta 60s+ a 8s).

No se agrega manejo especial de `ECONNABORTED` en el interceptor global — cada vista sigue manejando sus propios catch. Para `HomeView.vue` esto se usa en el punto 2.

---

## 2. Modal de error de creación de turno

**Archivo:** `frontend/src/views/HomeView.vue`

### Cuándo se activa

En `confirmTurn()`, el `catch` actual:
```js
catch (error) {
  console.error('Error creando turno:', error);
  showError('Error al crear turno');
}
```

se reemplaza por una distinción:

- **Error de red/timeout** (sin `error.response`, o `error.code === 'ECONNABORTED'`, o `error.response.status >= 500`) → activa el nuevo modal full-screen `showConnectionError`.
- **Error de validación** (`error.response.status` 4xx, ej. 422) → mantiene el toast actual (`showError`).

### Contenido del modal

Overlay full-screen, mismo estilo glassmorphism que los modales existentes (`.modal-overlay` / `.modal-card`), pero ocupando toda la pantalla y con tipografía grande (legible para el cliente del totem):

- Ícono de advertencia grande (⚠️ o similar, animación pulse como en `error.html`)
- Título: "No se pudo generar el turno"
- Texto: "Por favor, solicite ayuda a un asistente."
- Dos botones:
  - **Reintentar** → vuelve a llamar `confirmTurn()` con el `selectedService`/`customerDni`/`askCustomerDni` que ya estaban cargados (no se pierde la selección).
  - **Volver al inicio** → cierra el modal (`showConnectionError = false`) y resetea `selectedService = null`, `customerDni = ''`, `showConfirmModal = false`, volviendo a la grilla de servicios.

### Estado nuevo

```js
const showConnectionError = ref(false);
```

Se activa/desactiva solo en el catch de `confirmTurn()` y en los dos botones del modal.

---

## 3. Mensaje de error de impresión

**Archivo:** `frontend/src/views/HomeView.vue`

El turno **ya fue creado** en este punto — solo falló la impresión física. No se usa el modal full-screen del punto 2 (sería confuso, sugeriría que no hay turno).

Se actualiza el texto mostrado en `printError` (sección `.print-status--error` del modal de ticket) para incluir el número de ticket y la indicación de pedir ayuda:

> "No se pudo imprimir el ticket. Su turno **{{ generatedTurn.ticket_number }}** fue registrado igual — avise a un asistente para recibir su comprobante."

Si el error es específicamente "sin papel" (ver punto 4), el mensaje cambia a uno más específico.

El botón "Imprimir Ticket" existente (`resetAndPrint`) sigue funcionando como reintento — no se agrega lógica nueva de reintento.

---

## 4. Detección de "sin papel" (KPOS 58)

**Archivos:** `microservice/print_ticket.py`, `microservice/src/print/ticketPrinter.js`, `frontend/src/views/HomeView.vue`

### Backend de impresión (`print_ticket.py`)

Después de `EndDoc()` (o si `StartDoc`/`StartPage` fallan), consultar el estado del spooler con `win32print.OpenPrinter()` + `win32print.GetPrinter(handle, 2)`, leyendo el campo `Status` (bitmask de `PRINTER_INFO_2`).

Revisar los flags relevantes:
- `win32print.PRINTER_STATUS_PAPER_OUT` (0x00000010)
- `win32print.PRINTER_STATUS_OFFLINE` (0x00000080)
- `win32print.PRINTER_STATUS_ERROR` (0x00000002)
- `win32print.PRINTER_STATUS_NOT_AVAILABLE` (0x00001000)

Si `PAPER_OUT` está presente, el script termina con un mensaje específico distinguible (ej. imprime a `stderr`: `"SIN_PAPEL: ..."` y `sys.exit(1)`), independientemente de si `EndDoc()` reportó éxito o no.

**Nota de confiabilidad**: esto depende de que el driver de la KPOS 58 reporte estos bits correctamente vía el spooler de Windows. El usuario validará esto sacando el papel y probando una impresión real. Si el driver no reporta `PAPER_OUT` de forma confiable, este chequeo simplemente no se activa y el flujo cae al error genérico de impresión (punto 3) — no rompe nada agregarlo.

### `ticketPrinter.js`

En el `catch`/`reject` cuando el proceso Python termina con código != 0, si `stderr` contiene el prefijo `"SIN_PAPEL"`, rechazar con un `Error` cuyo `message` sea un string reconocible, ej. `"SIN_PAPEL"` (o un objeto `{ code: 'SIN_PAPEL', message: '...' }` si es más simple mantener consistencia con el resto del archivo — se decide en la fase de implementación según el patrón actual del archivo).

### Frontend (`HomeView.vue`)

En `printTicket()`, si `error.message` (o el código) indica `SIN_PAPEL`, mostrar en `printError`:

> "⚠️ Sin papel en la impresora. Su turno **{{ generatedTurn.ticket_number }}** fue registrado — avise a un asistente para recargar la impresora y recibir su comprobante."

Para cualquier otro error de impresión, se usa el mensaje genérico del punto 3.

---

## Fuera de alcance

- No se modifica el flujo de `confirmTurn()` para validaciones 422 (sigue con el toast existente).
- No se agrega reintento automático/polling — todos los reintentos son manuales (botón).
- No se toca `microservice/src/error.html` ni `main.js` (ya resueltos en una sesión anterior).
- No se agrega configuración en `.env` para el timeout de axios — se deja como constante (8000ms) en `api/index.js`, consistente con que es un valor de UX, no de despliegue.
