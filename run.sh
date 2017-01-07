#/bin/bash
set -e

php /code/main.php
apache2-foreground
