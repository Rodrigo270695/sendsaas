# Contrato Orvae PE → SendSaaS

Orvae (`D:\Programacion\Laravel\LaraReact\orvaepe`) cobra y crea la suscripción comercial. El hijo crea el tenant. El patrón es el de VetSaaS: **POST síncrono firmado HMAC**, no hay Job.

```
Cliente paga en Orvae (Culqi / MP / manual)
        │
        ▼
OrderPaidSubscriptionProvisioner
        │  SKU metadata.saas_product = sendsaas
        ▼
SendSaaSPlanProvisioner  (ya cableado en Orvae)
        │
        │  POST /api/internal/saas/provision
        │  HMAC-SHA256("{timestamp}.{body}", secret)
        ▼
SendSaaS TenantProvisioner
        │
        ├── public.tenants + schema od_*
        ├── migrate database/migrations/tenant
        ├── subscription + payment
        ├── roles Spatie (team = tenant_id)
        ├── user admin_empresa + bootstrap_url
        └── tenant_whatsapp_sessions (ensure OpenWA)
        │
        ▼
201 { login_url, bootstrap_url, tenant_slug }
        │
        ▼
Orvae redirige al cliente / manda WhatsApp
```

El mismo `HMAC` se usa en renovación: `POST /api/internal/saas/renew`.

---

## 1. Firma

```
signature = HMAC-SHA256( "{unix_ts}.{raw_json_body}" , ORVAE_PROVISION_HMAC_SECRET )
```

El body firmado es el JSON **byte a byte** (`JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`). No re-serializar.

Headers que Orvae envía:

```
Content-Type: application/json
Accept: application/json
X-Orvae-Timestamp: 1770000000
X-Orvae-Signature: <hex>          # sin prefijo; el hijo también acepta sha256=
X-Idempotency-Key: order:{uuid}
```

Rechazar (401) si:

| reason | Cuándo |
|---|---|
| `integration_not_configured` | Falta el secret en SendSaaS |
| `missing_signature_headers` | Falta timestamp o firma |
| `invalid_timestamp` | No es unix seconds |
| `timestamp_skew_too_large` | \|now − ts\| > `ORVAE_PROVISION_MAX_SKEW_SECONDS` (300) |
| `invalid_signature` | HMAC no coincide |

Idempotencia: misma `X-Idempotency-Key` → devolver el JSON cacheado en `provision_idempotency_keys` (TTL 30 días). Si no, Orvae reintenta y se duplican tenants.

GET (lookup): se firma `"{ts}."` (body vacío).

---

## 2. Endpoints que SendSaaS debe exponer

Prefijo `routes/api.php`, middleware `VerifyOrvaeProvisionSignature`:

| Método | Ruta | Uso |
|---|---|---|
| POST | `/api/internal/saas/provision` | Alta (mínimo) |
| POST | `/api/internal/saas/renew` | Renovación (mínimo) |
| GET | `/api/internal/saas/lookup?email=` | Tenant por `email_admin` |
| GET | `/api/internal/saas/tenants/{slug}` | Status |
| GET | `/api/internal/saas/showcase` | Opcional, carrusel Orvae |

---

## 3. POST `/provision` — body que Orvae manda (plano)

```json
{
  "external_order_id": "9f3c…",
  "order_number": "ORV-000123",
  "plan_slug": "starter",
  "ciclo": "mensual",
  "tenant_slug": "empresa-ana",
  "razon_social": "Empresa Ana Perez",
  "nombre_comercial": "Empresa Ana Perez",
  "telefono": "999111222",
  "timezone": "America/Lima",
  "locale": "es_PE",
  "canal_adquisicion": "orvae",
  "admin_nombres": "Ana",
  "admin_apellidos": "Perez",
  "admin_email": "ana@cliente.test",
  "admin_password": "<temporal-16>",
  "payment": {
    "monto": 49.0,
    "moneda": "PEN",
    "pasarela": "culqi",
    "transaction_id": "chg_xxx",
    "pagado_at": "2026-09-07T20:00:00-05:00"
  }
}
```

`plan_slug` debe existir en `plans.codigo`. Valores OmniDesk: `free`, `starter`, `profesional`, `business`, `enterprise`.

Validación mínima:

| Campo | Regla |
|---|---|
| `external_order_id` | required, ≤120 |
| `plan_slug` | required, existe en `plans` |
| `tenant_slug` | `^[a-z0-9\-]{3,60}$` |
| `razon_social` | required |
| `admin_email` | required email |
| `admin_password` | required, min 8 |
| `payment.monto` | required_with payment, ≥0 |
| `payment.moneda` | `PEN` \| `USD` |

Aceptar también el payload anidado de Aula Virtual (`customer` / `tenant` / `subscription`) vía un `ProvisionPayloadNormalizer`, como VetSaaS. Orvae no lo usa para VetSaaS; SendSaaS puede vivir solo con el plano.

---

## 4. Qué crea el provisioner

1. Validar Postgres + plan activo (`plans.codigo` = `plan_slug`).
2. Slug único.
3. Schema `od_` + 6 chars aleatorios (`[a-z0-9]`), único, max 63.
4. Fila `tenants` (`estado` = `active` si free, si no `trial`; `canal_adquisicion` = `orvae`).
5. `subscriptions` + `subscription_payments` si hay `payment`.
6. `CREATE SCHEMA` + migrate `database/migrations/tenant`.
7. `TenantRolesSeeder::seedForTenant` (`admin_empresa`, `supervisor`, `agente`).
8. User admin (`tenant_id`, `must_change_password=true`, `bootstrap_login_token`).
9. `tenant_whatsapp_sessions` (fila `created`; el QR se pide después).
10. **No crea sede.** El admin la da de alta; el plan limita `max_sedes`.

---

## 5. Respuesta 201

```json
{
  "status": "ok",
  "tenant": {
    "id": "uuid",
    "slug": "empresa-ana",
    "schema_name": "od_ab12cd",
    "razon_social": "Empresa Ana Perez",
    "estado": "trial",
    "trial_ends_at": "2026-09-21T20:00:00-05:00"
  },
  "login_url": "https://empresa-ana.sendsaas.orvae.pe/login",
  "academy_url": "https://empresa-ana.sendsaas.orvae.pe/login",
  "bootstrap_url": "https://empresa-ana.sendsaas.orvae.pe/auth/bienvenida/{token}",
  "tenant_slug": "empresa-ana"
}
```

Orvae lee `login_url` (o `academy_url`), `bootstrap_url` y `tenant_slug`. Si falta `login_url` reconstruye:

```
{SENDSAAS_TENANT_SCHEME}://{slug}.{SENDSAAS_TENANT_DOMAIN}/login
```

El path `/login` está hardcodeado en Orvae. El hijo debe devolver `login_url` ya armada.

Errores: `422 {error: invalid_payload}`, `500 {error: provisioning_failed}`.

---

## 6. POST `/renew`

Body: `external_order_id`, `tenant_slug`, `plan_slug`, `ciclo`, `payment` (required), `period_start`, `period_end`, `precio_pactado`.

Respuesta 200: `{ "status": "ok", "renewed": true, "subscription": {…}, "login_url": "…" }`.

---

## 7. Variables de entorno

### En SendSaaS (`.env`)

Mismo secret que `SENDSAAS_PROVISION_HMAC_SECRET` en Orvae. Generar: `php scripts/generate-orvae-hmac.php`. Plantilla VPS: `.env.vps.example`.

```env
ORVAE_PROVISION_HMAC_SECRET=
ORVAE_PROVISION_MAX_SKEW_SECONDS=300
ORVAE_PROVISION_IDEMPOTENCY_TTL_DAYS=30

SENDSAAS_TENANT_SCHEME=https
SENDSAAS_TENANT_DOMAIN=sendsaas.orvae.pe
SENDSAAS_TENANT_LOGIN_PATH=/login
SENDSAAS_BOOTSTRAP_TTL_HOURS=48

TENANT_CENTRAL_DOMAINS=sendsaas.orvae.pe,localhost
TENANT_ROOT_DOMAIN=sendsaas.orvae.pe
TENANT_SCHEMA_PREFIX=od_
TENANT_CACHE_TTL=60
```

Local: `TENANT_ROOT_DOMAIN=sendsaas.test` y hosts `empresa-ana.sendsaas.test`.

### En Orvae (ya cableado)

```env
SENDSAAS_PROVISIONING_ENABLED=true
SENDSAAS_PROVISION_URL=https://sendsaas.orvae.pe/api/internal/saas/provision
SENDSAAS_RENEW_URL=https://sendsaas.orvae.pe/api/internal/saas/renew
SENDSAAS_LOOKUP_URL=https://sendsaas.orvae.pe/api/internal/saas/lookup
SENDSAAS_PROVISION_HMAC_SECRET=
SENDSAAS_TENANT_DOMAIN=sendsaas.orvae.pe
SENDSAAS_TENANT_SCHEME=https
```

SKU de catálogo Orvae:

```json
{
  "sale_model": "saas_subscription",
  "billing_interval": "monthly",
  "fulfillment_type": "saas_url",
  "metadata": {
    "saas_product": "sendsaas",
    "saas_plan_slug": "starter"
  }
}
```

Orvae ya tiene `isSendsaas()`, `SendSaaSPlanProvisioner` y el dispatch. El POST fallará hasta que SendSaaS exponga `/api/internal/saas/*` (siguiente fase).

---

## 8. Checklist Orvae (padre)

1. Bloque `sendsaas` en `config/services.php` — hecho.
2. Vars en `.env` / `.env.example` — hecho.
3. `SaasCatalogSku::isSendsaas()` — hecho.
4. `SendSaaSPlanProvisioner` — hecho.
5. Dispatch en `OrderPaidSubscriptionProvisioner` — hecho.
6. Producto + SKUs en `catalog_products` / `catalog_skus` — **pendiente en el panel Orvae**.

---

## 9. Checklist SendSaaS (hijo)

1. `config/orvae.php` + `config/tenant.php` — hecho.
2. HMAC + env VPS — hecho (ver `docs/vps/01-despliegue.md`).
3. Superadmin + Spatie (usuarios / roles / permisos) — seeders listos.
4. Tablas de `docs/database/01-base-de-datos.md` — **aún no** (planes, features, subscriptions).
5. `VerifyOrvaeProvisionSignature` + rutas `internal/saas` — siguiente.
6. `TenantManager` + provisioner — siguiente.
