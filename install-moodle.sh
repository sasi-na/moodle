#!/bin/bash

# Wait for database to be ready
echo "Waiting for database to be ready..."
sleep 5

# # Check if config.php exists and remove it to force fresh installation
# if [ -f /var/www/html/config.php ]; then
#     echo "Removing existing config.php to force fresh installation..."
#     rm -f /var/www/html/config.php
# fi

# # Check if Moodle is already installed
# if [ ! -f /var/www/html/config.php ]; then
#     echo "Installing Moodle..."
#     cd /var/www/html
    
#     # Check PHP version
#     echo "PHP Version: $(php -v | head -n 1)"
    
#     # Install Moodle using the CLI installer
#     php admin/cli/install.php \
#         --lang=en \
#         --wwwroot=http://localhost:8082 \
#         --dataroot=/var/moodledata \
#         --dbtype=mysqli \
#         --dbhost=moodle-db \
#         --dbname=moodledb \
#         --dbuser=moodleuser \
#         --dbpass=moodlepass \
#         --dbport=3306 \
#         --prefix=mdl_ \
#         --fullname="Moodle Site" \
#         --shortname="moodle" \
#         --adminuser=admin \
#         --adminpass=Admin@1234 \
#         --adminemail=admin@example.com \
#         --non-interactive \
#         --agree-license
    
#     # Check if installation was successful
#     if [ -f /var/www/html/config.php ]; then
#         echo "Moodle installation completed successfully!"
#     else
#         echo "Moodle installation failed! Creating basic config.php..."
#         cat > /var/www/html/config.php << 'EOF'
# <?php
# unset($CFG);
# global $CFG;
# $CFG = new stdClass();

# $CFG->dbtype    = 'mysqli';
# $CFG->dblibrary = 'native';
# $CFG->dbhost    = 'moodle-db';
# $CFG->dbname    = 'moodledb';
# $CFG->dbuser    = 'moodleuser';
# $CFG->dbpass    = 'moodlepass';
# $CFG->prefix    = 'mdl_';
# $CFG->dboptions = array(
#     'dbpersist' => false,
#     'dbsocket'  => false,
#     'dbport'    => '3306',
#     'dbhandlesoptions' => false,
#     'dbcollation' => 'utf8mb4_unicode_ci',
# );

# $CFG->wwwroot   = 'http://localhost:8082';
# $CFG->dataroot  = '/var/moodledata';
# $CFG->directorypermissions = 02777;
# $CFG->admin = 'admin';

# require_once(__DIR__ . '/lib/setup.php');
# EOF
#         echo "Basic config.php created."
#     fi
# else
#     echo "Moodle is already installed."
# fi

# Fix permissions
chown -R www-data:www-data /var/www/html
chown -R www-data:www-data /var/moodledata
chmod -R 755 /var/www/html
chmod -R 777 /var/moodledata

# Start Apache in foreground
echo "Starting Apache..."
exec apache2-foreground
