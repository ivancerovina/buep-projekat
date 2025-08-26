# Projekat iz Bezbednosti u Elektronskom Poslovanju 2025

## Opis Projekta
Aplikacija za praćenje troškova goriva u kompaniji. Sistem omogućava zaposlenima da evidentiraju svoju potrošnju goriva, dok administratori mogu postavljati limite po zaposlenom i pratiti njihovu potrošnju. Aplikacija mora implementirati sve sigurnosne principe iz dokumenta i omogućiti bezbedno upravljanje korisničkim nalozima.

### Osnovne Funkcionalnosti Sistema
- **Zaposleni** mogu da se prijave i evidentiraju svoju potrošnju goriva (datum, kilometraža, količina goriva, cena)
- **Administratori** mogu da:
  - Postavljaju mesečne/godišnje limite potrošnje po zaposlenom
  - Prate i analiziraju potrošnju goriva svih zaposlenih
  - Generišu izveštaje o potrošnji
  - Upravljaju korisničkim nalozima

## Osnovni Zahtevi

### Cilj Projekta
Kreirati bezbednu web aplikaciju za interno praćenje troškova goriva kompanije koja implementira sve osnovne sigurnosne mehanizme i demonstrira razumevanje principa bezbednosti u elektronskom poslovanju.

## Tehnički Zahtevi

### 1. Autentifikacija i Autorizacija
- **Registracija korisnika** sa validacijom podataka
- **Prijava korisnika** (login forma)
- **Različite korisničke uloge**:
  - Administrator (upravlja limitima, korisnicima, generiše izveštaje)
  - Zaposleni (evidentira potrošnju goriva, pregleda svoju istoriju)
  - Manager (pregleda izveštaje svog tima/odeljenja)
- **Kontrola pristupa** zasnovana na ulogama (RBAC - Role-Based Access Control)
- **Logout funkcionalnost** sa bezbednim brisanjem sesije

### 2. Zaštita od Osnovnih Napada
- **SQL Injection** - korišćenje parametrizovanih upita ili ORM-a
- **Cross-Site Scripting (XSS)** - validacija i sanitizacija ulaznih podataka, korišćenje Content Security Policy
- **Cross-Site Request Forgery (CSRF)** - implementacija CSRF tokena
- **Session Management** - bezbedno upravljanje sesijama (HttpOnly, Secure cookies)

### 3. Enkripcija i Hashovanje
- **Hashovanje lozinki** korišćenjem sigurnih algoritama (bcrypt, Argon2, ili PBKDF2)
- **HTTPS komunikacija** (može se koristiti self-signed sertifikat za razvoj)
- **Enkripcija osetljivih podataka** u bazi podataka (po potrebi)

### 4. Logging i Monitoring
- **Logovanje sigurnosnih događaja** (neuspešne prijave, izmene kritičnih podataka)
- **Audit trail** - praćenje akcija korisnika
- **Pregled logova** kroz administratorski panel

### 5. Sigurnosna Konfiguracija
- **Security headers** (X-Frame-Options, X-Content-Type-Options, itd.)
- **Princip najmanje privilegije** - korisnici imaju pristup samo resursima koji su im potrebni
- **Validacija svih ulaznih podataka** na serverskoj strani
- **Rate limiting** za sprečavanje brute force napada

## Funkcionalnosti Aplikacije

### Obavezne Funkcionalnosti
1. **Registracija i prijava korisnika**
2. **Dashboard** sa različitim sadržajem za različite uloge:
   - Zaposleni: pregled svoje potrošnje, trenutni limit, forma za unos
   - Administrator: statistike potrošnje, upravljanje limitima, lista zaposlenih
   - Manager: pregled potrošnje tima, izveštaji
3. **CRUD operacije** za:
   - Evidencije potrošnje goriva (Create, Read, Update, Delete)
   - Limite po zaposlenom (samo Administrator)
   - Korisnički nalozi (samo Administrator)
4. **Profil korisnika** sa mogućnošću izmene podataka
5. **Administratorski panel** za:
   - Upravljanje korisnicima
   - Postavljanje limita potrošnje
   - Pregled logova
   - Generisanje izveštaja

### Dodatne Funkcionalnosti (Bonus)
- Implementacija **dva-faktorske autentifikacije (2FA)**
- **Password reset** funkcionalnost sa sigurnosnim tokenima
- **Captcha** za zaštitu od automatizovanih napada
- **API sa autentifikacijom** (JWT ili API ključevi)
- **Backup i recovery** strategija
- **Notifikacije** kada zaposleni priđe limitu (80%, 90%, 100%)
- **Export podataka** u CSV/Excel format
- **Grafički prikazi** potrošnje (grafikoni, trendovi)
- **Integracija sa GPS** za automatsko praćenje kilometraže

## Tehnologije

### Tehnološki Stack za Ovaj Projekat
- **Backend:** Pure PHP (bez framework-a)
- **Frontend:** HTML5, CSS3, Vanilla JavaScript (bez framework-a)
- **Baza Podataka:** MySQL
- **Server:** Apache/Nginx sa PHP podruškom

### Važne Napomene o Implementaciji
- **Bez PHP framework-a** - koristiti čist PHP kod
- **Bez JavaScript framework-a** - koristiti vanilla JavaScript
- **PDO ili MySQLi** za rad sa bazom podataka (obavezno parametrizovani upiti)
- **Sesije** za upravljanje korisničkim prijavama
- **Objektno-orijentisani pristup** gde je to moguće
- **MVC pattern** implementiran ručno za bolju organizaciju koda

## Način Ocenjivanja

### Kriterijumi (100 poena)
1. **Implementacija sigurnosnih mehanizama** (40 poena)
   - Autentifikacija i autorizacija (10)
   - Zaštita od napada (15)
   - Enkripcija i hashovanje (10)
   - Security headers i konfiguracija (5)

2. **Funkcionalnost aplikacije** (25 poena)
   - Kompletnost funkcionalnosti (15)
   - Korisničko iskustvo (5)
   - Responzivan dizajn (5)

3. **Kvalitet koda** (15 poena)
   - Čitljivost i organizacija (5)
   - Komentari i dokumentacija (5)
   - Best practices (5)

4. **Testiranje** (10 poena)
   - Unit testovi za kritične funkcionalnosti
   - Sigurnosno testiranje

5. **Dokumentacija** (10 poena)
   - README sa uputstvom za instalaciju
   - Opis sigurnosnih mehanizama
   - Dijagram arhitekture

## Rok za Predaju
- **Datum:** [Biće naknadno određen]
- **Format predaje:** GitHub repozitorijum sa pristupom za profesora

## Napomene
- Projekat može biti rađen **individualno ili u timu do 3 člana**
- Za timski rad se očekuje proporcionalno kompleksnija aplikacija
- **Plagijarizam** rezultuje automatskim padom ispita
- Obavezna je **odbrana projekta** sa demonstracijom funkcionalnosti

## Kontakt
Za sva pitanja u vezi projekta, kontaktirajte profesora putem email-a ili tokom konsultacija.

## Dodatne Smernice za Razvoj

### Preporučeni Pristup Razvoju
1. **Postaviti strukturu projekta** (folders: config, includes, public, assets, logs)
2. **Kreirati bazu podataka** sa tabelama za korisnike, evidencije goriva, limite
3. **Implementirati osnovnu autentifikaciju** (login, logout, session management)
4. **Dodati autorizaciju** sa ulogama (admin, zaposleni, manager)
5. **Kreirati CRUD operacije** za evidencije goriva
6. **Implementirati sigurnosne mere** (SQL injection, XSS, CSRF zaštita)
7. **Dodati logging sistem** za praćenje aktivnosti
8. **Testirati sigurnosne aspekte**
9. **Napisati dokumentaciju**

### Struktura Projekta (Preporučena)
```
buep-projekat/
├── config/
│   ├── database.php
│   └── config.php
├── includes/
│   ├── auth.php
│   ├── functions.php
│   ├── security.php
│   └── validation.php
├── public/
│   ├── index.php
│   ├── login.php
│   ├── dashboard.php
│   ├── fuel-records.php
│   └── admin/
│       ├── users.php
│       └── limits.php
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── logs/
│   └── security.log
└── sql/
    └── schema.sql
```

### Sigurnosni Checklist
- [ ] Sve lozinke su hashovane
- [ ] Sesije se bezbedno upravljaju
- [ ] Svi inputi su validirani (posebno numerički za potrošnju)
- [ ] CSRF zaštita je implementirana
- [ ] XSS zaštita je implementirana
- [ ] SQL injection zaštita je implementirana
- [ ] Security headers su konfigurisani
- [ ] HTTPS je omogućen
- [ ] Logovanje je implementirano (posebno za izmene limita i brisanje evidencija)
- [ ] Rate limiting je implementiran
- [ ] Validacija business logike (npr. zaposleni ne može uneti negativnu potrošnju)
- [ ] Provera ovlašćenja za pristup tuđim podacima o potrošnji