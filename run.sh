#!/usr/bin/env bash
set -e

sudo -u $DL_USER --preserve-env php /code/src/run.php

apache2-foreground
