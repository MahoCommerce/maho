#!/bin/bash
shopt -s globstar
shopt -s nullglob  ##: just in case there is non match for the glob.

glob=(public/skin/adminhtml/default/default/images/**/*.{png,gif,jpg})

for file in "${glob[@]}"; do
    bname=$(basename "$file")
    if git grep -q "$bname"; then
        echo "Keep:   $file"
    else
        echo "Delete: $file"
        rm "$file"
    fi
done
