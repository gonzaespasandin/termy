# -*- coding: utf-8 -*-
"""
Script de impresion de tickets para Turnero Totem
Usa win32print/win32ui para impresion directa en impresoras termicas
Este script es llamado por Electron para imprimir

Uso: python print_ticket.py <numero_ticket> <servicio> <cliente> <empresa>
"""

import sys
import io
import os
import time
import tempfile
from datetime import datetime

# Configurar stdout para UTF-8 en Windows
if sys.platform == 'win32':
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')
import win32print
import win32ui
import win32con

# ------------------------------------------------------------------
# Logging a archivo + stdout (para diagnosticar en el totem sin consola)
# ------------------------------------------------------------------
LOG_PATH = os.path.join(tempfile.gettempdir(), 'turnero_print.log')


def log(msg):
    """Escribe en stdout y agrega al archivo de log con timestamp."""
    line = f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] {msg}"
    print(msg)
    try:
        with open(LOG_PATH, 'a', encoding='utf-8') as f:
            f.write(line + '\n')
    except Exception:
        pass

# Flags de estado del job (winspool.h)
JOB_STATUS_ERROR             = 0x0002
JOB_STATUS_OFFLINE           = 0x0020
JOB_STATUS_PAPEROUT          = 0x0040
JOB_STATUS_PRINTED           = 0x0080
JOB_STATUS_DELETED           = 0x0100
JOB_STATUS_BLOCKED_DEVQ      = 0x0200
JOB_STATUS_USER_INTERVENTION = 0x0400
JOB_STATUS_COMPLETE          = 0x1000

_JOB_ERROR_FLAGS   = JOB_STATUS_ERROR | JOB_STATUS_OFFLINE | JOB_STATUS_PAPEROUT | JOB_STATUS_BLOCKED_DEVQ | JOB_STATUS_USER_INTERVENTION
_JOB_SUCCESS_FLAGS = JOB_STATUS_PRINTED | JOB_STATUS_COMPLETE | JOB_STATUS_DELETED

# Flags de estado de la impresora (PRINTER_INFO_2.Status)
PRINTER_STATUS_PAPER_OUT = 0x00000010
PRINTER_STATUS_OFFLINE   = 0x00000080
PRINTER_STATUS_ERROR     = 0x00000002


def _check_wmi(printer_name):
    """
    Consulta Win32_Printer via WMI — la fuente mas confiable para errores
    de hardware porque el driver la actualiza aunque el spooler no lo haga.

    DetectedErrorState: 2=NoError 3=LowPaper 4=NoPaper 7=DoorOpen 8=Jammed 9=ServiceReq
    ExtendedPrinterStatus:
      2=Idle 3=Printing 4=Warmup 8=Busy 10=Waiting 11=Processing 12=Init 13=PowerSave
      → todo lo demas es error:
      5=StoppedPrinting 6=Offline 7=Error 9=NotAvailable 14=PendingDel
      16=ManualFeed 17=NoToner 18=DoorOpen 19=PaperOut 20=OutputBinFull
      21=PaperProblem 22=CannotPrint
    PrinterStatus (Win32_Printer): 6=Offline 7=Error

    Retorna (ok, error_code) o (None, None) si WMI no esta disponible.
    """
    # ExtendedPrinterStatus que indican estado OK (impresora usable)
    EXT_OK = {1, 2, 3, 4, 8, 10, 11, 12, 13, 15}

    try:
        import win32com.client
        wmi = win32com.client.GetObject("winmgmts:")
        safe_name = printer_name.replace("'", "\\'")
        results = wmi.ExecQuery(
            f"SELECT DetectedErrorState,ExtendedPrinterStatus,PrinterStatus"
            f" FROM Win32_Printer WHERE Name='{safe_name}'"
        )
        for p in results:
            det  = int(getattr(p, 'DetectedErrorState',    0) or 0)
            ext  = int(getattr(p, 'ExtendedPrinterStatus', 0) or 0)
            base = int(getattr(p, 'PrinterStatus',         0) or 0)
            log(f"[WMI] DetectedErrorState={det}  ExtendedStatus={ext}  PrinterStatus={base}")

            # --- Papel ---
            if det == 4 or ext == 19:
                return False, "SIN_PAPEL"

            # --- Offline ---
            if ext == 6 or base == 7:
                return False, "OFFLINE"

            # --- Otros errores de hardware ---
            # det: 7=DoorOpen 8=Jammed 9=ServiceReq 11=PaperProblem
            if det in (7, 8, 9, 11):
                return False, "ERROR_IMPRESORA"
            # ext fuera del conjunto OK (9=NotAvailable, 5=Stopped, 7=Error, etc.)
            if ext != 0 and ext not in EXT_OK:
                return False, "ERROR_IMPRESORA"

            return True, None

        return True, None  # impresora no encontrada en WMI

    except Exception as e:
        log(f"[WMI] No disponible: {e}")
        return None, None  # no bloquear si WMI falla


def _check_printer_status(hprinter):
    """Chequeo via PRINTER_INFO_2 (fallback si WMI no esta disponible)."""
    try:
        info = win32print.GetPrinter(hprinter, 2)
        status = info.get("Status", 0)
        log(f"[INFO2] Status=0x{status:08X}")
        if status & PRINTER_STATUS_PAPER_OUT:
            return False, "SIN_PAPEL"
        if status & PRINTER_STATUS_OFFLINE:
            return False, "OFFLINE"
        if status & PRINTER_STATUS_ERROR:
            return False, "ERROR_IMPRESORA"
        return True, None
    except Exception:
        return True, None


def _find_our_job(hprinter, job_id, doc_name):
    """
    Busca nuestro job en la cola del spooler.

    Retorna (present, status):
      present=True  -> nuestro job sigue en cola (status = sus flags)
      present=False -> nuestro job ya no esta (salio = se imprimio)
      present=None  -> no se pudo enumerar la cola
    """
    try:
        jobs = win32print.EnumJobs(hprinter, 0, -1, 1)
    except Exception:
        return None, 0

    for j in jobs:
        jid = j.get("JobId", 0)
        doc = j.get("pDocument", "") or ""
        # Preferir match exacto por JobId; si no tenemos id, match por nombre
        if (job_id and jid == job_id) or (not job_id and doc == doc_name):
            return True, j.get("Status", 0)

    return False, 0


def _wait_for_job(hprinter, printer_name, job_id, doc_name, timeout=8.0):
    """
    Monitorea nuestro job hasta que se imprima o falle.

    PRINCIPIO CLAVE: un ticket esta impreso SOLO si su job sale de la cola
    del spooler. Si el job sigue encolado, fisicamente no salio de la
    impresora. Por eso:
      - job desaparecio de la cola  -> EXITO (se imprimio)
      - job con flags de error      -> FALLO
      - WMI reporta error de hardware -> FALLO (deteccion rapida)
      - TIMEOUT con job aun en cola  -> FALLO (nunca se imprimio)

    EndDoc() es asincrono: el job queda en cola y el driver tarda unos
    segundos en intentar transmitir y descubrir el error de papel.
    """
    deadline = time.time() + timeout
    saw_our_job = False

    while time.time() < deadline:
        # --- Deteccion rapida via WMI (errores de hardware) ---
        ok, err = _check_wmi(printer_name)
        if ok is False:
            return False, err

        # --- Estado de nuestro job en la cola ---
        present, status = _find_our_job(hprinter, job_id, doc_name)

        if present is True:
            saw_our_job = True
            log(f"[JOB id={job_id}] Status=0x{status:08X}")
            if status & JOB_STATUS_PAPEROUT:
                return False, "SIN_PAPEL"
            if status & JOB_STATUS_OFFLINE:
                return False, "OFFLINE"
            if status & _JOB_ERROR_FLAGS:
                return False, "ERROR_IMPRESORA"
            if status & _JOB_SUCCESS_FLAGS:
                # Job marcado como impreso (caso "conservar documentos")
                log("[JOB] Marcado como impreso")
                return True, None
            # sigue trabajando, continuar

        elif present is False and (saw_our_job or job_id):
            # Nuestro job salio de la cola: se imprimio fisicamente
            log("[JOB] Salio de la cola -> impreso")
            return True, None

        # --- Fallback PRINTER_INFO_2 ---
        ok3, err3 = _check_printer_status(hprinter)
        if not ok3:
            return False, err3

        time.sleep(0.4)

    # TIMEOUT: si nuestro job sigue en cola, nunca se imprimio -> FALLO
    present, status = _find_our_job(hprinter, job_id, doc_name)
    if present is True:
        log(f"[WAIT] Timeout con job aun en cola (Status=0x{status:08X}) -> FALLO")
        return False, "ERROR_IMPRESORA"

    # Si el job ya no esta y nunca vimos error, asumir exito
    log("[WAIT] Timeout sin job en cola -> asumiendo exito")
    return True, None


def imprimir_ticket(ticket_numero, nombre_servicio, nombre_cliente="", nombre_empresa="Dayco Gaming S.A."):
    """
    Imprime un ticket usando la API nativa de Windows.
    Funciona correctamente con impresoras termicas.
    """
    hprinter = None
    DOC_NAME = 'Ticket de Turno'
    try:
        # 1. Obtener la impresora predeterminada
        printer_name = win32print.GetDefaultPrinter()
        hprinter = win32print.OpenPrinter(printer_name)
        printer_info = win32print.GetPrinter(hprinter, 2)
        printer_name = printer_info["pPrinterName"]
        log(f"[PRINT] Imprimiendo {ticket_numero} en: {printer_name}")

        # 2. Chequeo previo via WMI — rechaza al instante si el hardware ya
        # reporta error en frio (impresora arranca sin papel, apagada, etc.)
        ok, error_code = _check_wmi(printer_name)
        if ok is None:
            ok, error_code = _check_printer_status(hprinter)
        if ok is False:
            win32print.ClosePrinter(hprinter)
            log(f"[ERROR] Impresora no lista (pre-check): {error_code}")
            print(error_code, file=sys.stderr)
            return False

        # 3. Crear el DC de impresion
        dc = win32ui.CreateDC()
        dc.CreatePrinterDC(printer_name)

        # 4. Iniciar documento. StartDoc devuelve el JobId del spooler;
        # lo usamos para rastrear ESTE job especifico al monitorear.
        job_id = 0
        try:
            ret = dc.StartDoc(DOC_NAME)
            if isinstance(ret, int) and ret > 0:
                job_id = ret
        except Exception:
            pass
        dc.StartPage()

        # Si StartDoc no nos dio un JobId, buscarlo en la cola por nombre
        if not job_id:
            try:
                for j in win32print.EnumJobs(hprinter, 0, -1, 1):
                    if (j.get("pDocument", "") or "") == DOC_NAME:
                        job_id = j.get("JobId", 0)
            except Exception:
                pass
        log(f"[PRINT] JobId={job_id}")

        # 5. Ancho de pagina
        page_width = dc.GetDeviceCaps(win32con.HORZRES)

        # 6. Fuentes
        font_small = win32ui.CreateFont({"name": "Arial", "height": 35, "weight": 400})
        font_header = win32ui.CreateFont({"name": "Arial", "height": 45, "weight": 600})
        font_ticket_num = win32ui.CreateFont({"name": "Arial", "height": 96, "weight": 700})
        font_service = win32ui.CreateFont({"name": "Arial", "height": 40, "weight": 600})

        # 7. Centrar texto
        dc.SetTextAlign(win32con.TA_CENTER)
        center_x = page_width // 2
        y_pos = 10

        # 8. Layout del ticket
        dc.SelectObject(font_small)
        dc.TextOut(center_x, y_pos, "* * * * * * * *"); y_pos += 50

        dc.SelectObject(font_header)
        dc.TextOut(center_x, y_pos, nombre_empresa); y_pos += 80
        dc.TextOut(center_x, y_pos, "TURNO"); y_pos += 80

        dc.SelectObject(font_ticket_num)
        dc.TextOut(center_x, y_pos, f"#{ticket_numero}"); y_pos += 160

        dc.SelectObject(font_service)
        dc.TextOut(center_x, y_pos, nombre_servicio); y_pos += 70

        if nombre_cliente and nombre_cliente.strip():
            dc.SelectObject(font_small)
            dc.TextOut(center_x, y_pos, nombre_cliente); y_pos += 60

        dc.SelectObject(font_small)
        dc.TextOut(center_x, y_pos, "- - - - - - - - - - - -"); y_pos += 50

        from datetime import datetime
        now = datetime.now()
        dc.TextOut(center_x, y_pos, now.strftime("%d/%m/%Y")); y_pos += 45
        dc.TextOut(center_x, y_pos, now.strftime("%H:%M:%S")); y_pos += 60

        dc.TextOut(center_x, y_pos, "- - - - - - - - - - - -"); y_pos += 50
        dc.TextOut(center_x, y_pos, "Por favor espere"); y_pos += 45
        dc.TextOut(center_x, y_pos, "a ser llamado"); y_pos += 60

        dc.SelectObject(font_header)
        dc.TextOut(center_x, y_pos, "Gracias!"); y_pos += 70

        dc.SelectObject(font_small)
        dc.TextOut(center_x, y_pos, "* * * * * * * *"); y_pos += 80

        # 9. Finalizar y encolar en el spooler (asincrono)
        dc.EndPage()
        dc.EndDoc()
        dc.DeleteDC()

        # 10. Monitorear resultado real del job.
        # Un ticket esta impreso SOLO si su job sale de la cola. Si sigue
        # encolado al expirar el timeout, fisicamente no se imprimio -> fallo.
        ok, error_code = _wait_for_job(hprinter, printer_name, job_id, DOC_NAME, timeout=8.0)
        win32print.ClosePrinter(hprinter)

        if not ok:
            log(f"[ERROR] Error detectado post-impresion: {error_code}")
            print(error_code, file=sys.stderr)
            return False

        log(f"[OK] Ticket {ticket_numero} impreso correctamente")
        return True

    except Exception as e:
        log(f"[ERROR] Error al imprimir: {str(e)}")
        print("ERROR_IMPRESORA", file=sys.stderr)
        if hprinter:
            try:
                win32print.ClosePrinter(hprinter)
            except Exception:
                pass
        return False


if __name__ == '__main__':
    if len(sys.argv) < 3:
        print("Uso: python print_ticket.py <numero_ticket> <servicio> [cliente] [empresa]")
        print("Ejemplo: python print_ticket.py AC-001 'Atencion al Cliente' 'Juan Perez' 'Mi Empresa'")
        sys.exit(1)

    ticket_numero    = sys.argv[1]
    nombre_servicio  = sys.argv[2]
    nombre_cliente   = sys.argv[3] if len(sys.argv) > 3 else ""
    nombre_empresa   = sys.argv[4] if len(sys.argv) > 4 else "Dayco Gaming S.A."

    success = imprimir_ticket(ticket_numero, nombre_servicio, nombre_cliente, nombre_empresa)
    sys.exit(0 if success else 1)
