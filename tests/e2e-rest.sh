#!/usr/bin/env bash
#
# E2E REST API test script for PM Gateway.
#
# Usage:
#   E2E_API_URL=https://gateway.example.com E2E_API_TOKEN=your-token ./tests/e2e-rest.sh
#
# Requirements:
#   - Running adapter (DDEV or production)
#   - Valid API token with at least one permitted tool
#
set -euo pipefail

: "${E2E_API_URL:?Set E2E_API_URL (e.g. https://gateway.example.com)}"
: "${E2E_API_TOKEN:?Set E2E_API_TOKEN to a valid Bearer token}"

PASS=0
FAIL=0

check() {
    local name="$1"
    local expected_code="$2"
    local actual_code="$3"

    if [ "$actual_code" = "$expected_code" ]; then
        echo "  ✓ $name (HTTP $actual_code)"
        PASS=$((PASS + 1))
    else
        echo "  ✗ $name — expected HTTP $expected_code, got $actual_code"
        FAIL=$((FAIL + 1))
    fi
}

echo "=== PM Gateway E2E REST API Tests ==="
echo "URL: $E2E_API_URL"
echo ""

# --- Authentication ---
echo "Authentication:"

code=$(curl -s -o /dev/null -w "%{http_code}" "$E2E_API_URL/api/v1/jobs")
check "GET /api/v1/jobs without token → 401" "401" "$code"

code=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "Authorization: Bearer invalid-token-xxx" \
    "$E2E_API_URL/api/v1/jobs")
check "GET /api/v1/jobs with invalid token → 401" "401" "$code"

# --- OpenAPI spec ---
echo ""
echo "OpenAPI:"

response=$(curl -s -w "\n%{http_code}" \
    -H "Authorization: Bearer $E2E_API_TOKEN" \
    "$E2E_API_URL/api/v1/openapi.json")
code=$(echo "$response" | tail -1)
body=$(echo "$response" | sed '$d')
check "GET /api/v1/openapi.json → 200" "200" "$code"

if echo "$body" | grep -q '"openapi"'; then
    echo "  ✓ Response contains OpenAPI spec"
    PASS=$((PASS + 1))
else
    echo "  ✗ Response does not contain OpenAPI spec"
    FAIL=$((FAIL + 1))
fi

# --- Job listing ---
echo ""
echo "Job listing:"

response=$(curl -s -w "\n%{http_code}" \
    -H "Authorization: Bearer $E2E_API_TOKEN" \
    "$E2E_API_URL/api/v1/jobs?limit=5")
code=$(echo "$response" | tail -1)
body=$(echo "$response" | sed '$d')
check "GET /api/v1/jobs?limit=5 → 200" "200" "$code"

if echo "$body" | grep -q '"jobs"'; then
    echo "  ✓ Response contains jobs array"
    PASS=$((PASS + 1))
else
    echo "  ✗ Response does not contain jobs array"
    FAIL=$((FAIL + 1))
fi

# --- Job status for non-existent job ---
echo ""
echo "Job status:"

code=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "Authorization: Bearer $E2E_API_TOKEN" \
    "$E2E_API_URL/api/v1/jobs/00000000-0000-0000-0000-000000000000")
check "GET /api/v1/jobs/{nonexistent} → 404" "404" "$code"

# --- Method not allowed ---
echo ""
echo "Method enforcement:"

code=$(curl -s -o /dev/null -w "%{http_code}" -X GET \
    -H "Authorization: Bearer $E2E_API_TOKEN" \
    "$E2E_API_URL/api/v1/tools/create_task")
check "GET /api/v1/tools/create_task → 405" "405" "$code"

# --- Summary ---
echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="

if [ "$FAIL" -gt 0 ]; then
    exit 1
fi
