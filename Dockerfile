FROM debian:jessie

# Inspiration from MAINTAINER Marc Richter <mail@marc-richter.info>
MAINTAINER Kyle Gordon <kyle@lodge.glasgownet.com>

RUN apt-get update && apt-get -y install wget git apache2 php5 php5-mysql php5-apcu php5-mcrypt php5-intl

# Use php5enmod to link /etc/php5/mods-available items to /etc/php5/apache2/conf.d/
RUN php5enmod apcu mcrypt intl pdo_mysql
RUN php5dismod mysqli mysql

#RUN rm /var/www/html/index.html && git clone https://github.com/ignacionelson/ProjectSend /var/www/html/
RUN rm /var/www/html/index.html && wget https://codeload.github.com/ignacionelson/ProjectSend/tar.gz/r756 -O /tmp/ProjectSend.tar.gz && tar -zxvf /tmp/ProjectSend.tar.gz --strip-components=1 -C /var/www/html/

# Forbid access to .git
RUN echo -e '\nRedirectMatch 404 /\.git' >> /var/www/html/.htaccess
RUN chown www-data:www-data -R /var/www/html

ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_LOG_DIR /var/log/apache2
ENV APACHE_LOCK_DIR /var/lock/apache2
ENV APACHE_PID_FILE /var/run/apache2.pid

# Add extra script
COPY docker-init docker-init
RUN chmod +x /docker-init

# Temporary diagnostics page
COPY phpinfo.php /var/www/html/info.php

EXPOSE 80
#CMD ["/usr/sbin/apache2", "-D", "FOREGROUND"]
CMD ["/docker-init"]

#VOLUME ["/app"]

#CMD ["/init"]
#COPY src/ /var/www/html/
