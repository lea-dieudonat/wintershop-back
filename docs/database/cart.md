# Cart Domain

## Cart

### Fields

-   **createdAt / updatedAt**: timestamps (updated on any cart change)
-   **totalPrice**: `DECIMAL(10,2)` montant du panier, recalculé à chaque modification

### Relations

-   **user**: `OneToOne(User)` panier unique par utilisateur
-   **items**: `OneToMany(CartItem)` avec cascade persist/remove et orphanRemoval

### Business Rules

-   **Création**: Automatique lors de l'inscription (getOrCreateCart)
-   **Suppression**: Cascade avec l'utilisateur
-   **Conversion**: Transformé en Order lors du checkout
-   **Durée de vie**: Persistant (pas de panier anonyme)
-   **Nettoyage**: Les items indisponibles (produit inactif ou stock insuffisant) sont supprimés automatiquement (`removeUnavailableItems`) et le total est recalculé
-   **Total**: Recalcul systématique (`calculateTotal`) après ajout/mise à jour/suppression/clear

## CartItem

### Fields

-   **quantity**: `int` (min 1, `Assert"); Positive`), contrôlé contre le stock produit
-   **unitPrice**: `DECIMAL(10,2)` prix produit au moment de l'ajout (figé pour le panier courant)
-   **product**: `ManyToOne(Product)` (obligatoire)
-   **cart**: `ManyToOne(Cart)` (obligatoire)
-   **createdAt**: timestamp de création de l'item

### Business Rules

-   **Validation avant ajout/mise à jour**: quantité >= 1 et <= stock disponible
-   **Quantité**: si demande <1 → rejetée ; si stock insuffisant → rejetée ; items inactifs ou sans stock sont retirés au rafraichissement
-   **Unit price**: copié depuis `Product.price` au moment de l'ajout et utilisé pour le calcul du total

## API Endpoints

-   **GET** `/api/cart` - Récupérer le panier actuel
-   **POST** `/api/cart/items` - Ajouter un produit au panier
-   **PATCH** `/api/cart/items/{id}` - Modifier la quantité d'un article
-   **DELETE** `/api/cart/items/{id}` - Supprimer un article du panier
-   **DELETE** `/api/cart` - Vider entièrement le panier

## Checkout Preconditions

-   Panier ne doit pas être vide
-   Aucun item inactif ou au stock insuffisant (validé avant conversion en commande)
