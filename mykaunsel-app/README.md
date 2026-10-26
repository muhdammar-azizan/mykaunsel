# MyKaunsel mobile app

Flutter app for individuals (Android first, iOS ready). Architecture and rules: [`../docs/APP_ARCHITECTURE.md`](../docs/APP_ARCHITECTURE.md). The server's API: [`../docs/API.md`](../docs/API.md).

## Run

Start the Laravel server on your computer (`php artisan serve`, from `mykaunsel-web`), then:

```
flutter pub get
flutter run --flavor dev --dart-define-from-file=env/dev.json
```

`env/dev.json` points at `http://10.0.2.2:8000`, which is how the **Android emulator** sees your computer. On a **real phone** copy it and use your computer's address on the same Wi-Fi (for example `http://192.168.1.20:8000`, with `php artisan serve --host=0.0.0.0`).

## Check

```
flutter analyze
flutter test
```

## Icon and splash

Made from the logo by `tool/generate_brand_assets.py`, `tool/generate_icons.py` and `dart run flutter_native_splash:create` (see `docs/APP_ARCHITECTURE.md`). Run them again only when the logo changes.

## After changing a model or a text

```
dart run build_runner build --delete-conflicting-outputs   # freezed / json models
flutter gen-l10n                                            # texts in lib/l10n/*.arb
```

The generated files are committed, so a fresh clone runs without these.
