/**
 * Turnero Totem - Aplicación de Escritorio
 * Main Process de Electron
 */

const { app, BrowserWindow, ipcMain, dialog, Menu, shell } = require('electron');
const path = require('path');
const { loadEnv } = require('./src/envLoader');

// Mantener referencia global de la ventana
let mainWindow = null;

let config = null;
let ticketPrinter = null;
let isShowingErrorPage = false;

function getServerUrl() {
  return `${config.serverUrl}?totem=${config.totemCode}`;
}

// Muestra la pantalla de error, evitando recargas duplicadas si ya está visible
function showErrorPage() {
  if (!mainWindow || isShowingErrorPage) return;
  isShowingErrorPage = true;
  mainWindow.loadFile(path.join(__dirname, 'src', 'error.html'));
}

// Carga (o recarga) la URL del servidor
function loadServer() {
  if (!mainWindow) return;
  const serverUrl = getServerUrl();
  isShowingErrorPage = false;

  console.log('🚀 Cargando:', serverUrl);

  mainWindow.loadURL(serverUrl).catch((err) => {
    console.error('❌ Error cargando URL:', err);
    showErrorPage();
  });
}

function createWindow() {
  // Crear la ventana del navegador
  mainWindow = new BrowserWindow({
    width: 1080,
    height: 1920,
    fullscreen: true, 
    kiosk: true,       
    frame: config.isDev,        
    autoHideMenuBar: true,
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      preload: path.join(__dirname, 'preload.js')
    }
  });

  // Cargar la URL del servidor
  loadServer();

  // Log cuando la página termine de cargar
  mainWindow.webContents.on('did-finish-load', () => {
    console.log('[MAIN] Pagina cargada correctamente');
  });

  // Prevenir navegación a otras URLs
  mainWindow.webContents.on('will-navigate', (event, url) => {
    if (!url.startsWith(config.serverUrl)) {
      event.preventDefault();
    }
  });

  // Manejar cierre de ventana
  mainWindow.on('closed', () => {
    mainWindow = null;
  });

  // Manejar errores de carga (solo del frame principal)
  mainWindow.webContents.on('did-fail-load', (event, errorCode, errorDescription, validatedURL, isMainFrame) => {
    if (!isMainFrame) return;
    console.error(`❌ Error de carga: ${errorDescription} (${errorCode})`);
    showErrorPage();
  });
}

// ============================================================
// IPC Handlers - Comunicación con el Renderer Process
// ============================================================

function registerIpcHandlers() {
  // Imprimir ticket
  ipcMain.handle('print-ticket', async (event, ticketData) => {
    console.log('📥 Solicitud de impresión:', ticketData);

    try {
      await ticketPrinter.printTicket(ticketData);
      return { success: true, message: 'Ticket impreso correctamente' };
    } catch (error) {
      console.error('❌ Error al imprimir:', error);
      return { success: false, error: error.message };
    }
  });

  // Obtener impresora predeterminada
  ipcMain.handle('get-printer', async () => {
    try {
      const printer = await ticketPrinter.getDefaultPrinter();
      return { success: true, printer };
    } catch (error) {
      return { success: false, error: error.message };
    }
  });

  // Test de impresión
  ipcMain.handle('test-print', async () => {
    try {
      await ticketPrinter.testPrint();
      return { success: true, message: 'Test de impresión enviado' };
    } catch (error) {
      return { success: false, error: error.message };
    }
  });

  // Obtener configuración del totem
  ipcMain.handle('get-config', () => {
    return {
      totemCode: config.totemCode,
      serverUrl: config.serverUrl,
      companyName: config.companyName
    };
  });

  // Recargar la aplicación
  ipcMain.handle('reload-app', () => {
    if (mainWindow) {
      mainWindow.reload();
    }
  });

  // Reintentar conexión con el servidor (desde la pantalla de error)
  ipcMain.handle('retry-connection', () => {
    console.log('🔄 Reintentando conexión (manual)...');
    loadServer();
  });

  // Abrir carpeta de configuración (soporte)
  ipcMain.handle('open-config-folder', async () => {
    try {
      const userDataDir = app.getPath('userData');
      await shell.openPath(userDataDir);
      return { success: true };
    } catch (error) {
      return { success: false, error: error.message };
    }
  });
}

// ============================================================
// Ciclo de vida de la aplicación
// ============================================================

app.whenReady().then(() => {
  // 1) Cargar/crear .env en una ruta editable (userData)
  const envInfo = loadEnv({ app });

  // 2) Cargar config y módulos dependientes del env
  config = require('./src/config');
  ticketPrinter = require('./src/print/ticketPrinter');

  // Deshabilitar el menú en producción
  if (!config.isDev) {
    Menu.setApplicationMenu(null);
  }

  console.log('');
  console.log('═'.repeat(60));
  console.log('  🖥️  TURNERO TOTEM - Aplicación de Escritorio');
  console.log('═'.repeat(60));
  console.log('');
  console.log('📋 Configuración:');
  console.log(`   Servidor: ${config.serverUrl}`);
  console.log(`   Totem: ${config.totemCode}`);
  console.log(`   Empresa: ${config.companyName}`);
  console.log(`   Modo: ${config.isDev ? 'Desarrollo' : 'Producción'}`);
  console.log(`   .env (preferido): ${envInfo.preferredEnvPath}`);
  if (envInfo.loaded?.length) {
    console.log('   .env cargados:');
    envInfo.loaded.forEach((p) => console.log(`     - ${p}`));
  }
  console.log('');

  if (envInfo.created) {
    try {
      dialog.showMessageBoxSync({
        type: 'info',
        title: 'Configuración inicial',
        message: 'Se creó el archivo de configuración (.env) del Totem.',
        detail:
          `Editalo y reiniciá la aplicación.\n\nUbicación:\n${envInfo.preferredEnvPath}`,
        buttons: ['OK']
      });
    } catch (_) {
      // noop
    }
  }

  registerIpcHandlers();
  createWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

// Prevenir múltiples instancias
const gotTheLock = app.requestSingleInstanceLock();

if (!gotTheLock) {
  app.quit();
} else {
  app.on('second-instance', () => {
    if (mainWindow) {
      if (mainWindow.isMinimized()) mainWindow.restore();
      mainWindow.focus();
    }
  });
}

// Manejar errores no capturados
process.on('uncaughtException', (error) => {
  console.error('❌ Error no capturado:', error);
});

