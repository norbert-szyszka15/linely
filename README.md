# Linely

Prosty projekt aplikacji webowej do tworzenia drzew genealogicznych.

## Uruchomienie

```bash
docker compose down -v
docker compose up -d --build
```

Aplikacja będzie dostępna pod adresem:

```text
http://localhost:8080
```

PgAdmin:

```text
http://localhost:5050
```

## Konta testowe

| Rola | Email |
| --- | --- |
| Administrator | admin@example.com |
| Użytkownik | user@example.com |

## Zakres prototypu

- logowanie użytkownika i administratora,
- panel użytkownika z listą drzew,
- panel administratora z listą użytkowników i wszystkich drzew,
- usuwanie drzew oraz użytkowników przez administratora,
- dodawanie i edycja osób w modalnych oknach,
- dodawanie partnerów oraz dzieci z pop-upów przy osobie,
- pełny widok drzewa przewijany w obu kierunkach,
- przesuwanie osób na canvasie z zapisem pozycji w bazie,
- przyciąganie przesuwanych osób do siatki,
- przesuwanie canvasu środkowym przyciskiem myszy,
- zoomowanie widoku drzewa,
- widok linii prostej wybranej osoby z rodzicami, dziadkami, dziećmi, wnukami i partnerami,
- jasny i ciemny motyw interfejsu.

## Struktura projektu

```text
public/
  index.php
  scripts/
  styles/
  views/
src/
  controllers/
  repositories/
config.php
Database.php
index.php
Routing.php
```

Po zmianach w `docker/db/init.sql` warto uruchomić projekt komendą `docker compose down -v`, żeby baza została utworzona od nowa z aktualnego schematu.
