# Documentation des Entités E-commerce

## Conventions générales

### Types de données financières
- **Toujours utiliser `decimal` en base**
- **Toujours utiliser `string` en PHP**
- **Jamais de `float` pour l'argent**
- Librairie recommandée: `brick/money` ou BCMath

### Timestamps
- `createdAt`: Date de création (immutable)
- `updatedAt`: Date de dernière modification (auto-update)
- Format: DateTime avec timezone UTC

### Soft Delete
- Privilégier `isActive` plutôt que suppression physique
- Permet de garder l'historique
- Respecte les contraintes RGPD

## Workflow global

### Parcours d'achat
1. User ajoute des produits → **Cart**
2. Cart items validés
3. Cart converti en **Order** (status: pending)
4. Paiement validé → Order (status: paid)
5. Préparation et expédition → Order (status: shipped)
6. Livraison → Order (status: delivered)

### Historisation des prix
⚠️ **Règle critique**: Les prix dans OrderItem sont FIGÉS
- Un produit peut changer de prix dans le temps
- Les commandes passées doivent garder le prix payé
- Aucune modification après création de l'Order

### Gestion du stock
```
Stock initial: 100
  ↓
Cart ajout: Stock = 100 (pas de réservation)
  ↓
Order création (pending): Stock = 100 (toujours pas réservé)
  ↓
Order paid: Stock = 95 (décrément de 5)
  ↓
Order cancelled: Stock = 100 (réincrémentation)
```

## Relations critiques

### User ↔ Cart (OneToOne)
- 1 user = 1 cart actif unique
- Créé automatiquement à l'inscription
- Jamais supprimé (sauf avec le user)

### Order ↔ Address (ManyToOne)
- L'adresse est COPIÉE dans Order (pas juste une référence)
- Si l'user modifie son adresse, les anciennes commandes gardent l'ancienne
- TODO: Implémenter une copie snapshot de l'adresse

## Optimisations futures

### Indexation
- `Product.slug` (unique + fréquent dans WHERE)
- `Order.orderNumber` (unique + recherche support)
- `Order.status` + `Order.createdAt` (filtres admin)

### Dénormalisation
- `Order.totalAmount`: déjà dénormalisé ✅
- TODO: `Order.itemsCount` pour éviter COUNT()

### Cache
- Liste des catégories (rarement modifiée)
- Produits populaires
- Stock produit (avec invalidation)

## Sécurité

### Données sensibles
- `User.password`: bcrypt/argon2id
- `User.email`: unique + validé
- Pas de CB stockée (utiliser Stripe/PayPal)

### Validation
- Tous les inputs utilisateur = Assert
- Prix/quantités = validations métier strictes
- Stock = vérification atomique en transaction