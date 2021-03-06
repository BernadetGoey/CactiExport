# Default settings for all test environments
#
# File should be renamed into 'settings.sh.default' (or similar) to reflect that these settings are the defaults used in the
# vagrant setup.

# Select which cacti version is active.
# One of '0.8.8h' or '1.1.38'.
CACTI_VERSION="1.1.38"

POLLEREXPORT_VERSION="mount"

# The list of to be installed PHP versions. All will be disabled.
#
PHP_VERSIONS="5.6 7.0 7.1 7.2"

# Select which php is active.
# One of PHP_VERSIONS
PHP_VERSION="7.2"

# The selected timezone.
TIMEZONE="Europe/Amsterdam"

# Install spine, one of 'yes' or 'no'.
WITH_SPINE="yes"

# The place where cacti will be installed.
# Don't change unless you know how to modify the apache setup, etc.
WEBROOT="/var/www/html"


