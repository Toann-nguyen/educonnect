#!/bin/bash
# Migrate 4 Auth Request classes từ namespace cũ sang Domains\Identity namespace
set -euo pipefail
cd /home/robert/educonnect

mkdir -p app/Domains/Identity/Http/Requests/Auth

for f in LoginRequest RegisterRequest ForgotPasswordRequest ResetPasswordRequest; do
  src="app/Http/Requests/Auth/$f.php"
  dst="app/Domains/Identity/Http/Requests/Auth/$f.php"
  if [ ! -f "$src" ]; then
    echo "SKIP $f — source không tồn tại: $src"
    continue
  fi
  sed 's|^namespace App\\Http\\Requests\\Auth;|namespace App\\Domains\\Identity\\Http\\Requests\\Auth;|' "$src" > "$dst"
  ns=$(grep '^namespace' "$dst")
  echo "OK $f -> $ns"
done

echo "=== verify: không còn MISS ==="
for u in $(grep -rh 'use App\\Domains\\Identity\\Http\\Requests' app/ --include='*.php' | sed 's/.*use //;s/;//' | sort -u); do
  dir=$(echo "$u" | sed 's|App\\|app/|;s|\\|/|g')
  if [ -f "$dir.php" ]; then echo "OK   $u"; else echo "MISS $u"; fi
done
