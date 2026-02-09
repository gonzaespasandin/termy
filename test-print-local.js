/**
 * Test de impresión local - NO requiere servidor
 * Ejecutar con: npm run test-print
 * 
 * Este script verifica que la impresión funcione correctamente
 * usando Python + win32 (método probado)
 */

const { exec, spawn } = require('child_process');
const path = require('path');
const readline = require('readline');

// Cargar configuración
require('dotenv').config({ path: path.join(__dirname, '.env') });

const COMPANY_NAME = process.env.COMPANY_NAME || 'Dayco Gaming S.A.';
const PRINT_SCRIPT = path.join(__dirname, 'print_ticket.py');

console.log('');
console.log('═'.repeat(60));
console.log('  🖨️  TEST DE IMPRESIÓN LOCAL - TURNERO TOTEM');
console.log('═'.repeat(60));
console.log('');
console.log('📋 Este test NO requiere conexión al servidor');
console.log('📋 Usa Python + win32 para impresión directa');
console.log('');

// Función para verificar Python
function checkPython() {
  return new Promise((resolve) => {
    exec('python --version', (error, stdout) => {
      if (error) {
        console.log('❌ Python no está instalado o no está en el PATH');
        console.log('');
        console.log('   Instala Python desde: https://python.org');
        console.log('   Asegúrate de marcar "Add to PATH" durante la instalación');
        resolve(false);
      } else {
        console.log(`✅ ${stdout.trim()}`);
        resolve(true);
      }
    });
  });
}

// Función para verificar pywin32
function checkPywin32() {
  return new Promise((resolve) => {
    exec('python -c "import win32print; print(win32print.GetDefaultPrinter())"', (error, stdout, stderr) => {
      if (error) {
        console.log('❌ pywin32 no está instalado');
        console.log('');
        console.log('   Ejecuta: pip install pywin32');
        resolve(false);
      } else {
        console.log(`✅ Impresora predeterminada: ${stdout.trim()}`);
        resolve(true);
      }
    });
  });
}

// Función para imprimir test
function printTest() {
  return new Promise((resolve, reject) => {
    console.log('');
    console.log('🖨️  Enviando ticket de prueba...');
    console.log('');
    
    const args = [
      PRINT_SCRIPT,
      'TEST-001',
      'Prueba de Impresión',
      'Test Local',
      COMPANY_NAME
    ];
    
    const python = spawn('python', args);
    
    python.stdout.on('data', (data) => {
      process.stdout.write(data);
    });
    
    python.stderr.on('data', (data) => {
      process.stderr.write(data);
    });
    
    python.on('close', (code) => {
      if (code === 0) {
        resolve(true);
      } else {
        reject(new Error(`Código de salida: ${code}`));
      }
    });
    
    python.on('error', (error) => {
      reject(error);
    });
  });
}

// Función para esperar Enter
function waitForEnter(message) {
  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
  });
  
  return new Promise((resolve) => {
    rl.question(message, () => {
      rl.close();
      resolve();
    });
  });
}

// Ejecutar tests
async function main() {
  // Paso 1: Verificar Python
  console.log('🔍 Verificando Python...');
  const hasPython = await checkPython();
  if (!hasPython) {
    await waitForEnter('\nPresiona Enter para salir...');
    process.exit(1);
  }
  
  // Paso 2: Verificar pywin32
  console.log('');
  console.log('🔍 Verificando pywin32 e impresora...');
  const hasPywin32 = await checkPywin32();
  if (!hasPywin32) {
    await waitForEnter('\nPresiona Enter para salir...');
    process.exit(1);
  }
  
  // Paso 3: Esperar confirmación
  await waitForEnter('\n✅ Todo listo. Presiona Enter para imprimir un ticket de prueba...');
  
  // Paso 4: Imprimir
  try {
    await printTest();
    console.log('');
    console.log('═'.repeat(60));
    console.log('  ✅ ¡IMPRESIÓN EXITOSA!');
    console.log('═'.repeat(60));
    console.log('');
    console.log('   El ticket debería estar imprimiéndose.');
    console.log('   Si no imprimió, verifica:');
    console.log('   - Que la impresora esté encendida');
    console.log('   - Que tenga papel');
    console.log('   - Que esté configurada como predeterminada');
  } catch (error) {
    console.log('');
    console.log('═'.repeat(60));
    console.log('  ❌ ERROR DE IMPRESIÓN');
    console.log('═'.repeat(60));
    console.log('');
    console.log(`   ${error.message}`);
  }
  
  await waitForEnter('\nPresiona Enter para salir...');
  process.exit(0);
}

main();
