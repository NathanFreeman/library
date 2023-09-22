FROM phpswoole/php:8.2

RUN apt update  \
    && apt install -y libaio-dev \
    && apt install -y libaio1 \
    && wget -nv https://download.oracle.com/otn_software/linux/instantclient/instantclient-basiclite-linuxx64.zip \
    && unzip instantclient-basiclite-linuxx64.zip && rm instantclient-basiclite-linuxx64.zip \
    && wget -nv https://download.oracle.com/otn_software/linux/instantclient/instantclient-sdk-linuxx64.zip \
    && unzip instantclient-sdk-linuxx64.zip && rm instantclient-sdk-linuxx64.zip \
    && mv instantclient_*_* ./instantclient \
    && rm ./instantclient/sdk/include/ldap.h \
    && echo DISABLE_INTERRUPT=on > ./instantclient/network/admin/sqlnet.ora \
    && mv ./instantclient /usr/local/ \
    && echo '/usr/local/instantclient' > /etc/ld.so.conf.d/oracle-instantclient.conf \
    && ldconfig \
    && ls -al /usr/local/instantclient \
    && export ORACLE_HOME=instantclient,/path/to/instant/client/lib \
    && apt install -y sqlite3 libsqlite3-dev libpq-dev \
    && pecl update-channels \
    && docker-php-ext-enable redis \
    && docker-php-ext-install mysqli \
    && docker-php-ext-enable mysqli \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-enable pdo_mysql \
    && docker-php-ext-install pdo_pgsql \
    && docker-php-ext-enable pdo_pgsql \
    && docker-php-ext-install pdo_oci \
    && docker-php-ext-enable pdo_oci \
    && docker-php-ext-install pdo_sqlite \
    && docker-php-ext-enable pdo_sqlite \
    && git clone https://github.com/swoole/swoole-src.git \
    && cd ./swoole-src \
    && phpize \
    && ./configure --enable-openssl \
                   --enable-sockets \
                   --enable-mysqlnd \
                   --enable-swoole-curl \
                   --enable-cares \
                   --enable-swoole-pgsql \
                   --with-swoole-oracle=instantclient,/usr/local/instantclient \
                   --enable-swoole-sqlite \
    && make -j$(cat /proc/cpuinfo | grep processor | wc -l) \
    && make install \
    && docker-php-ext-enable swoole \
    && php -m

RUN echo "swoole.enable_library=off" >> /usr/local/etc/php/conf.d/docker-php-ext-swoole.ini && \
    { \
        echo '[supervisord]'; \
        echo 'user = root'; \
        echo ''; \
        echo '[program:wordpress]'; \
        echo 'command = php /var/www/examples/fastcgi/proxy/wordpress.php'; \
        echo 'user = root'; \
        echo 'autostart = true'; \
        echo 'stdout_logfile=/proc/self/fd/1'; \
        echo 'stdout_logfile_maxbytes=0'; \
        echo 'stderr_logfile=/proc/self/fd/1'; \
        echo 'stderr_logfile_maxbytes=0'; \
    } > /etc/supervisor/service.d/wordpress.conf
