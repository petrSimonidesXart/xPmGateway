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
NC='\033[0m'

echo -e "${CYAN}=== PM Gateway MCP Test Script ===${NC}"
echo ""

# Step 1: Seed test data and get the token
echo -e "${CYAN}[1/5] Setting up test data...${NC}"
TOKEN=$(php scripts/seed-test-client.php)

if [ -z "$TOKEN" ]; then
    echo -e "${RED}ERROR: Failed to create test token${NC}"
    exit 1
fi

echo -e "${GREEN}  Token: ${TOKEN:0:8}...${NC}"
echo ""

# Step 2: Call initialize (JSON-RPC)
echo -e "${CYAN}[2/5] MCP initialize...${NC}"
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

# Step 3: Call tools/list
echo -e "${CYAN}[3/5] MCP tools/list...${NC}"
LIST_RESPONSE=$(curl -s -X POST "$BASE_URL/mcp" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{
        "jsonrpc": "2.0",
        "id": 2,
        "method": "tools/list",
        "params": {}
    }')
echo "  Response: $LIST_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "  Response: $LIST_RESPONSE"
echo ""

# Step 4: Call tools/call - create_task
echo -e "${CYAN}[4/5] MCP tools/call → create_task...${NC}"
echo "  (This will wait up to 20s for hybrid response, then return 'queued')"
CREATE_RESPONSE=$(curl -s --max-time 25 -X POST "$BASE_URL/mcp" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{
        "jsonrpc": "2.0",
        "id": 3,
        "method": "tools/call",
        "params": {
            "name": "create_task",
            "arguments": {
                "title": "Testovací úkol z MCP skriptu",
                "project": "Test Project",
                "assignee": "admin",
                "due_date": "2026-04-01",
                "estimate_hours": 4
            }
        }
    }')
echo "  Response: $CREATE_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "  Response: $CREATE_RESPONSE"
echo ""

# Extract job_id from response
JOB_ID=$(echo "$CREATE_RESPONSE" | php -r '
    $json = json_decode(file_get_contents("php://stdin"), true);
    echo $json["result"]["content"][0]["text"]["job_id"]
        ?? $json["result"]["job_id"]
        ?? "";
' 2>/dev/null)

# Try to parse job_id from the text content if it's embedded as JSON string
if [ -z "$JOB_ID" ]; then
    JOB_ID=$(echo "$CREATE_RESPONSE" | php -r '
        $json = json_decode(file_get_contents("php://stdin"), true);
        $text = $json["result"]["content"][0]["text"] ?? "";
        if (is_string($text)) {
            $inner = json_decode($text, true);
            echo $inner["job_id"] ?? "";
        }
    ' 2>/dev/null)
fi

if [ -z "$JOB_ID" ]; then
    echo -e "${RED}  Could not extract job_id, skipping get_job_status${NC}"
else
    echo -e "${GREEN}  Job ID: $JOB_ID${NC}"
    echo ""

    # Step 5a: Call tools/call - get_job_status
    echo -e "${CYAN}[5/5] MCP tools/call → get_job_status...${NC}"
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

# Bonus: list_my_recent_jobs
echo -e "${CYAN}[bonus] MCP tools/call → list_my_recent_jobs...${NC}"
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

echo -e "${GREEN}=== Done ===${NC}"
