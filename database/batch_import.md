# C:\xampp\htdocs\707_GYM

Sa loob ng directory na ito type mo powershell hindi cmd iba kasi syntax pag cmd eh

## Reset at fresh import

Ang buong block sa baba ay **magbubura ng `gym_707` database** bago gumawa ng bagong tables at default records. Mawawala ang manual members, attendance, memberships, payments, import history, at audit logs. I-back up muna ang anumang kailangang itago. Para lang ito sa deployment reset o dry run na talagang magsisimula sa umpisa.

iimport lang tayo neto pag deployment lang -- hindi ito ginagawa lagi.

```powershell
cd C:\xampp\htdocs\707_GYM

C:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE IF EXISTS gym_707"
if ($LASTEXITCODE -ne 0) { throw "Database reset failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/schema.sql"
if ($LASTEXITCODE -ne 0) { throw "Schema import failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/seed_system_defaults.sql"
if ($LASTEXITCODE -ne 0) { throw "System defaults import failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/seed_membership_plans.sql"
if ($LASTEXITCODE -ne 0) { throw "Membership plans import failed." }

C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT id, name, duration_days, price FROM gym_707.membership_plans ORDER BY id"
if ($LASTEXITCODE -ne 0) { throw "Membership plan verification failed." }

C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT (SELECT COUNT(*) FROM gym_707.members) AS members, (SELECT COUNT(*) FROM gym_707.memberships) AS memberships, (SELECT COUNT(*) FROM gym_707.payments) AS payments"
if ($LASTEXITCODE -ne 0) { throw "Empty records verification failed." }

Write-Host "success db batch import" -ForegroundColor Green

```

Ang `schema.sql` ay gumagawa ng database at tables. Ang `seed_system_defaults.sql` ay para sa default admin at backup setting; hindi nito papalitan ang existing admin password. Ang `seed_membership_plans.sql` ay para sa 6-month, 1-year, at 1-month plans. Walang demo members na ini-import.

## Update lang, panatilihin ang existing records

Huwag patakbuhin ang reset block kung kailangang manatili ang existing records. Ang membership seed ay gumagamit ng `REPLACE INTO` sa plan IDs 1, 2, at 3. Maaari itong mag-fail o makaapekto sa memberships na naka-link na sa mga plan.

Para sa kasalukuyang schema at system defaults, ito lang ang puwedeng i-run nang hindi nire-reset ang member at transaction records:

```powershell
cd C:\xampp\htdocs\707_GYM

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/schema.sql"
if ($LASTEXITCODE -ne 0) { throw "Schema import failed." }

C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root -e "source database/seed_system_defaults.sql"
if ($LASTEXITCODE -ne 0) { throw "System defaults import failed." }

C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT id, name, duration_days, price FROM gym_707.membership_plans ORDER BY id"
if ($LASTEXITCODE -ne 0) { throw "Membership plan verification failed." }
```

Kapag iba ang existing plan prices o IDs, suriin muna ang linked memberships bago palitan ang plan rows. Hindi ina-update ng `CREATE TABLE IF NOT EXISTS` ang structure ng existing tables; kailangan ng hiwalay na migration kung may kulang na column.
