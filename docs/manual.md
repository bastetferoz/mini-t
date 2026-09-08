# Mini-T — Manual de la plataforma

## Gestión de activos IT, offboarding y facturación

Sistema web (Laravel + Filament) para administrar equipos IT, el proceso de baja de empleados, la logística de devoluciones y el análisis de facturación de servicios, con carga de facturas asistida por IA.

---

## Índice

1. [Acceso y roles](#1-acceso-y-roles)
2. [Dashboard](#2-dashboard)
3. [Gente (personas)](#3-gente-personas)
4. [Activos](#4-activos)
5. [Asignación de equipos](#5-asignación-de-equipos)
6. [Proceso de baja (offboarding)](#6-proceso-de-baja-offboarding)
7. [Devoluciones y logística](#7-devoluciones-y-logística)
8. [Impresoras y conteo de páginas](#8-impresoras-y-conteo-de-páginas)
9. [Facturación](#9-facturación)
10. [Plantillas de correo](#10-plantillas-de-correo)
11. [Administración](#11-administración)
12. [Utilidades](#12-utilidades)
13. [Roles y permisos](#13-roles-y-permisos)
14. [Notas técnicas y mantenimiento](#14-notas-técnicas-y-mantenimiento)

---

## 1. Acceso y roles

**URL:** `http://tu-servidor/dashboard`

Ingreso con email y contraseña. Hay 3 roles:

- **Admin** — acceso total (incluye Administración y Backup).
- **IT** — activos, personas, facturación, impresoras, importaciones.
- **RRHH** — confirmación de recepción de equipos en el offboarding.

---

## 2. Dashboard

Al ingresar se ve el dashboard de devoluciones pendientes:

- **Métricas:** pendientes, en tránsito, entregados, demorados (>15 días).
- **Personas con equipos a recuperar:** lista con días de demora.
- **Panel de seguimiento (derecha):** al seleccionar una persona muestra la modalidad de envío, el número de seguimiento, el timeline de eventos y un botón "Actualizar" para consultar el estado en tiempo real.

---

## 3. Gente (personas)

**Menú:** Gente

Estados de una persona: **activo**, **offboarding** (en baja), **inactivo** (baja completada). El listado de "Gente" muestra activos y en offboarding; los inactivos se ven en la sección Offboardings.

### Crear / editar
Nombre, email, área y servicios asociados (Jira, Teams, Google Workspace, Slack, etc.).

### Ficha de persona
- Datos personales.
- Equipos asignados (dispositivo, marca, modelo, serie).
- Historial de activos (acciones previas).
- Acciones de offboarding (ver sección 6).

---

## 4. Activos

**Menú:** Activos *(admin/it)*

Campos: dispositivo, marca, modelo, procesador, memoria, disco, Nº serie, estado, observaciones.

### Estados de un activo
| Estado | Significado |
|--------|-------------|
| Disponible | Listo para asignar |
| En uso | Asignado a una persona |
| En devolución | En proceso de baja (in_transit) |
| Dado de baja | Retirado del inventario |

La vista de detalle incluye el historial completo del equipo (quién lo tuvo, motivos de movimiento, fechas).

---

## 5. Asignación de equipos

Desde la ficha de una persona, en la sección de equipos.

- **Asignar:** elegí un equipo *disponible*. Pasa a "En uso".
- **Desasignar:** motivo (upgrade, avería, devolución) + observación + correo opcional. Avería → el equipo pasa a "dado de baja"; el resto vuelve a "disponible".
- **Reemplazar:** elegí el nuevo equipo y el motivo (upgrade, avería, reemplazo preventivo, préstamo, extravío). El viejo se libera o se retira (avería/extravío) y el nuevo queda "En uso". Correo opcional.

---

## 6. Proceso de baja (offboarding)

### Solicitar baja *(admin/it)*
Desde la ficha, botón rojo "Solicitar baja". Muestra el resumen de equipos y servicios. Al confirmar:
- Los equipos pasan a "En devolución".
- Las asignaciones se archivan (soft delete).
- La persona pasa a "offboarding".

### Revertir baja *(solo admin)*
Restaura las asignaciones y devuelve los equipos a "En uso" y la persona a "activo".

### Registrar recepción — IT *(admin/it)*
Por cada equipo se marca si fue devuelto o no (motivo: ausente, roto, incompleto). Al confirmar, la persona pasa a "inactivo" y se envía el correo de resumen.

### Confirmar recepción — RRHH *(rrhh)*
Desde el dashboard, RRHH tilda los equipos recibidos. Queda registrado en el historial de forma separada de la confirmación de IT (doble confirmación).

---

## 7. Devoluciones y logística

Desde el dashboard, al seleccionar una persona:

- **EnvíoPack:** se ingresa el número de seguimiento. El sistema consulta el tracking público de EnvíoPack.
- **Moto / mensajería:** fecha de retiro y contacto.

### Tracking automático
- Consulta `api.enviopack.com/tracking/{numero}` (público, sin credenciales).
- Botón "Actualizar" para consulta manual; también corre programado.
- Estados: pendiente → colectado/retirado → en tránsito → entregado.
- Al detectar "entregado" se dispara un correo automático.
- Si el número está mal, se puede editar el envío.

---

## 8. Impresoras y conteo de páginas

**Menú:** Infra → Impresoras / Diagnóstico SNMP / Conteo de páginas *(admin/it)*

### Inventario de impresoras
Alta manual o desde el diagnóstico SNMP. Campos: nombre, tipo (red/manual), IP, marca, modelo, Nº serie, ubicación, comunidad SNMP.

- **Marca, modelo y Nº serie son editables.** Se completan automáticamente la primera vez que se verifica la impresora por SNMP, pero después se pueden corregir a mano y las lecturas automáticas ya no los sobrescriben. Útil cuando el SNMP devuelve la placa de red en lugar del modelo real.

### Diagnóstico SNMP
Herramienta para probar una IP: ping, lectura de OIDs de contador, walk del árbol SNMP y consulta de un OID puntual. Permite guardar la impresora detectada en el inventario.

### Conteo de páginas
- Selector de año y día de conteo automático mensual.
- Botón "Leer ahora" para sondear todas las impresoras de red al instante.
- **Cuadro mensual:** contador al cierre de cada mes.
- **Cuadro de diferencia mes a mes:** páginas nuevas impresas en cada mes (contador del mes menos el del anterior), con total anual.
- **Carga manual:** permite cargar a mano el contador al cierre de un mes (por si falta una lectura). Impacta en la tabla y el análisis.
- **Exportar (últimos 12 meses):** descarga un CSV con el contador y las páginas por mes de cada impresora.

---

## 9. Facturación

**Menú:** Facturación *(admin/it)*

### Carga (navegador de facturas)
Navegación por carpetas: **Proveedor → Año → tabla de facturas**.

#### Cargar con IA
1. Botón "Cargar con IA" y subir uno o varios archivos (PDF, JPG, PNG).
2. Cada archivo se encola y se procesa en segundo plano (con separación entre uno y otro para no saturar la IA).
3. La IA identifica el proveedor y extrae los datos (monto, moneda, fecha, período, Nº factura, referencia, empresa).
4. La factura se crea y el archivo se guarda en `storage/invoices/{proveedor}/{año}/{mes}/`.

**Reintentos:** si la IA falla por un límite temporal (rate limit / servidor saturado), el procesamiento se reintenta solo más tarde, para no perder la factura.

**Deduplicación:** cada archivo se identifica por su contenido (hash). Si un adjunto ya se procesó antes, se descarta sin volver a llamar a la IA (ahorra tokens en correos/cargas repetidas).

#### Carga manual
Formulario con todos los campos.

#### Acciones en la tabla
- **Período (columna editable):** selector de mes por factura. Cambia el mes al que se imputa la factura en el análisis. La elección manual manda sobre el criterio automático.
- **Mover a:** reasigna la factura a otro proveedor.
- **Reclasificar (keywords):** mueve las facturas de "otro" al proveedor cuyo texto/keywords coincidan (no usa IA).
- **Reclasificar con IA:** re-consulta la IA para identificar el proveedor (usa tokens).
- **Eliminar duplicados** y papelera individual.
- **Asignar empresa:** Novatech, Phinxlab o Cryptopatagonia (individual o masivo).

#### Cómo se determina el mes de una factura
1. Se usa el **período de servicio** que declara la factura (ej: "Summary for Mar 1 - Mar 31").
2. Si ese período difiere de la **fecha de emisión** en más de 2 meses, se considera un error de lectura y se usa la fecha de emisión.
3. Si no hay período, se usa la fecha de emisión.
4. Siempre se puede corregir a mano con la columna "Período".

### Análisis
**Menú:** Facturación → Análisis

- Filtros por año, proveedores y empresas.
- Tabla mes a mes (por proveedor o por empresa; en modo empresa convierte ARS→USD).
- Desglose por proveedor y por empresa.
- Gráfico comparativo interanual.
- Vistas guardadas de filtros.

### Proveedores
**Menú:** Facturación → Proveedores *(admin)*

Configuración por proveedor: nombre, identificador (slug), categoría, moneda habitual, empresa/gerencia, **palabras clave de detección** (la IA y el reclasificador las usan para identificar el proveedor), **prompt personalizado** (opcional), activo y multi-factura.

> Consejo: si un proveedor cae siempre en "otro", agregá una palabra clave que lo delate de forma única (por ejemplo, un prefijo constante del número de factura). El reclasificador por keywords busca también en el número de factura.

---

## 10. Plantillas de correo

**Menú:** Administración → Plantillas de correo *(admin)*

### Tipos
| Tipo | Cuándo se envía |
|------|----------------|
| Asignación de activo | Al asignar un equipo |
| Cambio de equipo | Al reemplazar un equipo |
| Devolución de equipo | Al desasignar |
| Alta de empleado | Al activar el ingreso |
| Baja de empleado | Al completar la recepción |
| Reporte de equipos pendientes | Programado (periódico) |
| Envío entregado | Automático al detectar entrega |

Cada plantilla define asunto, cuerpo (con variables `{{ }}`), perfil SMTP, destinatario/CC y frecuencia (para el reporte). Botón "Test" para enviar un correo de prueba con datos genéricos.

**Variables por tipo:**
- Reporte pendientes: `{{ pending_count }}`, `{{ pending_list }}`, `{{ date }}`
- Envío entregado: `{{ person_name }}`, `{{ tracking_number }}`, `{{ carrier }}`, `{{ date }}`
- Asignación / Alta: `{{ person_name }}`, `{{ asset }}`, `{{ date }}`
- Cambio: `{{ person_name }}`, `{{ old_asset }}`, `{{ new_asset }}`, `{{ reason }}`, `{{ date }}`

---

## 11. Administración

*(solo admin)*

- **Usuarios:** alta/edición con rol (admin, it, rrhh).
- **SMTP:** perfiles de servidor de correo (host, puerto, usuario, encriptación, remitente). Botón "Probar perfil".
- **IA:** perfiles de inteligencia artificial para el análisis de facturas. Proveedores: OpenAI (GPT), Google (Gemini), Anthropic (Claude), Groq. Se define modelo, API key y cuál es el predeterminado. Botón "Test".

---

## 12. Utilidades

**Menú:** Utilidades → Importar asignaciones *(admin/it)*

### Importar asignaciones (CSV)
Columnas: `person,device,brand,model,cpu,ram,disk,serial`. Detecta el delimitador (coma, punto y coma, tab). Si el serial existe no duplica el equipo; si la persona no existe, la crea.

### Impresoras y conteo (export/import JSON)
- **Exportar impresoras:** descarga un JSON con todas las impresoras y su historial de lecturas.
- **Importar impresoras:** sube ese JSON; las existentes (por IP o nombre) se actualizan, las nuevas se crean y se agregan las lecturas faltantes. Útil para no perder los datos de impresoras al actualizar producción.

### Proveedores de facturación (export/import JSON)
Exporta/importa la configuración de proveedores.

### Backup de base de datos *(solo admin)*
- **Exportar:** descarga un `.sql` completo.
- **Importar:** sube un `.sql` y restaura (sobrescribe los datos).

---

## 13. Roles y permisos

| Funcionalidad | Admin | IT | RRHH |
|--------------|-------|-----|------|
| Dashboard | ✓ | ✓ | ✓ |
| Ver activos | ✓ | ✓ | ✗ |
| Gestionar personas | ✓ | ✓ | ✗ |
| Asignar/desasignar equipos | ✓ | ✓ | ✗ |
| Solicitar baja | ✓ | ✓ | ✗ |
| Revertir baja | ✓ | ✗ | ✗ |
| Registrar recepción (IT) | ✓ | ✓ | ✗ |
| Confirmar recepción (RRHH) | ✗ | ✗ | ✓ |
| Impresoras y conteo | ✓ | ✓ | ✗ |
| Importar / exportar | ✓ | ✓ | ✗ |
| Facturación | ✓ | ✓ | ✗ |
| Administración (Usuarios, SMTP, IA, Plantillas, Proveedores) | ✓ | ✗ | ✗ |
| Backup / Restore DB | ✓ | ✗ | ✗ |

---

## 14. Notas técnicas y mantenimiento

- **Correos:** se envían con el perfil SMTP de la plantilla; si no tiene, usa el predeterminado.
- **Tracking:** consulta la API pública de EnvíoPack; se actualiza de forma programada y a demanda.
- **IA de facturas:** usa el perfil de IA marcado como predeterminado. Procesa PDF e imágenes; de los PDF toma la primera página.
- **Cola de procesamiento:** las cargas de facturas y reprocesos con IA corren en segundo plano (worker de cola). Tras actualizar el servidor hay que reiniciar el worker.
- **Comandos útiles (consola del servidor):**
  - `php artisan invoices:audit [proveedor]` — audita facturas: período, fecha de emisión y mes asignado; marca las inconsistentes y resume por mes. Solo lectura.
  - `php artisan invoices:sync-periods [--dry-run]` — recalcula el mes/año de todas las facturas según el criterio actual, sin usar IA. Con `--dry-run` solo muestra qué cambiaría.
  - `php artisan invoices:remove-duplicates` — elimina facturas duplicadas.
- **Actualización de producción:** `./update.sh` (trae cambios, corre migraciones, compila y reinicia el worker).
- **Archivos de facturas:** en `storage/app/public/invoices/`, organizados por proveedor/año/mes.

---

*Mini-T — Manual de la plataforma. Actualizado a septiembre 2026.*
