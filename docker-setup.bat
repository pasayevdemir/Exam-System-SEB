@echo off
echo === Docker Exam System Setup ===

echo [1/5] Building and starting containers...
docker compose up -d --build

echo [2/5] Waiting for database to be ready...
timeout /t 15 /noisy

echo [3/5] Generating app key...
docker compose exec app php artisan key:generate

echo [4/5] Running migrations...
docker compose exec app php artisan migrate --force

echo [5/5] Creating storage link...
docker compose exec app php artisan storage:link

echo.
echo === Setup Complete! ===
echo App running at: http://localhost:8000
echo Admin panel:    http://localhost:8000/admin/login
echo Admin user/pass: see ADMIN_USERNAME / ADMIN_PASSWORD in .env
echo.
pause
