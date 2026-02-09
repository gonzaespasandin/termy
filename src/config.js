/**
 * Configuración del Totem
 * Lee las variables de entorno desde process.env
 *
 * Nota: la carga del `.env` se hace explícitamente en `main.js` via `src/envLoader.js`
 */

const config = {
  // URL del servidor Vue/Laravel
  serverUrl: process.env.SERVER_URL || 'http://localhost:5173',
  
  // Código único del totem
  totemCode: process.env.TOTEM_CODE || 'TOTEM-DEFAULT',
  
  // Nombre de la empresa (para tickets)
  companyName: process.env.COMPANY_NAME || 'Dayco Gaming S.A.',
  
  // Nombre de la impresora (vacío = predeterminada)
  printerName: process.env.PRINTER_NAME || '',

  // Comando o ruta del ejecutable de Python
  pythonExe: process.env.PYTHON_EXE || 'python',
  
  // Modo desarrollo
  isDev: process.argv.includes('--dev') || process.env.NODE_ENV === 'development',
  
  // Tamaño del ticket
  ticketWidth: parseInt(process.env.TICKET_WIDTH) || 58, // mm
  
  // Auto-inicio con Windows
  autoStart: process.env.AUTO_START !== 'false'
};

module.exports = config;

