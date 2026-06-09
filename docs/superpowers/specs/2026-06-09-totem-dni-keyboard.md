# Spec: DNI en Totem — Modal de confirmación + Teclado numérico

**Fecha:** 2026-06-09  
**Estado:** Aprobado

---

## Problema

El totem tiene dos problemas en el flujo de selección de servicio:

1. Con `ask_customer_name = false`: al tocar una card se genera el turno inmediatamente, sin confirmación. Cualquier toque accidental crea un turno.
2. Con `ask_customer_name = true`: se pide el nombre del cliente con un input de texto libre. En pantalla táctil escribir el nombre es lento e incómodo. El DNI (solo números) es más rápido de ingresar y más útil para identificar al cliente.

---

## Solución

Dos cambios independientes pero relacionados:

1. **Modal de confirmación** (flujo sin identificación): reemplaza el disparo inmediato por un modal simple de "¿Confirmar turno?" cuando no se pide DNI.
2. **Teclado numérico para DNI** (flujo con identificación): reemplaza el input de texto por un modal con teclado numérico custom en layout split horizontal.

Adicionalmente, se renombran todos los campos `customer_name` → `customer_dni` y `ask_customer_name` → `ask_customer_dni` en toda la codebase.

---

## Pantalla del totem

Horizontal, aprox. 16:10 (ej. 1600×1000). Los modales deben ser anchos y no muy altos.

---

## Diseño de datos

### Migraciones nuevas (no modificar las existentes)

| Tabla | Columna anterior | Columna nueva |
|---|---|---|
| `turns` | `customer_name` (string nullable) | `customer_dni` (string nullable) |
| `totems` | `ask_customer_name` (boolean, default true) | `ask_customer_dni` (boolean, default true) |

### Validación backend (TurnController)
- `customer_dni`: `nullable|string|digits:8`
- Cuando `ask_customer_dni = false`, el frontend envía string vacío; el backend almacena `null`

---

## Flujos de UI

### Flujo A — Sin DNI (`ask_customer_dni = false`)

```
Tocar card de servicio
  → Modal de confirmación
      - Franja de color del servicio
      - "Está por solicitar un turno para" + nombre del servicio
      - [Confirmar Turno] [Cancelar]
  → Turno generado → Modal de ticket
```

### Flujo B — Con DNI (`ask_customer_dni = true`)

```
Tocar card de servicio
  → Modal split horizontal
      Panel izquierdo:
        - Franja de color del servicio
        - Nombre del servicio
        - Display DNI: "44.770.488" (o "__.___.___" vacío)
        - [Confirmar] (disabled hasta 8 dígitos)
        - [Cancelar]
      Panel derecho:
        - Teclado numérico 3×4:
            1  2  3
            4  5  6
            7  8  9
            ←  0  ✓
        - ← borra último dígito
        - ✓ confirma (disabled hasta 8 dígitos)
  → Turno generado → Modal de ticket
```

### Formato DNI en tiempo real

| Dígitos ingresados | Display |
|---|---|
| 0 | `__.___.___` |
| 2 | `44` |
| 5 | `44.770` |
| 8 | `44.770.488` |

Máximo: 8 dígitos. El input excedente es ignorado.

---

## Archivos a modificar

### Backend

| Archivo | Cambio |
|---|---|
| Nueva migración `turns` | `renameColumn('customer_name', 'customer_dni')` |
| Nueva migración `totems` | `renameColumn('ask_customer_name', 'ask_customer_dni')` |
| `app/Models/Turn.php` | `$fillable`: rename |
| `app/Models/Totem.php` | `$fillable` y `$casts`: rename |
| `app/Http/Controllers/Api/TurnController.php` | Validación + `Turn::create()` |
| `app/Http/Controllers/Api/TotemController.php` | Todas las referencias |
| `app/Http/Controllers/Api/HistoryController.php` | Output arrays |
| `database/seeders/TotemSeeder.php` | Rename campo |

### Frontend

| Archivo | Cambio |
|---|---|
| `src/views/HomeView.vue` | Reemplazar modal de nombre por modal de confirmación (A) y modal DNI con teclado (B) |
| `src/components/operator/OperatorCurrentTurn.vue` | `customer_name` → `customer_dni`, label "DNI:", formato `XX.XXX.XXX` |
| `src/components/admin/AdminHistorial.vue` | Header "DNI", campo y formato |
| `src/components/admin/AdminPerfiles.vue` | `ask_customer_name` → `ask_customer_dni`, label "Pedir DNI" |
| `src/services/printService.js` | Rename en JSDoc y test data |

### Microservice

| Archivo | Cambio |
|---|---|
| `src/print/ticketPrinter.js` | Destructuring y JSDoc |
| `preload.js` | JSDoc |

---

## Verificación

1. `php artisan migrate` — ambas migraciones sin error
2. `php artisan db:seed` — seeder con `ask_customer_dni`
3. Totem `ask_customer_dni = false`: toque en servicio → modal simple → confirmar → turno creado
4. Totem `ask_customer_dni = true`: toque en servicio → modal split → ingresar 8 dígitos → display formatea → confirmar → turno creado
5. Panel operador: turno muestra "DNI: 44.770.488"
6. Historial admin: columna "DNI" muestra valor formateado o "—"
7. Admin/Perfiles: toggle "Pedir DNI" guarda y recarga correctamente
