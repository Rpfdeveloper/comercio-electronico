#!/usr/bin/env python3
"""Detecta filtraciones comunes en archivos versionados; no imprime su contenido."""
import pathlib
import re
import subprocess
import sys

root = pathlib.Path(__file__).resolve().parents[1]
paths = subprocess.check_output(
    ["git", "ls-files", "--cached", "--others", "--exclude-standard", "-z"], cwd=root
).decode().split("\0")
email = re.compile(r"[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+@([A-Za-z0-9.-]+\.[A-Za-z]{2,})")
token = re.compile(r"(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,}|AKIA[0-9A-Z]{16}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----)")
violations = []
count = 0
for name in sorted(set(filter(None, paths))):
    path = pathlib.PurePosixPath(name)
    if ((path.name.startswith('.env') and path.name != '.env.example') or
        path.name.startswith('sess_') or path.suffix.lower() in {'.log', '.pem', '.key', '.p12', '.pfx'} or
        any(part in {'backups', 'dumps', 'sessions', '.preview-runtime', '.runtime'} for part in path.parts)):
        violations.append((name, 'archivo local o sensible'))
        continue
    data = (root / name).read_bytes()
    if b'\0' in data:  # Recursos binarios: revisión manual, no interpretar como texto.
        continue
    count += 1
    content = data.decode('utf-8', errors='replace')
    if token.search(content):
        violations.append((name, 'posible credencial'))
    for match in email.finditer(content):
        domain = match.group(1).lower()
        if domain not in {'example.invalid', 'example.com', 'example.org', 'users.noreply.github.com'}:
            violations.append((name, 'correo fuera de los dominios de ejemplo o noreply'))
            break
for name, reason in violations:
    print(f'REVISAR {name}: {reason}', file=sys.stderr)
if violations:
    sys.exit(1)
print(f'PASS privacidad: {count} archivos de texto; sin patrones prohibidos detectados')
