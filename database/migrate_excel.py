"""
Script de migración de datos desde Excel hacia MySQL (cmco_citas_db).
Extrae pacientes, médicos, consultorios, tasas históricas y citas existentes.
"""
import openpyxl
import glob
import os
import re

def clean_val(v):
    if v is None:
        return None
    s = str(v).strip()
    if s == '' or s == '0' or s == 'None':
        return None
    return s

def sql_escape(v):
    if v is None:
        return 'NULL'
    s = str(v).replace('\\', '\\\\').replace("'", "\\'")
    return f"'{s}'"

def main():
    files = glob.glob('*.xlsx')
    if not files:
        print("No se encontró archivo Excel.")
        return
    excel_path = files[0]
    print(f"Cargando {excel_path}...")
    wb = openpyxl.load_workbook(excel_path, data_only=True)
    
    sql_lines = [
        "SET NAMES utf8mb4;",
        "USE cmco_citas_db;",
        "SET FOREIGN_KEY_CHECKS = 0;"
    ]
    
    # 1. Migrar Base de Datos de Pacientes
    ws_db = wb['Base de Datos']
    print(f"Procesando hoja 'Base de Datos' ({ws_db.max_row} filas)...")
    
    patients_seen = {}
    
    for r in range(2, ws_db.max_row + 1):
        nombre = clean_val(ws_db.cell(r, 1).value)
        if not nombre:
            continue
        
        repres = clean_val(ws_db.cell(r, 2).value)
        telef = clean_val(ws_db.cell(r, 3).value)
        cedula = clean_val(ws_db.cell(r, 4).value)
        emp_rol = clean_val(ws_db.cell(r, 5).value)
        
        # Clasificar tipo de beneficio según columna Empleado
        tipo_beneficio = 'Particular'
        if emp_rol:
            emp_lower = emp_rol.lower()
            if 'emplead' in emp_lower or 'vice-presidente' in emp_lower or 'personal' in emp_lower:
                tipo_beneficio = 'Empleado'
            elif 'familiar' in emp_lower or 'hijo' in emp_lower:
                tipo_beneficio = 'Familiar'
            elif 'participante' in emp_lower:
                tipo_beneficio = 'Participante'
            else:
                tipo_beneficio = emp_rol
        
        # Formatear teléfono si es numérico
        if telef and telef.isdigit() and len(telef) == 10 and not telef.startswith('0'):
            telef = '0' + telef
            
        key = (nombre.lower(), cedula.lower() if cedula else '')
        if key not in patients_seen:
            patients_seen[key] = {
                'nombre': nombre,
                'cedula': cedula,
                'telefono': telef,
                'representante': repres,
                'tipo_beneficio': tipo_beneficio,
                'observaciones': f"Rol/Obs: {emp_rol}" if emp_rol and emp_rol != tipo_beneficio else None
            }
            
    print(f"Total pacientes únicos a migrar: {len(patients_seen)}")
    
    for p in patients_seen.values():
        sql_lines.append(
            f"INSERT INTO pacientes (nombre, cedula, telefono, representante, tipo_beneficio, observaciones) "
            f"VALUES ({sql_escape(p['nombre'])}, {sql_escape(p['cedula'])}, {sql_escape(p['telefono'])}, "
            f"{sql_escape(p['representante'])}, {sql_escape(p['tipo_beneficio'])}, {sql_escape(p['observaciones'])});"
        )
        
    # 2. Migrar Citas históricas desde hojas mensuales (Septiembre, Octubre, Noviembre)
    for sname in ['Septiembre', 'Octubre', 'Noviembre']:
        if sname not in wb.sheetnames:
            continue
        ws = wb[sname]
        print(f"Procesando citas históricas de hoja '{sname}'...")
        
        # En cada hoja mensual, recorrer la matriz por días
        # Cada día inicia con una fecha en col 1 (A), luego fila 5 headers, y 13 bloques de horas
        # Usemos la tabla plana AN:AY (cols 40:51) que ya contiene las citas del mes
        for r in range(6, ws.max_row + 1):
            fecha = ws.cell(r, 40).value # AN
            cons = clean_val(ws.cell(r, 41).value) # AO
            estado = clean_val(ws.cell(r, 42).value) # AP
            paciente = clean_val(ws.cell(r, 43).value) # AQ
            precio = ws.cell(r, 44).value # AR
            tipo = clean_val(ws.cell(r, 45).value) # AS
            f1 = clean_val(ws.cell(r, 46).value) # AT
            m1 = ws.cell(r, 47).value # AU
            f2 = clean_val(ws.cell(r, 48).value) # AV
            m2 = ws.cell(r, 49).value # AW
            emp_fac = clean_val(ws.cell(r, 50).value) # AX
            doctor = clean_val(ws.cell(r, 51).value) # AY
            
            # Si no hay paciente o es '0', omitir
            if not paciente or paciente == '0':
                continue
                
            # Formatear fecha
            if hasattr(fecha, 'strftime'):
                fecha_str = fecha.strftime('%Y-%m-%d')
            elif isinstance(fecha, str) and re.match(r'^\d{4}-\d{2}-\d{2}', fecha):
                fecha_str = fecha[:10]
            else:
                continue
                
            # Mapear estado a texto limpio
            estado_map = {
                '📌': 'Agendada',
                '📞': 'Sin confirmar',
                '✔️': 'Confirmado',
                '✅': 'Completado',
                '🚫': 'No asistio',
                '💲': 'Pagado',
                '📦': 'Facturado'
            }
            db_estado = estado_map.get(estado, 'Agendada')
            
            # Mapear tipo
            tipo_map = {
                'Particular': 'Particular',
                'Empleado': 'Empleado',
                'Familiar': 'Familiar',
                'Participante': 'Participante',
                'Exonerado ADH': 'Exonerado ADH',
                'Exonerado Doctor': 'Exonerado Doctor'
            }
            db_tipo = tipo_map.get(tipo, 'Particular')
            
            try:
                db_precio = float(precio) if precio else 50.00
            except:
                db_precio = 50.00
                
            # Asegurar paciente exista
            sql_lines.append(
                f"INSERT INTO pacientes (nombre, tipo_beneficio) "
                f"SELECT {sql_escape(paciente)}, {sql_escape(db_tipo)} "
                f"WHERE NOT EXISTS (SELECT 1 FROM pacientes WHERE nombre = {sql_escape(paciente)} LIMIT 1);"
            )
            
            # Cita query
            sql_lines.append(
                f"INSERT INTO citas (fecha, hora, consultorio_id, medico_id, paciente_id, estado, tipo, precio_usd, factura_o_empleado) "
                f"SELECT '{fecha_str}', '08:00:00', "
                f"COALESCE((SELECT id FROM consultorios WHERE nombre = {sql_escape(cons)} LIMIT 1), 1), "
                f"COALESCE((SELECT id FROM medicos WHERE nombre = {sql_escape(doctor)} LIMIT 1), 1), "
                f"(SELECT id FROM pacientes WHERE nombre = {sql_escape(paciente)} ORDER BY id DESC LIMIT 1), "
                f"{sql_escape(db_estado)}, {sql_escape(db_tipo)}, {db_precio}, {sql_escape(emp_fac)};"
            )
            
            # Pagos
            # Si tiene forma 1 y monto 1
            if f1 and m1 and clean_val(m1):
                try:
                    m1_val = float(m1)
                    if m1_val > 0:
                        sql_lines.append(
                            f"INSERT INTO pagos_citas (cita_id, numero_pago, forma_pago, monto_usd, monto_bs, tasa_cambio) "
                            f"SELECT LAST_INSERT_ID(), 1, {sql_escape(f1)}, {m1_val}, 0, 888.8909;"
                        )
                except:
                    pass
                    
            if f2 and m2 and clean_val(m2):
                try:
                    m2_val = float(m2)
                    if m2_val > 0:
                        sql_lines.append(
                            f"INSERT INTO pagos_citas (cita_id, numero_pago, forma_pago, monto_usd, monto_bs, tasa_cambio) "
                            f"SELECT LAST_INSERT_ID(), 2, {sql_escape(f2)}, {m2_val}, 0, 888.8909;"
                        )
                except:
                    pass

    sql_lines.append("SET FOREIGN_KEY_CHECKS = 1;")
    
    out_file = os.path.join('database', 'imported_data.sql')
    print(f"Escribiendo {len(sql_lines)} sentencias SQL en {out_file}...")
    with open(out_file, 'w', encoding='utf-8') as f:
        f.write('\n'.join(sql_lines))
        
    print("¡Generación completada exitosamente!")

if __name__ == '__main__':
    main()
