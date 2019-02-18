FROM alpine:latest

RUN apk update
RUN apk add busybox-extras php-cli php-json
ADD . /app
RUN chmod +x /app/fake-server.php
ADD inetd.conf /etc/inetd.conf
CMD /usr/sbin/inetd && tail -f /dev/null