/**
 * Preload Script - Puente seguro entre el renderer y main process
 * Expone funciones seguras al contexto del navegador
 */

const { contextBridge, ipcRenderer } = require('electron');

console.log('[PRELOAD] Iniciando preload.js...');

// Exponer API segura al window del navegador
try {
  contextBridge.exposeInMainWorld('turneroAPI', {
  
  // ============================================================
  // Impresión
  // ============================================================
  
  /**
   * Imprime un ticket
   * @param {Object} ticketData - Datos del ticket
   * @param {string} ticketData.ticket_number - Número del ticket (ej: "AC-001")
   * @param {string} ticketData.service_name - Nombre del servicio
   * @param {string} [ticketData.customer_dni] - DNI del cliente (opcional)
   * @returns {Promise<{success: boolean, message?: string, error?: string}>}
   */
  printTicket: (ticketData) => ipcRenderer.invoke('print-ticket', ticketData),
  
  /**
   * Obtiene la impresora predeterminada
   * @returns {Promise<{success: boolean, printer?: string, error?: string}>}
   */
  getPrinter: () => ipcRenderer.invoke('get-printer'),
  
  /**
   * Ejecuta una impresión de prueba
   * @returns {Promise<{success: boolean, message?: string, error?: string}>}
   */
  testPrint: () => ipcRenderer.invoke('test-print'),
  
  // ============================================================
  // Configuración
  // ============================================================
  
  /**
   * Obtiene la configuración del totem
   * @returns {Promise<{totemCode: string, serverUrl: string, companyName: string}>}
   */
  getConfig: () => ipcRenderer.invoke('get-config'),

  /**
   * Abre la carpeta de configuración (userData) para soporte
   */
  openConfigFolder: () => ipcRenderer.invoke('open-config-folder'),
  
  /**
   * Recarga la aplicación
   */
  reloadApp: () => ipcRenderer.invoke('reload-app'),

  /**
   * Reintenta la conexión con el servidor (desde la pantalla de error)
   */
  retryConnection: () => ipcRenderer.invoke('retry-connection'),
  
  // ============================================================
  // Información
  // ============================================================
  
  /**
   * Indica si está corriendo en Electron
   */
  isElectron: true,
  
  /**
   * Versión de la aplicación
   */
  version: '1.0.0'
  });
  
  console.log('[PRELOAD] turneroAPI expuesta correctamente!');
} catch (error) {
  console.error('[PRELOAD] Error exponiendo API:', error);
}

console.log('[PRELOAD] Preload completado.');

