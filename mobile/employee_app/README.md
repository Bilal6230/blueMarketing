# BlueMarketing Employee App

Flutter starter targeting the existing Laravel `/api/v1/mobile` endpoints.

## Run

Install Flutter SDK, then:

```powershell
cd mobile\employee_app
flutter create --platforms=android .
flutter pub get
flutter test
flutter run --dart-define=API_BASE_URL=https://YOUR-HTTPS-DOMAIN
```

Never commit credentials or production tokens. Use HTTPS for the API host.
Mobile app authorization is only for UI visibility: Laravel must enforce all permissions server-side.

## Phase status

- Implemented: login/logout, stored Sanctum token, rehydrate session, initial permission-filtered navigation.
- Pending: module screens/data wiring, offline behavior, token revocation UX, push notifications, project switching, end-to-end testing.
- Permissions map is provisional and must be reconciled with controller-specific checks before production.
- `flutter create` generates the platform scaffolding locally; it is not yet committed.
