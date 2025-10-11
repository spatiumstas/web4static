#!/usr/bin/env bash
set -euo pipefail

HASH_LEN="${HASH_LEN:-8}"
ASSETS=${ASSETS:-"script.js styles.css functions.php icons.svg"}
TARGET_DIR="${1:-}"

cd "${TARGET_DIR}"
has_cmd() { command -v "$1" >/dev/null 2>&1; }

hash_file() {
  local file="$1"
  local raw
  if has_cmd sha256sum; then
    raw=$(sha256sum "$file" | awk '{print $1}')
  elif has_cmd shasum; then
    raw=$(shasum -a 256 "$file" | awk '{print $1}')
  elif has_cmd md5sum; then
    raw=$(md5sum "$file" | awk '{print $1}')
  elif has_cmd md5; then
    raw=$(md5 -q "$file" 2>/dev/null || md5 "$file" | awk '{print $4}')
  else
    echo "No hashing found" >&2
    exit 1
  fi
  if has_cmd base32 && has_cmd xxd; then
    printf "%s" "$raw" | xxd -r -p | base32 | tr -d '=' | tr -d '\n' | tr 'A-Z' 'a-z' | cut -c1-"${HASH_LEN}"
  else
    printf "%s" "$raw" | cut -c1-"${HASH_LEN}"
  fi
}

declare -A rename_map=()
declare -A current_map=()
shopt -s nullglob

for asset in $ASSETS; do
  if [[ -f "$asset" ]]; then
    current="$asset"
  else
    base_name="${asset%%.*}"
    ext="${asset##*.}"
    candidates=("${base_name}-"*."${ext}")
    if (( ${#candidates[@]} )); then
      current="${candidates[0]}"
    else
      continue
    fi
  fi

  base_name="${asset%%.*}"
  ext="${asset##*.}"
  file_to_hash="$current"
  file_hash=$(hash_file "$file_to_hash")
  dst="${base_name}-${file_hash}.${ext}"

  rename_map["$asset"]="$dst"
  rename_map["$current"]="$dst"
  current_map["$asset"]="$current"
done

for key in "${!rename_map[@]}"; do
  src="$key"
  dst="${rename_map[$key]}"
  if [[ -f "$src" && "$src" != "$dst" ]]; then
    mv -f "$src" "$dst"
  fi
done

if [[ -f index.php ]]; then
  sed_expr=()
  for asset in $ASSETS; do
    new_name="${rename_map[$asset]:-}"
    current_name="${current_map[$asset]:-}"
    if [[ -n "$new_name" ]]; then
      safe_base=$(printf '%s' "$asset" | sed -e 's/\./\\\./g')
      sed_expr+=( -e "s#${safe_base}#${new_name}#g" )
      if [[ -n "$current_name" && "$current_name" != "$asset" ]]; then
        safe_cur=$(printf '%s' "$current_name" | sed -e 's/\./\\\./g')
        sed_expr+=( -e "s#${safe_cur}#${new_name}#g" )
      fi
    fi
  done
  if (( ${#sed_expr[@]} )); then
    sed -i ${sed_expr[@]} index.php
  fi
fi

exit 0