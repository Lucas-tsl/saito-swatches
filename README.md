# Saito Swatches

Plugin WordPress / WooCommerce de la famille Saito : remplace les listes déroulantes des produits variables par des swatches cliquables (image, couleur, icône, bouton, image de la variation), réglables pour toute la boutique ou produit par produit, sans surcharger de template WooCommerce.

- Swatches sur la fiche produit et sur les pages boutique (thèmes classiques et blocs).
- **Prix à l'unité** (« 115,00 € / 100 ml ») pour les variations et les produits simples.
- **Badge de rupture de stock** (« Bientôt disponible », « Épuisé »…) sur les valeurs épuisées.
- Prix dans le swatch, combinaisons indisponibles grisées ou masquées, accessibilité clavier.
- Images et couleurs transmises au panier flottant de [Saito Navi](https://github.com/Lucas-tsl/saito-navi).

Réglages : **Saito → Vignettes** (menu commun des plugins Saito) et onglet **Saito Swatches** de chaque produit. Détails dans [readme.txt](readme.txt).

## Développement

```
composer install && vendor/bin/phpcs      # WordPress Coding Standards
for t in tests/test-*.php; do php "$t"; done
npm install && npm run lint                 # ESLint + Stylelint
./scripts/build-wporg-release.sh            # zip WordPress.org dans build/
```

Le menu « Saito » commun vient de [saito-hub](https://github.com/Lucas-tsl/saito-hub) (`includes/saito-hub/`, ne pas modifier ici).

## Licence

GPLv2 ou ultérieure.
