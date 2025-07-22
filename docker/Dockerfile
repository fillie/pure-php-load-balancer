FROM php:8.4-cli AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    libssl-dev \
    libpcre3-dev \
    zlib1g-dev \
    inotify-tools \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN pecl install openswoole \
    && docker-php-ext-enable openswoole \
    && docker-php-ext-install pcntl

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install watchexec
RUN curl -L https://github.com/watchexec/watchexec/releases/download/v1.23.0/watchexec-1.23.0-x86_64-unknown-linux-gnu.tar.xz \
    | tar -xJ --strip-components=1 -C /usr/local/bin watchexec-1.23.0-x86_64-unknown-linux-gnu/watchexec

# Set working directory
WORKDIR /app

# Copy composer files first for better Docker layer caching
COPY composer.json ./
COPY composer.lock* ./

# Install dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev --quiet

# Copy the rest of the application
COPY . .

# Install dev dependencies if needed (for development)
RUN composer install --no-interaction --optimize-autoloader --quiet

# Create entrypoint script to ensure dependencies are installed
RUN echo '#!/bin/bash\ncomposer install --no-interaction --optimize-autoloader --quiet\nexec "$@"' > /entrypoint.sh && \
    chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

# Start with watchexec monitoring src directory with debounce
CMD ["watchexec", "-r", "-w", "src", "-e", "php", "--debounce", "2000", "--", "php", "src/Server.php"]