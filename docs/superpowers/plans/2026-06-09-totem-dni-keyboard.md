# Totem DNI Keypad + Confirmation Modal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the totem's free-text name capture with (a) a confirmation modal when DNI is not requested and (b) a touchscreen numeric keypad that captures an 8-digit DNI when it is, while renaming `customer_name` to `customer_dni` and `ask_customer_name` to `ask_customer_dni` across the entire codebase (Laravel backend, Vue frontend, Node microservice).

**Architecture:** Two database column renames via `renameColumn` migrations, then a cascade of literal string renames through PHP models/controllers/seeder, Vue components, and the Node print microservice. The totem `HomeView.vue` gains a confirmation modal (Feature 1) and a split-panel DNI keypad modal (Feature 2) with real-time `XX.XXX.XXX` formatting. No new API endpoints; the existing `POST /api/turns` and totem config endpoints carry the renamed fields.

**Tech Stack:** Laravel 12 (PHP 8.3+, Eloquent, PHPUnit + in-memory SQLite), Vue 3 (`<script setup>`, Composition API), Vite 7, Tailwind CSS v4 (scoped component CSS), Node.js Electron microservice.

---

## Conventions for this plan

- All paths are relative to the repo root `C:\Users\gonza\OneDrive\Desktop\termy`.
- Backend commands run from `backend/`. Frontend commands run from `frontend/`. Microservice commands run from `microservice/`.
- Backend tests use **PHPUnit** (no Pest in this project). New tests extend `Tests\TestCase` and use `Illuminate\Foundation\Testing\RefreshDatabase`, which runs every migration (including the new rename migrations) against in-memory SQLite.
- Frontend has no automated UI test runner; frontend tasks use explicit **manual verification steps** plus `npm run build` to catch compile errors.
- Commit after every task. Use the exact commit messages provided.

---

## Task 1: Rename `customer_name` -> `customer_dni` in the `turns` table (migration)

**Files:**
- Create: `backend/database/migrations/2026_06_09_100000_rename_customer_name_to_customer_dni_in_turns_table.php`

> NOTE: SQLite (used by the test suite) supports `renameColumn` natively in Laravel 12 via Doctrine-free schema. No `doctrine/dbal` install is required in Laravel 12.

- [ ] **Step 1: Create the migration file**

Create `backend/database/migrations/2026_06_09_100000_rename_customer_name_to_customer_dni_in_turns_table.php` with exactly this content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->renameColumn('customer_name', 'customer_dni');
        });
    }

    public function down(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->renameColumn('customer_dni', 'customer_name');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: output includes `....... rename_customer_name_to_customer_dni_in_turns_table .... DONE`

- [ ] **Step 3: Verify the column was renamed**

Run: `php artisan tinker --execute="echo Schema::hasColumn('turns', 'customer_dni') ? 'YES' : 'NO';"`
Expected: `YES`

- [ ] **Step 4: Commit**

```bash
git add backend/database/migrations/2026_06_09_100000_rename_customer_name_to_customer_dni_in_turns_table.php
git commit -m "feat(db): rename turns.customer_name to customer_dni

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Task 2: Rename `ask_customer_name` -> `ask_customer_dni` in the `totems` table (migration)

**Files:**
- Create: `backend/database/migrations/2026_06_09_100100_rename_ask_customer_name_to_ask_customer_dni_in_totems_table.php`

- [ ] **Step 1: Create the migration file**

Create `backend/database/migrations/2026_06_09_100100_rename_ask_customer_name_to_ask_customer_dni_in_totems_table.php` with exactly this content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('totems', function (Blueprint $table) {
            $table->renameColumn('ask_customer_name', 'ask_customer_dni');
        });
    }

    public function down(): void
    {
        Schema::table('totems', function (Blueprint $table) {
            $table->renameColumn('ask_customer_dni', 'ask_customer_name');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: output includes `....... rename_ask_customer_name_to_ask_customer_dni_in_totems_table .... DONE`

- [ ] **Step 3: Verify the column was renamed**

Run: `php artisan tinker --execute="echo Schema::hasColumn('totems', 'ask_customer_dni') ? 'YES' : 'NO';"`
Expected: `YES`

- [ ] **Step 4: Commit**

```bash
git add backend/database/migrations/2026_06_09_100100_rename_ask_customer_name_to_ask_customer_dni_in_totems_table.php
git commit -m "feat(db): rename totems.ask_customer_name to ask_customer_dni

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Task 3: Update Eloquent models (Turn, Totem)

**Files:**
- Modify: `backend/app/Models/Turn.php:18`
- Modify: `backend/app/Models/Totem.php:18,28`

- [ ] **Step 1: Update Turn model fillable**

In `backend/app/Models/Turn.php`, inside `protected $fillable`, change line 18 from `'customer_name',` to `'customer_dni',`.

- [ ] **Step 2: Update Totem model fillable and casts**

In `backend/app/Models/Totem.php`, line 18 (inside `$fillable`), change `'ask_customer_name',` to `'ask_customer_dni',`. And line 28 (inside `$casts`), change `'ask_customer_name' => 'boolean',` to `'ask_customer_dni' => 'boolean',`.

- [ ] **Step 3: Verify no stray references remain in models**

Run: `grep -rn "customer_name\|ask_customer_name" backend/app/Models`
Expected: no output (empty).

- [ ] **Step 4: Commit**

    git add backend/app/Models/Turn.php backend/app/Models/Totem.php
    git commit -m "refactor(models): rename customer_name/ask_customer_name to dni variants"

---

## Task 4: Update TurnController validation + create logic (TDD)

The DNI must be exactly 8 digits when supplied, but still optional (totems with `ask_customer_dni = false` create turns with no DNI).

**Files:**
- Modify: `backend/app/Http/Controllers/Api/TurnController.php:44,50-51,73`
- Test: `backend/tests/Feature/TurnDniTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/TurnDniTest.php` with exactly this content:


```php
<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnDniTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): Service
    {
        return Service::create([
            'name' => 'Atencion Cliente',
            'code' => 'AC',
            'color' => '#3b82f6',
            'is_active' => true,
        ]);
    }

    public function test_creates_turn_with_valid_8_digit_dni(): void
    {
        $service = $this->makeService();

        $response = $this->postJson('/api/turns', [
            'service_id' => $service->id,
            'customer_dni' => '44770488',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('customer_dni', '44770488');
        $this->assertDatabaseHas('turns', ['customer_dni' => '44770488']);
    }

    public function test_creates_turn_with_no_dni(): void
    {
        $service = $this->makeService();

        $response = $this->postJson('/api/turns', [
            'service_id' => $service->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('customer_dni', null);
    }

    public function test_rejects_dni_with_wrong_digit_count(): void
    {
        $service = $this->makeService();

        $response = $this->postJson('/api/turns', [
            'service_id' => $service->id,
            'customer_dni' => '123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('customer_dni');
    }

    public function test_rejects_non_numeric_dni(): void
    {
        $service = $this->makeService();

        $response = $this->postJson('/api/turns', [
            'service_id' => $service->id,
            'customer_dni' => 'ABCDEFGH',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('customer_dni');
    }
}
```

> If the `Service` columns differ, confirm them first with `php artisan tinker --execute="print_r(Schema::getColumnListing('services'));"` and edit the `create()` array to match real NOT NULL columns. Do NOT change the assertions.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=TurnDniTest`
Expected: FAIL. The two "rejects" tests fail because the current rule `nullable|string|max:20` accepts those values; the `customer_dni` JSON/db assertions fail because the controller still reads/writes `customer_name`.

- [ ] **Step 3: Update the validation rules**

In `backend/app/Http/Controllers/Api/TurnController.php`, in `store()`, change line 44 from `'customer_name' => 'nullable|string|max:20',` to `'customer_dni' => 'nullable|string|digits:8',`.

- [ ] **Step 4: Update the validation messages**

In the same `$request->validate(...)` call, replace lines 50-51:

```php
            'customer_dni.string' => 'El DNI debe ser una cadena de texto',
            'customer_dni.digits' => 'El DNI debe tener exactamente 8 digitos',
```

- [ ] **Step 5: Update the Turn::create() payload**

Change line 73 from `'customer_name' => $request->customer_name ?: null,` to `'customer_dni' => $request->customer_dni ?: null,` (keep the trailing comment).

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=TurnDniTest`
Expected: PASS (4 passed).

- [ ] **Step 7: Commit**

    git add backend/app/Http/Controllers/Api/TurnController.php backend/tests/Feature/TurnDniTest.php
    git commit -m "feat(api): validate customer_dni as 8 digits in TurnController"

---

## Task 5: Update TotemController, HistoryController, and TotemSeeder

**Files:**
- Modify: `backend/app/Http/Controllers/Api/TotemController.php:63,79,80,82,130,143,144`
- Modify: `backend/app/Http/Controllers/Api/HistoryController.php:61,122`
- Modify: `backend/database/seeders/TotemSeeder.php:19`

- [ ] **Step 1: Update TotemController store() validation + boolean coercion**

In `backend/app/Http/Controllers/Api/TotemController.php`:
- Line 63: change `'ask_customer_name' => 'sometimes|boolean',` to `'ask_customer_dni' => 'sometimes|boolean',`.
- Lines 79-83, replace the block:

```php
        if (isset($data['ask_customer_dni'])) {
            $data['ask_customer_dni'] = filter_var($data['ask_customer_dni'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $data['ask_customer_dni'] = true; // Por defecto pide DNI
        }
```

- [ ] **Step 2: Update TotemController update() validation + boolean coercion**

In the same file:
- Line 130: change `'ask_customer_name' => 'sometimes|boolean',` to `'ask_customer_dni' => 'sometimes|boolean',`.
- Lines 143-145, replace the block:

```php
        if (isset($data['ask_customer_dni'])) {
            $data['ask_customer_dni'] = filter_var($data['ask_customer_dni'], FILTER_VALIDATE_BOOLEAN);
        }
```

- [ ] **Step 3: Update HistoryController response mapping**

In `backend/app/Http/Controllers/Api/HistoryController.php`:
- Line 61: change `'customer_name' => $turn->customer_name,` to `'customer_dni' => $turn->customer_dni,`.
- Line 122: change `'customer_name' => $turn->customer_name,` to `'customer_dni' => $turn->customer_dni,`.

- [ ] **Step 4: Update TotemSeeder**

In `backend/database/seeders/TotemSeeder.php`, line 19: change `'ask_customer_name' => false,` to `'ask_customer_dni' => false,`.

- [ ] **Step 5: Verify no backend references remain**

Run: `grep -rn "customer_name\|ask_customer_name" backend/app backend/database/seeders`
Expected: no output (empty). (Historical migration files under `backend/database/migrations` that reference the old names in `->after()` clauses are intentionally left untouched — they describe past schema state and must not be edited.)

- [ ] **Step 6: Run the full backend test suite**

Run: `php artisan test`
Expected: PASS (all tests green, including `TurnDniTest`).

- [ ] **Step 7: Commit**

    git add backend/app/Http/Controllers/Api/TotemController.php backend/app/Http/Controllers/Api/HistoryController.php backend/database/seeders/TotemSeeder.php
    git commit -m "refactor(api): rename ask_customer_name/customer_name to dni in controllers and seeder"


---

## Task 6: Rework totem HomeView — confirmation modal + DNI keypad

This is the largest task. It rewrites the totem flow:
- When `askCustomerDni` is **false**: clicking a service opens a **confirmation modal** (Feature 1).
- When `askCustomerDni` is **true**: clicking a service opens a **split-panel DNI keypad modal** (Feature 2).

**Files:**
- Modify: `frontend/src/views/HomeView.vue` (script lines 277, 283, 422, 446-453, 455-498, 502-507; template lines 116-169; styles append at end of `<style scoped>`)

### Step A — Script changes

- [ ] **Step A1: Rename the state refs**

In the `<script setup>`:
- Line 277: change `const askCustomerName = ref(true);` to `const askCustomerDni = ref(true);`
- Line 283: change `const customerName = ref('');` to `const customerDni = ref('');`

Immediately after the `customerDni` ref (line 283), add a new ref for Feature 1:

```js
const showConfirmModal = ref(false);
```

- [ ] **Step A2: Add the DNI computed helpers**

Directly below the `showError` function (after line 300), add:

```js
// ──────────────────────────────
// DNI (documento) — formato XX.XXX.XXX, máx 8 dígitos
// ──────────────────────────────
const formatDni = (raw) => {
  const d = String(raw).replace(/\D/g, '').slice(0, 8);
  if (d.length <= 2) return d;
  if (d.length <= 5) return `${d.slice(0, 2)}.${d.slice(2)}`;
  return `${d.slice(0, 2)}.${d.slice(2, 5)}.${d.slice(5)}`;
};
const dniDisplay = computed(() => (customerDni.value ? formatDni(customerDni.value) : '__.___.___'));
const dniValid = computed(() => customerDni.value.length === 8);
const appendDigit = (d) => { if (customerDni.value.length < 8) customerDni.value += String(d); };
const deleteDigit = () => { customerDni.value = customerDni.value.slice(0, -1); };
```

- [ ] **Step A3: Update the totem config flag read**

Line 422: change

```js
    askCustomerName.value = response.data.totem.ask_customer_name !== false;
```

to

```js
    askCustomerDni.value = response.data.totem.ask_customer_dni !== false;
```

- [ ] **Step A4: Rewrite `selectService()`**

Replace the whole `selectService` function (lines 446-453) with:

```js
const selectService = (service) => {
  if (confirming.value) return;
  selectedService.value = service;
  customerDni.value = '';
  if (askCustomerDni.value) {
    // Feature 2: el modal del teclado DNI se abre vía v-if (selectedService && askCustomerDni)
    return;
  }
  // Feature 1: pedir confirmación antes de generar el turno
  showConfirmModal.value = true;
};
```

- [ ] **Step A5: Add a confirm handler for Feature 1**

Directly below the rewritten `selectService` (before `confirmTurn`), add:

```js
const confirmFromModal = async () => {
  showConfirmModal.value = false;
  await confirmTurn();
};
```

- [ ] **Step A6: Update `confirmTurn()` payload**

In `confirmTurn()` replace lines 459-464:

```js
    const finalCustomerDni = askCustomerDni.value ? (customerDni.value || '') : '';
    const response = await createTurn({
      service_id: selectedService.value.id,
      customer_dni: finalCustomerDni,
      totem_id: totemId.value,
    });
```

- [ ] **Step A7: Update `printTicket()` payload**

In `printTicket()` replace lines 482-487:

```js
    const finalCustomerDni = askCustomerDni.value ? (customerDni.value || '') : '';
    const result = await printService.printTicket({
      ticket_number: generatedTurn.value.ticket_number,
      service_name: generatedTurn.value.service.name,
      customer_dni: finalCustomerDni,
    });
```

- [ ] **Step A8: Reset DNI + confirm modal on close**

In `closeTicketModal()` (lines 502-507), change `customerName.value = '';` to `customerDni.value = '';` and add `showConfirmModal.value = false;` so the function reads:

```js
const closeTicketModal = () => {
  generatedTurn.value = null;
  customerDni.value = '';
  showConfirmModal.value = false;
  printError.value = '';
  printSuccess.value = false;
};
```


### Step B — Template changes

- [ ] **Step B1: Replace the old name modal with TWO modals**

Replace the entire block currently at lines 115-169 (the `<!-- ═══ MODAL: CONFIRMAR TURNO ═══ -->` transition that contains the text `<input v-model="customerName" ...>`) with the following two modals:

```html
    <!-- ═══════════ MODAL 1: CONFIRMAR TURNO (sin DNI) ═══════════ -->
    <transition name="modal-fade">
      <div
        v-if="showConfirmModal && selectedService"
        class="modal-backdrop"
        @click.self="showConfirmModal = false"
      >
        <transition name="modal-scale" appear>
          <div class="modal-card">
            <div class="modal-band" :style="{ backgroundColor: selectedService.color }"></div>

            <div class="modal-body">
              <p class="modal-eyebrow">Está por solicitar un turno para:</p>
              <h2 class="modal-service-name" :style="{ color: selectedService.color }">
                {{ selectedService.name }}
              </h2>

              <div class="modal-actions">
                <button
                  class="btn-primary"
                  :style="{ backgroundColor: selectedService.color }"
                  :disabled="confirming"
                  @click="confirmFromModal"
                >
                  <i :class="confirming ? 'fa fa-spinner fa-spin' : 'fa fa-check-circle'"></i>
                  {{ confirming ? 'Generando turno...' : 'Confirmar Turno' }}
                </button>
                <button class="btn-secondary" @click="showConfirmModal = false">
                  <i class="fa fa-times"></i>
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        </transition>
      </div>
    </transition>

    <!-- ═══════════ MODAL 2: TECLADO NUMÉRICO DNI ═══════════ -->
    <transition name="modal-fade">
      <div
        v-if="selectedService && askCustomerDni"
        class="modal-backdrop"
        @click.self="selectedService = null"
      >
        <transition name="modal-scale" appear>
          <div class="modal-card dni-modal">
            <div class="dni-split">
              <!-- Panel izquierdo (~40%) -->
              <div class="dni-left">
                <div class="dni-band" :style="{ backgroundColor: selectedService.color }"></div>
                <p class="modal-eyebrow">Ingrese su DNI</p>
                <h2 class="dni-service-name" :style="{ color: selectedService.color }">
                  {{ selectedService.name }}
                </h2>

                <div class="dni-display" :class="{ 'dni-display--filled': customerDni.length > 0 }">
                  {{ dniDisplay }}
                </div>

                <div class="dni-left-actions">
                  <button
                    class="btn-primary"
                    :style="{ backgroundColor: selectedService.color }"
                    :disabled="!dniValid || confirming"
                    @click="confirmTurn"
                  >
                    <i :class="confirming ? 'fa fa-spinner fa-spin' : 'fa fa-check-circle'"></i>
                    {{ confirming ? 'Generando...' : 'Confirmar' }}
                  </button>
                  <button class="btn-secondary" @click="selectedService = null">
                    <i class="fa fa-times"></i>
                    Cancelar
                  </button>
                </div>
              </div>

              <!-- Panel derecho (~60%): teclado 3×4 -->
              <div class="dni-right">
                <div class="keypad-grid">
                  <button class="keypad-key" @click="appendDigit(1)">1</button>
                  <button class="keypad-key" @click="appendDigit(2)">2</button>
                  <button class="keypad-key" @click="appendDigit(3)">3</button>
                  <button class="keypad-key" @click="appendDigit(4)">4</button>
                  <button class="keypad-key" @click="appendDigit(5)">5</button>
                  <button class="keypad-key" @click="appendDigit(6)">6</button>
                  <button class="keypad-key" @click="appendDigit(7)">7</button>
                  <button class="keypad-key" @click="appendDigit(8)">8</button>
                  <button class="keypad-key" @click="appendDigit(9)">9</button>
                  <button class="keypad-key keypad-key--action" @click="deleteDigit">
                    <i class="fa fa-delete-left"></i>
                  </button>
                  <button class="keypad-key" @click="appendDigit(0)">0</button>
                  <button
                    class="keypad-key keypad-key--confirm"
                    :style="dniValid ? { backgroundColor: selectedService.color, color: '#fff' } : {}"
                    :disabled="!dniValid"
                    @click="confirmTurn"
                  >
                    <i class="fa fa-check"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </transition>
      </div>
    </transition>
```

> The `fa-delete-left` icon is the Font Awesome backspace glyph. If the installed FA version lacks it, fall back to `fa-arrow-left`.


### Step C — CSS (DNI keypad styles)

- [ ] **Step C1: Append the DNI modal styles**

At the very end of the `<style scoped>` block (just before the closing `</style>` on line 1215), append the following CSS. It reuses the existing `.modal-card`, `.modal-band`, `.modal-eyebrow`, `.btn-primary`, and `.btn-secondary` classes for visual consistency (glassmorphism backdrop, `border-radius: 28px`, Plus Jakarta Sans):

```css
/* ═══════════════════════════════════════
   MODAL DNI — SPLIT HORIZONTAL + TECLADO
═══════════════════════════════════════ */
.dni-modal {
  max-width: 880px;
}

.dni-split {
  display: flex;
  align-items: stretch;
  min-height: 480px;
}

/* Panel izquierdo (~40%) */
.dni-left {
  flex: 0 0 40%;
  position: relative;
  padding: 2.5rem 2rem 2rem;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #f1f5f9;
}

.dni-band {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 8px;
}

.dni-service-name {
  font-size: 1.75rem;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.03em;
  margin: 0.25rem 0 1.75rem;
}

.dni-display {
  font-size: 2.6rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  color: #cbd5e1;
  text-align: center;
  padding: 1.1rem 0.75rem;
  border: 2px dashed #e2e8f0;
  border-radius: 16px;
  background: #f8fafc;
  font-variant-numeric: tabular-nums;
  user-select: none;
}

.dni-display--filled {
  color: #1a202c;
  border-style: solid;
  border-color: #cbd5e1;
  background: #ffffff;
}

.dni-left-actions {
  margin-top: auto;
  padding-top: 1.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

/* Panel derecho (~60%): teclado */
.dni-right {
  flex: 1;
  padding: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.keypad-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: repeat(4, 1fr);
  gap: 1rem;
  width: 100%;
  height: 100%;
}

.keypad-key {
  border: none;
  border-radius: 18px;
  background: #f1f5f9;
  color: #1a202c;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 2.2rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 84px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  transition: transform 0.08s ease, background 0.12s ease;
  -webkit-tap-highlight-color: transparent;
  user-select: none;
}

/* Feedback táctil */
.keypad-key:active {
  transform: scale(0.92);
  background: #e2e8f0;
}

.keypad-key--action {
  background: #fef3c7;
  color: #b45309;
  font-size: 1.8rem;
}

.keypad-key--action:active {
  background: #fde68a;
}

.keypad-key--confirm {
  background: #dcfce7;
  color: #15803d;
  font-size: 1.8rem;
}

.keypad-key--confirm:active {
  transform: scale(0.92);
}

.keypad-key--confirm:disabled {
  background: #f1f5f9;
  color: #cbd5e1;
  cursor: not-allowed;
  transform: none;
}
```

### Step D — Verify and commit

- [ ] **Step D1: Confirm no old references remain in HomeView**

Run: `grep -n "customerName\|askCustomerName\|customer_name\|ask_customer_name" frontend/src/views/HomeView.vue`
Expected: no output (empty).

- [ ] **Step D2: Build the frontend**

Run (from `frontend/`): `npm run build`
Expected: build succeeds with no Vue compile / template errors.

- [ ] **Step D3: Manual verification — confirmation modal (Feature 1)**

Run the app against a totem whose profile has "Pedir DNI" OFF (`ask_customer_dni = false`). Tap a service card. Verify:
  - A modal opens showing the service color band, "Está por solicitar un turno para:", and the service name in its color.
  - [Confirmar Turno] creates the turn and shows the ticket modal.
  - [Cancelar] closes the modal without creating a turn.

- [ ] **Step D4: Manual verification — DNI keypad (Feature 2)**

Run the app against a totem whose profile has "Pedir DNI" ON (`ask_customer_dni = true`). Tap a service card. Verify:
  - Split modal: left panel (service name + DNI display `__.___.___`), right panel 3×4 keypad.
  - Typing `44770488` shows live formatting `4` -> `44` -> `44.7` -> ... -> `44.770.488`.
  - Keys scale down on press (`:active`).
  - A 9th digit is ignored (stays at 8).
  - `←` deletes the last digit.
  - [Confirmar] and the `✓` key are disabled until exactly 8 digits, then create the turn.
  - Verify in the DB / history that `customer_dni` is `44770488`.

- [ ] **Step D5: Commit**

    git add frontend/src/views/HomeView.vue
    git commit -m "feat(totem): add confirmation modal and DNI numeric keypad"


---

## Task 7: Update OperatorCurrentTurn — show formatted DNI

**Files:**
- Modify: `frontend/src/components/operator/OperatorCurrentTurn.vue:53-55,108-110` (+ add a `formatDni` helper in `<script setup>`)

- [ ] **Step 1: Add a `formatDni` helper**

In the component's `<script setup>`, add (near the other helpers like `formatTime`):

```js
const formatDni = (raw) => {
  if (!raw) return '';
  const d = String(raw).replace(/\D/g, '').slice(0, 8);
  if (d.length <= 2) return d;
  if (d.length <= 5) return `${d.slice(0, 2)}.${d.slice(2)}`;
  return `${d.slice(0, 2)}.${d.slice(2, 5)}.${d.slice(5)}`;
};
```

- [ ] **Step 2: Update the "called" state customer row (lines 53-55)**

Replace:

```html
        <div v-if="currentTurn.customer_dni" class="customer-row">
          <i class="fas fa-id-card"></i>
          <span>DNI: {{ formatDni(currentTurn.customer_dni) }}</span>
        </div>
```

- [ ] **Step 3: Update the "in_progress" state customer row (lines 108-110)**

Replace with the identical block:

```html
        <div v-if="currentTurn.customer_dni" class="customer-row">
          <i class="fas fa-id-card"></i>
          <span>DNI: {{ formatDni(currentTurn.customer_dni) }}</span>
        </div>
```

- [ ] **Step 4: Verify and commit**

Run: `grep -n "customer_name" frontend/src/components/operator/OperatorCurrentTurn.vue`
Expected: no output.

    git add frontend/src/components/operator/OperatorCurrentTurn.vue
    git commit -m "feat(operator): show formatted DNI on current turn"

---

## Task 8: Update AdminHistorial + AdminPerfiles

**Files:**
- Modify: `frontend/src/components/admin/AdminHistorial.vue:74,111`
- Modify: `frontend/src/components/admin/AdminPerfiles.vue:71-72,193,197,332,423,468,489`

- [ ] **Step 1: Update AdminHistorial column header**

In `frontend/src/components/admin/AdminHistorial.vue`, line 74, change the header `<th>Cliente</th>` to `<th>DNI</th>`.

- [ ] **Step 2: Add a `formatDni` helper to AdminHistorial**

In its `<script setup>`, add (near other formatters such as `formatDuration`):

```js
const formatDni = (raw) => {
  if (!raw) return '—';
  const d = String(raw).replace(/\D/g, '').slice(0, 8);
  if (d.length <= 2) return d;
  if (d.length <= 5) return `${d.slice(0, 2)}.${d.slice(2)}`;
  return `${d.slice(0, 2)}.${d.slice(2, 5)}.${d.slice(5)}`;
};
```

- [ ] **Step 3: Update AdminHistorial cell**

Line 111, change `<td class="td-muted">{{ turn.customer_name || '—' }}</td>` to:

```html
              <td class="td-muted">{{ formatDni(turn.customer_dni) }}</td>
```

- [ ] **Step 4: Update AdminPerfiles status badge (lines 71-72)**

Replace:

```html
              <span class="status-badge" :class="profile.ask_customer_dni !== false ? 'status-yes' : 'status-no'">
                {{ profile.ask_customer_dni !== false ? 'Sí' : 'No' }}
```

- [ ] **Step 5: Update AdminPerfiles toggle (line 193) and label (line 197)**

Line 193, replace the toggle switch element:

```html
                  <div class="toggle-switch" :class="{ 'toggle-on': form.ask_customer_dni }" @click="form.ask_customer_dni = !form.ask_customer_dni" style="--toggle-color: #f59e0b;">
```

Line 197, change the label text `<span class="toggle-text">Pedir nombre del cliente</span>` to:

```html
                    <span class="toggle-text">Pedir DNI del cliente</span>
```

And the hint on line 198, change to:

```html
                    <p class="toggle-hint">Si está desactivado, el ticket se imprime directamente sin solicitar DNI</p>
```

- [ ] **Step 6: Update AdminPerfiles form state + payload**

- Line 332 (default form object): change `ask_customer_name: true,` to `ask_customer_dni: true,`.
- Line 423 (loading a profile into the form): change to `ask_customer_dni: profile.ask_customer_dni !== undefined ? profile.ask_customer_dni : true,`.
- Line 468 (FormData append): change to `formData.append('ask_customer_dni', form.value.ask_customer_dni ? '1' : '0')`.
- Line 489 (plain object payload): change `ask_customer_name: form.value.ask_customer_name,` to `ask_customer_dni: form.value.ask_customer_dni,`.

- [ ] **Step 7: Verify and commit**

Run: `grep -rn "customer_name\|ask_customer_name" frontend/src/components/admin`
Expected: no output.

    git add frontend/src/components/admin/AdminHistorial.vue frontend/src/components/admin/AdminPerfiles.vue
    git commit -m "feat(admin): rename name column/toggle to DNI in historial and perfiles"

---

## Task 9: Update the print microservice + printService

**Files:**
- Modify: `microservice/src/print/ticketPrinter.js:67,71,88,132`
- Modify: `microservice/preload.js:23`
- Modify: `frontend/src/services/printService.js:64,150`

> The Python print script receives the DNI as a positional CLI argument; renaming the JS variable does not change argument order, so no Python changes are required.

- [ ] **Step 1: Update ticketPrinter.js**

In `microservice/src/print/ticketPrinter.js`:
- Line 67 (JSDoc): change `@param {string} [ticketData.customer_name] - Nombre del cliente` to `@param {string} [ticketData.customer_dni] - DNI del cliente`.
- Line 71 (destructure): change `const { ticket_number, service_name, customer_name = '' } = ticketData;` to `const { ticket_number, service_name, customer_dni = '' } = ticketData;`.
- Line 88 (args array): change `customer_name || '',` to `customer_dni || '',`.
- Line 132 (testPrint data): change `customer_name: 'Test Automático'` to `customer_dni: '44770488'`.

- [ ] **Step 2: Update preload.js**

In `microservice/preload.js`, line 23 (JSDoc): change `@param {string} [ticketData.customer_name] - Nombre del cliente (opcional)` to `@param {string} [ticketData.customer_dni] - DNI del cliente (opcional)`.

- [ ] **Step 3: Update printService.js**

In `frontend/src/services/printService.js`:
- Line 64 (JSDoc): change `@param {string} [ticketData.customer_name] - Nombre del cliente (opcional)` to `@param {string} [ticketData.customer_dni] - DNI del cliente (opcional)`.
- Line 150 (test data): change `customer_name: 'Test Automático'` to `customer_dni: '44770488'`.

- [ ] **Step 4: Verify and commit**

Run: `grep -rn "customer_name" microservice/src microservice/preload.js frontend/src/services/printService.js`
Expected: no output.

    git add microservice/src/print/ticketPrinter.js microservice/preload.js frontend/src/services/printService.js
    git commit -m "refactor(print): rename customer_name to customer_dni in microservice and printService"

---

## Task 10: Final full-codebase verification

- [ ] **Step 1: Global grep for any leftover references**

Run from repo root: `grep -rn "customer_name\|ask_customer_name\|customerName\|askCustomerName" backend/app backend/database/seeders frontend/src microservice/src microservice/preload.js`
Expected: no output. (Historical migration files in `backend/database/migrations` and the spec/docs files are intentionally excluded and may still contain the old names.)

- [ ] **Step 2: Run the backend suite**

Run (from `backend/`): `php artisan test`
Expected: PASS (all green).

- [ ] **Step 3: Build the frontend**

Run (from `frontend/`): `npm run build`
Expected: build succeeds.

- [ ] **Step 4: Confirm clean git status**

Run: `git status`
Expected: working tree clean (all work committed across Tasks 1-9).

---

## Done

All spec requirements are covered: Feature 1 (confirmation modal), Feature 2 (DNI keypad), and the complete `customer_name -> customer_dni` / `ask_customer_name -> ask_customer_dni` rename across backend, frontend, and microservice.
