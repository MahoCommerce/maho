#!/bin/bash
# SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
# SPDX-License-Identifier: OSL-3.0
#
# Run one pass of the benchmark in parallel groups: the read cases are split over the users, one
# group for each user, and the write cases run after them, in sequence, with the first user.
# Each group needs its own user: the notes of the remember tool and the conversation lookup of
# pass.sh are per user.
#
# Usage: parallel.sh <output folder> <creds file> [creds file ...] [-- case name ...]
# Environment: the same as pass.sh, without CREDS.

set -u
SK=$(cd "$(dirname "$0")" && pwd)
OUT=$1
shift
CREDS_FILES=()
while [ $# -gt 0 ] && [ "$1" != "--" ]; do CREDS_FILES+=("$1"); shift; done
[ "${1:-}" = "--" ] && shift
ONLY=" $* "
pick() { [ "$ONLY" = "  " ] || [[ "$ONLY" == *" $1 "* ]]; }

READS=()
for name in $(grep -oE '^run [a-z0-9-]+' "$SK/pass.sh" | cut -d' ' -f2); do pick "$name" && READS+=("$name"); done
WRITES=()
for name in $(grep -oE '^write [a-z0-9-]+' "$SK/pass.sh" | cut -d' ' -f2); do pick "$name" && WRITES+=("$name"); done

N=${#CREDS_FILES[@]}
for ((g = 0; g < N; g++)); do
    group=()
    for ((i = g; i < ${#READS[@]}; i += N)); do group+=("${READS[$i]}"); done
    [ ${#group[@]} -gt 0 ] && CREDS=${CREDS_FILES[$g]} "$SK/pass.sh" "$OUT" "${group[@]}" > /dev/null &
    # On SQLite, two turns that create a conversation in the same second fail with "database is locked".
    sleep 5
done
wait
[ ${#WRITES[@]} -gt 0 ] && CREDS=${CREDS_FILES[0]} "$SK/pass.sh" "$OUT" "${WRITES[@]}" > /dev/null
echo "PASS-DONE $OUT"
