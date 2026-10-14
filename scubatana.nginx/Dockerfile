FROM nginx:1.27-alpine

COPY conf/default.conf /etc/nginx/conf.d/default.conf
COPY --from=backend public /var/www/html/public

HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
	CMD wget -q -O /dev/null http://127.0.0.1/healthz
