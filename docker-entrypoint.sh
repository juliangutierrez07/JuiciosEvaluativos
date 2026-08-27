#!/bin/sh
set -eu

php /var/www/html/database/migrate.php
exec apache2-foreground

