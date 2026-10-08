# =============================================================================
# Moodle Application Image
# =============================================================================
# Uses pre-built base image with PHP extensions for faster builds.
# Base image: moodle-base:php8.2 (rebuild when PHP deps change)
# =============================================================================

FROM asia-south1-docker.pkg.dev/org-dev-infra/services/moodle-base:php8.2

# Install Python3 + PyMuPDF for ID card PDF generation (runs as root before USER switch)
RUN apt-get update && apt-get install -y --no-install-recommends \
        python3 \
        python3-pip \
    && pip3 install --no-cache-dir --break-system-packages pymupdf \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy Moodle source with correct ownership (avoids slow chown step)
COPY --chown=www-data:www-data . /var/www/html

# Set the working directory
USER www-data
WORKDIR /var/www/html

# Expose PHP-FPM port
EXPOSE 9000

# The default command for php-fpm is already set in the base image
