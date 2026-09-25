# Usklađenost cijena za WooCommerce

WordPress/WooCommerce dodatak koji povezuje trgovinu sa servisom [Usklađenost cijena](https://uskladjenost-cijena.com): sidrene cijene i javni strojno čitljiv cjenik po NN 101/2026.

Što radi:

- **Sinkronizacija na spremanje.** Svaka promjena proizvoda (naziv, šifra, GTIN, kategorija, cijena, akcija s datumom isteka, zaliha) odmah ide u servis kroz `PUT /items/{id}` i `POST /prices/by-external/{id}`. Varijacije su zasebni artikli s nazivom „Roditelj – atributi”; virtualni proizvodi su usluge.
- **Sidrena cijena uz cijenu.** Na stranici proizvoda ispod cijene dodatak ispisuje tekst koji servis izračuna (`GET /compliance/by-external/{id}` na kanalu webshopa), za jednostavne proizvode i za odabranu varijaciju. Odgovor se kešira 6 sati i briše kod svake sinkronizacije tog proizvoda.
- **Cijeli katalog na klik** (WooCommerce → Postavke → Usklađenost cijena) ili `wp uskladjenost sync`: serije od 200 artikala kroz `POST /items/bulk` i `POST /price-events/bulk`.

Bez Composera na produkciji: dodatak koristi WordPressov HTTP sloj. Zahtijeva PHP 8.0+, WooCommerce 8+.

## Postavljanje

1. U aplikaciji: trgovac → **Kanali** → dodajte kanal tipa *webshop* (šifra npr. `WEB`); **API pristup** → novi token s opsezima `catalog:write`, `prices:write`, `compliance:read`.
2. Instalirajte dodatak (ZIP ove mape ili `git clone` u `wp-content/plugins/`).
3. WooCommerce → Postavke → **Usklađenost cijena**: token, ID trgovca, šifra kanala. Spremite i kliknite **Pošalji sve proizvode i cijene**.

Javni cjenik za webshop servis od tada gradi i objavljuje sam; snippet gumba „Cjenik” za vašu stranicu je u aplikaciji pod Objava cjenika.

## Razvoj

```bash
composer install && vendor/bin/phpunit   # mapper je čisti PHP i testira se bez WordPressa
```

Licenca MIT, © Info Media d.o.o.

## Ostali SDK-ovi i dodaci

Ista obitelj za isti API, svaki u svom repozitoriju:

- [uskladjenost-cijena-php](https://github.com/ddragas/uskladjenost-cijena-php) – PHP SDK (Composer `infomedia/uskladjenost-cijena-php`)
- [uskladjenost-cijena-python](https://github.com/ddragas/uskladjenost-cijena-python) – Python SDK (`uskladjenost-cijena`)
- [uskladjenost-cijena-js](https://github.com/ddragas/uskladjenost-cijena-js) – JavaScript/TypeScript SDK (`@uskladjenost-cijena/sdk`)
- [uskladjenost-cijena-shopify](https://github.com/ddragas/uskladjenost-cijena-shopify) – Shopify custom app
- [uskladjenost-cijena-prestashop](https://github.com/ddragas/uskladjenost-cijena-prestashop) – PrestaShop 8 modul
