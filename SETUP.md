# FlowWebAppScrapper - Setup Guide

## Aplikacja do scrapowania aplikacji webowych z użyciem Laravel i Filament

### Wymagania

- PHP 8.2+
- Composer
- Node.js (dla Puppeteer)
- NPM
- SQLite/MySQL/PostgreSQL

### Instalacja

1. **Zainstaluj zależności PHP:**
```bash
composer install
```

2. **Skonfiguruj środowisko:**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Skonfiguruj bazę danych w `.env`:**
```
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite
```

4. **Uruchom migracje:**
```bash
php artisan migrate
```

5. **Utwórz użytkownika administratora:**
```bash
php artisan tinker
```
W tinkerze:
```php
App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => bcrypt('password')
]);
```

6. **Zainstaluj Node.js i Puppeteer:**
```bash
npm install puppeteer
```

7. **Połącz storage:**
```bash
php artisan storage:link
```

8. **Uruchom serwer:**
```bash
php artisan serve
```

### Konfiguracja Puppeteer

W pliku `app/Services/WebScrapingService.php` możesz potrzebować dostosować ścieżki do Node.js i NPM:

```php
// Dla macOS z Homebrew:
->setNodeBinary('/opt/homebrew/bin/node')
->setNpmBinary('/opt/homebrew/bin/npm')

// Dla Ubuntu/Debian:
->setNodeBinary('/usr/bin/node')
->setNpmBinary('/usr/bin/npm')

// Dla Windows:
->setNodeBinary('C:\\Program Files\\nodejs\\node.exe')
->setNpmBinary('C:\\Program Files\\nodejs\\npm.cmd')
```

### Użytkowanie

1. **Panel administratora:**
   - URL: `http://localhost:8000/admin`
   - Email: `admin@example.com`
   - Hasło: `password`

2. **Tworzenie projektu:**
   - Przejdź do "Projects" w panelu
   - Kliknij "New Project"
   - Wypełnij formularz z URL i danymi logowania (opcjonalnie)
   - Zapisz projekt

3. **Uruchamianie scrapowania:**

   **Przez panel admin:**
   - W liście projektów kliknij "Start Scraping" przy projekcie

   **Przez konsolę:**
   ```bash
   # Lista projektów oczekujących
   php artisan scrape:project
   
   # Scraping konkretnego projektu
   php artisan scrape:project 1
   
   # Synchroniczny scraping (bez kolejki)
   php artisan scrape:project 1 --sync
   ```

4. **Uruchamianie kolejki (dla asynchronicznego przetwarzania):**
```bash
php artisan queue:work
```

### Funkcjonalności

**WebScrapingService:**
- Automatyczne logowanie na podstawie podanych danych
- Przechodzenie przez wszystkie linki do określonej głębokości
- Robienie zrzutów ekranu każdej strony
- Analiza formularzy i ich pól
- Wykrywanie endpointów API w JavaScript
- Zapisywanie wszystkich znalezionych danych w formacie JSON

**AIAnalysisService:**
- Generowanie opisów aplikacji na podstawie zebranych danych
- Tworzenie schematów modeli bazy danych
- Analiza wzorców URL i sugerowanie struktur
- Mapowanie typów pól HTML na typy bazy danych

**Filament Panel:**
- Zarządzanie projektami przez interfejs webowy
- Podgląd rezultatów scrapowania
- Filtrowanie i wyszukiwanie projektów
- Akcje masowe

### Struktura danych

**Tabela `projects`:**
- `name` - nazwa projektu
- `url` - główny URL aplikacji
- `login_url` - URL strony logowania (opcjonalnie)
- `username`/`password` - dane logowania
- `login_data` - dodatkowe dane logowania (JSON)
- `status` - status: pending/running/completed/failed
- `max_depth` - maksymalna głębokość crawlingu
- `scraped_urls` - lista znalezionych URL-i (JSON)
- `screenshots` - lista zrzutów ekranu (JSON)
- `form_data` - dane o formularzach (JSON)
- `api_requests` - znalezione endpointy API (JSON)
- `description` - opis wygenerowany przez AI
- `model_schema` - schemat modeli wygenerowany przez AI (JSON)

### Testowanie

```bash
# Wszystkie testy
php artisan test

# Konkretna kategoria
php artisan test tests/Unit/ProjectTest.php
```

### Rozwiązywanie problemów

1. **Błędy Puppeteer:**
   - Sprawdź czy Node.js jest zainstalowany: `node --version`
   - Sprawdź ścieżki w `WebScrapingService.php`
   - Zainstaluj brakujące zależności: `npm install puppeteer`

2. **Błędy uprawnień:**
   - Upewnij się, że katalog `storage/` ma odpowiednie uprawnienia
   - Sprawdź czy `storage/app/public/screenshots` istnieje

3. **Błędy kolejki:**
   - Upewnij się, że `queue:work` jest uruchomione
   - Sprawdź konfigurację kolejki w `.env`

### Integracja z AI

Aby włączyć prawdziwą integrację z AI (np. OpenAI), odkmentuj i skonfiguruj metodę `callAIService()` w `AIAnalysisService.php`:

```php
// Dodaj do .env
OPENAI_API_KEY=your_api_key_here

// Odkmentuj kod w AIAnalysisService.php
```

### Bezpieczeństwo

- Hasła są automatycznie ukrywane w odpowiedziach API
- Dane logowania są przechowywane w zaszyfrowanej bazie danych
- Zaleca się użycie HTTPS w produkcji
- Ogranicz dostęp do panelu administratora

### Rozszerzenia

Aplikacja jest zaprojektowana do łatwego rozszerzania:

- Dodaj nowe typy analizy w `AIAnalysisService`
- Rozszerz `WebScrapingService` o nowe funkcje
- Dodaj nowe pola do modelu `Project`
- Stwórz dodatkowe zasoby Filament