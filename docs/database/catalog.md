# Catalog Domain

## Category

### Fields

-   **name**: obligatoire
-   **slug**: unique, généré automatiquement (slugify du nom)
-   **description**: optionnel
-   **createdAt / updatedAt**: timestamps

### Relations

-   **products**: `OneToMany(Product)`

### slug

-   Généré automatiquement via un service Slugger #TODO
-   Format: minuscules, tirets, sans accents
-   Exemple: "Vêtements Homme" → "vetements-homme"
-   Utilisé dans les routes: `/categorie/{slug}`

## Product

### Fields

-   **name, description**
-   **slug**: unique, généré automatiquement à la création
-   **price**: `DECIMAL(10,2)`, validations (positif, 2 décimales, regex)
-   **stock**: int >= 0, `0` = rupture
-   **imageUrl**: optionnel
-   **isActive**: bool, visible ou masqué
-   **createdAt / updatedAt**: timestamps

### Relations

-   **category**: `ManyToOne(Category)` (obligatoire)
-   **orderItems**: `OneToMany(OrderItem)` (historisation commandes)
-   **cartItems**: `OneToMany(CartItem)`
-   **translations**: `OneToMany(ProductTranslation)` (cascade persist/remove)

### price

-   **Type**: decimal(10,2) → string en PHP
-   **Format**: 2 décimales obligatoires
-   **Devise**: EUR (euros)
-   **Important**:
    -   NE PAS utiliser de float pour les calculs
    -   Utiliser BCMath ou une library comme brick/money
    -   Exemple: "19.99", "1234.50"

### stock

-   Décrémenté automatiquement lors de la validation d'une commande
-   Si stock = 0 → produit marqué "en rupture"
-   TODO: Implémenter un système d'alerte pour stock < seuil

### isActive

-   `true`: Produit visible dans le catalogue
-   `false`: Produit masqué (soft delete)
-   Utile pour:
    -   Produits temporairement indisponibles
    -   Produits en préparation
    -   Garder l'historique des commandes passées

### Slug

-   Produit: généré en PrePersist `{name}-{timestamp}-{rand}` pour unicité avant ID
-   Catégorie: généré en PrePersist/PreUpdate via slugify(name)

### API & Filters

-   **Product** API: CRUD ouvert en lecture, admin-only pour écriture (`POST/PUT/PATCH/DELETE`)
-   **Category** API: idem (admin pour écriture)
-   **Filters**: search (name/description/category.slug), range (price/stock), order (price/name/stock/id)
