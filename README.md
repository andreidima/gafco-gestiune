# GAFCO Gestiune

Aplicatie Laravel pentru gestiunea utilajelor, sculelor, echipamentelor, stocurilor si transferurilor intre santiere.

## Functionalitati demo

- Dashboard operational
- Locatii: baze si santiere
- Nomenclator materiale, scule si echipamente
- Echipamente cu QR, locatie curenta, responsabil si istoric
- Transferuri cu flux: cerere, aprobare, sofer, tranzit, confirmare primire
- Cereri sofer
- Receptii furnizori cu aviz/factura
- Rapoarte: inventar pe locatie, lipsuri/neconfirmate, istoric transferuri

## Date demo

Seeder-ul genereaza date suficiente pentru prezentare:

- utilizatori si roluri
- santiere si baza
- furnizori
- nomenclator
- sute de echipamente si miscari
- transferuri, receptii si cereri sofer

Cont demo:

```text
admin@example.com
password
```

## Setup local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

## Verificare

```bash
php artisan test
npm run build
```
