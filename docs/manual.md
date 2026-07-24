# Mini-T — Manual de usuario

## Gestión de activos IT y offboarding

---

## Índice

1. [Acceso al sistema](#1-acceso-al-sistema)
2. [Dashboard](#2-dashboard)
3. [Gestión de personas (Gente)](#3-gestión-de-personas)
4. [Gestión de activos](#4-gestión-de-activos)
5. [Asignación de equipos](#5-asignación-de-equipos)
6. [Proceso de baja (Offboarding)](#6-proceso-de-baja)
7. [Devoluciones y logística](#7-devoluciones-y-logística)
8. [Módulo de facturación](#8-módulo-de-facturación)
9. [Plantillas de correo](#9-plantillas-de-correo)
10. [Administración](#10-administración)
11. [Roles y permisos](#11-roles-y-permisos)

---

## 1. Acceso al sistema

**URL:** `http://tu-servidor/dashboard`

Ingresá con tu email y contraseña. El sistema tiene 3 roles:
- **Admin** — acceso total
- **IT** — gestión de activos, personas y facturación
- **RRHH** — confirmación de recepción de equipos

<!-- 📸 Captura: pantalla de login -->

---

## 2. Dashboard

Al ingresar se muestra el dashboard con:
- **Métricas**: pendientes, en tránsito, entregados, demorados
- **Personas con equipos a recuperar**: lista con días de demora
- **Panel de seguimiento**: detalle del envío seleccionado

### Panel de seguimiento
Al seleccionar una persona, el panel derecho (sticky) muestra:
- Modalidad de envío (EnvíoPack o moto)
- Número de seguimiento
- Timeline de eventos
- Botón "Actualizar" para consultar estado en tiempo real

<!-- 📸 Captura: dashboard completo -->
<!-- 📸 Captura: panel de seguimiento con timeline -->

---

## 3. Gestión de personas

**Menú:** Gente

### Crear persona
1. Clic en "New"
2. Completar: Nombre, Email, Área
3. Seleccionar Servicios (Jira, Teams, Google Workspace, etc.)
4. Guardar

### Ver ficha de persona
La ficha muestra:
- Datos personales
- Equipos asignados (tabla con dispositivo, marca, modelo, serie)
- Historial de activos (acciones previas)

### Toggles de notificación
Al editar una persona, podés activar:
- **Nuevo ingreso** — envía correo de onboarding
- **Asignación de equipo** — envía correo con detalle de equipos

<!-- 📸 Captura: formulario de persona -->
<!-- 📸 Captura: ficha con equipos asignados -->

---

## 4. Gestión de activos

**Menú:** Activos *(solo admin/it)*

### Crear activo
Campos: Dispositivo, Marca, Modelo, Procesador, Memoria, Disco, Nº Serie, Estado, Observaciones.

### Estados de un activo
| Estado | Significado |
|--------|-------------|
| Disponible | Listo para asignar |
| En uso | Asignado a una persona |
| En devolución | En proceso de baja |
| Dado de baja | Retirado del inventario |

### Vista detalle
Muestra todos los datos + historial completo del equipo (quién lo tuvo, por qué se movió, fechas).

<!-- 📸 Captura: tabla de activos -->
<!-- 📸 Captura: detalle de activo con historial -->

---

## 5. Asignación de equipos

### Asignar
1. Entrá a la ficha de una persona
2. En la sección "Assignments", clic en "Asignar equipo"
3. Buscá el equipo disponible
4. Confirmar

El equipo pasa a estado "En uso".

### Desasignar
1. En la fila del equipo, clic en "Desasignar"
2. Seleccionar motivo: Upgrade, Avería, Devolución
3. Opcionalmente agregar observación
4. Activar/desactivar envío de correo
5. Confirmar

### Reemplazar
1. Clic en "Reemplazar"
2. Seleccionar nuevo equipo
3. Motivo: Upgrade, Avería, Reemplazo preventivo, Préstamo, Extravío
4. Observación + correo opcional
5. Confirmar

El equipo viejo se libera (o retira si es avería/extravío) y el nuevo se asigna.

<!-- 📸 Captura: modal de asignación -->
<!-- 📸 Captura: modal de reemplazo -->

---

## 6. Proceso de baja

### Iniciar baja
1. Entrá a la ficha de la persona
2. Clic en "Solicitar baja" (botón rojo)
3. Se muestra un resumen de equipos asignados y servicios
4. Confirmar

**Qué sucede:**
- Los equipos pasan a "En devolución"
- Las asignaciones se eliminan (soft delete)
- La persona pasa a estado "offboarding"
- Se registra la fecha de inicio

### Revertir baja (solo admin)
Si la baja fue un error:
1. Botón gris "Revertir baja" (solo visible para admin)
2. Confirmar
3. Se restauran las asignaciones y los equipos vuelven a "En uso"

### Registrar recepción (IT)
1. Botón verde "Registrar recepción"
2. Para cada equipo, marcar si fue devuelto o no
3. Si no fue devuelto, indicar motivo (ausente, roto, incompleto)
4. Comentario opcional
5. Confirmar

La persona pasa a "inactive" y se envía correo de resumen.

### Confirmación RRHH
1. Desde el dashboard, RRHH ve el botón "Confirmar recepción"
2. Se abre un modal con checkboxes por cada equipo
3. Tildar los recibidos
4. Confirmar

Queda registrado en el historial por separado (IT vs RRHH).

<!-- 📸 Captura: modal de solicitar baja -->
<!-- 📸 Captura: botón revertir baja -->
<!-- 📸 Captura: registrar recepción -->
<!-- 📸 Captura: confirmación RRHH -->

---

## 7. Devoluciones y logística

### Coordinar envío
Desde el dashboard, seleccioná una persona y elegí:

**EnvíoPack:**
1. Ingresá el número de seguimiento (ej: EP013090165R)
2. Comentario opcional
3. "Guardar y coordinar"

**Moto/Mensajería:**
1. Fecha programada de retiro
2. Contacto
3. Comentario
4. Guardar

### Tracking automático
- El sistema consulta `api.enviopack.com/tracking/{numero}` (público, sin credenciales)
- Botón "Actualizar" para consultar manualmente
- Los estados se mapean: Pendiente → Retirado → En tránsito → Entregado
- Cuando marca "Entregado", se dispara un correo automático

### Editar envío
Si el número de seguimiento es incorrecto:
1. Clic en "Editar"
2. Corregir datos
3. Guardar

<!-- 📸 Captura: formulario EnvíoPack -->
<!-- 📸 Captura: timeline de tracking -->

---

## 8. Módulo de facturación

**Menú:** Facturación *(solo admin/it)*

### Carga de facturas

#### Navegación por carpetas
La sección "Carga" muestra:
1. **Nivel 1** — Carpetas por proveedor (Amazon, Google, Telecom, etc.)
2. **Nivel 2** — Carpetas por año
3. **Nivel 3** — Tabla con las facturas (mes, servicio, referencia, monto, moneda, etc.)

#### Cargar con IA
1. Clic en "Cargar con IA" (botón verde)
2. Subir uno o varios archivos (PDF, JPG, PNG)
3. La IA analiza cada archivo y extrae: proveedor, monto, moneda, fecha, período, Nº factura, referencia
4. Se crea automáticamente el registro y se guarda el archivo en `storage/invoices/{proveedor}/{año}/{mes}/`

**Proceso interno (2 etapas):**
1. La IA identifica el proveedor por keywords configuradas
2. Usa un prompt específico (o genérico) para extraer los datos

#### Carga manual
Botón "Carga manual" → formulario completo con todos los campos.

#### Asignar empresa
Cada factura puede asignarse a: Novatech, Phinxlab, o Cryptopatagonia.
- Individual: botón en cada fila
- Masivo: seleccionar varias → "Asignar empresa"

<!-- 📸 Captura: carpetas de proveedores -->
<!-- 📸 Captura: modal de carga con IA -->
<!-- 📸 Captura: tabla de facturas -->

### Análisis

**Menú:** Facturación → Análisis

Muestra:
- Filtros por año y moneda
- Checklist de proveedores (tildar/destildar)
- Checklist de empresas
- Resumen: total año, año anterior, variación %, promedio mensual
- Tabla mes a mes por proveedor (o por empresa, según vista)
- Desglose por proveedor con barras de progreso
- Desglose por empresa

<!-- 📸 Captura: página de análisis -->

### Proveedores de facturación

**Menú:** Facturación → Proveedores

Configurar proveedores para la detección automática por IA:
- Nombre y slug (identificador)
- Categoría (Cloud, Internet, Telefonía, etc.)
- Moneda habitual
- Palabras clave de detección (la IA las usa para identificar)
- Prompt personalizado (opcional, para extracción precisa)

<!-- 📸 Captura: lista de proveedores -->
<!-- 📸 Captura: formulario de proveedor -->

---

## 9. Plantillas de correo

**Menú:** Administración → Plantillas de correo *(solo admin)*

### Tipos disponibles
| Tipo | Cuándo se envía |
|------|----------------|
| Asignación de activo | Al asignar un equipo |
| Cambio de equipo | Al reemplazar un equipo |
| Devolución de equipo | Al desasignar |
| Alta de empleado | Al activar toggle "Nuevo ingreso" |
| Baja de empleado | Al completar recepción |
| Reporte de equipos pendientes | Programado (periódico) |
| Envío entregado | Automático al detectar entrega |

### Configurar plantilla
1. Nombre descriptivo
2. Tipo de plantilla
3. Asunto (puede usar variables)
4. Cuerpo (editor rich text)
5. Variables: botones clickeables que copian la variable al portapapeles
6. Perfil SMTP
7. **Configuración de envío:**
   - Destinatario fijo
   - CC
   - Frecuencia (solo para reporte periódico)

### Variables por tipo
- **Reporte pendientes:** `{{ pending_count }}`, `{{ pending_list }}`, `{{ date }}`
- **Envío entregado:** `{{ person_name }}`, `{{ tracking_number }}`, `{{ carrier }}`, `{{ date }}`
- **Asignación/Alta:** `{{ person_name }}`, `{{ asset }}`, `{{ date }}`
- **Cambio:** `{{ person_name }}`, `{{ old_asset }}`, `{{ new_asset }}`, `{{ reason }}`, `{{ date }}`

### Botón Test
Cada plantilla tiene un botón "Test" que envía un correo real con datos genéricos al destinatario configurado, para verificar que funciona correctamente.

<!-- 📸 Captura: formulario de plantilla -->
<!-- 📸 Captura: botones de variables -->

---

## 10. Administración

### Usuarios
**Menú:** Administración → Usuarios *(solo admin)*

Crear/editar usuarios con:
- Nombre, email, contraseña
- Rol (admin, it, rrhh)
- Permisos específicos

### SMTP
**Menú:** Administración → SMTP *(solo admin)*

Perfiles de servidor de correo:
- Servidor, puerto, usuario, contraseña, encriptación
- Correo y nombre remitente
- Botón "Probar perfil" para verificar conexión

### IA
**Menú:** Administración → IA *(solo admin)*

Perfiles de inteligencia artificial para análisis de facturas:
- Proveedores: OpenAI (GPT), Google (Gemini), Anthropic (Claude), Groq
- Modelo específico por proveedor
- API Key
- Botón "Test" para verificar conexión
- Marcar uno como predeterminado

<!-- 📸 Captura: perfiles de IA -->

---

## 11. Roles y permisos

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
| Importar asignaciones | ✓ | ✓ | ✗ |
| Facturación | ✓ | ✓ | ✗ |
| Administración (Usuarios, SMTP, IA, Plantillas) | ✓ | ✗ | ✗ |
| Backup/Restore DB | ✓ | ✗ | ✗ |

---

## Importar asignaciones masivas

**Menú:** Utilidades → Importar asignaciones *(admin/it)*

### Formato CSV
El archivo debe tener estas columnas (encabezado en la primera fila):

```
person,device,brand,model,cpu,ram,disk,serial
Juan Pérez,Notebook,Dell,Latitude 5520,i5,8GB,256GB SSD,ABC123
María García,Mouse,Logitech,M170,,,,
```

- Si el serial existe, no duplica el equipo
- Si la persona no existe, la crea
- Detecta automáticamente delimitador (coma, punto y coma, tab)

### Backup de Base de Datos (solo admin)
- **Exportar:** descarga archivo .sql completo
- **Importar:** sube un .sql y restaura (sobreescribe datos)

<!-- 📸 Captura: página de importación -->

---

## Notas técnicas

- **Correos:** se envían usando el perfil SMTP configurado en la plantilla. Si no tiene, usa el predeterminado.
- **Tracking:** consulta `api.enviopack.com` (público). El job `UpdateTrackingStatus` corre cada 30 minutos.
- **Reporte periódico:** el comando `mail:pending-assets-report` corre a las 9:00 AM y evalúa la frecuencia configurada.
- **IA de facturas:** usa el perfil marcado como predeterminado. Soporta PDF e imágenes.
- **Archivos:** se guardan en `storage/app/public/invoices/` organizados por proveedor/año/mes.

---

*Mini-T — Gestión de activos IT v1.0*
