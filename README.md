# 707 GYM

PHP/MySQL system para sa gym member records, memberships, attendance, walk-in visits, payments, CSV imports, at database backups.

## Requirements

- XAMPP na may PHP 8+, Apache, at MySQL/MariaDB.
- Ilagay ang project sa `C:\xampp\htdocs\707_GYM`.
- I-check ang database credentials at `BASE_URL` sa `config/config.php` kung iba ang setup ng gym.

## Fresh setup

1. Start Apache at MySQL sa XAMPP.
2. Sundin ang **Reset at fresh import** sa [database/batch_import.md](database/batch_import.md). Ang import order ay `schema.sql`, `seed_system_defaults.sql`, at `seed_membership_plans.sql`.
3. Buksan ang `http://localhost/707_GYM/` at palitan ang default admin password sa System Settings bago gamitin sa gym.

Walang demo members sa seeds. Ang reset command sa import guide ay **bubura sa buong `gym_707` database**. Gamitin lang iyon kapag talagang magsisimula sa zero; may hiwalay na update instructions sa guide para sa database na may real records. Huwag muling i-run ang membership seed kapag may linked memberships na dahil gumagamit ito ng `REPLACE INTO`.

## Rates at attendance

| Category | Presyo |
| --- | ---: |
| Regular walk-in | ₱100 bawat pagpasok |
| 6-month membership | ₱300 membership fee |
| 1-year membership | ₱500 membership fee |
| 1-month subscription | ₱1,000 membership fee |
| Visit na may active membership | ₱70 bawat pagpasok |

Ang member na walang current active membership ay **Pending** sa Members page. Sa daily member check-in, kailangang kumpirmahin ang bayad at ilagay ang amount tendered; ipinapakita ang sukli at nai-record ang visit payment. Hiwalay ang Walk-in, Membership, at Daily Member Fee sa Payments page. Nasa **System Settings → CSV Import** ang import tools.

## Backups

Naka-on ang daily backup setting sa fresh setup. Para gumana ang automatic backup, i-install nang isang beses ang Windows scheduled task gamit ang PowerShell:

```powershell
cd C:\xampp\htdocs\707_GYM
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\install_daily_backup_task.ps1
```

Nakatakda ito sa **1:00 AM**. Dapat running ang MySQL at naka-sign in ang Windows user na nag-install ng task. Nasa `backup/` ang SQL files. Ang bagong generated backup ay may `CREATE DATABASE IF NOT EXISTS gym_707`, kaya puwedeng i-import sa phpMyAdmin kahit wala pa ang database. Mag-ingat sa restore: papalitan nito ang existing tables at records.
