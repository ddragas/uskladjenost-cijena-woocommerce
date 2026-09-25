=== Usklađenost cijena za WooCommerce ===
Contributors: infomedia
Tags: woocommerce, cjenik, sidrena cijena, NN 101/2026, cijene
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: MIT

Sidrene cijene i javni cjenik po NN 101/2026 za WooCommerce trgovine, preko servisa Usklađenost cijena.

== Description ==

Od 1. 10. 2026. uz svaku cijenu ide sidrena cijena, a tko ima web stranicu objavljuje strojno čitljiv cjenik (CSV i XML) svaki dan do 8 sati. Ovaj dodatak povezuje WooCommerce sa servisom Usklađenost cijena:

* svaka spremljena promjena proizvoda (naziv, šifra, GTIN, cijena, akcija, zaliha) odmah ide u servis; varijacije su zasebni artikli
* uz cijenu na stranici proizvoda ispisuje se propisani tekst sidrene cijene, onaj koji servis izračuna po pravilima za kategoriju artikla
* javni cjenik za webshop servis gradi i objavljuje sam, svaki dan
* gumb „Pošalji sve proizvode” i WP-CLI naredba `wp uskladjenost sync` šalju cijeli katalog

== Installation ==

1. U aplikaciji Usklađenost cijena otvorite trgovca, dodajte prodajni kanal tipa webshop (npr. šifra WEB) i pod API pristup izdajte token s opsezima catalog:write, prices:write i compliance:read.
2. Instalirajte i aktivirajte dodatak.
3. WooCommerce → Postavke → Usklađenost cijena: upišite token, ID trgovca i šifru kanala; spremite; kliknite „Pošalji sve proizvode i cijene”.

== Changelog ==

= 1.0.0 =
* Prva verzija.
