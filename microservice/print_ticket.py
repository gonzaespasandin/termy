# -*- coding: utf-8 -*-
"""
Script de impresion de tickets para Turnero Totem
Usa win32print/win32ui para impresion directa en impresoras termicas
Este script es llamado por Electron para imprimir

Uso: python print_ticket.py <numero_ticket> <servicio> <cliente> <empresa>
"""

import sys
import io

# Configurar stdout para UTF-8 en Windows
if sys.platform == 'win32':
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')
import win32print
import win32ui
import win32con

# PRINTER_STATUS_PAPER_OUT no siempre esta expuesto segun la version de pywin32;
# 0x00000010 es el valor estandar de winspool.h
PRINTER_STATUS_PAPER_OUT = getattr(win32print, "PRINTER_STATUS_PAPER_OUT", 0x00000010)


def _printer_sin_papel(hprinter):
    """Devuelve True si el spooler reporta PRINTER_STATUS_PAPER_OUT para esta impresora.

    Best-effort: depende de que el driver de la impresora reporte el bit de
    estado correctamente. Si la consulta falla, devuelve False (no rompe el flujo).
    """
    if not hprinter:
        return False
    try:
        info = win32print.GetPrinter(hprinter, 2)
        status = info.get("Status", 0)
        return bool(status & PRINTER_STATUS_PAPER_OUT)
    except Exception:
        return False


def imprimir_ticket(ticket_numero, nombre_servicio, nombre_cliente="", nombre_empresa="Dayco Gaming S.A."):
    """
    Imprime un ticket usando la API nativa de Windows
    Funciona correctamente con impresoras termicas
    """
    hprinter = None
    try:
        # 1. Obtener la impresora predeterminada
        printer_name = win32print.GetDefaultPrinter()
        hprinter = win32print.OpenPrinter(printer_name)
        printer_info = win32print.GetPrinter(hprinter, 2)
        printer_name = printer_info["pPrinterName"]
        
        print(f"[PRINT] Imprimiendo en: {printer_name}")

        # 2. Crear el DC de impresion
        dc = win32ui.CreateDC()
        dc.CreatePrinterDC(printer_name)

        # 3. Iniciar documento
        dc.StartDoc('Ticket de Turno')
        dc.StartPage()

        # 4. Obtener ancho de pagina
        page_width = dc.GetDeviceCaps(win32con.HORZRES)

        # 5. Crear fuentes
        font_small = win32ui.CreateFont({
            "name": "Arial",
            "height": 35,
            "weight": 400,
        })
        
        font_header = win32ui.CreateFont({
            "name": "Arial",
            "height": 45,
            "weight": 600,
        })
        
        font_ticket_num = win32ui.CreateFont({
            "name": "Arial",
            "height": 96,
            "weight": 700,
        })
        
        font_service = win32ui.CreateFont({
            "name": "Arial",
            "height": 40,
            "weight": 600,
        })

        # 6. Centrar texto
        dc.SetTextAlign(win32con.TA_CENTER)
        center_x = page_width // 2

        # 7. Imprimir contenido
        y_pos = 10
        
        # Linea decorativa
        dc.SelectObject(font_small)
        dc.TextOut(center_x, y_pos, "* * * * * * * *")
        y_pos += 50
        
        # Nombre empresa
        dc.SelectObject(font_header)
        dc.TextOut(center_x, y_pos, nombre_empresa)
        y_pos += 80
        
        # TURNO
        dc.TextOut(center_x, y_pos, "TURNO")
        y_pos += 80

        # Numero de ticket (grande)
        dc.SelectObject(font_ticket_num)
        dc.TextOut(center_x, y_pos, f"#{ticket_numero}")
        y_pos += 160

        # Servicio
        dc.SelectObject(font_service)
        dc.TextOut(center_x, y_pos, nombre_servicio)
        y_pos += 70

        # Cliente (si existe)
        if nombre_cliente and nombre_cliente.strip():
            dc.SelectObject(font_small)
            dc.TextOut(center_x, y_pos, nombre_cliente)
            y_pos += 60

        # Linea divisoria
        dc.SelectObject(font_small)
        dc.TextOut(center_x, y_pos, "- - - - - - - - - - - -")
        y_pos += 50
        
        # Fecha y hora
        from datetime import datetime
        now = datetime.now()
        fecha = now.strftime("%d/%m/%Y")
        hora = now.strftime("%H:%M:%S")
        
        dc.TextOut(center_x, y_pos, fecha)
        y_pos += 45
        dc.TextOut(center_x, y_pos, hora)
        y_pos += 60
        
        # Linea divisoria
        dc.TextOut(center_x, y_pos, "- - - - - - - - - - - -")
        y_pos += 50
        
        # Mensaje
        dc.TextOut(center_x, y_pos, "Por favor espere")
        y_pos += 45
        dc.TextOut(center_x, y_pos, "a ser llamado")
        y_pos += 60
        
        # Gracias
        dc.SelectObject(font_header)
        dc.TextOut(center_x, y_pos, "Gracias!")
        y_pos += 70
        
        # Linea decorativa final
        dc.SelectObject(font_small)
        dc.TextOut(center_x, y_pos, "* * * * * * * *")
        y_pos += 80  # Espacio extra para que salga el papel

        # 8. Finalizar
        dc.EndPage()
        dc.EndDoc()
        dc.DeleteDC()

        if _printer_sin_papel(hprinter):
            win32print.ClosePrinter(hprinter)
            print("[ERROR] Impresora sin papel", file=sys.stderr)
            print("SIN_PAPEL", file=sys.stderr)
            return False

        win32print.ClosePrinter(hprinter)
        print(f"[OK] Ticket {ticket_numero} impreso correctamente")
        return True

    except Exception as e:
        print(f"[ERROR] Error al imprimir: {str(e)}", file=sys.stderr)
        if _printer_sin_papel(hprinter):
            print("SIN_PAPEL", file=sys.stderr)
        return False


if __name__ == '__main__':
    # Obtener argumentos de linea de comandos
    if len(sys.argv) < 3:
        print("Uso: python print_ticket.py <numero_ticket> <servicio> [cliente] [empresa]")
        print("Ejemplo: python print_ticket.py AC-001 'Atencion al Cliente' 'Juan Perez' 'Mi Empresa'")
        sys.exit(1)
    
    ticket_numero = sys.argv[1]
    nombre_servicio = sys.argv[2]
    nombre_cliente = sys.argv[3] if len(sys.argv) > 3 else ""
    nombre_empresa = sys.argv[4] if len(sys.argv) > 4 else "Dayco Gaming S.A."
    
    success = imprimir_ticket(ticket_numero, nombre_servicio, nombre_cliente, nombre_empresa)
    sys.exit(0 if success else 1)

