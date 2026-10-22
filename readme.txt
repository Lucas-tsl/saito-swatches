=== Saito Swatches ===
Contributors: lucastsl
Tags: woocommerce, variation swatches, swatches, product variations, unit price
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Image, color, icon or variation-image swatches for WooCommerce, global or per product, on product and shop pages, with a unit price (per 100 ml…).

== Description ==

Saito Swatches replaces the dropdowns of WooCommerce variable products with clickable swatches — bottles, sizes, colors, packs — without overriding any WooCommerce template. Your theme keeps the original `variable.php`, so WooCommerce updates never break the swatches.

Set your swatches once for the whole shop in **Saito → Swatches → Attributes** (with a live preview), then fine-tune any product in its own **Saito Swatches** tab.

= Features =

* **Swatch type per attribute**: automatic, image, variation image, color, button with text, icon, or keep the dropdown.
* **Per product**: a "Saito Swatches" tab in the product data box to change the type, shape, size, image, color, icon and texts of each value for this product only — including **custom attributes** typed in the product.
* **Variation image** swatches: each swatch shows the image of its variation, nothing else to upload.
* **Price in the swatch** (optional, globally or per product): the lowest price of the matching variations.
* **Shop and category pages**, classic themes and block themes (Product Collection block): small swatches under each product; hovering one shows the variation image, clicking opens the product with that value already selected.
* **Per value**: an image (media library), a color (color picker), an icon, and optional texts — displayed label, subtitle and quantity for the unit price.
* **Create attribute values** without leaving the page. The same data is also editable in **Products → Attributes → Configure terms**.
* **Images with their real width and height** printed, to avoid layout shifts (CLS).
* **Unit price** under the selected variation, e.g. "115,00 € / 100 ml", formatted with your WooCommerce currency settings — and next to the price of **simple products**, from a capacity set in the product tab or read from an existing field (such as an ACF "capacity" field).
* **All texts editable**: global subtitle, unit price text, image alternative text, unavailable tooltip, "Clear" link.
* **Appearance**: layout (row, grid, stacked), image beside or above the text, shape (circle, rounded, square), spacing, border, corner radius, colors, dot and icon size, opacity of non-selected swatches, dedicated mobile layout and breakpoint.
* **Unavailable combinations** faded, struck through or hidden, using WooCommerce's own availability data.
* **Out of stock badge** on values whose variations are all out of stock: your own text ("Sold out", "Back soon"…), color and position. The value stays clickable.
* **Copy the settings of another product** in one click from the product tab, then adjust what differs.
* **Accessible**: real buttons, radio-group semantics, arrow-key navigation, visible focus.
* **Lightweight**: assets load on product pages only (plus a tiny script on shop pages when shop swatches are on); no external requests, no tracking.
* Part of the **Saito** family: the settings live in the shared Saito menu when another Saito plugin (such as Saito Navi) is active, and images and colors also fill the swatches of the Saito Navi sticky add-to-cart.

= How it works =

The native `<select>` stays in the page, hidden, and remains the source of truth for WooCommerce. Clicking a swatch selects the matching option and triggers WooCommerce's usual events, so prices, stock, images and the add-to-cart button behave exactly as before.

Values are displayed in the order set for the attribute in WooCommerce. In automatic mode, an attribute shows swatches as soon as one of its values has an image, a color or an icon; other attributes keep their regular dropdown.

What a product sets in its Saito Swatches tab wins over the global values; empty fields fall back to them. So you can share one set of colors across all your lipsticks and still give one product its own.

= For developers =

* `saito_swatches_settings` — filter the settings at runtime.
* `saito_swatches_load_assets` — return `true` to load the scripts outside product pages (quick view, shop page).
* `saito_swatches_parent_menu` — change the admin menu the settings page belongs to.
* `saito_swatches_bridge_images` — change the images and colors sent to the Saito Navi sticky add-to-cart.
* `saito_swatches_loop_block` — block after which the swatches are printed in block product grids (default `woocommerce/product-price`).
* `saito_swatches_loop_position` — move the shop page swatches: `array( hook, priority )`, default `array( 'woocommerce_after_shop_loop_item', 7 )`.
* `saito_swatches_get_image_url( $value, $size )` — get the image URL of an attribute value from another plugin.

== Installation ==

1. Install and activate WooCommerce.
2. Install Saito Swatches from **Plugins → Add New**, or upload the `saito-swatches` folder to `/wp-content/plugins/`, then activate it.
3. Go to **Saito → Swatches → Attributes**, choose a swatch type for your attribute and give each value an image, a color or an icon.
4. Save and open a variable product. To change one product only, or for custom attributes, use the **Saito Swatches** tab of the product data box.

If your theme overrides `woocommerce/single-product/add-to-cart/variable.php` only to add swatches, you can delete that override.

== Frequently Asked Questions ==

= Does it work with my theme? =

It works with themes that use the classic WooCommerce add-to-cart form (`variable.php`), which covers classic themes and most page builders. The form generated by the WooCommerce "Add to Cart with Options" block in block themes uses its own markup and is not supported yet.

= Which attributes can show swatches? =

All of them. Global attributes (Products → Attributes) are set once in Saito → Swatches → Attributes and reused by every product. Custom attributes typed directly in a product are set in the Saito Swatches tab of that product.

= How is the unit price computed? =

Price of the selected variation ÷ quantity × reference quantity. With a reference quantity of 100, a 30 ml bottle at 34,50 € shows 115,00 € / 100 ml. The quantity is read from the first number in the value name, or from its "Quantity" field.

For simple products, the quantity is the "Capacity" of the product's Saito Swatches tab or, when empty, the field named in Advanced → Capacity field of products (`product_capacity` by default, e.g. an ACF field).

= I used another swatches or price-per-unit plugin =

Deactivate it to avoid two sets of swatches or two unit prices. Its data is not imported: set your values once in Saito → Swatches → Attributes. A capacity stored in a product field can be reused directly with the "Capacity field of products" setting.

= Where is the data stored? =

The settings in the `saito_swatches_settings` and `saito_swatches_attribute_types` options, the data of each value as term meta, and product settings in the `_saito_swatches` product meta. Everything is removed when you delete the plugin, unless you ticked "Keep settings when deleting the plugin".

= Is it translatable? =

Yes. The plugin is fully translatable and ships with a French translation.

== Screenshots ==

1. General tab: activation, variation form options and unit price, with the first-steps card.
2. Attributes tab: swatch type per attribute, image / color / icon and texts per value, new values, with the live preview.
3. Appearance tab: layout, sizes, colors and mobile settings with the live preview.
4. Swatches and unit price on a product page (desktop).
5. Swatches stacked on mobile.
6. The Saito Swatches tab of a product: colors for a custom attribute, shape, size and price display.
7. Swatches and unit prices on the shop page.

== Changelog ==

= 1.4.0 =
* Shop page swatches now also appear in product grids built with blocks (Product Collection, block themes), after the price, the name or the image.
* Clearer hierarchy inside swatches: value name, lighter subtitle, price set apart.
* New Typography settings: sizes of the name, subtitle and price, colors of the subtitle and price.
* New setting for the size of shop page swatches.

= 1.3.0 =
* New out-of-stock badge on swatches, with editable text, color and position (top corner or under the text). Shop page swatches of out-of-stock values are faded and struck through.
* New "Copy the settings of another product" in the product tab.
* Product tab redesigned: it no longer inherits the WooCommerce field layout that squeezed its fields.

= 1.2.0 =
* New "Saito Swatches" tab in the product data box: type, shape, size, image, color, icon and texts per value for one product, global values used when empty.
* Custom attributes (typed in a product) can now show swatches.
* New "Variation image" swatch type.
* Optional price in each swatch, globally or per product.
* Swatches on shop and category pages, with the variation image on hover and the value preselected on click.
* Unit price for simple products, from the product capacity or an existing field (such as ACF).
* Shape setting: circle, rounded square or square.

= 1.1.0 =
* One place for everything: the Attributes tab (swatch type per attribute; image, color, icon, label, subtitle and quantity per value; new values).
* Color, button and icon swatches, in addition to images.
* The same fields in Products → Attributes → Configure terms.
* The former "Values" list is migrated automatically onto the matching attribute values, then removed.
* Images and colors are shared with the Saito Navi sticky add-to-cart.
* Assets are versioned by file date, so caches always serve the latest files.

= 1.0.0 =
* First public release.

== Upgrade Notice ==

= 1.4.0 =
Shop swatches in block product grids, typography settings.

= 1.3.0 =
Out-of-stock badge with your own text, and copy of swatch settings between products.

= 1.2.0 =
Per-product swatches, custom attributes, variation image swatches, prices in swatches, shop page swatches and unit price for simple products.

= 1.1.0 =
Everything moves to the Attributes tab; your former "Values" settings are migrated automatically.

= 1.0.0 =
First public release.
