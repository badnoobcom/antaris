FROM debian:latest
USER root
EXPOSE 2002
RUN echo 'deb http://ftp.de.debian.org/debian/ jessie-updates main' >> /etc/apt/sources.list\
 && echo 'deb-src http://ftp.de.debian.org/debian/ jessie-updates main' >> /etc/apt/sources.list\
 && echo 'deb http://packages.dotdeb.org jessie all' >> /etc/apt/sources.list\
 && echo 'deb-src http://packages.dotdeb.org jessie all' >> /etc/apt/sources.list
RUN apt-key adv --keyserver keys.gnupg.net --recv-keys E9C74FEEA2098A6E
RUN apt-get update
RUN apt-get install -y --show-progress --no-install-recommends\
	apache2\
	libapache2-mod-jk\
	imagemagick\
	php7.0\
	php7.0-imagick
RUN echo '<IfModule jk_module>\n\
	Listen 2002\n\
</IfModule>' >> /etc/apache2/ports.conf

ADD resources/defaultApacheConfig.conf /etc/apache2/sites-available/000-default.conf
CMD service apache2 start && \
    cd /www/antaris && \
    ./start-service.sh
