/**
 * Carga y asegura la configuración del Totem desde un .env editable.
 *
 * Objetivo:
 * - En producción (instalado por NSIS): guardar y leer `.env` desde `app.getPath('userData')`
 *   (ej: C:\Users\<user>\AppData\Roaming\Turnero Totem\.env)
 * - Empaquetar `env.example` y `print_ticket.py` como extraResources
 * - Si no existe `.env`, copiarlo desde `env.example` para que el usuario lo edite.
 */

const fs = require('fs');
const path = require('path');
const dotenv = require('dotenv');

function safeMkdirp(dir) {
  try {
    fs.mkdirSync(dir, { recursive: true });
  } catch (_) {
    // noop
  }
}

function fileExists(p) {
  try {
    return fs.existsSync(p);
  } catch (_) {
    return false;
  }
}

function copyFileSafe(from, to) {
  try {
    fs.copyFileSync(from, to);
    return true;
  } catch (_) {
    return false;
  }
}

function getExecDir() {
  try {
    return path.dirname(process.execPath);
  } catch (_) {
    return process.cwd();
  }
}

function getEnvExamplePath(isPackaged) {
  // En producción, `env.example` se copia a `process.resourcesPath` via extraResources
  if (isPackaged) return path.join(process.resourcesPath, 'env.example');
  // En dev, está en la raíz del proyecto turnero-totem/
  return path.join(__dirname, '..', 'env.example');
}

/**
 * Retorna la ruta "preferida" del `.env` editable.
 * @param {import('electron').App} app
 */
function getPreferredEnvPath(app) {
  // userData suele ser: C:\Users\<user>\AppData\Roaming\<productName>\
  const userDataDir = app.getPath('userData');
  return path.join(userDataDir, '.env');
}

/**
 * Carga `.env` priorizando userData.
 * @param {object} opts
 * @param {import('electron').App} opts.app
 */
function loadEnv({ app }) {
  const isPackaged = app.isPackaged;
  const preferredEnvPath = getPreferredEnvPath(app);
  const existedBefore = fileExists(preferredEnvPath);
  const execDirEnvPath = path.join(getExecDir(), '.env');
  const cwdEnvPath = path.join(process.cwd(), '.env');

  // 1) Si no existe el `.env` en userData, intentar crearlo desde env.example
  if (!fileExists(preferredEnvPath)) {
    safeMkdirp(path.dirname(preferredEnvPath));
    const examplePath = getEnvExamplePath(isPackaged);
    if (fileExists(examplePath)) {
      copyFileSafe(examplePath, preferredEnvPath);
    } else {
      // fallback: crear un .env mínimo
      try {
        fs.writeFileSync(
          preferredEnvPath,
          [
            'SERVER_URL=http://localhost:5173',
            'TOTEM_CODE=TOTEM-DEFAULT',
            'COMPANY_NAME=Dayco Gaming S.A.',
            'PRINTER_NAME=',
            'TICKET_WIDTH=58',
            'AUTO_START=true',
            '',
            '# Opcional: ruta o comando de Python (si "python" no está en PATH)',
            '# PYTHON_EXE=python',
            ''
          ].join('\n'),
          'utf8'
        );
      } catch (_) {
        // noop
      }
    }
  }

  // 2) Cargar variables: prefer userData, luego carpeta del exe, luego cwd
  //    (no pisa variables ya existentes: dotenv por defecto NO overridea)
  const loaded = [];
  const candidates = [preferredEnvPath, execDirEnvPath, cwdEnvPath];
  for (const candidate of candidates) {
    if (fileExists(candidate)) {
      dotenv.config({ path: candidate });
      loaded.push(candidate);
    }
  }

  return {
    preferredEnvPath,
    created: !existedBefore && fileExists(preferredEnvPath),
    loaded
  };
}

module.exports = {
  loadEnv,
  getPreferredEnvPath
};


