# Mini-T — Contexto del proyecto

## Qué es
Sistema de gestión de activos IT y offboarding de empleados. Laravel 13 + Filament 5 + Livewire. UI en español. DB MySQL (`mini_t`).

## Stack
- PHP 8.3+, Laravel 13, Filament 5
- Spatie Permission (roles: admin, it, rrhh)
- Spatie Activity Log
- Vite + Tailwind
- MySQL en WSL Ubuntu

## Estructura de carpetas Filament
```
app/Filament/Resources/{Entidad}/
├── {Entidad}Resource.php
├── Pages/ (List, Create, Edit, View)
├── Schemas/ (Forms, Infolists)
├── Tables/
└── RelationManagers/
```

## Modelos principales
| Modelo | Propósito |
|--------|-----------|
| Person | Empleados (status: active/offboarding/inactive) |
| Asset | Equipos IT (status: available/assigned/in_transit/retired) |
| Assignment | Vincula persona ↔ activo (SoftDeletes) |
| AssetHistory | Auditoría de movimientos |
| ReturnProcess | Proceso de devolución (doble confirmación RRHH + IT) |
| ReturnShipment | Logística de devolución (EnvíoPack tracking) |
| Invoice | Facturas de servicios telecom/cloud |
| InvoiceProvider | Proveedores de factura con keywords + prompt IA |
| AiProfile | Perfiles de IA multi-proveedor (OpenAI/Google/Anthropic/Groq) |
| MailTemplate | Plantillas de correo con variables {{ }} |
| SmtpProfile | Perfiles SMTP dinámicos |
| User | Usuarios con roles Spatie |

## Flujos de negocio

### Asignación de activos
Persona → asignar equipo → asset pasa a `assigned` → historial

### Desasignar / Reemplazar
- Desasignar: motivo (upgrade/avería/devolución) + email opcional
- Reemplazar: motivo (upgrade/avería/reemplazo preventivo/préstamo/extravío) + email
- Extravío y avería → asset pasa a `retired`

### Offboarding (bajas)
1. "Solicitar baja" → assets a `in_transit`, soft-delete assignments, persona a `offboarding`
2. Widget PendingReturns → logística EnvíoPack o moto
3. RRHH confirma recepción (queda en historial separado)
4. IT "Registrar recepción" → marca devuelto/no devuelto por asset
5. Persona pasa a `inactive`
6. "Revertir baja" (solo admin) → restaura todo

### Facturación
- **Carga**: navegación por carpetas (Proveedor → Año → tabla). Upload múltiple con IA.
- **IA 2 etapas**: 1) identifica proveedor por keywords, 2) extrae datos con prompt específico o genérico
- **Análisis**: filtros por año/proveedor/empresa, tabla mes a mes, desglose por proveedor y empresa
- Campos: provider, company (phinxlab/novatech/cryptopatagonia), project, reference, amount, currency, amount_usd, exchange_rate
- Archivos organizados en storage: `invoices/{proveedor}/{año}/{mes}/`

### Tracking EnvíoPack
- Endpoint público: `GET https://api.enviopack.com/tracking/{tracking}`
- No requiere credenciales
- Códigos: INLD (pendiente), COLE (colectado), RETI (retirado), ENVI (en tránsito), ENTR (entregado)

## Servicios
- `InvoiceParserService` — análisis de facturas con IA (OpenAI/Gemini/Claude/Groq)
- `MailTemplateService` — envío de correos con plantillas + SMTP dinámico
- `TrackingService` — consulta tracking EnvíoPack público
- `ExchangeRateService` — tipo de cambio BNA

## Roles y permisos
| Recurso | admin | it | rrhh |
|---------|-------|-----|------|
| Activos | ✓ | ✓ | ✗ |
| Gente (CRUD) | ✓ | ✓ | ✗ |
| Solicitar baja | ✓ | ✓ | ✗ |
| Revertir baja | ✓ | ✗ | ✗ |
| Confirmar recepción RRHH | ✗ | ✗ | ✓ |
| Registrar recepción IT | ✓ | ✓ | ✗ |
| Importar asignaciones | ✓ | ✓ | ✗ |
| Facturación | ✓ | ✓ | ✗ |
| Administración (Usuarios, SMTP, IA) | ✓ | ✗ | ✗ |

## Comandos útiles
```bash
./update.sh              # Pull + deps + migrate + build + cache
php artisan migrate      # Correr migraciones
php artisan storage:link # Symlink para archivos públicos
npm run build            # Compilar assets
php artisan optimize:clear  # Limpiar toda la cache
```

## Rama activa
`dev` para desarrollo, `main` para producción. Merge de dev → main al finalizar cambios.
