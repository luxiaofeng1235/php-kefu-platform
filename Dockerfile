# 基于官方 PHP 8.2 CLI Alpine 镜像
FROM php:8.2-cli-alpine

# 安装系统依赖和 PHP 扩展编译依赖
RUN apk add --no-cache \
        libpng-dev libjpeg-turbo-dev freetype-dev libwebp-dev zlib-dev \
        curl-dev libxml2-dev mariadb-connector-c-dev \
        autoconf gcc g++ make bash pkgconfig icu-dev

# 安装 GD
RUN docker-php-ext-configure gd \
        --with-jpeg=/usr/include/ \
        --with-freetype=/usr/include/freetype2/ \
        --with-webp=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd

# 安装其他必需扩展
RUN docker-php-ext-install -j$(nproc) \
        curl \
        pcntl \
        pdo_mysql \
        mysqli

# 安装 PECL 扩展
RUN pecl install redis igbinary \
    && docker-php-ext-enable redis igbinary \
    && apk del autoconf gcc g++ make pkgconfig

# 设置上传限制 & 永不超时
RUN echo "upload_max_filesize=200M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=200M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time=0" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time=-1" >> /usr/local/etc/php/conf.d/uploads.ini

# 设置工作目录
WORKDIR /opt/www

# 拷贝项目文件
COPY . /opt/www

# 创建运行时目录
RUN mkdir -p /opt/www/runtime/logs \
    && chmod -R 777 /opt/www/runtime

# 安装 Composer 并使用阿里云镜像
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ \
    && composer install --no-interaction --optimize-autoloader \
    && composer dump-autoload -o

# 默认启动命令
CMD ["php", "start.php", "start"]
