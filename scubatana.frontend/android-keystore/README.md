# Clés de signature Android

`scubanion-dev.keystore` est la clé dev, versionnée pour que toute l'équipe puisse installer des builds release sur son téléphone (`npm run adb:install`). Le dépôt étant public, elle ne doit pas signer l'APK publié.

## Clé de release

Le pipeline GitLab (`build:android`) signe l'APK avec la clé dev du dépôt, sauf si la variable `ANDROID_RELEASE_KEYSTORE_BASE64` est définie dans *Settings > CI/CD > Variables* (masquée et protégée). Une variable protégée n'est transmise qu'aux branches et tags protégés : protéger aussi les tags `v*` (*Settings > Repository > Protected tags*), sinon les pipelines de release signent avec la clé dev. Pour la créer, une seule fois, hors du dépôt :

```bash
keytool -genkeypair -v -keystore scubanion-release.keystore -alias scubanion -keyalg RSA -keysize 2048 -validity 10000
base64 -w0 scubanion-release.keystore   # valeur de ANDROID_RELEASE_KEYSTORE_BASE64
```

Mettre aussi à jour `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS` et `ANDROID_KEY_PASSWORD` avec ceux de cette clé, et la ranger en lieu sûr : la perdre empêche de publier une mise à jour de l'app. Un APK signé avec une clé ne remplace pas une app installée avec l'autre : désinstaller avant de passer de l'une à l'autre.
