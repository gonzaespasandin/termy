# Manejo de Errores en el Totem — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reducir el tiempo de espera ante un servidor caído, mostrar un mensaje claro de "llame a un asistente" cuando no se puede generar un turno, mejorar el mensaje de error de impresión, y detectar (best-effort) cuando la impresora KPOS 58 se queda sin papel.

**Architecture:** Cuatro cambios independientes pero relacionados: (1) timeout global en el cliente axios (`frontend/src/api/index.js`), (2) un overlay full-screen nuevo en `HomeView.vue` para errores de red/timeout/5xx al crear el turno, (3) mensajes de `printError` actualizados para indicar que el turno ya fue registrado, y (4) detección de `PRINTER_STATUS_PAPER_OUT` en `print_ticket.py` propagada a través de `ticketPrinter.js` hasta el frontend.

**Tech Stack:** Vue 3 (`<script setup>`, Composition API), Axios, Electron 28 (proceso hijo Python), pywin32 (`win32print`).

**Spec:** `docs/superpowers/specs/2026-06-10-totem-error-handling-design.md`

---

## Notas sobre testing

Este proyecto frontend no tiene un test runner configurado (`npm run build` es la única validación automatizada disponible). El microservice tampoco tiene tests automatizados para el flujo de impresión. Por lo tanto, la verificación de cada tarea combina:

- **Sintaxis/build**: `npm run build` (frontend), `node --check` (JS de Electron), `python -m py_compile` (script Python).
- **Verificación manual**: pasos explícitos para probar en el navegador/totem, incluyendo el escenario "servidor apagado" y "impresora sin papel" (el usuario tiene acceso físico a la KPOS 58 para sacar el papel y probar).

---

### Task 1: Timeout global en el cliente axios

**Files:**
- Modify: `frontend/src/api/index.js:3-9`

- [ ] **Step 1: Agregar `timeout: 8000` a la config de axios**

Reemplazar:

```js
const api = axios.create({
    baseURL: 'http://192.168.0.51:8000/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});
```

por:

```js
const api = axios.create({
    baseURL: 'http://192.168.0.51:8000/api',
    timeout: 8000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});
```

- [ ] **Step 2: Verificar build**

Run: `cd frontend && npm run build`
Expected: build exitoso, sin errores.

- [ ] **Step 3: Commit**

```bash
git add frontend/src/api/index.js
git commit -m "feat(api): add 8s timeout to axios client to fail fast when server is unreachable"
```

---

### Task 2: Estado y lógica del modal de error de conexión (HomeView script)

**Files:**
- Modify: `frontend/src/views/HomeView.vue` (script setup, líneas ~338-550)

- [ ] **Step 1: Agregar el ref `showConnectionError`**

En `frontend/src/views/HomeView.vue`, junto a los demás refs de estado de turno (línea 338, después de `const generatedTurn = ref(null);`):

```js
const generatedTurn = ref(null);
const showConnectionError = ref(false);
```

- [ ] **Step 2: Agregar el helper `isConnectionError`**

Justo antes de `const confirmTurn = async () => {` (línea 531), agregar:

```js
// Distingue errores de red/timeout/5xx (servidor inalcanzable) de errores
// de validación (4xx), que se siguen mostrando como toast.
const isConnectionError = (error) => {
  return !error.response || error.response.status >= 500;
};
```

- [ ] **Step 3: Modificar `confirmTurn()` para activar el modal de conexión**

Reemplazar el cuerpo actual de `confirmTurn`:

```js
const confirmTurn = async () => {
  if (confirming.value) return;
  confirming.value = true;
  try {
    const finalCustomerDni = askCustomerDni.value ? (customerDni.value || '') : '';
    const response = await createTurn({
      service_id: selectedService.value.id,
      customer_dni: finalCustomerDni,
      totem_id: totemId.value,
    });
    generatedTurn.value = response.data;
    selectedService.value = null;
    await printTicket();
  } catch (error) {
    console.error('Error creando turno:', error);
    showError('Error al crear turno');
  } finally {
    confirming.value = false;
  }
};
```

por:

```js
const confirmTurn = async () => {
  if (confirming.value) return;
  confirming.value = true;
  try {
    const finalCustomerDni = askCustomerDni.value ? (customerDni.value || '') : '';
    const response = await createTurn({
      service_id: selectedService.value.id,
      customer_dni: finalCustomerDni,
      totem_id: totemId.value,
    });
    generatedTurn.value = response.data;
    selectedService.value = null;
    showConnectionError.value = false;
    await printTicket();
  } catch (error) {
    console.error('Error creando turno:', error);
    if (isConnectionError(error)) {
      showConnectionError.value = true;
    } else {
      showError('Error al crear turno');
    }
  } finally {
    confirming.value = false;
  }
};
```

- [ ] **Step 4: Agregar `cancelConnectionError()`**

Justo después de `confirmTurn`, antes de `const printTicket = async () => {` (línea 552), agregar:

```js
const cancelConnectionError = () => {
  showConnectionError.value = false;
  selectedService.value = null;
  customerDni.value = '';
  showConfirmModal.value = false;
};
```

- [ ] **Step 5: Verificar build**

Run: `cd frontend && npm run build`
Expected: build exitoso, sin errores. (El modal del Task 3 todavía no existe, pero las funciones nuevas no rompen nada al no estar referenciadas en el template aún... en realidad sí estarán referenciadas recién en el Task 3 — si Vite se queja de variables no usadas en el `<script setup>`, es solo un warning de lint, no bloquea el build).

- [ ] **Step 6: Commit**

```bash
git add frontend/src/views/HomeView.vue
git commit -m "feat(totem): detect connection/5xx errors separately when creating a turn"
```

---

### Task 3: Modal de error de conexión (template + estilos) y prueba manual

**Files:**
- Modify: `frontend/src/views/HomeView.vue` (template, después de la línea 222 / antes del `<!-- ═══════════ MODAL: TICKET GENERADO ═══════════ -->`)
- Modify: `frontend/src/views/HomeView.vue` (`<style scoped>`, agregar bloque nuevo)

- [ ] **Step 1: Agregar el template del overlay**

Insertar el siguiente bloque inmediatamente después del cierre del Modal 2 (línea 222, `</transition>` que cierra el modal DNI) y antes de `<!-- ═══════════ MODAL: TICKET GENERADO ═══════════ -->` (línea 224):

```html
    <!-- ═══════════ OVERLAY: ERROR DE CONEXIÓN ═══════════ -->
    <transition name="modal-fade">
      <div v-if="showConnectionError" class="connection-error-overlay">
        <div class="connection-error-icon">
          <i class="fa fa-triangle-exclamation"></i>
        </div>
        <h2 class="connection-error-title">No se pudo generar el turno</h2>
        <p class="connection-error-text">Por favor, solicite ayuda a un asistente.</p>
        <div class="connection-error-actions">
          <button
            class="btn-primary connection-error-btn"
            :disabled="confirming"
            @click="confirmTurn"
          >
            <i :class="confirming ? 'fa fa-spinner fa-spin' : 'fa fa-rotate-right'"></i>
            {{ confirming ? 'Reintentando...' : 'Reintentar' }}
          </button>
          <button class="btn-secondary connection-error-btn" @click="cancelConnectionError">
            <i class="fa fa-house"></i>
            Volver al inicio
          </button>
        </div>
      </div>
    </transition>
```

- [ ] **Step 2: Agregar los estilos del overlay**

Al final del bloque `<style scoped>` (después de la regla `.keypad-key--confirm:disabled { ... }`, antes del cierre `</style>`), agregar:

```css
/* ═══════════════════════════════════════
   OVERLAY: ERROR DE CONEXIÓN
═══════════════════════════════════════ */
.connection-error-overlay {
  position: fixed;
  inset: 0;
  z-index: 200;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 2rem;
  color: #ffffff;
}

.connection-error-icon {
  font-size: 5rem;
  color: #ef4444;
  margin-bottom: 1.5rem;
  animation: connection-error-pulse 2s infinite;
}

@keyframes connection-error-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.7; transform: scale(0.95); }
}

.connection-error-title {
  font-size: 2.5rem;
  font-weight: 800;
  margin: 0 0 1rem;
}

.connection-error-text {
  font-size: 1.4rem;
  color: rgba(255, 255, 255, 0.8);
  margin: 0 0 2.5rem;
}

.connection-error-actions {
  display: flex;
  gap: 1rem;
  width: 100%;
  max-width: 480px;
}

.connection-error-btn {
  font-size: 1.3rem;
  padding: 1.25rem 1.5rem;
}
```

- [ ] **Step 3: Verificar build**

Run: `cd frontend && npm run build`
Expected: build exitoso, sin errores.

- [ ] **Step 4: Prueba manual — servidor apagado**

1. Detener el backend Laravel (`Ctrl+C` en `php artisan serve`).
2. Abrir el totem en el navegador (`npm run dev` en `frontend/`, ir a `/`).
3. Si la pantalla ya cargó servicios (cacheados o desde una corrida previa), tocar una card de servicio.
4. **Flujo sin DNI** (`ask_customer_dni = false`): confirmar en el Modal 1 → en ~8s debe aparecer el overlay full-screen "No se pudo generar el turno — Por favor, solicite ayuda a un asistente." con botones "Reintentar" y "Volver al inicio".
5. **Flujo con DNI** (`ask_customer_dni = true`): cargar 8 dígitos y confirmar → mismo resultado.
6. Tocar "Volver al inicio" → debe volver a la grilla de servicios sin overlays.
7. Repetir, esta vez tocar "Reintentar" → debe reintentar (`confirming` = true, botón dice "Reintentando...") y volver a fallar (servidor sigue apagado) → overlay sigue visible.
8. Levantar el backend de nuevo (`php artisan serve`) y tocar "Reintentar" → debe generar el turno correctamente y mostrar el modal de ticket.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/views/HomeView.vue
git commit -m "feat(totem): add full-screen connection error overlay with retry/home actions"
```

---

### Task 4: Detección de "sin papel" en `print_ticket.py`

**Files:**
- Modify: `microservice/print_ticket.py`

- [ ] **Step 1: Agregar constante y helper de estado de impresora**

Después de los imports (línea 19, después de `import win32con`), agregar:

```python

# PRINTER_STATUS_PAPER_OUT no siempre está expuesto segun la version de pywin32;
# 0x00000010 es el valor estandar de winspool.h
PRINTER_STATUS_PAPER_OUT = getattr(win32print, "PRINTER_STATUS_PAPER_OUT", 0x00000010)


def _printer_sin_papel(hprinter):
    """Devuelve True si el spooler reporta PRINTER_STATUS_PAPER_OUT para esta impresora.

    Best-effort: depende de que el driver de la impresora reporte el bit de
    estado correctamente. Si la consulta falla, devuelve False (no rompe el flujo).
    """
    if not hprinter:
        return False
    try:
        info = win32print.GetPrinter(hprinter, 2)
        status = info.get("Status", 0)
        return bool(status & PRINTER_STATUS_PAPER_OUT)
    except Exception:
        return False
```

- [ ] **Step 2: Usar `hprinter` para chequear el estado al final de `imprimir_ticket`**

Reemplazar el bloque final de `imprimir_ticket` (líneas 144-154):

```python
        # 8. Finalizar
        dc.EndPage()
        dc.EndDoc()
        dc.DeleteDC()
        
        print(f"[OK] Ticket {ticket_numero} impreso correctamente")
        return True

    except Exception as e:
        print(f"[ERROR] Error al imprimir: {str(e)}", file=sys.stderr)
        return False
```

por:

```python
        # 8. Finalizar
        dc.EndPage()
        dc.EndDoc()
        dc.DeleteDC()

        if _printer_sin_papel(hprinter):
            win32print.ClosePrinter(hprinter)
            print("[ERROR] Impresora sin papel", file=sys.stderr)
            print("SIN_PAPEL", file=sys.stderr)
            return False

        win32print.ClosePrinter(hprinter)
        print(f"[OK] Ticket {ticket_numero} impreso correctamente")
        return True

    except Exception as e:
        print(f"[ERROR] Error al imprimir: {str(e)}", file=sys.stderr)
        if _printer_sin_papel(hprinter):
            print("SIN_PAPEL", file=sys.stderr)
        return False
```

- [ ] **Step 3: Inicializar `hprinter` antes del `try` para que esté definido en el `except`**

En `imprimir_ticket`, justo antes de la línea `try:` (línea 26), agregar:

```python
    hprinter = None
    try:
```

(Esto reemplaza la línea `try:` existente — queda `hprinter = None` en la línea anterior.)

- [ ] **Step 4: Verificar sintaxis**

Run: `cd microservice && python -m py_compile print_ticket.py`
Expected: sin output (compila sin errores). Si `python` no está en el PATH, usar la ruta configurada en `PYTHON_EXE` del `.env` del totem.

- [ ] **Step 5: Commit**

```bash
git add microservice/print_ticket.py
git commit -m "feat(print): detect PRINTER_STATUS_PAPER_OUT after printing and emit SIN_PAPEL marker"
```

---

### Task 5: Propagar el error `SIN_PAPEL` en `ticketPrinter.js`

**Files:**
- Modify: `microservice/src/print/ticketPrinter.js:107-115`

- [ ] **Step 1: Detectar el marcador `SIN_PAPEL` en stderr**

Reemplazar:

```js
    python.on('close', (code) => {
      if (code === 0) {
        console.log(`✅ Ticket ${ticket_number} impreso correctamente`);
        resolve();
      } else {
        console.error(`❌ Error al imprimir (código ${code})`);
        reject(new Error(stderr || `Error de impresión (código ${code})`));
      }
    });
```

por:

```js
    python.on('close', (code) => {
      if (code === 0) {
        console.log(`✅ Ticket ${ticket_number} impreso correctamente`);
        resolve();
      } else {
        console.error(`❌ Error al imprimir (código ${code})`);
        if (stderr.includes('SIN_PAPEL')) {
          reject(new Error('SIN_PAPEL'));
        } else {
          reject(new Error(stderr || `Error de impresión (código ${code})`));
        }
      }
    });
```

- [ ] **Step 2: Verificar sintaxis**

Run: `cd microservice && node --check src/print/ticketPrinter.js`
Expected: sin output (sintaxis válida).

- [ ] **Step 3: Commit**

```bash
git add microservice/src/print/ticketPrinter.js
git commit -m "feat(print): propagate SIN_PAPEL error from print_ticket.py to renderer"
```

---

### Task 6: Mensajes de error de impresión en `HomeView.vue` + prueba con impresora real

**Files:**
- Modify: `frontend/src/views/HomeView.vue:569-571`

- [ ] **Step 1: Diferenciar el mensaje de "sin papel" del mensaje genérico**

Reemplazar:

```js
  } catch (error) {
    printError.value = error.message || 'Error desconocido al imprimir';
  } finally {
```

por:

```js
  } catch (error) {
    if (error.message === 'SIN_PAPEL') {
      printError.value = `⚠️ Sin papel en la impresora. Su turno ${generatedTurn.value.ticket_number} fue registrado — avise a un asistente para recargar la impresora y recibir su comprobante.`;
    } else {
      printError.value = `No se pudo imprimir el ticket. Su turno ${generatedTurn.value.ticket_number} fue registrado igual — avise a un asistente para recibir su comprobante.`;
    }
  } finally {
```

- [ ] **Step 2: Verificar build**

Run: `cd frontend && npm run build`
Expected: build exitoso, sin errores.

- [ ] **Step 3: Commit**

```bash
git add frontend/src/views/HomeView.vue
git commit -m "feat(totem): show ticket-aware print error messages, including out-of-paper"
```

- [ ] **Step 4: Prueba manual con la impresora KPOS 58 (requiere hardware)**

Esta prueba requiere correr la app de Electron (`microservice/`) conectada a la KPOS 58, ya que el flujo de impresión vía navegador no pasa por `print_ticket.py`.

1. `cd microservice && npm run dev` (con `SERVER_URL` apuntando al frontend con el backend corriendo).
2. Con papel cargado, generar un turno y confirmar que imprime y muestra "Ticket impreso correctamente".
3. **Sacar el papel de la impresora.**
4. Generar otro turno e imprimir.
   - Si el driver de la KPOS 58 reporta `PRINTER_STATUS_PAPER_OUT` correctamente: debe verse el mensaje "⚠️ Sin papel en la impresora. Su turno {ticket} fue registrado — avise a un asistente para recargar la impresora y recibir su comprobante."
   - Si el driver **no** reporta ese bit (limitación documentada en el spec): debe verse el mensaje genérico "No se pudo imprimir el ticket. Su turno {ticket} fue registrado igual — avise a un asistente para recibir su comprobante." (o puede que la impresión "tenga éxito" sin papel — en ese caso, no hay forma de detectarlo con esta impresora/driver y queda documentado como limitación conocida).
5. Volver a cargar papel y tocar "Imprimir Ticket" (reintento) → debe imprimir correctamente y mostrar "Ticket impreso correctamente".
6. Reportar el resultado (cuál de los dos casos ocurrió) para dejarlo documentado en el spec si hace falta.

---

## Resumen de archivos modificados

- `frontend/src/api/index.js` — timeout de 8s
- `frontend/src/views/HomeView.vue` — overlay de error de conexión, mensajes de impresión
- `microservice/print_ticket.py` — detección de `PRINTER_STATUS_PAPER_OUT`
- `microservice/src/print/ticketPrinter.js` — propagación de `SIN_PAPEL`
