#!/usr/bin/env bash
set -e

php /code/src/run.php

if [ "$SKIP_WAIT_FOR_IT" = "" ]; then
    apache2-foreground
fi
