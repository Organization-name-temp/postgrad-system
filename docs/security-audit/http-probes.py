"""Bounded local HTTP probes: python3 docs/security-audit/http-probes.py.

Reads public endpoints and submits one invalid login without a CSRF token.
Does not authenticate, create accounts, or change academic data.
"""
import json
import urllib.error
import urllib.request
from pathlib import Path

BASE = "http://127.0.0.1:8000"
EVIDENCE = Path(__file__).parent / "evidence"
results = []

def probe(path, headers=None, body=None):
    request = urllib.request.Request(BASE + path, headers=headers or {}, data=body)
    try:
        response = urllib.request.urlopen(request, timeout=5)
    except urllib.error.HTTPError as error:
        response = error
    payload = response.read()
    results.append({
        "path": path, "request_headers": headers or {}, "status": response.code,
        "response_headers": {key: value for key, value in response.headers.items() if key.lower() != "set-cookie"},
        "body_preview": payload[:120].decode(errors="replace"),
    })
    return response.code, payload

probe("/api/v1/me")
probe("/api/v1/me", {"Origin": "http://evil.example.test"})
status, payload = probe("/api/v1/records/verify/invalid-audit-token")
debug = json.loads(payload)
EVIDENCE.mkdir(exist_ok=True)
(EVIDENCE / "debug-disclosure.json").write_text(json.dumps({
    "status": status, "exception": debug.get("exception"), "file": debug.get("file"),
    "trace_frames": len(debug.get("trace", [])),
}, indent=2) + "\n")
probe("/api/v1/health")
probe("/.env")
probe("/api/v1/login", {
    "Content-Type": "application/json", "Accept": "application/json",
    "Origin": "http://localhost:5173",
}, json.dumps({"email": "audit-absent@example.test", "password": "wrong"}).encode())
(EVIDENCE / "http-controls.json").write_text(json.dumps(results, indent=2) + "\n")
print([(row["path"], row["status"]) for row in results])
