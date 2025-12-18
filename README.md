# Rozkroje

Aplikacja do dzielenia arkuszy XLS/XLSX na osobne pliki według typu materiału. Backend: Laravel + Passport (OAuth2), frontend: Vue 3 + Vite. API zwraca dane w formacie JSON.

## Wymagania

- PHP >= 8.2 z rozszerzeniami `zip`, `fileinfo`
- Composer
- Node.js >= 18 + npm
- MySQL (domyślnie baza `rozkroje`)

## Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Ustaw w `.env` dane MySQL (DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD).

Zainstaluj tabelki i dane startowe (użytkownicy, typy materiałów, przykładowy projekt):

```bash
php artisan migrate --seed
php artisan passport:install
php artisan storage:link
```

Uruchom serwer API (domyślnie `http://localhost:8000`):

```bash
php artisan serve
```

## Frontend (Vue 3 + Vite)

```bash
cd frontend
npm install
```

Skonfiguruj adres API w `.env` frontendu:

```
VITE_API_URL=http://localhost:8000/api
```

Start środowiska developerskiego:

```bash
npm run dev
```

## Uwierzytelnianie

OAuth2 przez Laravel Passport. Endpoint logowania: `POST /api/auth/login` (email, password). Token Bearer zapisywany w lokalnej pamięci frontendu.

Kontroler: `App\Http\Controllers\AuthController`. Model `User` korzysta z `HasApiTokens`. Dla kont administracyjnych pole `is_admin = true`.

Konta startowe po seederze:
- Administrator: `admin@example.com` / `admin123`
- Użytkownik: `user@example.com` / `password123`

## Kluczowe endpointy API

- `POST /api/auth/login`, `POST /api/auth/register`, `GET /api/auth/me`
- Projekty: `GET/POST/PUT/DELETE /api/projects`
- Typy materiałów: `GET /api/material-types` (wszyscy), `POST/PUT/DELETE /api/material-types` (admin)
- Materiały: `GET /api/materials`, `POST /api/materials`
- Użytkownicy (admin): `GET/POST/PUT/DELETE /api/users`
- Upload/analiza: `POST /api/uploads/inspect`, `POST /api/uploads/process`, `GET /api/downloads/{token}`

## Przepływ przetwarzania plików

1. Użytkownik wybiera projekt i wgrywa XLS/XLSX (`/api/uploads/inspect`). Backend zapisuje plik i zwraca `upload_token` oraz listę nieznanych materiałów (z ostatniej kolumny).
2. Frontend dla nieznanych materiałów wymusza wybór typu (select z tabeli `material_types`); po zatwierdzeniu wysyła `/api/uploads/process` z mapowaniem materiał → typ.
3. Backend tworzy brakujące rekordy `materials`, następnie dzieli wiersze na typy materiałów, dopisuje w pierwszym wierszu klienta i projekt, zapisuje XLS dla każdego typu, pakuje do ZIP i zwraca link do pobrania.
4. Link pobrania to `GET /api/downloads/{upload_token}` (wymaga autoryzacji).

Jeżeli po wstępnej analizie brak nieznanych materiałów, można od razu wywołać `/api/uploads/process` z pustą listą `assignments`.

## Rozszerzalne hooki

Puste funkcje wywoływane podczas przetwarzania znajdują się w `app/Services/MaterialProcessingHooks.php`:
- `beforeAnalysis(Spreadsheet $spreadsheet, Project $project)` – uruchamiana po weryfikacji materiałów, przed iteracją wierszy.
- `onRow(array $rowData, string $materialName)` – uruchamiana dla każdego analizowanego wiersza.

## Struktura danych

- `projects`: `name`, `client_name`, `created_by`
- `material_types`: `name`
- `materials`: `name`, `material_type_id`
- `upload_sessions`: `token`, `project_id`, `file_path`, `result_path`, `status`
- `users`: standardowe pola + `is_admin`

## Widoki frontendu

- Logowanie (token Bearer)
- Zarządzanie projektami (CRUD)
- Zarządzanie typami materiałów (CRUD, tylko admin)
- Zarządzanie użytkownikami (CRUD, tylko admin)
- Ekran uploadu: wybór projektu, upload, przypisanie typów nieznanych materiałów, pobranie ZIP

## Uwagi

- API udostępnia CORS (`config/cors.php`), domyślnie zezwala na wszystkie pochodzenia dla ścieżek `/api/*` i `/oauth/*`.
- Wyniki eksportu trafiają do `storage/app/public/downloads`, oryginalne pliki do `storage/app/uploads/{token}`.
- Pamiętaj o uruchomieniu `php artisan storage:link`, aby publiczny symlink działał na produkcji.
