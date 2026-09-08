# Despliegue VPS — SendSaaS / OmniDesk

DNS ya apuntan a `161.132.54.109`:

| Host | Tipo | Uso |
|---|---|---|
| `sendsaas.orvae.pe` | A | Superadmin / dominio central |
| `*.sendsaas.orvae.pe` | A | Cada tenant: `{slug}.sendsaas.orvae.pe` |

Orvae **solo cobra**. Features y límites viven en este hijo. Esta fase: mapear env + HMAC + superadmin (usuarios, roles, permisos). **Sin endpoints de provisión todavía.**

---

## 1. HMAC (el mismo en ambos lados)

Generar otro si quieres rotarlo:

```bash
php scripts/generate-orvae-hmac.php
```

| App | Variable |
|---|---|
| SendSaaS (este repo) | `ORVAE_PROVISION_HMAC_SECRET` |
| Orvae (`orvaepe`) | `SENDSAAS_PROVISION_HMAC_SECRET` |

Firma: `HMAC-SHA256("{unix_ts}.{raw_json_body}", secret)`.

---

## 2. Nginx (mismo server, wildcard)

```nginx
server {
    listen 80;
    server_name sendsaas.orvae.pe *.sendsaas.orvae.pe;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name sendsaas.orvae.pe *.sendsaas.orvae.pe;

    root /var/www/sendsaas/public;
    index index.php;

    # Certificado wildcard *.sendsaas.orvae.pe (+ SAN sendsaas.orvae.pe)

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

Cookie de sesión: `SESSION_DOMAIN=null` (una cookie por host, igual que VetSaaS). Así **Abrir subdominio** muestra el login de la empresa y **Salir de soporte** no tumba la sesión del panel central.

---

## 3. Primer boot en el VPS (solo lo que ya existe)

```bash
cd /var/www/sendsaas
cp .env.vps.example .env
# completar APP_KEY, DB_*, HMAC, PLATFORM_SUPERADMIN_PASSWORD
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=PermissionsSeeder --force
php artisan db:seed --class=SuperadminSeeder --force
php artisan config:cache
php artisan route:cache
```

Login superadmin: `https://sendsaas.orvae.pe/login` con `PLATFORM_SUPERADMIN_EMAIL`.

---

## 4. Orvae (padre) en el mismo VPS

En `/var/www/orvaepe/.env` (o el path real):

```env
SENDSAAS_PROVISIONING_ENABLED=true
SENDSAAS_PROVISION_URL=https://sendsaas.orvae.pe/api/internal/saas/provision
SENDSAAS_RENEW_URL=https://sendsaas.orvae.pe/api/internal/saas/renew
SENDSAAS_LOOKUP_URL=https://sendsaas.orvae.pe/api/internal/saas/lookup
SENDSAAS_PROVISION_HMAC_SECRET=<el mismo hex>
SENDSAAS_TENANT_DOMAIN=sendsaas.orvae.pe
SENDSAAS_TENANT_SCHEME=https
```

Luego `php artisan config:cache` en Orvae.

SKU de catálogo (cuando crees el producto en Orvae):

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

Planes OmniDesk: `free`, `starter`, `profesional`, `business`, `enterprise`.

Hasta que existan las rutas `/api/internal/saas/*` en SendSaaS, un pago en Orvae fallará el POST (queda logueado en `billing_snapshot`). Eso es el siguiente paso, no este.

---

## 5. Qué NO va en este paso

- Migraciones nuevas (planes, features, subscriptions, sedes, schemas `od_*`).
- Panel `/plataforma` de planes.
- Creación de tenants por subdominio.
