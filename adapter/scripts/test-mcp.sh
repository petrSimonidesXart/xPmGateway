#!/bin/bash
#
# Test script for MCP Gateway API
# Usage: ddev exec "cd /var/www/html/adapter && bash scripts/test-mcp.sh"
#
set -e

BASE_URL="http://localhost"
GREEN='\033[0;32m'
RED='\033[0;31m'
CYAN='\033[0;36m'
YELLOW='\033[0;33m'
NC='\033[0m'

echo -e "${CYAN}=== PM Gateway MCP Test Script ===${NC}"
echo ""

# Step 1: Seed test data and get the token
echo -e "${CYAN}[1/6] Setting up test data...${NC}"
TOKEN=$(php scripts/seed-test-client.php)

if [ -z "$TOKEN" ]; then
    echo -e "${RED}ERROR: Failed to create test token${NC}"
    exit 1
fi

echo -e "${GREEN}  Token: ${TOKEN:0:8}...${NC}"
echo ""

# Step 2: Call initialize (JSON-RPC)
echo -e "${CYAN}[2/6] MCP initialize...${NC}"
INIT_RESPONSE=$(curl -s -X POST "$BASE_URL/mcp" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{
        "jsonrpc": "2.0",
        "id": 1,
        "method": "initialize",
        "params": {
            "protocolVersion": "2024-11-05",
            "capabilities": {},
            "clientInfo": {"name": "test-script", "version": "1.0"}
        }
    }')
echo "  Response: $INIT_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "  Response: $INIT_RESPONSE"
echo ""

# Step 3: Call tools/list — should include PM tools + scenarios
echo -e "${CYAN}[3/6] MCP tools/list...${NC}"
LIST_RESPONSE=$(curl -s -X POST "$BASE_URL/mcp" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{
        "jsonrpc": "2.0",
        "id": 2,
        "method": "tools/list",
        "params": {}
    }')

# Count and list tools
TOOL_COUNT=$(echo "$LIST_RESPONSE" | php -r '
    $json = json_decode(file_get_contents("php://stdin"), true);
    $tools = $json["result"]["tools"] ?? [];
    echo count($tools);
' 2>/dev/null)
echo -e "  ${GREEN}Found $TOOL_COUNT tools${NC}"

echo "$LIST_RESPONSE" | php -r '
    $json = json_decode(file_get_contents("php://stdin"), true);
    $tools = $json["result"]["tools"] ?? [];
    foreach ($tools as $t) {
        $prefix = str_starts_with($t["name"], "scenario_") ? "  🔗" : "  🔧";
        echo "$prefix {$t[\"name\"]} — {$t[\"description\"]}\n";
    }
' 2>/dev/null
echo ""

# Step 4: Call tools/call → pm_login (standalone test)
echo -e "${CYAN}[4/6] MCP tools/call → pm_login...${NC}"
echo -e "${YELLOW}  (This will wait up to 20s for the worker to process)${NC}"
LOGIN_RESPONSE=$(curl -s --max-time 25 -X POST "$BASE_URL/mcp" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{
        "jsonrpc": "2.0",
        "id": 3,
        "method": "tools/call",
        "params": {
            "name": "pm_login",
            "arguments": {}
        }
    }')
echo "  Response: $LOGIN_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "  Response: $LOGIN_RESPONSE"

# Extract job_id
JOB_ID=$(echo "$LOGIN_RESPONSE" | php -r '
    $json = json_decode(file_get_contents("php://stdin"), true);
    $text = $json["result"]["content"][0]["text"] ?? "";
    if (is_string($text)) {
        $inner = json_decode($text, true);
        echo $inner["job_id"] ?? "";
    }
' 2>/dev/null)
echo ""

# Step 5: get_job_status
if [ -z "$JOB_ID" ]; then
    echo -e "${RED}  Could not extract job_id, skipping get_job_status${NC}"
else
    echo -e "${GREEN}  Job ID: $JOB_ID${NC}"
    echo ""

    echo -e "${CYAN}[5/6] MCP tools/call → get_job_status...${NC}"
    STATUS_RESPONSE=$(curl -s -X POST "$BASE_URL/mcp" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $TOKEN" \
        -d "{
            \"jsonrpc\": \"2.0\",
            \"id\": 4,
            \"method\": \"tools/call\",
            \"params\": {
                \"name\": \"get_job_status\",
                \"arguments\": {
                    \"job_id\": \"$JOB_ID\"
                }
            }
        }")
    echo "  Response: $STATUS_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "  Response: $STATUS_RESPONSE"
    echo ""
fi

# Step 6: list_my_recent_jobs
echo -e "${CYAN}[6/6] MCP tools/call → list_my_recent_jobs...${NC}"
JOBS_RESPONSE=$(curl -s -X POST "$BASE_URL/mcp" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{
        "jsonrpc": "2.0",
        "id": 5,
        "method": "tools/call",
        "params": {
            "name": "list_my_recent_jobs",
            "arguments": {
                "limit": 5
            }
        }
    }')
echo "  Response: $JOBS_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "  Response: $JOBS_RESPONSE"
echo ""

# Bonus: REST API v1 test
echo -e "${CYAN}[bonus] REST API v1 — OpenAPI spec...${NC}"
OPENAPI_RESPONSE=$(curl -s "$BASE_URL/api/v1/openapi.json" \
    -H "Authorization: Bearer $TOKEN")
ENDPOINT_COUNT=$(echo "$OPENAPI_RESPONSE" | php -r '
    $json = json_decode(file_get_contents("php://stdin"), true);
    $paths = $json["paths"] ?? [];
    echo count($paths);
' 2>/dev/null)
echo -e "  ${GREEN}OpenAPI spec has $ENDPOINT_COUNT endpoints${NC}"
echo "$OPENAPI_RESPONSE" | php -r '
    $json = json_decode(file_get_contents("php://stdin"), true);
    foreach ($json["paths"] ?? [] as $path => $methods) {
        foreach ($methods as $method => $op) {
            echo "  " . strtoupper($method) . " $path — " . ($op["summary"] ?? "") . "\n";
        }
    }
' 2>/dev/null
echo ""

echo -e "${GREEN}=== Done ===${NC}"
