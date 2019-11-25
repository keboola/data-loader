#!/usr/bin/env bash
set -e

php /code/src/run.php

apache2-foreground
