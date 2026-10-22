/**
 * Módulo de Impresión de Tickets
 * Usa Python + win32 para imprimir (método probado que funciona)
 * Electron ejecuta el script de Python como proceso hijo
 */

const { exec, spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const config = require('../config');
const { app } = require('electron');

// Ruta al script de Python
const PYTHON_SCRIPT = app.isPackaged
  ? path.join(process.resourcesPath, 'print_ticket.py')
  : path.join(__dirname, '..', '..', 'print_ticket.py');

function assertPythonScriptExists() {
  if (!fs.existsSync(PYTHON_SCRIPT)) {
    throw new Error(
      [
        'No se encontró el script de impresión (print_ticket.py).',
        `Ruta buscada: ${PYTHON_SCRIPT}`,
        'Reinstalá la app o verificá el empaquetado (extraResources).'
      ].join(' ')
    );
  }
}

/**
 * Verifica si Python está instalado
 * @returns {Promise<boolean>}
 */
async function checkPython() {
  return new Promise((resolve) => {
    exec(`"${config.pythonExe}" --version`, (error) => {
      resolve(!error);
    });
  });
}

/**
 * Obtiene la impresora predeterminada usando Python
 * @returns {Promise<string>}
 */
async function getDefaultPrinter() {
  return new Promise((resolve, reject) => {
    const script = `
import win32print
print(win32print.GetDefaultPrinter())
`;
    exec(`"${config.pythonExe}" -c "${script}"`, (error, stdout, stderr) => {
      if (error) {
        reject(new Error('No se pudo obtener la impresora'));
      } else {
        resolve(stdout.trim());
      }
    });
  });
}

/**
 * Imprime un ticket usando Python + win32
 * @param {Object} ticketData - Datos del ticket
 * @param {string} ticketData.ticket_number - Número del ticket
 * @param {string} ticketData.service_name - Nombre del servicio
 * @param {string} [ticketData.customer_dni] - DNI del cliente
 * @returns {Promise<void>}
 */
async function printTicket(ticketData) {
  const { ticket_number, service_name, customer_dni = '' } = ticketData;
  
  console.log(`🖨️ Imprimiendo ticket: ${ticket_number}`);
  
  return new Promise((resolve, reject) => {
    try {
      assertPythonScriptExists();
    } catch (e) {
      return reject(e);
    }

    // Ejecutar el script de Python con los argumentos
    // Si no hay nombre, pasar string vacío para que no se imprima
    const args = [
      PYTHON_SCRIPT,
      ticket_number,
      service_name,
      customer_dni || '',
      config.companyName
    ];
    
    const python = spawn(config.pythonExe, args, { windowsHide: true });
    
    let stdout = '';
    let stderr = '';
    
    python.stdout.on('data', (data) => {
      stdout += data.toString();
      console.log(data.toString());
    });
    
    python.stderr.on('data', (data) => {
      stderr += data.toString();
      console.error(data.toString());
    });
    
    python.on('close', (code) => {
      if (code === 0) {
        console.log(`✅ Ticket ${ticket_number} impreso correctamente`);
        resolve();
      } else {
        console.error(`❌ Error al imprimir (código ${code})`);
        if (stderr.includes('SIN_PAPEL')) {
          reject(new Error('SIN_PAPEL'));
        } else if (stderr.includes('OFFLINE')) {
          reject(new Error('OFFLINE'));
        } else if (stderr.includes('ERROR_IMPRESORA')) {
          reject(new Error('ERROR_IMPRESORA'));
        } else {
          reject(new Error(stderr.trim() || `Error de impresión (código ${code})`));
        }
      }
    });
    
    python.on('error', (error) => {
      console.error('❌ Error ejecutando Python:', error);
      reject(new Error('Python no está instalado o no se encuentra en el PATH (o configure PYTHON_EXE en el .env)'));
    });
  });
}

/**
 * Imprime un ticket de prueba
 * @returns {Promise<void>}
 */
async function testPrint() {
  return printTicket({
    ticket_number: 'TEST-001',
    service_name: 'Prueba de Impresión',
    customer_dni: '44770488'
  });
}

module.exports = {
  printTicket,
  getDefaultPrinter,
  testPrint,
  checkPython
};
