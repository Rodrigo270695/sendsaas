# Especificación Técnica y Documento de Arquitectura
## Plataforma SaaS Omnicanal de Atención, Conversaciones y Automatización

**Versión:** 1.2.0  
**Fecha:** 2026-09-07  
**Estado:** Propuesta técnica / SaaS (primeros clientes: 2 empresas)  
**Tipo de documento:** Análisis y diseño de sistema  
**Enfoque:** SaaS Multi-tenant B2B vendible, mismo patrón que VetSaaS  
**Stack propuesto:** Laravel 12 + React + Inertia + Tailwind CSS + PostgreSQL (schemas) + Redis  
**Canal inicial:** OpenWA (sesión WhatsApp Web por tenant)  
**Go-to-market:** se arranca con 2 tenants reales. El producto nace listo para el tenant 3, 4 y N.

---

# 1. Resumen ejecutivo

El presente documento define la arquitectura de **OmniDesk como SaaS vendible**, no como un sistema a medida para dos clínicas.

**Es un producto SaaS desde el commit 1.** Superadmin, planes, suscripciones, límites, provisioner de tenants y aislamiento por schema existen aunque el primer mes solo haya dos clientes. Crear la empresa 3 no puede exigir un rediseño.

**Los 2 primeros tenants son go-to-market, no el techo del producto.** Se usan para validar bandeja, OpenWA y pacing. El código, el panel y el modelo comercial se escriben para N empresas.

**Canal inicial:** OpenWA (sesión WhatsApp Web por tenant, QR), igual que VetSaaS. Cloud API queda detrás de `ChannelProvider` para cuando un cliente o el volumen lo pidan.

**Tenancy:** patrón VetSaaS. Schema `public` (plataforma) + schema dedicado por tenant. `TenantProvisioner` crea fila + schema + migrate + admin + sesión OpenWA + suscripción al plan. No hay lista hardcodeada de empresas.

**Envío:** la cuota (p. ej. 500 msg/día) es un **límite de plan**, no una constante del sistema. El worker drip sirve a todos los tenants. Las respuestas de agente salen ya; campañas y recordatorios se reparte en la jornada.

**Cobro:** el modelo SaaS (planes, suscripción, uso, suspensión) se implementa en el core. Las dos primeras pueden entrar en trial o cobro manual; la pasarela (Culqi/Niubiz/Stripe) se enciende cuando se abra el signup público. No se “agrega billing después”: se activa el cobro automático sobre algo que ya existe.

---

# 2. Nombre conceptual del producto

Nombre temporal:

> **OmniDesk SaaS**

El nombre es provisional. La marca definitiva deberá validarse posteriormente mediante disponibilidad de dominio, redes sociales, registro de marca y análisis comercial.

Descripción comercial:

> **Centraliza, organiza y automatiza las conversaciones de tu negocio.**

Propuesta de valor:

> Una plataforma SaaS que permite a empresas atender WhatsApp desde una bandeja compartida, asignar conversaciones a sus equipos, realizar seguimiento de clientes y automatizar procesos comerciales.

---

# 3. Problema que resuelve

Actualmente muchas pequeñas y medianas empresas gestionan su atención mediante:

- Un teléfono personal.
- WhatsApp instalado en varios dispositivos.
- Hojas de Excel.
- Conversaciones sin seguimiento.
- Información dispersa.
- Falta de responsables.
- Ausencia de historial comercial.
- Recordatorios manuales.
- Envíos promocionales no estructurados.

Esto produce:

- Clientes sin respuesta.
- Conversaciones duplicadas.
- Pérdida de oportunidades.
- Falta de trazabilidad.
- Imposibilidad de medir productividad.
- Dependencia del propietario.
- Dificultad para escalar el equipo de atención.

La plataforma propone centralizar este proceso.

---

# 4. Objetivos del sistema

## 4.1 Objetivo general

Desarrollar una plataforma SaaS multi-tenant vendible (schemas PostgreSQL, superadmin, planes) para gestionar conversaciones empresariales, inicialmente mediante OpenWA, con envío controlado según el límite del plan (default Starter: 500 msg/día).

## 4.2 Objetivos específicos

- Registrar empresas como tenants.
- Gestionar usuarios y roles.
- Integrar OpenWA (sesión por tenant, QR, webhooks).
- Recibir mensajes mediante webhooks de OpenWA.
- Enviar mensajes con cuota diaria y pacing, no en ráfaga.
- Mantener historial completo de conversaciones.
- Permitir atención simultánea por varios agentes.
- Asignar conversaciones.
- Administrar contactos.
- Clasificar contactos mediante etiquetas.
- Crear recordatorios.
- Crear respuestas rápidas.
- Crear campañas.
- Implementar automatizaciones.
- Generar indicadores de atención.
- Controlar límites según plan.
- Preparar arquitectura para IA y nuevos canales.

---

# 5. Alcance del MVP

## 5.1 Incluido

### Administración

- Registro.
- Login.
- Recuperación de contraseña.
- Perfil.
- Empresa.
- Usuarios.
- Roles.
- Permisos.
- Suscripción.

### WhatsApp (OpenWA)

- Crear sesión OpenWA nombrada con el slug del tenant.
- Mostrar QR y vincular el teléfono.
- Reconectar / detener sesión (igual patrón VetSaaS).
- Registrar webhook de entrada por sesión.
- Recepción de mensajes.
- Envío de texto (imagen/documento después).
- Estados de sesión (`created`, `qr_ready`, `ready`, `disconnected`).
- Historial en el schema del tenant.
- Cuota diaria del plan y cola drip.

### Conversaciones

- Bandeja de entrada.
- Conversación individual.
- Búsqueda.
- Filtros.
- Estados.
- Asignación.
- Transferencia.
- Notas internas.
- Etiquetas.

### Contactos

- Nombre.
- Teléfono.
- Email.
- Etiquetas.
- Notas.
- Último contacto.
- Historial.

### Operación

- Respuestas rápidas.
- Recordatorios.
- Notificaciones.
- Dashboard básico.

---

# 6. Fuera del MVP inicial

No se recomienda implementar inicialmente:

- WhatsApp Cloud API oficial (queda detrás de `ChannelProvider`).
- IA generativa avanzada.
- Chatbot visual complejo.
- Instagram, Messenger, Telegram, email.
- Campañas masivas sin cola drip.
- CRM financiero / facturación electrónica.
- App móvil nativa.
- Pasarela de pago en el primer sprint (el modelo de planes/suscripción sí entra).
- Marketplace e integraciones masivas.

Fuera del MVP = no retrasa el primer ingreso. No significa “esto no es un SaaS”. El núcleo (tenant N, plan, límite, suspender, impersonar) sí entra.

---

# 7. Actores del sistema

## 7.1 Super Administrador

Responsable de la plataforma SaaS. Opera en el dominio central (sin schema de clínica), igual que el panel `plataforma` de VetSaaS.

Puede:

- Crear / suspender / reactivar / impersonar cualquier tenant (no solo los 2 primeros).
- Administrar planes, precios y límites (`max_outbound_per_day`, agentes, números).
- Ver y asignar suscripciones (trial, active, past_due, cancelled).
- Ver estado de cada sesión OpenWA (QR, ready, error, 429).
- Ver consumo diario por empresa (X / límite del plan).
- Forzar stop / restart / re-QR de una sesión.
- Consultar errores de integración y cola drip.
- Métricas de plataforma: tenants activos, MRR, cuota usada, sesiones caídas.

## 7.2 Administrador del tenant

Administrador de una empresa cliente.

Puede:

- Gestionar usuarios.
- Configurar WhatsApp.
- Administrar etiquetas.
- Administrar respuestas rápidas.
- Crear campañas.
- Configurar automatizaciones.
- Consultar reportes.

## 7.3 Supervisor

Puede:

- Ver conversaciones del equipo.
- Asignar conversaciones.
- Transferir conversaciones.
- Consultar métricas.
- Supervisar agentes.

## 7.4 Agente

Puede:

- Ver conversaciones asignadas.
- Responder.
- Agregar notas.
- Cambiar estado.
- Crear recordatorios.
- Consultar contactos.

## 7.5 Cliente final

Persona que conversa con la empresa mediante WhatsApp.

---

# 8. Arquitectura general

La arquitectura es un **monolito modular** con el mismo corte que VetSaaS: panel de plataforma + app de tenant por subdominio + gateway OpenWA aparte.

No se recomienda comenzar con microservicios.

```text
                    INTERNET
                       |
          +------------+------------+
          |                         |
          v                         v
  plataforma.dominio          empresa.dominio
  (superadmin, public)        (tenant, schema propio)
          |                         |
          +------------+------------+
                       |
                       v
                 Laravel 12
                 TenantManager
                 (SET search_path)
                       |
       +---------------+---------------+
       |               |               |
       v               v               v
  PostgreSQL         Redis          OpenWA
  public +           colas          gateway
  od_empresa_a       drip           (sesiones)
  od_empresa_b       cache
                       |
                       v
                 WhatsApp Web
                 (teléfono de cada empresa)
```

OpenWA corre como servicio aparte (API + admin). Laravel no habla con WhatsApp directo: solo con `OPENWA_API_URL` + `X-API-Key`.

---

# 9. Arquitectura lógica

La aplicación deberá dividirse en módulos de dominio.

```text
app/
├── Domain/
│   ├── Tenant/
│   ├── User/
│   ├── Contact/
│   ├── Conversation/
│   ├── Message/
│   ├── WhatsApp/
│   ├── Campaign/
│   ├── Automation/
│   ├── Reminder/
│   ├── Subscription/
│   └── Reporting/
│
├── Application/
│   ├── Actions/
│   ├── Services/
│   └── DTO/
│
├── Infrastructure/
│   ├── WhatsApp/
│   ├── Persistence/
│   ├── Queue/
│   └── Notifications/
│
└── Http/
    ├── Controllers/
    ├── Requests/
    └── Resources/
```

La estructura exacta podrá adaptarse al crecimiento del proyecto.

---

# 10. Multi-tenancy (patrón VetSaaS: schemas PostgreSQL)

El aislamiento **no** es `WHERE tenant_id = ?` en una sola base compartida. Es el mismo mecanismo de VetSaaS:

1. Resolver el tenant por slug del subdominio (nunca por `schema_name` que mande el cliente).
2. Validar estado (`active`, `trial`; bloquear `suspended`).
3. Aplicar `SET search_path TO "<schema>", public` en la conexión Postgres.
4. A partir de ahí, `contacts`, `conversations`, `messages` viven solo en ese schema.

## 10.1 Schema `public` (plataforma)

Tablas que ve el superadmin. No cambian de schema:

```text
tenants
users                    (plataforma + FK tenant_id si es usuario de clínica)
plans
subscriptions
tenant_whatsapp_sessions (openwa_session_id, status, phone, last_error)
platform_settings
audit_logs_platform
```

`tenants` (mínimo):

```text
id (uuid)
slug                 -- subdominio: empresa-a
schema_name          -- od_empresa_a
razon_social
email_admin
estado
timezone             -- America/Lima
locale               -- es_PE
```

Nunca aceptar `schema_name` desde la request. Se lee de `tenants`. Antes de inyectarlo en `SET search_path` se sanea (`/^[a-z_][a-z0-9_]{0,62}$/`).

## 10.2 Schema por tenant

Al provisionar:

```text
CREATE SCHEMA IF NOT EXISTS "od_empresa_a";
-- migraciones de database/migrations/tenant
SET search_path TO "od_empresa_a", public;
```

Prefijo recomendado: `od_` (como `vet_` en VetSaaS). El log de migraciones tenant vive **dentro** del schema, no en `public.migrations`.

Tablas de negocio (sin `tenant_id`; el schema ya aísla):

```text
users_tenant / membership via users.tenant_id en public
contacts
conversations
conversation_messages
conversation_tags
tags
quick_replies
reminders
outbound_queue          -- drip 500/día
automations
campaigns
campaign_recipients
audit_logs
```

## 10.3 Resolución de request (igual VetSaaS)

```text
empresa-a.omnidesk.test
        |
        v
ResolveTenant (slug = empresa-a)
        |
        v
TenantManager::resolveBySlug()
        |
        v
SET search_path TO "od_empresa_a", public
        |
        v
Bandeja, contactos, envíos
```

Dominio central (`app`, `plataforma`, sin subdominio de clínica):

```text
search_path = public
Superadmin ve tenants, sesiones OpenWA, cuotas, errores
```

Jobs y comandos: `TenantManager::resolveById($id)` al entrar, `forget()` al salir (vuelve `search_path` a `public`).

## 10.4 Primeros clientes y el tenant N

Los dos primeros se crean desde el superadmin (o `omnidesk:tenant-provision`). El camino es el mismo que usará el signup público:

```text
TenantProvisioner(payload)
  |-- public.tenants
  |-- CREATE SCHEMA od_{slug}
  |-- migrate tenant
  |-- usuario admin
  |-- subscription + plan (starter / profesional / …)
  |-- ensure OpenWA session(slug)
```

```text
empresa-a  →  od_empresa_a  →  sesión OpenWA "empresa-a"  →  plan X
empresa-b  →  od_empresa_b  →  sesión OpenWA "empresa-b"  →  plan X
empresa-n  →  od_empresa_n  →  sesión OpenWA "empresa-n"  →  plan Y
```

Nada de `if (slug === 'empresa-a')`. Cuota, ventana y agentes se leen del plan / override del tenant.

El signup público (registro → plan → pagar → provisionar) se puede abrir cuando haya demanda. El provisioner y el panel ya están. No se reescribe el producto para “empezar a vender”.

## 10.5 Regla crítica

Una query de bandeja **nunca** filtra por `tenant_id` “por si acaso”. Si el `search_path` está mal, es un bug de bootstrap, no se parchea en el modelo. Los modelos de plataforma (`Tenant`, `TenantWhatsAppSession`) usan schema `public` explícito (`UsesPublicSchema`).

---

# 11. Flujo de onboarding (SaaS)

Hay un solo provisioner y dos puertas de entrada. Hoy entra el superadmin; mañana el cliente se registra solo. El resto del flujo no cambia.

```text
Puerta A (ahora)              Puerta B (venta pública)
Superadmin crea tenant        Registro → plan → pago / trial
        \                            /
         v                          v
              TenantProvisioner
         (schema + admin + plan + sesión OpenWA)
                        |
                        v
           Admin entra al subdominio
                        |
                        v
              Conectar WhatsApp (QR)
                        |
                        v
              Crear agentes → primera conversación
```

Estados de suscripción desde el día 1: `trial`, `active`, `past_due`, `suspended`, `cancelled`. Un tenant suspendido no resuelve `search_path` operativo (igual VetSaaS).

---

# 12. Flujo de recepción (OpenWA)

```text
Cliente WhatsApp
   |
   v
Teléfono / WhatsApp Web de la empresa
   |
   v
OpenWA (sesión = slug del tenant)
   |
   | POST webhook + secret
   v
Laravel  /webhooks/openwa/{tenant}
   |
   +--> Validar X-Webhook-Secret
   +--> Resolver tenant por slug o session_id
   +--> SET search_path al schema
   +--> Identificar contacto (phone / chatId)
   +--> Crear/actualizar conversación
   +--> Registrar mensaje (idempotente por external_id)
   +--> Notificar agentes (WebSocket)
   |
   v
Bandeja React (sin refresh)
```

Los webhooks de OpenWA se reintentan. `external_id` único por conversación/schema.

---

# 13. Flujo de respuesta de agente

La respuesta humana **no espera** el drip de campañas. Sí descuenta la cuota del plan. Si se agotó, se bloquea el envío (no el inbox).

```text
Agente
  |
  v
Bandeja
  |
  v
Laravel (schema del tenant)
  |
  +--> Permisos, conversación, sesión ready
  +--> ¿Queda cuota hoy?  (X < plan.max_outbound_per_day)
  +--> ¿OpenWA en cooldown 429?  → reencolar corto
  +--> OpenWaClient::sendText(sessionId, chatId, text)
  +--> Persistir mensaje + descontar cuota
  |
  v
WhatsApp del cliente
```

Estados del mensaje:

```text
pending
sent
delivered
read
failed
```

OpenWA a veces responde 5xx/timeout cuando el mensaje ya salió. Reutilizar el fallback de VetSaaS: timeout sin bytes = no se asume envío; 5xx tardío = se asume entregado y no se reintenta (evita duplicados).

---

# 13A. OpenWA — cómo se conecta y cómo se opera

Referencia de implementación existente: `vetsaas/app/Services/OpenWa/*` y `config/openwa.php`.

## Sesión

Una sesión OpenWA por tenant. Nombre = `slug`. Metadata en `public.tenant_whatsapp_sessions` (no en el schema de la clínica), para que el superadmin la vea sin cambiar `search_path`.

```text
tenant_whatsapp_sessions
  tenant_id
  openwa_session_id
  openwa_session_name     -- slug
  status                  -- created | qr_ready | authenticating | ready | disconnected | failed
  phone
  push_name
  auto_reconnect
  last_error
  last_synced_at
  connected_at
```

## Cliente HTTP

```text
OPENWA_ENABLED=true
OPENWA_API_URL=https://wa.tudominio
OPENWA_API_KEY=...
OPENWA_ADMIN_URL=https://wa-admin.tudominio
```

Operaciones mínimas (ya existen en VetSaaS):

```text
GET  /api/sessions
POST /api/sessions                      { name, config.autoReconnect }
GET  /api/sessions/{id}
POST /api/sessions/{id}/start
GET  /api/sessions/{id}/qr
POST /api/sessions/{id}/stop
POST /api/sessions/{id}/messages/send-text
POST /api/sessions/{id}/webhooks
```

Header: `X-API-Key`. Si OpenWA responde **429**, marcar cooldown en cache (~240 s) y no martillar la API.

Con 2 sesiones no hace falta rotar lotes grandes. Cuando haya más tenants, copiar de VetSaaS: `sync_max_tenants_per_run`, cache de `listSessions`, pausa entre starts.

## Capa de canal

Laravel habla con `OpenWaClient`, no con WhatsApp. El día que se pase a Cloud API se implementa otro provider:

```text
ChannelProviderInterface
  connect / qr / disconnect
  sendText / sendImage / sendDocument
  registerWebhook
```

`WhatsAppOpenWaProvider` es el único provider al vender el MVP. Cloud API se suma después sin tocar la bandeja.

---

# 13B. Pacing — cuota del plan, repartida en el día

Objetivo: no quemar el número. OpenWA usa WhatsApp Web; una ráfaga de 500 se parece a spam.

## Números por plan (default Starter)

```text
Cuota diaria:                plan.max_outbound_per_day  (default 500)
Ventana:                     plan.send_window_start/end (default 08:00–20:00)
Duración típica:             12 h = 43 200 s
Intervalo drip:              ventana_s / cuota_restante  (~86 s si 500)
Jitter:                      ±20 %
Tope por minuto:             1
Tope por hora:               ceil(cuota / horas_ventana)
Reset:                       00:00 timezone del tenant
```

El worker no asume “2 empresas” ni “500”. Lee plan + override + timezone. 10 tenants = el mismo job recorriendo suscripciones activas.

Además: **candado global OpenWA** (un `sendText` a la vez, ~2–3 s entre envíos) para no tumbar el gateway con 429 cuando haya N sesiones.

## Qué se dripea y qué no

| Tipo | ¿Drip? | ¿Cuenta en la cuota del plan? |
|---|---|---|
| Respuesta de agente | No. Sale ya. | Sí |
| Nota / interno | No sale a WhatsApp | No |
| Recordatorio | Sí | Sí |
| Campaña / difusión | Sí | Sí |
| Automatización (regla) | Sí | Sí |

Si la cuota del día se acabó:

- El agente ve aviso: “Límite diario alcanzado. Se reanuda mañana a las 08:00.”
- La cola drip se pausa hasta el próximo reset + ventana.
- Nunca se “alcanza” mandando el resto a las 23:50.

## Cola `outbound_queue` (schema del tenant)

```text
id
conversation_id          nullable
contact_phone
chat_id
body
message_type             text | image | document
priority                 interactive | drip
status                   queued | reserved | sent | failed | cancelled
available_at
sent_at
attempts
last_error
created_at
```

Worker (scheduler cada 15–30 s, o job encadenado):

```text
1. ¿Estamos en ventana 08:00–20:00?
2. ¿Queda cuota tenant hoy?
3. ¿OpenWA ready y sin cooldown 429?
4. ¿Pasó el intervalo (último envío drip + 86s ± jitter)?
5. Reservar 1 fila queued (available_at <= now)
6. SET search_path → sendText → marcar sent / failed
7. Incrementar contador diario en Redis:
     openwa:quota:{tenant_id}:{Y-m-d}
```

Campañas no llaman a OpenWA en el `store`. Insertan N filas `queued` y el worker las suelta a lo largo del día. 500 destinos = ~12 horas, no 5 minutos.

## Superadmin

Por tenant debe ver:

```text
Sesión: ready | qr | disconnected
Hoy:    187 / 500
Ventana: 08:00–20:00
Cola drip: 312 pendientes
Último envío: hace 1 min
Cooldown 429: no
```

Cuando crezcan: la cuota pasa a ser dato de plan (`max_outbound_per_day`), no constante. El worker no cambia.

---

# 14. Modelo de conversación

Una conversación representa el contexto de interacción entre un contacto y una cuenta/canal de la empresa.

Estados:

```text
NEW
OPEN
PENDING
RESOLVED
CLOSED
```

Datos principales (viven en el schema del tenant; no llevan `tenant_id`):

```text
id
contact_id
channel          -- whatsapp
assigned_user_id
status
priority
last_message_at
created_at
updated_at
```

---

# 15. Modelo de mensaje

Cada mensaje debe conservar trazabilidad.

Campos conceptuales:

```text
id
conversation_id
sender_type
sender_id
external_id
direction
message_type
body
media_url
status
sent_at
delivered_at
read_at
failed_at
metadata
created_at
updated_at
```

`external_id` será importante para relacionar el mensaje interno con el identificador proporcionado por el proveedor.

---

# 16. Tipos de mensajes

El diseño deberá permitir:

```text
text
image
video
audio
document
location
template
interactive
system
```

El MVP puede iniciar únicamente con:

```text
text
image
document
```

(Las plantillas oficiales son de Cloud API; en OpenWA se envía texto normal.)

pero la base de datos no debería impedir incorporar los demás tipos.

---

# 17. Contactos

Modelo conceptual:

```text
contacts

id
name
phone
email
avatar
source
status
last_contact_at
metadata
created_at
updated_at
```

`phone` único **dentro del schema**. El aislamiento lo da PostgreSQL, no `tenant_id`.

---

# 18. Asignación de conversaciones

Una conversación puede asignarse a:

- Agente.
- Supervisor.
- Equipo.

Flujo:

```text
Nueva conversación
       |
       v
Bandeja general
       |
       +----> Asignación manual
       |
       +----> Asignación automática
                    |
                    v
              Agente disponible
```

La asignación automática puede implementarse posteriormente mediante:

- Round-robin.
- Menor cantidad de conversaciones.
- Equipo.
- Horario.
- Etiqueta.
- Regla de negocio.

---

# 19. Etiquetas

Las etiquetas permitirán segmentar contactos y conversaciones.

Ejemplos:

```text
CLIENTE_NUEVO
CLIENTE_FRECUENTE
INTERESADO
COTIZACION
PENDIENTE_PAGO
VIP
DELIVERY
```

Modelo:

```text
tags
contact_tags
conversation_tags
```

---

# 20. Respuestas rápidas

Permitirán reutilizar mensajes frecuentes.

Ejemplo:

```text
Código:
SALUDO

Mensaje:
Hola {{nombre}} 👋
Gracias por comunicarte con nosotros.
¿En qué podemos ayudarte?
```

El motor deberá soportar variables.

Ejemplos:

```text
{{nombre}}
{{empresa}}
{{agente}}
{{fecha}}
```

---

# 21. Recordatorios

Entidad:

```text
reminders

id
user_id
contact_id
conversation_id
title
description
scheduled_at
status
completed_at
```

Estados:

```text
PENDING
COMPLETED
CANCELLED
EXPIRED
```

Proceso:

```text
Agente
  |
  v
Crear recordatorio
  |
  v
Redis / Scheduler
  |
  v
Job
  |
  v
Notificación
```

---

# 22. Campañas

Las campañas deben implementarse con especial cuidado debido a las políticas del proveedor del canal.

Modelo:

```text
campaigns
campaign_recipients
campaign_messages
```

Estados:

```text
DRAFT
SCHEDULED
PROCESSING
RUNNING
COMPLETED
CANCELLED
FAILED
```

Flujo:

```text
Administrador
    |
    v
Crear campaña
    |
    v
Seleccionar segmento
    |
    v
Seleccionar plantilla
    |
    v
Programar
    |
    v
Queue
    |
    v
Envío controlado
    |
    v
Actualizar resultados
```

No se diseña para spam. Toda campaña entra a `outbound_queue` y el worker la suelta según intervalo (ventana / cuota restante). En Starter (~500/día) un blast de 500 tarda el día laboral, no minutos.

Consentimiento, opt-out y no reenviar a quien pidió baja son obligatorios. Un número baneado tumba la atención real de esa empresa.

---

# 23. Automatizaciones

La primera versión puede utilizar reglas simples:

```text
WHEN evento
IF condición
THEN acción
```

Ejemplo:

```text
WHEN
  llega mensaje

IF
  mensaje contiene "precio"

THEN
  enviar respuesta rápida "PRECIOS"
```

Otro:

```text
WHEN
  conversación queda pendiente

IF
  pasan 24 horas

THEN
  crear recordatorio
```

Arquitectura conceptual:

```text
Event
  |
  v
Rule Engine
  |
  +--> Conditions
  |
  +--> Actions
```

---

# 24. Dashboard

El dashboard inicial debe mostrar:

```text
Conversaciones nuevas
Conversaciones abiertas
Conversaciones pendientes
Conversaciones resueltas

Mensajes enviados
Mensajes recibidos

Tiempo promedio de primera respuesta
Tiempo promedio de resolución

Conversaciones por agente
```

Ejemplo:

```text
┌───────────────────────────────────────┐
│ Dashboard                             │
├────────────┬────────────┬─────────────┤
│ Nuevas     │ Abiertas   │ Resueltas   │
│    25      │    84      │    120      │
├────────────┴────────────┴─────────────┤
│ Conversaciones por agente             │
│                                       │
│ Carlos       ████████████  45         │
│ María        █████████     39         │
│ Pedro        ██████        22         │
└───────────────────────────────────────┘
```

---

# 25. Notificaciones en tiempo real

Para una experiencia tipo chat, el sistema deberá utilizar WebSockets.

Flujo:

```text
WhatsApp
   |
   v
Laravel
   |
   v
Database
   |
   v
Event
   |
   v
WebSocket
   |
   v
React
```

El agente no debería tener que refrescar el navegador para recibir mensajes.

---

# 26. Colas y procesamiento asíncrono

No todo debe ejecutarse dentro de la petición HTTP.

Utilizar:

```text
Redis
Laravel Queue
Laravel Scheduler
```

Jobs sugeridos:

```text
ProcessIncomingOpenWaWebhook
SendInteractiveWhatsAppMessage   -- respuesta de agente
ProcessOutboundDripQueue         -- 1 msg, respeta intervalo / cuota del plan / ventana
SyncOpenWaSession                -- QR, ready, reconnect
SendReminderNotification
ProcessAutomation
UpdateMessageStatus
```

Esto permitirá escalar el procesamiento.

---

# 27. Idempotencia

Los webhooks de OpenWA pueden reintentarse.

Por lo tanto:

```text
external_message_id
```

debe tener una estrategia de unicidad.

Antes de crear un mensaje:

```text
¿Ya existe external_id?
       |
   +---+---+
   |       |
  Sí      No
   |       |
Ignorar   Crear
```

Esto evita mensajes duplicados.

---

# 28. Seguridad

La seguridad será transversal.

## Requisitos

- HTTPS obligatorio.
- Hash seguro de contraseñas.
- Tokens protegidos.
- Validación de webhooks.
- Control de autorización.
- Rate limiting.
- Protección CSRF cuando corresponda.
- Validación de inputs.
- Sanitización.
- Logs de auditoría.
- Separación por schema (`search_path`); modelos de plataforma en `public`.
- Nunca resolver schema desde input del usuario.
- Protección contra acceso cruzado (subdominio ≠ tenant del user).
- Secret de webhook OpenWA por sesión.
- Gestión segura de secretos.

Nunca almacenar:

- Tokens en código fuente.
- Credenciales en Git.
- Secretos dentro del frontend.

Utilizar variables de entorno y/o secret manager.

---

# 29. Auditoría

Se recomienda registrar operaciones críticas:

```text
USER_LOGIN
USER_CREATED
USER_UPDATED
CONVERSATION_ASSIGNED
CONVERSATION_TRANSFERRED
MESSAGE_SENT
CAMPAIGN_CREATED
CAMPAIGN_STARTED
WHATSAPP_CONNECTED
WHATSAPP_DISCONNECTED
SUBSCRIPTION_CHANGED
```

Modelo:

```text
audit_logs          -- schema del tenant

id
user_id
action
entity_type
entity_id
old_values
new_values
ip_address
user_agent
created_at
```

Eventos de plataforma (crear tenant, restart sesión) van a `public`, no aquí.

---

# 30. Suscripciones SaaS

Esto es parte del core, no un extra “para cuando se venda”. Igual que VetSaaS: sin suscripción no hay tenant operable.

```text
public.plans
public.subscriptions
public.subscription_payments
public.usage_records
public.tenant_plan_overrides
```

Cada tenant nace con una `subscription` (trial o pagada). Los límites se leen de ahí:

```text
max_users
max_whatsapp_sessions     -- 1 en Starter
max_outbound_per_day      -- 500 Starter; más en planes altos
send_window_start / end
max_contacts
max_campaigns
```

**Cobro ahora vs después**

- Ahora: superadmin asigna plan; las 2 primeras pueden ser trial o pago por transferencia. Queda `subscription` + ciclo.
- Venta: se enciende pasarela y signup. Mismos planes, mismo provisioner, mismo corte por `past_due`.

No se hardcodea la cuota 500 en el worker. Se hardcodea el default del plan Starter.

---

# 31. Planes comerciales iniciales

## Starter

**S/ 49 mensuales**

- 1 número.
- 2 agentes.
- Bandeja compartida.
- Contactos.
- Etiquetas.
- Respuestas rápidas.
- Historial.

## Profesional

**S/ 99 mensuales**

- 1 número.
- 5 agentes.
- Todo Starter.
- Recordatorios.
- Automatizaciones básicas.
- Campañas.
- Dashboard.
- Métricas.

## Business

**S/ 199 mensuales**

- Hasta 2 números.
- 15 agentes.
- Automatizaciones avanzadas.
- Segmentación.
- Reportes.
- API.
- Roles avanzados.

## Enterprise

**Desde S/ 399 mensuales**

- Números adicionales.
- Usuarios adicionales.
- Integraciones.
- API avanzada.
- Soporte prioritario.
- Acuerdos comerciales personalizados.

Los precios deben validarse mediante pruebas comerciales antes del lanzamiento definitivo.

---

# 32. Modelo de monetización

El modelo principal será:

```text
Suscripción mensual
```

Ingresos adicionales posibles:

```text
Usuarios adicionales
Números adicionales
Consumo de mensajería
Automatizaciones premium
IA
Integraciones
Soporte premium
Implementación
```

No se recomienda esconder costos variables importantes dentro de planes ilimitados.

---

# 33. Economía unitaria

La métrica fundamental será:

```text
MRR
```

Monthly Recurring Revenue.

Ejemplo:

```text
100 clientes
x
S/ 99 promedio
=
S/ 9,900 MRR
```

También deben controlarse:

```text
CAC
LTV
Churn
ARPU
Gross Margin
Activation Rate
Retention
```

Objetivo inicial:

> Validar que el costo mensual de servir a un cliente sea suficientemente menor que el ingreso recurrente generado.

---

# 34. Arquitectura de base de datos

Dos capas, como VetSaaS.

## public (plataforma)

```text
tenants
users
plans
subscriptions
tenant_whatsapp_sessions
platform_settings
```

## schema del tenant (`od_{slug}`)

```text
contacts
  +---- contact_tags
conversations
  +---- conversation_messages
  +---- conversation_tags
outbound_queue
reminders
campaigns
  +---- campaign_recipients
automations
quick_replies
audit_logs
```

`users` de clínica pueden vivir en `public` con `tenant_id` (login único + `tenant.match-user` por host), igual que VetSaaS. Los datos de conversación nunca cruzan de schema.

---

# 35. Índices recomendados

Para tablas de alto crecimiento:

```text
conversations:
INDEX(status)
INDEX(assigned_user_id)
INDEX(last_message_at)

messages:
INDEX(conversation_id, created_at)
UNIQUE(external_id)

contacts:
UNIQUE(phone)

outbound_queue:
INDEX(status, available_at, priority)

audit_logs:
INDEX(created_at)

campaign_recipients:
INDEX(campaign_id, status)
```

Estos índices deberán revisarse mediante métricas reales de producción.

---

# 36. API interna

Convención:

```text
/api/v1/
```

Ejemplos:

```text
POST   /auth/login
POST   /auth/logout

GET    /conversations
GET    /conversations/{id}
POST   /conversations/{id}/messages
POST   /conversations/{id}/assign
POST   /conversations/{id}/resolve

GET    /contacts
GET    /contacts/{id}

GET    /whatsapp/accounts
POST   /whatsapp/connect

GET    /campaigns
POST   /campaigns
POST   /campaigns/{id}/launch

GET    /reminders
POST   /reminders

GET    /dashboard
```

Webhooks:

```text
POST /webhooks/whatsapp
```

Los endpoints públicos de webhook deberán tener controles específicos de autenticidad e idempotencia.

---

# 37. Frontend

Tecnología:

```text
React
Inertia
Tailwind CSS
TypeScript recomendado
```

Pantallas principales:

```text
/login

/register

/onboarding

/dashboard

/inbox
/inbox/{conversation}

/contacts
/contacts/{contact}

/campaigns
/campaigns/create

/automations

/reminders

/team

/settings

/settings/whatsapp

/settings/subscription
```

---

# 38. Diseño de la bandeja

La pantalla más importante del producto será:

```text
┌─────────────────────────────────────────────────────────┐
│ Logo       Buscar...                     Usuario        │
├──────────────┬──────────────────────────┬───────────────┤
│ Conversac.   │ Juan Pérez               │ Información   │
│              │                          │               │
│ 🔴 Juan      │ Hola, necesito precio    │ Juan Pérez    │
│ 🟡 María     │                          │ +51...        │
│ 🟢 Carlos    │ Tú: Claro Juan...        │               │
│              │                          │ Tags          │
│              │                          │ [Interesado]  │
│              │                          │               │
│              │                          │ Responsable   │
│              │                          │ Carlos        │
├──────────────┴──────────────────────────┴───────────────┤
│ Escribe un mensaje...                         [Enviar] │
└─────────────────────────────────────────────────────────┘
```

La UX de esta pantalla debe ser prioritaria frente a módulos secundarios.

---

# 39. Reglas de negocio críticas

## RB-001

Un usuario solamente podrá acceder a información perteneciente a su tenant.

## RB-002

Un agente solamente podrá realizar las acciones autorizadas por su rol.

## RB-003

Un mensaje entrante no podrá registrarse dos veces.

## RB-004

Una campaña solamente podrá ejecutarse si cumple las condiciones del plan y del proveedor.

## RB-005

Un tenant suspendido no podrá realizar operaciones restringidas.

## RB-006

Los límites del plan deberán validarse en backend.

## RB-007

El frontend nunca será responsable de imponer reglas de seguridad.

---

# 40. Observabilidad

El sistema deberá tener:

```text
Application Logs
Queue Logs
Webhook Logs
Error Tracking
Audit Logs
Metrics
Health Checks
```

Endpoints recomendados:

```text
/health
/ready
```

Métricas:

```text
webhook_success
webhook_failure
messages_sent
messages_failed
queue_size
queue_failed_jobs
response_time
database_latency
```

---

# 41. Backup

Política inicial recomendada:

```text
Database:
Backup diario

Retención:
7 - 30 días

Storage:
Backup periódico

Configuración:
versionada
```

Antes de producción deberá probarse la restauración.

Un backup que nunca se ha restaurado no debe considerarse una estrategia de recuperación validada.

---

# 42. Escalabilidad

La primera etapa puede operar con:

```text
1 aplicación Laravel
1 worker
1 Redis
1 base de datos
1 storage
```

Al crecer:

```text
                    Load Balancer
                         |
              +----------+----------+
              |                     |
          Laravel #1            Laravel #2
              |                     |
              +----------+----------+
                         |
                       Redis
                         |
                  Queue Workers
                   /    |     \
                  /     |      \
              Worker Worker Worker
                         |
                      Database
```

No se debe introducir complejidad de infraestructura antes de necesitarla.

---

# 43. Roadmap

## Fase 0 — Descubrimiento

Duración estimada:

**1 semana**

Entregables:

- Requisitos.
- User stories.
- Wireframes.
- Arquitectura.
- Modelo de datos.
- Definición comercial.

---

## Fase 1 — Core SaaS + OpenWA

Duración:

**4–6 semanas**

Implementar:

- Tenancy VetSaaS: `public` + schema, `TenantManager`, `TenantProvisioner` (tenant N).
- Planes, suscripciones, límites, estados (trial/active/suspended).
- Panel superadmin: tenants, planes, sesión OpenWA, uso, impersonar.
- Auth, usuarios, roles en subdominio.
- OpenWA: sesión, QR, webhook, sendText.
- Conversaciones, mensajes, contactos, asignación.
- `outbound_queue` + worker drip según límite del plan.
- WebSockets.

Las 2 primeras empresas se dan de alta con ese mismo flujo. No hay un “modo piloto” en el código.

---

## Fase 2 — Operación

Duración:

**2–3 semanas**

Implementar:

- Etiquetas.
- Respuestas rápidas.
- Recordatorios.
- Dashboard.
- Auditoría.
- Notificaciones.

---

## Fase 3 — Cobro automático (venta pública)

Duración:

**2–3 semanas**

El modelo de planes ya existe (Fase 1). Aquí se enciende la venta self-serve:

- Signup público.
- Pasarela (Culqi / Niubiz / Stripe).
- Renovación, past_due, win-back.
- Factura / boleta de suscripción si aplica.
- Portal de la empresa: ver plan, uso, upgrade.

---

## Fase 4 — Automatización

Implementar:

- Reglas.
- Triggers.
- Condiciones.
- Acciones.
- Campañas.
- Segmentación.

---

## Fase 5 — Inteligencia

Implementar posteriormente:

- IA.
- Respuestas sugeridas.
- Resumen automático.
- Clasificación de conversaciones.
- Detección de intención.
- Chatbot.
- Predicción de abandono.
- Scoring de leads.

---

# 44. MVP comercial recomendado

El producto no debe esperar a tener todas las funcionalidades.

El primer producto que se puede vender (aunque empiece con 2 clientes) es:

```text
SaaS real
  superadmin + plans + subscriptions + provisioner N
   +
OpenWA (QR por tenant)
   +
Bandeja + agentes + contactos + historial
   +
Cuota/día del plan, drip
```

Lo que se pospone es pasarela, Cloud API, IA y más canales — no el hecho de ser SaaS. La empresa 3 se crea en el panel, no con un fork.

---

# 45. Estrategia de validación

Antes de invertir meses en desarrollo:

## Paso 1

Construir prototipo navegable.

## Paso 2

Presentarlo a 5–10 empresas.

## Paso 3

Identificar problemas reales.

## Paso 4

Conseguir 3 primeros usuarios piloto.

## Paso 5

Cobrar.

Aunque sea un precio reducido.

La validación importante no es:

> "¿Te gusta?"

La pregunta importante es:

> **"¿Pagarías S/99 mensuales por utilizarlo?"**

---

# 46. Nichos iniciales

No se recomienda atacar todos los sectores simultáneamente.

Los primeros candidatos:

### Restaurantes

Necesidades:

- Reservas.
- Pedidos.
- Promociones.
- Seguimiento.

### Clínicas

Necesidades:

- Citas.
- Confirmaciones.
- Recordatorios.

### Inmobiliarias

Necesidades:

- Leads.
- Seguimiento.
- Asignación de asesores.

### Servicios técnicos

Necesidades:

- Cotizaciones.
- Seguimientos.
- Recordatorios.

### Tiendas

Necesidades:

- Consultas.
- Ventas.
- Postventa.

---

# 47. Ventaja competitiva

La plataforma no debería competir únicamente por precio.

Debe competir por:

```text
Simplicidad
+
Atención rápida
+
Automatización
+
Segmentación
+
Experiencia local
```

Una estrategia interesante sería construir posteriormente verticales:

```text
OmniDesk Restaurantes
OmniDesk Clínicas
OmniDesk Inmobiliarias
OmniDesk Servicios
```

compartiendo el mismo core SaaS.

---

# 48. Riesgos

## Riesgo 1 — OpenWA / WhatsApp Web

OpenWA no es la API oficial. El número puede desconectarse o restringirse si se comporta como bot/spam.

Mitigación:

- 500/día, ventana laboral, 1 msg/~86 s, sin ráfagas.
- Respuestas humanas inmediatas; campañas solo por drip.
- Una sesión por empresa, teléfono dedicado de la empresa, no un número compartido.
- Superadmin ve sesión, 429 y cuota.
- `ChannelProviderInterface` listo para migrar a Cloud API si el volumen o el riesgo lo exigen.

---

## Riesgo 2 — 429 y sesiones caídas en OpenWA

Ya pasó en VetSaaS con muchas clínicas.

Mitigación: cache de `listSessions`, cooldown 240 s, reconnect con presupuesto, no start masivo. Con 2 tenants es barato; el código debe nacer con esos frenos.

---

## Riesgo 3 — Fuga entre empresas

Mitigación: schemas + `search_path` + nunca `schema_name` desde la request + `UsesPublicSchema` en modelos de plataforma.

---

## Riesgo 4 — Complejidad prematura

Mitigación: no construir Cloud API, IA ni pasarela en el sprint 1. Sí construir SaaS (tenant N, plan, límite, suspender). Las 2 primeras son clientes, no una excepción en el código.

---

# 49. Arquitectura preparada para IA

La IA no debe estar acoplada al núcleo.

Diseño:

```text
Conversation
      |
      v
AI Service
      |
      +--> Summarize
      +--> Classify
      +--> Suggest Reply
      +--> Detect Intent
      +--> Lead Score
```

Esto permitirá integrar posteriormente diferentes proveedores.

---

# 50. Arquitectura preparada para múltiples canales

Modelo conceptual:

```text
                    Conversation Core
                           |
            +--------------+--------------+
            |              |              |
        WhatsApp       Instagram       Telegram
            |              |              |
       Provider A      Provider B      Provider C
```

La conversación debe pertenecer al sistema, no al proveedor.

Esto permite que una futura conversación tenga:

```text
channel = whatsapp
```

o:

```text
channel = instagram
```

sin rediseñar todo el núcleo.

---

# 51. Principios de ingeniería

El proyecto deberá seguir estos principios:

1. Seguridad por diseño.
2. Multi-tenancy desde el primer commit.
3. Backend como autoridad de negocio.
4. Idempotencia en integraciones.
5. Procesamiento asíncrono.
6. Observabilidad.
7. Código mantenible.
8. Separación de dominios.
9. APIs versionadas.
10. Pruebas automatizadas.
11. Migraciones controladas.
12. No sobrearquitecturar prematuramente.

---

# 52. Criterios de aceptación del MVP

El MVP será considerado funcional cuando:

- Una empresa pueda registrarse.
- Un administrador pueda crear agentes.
- El superadmin pueda crear un tenant y su schema.
- La empresa pueda escanear QR y dejar la sesión `ready`.
- Un cliente pueda escribir y el mensaje llegue a la bandeja sin refresh.
- Un agente pueda responder (descuenta cuota).
- Una campaña/recordatorio salga por drip, no en ráfaga.
- Al agotar la cuota del plan no salga nada más hasta el día siguiente.
- Empresa A no vea datos de empresa B (schemas distintos).
- Webhooks duplicados no creen mensajes dobles.
- El superadmin vea sesión, cuota y errores.

---

# 53. Prioridad de desarrollo

Orden recomendado:

```text
1. Tenancy schemas + TenantManager + provisioner (copia mental VetSaaS)
2. Superadmin: tenants + impersonación
3. Auth / usuarios / roles
4. OpenWaClient + sesión + QR
5. Webhooks inbound
6. Contactos / conversaciones / mensajes
7. Bandeja + WebSockets
8. Envío agente + cuota del plan
9. outbound_queue + drip worker
10. Asignación / etiquetas / respuestas rápidas
11. Recordatorios
12. Dashboard
13. Campañas (solo sobre la cola)
14. Pasarela + signup público
15. Cloud API / IA — cuando un cliente o el volumen lo pidan
```

---

# 54. Decisión arquitectónica principal

## Monolito modular antes que microservicios

Para el MVP:

```text
Laravel
  |
  +-- Domain Modules
  |
  +-- Queue
  |
  +-- Redis
  |
  +-- Database
```

No se recomienda separar:

```text
conversation-service
message-service
campaign-service
contact-service
```

desde el inicio.

La complejidad operacional no estaría justificada por el volumen inicial.

Cuando exista una necesidad real de escala, los módulos que tengan mayor carga podrán extraerse progresivamente.

---

# 55. Evolución esperada

### Etapa 1

```text
WhatsApp + Inbox
```

### Etapa 2

```text
Inbox + CRM básico
```

### Etapa 3

```text
CRM + Automatización
```

### Etapa 4

```text
Automatización + IA
```

### Etapa 5

```text
Omnicanal + IA + CRM
```

La visión final sería:

> **Una plataforma de comunicación empresarial que centralice conversaciones, clientes, automatizaciones y procesos comerciales en un solo lugar.**

---

# 56. Conclusión

OmniDesk se construye como **SaaS vendible** (VetSaaS: schemas, superadmin, planes, provisioner). Las 2 primeras empresas son los primeros clientes, no el producto.

Arranque:

> **Plataforma SaaS + OpenWA + bandeja + cuota del plan + drip.**

Se vende cuando haya un tercer interesado: se provisiona, se asigna plan, se cobra (manual o pasarela). No se reescribe.

El núcleo no se acopla a OpenWA ni a dos slugs fijos. Tenant N, plan y `ChannelProvider` nacen en el core.

---

# 57. Próximo documento recomendado

Después de esta especificación, el siguiente artefacto técnico debería ser:

Siguiente artefacto:

```text
02-BACKLOG-SAAS-OPENWA.md
```

Tickets del core vendible:

- `TenantManager` / `TenantProvisioner` / schemas (N tenants).
- Planes, suscripciones, límites, suspensión.
- Superadmin: tenants, planes, OpenWA, uso, impersonar.
- `OpenWaClient` + QR + webhook.
- Bandeja + `outbound_queue` + drip según plan.

Pasarela y signup público: Fase 3, sobre ese mismo core. Cloud API e IA no bloquean la venta.
