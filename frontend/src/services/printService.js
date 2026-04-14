/**
 * Servicio de Impresión Unificado
 * 
 * Detecta automáticamente si está corriendo en Electron (totem desktop)
 * o en navegador (usa microservicio HTTP).
 */

// URL del microservicio de impresión (para modo navegador)
const PRINT_SERVICE_URL = import.meta.env.VITE_PRINT_SERVICE_URL || 'http://localhost:5000';

/**
 * Verifica si la aplicación está corriendo en Electron
 * @returns {boolean}
 */
export function isElectron() {
  const hasAPI = !!(window.turneroAPI && window.turneroAPI.isElectron);
  console.log('[PrintService] Detectando Electron...');
  console.log('[PrintService] window.turneroAPI:', window.turneroAPI);
  console.log('[PrintService] isElectron:', hasAPI);
  return hasAPI;
}

/**
 * Obtiene información de la impresora
 * @returns {Promise<{success: boolean, printer?: string, error?: string}>}
 */
export async function getPrinterInfo() {
  if (isElectron()) {
    // Usar API de Electron
    return await window.turneroAPI.getPrinter();
  } else {
    // Usar microservicio HTTP
    try {
      const response = await fetch(`${PRINT_SERVICE_URL}/health`);
      const data = await response.json();
      return {
        success: data.status === 'ok',
        printer: data.printer,
        message: data.message
      };
    } catch (error) {
      return {
        success: false,
        error: 'Servicio de impresión no disponible'
      };
    }
  }
}

/**
 * Verifica si el servicio de impresión está disponible
 * @returns {Promise<boolean>}
 */
export async function isPrintServiceAvailable() {
  const info = await getPrinterInfo();
  return info.success;
}

/**
 * Imprime un ticket
 * @param {Object} ticketData - Datos del ticket
 * @param {string} ticketData.ticket_number - Número del ticket (ej: "AC-001")
 * @param {string} ticketData.service_name - Nombre del servicio
 * @param {string} [ticketData.customer_name] - Nombre del cliente (opcional)
 * @returns {Promise<{success: boolean, message?: string, error?: string}>}
 */
export async function printTicket(ticketData) {
  console.log('🖨️ Imprimiendo ticket:', ticketData);
  console.log('📍 Modo:', isElectron() ? 'Electron' : 'Microservicio HTTP');

  if (isElectron()) {
    // ============================================
    // MODO ELECTRON (App de escritorio)
    // ============================================
    try {
      const result = await window.turneroAPI.printTicket(ticketData);
      return result;
    } catch (error) {
      console.error('Error al imprimir (Electron):', error);
      return {
        success: false,
        error: error.message || 'Error desconocido al imprimir'
      };
    }
  } else {
    // ============================================
    // MODO NAVEGADOR (Microservicio HTTP)
    // ============================================
    try {
      // Verificar que el servicio esté disponible
      const healthCheck = await fetch(`${PRINT_SERVICE_URL}/health`);
      if (!healthCheck.ok) {
        return {
          success: false,
          error: 'Servicio de impresión no responde'
        };
      }

      // Enviar a imprimir
      const response = await fetch(`${PRINT_SERVICE_URL}/print`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(ticketData)
      });

      const data = await response.json();

      if (response.ok && data.success) {
        return {
          success: true,
          message: data.message || 'Ticket impreso correctamente'
        };
      } else {
        return {
          success: false,
          error: data.error || 'Error al imprimir'
        };
      }
    } catch (error) {
      console.error('Error al imprimir (HTTP):', error);
      
      if (error.name === 'TypeError' && error.message.includes('fetch')) {
        return {
          success: false,
          error: 'Servicio de impresión no disponible. Verifique que esté ejecutando print_service.py'
        };
      }
      
      return {
        success: false,
        error: error.message || 'Error desconocido al imprimir'
      };
    }
  }
}

/**
 * Ejecuta una impresión de prueba
 * @returns {Promise<{success: boolean, message?: string, error?: string}>}
 */
export async function testPrint() {
  if (isElectron()) {
    return await window.turneroAPI.testPrint();
  } else {
    return await printTicket({
      ticket_number: 'TEST-001',
      service_name: 'Prueba de Impresión',
      customer_name: 'Test Automático'
    });
  }
}

/**
 * Obtiene la configuración del totem (solo disponible en Electron)
 * @returns {Promise<{totemCode?: string, serverUrl?: string, companyName?: string} | null>}
 */
export async function getTotemConfig() {
  if (isElectron()) {
    return await window.turneroAPI.getConfig();
  }
  return null;
}

export default {
  isElectron,
  getPrinterInfo,
  isPrintServiceAvailable,
  printTicket,
  testPrint,
  getTotemConfig
};

