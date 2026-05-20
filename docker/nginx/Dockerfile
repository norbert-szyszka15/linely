FROM nginx:1.27-alpine

COPY . /app/
RUN apk add --no-cache openssl \
    && mkdir -p /etc/nginx/certs \
    && openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout /etc/nginx/certs/linely.key \
        -out /etc/nginx/certs/linely.crt \
        -subj /CN=localhost
COPY ./docker/nginx/nginx.conf /etc/nginx/conf.d/default.conf
