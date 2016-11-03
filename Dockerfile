FROM debian:jessie

# Inspiration from MAINTAINER Marc Richter <mail@marc-richter.info>
MAINTAINER Kyle Gordon <kyle@lodge.glasgownet.com>

RUN apt update && apt -y install git apache2 php5 php5-mysql php5-apcu php5-mcrypt php5-intl

# Enable mysql extension, disable PDO and mysqli
#RUN sed -i'' 's#^;\(extension=mysql.so.*$\)#\1#g' /etc/php/php.ini
#RUN sed -i'' 's#^\(extension=mysqli.so.*$\)#;\1#g' /etc/php/php.ini
#RUN sed -i'' 's#^\(extension=pdo_mysql.so.*$\)#;\1#g' /etc/php/php.ini

# Use php5enmod to link /etc/php5/mods-available items to /etc/php5/apache2/conf.d/
# FIXME See above about disabling or enabling mysql php extensions
RUN php5enmod apcu mysql mysqli pdo_mysql mcrypt intl

# Add extra script
#ADD assets/init /extra/init

#VOLUME ["/app"]
EXPOSE 80

#CMD ["/init"]
#COPY src/ /var/www/html/

RUN rm /var/www/html/index.html && git clone https://github.com/ignacionelson/ProjectSend /var/www/html/

ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_LOG_DIR /var/log/apache2
ENV APACHE_LOCK_DIR /var/lock/apache2
ENV APACHE_PID_FILE /var/run/apache2.pid

EXPOSE 80
CMD ["/usr/sbin/apache2", "-D", "FOREGROUND"]
