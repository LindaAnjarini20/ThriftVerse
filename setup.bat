@echo off
REM Jalankan dari Laragon Terminal (Cmder) di folder proyek ini. Pastikan MySQL Laragon sudah Start All.
if not exist .env copy .env.example .env
call php artisan key:generate --force
echo CREATE DATABASE IF NOT EXISTS thriftverse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; | mysql -uroot
call php artisan config:clear
call php artisan view:clear
call php artisan migrate:fresh --seed --force
if not exist public\storage call php artisan storage:link
echo.
echo Selesai. Buka http://thriftverse.test  (atau jalankan: php artisan serve)
echo Login admin: admin@thriftverse.app / password123
pause
