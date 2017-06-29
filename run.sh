#!/usr/bin/env bash
set -e

php /appcode/main.php
apache2-foreground
