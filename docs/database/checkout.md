# Checkout Domain

Le domaine checkout gère la conversion des paniers en commandes et initie le processus de paiement via Stripe. Il orchestre la validation, la création de commande, le calcul des frais de livraison et le flux de paiement.

## CheckoutService

Service central responsable de la transformation des paniers en commandes et de la gestion des opérations liées aux commandes.

### Méthodes

#### `createOrderFromCart(Cart, Address, Address, ShippingMethod): Order`

Point d'entrée principal du processus de checkout. Convertit un panier en commande avec des totaux calculés.

**Flux :**

1. Valide le panier (non vide, articles disponibles, stock suffisant)
2. Crée une entité Order avec le statut PENDING
3. Convertit les CartItems en OrderItems (snapshots du unitPrice depuis Product.price au moment du checkout)
4. Calcule le total des produits avec bcadd (bcmath pour la précision)
5. Calcule les frais de livraison via `ShippingMethod.getActualCost(productsTotal)`
6. Définit totalAmount = productsTotal + shippingCost
7. Persiste et flush en base de données
8. Retourne Order

**Préconditions :**

-   Le panier ne doit pas être vide
-   Tous les produits doivent être actifs (`product.isActive == true`)
-   Le stock doit être suffisant pour tous les articles (`product.stock >= cartItem.quantity`)

**Exceptions :**

-   `RuntimeException` si le panier est vide
-   `RuntimeException` si un article n'est pas disponible
-   `RuntimeException` si stock insuffisant

#### `validateCartForCheckout(Cart): void`

Valide que le panier est prêt pour le checkout sans le modifier.

**Vérifications :**

-   Le panier n'est pas vide (contient des articles)
-   Tous les produits sont actifs
-   Tous les produits ont un stock suffisant

#### `decrementStock(Order): void`

Décrémente le stock des produits lorsque la commande passe au statut PAID. Appelée par le gestionnaire de webhook après paiement réussi.

**Flux :**

1. Itère sur tous les OrderItems
2. Pour chaque article, décrémente le stock produit de la quantité
3. Valide que le stock suffisant existe (prévient l'inventaire négatif)
4. Flush les modifications en base de données

**Exception :**

-   `RuntimeException` si stock insuffisant (ne devrait pas survenir si la validation du checkout s'est bien déroulée)

### Précision & Calculs

**Tous les calculs monétaires utilisent bcmath :**

-   `bcadd()` pour l'addition (produits + livraison)
-   `bcmul()` pour la multiplication (unitPrice × quantité)
-   L'échelle est toujours définie à 2 décimales (précision en centimes EUR)

## Business Rules

-   **Panier non vide** : Un panier vide ne peut pas être converti en commande
-   **Produits actifs** : Tous les produits du panier doivent être actifs au moment du checkout (les produits inactifs invalident le checkout)
-   **Stock disponible** : La quantité en stock doit être >= à la quantité demandée pour chaque article
-   **Adresses validées** : Les adresses doivent appartenir à l'utilisateur authentifié (sécurité)
-   **Stock décrémenté après paiement** : Le stock n'est jamais décrémenté lors de la création de la commande, seulement après un paiement Stripe réussi (webhook)
-   **Prix et quantités figés** : Une fois la commande créée, les prix unitaires et quantités sont immuables dans OrderItem (historisation)
-   **Total immuable** : Le totalAmount ne change jamais après la création de la commande, même si les produits changent de prix
-   **Session Stripe 30 min** : La session Stripe expire après 30 minutes, forçant le client à recommencer le checkout
-   **Une seule commande par panier** : Après création de la commande, le panier est vidé (CartItems supprimés, totalPrice = 0)
-   **Frais de livraison calculés dynamiquement** : Les frais varient selon la méthode choisie et le montant du panier
-   **Idempotence du webhook** : Si le même webhook Stripe est reçu plusieurs fois, la commande n'est mise à jour qu'une seule fois (basée sur stripeSessionId)

## CheckoutInputDto

DTO de requête pour la validation du endpoint checkout.

### Champs

-   **shippingAddressId**: `int` (positif, obligatoire) - ID de l'adresse de livraison de l'utilisateur
-   **billingAddressId**: `int` (positif, obligatoire) - ID de l'adresse de facturation de l'utilisateur
-   **shippingMethod**: `ShippingMethod` (enum, obligatoire) - Option de livraison sélectionnée

### Validation

Tous les champs sont obligatoires et validés :

-   Les IDs d'adresse doivent être des entiers positifs
-   ShippingMethod doit être une valeur enum valide
-   Les adresses doivent appartenir à l'utilisateur authentifié (validé dans le contrôleur)

## Enum ShippingMethod

Définit les options de livraison disponibles avec les coûts et délais de livraison associés.

### Cas

-   **STANDARD** (`'standard'`): Livraison Standard
    -   Coût : 2,99 €
    -   Délai : 3-5 jours ouvrables
-   **EXPRESS** (`'express'`): Livraison Express
    -   Coût : 4,99 €
    -   Délai : 1-2 jours ouvrables
-   **RELAY_POINT** (`'relay_point'`): Retrait en Point Relais
    -   Coût : 0,00 € (gratuit)
    -   Délai : 5-7 jours ouvrables

### Méthodes

-   `getLabel(): string` - Nom lisible de la méthode de livraison
-   `getCost(): float` - Coût de base pour cette méthode
-   `getDeliveryTime(): string` - Fenêtre de délai estimée
-   `getActualCost(string orderAmount): string` - Obtenir le coût final (actuellement retourne toujours le coût de base)

## Flux de Checkout

### Endpoint API

**POST** `/api/checkout`

-   **Sécurité** : Nécessite `ROLE_USER` (utilisateur authentifié)
-   **Entrée** : `CheckoutInputDto` (JSON)
-   **Sortie** : `CheckoutSessionOutputDto` (JSON)
-   **Codes de statut** :
    -   `201 Created` : Session de checkout créée avec succès
    -   `400 Bad Request` : Panier, adresses ou validation invalides
    -   `401 Unauthorized` : Utilisateur non authentifié
    -   `500 Internal Server Error` : Erreur inattendue

### Exemple de Requête

```json
{
    "shippingAddressId": 123,
    "billingAddressId": 124,
    "shippingMethod": "express"
}
```

### Exemple de Réponse (Succès)

```json
{
    "sessionId": "cs_test_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6",
    "sessionUrl": "https://checkout.stripe.com/pay/cs_test_...",
    "orderId": 42,
    "orderReference": "ORD-20260121-00042",
    "publicKey": "pk_test_..."
}
```

### Flux de Checkout Complet

1. **Le frontend initie le checkout**

    - L'utilisateur fournit l'adresse de livraison, l'adresse de facturation, le mode de livraison
    - Appelle POST `/api/checkout` avec CheckoutInputDto

2. **Le backend crée la commande**

    - CheckoutService valide le panier
    - Crée une entité Order avec le statut PENDING
    - Calcule les frais de livraison
    - Calcule le montant total (produits + livraison)
    - Persiste la Order en base de données

3. **Le backend crée la session Stripe**

    - CheckoutService.createOrderFromCart() est appelé
    - Construit les éléments de ligne à partir des OrderItems
    - Inclut la livraison comme élément de ligne séparé (si applicable)
    - Crée une Session Checkout Stripe avec expiration de 30 minutes
    - Stocke l'ID de session sur Order.stripeSessionId
    - Flush les modifications

4. **Le frontend vide le panier**

    - Tous les CartItems sont supprimés
    - Cart.totalPrice défini à 0,00
    - Les modifications sont flushées en base de données

5. **Le frontend reçoit l'URL de checkout**

    - CheckoutSessionOutputDto retourné avec l'URL Stripe checkout
    - Le frontend redirige l'utilisateur vers la page de checkout hébergée par Stripe

6. **L'utilisateur complète le paiement sur Stripe**

    - L'utilisateur entre les détails de paiement sur la page Stripe
    - Stripe traite le paiement
    - En cas de succès, Stripe déclenche le webhook `checkout.session.completed`

7. **Le backend traite le webhook**

    - StripeWebhookController reçoit le webhook
    - Vérifie la signature du webhook avec STRIPE_WEBHOOK_SECRET
    - Recherche la Order par stripeSessionId
    - Appelle CheckoutService.decrementStock()
    - Met à jour Order.status à PAID
    - Met à jour le timestamp Order.paidAt
    - Envoie une notification de succès/échec au frontend

8. **La commande entre dans le flux de paiement**
    - Les transitions de statut : PENDING → PAID → PROCESSING → SHIPPED → DELIVERED
    - Le stock est maintenant définitivement décrémenté
    - Les quantités de produits sont mises à jour en base de données

## Champs Order Liés au Checkout

### Champs Monétaires

-   **totalAmount**: `DECIMAL(10,2)` - Valeur totale de la commande incluant la livraison (immuable après création)
-   **shippingCost**: `DECIMAL(10,2)` - Coût de livraison pour la méthode sélectionnée

### Champs de Livraison

-   **shippingAddress**: `ManyToOne(Address)` - Adresse de livraison du colis
-   **billingAddress**: `ManyToOne(Address)` - Adresse de facturation (peut différer de l'adresse de livraison)
-   **shippingMethod**: enum `ShippingMethod` - Option de livraison sélectionnée au checkout

### Champs de Suivi de Paiement

-   **stripeSessionId**: `string(255)` - ID de Session Checkout Stripe (pour la recherche webhook)
-   **stripePaymentIntentId**: `string(255)` - ID PaymentIntent Stripe (si applicable)
-   **paidAt**: `DATETIME_IMMUTABLE` - Horodatage du paiement réussi (défini par le gestionnaire webhook)

## Intégrité des Données & Invariants

### Modèle de Snapshot Historique

-   **OrderItem.unitPrice** : Snapshots du Product.price au moment du checkout, empêchant les changements de prix rétroactifs d'affecter l'historique des commandes
-   **OrderItem.quantity** : Verrouillée après création de la commande (impossible de modifier la quantité passée du checkout)
-   **Order.totalAmount** : Immuable après checkout (pas de recalcul basé sur les changements de produits)

### Gestion du Stock

-   Le stock est décrémenté uniquement après un paiement réussi (webhook, statut = PAID)
-   Les décréments de stock sont permanents pour les commandes livrées
-   Le processus de remboursement peut incrémenter le stock si le remboursement est approuvé

### États Terminaux de Commande

-   Une fois que Order atteint le statut CANCELLED ou REFUNDED, aucune opération supplémentaire n'est autorisée
-   Le panier est vidé après création de la commande (transition unidirectionnelle)
-   Les OrderItems persisten dans Order même si les Produits sont supprimés ou désactivés ultérieurement

## Gestion des Erreurs

### Erreurs de Validation

-   **Panier vide** : "Cannot create order from empty cart"
-   **Articles non disponibles** : "Some items in your cart are no longer available. Please review your cart."
-   **Stock insuffisant** : "Some items in your cart have insufficient stock. Please review your cart."
-   **Adresses invalides** : "Invalid address provided."

### Erreurs Stripe

-   **Création de session échouée** : Capturée et retournée comme "An unexpected error occurred..."
-   **Signature de webhook invalide** : Rejetée (prévient les mises à jour de statut non autorisées)
-   **Session non trouvée** : Webhook silencieusement ignoré (idempotent)

## Configuration

### Variables d'Environnement

-   `STRIPE_SECRET_KEY` : Clé privée pour l'API Stripe (dans .env.local)
-   `STRIPE_PUBLIC_KEY` : Clé publique pour Stripe.js sur le frontend (dans .env.local)
-   `STRIPE_WEBHOOK_SECRET` : Secret pour la vérification de la signature du webhook (dans .env.local)

### Configuration du Service (services.yaml)

```yaml
services:
    App\Service\StripePaymentService:
        arguments:
            - "%env(STRIPE_SECRET_KEY)%"
        calls:
            - method: setStripePublicKey
              arguments:
                  - "%env(STRIPE_PUBLIC_KEY)%"
```

## Entités Connexes

-   **Cart** : Entité source pour le checkout (convertie en Order)
-   **CartItem** : Articles de ligne convertis en OrderItems
-   **Order** : Entité de sortie représentant l'achat finalisé
-   **OrderItem** : Snapshot historique des articles du panier au checkout
-   **Product** : Référencé pour les informations de prix et de stock
-   **Address** : Informations d'adresse de livraison et de facturation
-   **User** : Propriétaire de la commande et du panier
-   **ShippingMethod** : Détermine le calcul des frais de livraison

## Services Connexes

-   **CheckoutService** : Logique de checkout principale
-   **CartService** : Gestion du panier (nettoyage automatique, totaux)
-   **StripePaymentService** : Intégration Stripe et création de session
-   **AddressRepository** : Validation et récupération des adresses
-   **CartRepository** : Recherche du panier par utilisateur
