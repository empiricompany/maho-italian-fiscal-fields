#!/usr/bin/env bash
#
# SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
# SPDX-License-Identifier: AFL-3.0
#
# e2e-api-check.sh — End-to-end verification of the Italian fiscal fields
# (cod_fisc / email_pec / cod_sdi) across ALL Maho API types, run against the
# CURRENT harbor installation (read-only: no data is created or modified).
#
# Usage (from the harbor project root, e.g. ~/maho_harbor):
#   bash localdev/maho-italian-fiscal-fields/tests/e2e-api-check.sh
#
# All parameters are optional environment variables:
#   BASE_URL          Store base URL          (default: https://localhost:8443)
#   CLIENT_ID         OAuth2 client id        (default: the harbor dev client)
#   CLIENT_SECRET     OAuth2 client secret    (default: the harbor dev secret)
#   ORDER_ENTITY_ID   Order entity id for REST v2      (default: 1)
#   ORDER_INCREMENT   Order increment_id for legacy   (default: 100000001)
#   ADDRESS_ID        Customer address id for legacy   (default: 11)
#   CUSTOMER_EMAIL    Email for the customer listing   (default: luca.bianchi@example.com)
#   SOAP_USER         Legacy SOAP/XML-RPC/JSON-RPC user  (no default: legacy tests SKIP)
#   SOAP_APIKEY       Legacy SOAP/XML-RPC/JSON-RPC api key
#   HARBOR            harbor CLI path          (default: ./vendor/bin/harbor)
#
# Test map (each section documents what it verifies and why):
#   T1  REST v2  OAuth2 token (client_credentials grant, /api/rest/v2/auth/token)
#   T2  REST v2  GET /orders/{id} -> billingAddress.extensions.italianFiscalFields
#                (populated by the module observer on api_order_dto_build)
#   T3  REST v2  GET /customers/{id} -> addresses[*].extensions
#                KNOWN CORE GAP: CustomerProvider::mapToDto() does not dispatch
#                api_customer_dto_build (only the search path does). Expected
#                to FAIL until the upstream PR lands. Reported as KNOWN-GAP.
#   T4  REST v2  GET /customers?email=... (listing/search path, which DOES
#                dispatch the event, but loads the default billing address
#                with a restricted addAttributeToSelect). Expected KNOWN-GAP.
#   T5  SOAP v2  /api/v2_soap?wsdl=1 complexTypes contain the 3 elements
#                (module etc/wsdl.xml merged by node name — replaces the
#                legacy core patch of Mage/Sales/etc/wsdl.xml)
#   T6  SOAP v2  salesOrderInfo call: billing_address carries the 3 fields
#                (the SoapServer filters output on the wsdl complexType, so
#                this proves both the wsdl merge AND the data flow)
#   T7  SOAP v1  /api/soap login + sales_order.info: billing_address carries
#                the 3 fields (v1 passes ALL model data, no wsdl filter)
#   T8  JSON-RPC /api/jsonrpc login + sales_order.info + customer_address.info
#   T9  XML-RPC  /api/xmlrpc  login + sales_order.info (python xmlrpc.client)
#   T10 WSI      Reports whether WS-I compliance mode is enabled; when it is,
#                the wsdl served is the wsi.xml one (module etc/wsi.xml) and
#                the 3 elements must be present there too.
#   T11 api2     Legacy REST (/api/rest) uses OAuth 1.0a (3-legged): not
#                covered here by design. Coverage of the customer_address
#                resource attributes is proven by the SQLite suite (test A6).
#
# Exit code: 0 if all non-KNOWN-GAP tests pass, 1 otherwise.

set -u

BASE_URL="${BASE_URL:-https://localhost}"
CLIENT_ID="${CLIENT_ID:-maho_347dbfaa807abfc7575331d313ea51a1}"
CLIENT_SECRET="${CLIENT_SECRET:-1051ef40808303a05eb1b3f62d4ee757018379acc32ff349f637b0dcfd7211dd}"
ORDER_ENTITY_ID="${ORDER_ENTITY_ID:-1}"
ORDER_INCREMENT="${ORDER_INCREMENT:-100000001}"
ADDRESS_ID="${ADDRESS_ID:-11}"
CUSTOMER_EMAIL="${CUSTOMER_EMAIL:-luca.bianchi@example.com}"
SOAP_USER="${SOAP_USER:-}"
SOAP_APIKEY="${SOAP_APIKEY:-}"
HARBOR="${HARBOR:-./vendor/bin/harbor}"

PASS=0; FAIL=0; SKIP=0; KNOWN_GAP=0

ok()   { printf '  \033[32mPASS\033[0m %s\n' "$1"; PASS=$((PASS+1)); }
ko()   { printf '  \033[31mFAIL\033[0m %s\n' "$1"; FAIL=$((FAIL+1)); }
skip() { printf '  \033[33mSKIP\033[0m %s\n' "$1"; SKIP=$((SKIP+1)); }
gap()  { printf '  \033[35mKNOWN-GAP\033[0m %s\n' "$1"; KNOWN_GAP=$((KNOWN_GAP+1)); }
hdr()  { printf '\n\033[1m== %s\033[0m\n' "$1"; }

###############################################################################
hdr "T1 — REST v2: OAuth2 token (client_credentials, POST /api/rest/v2/auth/token)"
TOKEN=$(curl -sk -X POST "$BASE_URL/api/rest/v2/auth/token" \
    -H 'Content-Type: application/json' \
    -d "{\"grant_type\":\"client_credentials\",\"client_id\":\"$CLIENT_ID\",\"client_secret\":\"$CLIENT_SECRET\"}" \
    | python3 -c 'import sys,json;print(json.load(sys.stdin).get("token",""))' 2>/dev/null)
if [ -n "$TOKEN" ]; then
    ok "token issued"
else
    ko "no token: check CLIENT_ID/CLIENT_SECRET and that REST v2 protocol is enabled"
    echo "    (without the token T2-T4 cannot run)"
fi

###############################################################################
if [ -n "$TOKEN" ]; then
    hdr "T2 — REST v2: GET /orders/$ORDER_ENTITY_ID -> billingAddress.extensions"
    BODY=$(curl -sk -H "Authorization: Bearer $TOKEN" "$BASE_URL/api/rest/v2/orders/$ORDER_ENTITY_ID")
    EXT=$(printf '%s' "$BODY" | python3 -c 'import sys,json;print(json.dumps(json.load(sys.stdin).get("billingAddress",{}).get("extensions",{})))' 2>/dev/null)
    if printf '%s' "$EXT" | grep -q italianFiscalFields; then
        ok "billingAddress.extensions = $EXT"
    else
        ko "extensions missing or empty: $EXT"
    fi

    hdr "T3 — REST v2: GET /customers (item path) -> addresses[*].extensions"
    echo "  NOTE: CustomerProvider::mapToDto() does not dispatch api_customer_dto_build"
    echo "        (core gap documented in plans/italian-fiscal-fields-api-implementation.md Task E.3)"
    BODY=$(curl -sk -H "Authorization: Bearer $TOKEN" "$BASE_URL/api/rest/v2/customers?email=$CUSTOMER_EMAIL")
    CID=$(printf '%s' "$BODY" | python3 -c 'import sys,json;print(json.load(sys.stdin)["member"][0]["id"])' 2>/dev/null)
    EXT=$(curl -sk -H "Authorization: Bearer $TOKEN" "$BASE_URL/api/rest/v2/customers/$CID" \
        | python3 -c 'import sys,json;d=json.load(sys.stdin);print(json.dumps([a.get("extensions") for a in d.get("addresses",[])]))' 2>/dev/null)
    if printf '%s' "$EXT" | grep -q italianFiscalFields; then
        ok "addresses extensions = $EXT (upstream PR landed?)"
    else
        gap "item path has no api_customer_dto_build dispatch -> $EXT"
    fi

    hdr "T4 — REST v2: GET /customers?email=... (search path, event IS dispatched)"
    echo "  NOTE: the search path loads the default billing address with a restricted"
    echo "        addAttributeToSelect, so fiscal values are not selected -> KNOWN-GAP"
    EXT=$(printf '%s' "$BODY" | python3 -c 'import sys,json;print(json.dumps(json.load(sys.stdin)["member"][0].get("defaultBillingAddress",{}).get("extensions",{})))' 2>/dev/null)
    if printf '%s' "$EXT" | grep -q italianFiscalFields; then
        ok "defaultBillingAddress.extensions = $EXT"
    else
        gap "search-path address select excludes custom attributes -> $EXT"
    fi
fi

###############################################################################
hdr "T5 — SOAP v2: wsdl complexTypes contain the fiscal elements"
WSDL=$(curl -sk "$BASE_URL/api/v2_soap/?wsdl=1")
if printf '%s' "$WSDL" | grep -q 'name="cod_fisc"'; then
    ok "wsdl carries cod_fisc/email_pec/cod_sdi (complexType merge from etc/wsdl.xml)"
else
    ko "cod_fisc not found in wsdl (module etc/wsdl.xml not merged? flush the api wsdl cache)"
fi

###############################################################################
hdr "T10 — WSI: WS-I compliance mode report"
WSI_ON=$($HARBOR exec php -r 'require "vendor/autoload.php"; Mage::app("admin"); echo Mage::helper("api")->isComplianceWSI() ? "1" : "0";' 2>/dev/null)
if [ "$WSI_ON" = "1" ]; then
    if printf '%s' "$WSDL" | grep -q 'name="cod_fisc"'; then
        ok "WSI mode ON and wsi complexTypes carry the fiscal elements"
    else
        ko "WSI mode ON but elements missing: flush the wsdl cache after enabling"
    fi
else
    skip "WS-I compliance OFF (enable in admin + flush wsdl cache to test the wsi.xml merge)"
fi

###############################################################################
# Legacy protocols need a SOAP/XML-RPC user (System > Web Services > Users)
if [ -z "$SOAP_USER" ] || [ -z "$SOAP_APIKEY" ]; then
    hdr "T6-T9 — Legacy protocols (SOAP v1/v2, JSON-RPC, XML-RPC)"
    skip "set SOAP_USER and SOAP_APIKEY (admin: System > Web Services > Users) to run these"
else
    hdr "T8 — JSON-RPC: login + sales_order.info + customer_address.info"
    SESS=$(curl -sk "$BASE_URL/api/jsonrpc" -H 'Content-Type: application/json' \
        -d "{\"jsonrpc\":\"2.0\",\"method\":\"login\",\"params\":[\"$SOAP_USER\",\"$SOAP_APIKEY\"],\"id\":1}" \
        | python3 -c 'import sys,json;print(json.load(sys.stdin).get("result",""))' 2>/dev/null)
    if [ -n "$SESS" ]; then
        ok "login ok (session issued)"
        ORDER_JSON=$(curl -sk "$BASE_URL/api/jsonrpc" -H 'Content-Type: application/json' \
            -d "{\"jsonrpc\":\"2.0\",\"method\":\"call\",\"params\":[\"$SESS\",\"sales_order.info\",[\"$ORDER_INCREMENT\"]],\"id\":2}")
        BA=$(printf '%s' "$ORDER_JSON" | python3 -c 'import sys,json;print(json.dumps(json.load(sys.stdin)["result"]["billing_address"]))' 2>/dev/null)
        if printf '%s' "$BA" | grep -q '"cod_fisc"'; then
            ok "sales_order.info billing_address carries the fiscal fields"
        else
            ko "sales_order.info billing_address missing fiscal fields"
        fi
        ADDR_JSON=$(curl -sk "$BASE_URL/api/jsonrpc" -H 'Content-Type: application/json' \
            -d "{\"jsonrpc\":\"2.0\",\"method\":\"call\",\"params\":[\"$SESS\",\"customer_address.info\",[\"$ADDRESS_ID\"]],\"id\":3}")
        if printf '%s' "$ADDR_JSON" | grep -q '"cod_fisc"'; then
            ok "customer_address.info carries the fiscal fields"
        else
            ko "customer_address.info missing fiscal fields"
        fi
    else
        ko "login failed: check SOAP_USER/SOAP_APIKEY and the user role resources"
    fi

    hdr "T9 — XML-RPC: login + sales_order.info (python xmlrpc.client)"
    XR=$(python3 - "$BASE_URL/api/xmlrpc" "$SOAP_USER" "$SOAP_APIKEY" "$ORDER_INCREMENT" <<'PYEOF'
import sys, ssl, xmlrpc.client
base, user, key, inc = sys.argv[1:5]
# Self-signed dev certificate: skip TLS verification (curl -k equivalent)
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
try:
    s = xmlrpc.client.ServerProxy(base, context=ctx)
    sess = s.login(user, key)
    info = s.call(sess, 'sales_order.info', [inc])
    ba = info.get('billing_address', {})
    sys.exit(0 if 'cod_fisc' in ba else 1)
except Exception as e:
    print(f'  xmlrpc error: {e}', file=sys.stderr)
    sys.exit(2)
PYEOF
)
    case $? in
        0) ok "sales_order.info billing_address carries the fiscal fields" ;;
        *) ko "xmlrpc sales_order.info failed (see stderr above)" ;;
    esac

    hdr "T6/T7 — SOAP v2 + SOAP v1: salesOrderInfo / sales_order.info via PHP SoapClient"
    # NOTE: run with the HOST php (ext-soap), not inside the container: the
    # container's localhost does not resolve to the web server.
    # KNOWN CORE GAP (plan Task E.4): the server-side SoapServer loads its own
    # wsdl BY URL (not the in-memory XML): on non-standard ports getHttpHost()
    # trims the port, and even on the right port the self-signed dev
    # certificate makes the server-side libxml fetch fail -> every SOAP v1/v2
    # call answers "Couldn't load from ... ?wsdl=1". Reported as KNOWN-GAP.
    cat > var/e2e-soap-check.php <<PHPEOF
<?php
// Generated by e2e-api-check.sh — safe to delete.
\$base = '$BASE_URL';
\$user = '$SOAP_USER';
\$key  = '$SOAP_APIKEY';
\$inc  = '$ORDER_INCREMENT';
// Self-signed dev certificate: skip TLS verification (curl -k equivalent)
\$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
\$opts = ['cache_wsdl' => WSDL_CACHE_NONE, 'stream_context' => \$ctx, 'exceptions' => true];
\$run = function () use (\$base, \$user, \$key, \$inc, \$opts) {
    // SOAP v2: output filtered on the wsdl complexType -> proves the wsdl merge
    try {
        \$v2 = new SoapClient(\$base . '/api/v2_soap/?wsdl=1', \$opts + ['location' => \$base . '/api/v2_soap/']);
        \$sess = \$v2->login(\$user, \$key);
        \$order = \$v2->salesOrderInfo(\$sess, \$inc);
        \$ba = \$order->billing_address ?? null;
        echo (isset(\$ba->cod_fisc) ? "V2OK\n" : "V2MISS\n");
    } catch (Throwable \$e) {
        echo "V2ERR: ", \$e->getMessage(), "\n";
    }
    // SOAP v1: passes ALL model data (no wsdl filter)
    try {
        \$v1 = new SoapClient(\$base . '/api/soap/?wsdl=1', \$opts + ['location' => \$base . '/api/soap/']);
        \$sess = \$v1->login(\$user, \$key);
        \$info = \$v1->call(\$sess, 'sales_order.info', \$inc);
        \$ba = \$info['billing_address'] ?? [];
        echo (isset(\$ba['cod_fisc']) ? "V1OK\n" : "V1MISS\n");
    } catch (Throwable \$e) {
        echo "V1ERR: ", \$e->getMessage(), "\n";
    }
    return 0;
};
exit(\$run());
PHPEOF
    SOAPRES=$(php var/e2e-soap-check.php 2>&1)
    rm -f var/e2e-soap-check.php
    case "$SOAPRES" in
        *V2OK*) ok "SOAP v2 salesOrderInfo: billing_address->cod_fisc present (wsdl merge + data)" ;;
        *"Couldn't load from"*) gap "SOAP v2 blocked by core gap E.4 (server-side SoapServer loads its wsdl by URL; self-signed cert); the wsdl merge itself is proven by T5" ;;
        *V2MISS*) ko "SOAP v2 salesOrderInfo: cod_fisc missing (wsdl cache stale?)" ;;
        *V2ERR*) ko "SOAP v2 call errored (check user role: sales/order)" ;;
        *) ko "SOAP v2 check produced no result: $SOAPRES" ;;
    esac
    case "$SOAPRES" in
        *V1OK*) ok "SOAP v1 sales_order.info: billing_address cod_fisc present" ;;
        *"Couldn't load from"*) gap "SOAP v1 blocked by the same core gap E.4; v1 data flow is proven by T8/T9 (same handlers)" ;;
        *V1MISS*) ko "SOAP v1 sales_order.info: cod_fisc missing" ;;
        *V1ERR*) ko "SOAP v1 call errored (check user role: sales/order)" ;;
        *) ko "SOAP v1 check produced no result" ;;
    esac
fi

###############################################################################
hdr "T11 — Legacy REST api2 (/api/rest): OAuth 1.0a"
skip "3-legged OAuth 1.0a by design; attribute coverage proven by the SQLite suite (test A6: customer_address EAV attributes visible and not excluded in api2)"

###############################################################################
printf '\n\033[1mSummary\033[0m: %d pass, %d fail, %d known-gap, %d skipped\n' "$PASS" "$FAIL" "$KNOWN_GAP" "$SKIP"
[ "$FAIL" -eq 0 ]
