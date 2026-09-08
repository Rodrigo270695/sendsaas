<?php

/**
 * Genera un secret HMAC-SHA256 (64 hex) para Orvae ↔ SendSaaS.
 *
 * Uso:
 *   php scripts/generate-orvae-hmac.php
 *
 * Copia el mismo valor en:
 *   SendSaaS: ORVAE_PROVISION_HMAC_SECRET
 *   Orvae:    SENDSAAS_PROVISION_HMAC_SECRET
 */

echo bin2hex(random_bytes(32)), PHP_EOL;
