FROM eclipse-mosquitto:2

COPY conf/mosquitto.conf /mosquitto/config/mosquitto.conf
COPY --chmod=755 demarrer.sh /usr/local/bin/demarrer.sh

CMD ["/usr/local/bin/demarrer.sh"]

HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
	CMD mosquitto_sub -h 127.0.0.1 -p 1883 -u "$MQTT_USERNAME" -P "$MQTT_PASSWORD" -t '$SYS/broker/version' -C 1 -W 2 > /dev/null
