FROM nginx:1.27-alpine

# Au démarrage, l'image officielle lit le résolveur DNS de Docker dans /etc/resolv.conf (NGINX_LOCAL_RESOLVERS),
# puis transforme les modèles de templates/ en fichiers de conf.d/ : aucune adresse IP n'est écrite en dur.
ENV NGINX_ENTRYPOINT_LOCAL_RESOLVERS=1

COPY conf/default.conf /etc/nginx/templates/default.conf.template
COPY conf/metrics.conf /etc/nginx/templates/metrics.conf.template
COPY --from=backend public /var/www/html/public

HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
	CMD wget -q -O /dev/null http://127.0.0.1/healthz
