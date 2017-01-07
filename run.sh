#/bin/bash
set -e

php /appcode/main.php
apache2-foreground
