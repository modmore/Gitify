#!/bin/sh
set -euo pipefail

if [ "$#" -ne 1 ]; then
    set -- Gitify
fi

# first arg is a option 
if [ "${1#-}" != "$1" ]; then
    set -- Gitify "$@"
fi

# if our command is a valid gitify subcommand, let's invoke it through gitify instead
if Gitify help "$1" > /dev/null 2>&1; then
    set -- Gitify "$@"
fi

exec "$@"
