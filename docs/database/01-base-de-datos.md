# Base de datos OmniDesk / SendSaaS

Orden de migraciones, columnas y relaciones. Mapeado de VetSaaS (planes, features, sedes, subscriptions, provisión Orvae) + tablas de bandeja WhatsApp.

Orvae es el padre comercial. Este schema es el del hijo. El contrato HTTP está en [../orvae/01-contrato-provision.md](../orvae/01-contrato-provision.md).

---

## 0. Principios

```
PostgreSQL (una sola BD)
│
├── schema public          ← plataforma. La ve el superadmin y Orvae.
│   tenants, users, roles, plans, subscriptions, sedes,
│   tenant_whatsapp_sessions, provision_idempotency_keys
│
└── schema od_<6chars>     ← 1 por empresa. search_path = "od_xxx", public
    contacts, conversations, messages, tags, campaigns,
    outbound_queue, automations, audit_logs
    (sin tenant_id: el schema ya aísla)
```

- Host tenant: `{slug}.{TENANT_ROOT_DOMAIN}` → `empresa-ana.sendsaas.orvae.pe`.
- Host central (no tenant): `TENANT_CENTRAL_DOMAINS` → `sendsaas.orvae.pe`.
- **No hay tabla `domains`.** El host se deriva del slug.
- `users` y `sedes` viven en **public** con `tenant_id`.
- Schema **nunca** viene del request. Se lee de `tenants.schema_name` y se sanea (`od_` + `[a-z0-9_]`, max 63).
- Prefijo: `TENANT_SCHEMA_PREFIX=od_` (VetSaaS usa `vet_`).
- Log de migraciones tenant: **dentro** del schema, no en `public.migrations`.

Leyenda: **YA** = ya corrió en este repo. **PENDIENTE** = hay que crear.

---

## 1. Carpetas

```
database/
├── migrations/                      # schema public (plataforma)
│   ├── 0001_01_01_000000_…          # YA Laravel
│   ├── 2026_09_08_…                 # YA Spatie + tenants mínimos + users SaaS
│   └── 2026_09_09_1xxxxx_…          # PENDIENTE: ubigeo, planes, sedes, billing, Orvae
│
├── migrations/tenant/               # PENDIENTE — se aplican por schema od_*
│   ├── 2026_09_09_t010_…
│   └── 2026_09_09_t0xx_…
│
└── seeders/
    ├── PermissionsSeeder.php        # YA
    ├── SuperadminSeeder.php         # YA
    ├── TenantRolesSeeder.php        # YA
    └── PlansAndFeaturesSeeder.php   # PENDIENTE
```

`TenantProvisioner` corre `migrate --path=database/migrations/tenant` con `search_path` del schema nuevo.

---

## 2. Schema `public` — orden de migración

### 2.1 Núcleo Laravel — YA

| # | Archivo | Tablas |
|---|---|---|
| 1 | `0001_01_01_000000_create_users_table` | `users` (luego se reconstruye a UUID), `password_reset_tokens`, `sessions` |
| 2 | `0001_01_01_000001_create_cache_table` | `cache`, `cache_locks` |
| 3 | `0001_01_01_000002_create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` |
| 4 | `2024_01_01_000000_create_passkeys_table` | `passkeys` (`user_id` → UUID) |
| 5 | `2025_08_14_170933_add_two_factor_columns_to_users_table` | 2FA en `users` |

### 2.2 RBAC Spatie — YA

| # | Archivo | Notas |
|---|---|---|
| 6 | `2026_09_08_001101_create_permission_tables` | `permissions`, `roles`, `model_has_*`, `role_has_permissions`. `roles.tenant_id` UUID nullable. PK de pivotes **sin** `tenant_id` (Postgres no permite NULL en PK; superadmin = team null). |
| 7 | `2026_09_08_130000_allow_null_tenant_on_permission_pivots` | Alinea pivotes ya creados en PG. |

Roles:

| Ámbito | `roles.tenant_id` | Nombres |
|---|---|---|
| Plataforma | `null` | `superadmin` |
| Empresa | UUID del tenant | `admin_empresa`, `supervisor`, `agente` |

### 2.3 Tenants mínimos + users SaaS — YA

| # | Archivo | Estado |
|---|---|---|
| 8 | `2026_09_08_120000_create_tenants_table` | Esqueleto. Faltan ubigeo, referidos, geo, `email_admin` unique. |
| 9 | `2026_09_08_120100_upgrade_users_table_to_saas` | `users.id` UUID, `tenant_id`, perfil, lifecycle, soft deletes. |

`users` (YA, columnas actuales):

| Columna | Tipo | Notas |
|---|---|---|
| id | uuid PK | |
| tenant_id | uuid nullable | `null` = plataforma. FK tenants nullOnDelete |
| name, email | string | email unique global hoy; VetSaaS usa unique por tenant (`COALESCE`) |
| phone | string(32) | |
| documento_tipo / documento_numero | | |
| colegiatura, cv_path, dni_file_path, firma_path | | Perfil; en OmniDesk colegiatura puede quedar unused |
| password + 2FA | | |
| is_active | bool | |
| must_change_password | bool | Orvae: admin nace en true |
| bootstrap_login_token / expires_at | | URL de bienvenida |
| last_login_at, last_seen_at, last_path, last_module, last_path_at | | |
| created_by_id | uuid FK users | |
| timestamps + deleted_at | | |

**PENDIENTE en users:** unique `(COALESCE(tenant_id,'__central__'), lower(email))` como VetSaaS, si se permite el mismo email en dos empresas.

### 2.4 Ubigeo — PENDIENTE

Antes de ampliar `tenants` / `sedes` (FK `distrito_id`).

| # | Archivo propuesto | Tabla | Columnas |
|---|---|---|---|
| 10 | `2026_09_09_100010_create_paises_table` | `paises` | id, name(100), status |
| 11 | `2026_09_09_100011_create_departamentos_table` | `departamentos` | id, pais_id FK, name, status |
| 12 | `2026_09_09_100012_create_provincias_table` | `provincias` | id, departamento_id FK, name, status |
| 13 | `2026_09_09_100013_create_distritos_table` | `distritos` | id, provincia_id FK, name, status |

### 2.5 Planes y features — PENDIENTE

Igual que VetSaaS: catálogo en `plans` + filas `plan_features` (no columnas sueltas por feature).

#### `plans`

| Columna | Tipo | Notas |
|---|---|---|
| id | uuid PK | |
| codigo | string(30) unique | `free`, `starter`, `profesional`, `business`, `enterprise` — es el `plan_slug` de Orvae |
| nombre | string(80) | |
| descripcion | text nullable | |
| badge | string(50) nullable | “Más popular” |
| color_hex | string(7) | `#AB3C3D` |
| precio_mensual | decimal(10,2) | |
| precio_anual | decimal(10,2) nullable | |
| trial_days | smallint | 0 en free |
| orden | smallint | |
| es_publico | bool | visible en signup |
| activo | bool | Orvae solo provisiona activos |
| referral_reward_days | smallint default 0 | opcional, igual VetSaaS |
| timestamps | | |

#### `plan_features`

| Columna | Tipo | Notas |
|---|---|---|
| id | uuid PK | |
| plan_id | uuid FK plans cascade | |
| feature | string(60) | clave estable |
| valor_int | int nullable | `-1` = ilimitado |
| valor_bool | bool nullable | |
| valor_str | string(50) nullable | |
| unique | (plan_id, feature) | |

#### `promo_codes` (opcional, mismo orden que VetSaaS)

codigo unique, tipo_descuento, valor, plan_id_restriccion FK, max_usos, usos_actuales, un_uso_por_tenant, activo, valido_desde/hasta.

| # | Archivo propuesto |
|---|---|
| 14 | `2026_09_09_100030_create_plans_table` |
| 15 | `2026_09_09_100040_create_plan_features_table` |
| 16 | `2026_09_09_100050_create_promo_codes_table` |

---

## 3. Catálogo de planes OmniDesk

Features (fuente de verdad = seeder, no el modelo).

**Límites int** (`-1` = ilimitado):

| feature | Qué limita |
|---|---|
| `max_sedes` | Sucursales en `public.sedes` |
| `max_usuarios` | Users con `tenant_id` = esta empresa |
| `max_whatsapp_sessions` | Filas en `tenant_whatsapp_sessions` (Starter = 1) |
| `max_outbound_per_day` | Cupo drip + respuestas (default Starter 500) |
| `max_contacts` | Contactos en el schema |
| `max_campaigns` | Campañas activas / mes |
| `max_automations` | Automatizaciones activas |

**Bool:**

| feature | Módulo |
|---|---|
| `multi_sede` | Más de una sede |
| `modulo_recordatorios` | |
| `modulo_automatizaciones` | |
| `modulo_campanas` | |
| `modulo_dashboard` | Métricas |
| `api_acceso` | |
| `reportes_avanzados` | |

**Str:**

| feature | Valores |
|---|---|
| `send_window_start` | `08:00` |
| `send_window_end` | `20:00` |
| `soporte_tipo` | `docs` / `email` / `whatsapp` / `whatsapp_prioritario` |

### Valores iniciales (spec §31)

| codigo | Precio/mes | Trial | sedes | users | WA | outbound/día | Highlights |
|---|---|---|---|---|---|---|---|
| `free` | 0 | 0 | 1 | 1 | 1 | 50 | Bandeja + contactos |
| `starter` | 49 | 14 | 1 | 2 | 1 | **500** | Etiquetas, quick replies |
| `profesional` | 99 | 14 | 1 | 5 | 1 | 1500 | Recordatorios, automatizaciones, campañas, dashboard |
| `business` | 199 | 7 | 2 | 15 | 2 | 5000 | Segmentación, reportes, API, `multi_sede` |
| `enterprise` | 399 | 7 | −1 | −1 | −1 | −1 | Todo + soporte prioritario |

`PlansAndFeaturesSeeder` debe correr **antes** del primer POST de Orvae. El provisioner busca `plans.codigo = plan_slug`.

Overrides por tenant: `tenant_plan_overrides` (más abajo). El worker drip **nunca** hardcodea 500: lee plan + override + timezone.

---

## 4. Ampliar `tenants` — PENDIENTE

La tabla YA existe. Una migración `2026_09_09_100060_upgrade_tenants_table_to_saas` añade lo que VetSaaS tiene y OmniDesk necesita.

| Columna | Tipo | Notas |
|---|---|---|
| distrito_id | FK distritos nullOnDelete | |
| geo_lat, geo_lng | decimal(10,7) | |
| geo_consent_at, geo_denied_at, geo_captured_at, geo_refresh_requested_at | timestamptz | |
| referido_por_tenant_id | uuid FK tenants | |
| referral_code | string(40) unique | |
| referral_days_balance | uint default 0 | |
| modulos_deshabilitados | json | kill-switch superadmin |
| email_admin | unique | hoy no es unique |

CHECKs Postgres (copia VetSaaS):

```
slug ~ '^[a-z0-9\-]+$'
ruc IS NULL OR ruc ~ '^\d{11}$'
onboarding_paso BETWEEN 0 AND 5
estado IN ('trial','active','suspended','cancelled')
```

Índices parciales: slug / estado where `deleted_at IS NULL`; trial_ends_at where `estado = 'trial'`.

`email_admin` unique: Orvae hace lookup por email.

No copiar de VetSaaS: `nubefact_*`, `sunat_configurado` (no hay FEL en OmniDesk).

---

## 5. Sedes — PENDIENTE (public, no en el schema tenant)

Igual que VetSaaS. Una empresa puede tener sucursales; cada sede puede terminar con su propio número WhatsApp (limitado por `max_whatsapp_sessions` / `max_sedes`).

| # | Archivo propuesto |
|---|---|
| 17 | `2026_09_09_100080_create_sedes_table` |

#### `sedes`

| Columna | Tipo | Notas |
|---|---|---|
| id | uuid PK | |
| tenant_id | uuid NOT NULL FK tenants cascade | |
| nombre | string(150) | |
| codigo | string(10) | `SEDE-001`; CHECK `[A-Z0-9\-]+` |
| direccion, telefono, email | | |
| distrito_id | FK distritos | |
| distrito, provincia, departamento | string cache | |
| activa | bool default true | |
| created_by_id, updated_by_id | FK users | |
| timestampsTz + softDeletes | | |
| unique | (tenant_id, codigo) | |

El provisioner **no crea sede**. El `admin_empresa` la crea. Límite: `max_sedes` + `multi_sede`.

Más adelante: `tenant_whatsapp_sessions.sede_id` nullable (1 número por sede en Business+).

---

## 6. Subscriptions y cobro — PENDIENTE

| # | Archivo propuesto | Tabla |
|---|---|---|
| 18 | `2026_09_09_100070_create_subscriptions_table` | `subscriptions` |
| 19 | `2026_09_09_100080_create_subscription_payments_table` | `subscription_payments` |
| 20 | `2026_09_09_100090_create_subscription_renewal_reminders_table` | reminders |
| 21 | `2026_09_09_100100_create_tenant_plan_overrides_table` | overrides |
| 22 | `2026_09_09_100110_create_usage_records_table` | cupo diario outbound |

### `subscriptions`

| Columna | Tipo | Notas |
|---|---|---|
| id | uuid PK | |
| tenant_id | uuid FK tenants cascade | unique parcial: 1 fila con `estado <> cancelled` |
| plan_id | uuid FK plans | |
| estado | string(20) | CHECK `trial\|active\|grace\|suspended\|cancelled` |
| ciclo | string | `mensual\|trimestral\|semestral\|anual` |
| trial_ends_at, current_period_start, current_period_end, grace_ends_at, cancelled_at | timestamptz | |
| cancel_reason, cancel_feedback | text | |
| precio_pactado | decimal(10,2) | |
| descuento_pct | decimal(5,2) | |
| promo_code_id | uuid FK nullable | |
| proximo_cobro_at | timestamptz | |
| metodo_pago_token | string(200) | |

`grace` vive aquí, no en `tenants.estado` (VetSaaS). El middleware de tenant deja pasar `active`, `trial` y `grace`.

### `subscription_payments`

uuid; FKs subscription, tenant, plan; monto, moneda CHAR(3), igv_monto, descuento_monto, total; estado CHECK `pendiente\|procesado\|fallido\|reembolsado`; pasarela (`orvae\|culqi\|manual\|…`); pasarela_transaction_id; pasarela_response json; periodo_inicio/fin; error_mensaje; pagado_at; internal_note; refunded_*.

Orvae manda el bloque `payment` en provision/renew → se inserta aquí.

### `tenant_plan_overrides`

uuid; tenant_id FK cascade; feature(40); extra uint; override int nullable; precio_mensual nullable; motivo; expires_at; created_by_id; unique (tenant_id, feature).

Sirve para “+200 outbound/día” o precio pactado sin tocar el plan.

### `usage_records`

Para no hardcodear Redis como única fuente:

| Columna | Tipo |
|---|---|
| id | uuid |
| tenant_id | uuid FK |
| metric | string(40) — `outbound_day` |
| period | date — día Lima |
| used | int |
| unique | (tenant_id, metric, period) |

El worker incrementa `used`. El límite sale de plan + override.

---

## 7. Orvae + WhatsApp plataforma — PENDIENTE

| # | Archivo propuesto | Tabla |
|---|---|---|
| 23 | `2026_09_09_100120_create_provision_idempotency_keys_table` | replay HMAC |
| 24 | `2026_09_09_100130_create_tenant_whatsapp_sessions_table` | 1 (o N) OpenWA por tenant |
| 25 | `2026_09_09_100140_create_platform_settings_table` | singleton |
| 26 | `2026_09_09_100150_create_impersonation_audit_logs_table` | superadmin → empresa |
| 27 | `2026_09_09_100160_create_platform_security_audit_logs_table` | |

### `provision_idempotency_keys`

| Columna | Tipo |
|---|---|
| id | bigIncrements |
| key | string(120) unique — valor de `X-Idempotency-Key` |
| source | string default `orvae` CHECK `orvae\|manual` |
| tenant_id | uuid FK nullOnDelete |
| status_code | int |
| response_body | json |
| created_at, expires_at | |

TTL: `ORVAE_PROVISION_IDEMPOTENCY_TTL_DAYS=30`.

### `tenant_whatsapp_sessions`

En **public** para que el superadmin vea QR/errores sin cambiar `search_path`.

| Columna | Tipo | Notas |
|---|---|---|
| id | uuid PK | |
| tenant_id | uuid FK tenants cascade | unique si 1 sesión; si N, unique (tenant_id, sede_id) |
| sede_id | uuid FK sedes nullable | Business+ |
| openwa_session_id | string(80) | |
| openwa_session_name | string(120) | = `tenants.slug` |
| status | string | CHECK `created\|initializing\|qr_ready\|authenticating\|ready\|disconnected\|failed` |
| phone, push_name | | |
| connected_at, last_synced_at | timestamptz | |
| last_error | text | |
| auto_reconnect | bool default true | |

### `platform_settings`

Singleton (unique index `(TRUE)` en PG). Flags globales, no secretos de tenant.

---

## 8. Relaciones `public`

```
plans 1──N plan_features
plans 1──N subscriptions
tenants 1──1 subscriptions          (parcial: no cancelled)
tenants 1──N sedes
tenants 1──N users                  (empleados)
tenants 1──N tenant_plan_overrides
tenants 1──N tenant_whatsapp_sessions
tenants 1──N subscription_payments
tenants 1──N usage_records
users   tenant_id null              → superadmin
roles   tenant_id null              → rol plataforma
roles   tenant_id = empresa         → roles operativos
```

---

## 9. Schema tenant — orden de migración

Carpeta `database/migrations/tenant/`. Prefijo `tNNN` como VetSaaS. **No** incluir users, sedes, plans.

Settings de la empresa (RUC, logo, ventana de envío default) pueden ir en `cfg_empresa_settings` singleton, FK a `public.distritos` / `public.users`.

| # | Archivo | Tabla | Columnas clave |
|---|---|---|---|
| t010 | `…_t010_create_cfg_empresa_settings` | `cfg_empresa_settings` | singleton: razon, ruc, logo, color `#AB3C3D`, timezone |
| t020 | `…_t020_create_tags` | `tags` | id uuid, name, color, unique name |
| t021 | `…_t021_create_contacts` | `contacts` | name, phone unique, email, notes, last_contact_at, sede_id (uuid public.sedes, sin FK cross-schema obligatoria) |
| t022 | `…_t022_create_contact_tags` | `contact_tags` | contact_id, tag_id |
| t030 | `…_t030_create_conversations` | `conversations` | contact_id, channel default `whatsapp`, assigned_user_id (public.users), sede_id, status (`OPEN\|PENDING\|RESOLVED\|CLOSED`), priority, last_message_at, unread_count |
| t031 | `…_t031_create_conversation_messages` | `conversation_messages` | conversation_id, sender_type (`contact\|agent\|system`), sender_id, external_id unique, direction (`in\|out`), message_type, body, media_url, status, sent_at, delivered_at, read_at, failed_at, metadata json |
| t032 | `…_t032_create_conversation_tags` | pivot | |
| t033 | `…_t033_create_internal_notes` | `conversation_notes` | conversation_id, user_id, body |
| t040 | `…_t040_create_quick_replies` | `quick_replies` | title, body, shortcut |
| t050 | `…_t050_create_reminders` | `reminders` | contact_id, conversation_id, due_at, body, done_at, created_by |
| t060 | `…_t060_create_outbound_queue` | `outbound_queue` | kind (`campaign\|reminder\|automation\|agent`), payload json, phone, scheduled_at, sent_at, status (`pending\|sent\|failed\|skipped`), conversation_id, campaign_id, error |
| t070 | `…_t070_create_campaigns` | `campaigns` | name, status, body, scheduled_at, created_by |
| t071 | `…_t071_create_campaign_recipients` | `campaign_recipients` | campaign_id, contact_id, outbound_id, status |
| t080 | `…_t080_create_automations` | `automations` | name, trigger, config json, active |
| t090 | `…_t090_create_audit_logs` | `audit_logs` | user_id, action, entity_type, entity_id, old/new json, ip, user_agent |

Índices tenant (mínimo):

- `contacts.phone` unique
- `conversations (status, last_message_at desc)`
- `conversations.assigned_user_id`
- `conversation_messages.external_id` unique
- `outbound_queue (status, scheduled_at)` where pending

`assigned_user_id` y `sede_id` apuntan a filas de **public**. No hace falta FK Postgres cross-schema; la integridad la guarda la app.

---

## 10. Qué hace el provisioner (orden de writes)

```
1. plans.codigo = plan_slug          (ya seedado)
2. INSERT tenants                    (schema_name = od_xxxxxx)
3. INSERT subscriptions              (+ payment si Orvae mandó payment)
4. CREATE SCHEMA "od_xxxxxx"
5. migrate --path=database/migrations/tenant
6. INSERT cfg_empresa_settings
7. TenantRolesSeeder::seedForTenant
8. INSERT users admin_empresa
9. INSERT tenant_whatsapp_sessions status=created
10. NO inserta sedes
11. Cache respuesta en provision_idempotency_keys
```

---

## 11. Seeders (orden)

| Orden | Seeder | Cuándo |
|---|---|---|
| 1 | `PermissionsSeeder` | `db:seed` / migrate fresh |
| 2 | `PlansAndFeaturesSeeder` | **antes** de Orvae |
| 3 | `SuperadminSeeder` | panel central |
| 4 | `TenantRolesSeeder` | lo llama el provisioner, no el seed global vacío |

---

## 12. Fuera de este mapa (no copiar de VetSaaS)

No van al MVP de SendSaaS:

- FEL / Nubefact / `fel_*`
- Inventario, ventas, caja, grooming, hotel, HC, pacientes
- SalesBot, prospectos veterinarias, portal propietario
- `existencias_sede` y series de factura en sedes (sí dejamos sedes como sucursal + futuro número WA)

Sedes **sí** se copian: son multi-sucursal de la empresa, no un módulo clínico.

---

## 13. Estado actual vs siguiente sprint

**Ya en Postgres local:** Laravel + Spatie + `tenants` mínimo + `users` UUID SaaS.

**Siguiente bloque de migraciones (este orden, no otro):**

1. Ubigeo  
2. `plans` + `plan_features` + seeder  
3. Upgrade `tenants`  
4. `sedes`  
5. `subscriptions` + payments + overrides + `usage_records`  
6. `provision_idempotency_keys`  
7. `tenant_whatsapp_sessions` + settings + audit  
8. Carpeta `migrations/tenant` t010–t090  
9. `TenantManager` + `TenantProvisioner` + rutas Orvae  

Sin el paso 9 Orvae no tiene a quién pegarle. Sin el paso 2 el POST `/provision` no resuelve `plan_slug`.
